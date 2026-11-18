import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  ApiException(this.message, [this.statusCode]);

  @override
  String toString() => message;
}

/// Thin wrapper around the Laravel API: adds the token, parses JSON, turns errors into [ApiException].
class ApiClient {
  static const _tokenKey = 'sr_token';

  String? _token;
  String? get token => _token;
  bool get hasToken => _token != null;

  /// Called when the server says the token is no longer valid (HTTP 401).
  void Function()? onUnauthorized;

  Future<void> loadToken() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString(_tokenKey);
  }

  Future<void> saveToken(String? token) async {
    _token = token;
    final prefs = await SharedPreferences.getInstance();
    if (token == null) {
      await prefs.remove(_tokenKey);
    } else {
      await prefs.setString(_tokenKey, token);
    }
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        if (_token != null) 'Authorization': 'Bearer $_token',
      };

  Uri _uri(String path) => Uri.parse('${AppConfig.apiUrl}$path');

  Future<dynamic> get(String path) => _send(() => http.get(_uri(path), headers: _headers));

  Future<dynamic> post(String path, [Map<String, dynamic>? body]) => _send(() => http.post(
        _uri(path),
        headers: {..._headers, 'Content-Type': 'application/json'},
        body: jsonEncode(body ?? {}),
      ));

  Future<dynamic> delete(String path) => _send(() => http.delete(_uri(path), headers: _headers));

  /// Multipart upload: [fields] are text fields, [files] maps a field name to a file path on the phone.
  Future<dynamic> upload(String path, {required Map<String, String> fields, required Map<String, String> files}) {
    return _send(() async {
      final request = http.MultipartRequest('POST', _uri(path))
        ..headers.addAll(_headers)
        ..fields.addAll(fields);
      for (final entry in files.entries) {
        request.files.add(await http.MultipartFile.fromPath(entry.key, entry.value));
      }
      return http.Response.fromStream(await request.send());
    });
  }

  Future<dynamic> _send(Future<http.Response> Function() call) async {
    final http.Response res;
    try {
      res = await call().timeout(const Duration(seconds: 30));
    } catch (_) {
      throw ApiException('Cannot reach the server. Check your internet connection and the server address.');
    }

    dynamic data;
    try {
      data = res.body.isEmpty ? null : jsonDecode(res.body);
    } catch (_) {
      data = null;
    }

    if (res.statusCode == 401) {
      onUnauthorized?.call();
      throw ApiException('Your session has expired. Please log in again.', 401);
    }
    if (res.statusCode >= 200 && res.statusCode < 300) return data;

    var message = 'Something went wrong (${res.statusCode}).';
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        message = first is List && first.isNotEmpty ? first.first.toString() : first.toString();
      } else if (data['message'] is String) {
        message = data['message'] as String;
      }
    }
    throw ApiException(message, res.statusCode);
  }
}
