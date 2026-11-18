import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'screens/pending_screen.dart';
import 'screens/vehicle_screen.dart';
import 'screens/verification_screen.dart';
import 'services/api_client.dart';
import 'state/auth_state.dart';
import 'theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final api = ApiClient();
  runApp(
    MultiProvider(
      providers: [
        Provider<ApiClient>.value(value: api),
        ChangeNotifierProvider<AuthState>(create: (_) => AuthState(api)..start()),
      ],
      child: const SisterRideApp(),
    ),
  );
}

class SisterRideApp extends StatelessWidget {
  const SisterRideApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SisterRide',
      theme: buildTheme(),
      debugShowCheckedModeBanner: false,
      home: const AuthGate(),
    );
  }
}

/// One app, many screens: the user's login, role and verification status pick the screen.
class AuthGate extends StatelessWidget {
  const AuthGate({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthState>();

    if (!auth.ready) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (!auth.loggedIn) return const LoginScreen();

    if (auth.isAdmin) {
      return Scaffold(
        appBar: AppBar(title: const Text('SisterRide')),
        body: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Text(
                'Admins use the web panel at /admin on the server.',
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              FilledButton(onPressed: auth.logout, child: const Text('Log out')),
            ],
          ),
        ),
      );
    }

    switch (auth.stage) {
      case 'verify':
        return const VerificationScreen();
      case 'vehicle':
        return const VehicleScreen();
      case 'pending':
        return const PendingScreen();
      default:
        return const HomeShell();
    }
  }
}
