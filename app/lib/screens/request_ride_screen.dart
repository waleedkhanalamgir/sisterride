import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../state/auth_state.dart';
import '../theme.dart';
import '../utils.dart';
import 'ride_detail_screen.dart';

/// Pick pickup and drop-off on the map, choose a time, and request a ride.
class RequestRideScreen extends StatefulWidget {
  const RequestRideScreen({super.key});

  @override
  State<RequestRideScreen> createState() => _RequestRideScreenState();
}

class _RequestRideScreenState extends State<RequestRideScreen> {
  // Map starts on Lahore. Change this to your own city.
  static const _startCenter = LatLng(31.5204, 74.3587);

  final _pickupText = TextEditingController();
  final _dropoffText = TextEditingController();

  LatLng? _pickup;
  LatLng? _dropoff;
  String _picking = 'pickup'; // which point the next map tap sets
  String _mode = 'car_rickshaw';
  late DateTime _departAt = DateTime.now().add(const Duration(minutes: 30));
  final Set<int> _days = {}; // 1 = Monday ... 7 = Sunday
  Map<String, dynamic>? _preview;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _pickupText.dispose();
    _dropoffText.dispose();
    super.dispose();
  }

  void _onTap(LatLng point) {
    setState(() {
      if (_picking == 'pickup') {
        _pickup = point;
        _picking = 'dropoff';
      } else {
        _dropoff = point;
      }
      _preview = null;
    });
    _loadPreview();
  }

  Map<String, dynamic> get _places => {
        'pickup_lat': _pickup!.latitude,
        'pickup_lng': _pickup!.longitude,
        'dropoff_lat': _dropoff!.latitude,
        'dropoff_lng': _dropoff!.longitude,
      };

  Future<void> _loadPreview() async {
    if (_pickup == null || _dropoff == null) return;
    try {
      final res = await context.read<AuthState>().api.post('/rides/preview', {..._places, 'mode': _mode}) as Map;
      if (mounted) setState(() => _preview = Map<String, dynamic>.from(res));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  Future<void> _pickDateTime() async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: _departAt,
      firstDate: now,
      lastDate: now.add(const Duration(days: 7)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(context: context, initialTime: TimeOfDay.fromDateTime(_departAt));
    if (time == null || !mounted) return;
    setState(() => _departAt = DateTime(date.year, date.month, date.day, time.hour, time.minute));
  }

  Future<void> _submit() async {
    if (_pickup == null || _dropoff == null) {
      setState(() => _error = 'Tap the map to set your pickup, then your drop-off.');
      return;
    }
    if (!_departAt.isAfter(DateTime.now())) {
      setState(() => _error = 'Choose a time in the future.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final res = await context.read<AuthState>().api.post('/rides', {
        ..._places,
        'mode': _mode,
        'pickup_address': _pickupText.text.trim().isEmpty ? 'Pinned pickup' : _pickupText.text.trim(),
        'dropoff_address': _dropoffText.text.trim().isEmpty ? 'Pinned drop-off' : _dropoffText.text.trim(),
        // Sent in UTC so the server and phone agree on the time.
        'depart_at': _departAt.toUtc().toIso8601String(),
        if (_days.isNotEmpty) 'recurrence_days': (_days.toList()..sort()),
      }) as Map;
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => RideDetailScreen(rideId: res['id'] as int)),
      );
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    const dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    return Scaffold(
      appBar: AppBar(title: const Text('Request a ride')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'bike', label: Text('Bike'), icon: Icon(Icons.two_wheeler)),
              ButtonSegment(value: 'car_rickshaw', label: Text('Car / Rickshaw'), icon: Icon(Icons.local_taxi)),
            ],
            selected: {_mode},
            onSelectionChanged: (s) {
              setState(() {
                _mode = s.first;
                _preview = null;
              });
              _loadPreview();
            },
          ),
          const SizedBox(height: 12),
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'pickup', label: Text('Set pickup'), icon: Icon(Icons.trip_origin)),
              ButtonSegment(value: 'dropoff', label: Text('Set drop-off'), icon: Icon(Icons.flag)),
            ],
            selected: {_picking},
            onSelectionChanged: (s) => setState(() => _picking = s.first),
          ),
          const SizedBox(height: 8),
          SizedBox(
            height: 300,
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: FlutterMap(
                options: MapOptions(
                  initialCenter: _startCenter,
                  initialZoom: 12,
                  onTap: (tapPosition, point) => _onTap(point),
                ),
                children: [
                  TileLayer(
                    urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                    userAgentPackageName: 'com.example.sisterride',
                  ),
                  if (_pickup != null && _dropoff != null)
                    PolylineLayer(polylines: [Polyline(points: [_pickup!, _dropoff!], strokeWidth: 3, color: kPink)]),
                  MarkerLayer(markers: [
                    if (_pickup != null)
                      Marker(
                        point: _pickup!,
                        width: 40,
                        height: 40,
                        child: const Icon(Icons.location_on, color: Colors.green, size: 36),
                      ),
                    if (_dropoff != null)
                      Marker(
                        point: _dropoff!,
                        width: 40,
                        height: 40,
                        child: const Icon(Icons.location_on, color: Colors.red, size: 36),
                      ),
                  ]),
                ],
              ),
            ),
          ),
          const Padding(
            padding: EdgeInsets.only(top: 4),
            child: Text('Tap the map to place the green pickup pin, then the red drop-off pin. Map data from OpenStreetMap.',
                style: TextStyle(fontSize: 12)),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _pickupText,
            decoration: const InputDecoration(labelText: 'Pickup name (optional)', hintText: 'e.g. Johar Town, Block C'),
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _dropoffText,
            decoration: const InputDecoration(labelText: 'Drop-off name (optional)', hintText: 'e.g. University gate'),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: _pickDateTime,
            icon: const Icon(Icons.schedule),
            label: Text('Leave at ${fmtDateTime(_departAt.toIso8601String())}'),
          ),
          const SizedBox(height: 8),
          const Text('Repeat on (optional)', style: TextStyle(fontSize: 12)),
          Wrap(
            spacing: 6,
            children: [
              for (var i = 0; i < 7; i++)
                FilterChip(
                  label: Text(dayNames[i]),
                  selected: _days.contains(i + 1),
                  onSelected: (on) => setState(() => on ? _days.add(i + 1) : _days.remove(i + 1)),
                ),
            ],
          ),
          if (_days.isNotEmpty)
            const Text('Repeat days are saved. Automatic daily booking arrives in a later step.', style: TextStyle(fontSize: 12)),
          if (_preview != null) _PreviewCard(preview: _preview!, mode: _mode),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: TextStyle(color: theme.colorScheme.error)),
          ],
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _busy ? null : _submit,
            child: _busy
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Request ride'),
          ),
        ],
      ),
    );
  }
}

class _PreviewCard extends StatelessWidget {
  const _PreviewCard({required this.preview, required this.mode});

  final Map<String, dynamic> preview;
  final String mode;

  @override
  Widget build(BuildContext context) {
    final per = Map<String, dynamic>.from(preview['per_person_by_group_size'] as Map);
    final keys = per.keys.toList()..sort();
    return Card(
      margin: const EdgeInsets.only(top: 12),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('About ${preview['distance_km']} km', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 6),
            for (final k in keys)
              Text(k == '1' ? (mode == 'bike' ? 'Your fare: Rs ${per[k]}' : 'Riding alone: Rs ${per[k]}') : 'Shared by $k women: about Rs ${per[k]} each'),
            const SizedBox(height: 4),
            const Text('Estimate only. Your exact share is fixed when your group is formed.', style: TextStyle(fontSize: 12)),
          ],
        ),
      ),
    );
  }
}
