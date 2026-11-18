import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../state/auth_state.dart';
import '../utils.dart';
import 'request_ride_screen.dart';
import 'ride_detail_screen.dart';

class MyRidesScreen extends StatefulWidget {
  const MyRidesScreen({super.key});

  @override
  State<MyRidesScreen> createState() => _MyRidesScreenState();
}

class _MyRidesScreenState extends State<MyRidesScreen> {
  List<Map<String, dynamic>> _rides = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await context.read<AuthState>().api.get('/rides') as List;
      if (!mounted) return;
      setState(() {
        _rides = data.map((e) => Map<String, dynamic>.from(e as Map)).toList();
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

  Future<void> _open(Widget screen) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));
    _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My rides')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _open(const RequestRideScreen()),
        icon: const Icon(Icons.add),
        label: const Text('New ride'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Padding(padding: const EdgeInsets.all(20), child: Text(_error!)))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: _rides.isEmpty
                      ? ListView(children: const [
                          Padding(
                            padding: EdgeInsets.all(32),
                            child: Text('No rides yet. Tap New ride to request one.', textAlign: TextAlign.center),
                          ),
                        ])
                      : ListView(
                          children: [
                            for (final r in _rides)
                              ListTile(
                                leading: Icon(r['mode'] == 'bike' ? Icons.two_wheeler : Icons.local_taxi),
                                title: Text('${r['pickup_address']} to ${r['dropoff_address']}'),
                                subtitle: Text('${fmtDateTime(r['depart_at'] as String?)} | ${rideStatusLabel(r)}'),
                                trailing: const Icon(Icons.chevron_right),
                                onTap: () => _open(RideDetailScreen(rideId: r['id'] as int)),
                              ),
                          ],
                        ),
                ),
    );
  }
}
