import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../state/auth_state.dart';
import '../utils.dart';

/// One ride: status, your fare share, boarding PIN, co-riders and driver. Refreshes by itself.
class RideDetailScreen extends StatefulWidget {
  const RideDetailScreen({super.key, required this.rideId});

  final int rideId;

  @override
  State<RideDetailScreen> createState() => _RideDetailScreenState();
}

class _RideDetailScreenState extends State<RideDetailScreen> {
  Map<String, dynamic>? _ride;
  String? _error;
  bool _busy = false;
  Timer? _timer;

  ApiClient get _api => context.read<AuthState>().api;

  @override
  void initState() {
    super.initState();
    _load();
    _timer = Timer.periodic(const Duration(seconds: 15), (_) => _load());
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final res = await _api.get('/rides/${widget.rideId}') as Map;
      if (mounted) {
        setState(() {
          _ride = Map<String, dynamic>.from(res);
          _error = null;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  Future<void> _cancel() async {
    final sure = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cancel this ride?'),
        content: const Text('If you are in a group, the group is re-formed without you.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Keep ride')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Cancel ride')),
        ],
      ),
    );
    if (sure != true) return;
    setState(() => _busy = true);
    try {
      await _api.post('/rides/${widget.rideId}/cancel');
      await _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final ride = _ride;
    return Scaffold(
      appBar: AppBar(title: const Text('Your ride'), actions: [
        IconButton(onPressed: _load, icon: const Icon(Icons.refresh)),
      ]),
      body: ride == null
          ? Center(child: _error != null ? Text(_error!) : const CircularProgressIndicator())
          : RefreshIndicator(onRefresh: _load, child: _body(ride)),
    );
  }

  Widget _body(Map<String, dynamic> ride) {
    final theme = Theme.of(context);
    final group = ride['group'] as Map?;
    final status = ride['status'] as String;
    final canCancel = status == 'requested' || status == 'matched';
    final pin = group?['boarding_pin'] as String?;
    final driver = group?['driver'] as Map?;
    final coRiders = (group?['co_riders'] as List?) ?? const [];

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          color: theme.colorScheme.primaryContainer,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(rideStatusLabel(ride), style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
                const SizedBox(height: 4),
                Text(_hint(ride)),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        ListTile(
          leading: const Icon(Icons.trip_origin, color: Colors.green),
          title: Text('${ride['pickup_address']}'),
          subtitle: const Text('Pickup'),
        ),
        ListTile(
          leading: const Icon(Icons.flag, color: Colors.red),
          title: Text('${ride['dropoff_address']}'),
          subtitle: Text('Drop-off | ${ride['distance_km']} km'),
        ),
        ListTile(
          leading: const Icon(Icons.schedule),
          title: Text(fmtDateTime(ride['depart_at'] as String?)),
          subtitle: Text(modeLabel(ride['mode'] as String?)),
        ),
        if (group != null) ...[
          const Divider(),
          ListTile(
            leading: const Icon(Icons.payments),
            title: Text('Your share: Rs ${_money(group['my_fare_share'])}'),
            subtitle: Text('Total fare Rs ${_money(group['total_fare'])} split by distance | pickup stop ${group['pickup_order']}'),
          ),
          if (pin != null)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    const Text('Boarding PIN'),
                    Text(pin, style: theme.textTheme.displaySmall?.copyWith(letterSpacing: 8, fontWeight: FontWeight.bold)),
                    const Text('Show this to your driver before you get in.', style: TextStyle(fontSize: 12)),
                  ],
                ),
              ),
            ),
          if (driver != null)
            ListTile(
              leading: const Icon(Icons.directions_car),
              title: Text('${driver['name']} | ${driver['vehicle_type']} ${driver['plate_number']}'),
              subtitle: Text('${driver['make_model'] ?? ''}  Trust ${driver['trust_score']}'),
            ),
          if (coRiders.isNotEmpty) ...[
            const Padding(
              padding: EdgeInsets.only(top: 8, left: 16),
              child: Text('Riding with', style: TextStyle(fontWeight: FontWeight.w600)),
            ),
            for (final c in coRiders)
              ListTile(
                leading: const CircleAvatar(child: Icon(Icons.person)),
                title: Text('${(c as Map)['name']}'),
                subtitle: Text('Verified | Trust ${c['trust_score']}'),
              ),
          ],
        ],
        if (canCancel) ...[
          const SizedBox(height: 16),
          OutlinedButton(onPressed: _busy ? null : _cancel, child: const Text('Cancel ride')),
        ],
      ],
    );
  }

  String _hint(Map ride) {
    final group = ride['group'] as Map?;
    switch (ride['status']) {
      case 'requested':
        return ride['mode'] == 'bike'
            ? 'We are looking for a verified rider. This page updates by itself.'
            : 'We match you with 2 to 4 verified women going your way within 15 minutes of your time. This page updates by itself.';
      case 'matched':
        if (group?['status'] == 'driver_accepted') return 'Be ready at your pickup point. Show your PIN to the driver.';
        return 'Your group is ready. A driver has not accepted yet.';
      case 'no_match':
        return 'No group formed before your departure time. Try a different time or request again.';
      default:
        return '';
    }
  }

  String _money(dynamic v) => v is num ? v.round().toString() : '$v';
}
