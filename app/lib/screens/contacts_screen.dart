import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../state/auth_state.dart';
import 'register_screen.dart' show kPhonePattern;

/// Up to 3 trusted contacts who will receive a live trip link (sending comes with the safety step).
class ContactsScreen extends StatefulWidget {
  const ContactsScreen({super.key});

  @override
  State<ContactsScreen> createState() => _ContactsScreenState();
}

class _ContactsScreenState extends State<ContactsScreen> {
  List<Map<String, dynamic>> _contacts = [];
  bool _loading = true;
  String? _error;

  ApiClient get _api => context.read<AuthState>().api;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await _api.get('/contacts') as List;
      if (!mounted) return;
      setState(() {
        _contacts = data.map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _error = null;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _error = e.message;
          _loading = false;
        });
      }
    }
  }

  Future<void> _add() async {
    final name = TextEditingController();
    final phone = TextEditingController();
    final relation = TextEditingController();
    final form = GlobalKey<FormState>();

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Add trusted contact'),
        content: Form(
          key: form,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: name,
                decoration: const InputDecoration(labelText: 'Name'),
                validator: (v) => (v == null || v.trim().isEmpty) ? 'Enter a name' : null,
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: phone,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(labelText: 'Mobile number'),
                validator: (v) => kPhonePattern.hasMatch((v ?? '').trim()) ? null : 'Use a number like 03001234567',
              ),
              const SizedBox(height: 8),
              TextFormField(controller: relation, decoration: const InputDecoration(labelText: 'Relation (optional)')),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(minimumSize: const Size(96, 40)),
            onPressed: () {
              if (form.currentState!.validate()) Navigator.pop(ctx, true);
            },
            child: const Text('Add'),
          ),
        ],
      ),
    );

    final body = {
      'name': name.text.trim(),
      'phone': phone.text.trim(),
      if (relation.text.trim().isNotEmpty) 'relation': relation.text.trim(),
    };
    name.dispose();
    phone.dispose();
    relation.dispose();
    if (ok != true) return;

    try {
      await _api.post('/contacts', body);
      await _load();
    } on ApiException catch (e) {
      _toast(e.message);
    }
  }

  Future<void> _remove(Map<String, dynamic> c) async {
    try {
      await _api.delete('/contacts/${c['id']}');
      await _load();
    } on ApiException catch (e) {
      _toast(e.message);
    }
  }

  void _toast(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Trusted contacts')),
      floatingActionButton:
          _contacts.length < 3 ? FloatingActionButton.extended(onPressed: _add, icon: const Icon(Icons.add), label: const Text('Add')) : null,
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Padding(padding: const EdgeInsets.all(20), child: Text(_error!)))
              : _contacts.isEmpty
                  ? const Center(
                      child: Padding(
                        padding: EdgeInsets.all(24),
                        child: Text(
                          'Add up to 3 people who should get your live trip link when you ride.',
                          textAlign: TextAlign.center,
                        ),
                      ),
                    )
                  : ListView(
                      children: [
                        for (final c in _contacts)
                          ListTile(
                            leading: const CircleAvatar(child: Icon(Icons.person)),
                            title: Text('${c['name']}'),
                            subtitle: Text('${c['phone']}${c['relation'] != null ? ' | ${c['relation']}' : ''}'),
                            trailing: IconButton(icon: const Icon(Icons.delete_outline), onPressed: () => _remove(c)),
                          ),
                      ],
                    ),
    );
  }
}
