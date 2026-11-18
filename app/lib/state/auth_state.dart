import 'package:flutter/foundation.dart';

import '../services/api_client.dart';

/// Holds the logged-in user and decides which screens the app shows.
class AuthState extends ChangeNotifier {
  AuthState(this.api) {
    api.onUnauthorized = _forceLogout;
  }

  final ApiClient api;

  bool ready = false; // finished checking for a saved login
  Map<String, dynamic>? user;

  bool get loggedIn => user != null;

  String get role => (user?['role'] ?? 'passenger') as String;
  String get activeRole => (user?['active_role'] ?? role) as String;
  String get status => (user?['verification_status'] ?? 'unsubmitted') as String;
  bool get isProvider => role == 'provider';
  bool get isAdmin => role == 'admin';
  bool get hasVehicle => user?['vehicle'] != null;
  String? get rejectionReason => (user?['verification'] as Map?)?['rejection_reason'] as String?;

  /// Which step the user is on: 'verify', 'vehicle', 'pending' or 'home'.
  String get stage {
    if (status == 'unsubmitted' || status == 'rejected') return 'verify';
    if (isProvider && !hasVehicle) return 'vehicle';
    if (status == 'pending') return 'pending';
    return 'home';
  }

  Future<void> start() async {
    await api.loadToken();
    if (api.hasToken) {
      try {
        await refresh();
      } on ApiException catch (e) {
        // Keep the saved login if the server is only unreachable; drop it if rejected.
        if (e.statusCode != null) await _forceLogout();
      }
    }
    ready = true;
    notifyListeners();
  }

  Future<void> refresh() async {
    user = Map<String, dynamic>.from(await api.get('/me') as Map);
    notifyListeners();
  }

  Future<void> login(String login, String password) async {
    final data = await api.post('/login', {'login': login, 'password': password}) as Map;
    await api.saveToken(data['token'] as String);
    await refresh();
  }

  Future<void> register(Map<String, dynamic> body) async {
    final data = await api.post('/register', body) as Map;
    await api.saveToken(data['token'] as String);
    await refresh();
  }

  Future<void> switchRole(String activeRole) async {
    await api.post('/role/switch', {'active_role': activeRole});
    await refresh();
  }

  Future<void> logout() async {
    try {
      await api.post('/logout');
    } catch (_) {
      // Logging out locally is enough if the server cannot be reached.
    }
    await _forceLogout();
  }

  Future<void> _forceLogout() async {
    await api.saveToken(null);
    user = null;
    notifyListeners();
  }
}
