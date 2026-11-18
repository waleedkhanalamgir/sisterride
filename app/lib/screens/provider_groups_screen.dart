import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../state/auth_state.dart';
import '../utils.dart';

/// Provider side: matched groups waiting for a driver (Available) and the ones she accepted (My rides).
class ProviderGroupsScreen extends StatefulWidget {
  const ProviderGroupsScreen({super.key});

  @override
  State<ProviderGroupsScreen> createState() => _ProviderGroupsScreenState();
}

class _ProviderGroupsScreenState extends State<ProviderGroupsScreen> {
  List<Map<String, dynamic>> _available = [];
  List<Map<String, dynamic>> _mine = [];
  bool _loading = true;
  String? _error;
  int? _busyId;

  ApiClient get _api => context.read<AuthState>().api;

  @override
  void initState() {
    super.initState();
    _load();
  }

  List<Map<String, dynamic>> _list(dynamic data) =>
      (data as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();

  Future<void> _load() async {
    try {
      final results = await Future.wait([_api.get('/provider/groups/available'), _api.get('/provider/groups/mine')]);
      if (!mounted) return;
      setState(() {
        _available = _list(results[0]);
        _mine = _list(results[1]);
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

  Future<void> _act(int id, String action) async {
    setState(() => _busyId = id);
    try {
      await _api.post('/provider/groups/$id/$action');
      await _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      await _load(); // someone else may have taken it
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Rides'),
          actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh))],
          bottom: TabBar(tabs: [
            Tab(text: 'Available (${_available.length})'),
            Tab(text: 'My rides (${_mine.length})'),
          ]),
        ),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? Center(child: Padding(padding: const EdgeInsets.all(20), child: Text(_error!)))
                : TabBarView(children: [
                    _listView(_available, accepted: false, empty: 'No groups are waiting for a driver right now.'),
                    _listView(_mine, accepted: true, empty: 'You have not accepted any rides yet.'),
                  ]),
      ),
    );
  }

  Widget _listView(List<Map<String, dynamic>> groups, {required bool accepted, required String empty}) {
    return RefreshIndicator(
      onRefresh: _load,
      child: groups.isEmpty
          ? ListView(children: [Padding(padding: const EdgeInsets.all(32), child: Text(empty, textAlign: TextAlign.center))])
          : ListView(
              padding: const EdgeInsets.all(12),
              children: [for (final g in groups) _GroupCard(group: g, accepted: accepted, busy: _busyId == g['id'], onAct: _act)],
            ),
    );
  }
}

class _GroupCard extends StatelessWidget {
  const _GroupCard({required this.group, required this.accepted, required this.busy, required this.onAct});

  final Map<String, dynamic> group;
  final bool accepted;
  final bool busy;
  final Future<void> Function(int id, String action) onAct;

  @override
  Widget build(BuildContext context) {
    final stops = (group['stops'] as List).cast<Map>();
    final firstPickup = stops.firstWhere((s) => s['type'] == 'pickup', orElse: () => const {});
    final lastDropoff = stops.lastWhere((s) => s['type'] == 'dropoff', orElse: () => const {});
    final passengers = (group['passengers'] as List?)?.cast<Map>() ?? const [];
    final id = group['id'] as int;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(fmtDateTime(group['depart_at'] as String?), style: Theme.of(context).textTheme.titleMedium),
                Text('${group['passenger_count']} ${group['passenger_count'] == 1 ? 'woman' : 'women'}'),
              ],
            ),
            const SizedBox(height: 6),
            Text('From: ${firstPickup['address'] ?? '-'}'),
            Text('To: ${lastDropoff['address'] ?? '-'}'),
            Text('${group['route_km']} km | ${modeLabel(group['mode'] as String?)} | ${stops.length} stops'),
            const SizedBox(height: 6),
            Text('You earn Rs ${(group['driver_earning'] as num).round()}  (fare Rs ${(group['total_fare'] as num).round()})',
                style: const TextStyle(fontWeight: FontWeight.w600)),
            if (accepted && passengers.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text('Passengers: ${passengers.map((p) => p['name']).join(', ')}'),
            ],
            const SizedBox(height: 10),
            accepted
                ? OutlinedButton(onPressed: busy ? null : () => onAct(id, 'release'), child: const Text('Hand back this ride'))
                : FilledButton(onPressed: busy ? null : () => onAct(id, 'accept'), child: const Text('Accept ride')),
          ],
        ),
      ),
    );
  }
}
