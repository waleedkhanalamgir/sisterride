# SisterRide Flutter app (Step 2)

One app for everyone. After login, the screen depends on who you are:

| User state | Screen |
|---|---|
| Not logged in | Login / Create account |
| Verification not sent, or rejected | Verify identity (CNIC number, CNIC photo, live selfie, optional student ID) |
| Provider with no vehicle yet | Vehicle details and documents |
| Waiting for admin | "Review in progress" with a Check status button |
| Approved passenger | Home (request a ride, fare estimator), **Rides** (my rides and detail), Trusted contacts, Profile |
| Approved provider | Drive (status), **Rides** (Available groups to accept, My rides), Profile. Profile has a **Passenger / Provider switch** |
| Admin | A message pointing to the web admin panel |

## Run it

1. Install Flutter (https://docs.flutter.dev/get-started/install).
2. This folder has the Dart code only. Generate the Android/iOS folders **inside this folder**:
   ```bash
   cd app
   flutter create . --project-name sisterride --platforms=android,ios
   flutter pub get
   ```
   `flutter create .` keeps the existing `lib/` and `pubspec.yaml`. If it asks to overwrite `lib/main.dart`, say no.
3. Android: edit `android/app/src/main/AndroidManifest.xml`.
   - Above `<application>` add:
     `<uses-permission android:name="android.permission.INTERNET"/>`
   - Inside the `<application ...>` tag add: `android:usesCleartextTraffic="true"` (development only, because the dev server is plain http).
4. iOS (only if you use a Mac): in `ios/Runner/Info.plist` add `NSCameraUsageDescription` and `NSPhotoLibraryUsageDescription` with a short reason, and allow http for local development (App Transport Security exception).
5. Start the Laravel server (`php artisan serve`, or `php artisan serve --host=0.0.0.0` for a real phone) and run:
   ```bash
   flutter run                                              # Android emulator (uses http://10.0.2.2:8000)
   flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000   # real phone: your computer's LAN IP
   ```

## Try the full flow
1. Register a passenger (the server accepts women only for passengers), submit verification.
2. Open `http://127.0.0.1:8000/admin/` on your computer, log in as the admin, approve her.
3. In the app tap **Check status**. The home screen opens.
4. Register a provider (car driver), submit verification, add a vehicle, get approved in the admin panel, then use Profile to switch between Passenger and Provider.

## Step 3: requesting and matching rides
- **Request a ride:** set the mode, tap the map for pickup then drop-off (OpenStreetMap, no API key), pick a time, optionally name the places and a repeat pattern. You see a price preview (solo vs shared).
- **Matching:** the server groups you with 2 to 4 verified women on a similar route and time. The ride screen updates itself every 15 seconds.
- **Driver:** a provider opens Rides, sees matched groups for her vehicle type with what she would earn, and taps Accept. The first driver wins.
- **After acceptance:** the passenger sees her driver, plate number, co-riders (first names) and her 4-digit boarding PIN.
- The map starts on Lahore; change `_startCenter` in `request_ride_screen.dart` for your city. OpenStreetMap's public tile server is fine for a project demo, not for heavy production traffic.
- Place names are typed by hand for now (address search comes later).

Android needs the INTERNET permission already added in the earlier step, and no Maps key.

## Not built yet (next steps)
Trip start/finish with PIN check, live tracking, SOS button, group chat, wallet and payments, ratings, automatic daily booking from the repeat days. The app does not pretend these exist.

## Status of this code
Written without a Flutter SDK available, so it has **not been compiled**. Run `flutter analyze` and `flutter run`, and send me any error text; fixes are usually one line.
