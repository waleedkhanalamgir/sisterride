import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../state/auth_state.dart';
import '../widgets/photo_field.dart';

/// Step 2 for providers: vehicle details and documents.
class VehicleScreen extends StatefulWidget {
  const VehicleScreen({super.key});

  @override
  State<VehicleScreen> createState() => _VehicleScreenState();
}

class _VehicleScreenState extends State<VehicleScreen> {
  final _form = GlobalKey<FormState>();
  final _plate = TextEditingController();
  final _model = TextEditingController();
  String? _type;
  String? _license;
  String? _vehiclePhoto;
  String? _registration;
  bool _busy = false;
  String? _error;

  // Vehicle types allowed for each provider type (same rule as the server).
  static const _typesByProvider = {
    'bike_rider': {'bike': 'Bike', 'scooty': 'Scooty'},
    'car_driver': {'car': 'Car'},
    'rickshaw_driver': {'rickshaw': 'Rickshaw'},
  };

  Map<String, String> get _allowed {
    final provider = context.read<AuthState>().user?['provider_type'] as String?;
    return _typesByProvider[provider] ?? const {};
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final allowed = _allowed;
      if (allowed.isNotEmpty && mounted) setState(() => _type = allowed.keys.first);
    });
  }

  @override
  void dispose() {
    _plate.dispose();
    _model.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    if (_type == null || _license == null || _vehiclePhoto == null) {
      setState(() => _error = 'Add your license photo and a photo of the vehicle.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    final auth = context.read<AuthState>();
    try {
      await auth.api.upload(
        '/vehicle',
        fields: {
          'type': _type!,
          'plate_number': _plate.text.trim(),
          if (_model.text.trim().isNotEmpty) 'make_model': _model.text.trim(),
        },
        files: {
          'license_image': _license!,
          'photo': _vehiclePhoto!,
          if (_registration != null) 'registration_image': _registration!,
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
    final allowed = _allowed;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Your vehicle'),
        actions: [TextButton(onPressed: context.read<AuthState>().logout, child: const Text('Log out'))],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: _form,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (allowed.length > 1)
                  Wrap(
                    spacing: 8,
                    children: [
                      for (final e in allowed.entries)
                        ChoiceChip(
                          label: Text(e.value),
                          selected: _type == e.key,
                          onSelected: (_) => setState(() => _type = e.key),
                        ),
                    ],
                  ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _plate,
                  textCapitalization: TextCapitalization.characters,
                  decoration: const InputDecoration(labelText: 'Number plate', hintText: 'LEA-1234'),
                  validator: (v) => (v == null || v.trim().length < 4) ? 'Enter the number plate' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _model,
                  decoration: const InputDecoration(labelText: 'Make and model (optional)'),
                ),
                const SizedBox(height: 16),
                PhotoField(label: 'Driving license', path: _license, onPicked: (p) => setState(() => _license = p)),
                const SizedBox(height: 12),
                PhotoField(
                    label: 'Photo of the vehicle', path: _vehiclePhoto, onPicked: (p) => setState(() => _vehiclePhoto = p)),
                const SizedBox(height: 12),
                PhotoField(
                    label: 'Registration book',
                    path: _registration,
                    optional: true,
                    onPicked: (p) => setState(() => _registration = p)),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                ],
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Text('Save vehicle'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
