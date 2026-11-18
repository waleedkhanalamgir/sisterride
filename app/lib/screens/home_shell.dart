import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/auth_state.dart';
import 'contacts_screen.dart';
import 'my_rides_screen.dart';
import 'passenger_home.dart';
import 'profile_screen.dart';
import 'provider_groups_screen.dart';
import 'provider_home.dart';

/// The main screen after login. Tabs depend on which side of the app the user is on.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;
  String? _lastRole;

  @override
  Widget build(BuildContext context) {
    final role = context.select<AuthState, String>((a) => a.activeRole);

    // Start from the first tab again when the user switches sides.
    if (_lastRole != null && _lastRole != role) _index = 0;
    _lastRole = role;

    final isProvider = role == 'provider';
    final pages = isProvider
        ? const [ProviderHome(), ProviderGroupsScreen(), ProfileScreen()]
        : const [PassengerHome(), MyRidesScreen(), ContactsScreen(), ProfileScreen()];
    final destinations = isProvider
        ? const [
            NavigationDestination(icon: Icon(Icons.two_wheeler), label: 'Drive'),
            NavigationDestination(icon: Icon(Icons.list_alt), label: 'Rides'),
            NavigationDestination(icon: Icon(Icons.person), label: 'Profile'),
          ]
        : const [
            NavigationDestination(icon: Icon(Icons.home), label: 'Home'),
            NavigationDestination(icon: Icon(Icons.route), label: 'Rides'),
            NavigationDestination(icon: Icon(Icons.shield), label: 'Trusted'),
            NavigationDestination(icon: Icon(Icons.person), label: 'Profile'),
          ];

    if (_index >= pages.length) _index = 0;

    return Scaffold(
      body: SafeArea(child: pages[_index]),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: destinations,
      ),
    );
  }
}
