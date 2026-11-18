import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../state/auth_state.dart';

final RegExp kPhonePattern = RegExp(r'^(\+92|0)3\d{9}$');

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();

  String _role = 'passenger'; // passenger | provider
  String _providerType = 'bike_rider'; // bike_rider | car_driver | rickshaw_driver
  String _gender = 'female';
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    for (final c in [_name, _phone, _email, _password, _confirm]) {
      c.dispose();
    }
    super.dispose();
  }

  // Safety rule from the pitch deck: passengers and bike riders are always women.
  bool get _genderLocked => _role == 'passenger' || _providerType == 'bike_rider';

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final body = <String, dynamic>{
      'name': _name.text.trim(),
      'phone': _phone.text.trim(),
      if (_email.text.trim().isNotEmpty) 'email': _email.text.trim(),
      'password': _password.text,
      'password_confirmation': _confirm.text,
      'gender': _genderLocked ? 'female' : _gender,
      'role': _role,
      if (_role == 'provider') 'provider_type': _providerType,
    };
    try {
      await context.read<AuthState>().register(body);
      if (mounted) Navigator.of(context).popUntil((route) => route.isFirst);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Create account')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: _form,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text('I want to', style: TextStyle(fontWeight: FontWeight.w600)),
                const SizedBox(height: 8),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'passenger', label: Text('Ride'), icon: Icon(Icons.hail)),
                    ButtonSegment(value: 'provider', label: Text('Drive / Ride'), icon: Icon(Icons.two_wheeler)),
                  ],
                  selected: {_role},
                  onSelectionChanged: (s) => setState(() => _role = s.first),
                ),
                if (_role == 'provider') ...[
                  const SizedBox(height: 16),
                  const Text('I use a', style: TextStyle(fontWeight: FontWeight.w600)),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    children: [
                      for (final t in const {
                        'bike_rider': 'Bike / Scooty',
                        'car_driver': 'Car',
                        'rickshaw_driver': 'Rickshaw',
                      }.entries)
                        ChoiceChip(
                          label: Text(t.value),
                          selected: _providerType == t.key,
                          onSelected: (_) => setState(() => _providerType = t.key),
                        ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Bike and scooty riders must be women. Car and rickshaw drivers can be men or women.',
                    style: TextStyle(fontSize: 12),
                  ),
                  if (!_genderLocked) ...[
                    const SizedBox(height: 12),
                    SegmentedButton<String>(
                      segments: const [
                        ButtonSegment(value: 'female', label: Text('Female')),
                        ButtonSegment(value: 'male', label: Text('Male')),
                      ],
                      selected: {_gender},
                      onSelectionChanged: (s) => setState(() => _gender = s.first),
                    ),
                  ],
                ],
                const SizedBox(height: 20),
                TextFormField(
                  controller: _name,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(labelText: 'Full name'),
                  validator: (v) => (v == null || v.trim().length < 3) ? 'Enter your full name' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _phone,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(labelText: 'Mobile number', hintText: '03XXXXXXXXX'),
                  validator: (v) => kPhonePattern.hasMatch((v ?? '').trim()) ? null : 'Use a Pakistani mobile number like 03001234567',
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(labelText: 'Email (optional)'),
                  validator: (v) {
                    final t = (v ?? '').trim();
                    return t.isEmpty || t.contains('@') ? null : 'Enter a valid email';
                  },
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _password,
                  obscureText: true,
                  decoration: const InputDecoration(labelText: 'Password (min 8 characters)'),
                  validator: (v) => (v == null || v.length < 8) ? 'At least 8 characters' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _confirm,
                  obscureText: true,
                  decoration: const InputDecoration(labelText: 'Confirm password'),
                  validator: (v) => v == _password.text ? null : 'Passwords do not match',
                ),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                ],
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Text('Create account'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
