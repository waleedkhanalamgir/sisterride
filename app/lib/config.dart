/// Where the Laravel backend runs.
///
/// - Android emulator:   http://10.0.2.2:8000   (10.0.2.2 is the emulator's name for your computer)
/// - iOS simulator:      http://127.0.0.1:8000
/// - Real phone on Wi-Fi: http://<your-computer-LAN-IP>:8000  (start the server with
///   `php artisan serve --host=0.0.0.0`)
///
/// You can also override it without editing code:
///   flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000
class AppConfig {
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000',
  );

  static String get apiUrl => '$baseUrl/api';
}
