import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../state/auth_state.dart';
import '../widgets/photo_field.dart';

/// Step 1 for everyone: CNIC number, CNIC photo and a live selfie. An admin approves it.
class VerificationScreen extends StatefulWidget {
  const VerificationScreen({super.key});

  @override
  State<VerificationScreen> createState() => _VerificationScreenState();
}

class _VerificationScreenState extends State<VerificationScreen> {
  final _form = GlobalKey<FormState>();
  final _cnic = TextEditingController();
  String? _cnicPhoto;
  String? _selfie;
  String? _studentId;
  bool _busy = false;
  String? _error;

  static final _cnicPattern = RegExp(r'^\d{5}-\d{7}-\d$');

  @override
  void dispose() {
    _cnic.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    if (_cnicPhoto == null || _selfie == null) {
      setState(() => _error = 'Please add your CNIC photo and a selfie.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    final auth = context.read<AuthState>();
    try {
      await auth.api.upload(
        '/verification',
        fields: {'cnic_number': _cnic.text.trim()},
        files: {
          'cnic_image': _cnicPhoto!,
          'selfie': _selfie!,
          if (_studentId != null) 'student_id_image': _studentId!,
        },
      );
      await auth.refresh();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthState>();
    return Scaffold(
      appBar: AppBar(
        title: const Text('Verify your identity'),
        actions: [TextButton(onPressed: auth.logout, child: const Text('Log out'))],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: _form,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (auth.status == 'rejected')
                  Card(
                    color: Theme.of(context).colorScheme.errorContainer,
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Text('Your last submission was rejected: ${auth.rejectionReason ?? 'please try again'}'),
                    ),
                  ),
                const Text(
                  'Every SisterRide member is verified. Your documents are stored privately and only reviewed by our team.',
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _cnic,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'CNIC number', hintText: '35202-1234567-8'),
                  validator: (v) => _cnicPattern.hasMatch((v ?? '').trim()) ? null : 'Format: 12345-1234567-1',
                ),
                const SizedBox(height: 16),
                PhotoField(label: 'CNIC photo (front)', path: _cnicPhoto, onPicked: (p) => setState(() => _cnicPhoto = p)),
                const SizedBox(height: 12),
                PhotoField(
                    label: 'Live selfie', path: _selfie, camera: true, onPicked: (p) => setState(() => _selfie = p)),
                const SizedBox(height: 12),
                PhotoField(
                    label: 'Student ID',
                    path: _studentId,
                    optional: true,
                    onPicked: (p) => setState(() => _studentId = p)),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                ],
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Text('Submit for review'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
