import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../state/auth_state.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  Future<void> _switch(BuildContext context, String role) async {
    final messenger = ScaffoldMessenger.of(context);
    try {
      await context.read<AuthState>().switchRole(role);
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthState>();
    final user = auth.user ?? {};
    final canSwitch = auth.isProvider && auth.status == 'approved';

    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        const Center(child: CircleAvatar(radius: 36, child: Icon(Icons.person, size: 40))),
        const SizedBox(height: 12),
        Center(child: Text('${user['name'] ?? ''}', style: Theme.of(context).textTheme.titleLarge)),
        Center(child: Text('${user['phone'] ?? ''}')),
        const SizedBox(height: 16),
        Card(
          child: Column(
            children: [
              ListTile(
                leading: const Icon(Icons.verified_user),
                title: const Text('Verification'),
                trailing: Text(auth.status),
              ),
              ListTile(
                leading: const Icon(Icons.star),
                title: const Text('Trust score'),
                trailing: Text('${user['trust_score'] ?? '-'}'),
              ),
              ListTile(
                leading: const Icon(Icons.account_balance_wallet),
                title: const Text('Wallet balance'),
                trailing: Text('Rs ${(user['wallet'] as Map?)?['balance'] ?? '0.00'}'),
              ),
            ],
          ),
        ),
        if (canSwitch) ...[
          const SizedBox(height: 16),
          const Text('Use the app as', style: TextStyle(fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'passenger', label: Text('Passenger'), icon: Icon(Icons.hail)),
              ButtonSegment(value: 'provider', label: Text('Provider'), icon: Icon(Icons.two_wheeler)),
            ],
            selected: {auth.activeRole},
            onSelectionChanged: (s) => _switch(context, s.first),
          ),
        ],
        const SizedBox(height: 24),
        OutlinedButton.icon(
          onPressed: auth.logout,
          icon: const Icon(Icons.logout),
          label: const Text('Log out'),
        ),
      ],
    );
  }
}
