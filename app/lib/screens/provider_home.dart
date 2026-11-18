import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/auth_state.dart';

class ProviderHome extends StatelessWidget {
  const ProviderHome({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthState>();
    final user = auth.user ?? {};
    final vehicle = user['vehicle'] as Map?;
    final theme = Theme.of(context);

    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        Text('Hello, ${((user['name'] ?? '') as String).split(' ').first}',
            style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold)),
        const Text('Provider side'),
        const SizedBox(height: 16),
        Card(
          child: ListTile(
            leading: const Icon(Icons.verified_user),
            title: const Text('Verification'),
            subtitle: Text(auth.status),
          ),
        ),
        if (vehicle != null)
          Card(
            child: ListTile(
              leading: const Icon(Icons.directions_car),
              title: Text('${vehicle['type']} - ${vehicle['plate_number']}'),
              subtitle: Text('${vehicle['make_model'] ?? 'No model added'} | ${vehicle['status']}'),
            ),
          ),
        const SizedBox(height: 8),
        const Card(
          child: ListTile(
            leading: Icon(Icons.notifications_active),
            title: Text('Ride groups'),
            subtitle: Text('Open the Rides tab to see matched groups waiting for a driver.'),
          ),
        ),
      ],
    );
  }
}
