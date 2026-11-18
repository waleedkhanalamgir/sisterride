import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../state/auth_state.dart';
import 'request_ride_screen.dart';

class PassengerHome extends StatefulWidget {
  const PassengerHome({super.key});

  @override
  State<PassengerHome> createState() => _PassengerHomeState();
}

class _PassengerHomeState extends State<PassengerHome> {
  String _mode = 'car_rickshaw';
  final _km = TextEditingController(text: '9');
  int _women = 3;
  bool _busy = false;
  String? _error;
  Map<String, dynamic>? _quote;

  @override
  void dispose() {
    _km.dispose();
    super.dispose();
  }

  /// Example group: the first woman rides the whole route, each next one rides a shorter part.
  /// 3 women on a 9 km route gives 9 km, 6 km and 3 km (the example from the pitch deck).
  List<Map<String, dynamic>> _passengers(double routeKm) => [
        for (var i = 0; i < _women; i++)
          {'id': 'p${i + 1}', 'distance_km': double.parse((routeKm * (_women - i) / _women).toStringAsFixed(2))},
      ];

  Future<void> _estimate() async {
    final km = double.tryParse(_km.text.trim());
    if (km == null || km < 0.5 || km > 100) {
      setState(() => _error = 'Enter a distance between 0.5 and 100 km.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final res = await context.read<AuthState>().api.post('/fare/quote', {
        'mode': _mode,
        'route_km': km,
        'passengers': _passengers(km),
      }) as Map;
      setState(() => _quote = Map<String, dynamic>.from(res));
    } on ApiException catch (e) {
      setState(() {
        _quote = null;
        _error = e.message;
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final name = (context.read<AuthState>().user?['name'] ?? '') as String;
    final first = name.split(' ').first;
    final theme = Theme.of(context);

    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        Text('Hello, $first', style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold)),
        const Text('Where are you headed today?'),
        const SizedBox(height: 16),
        Card(
          color: theme.colorScheme.primaryContainer,
          child: ListTile(
            leading: const Icon(Icons.route),
            title: const Text('Request a shared ride'),
            subtitle: const Text('Pick your route and time. We match you with verified women going your way.'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const RequestRideScreen())),
          ),
        ),
        const SizedBox(height: 16),
        Text('Fare estimator', style: theme.textTheme.titleMedium),
        const Text('See how a shared fare is split. Uses the live rate cards from the server.',
            style: TextStyle(fontSize: 12)),
        const SizedBox(height: 12),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'bike', label: Text('Bike'), icon: Icon(Icons.two_wheeler)),
            ButtonSegment(value: 'car_rickshaw', label: Text('Car / Rickshaw'), icon: Icon(Icons.local_taxi)),
          ],
          selected: {_mode},
          onSelectionChanged: (s) => setState(() {
            _mode = s.first;
            if (_mode == 'bike') _women = 1; // bike mode carries one passenger
          }),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _km,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: const InputDecoration(labelText: 'Route distance (km)'),
        ),
        const SizedBox(height: 12),
        if (_mode == 'car_rickshaw') ...[
          Text('Women sharing: $_women'),
          Slider(
            value: _women.toDouble(),
            min: 1,
            max: 4,
            divisions: 3,
            label: '$_women',
            onChanged: (v) => setState(() => _women = v.round()),
          ),
        ],
        FilledButton(
          onPressed: _busy ? null : _estimate,
          child: _busy
              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
              : const Text('Estimate fare'),
        ),
        if (_error != null) ...[
          const SizedBox(height: 12),
          Text(_error!, style: TextStyle(color: theme.colorScheme.error)),
        ],
        if (_quote != null) _QuoteCard(quote: _quote!),
      ],
    );
  }
}

class _QuoteCard extends StatelessWidget {
  const _QuoteCard({required this.quote});

  final Map<String, dynamic> quote;

  @override
  Widget build(BuildContext context) {
    final shares = Map<String, dynamic>.from(quote['shares'] as Map);
    final keys = shares.keys.toList()..sort();
    return Card(
      margin: const EdgeInsets.only(top: 16),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Total fare: Rs ${quote['total_fare']}', style: Theme.of(context).textTheme.titleMedium),
            const Divider(),
            for (var i = 0; i < keys.length; i++)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 2),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [Text('Woman ${i + 1} pays'), Text('Rs ${shares[keys[i]]}')],
                ),
              ),
            const Divider(),
            Text('Platform commission (${quote['commission_pct']}%): Rs ${quote['commission']}',
                style: const TextStyle(fontSize: 12)),
            Text('Driver earns: Rs ${quote['driver_earning']}', style: const TextStyle(fontSize: 12)),
          ],
        ),
      ),
    );
  }
}
