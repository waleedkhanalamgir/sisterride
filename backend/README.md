# SisterRide backend (Laravel) – Step 1 starter files

These files are an **overlay**: copy them into a fresh Laravel 11 project.

## 1. Create the Laravel project (on your computer)

```bash
composer create-project laravel/laravel sisterride-backend
cd sisterride-backend
php artisan install:api        # installs Sanctum and creates routes/api.php
```

## 2. Copy this overlay into the project
Copy the folders `app/`, `database/`, `routes/`, `tests/` from this overlay into `sisterride-backend/`.
Say yes to overwrite: `app/Models/User.php`, `routes/api.php` and `database/seeders/DatabaseSeeder.php`.

## 3. Register the role middleware
In `bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware) { ... })` add:

```php
$middleware->alias([
    'role' => \App\Http\Middleware\EnsureRole::class,
]);
```

## 4. Database
- Quick start: SQLite (the Laravel default), nothing to configure.
- For the real project: PostgreSQL. Set `DB_CONNECTION=pgsql` and the `DB_*` values in `.env`.

```bash
php artisan migrate --seed
php artisan serve
```

The seeder creates the two rate cards and a demo admin
(phone `03000000000`, password `password123`). Change the password before real use.

## 5. Try the API (Postman or curl)

| Method | URL | Purpose |
|---|---|---|
| POST | /api/register | Create passenger or provider account (returns token) |
| POST | /api/login | Login with phone or email |
| GET | /api/me | Current user, verification, vehicle, contacts, wallet |
| POST | /api/role/switch | Approved provider switches passenger/provider side |
| POST | /api/verification | Upload CNIC number, CNIC image, selfie (multipart) |
| POST | /api/vehicle | Provider adds vehicle and documents (multipart) |
| GET/POST/DELETE | /api/contacts | Trusted contacts (max 3) |
| POST | /api/fare/quote | Fare and split preview |
| GET | /api/admin/stats | Dashboard counts |
| GET | /api/admin/verifications?status=pending | Review queue (pending, approved, rejected) |
| GET | /api/admin/verifications/{id}/document/{cnic,selfie,student} | View a private document |
| POST | /api/admin/verifications/{id}/approve or /reject | Admin decision |
| GET | /api/admin/users | Search users |
| POST | /api/admin/users/{id}/suspend | Suspend or reinstate (`{"suspended": true}`) |
| GET, PATCH | /api/admin/sos | SOS alerts: acknowledge or resolve |
| GET, PUT | /api/admin/rate-cards | View and change fares and commission |

### Step 3 endpoints: rides and matching

| Method | URL | Who | Purpose |
|---|---|---|---|
| POST | /api/rides/preview | passenger | Distance, solo fare and per-woman fare for group sizes 2 to 4 |
| POST | /api/rides | passenger | Request a ride (mode, pickup/drop-off coordinates, `depart_at`, optional `recurrence_days`). Matching runs straight away. |
| GET | /api/rides, /api/rides/{id} | passenger | Her rides; detail shows status, her fare share, co-riders (first names), driver, and boarding PIN once a driver accepts |
| POST | /api/rides/{id}/cancel | passenger | Cancel; her group is dissolved and the others are re-matched |
| GET | /api/provider/groups/available | provider | Matched groups waiting for a driver (only your vehicle's mode) |
| GET | /api/provider/groups/mine | provider | Groups you accepted |
| POST | /api/provider/groups/{id}/accept, /release | provider | Take or hand back a group (first driver wins; seats checked) |
| GET | /api/admin/groups | admin | All groups and the number of waiting requests |
| POST | /api/admin/matching/run | admin | Run matching now |

**How matching works** (`app/Services/GroupPlanner.php`, pure PHP, unit tested):
- Same mode, departure within 15 minutes, pickups within 1.5 km, travelling the same way, at least 60% of the shorter trip on the longer route (the 60% is my assumption; change `MIN_OVERLAP`).
- Pair score = 0.45 route overlap + 0.25 time closeness + 0.20 pickup proximity + 0.10 trust (your deck formula).
- Groups of 2 to 4 women (car / rickshaw). Nobody rides more than 120% of her direct distance (detour cap). Bike mode is one woman, one rider.
- Fare: total from the rate card for the group route, split by each woman's distance, commission taken from the total.
- Distances are estimated as straight-line x 1.3 (no Google Directions needed).

**Run matching automatically.** It already runs when a ride is requested and when one is cancelled. To also run it every minute (closes expired requests and picks up late matches), add this to the bottom of `routes/console.php`:
```php
use Illuminate\Support\Facades\Schedule;
Schedule::command('rides:match')->everyMinute();
```
and keep `php artisan schedule:work` running in development. You can also run `php artisan rides:match` by hand.

**Demo data** (3 verified women on one corridor and an approved car driver):
```bash
php artisan db:seed --class=DemoSeeder
```
Passengers `03001110001`, `...02`, `...03`, driver `03001110009`, password `password123`. They are grouped immediately,
so log in as the driver in the app and accept the group, then log in as a passenger to see her fare share and PIN.
The sample coordinates are made-up points, not real addresses.

**Tests** (no database needed):
```bash
php artisan test --filter=GroupPlannerTest
```

## Admin web panel
Already included in `public/admin/` (plain HTML, CSS, JavaScript; no build step, no CDN).
After `php artisan serve`, open **http://127.0.0.1:8000/admin/** (or `/admin/index.html`)
and log in with the seeded admin. Pages: Dashboard, Verifications (view CNIC and selfie, approve or reject with a reason),
Users (search, suspend), Rides (groups, waiting requests, Run matching now), SOS alerts (open in Maps, acknowledge, resolve), Rate cards.

Send the token as `Authorization: Bearer <token>`.

Fare quote example body:
```json
{ "mode": "car_rickshaw", "route_km": 9,
  "passengers": [ {"id": "a", "distance_km": 9}, {"id": "b", "distance_km": 6}, {"id": "c", "distance_km": 3} ] }
```

## 6. Tests
```bash
php artisan test --filter=FareSplitterTest
```
Checks the deck example: 9 km, 6 km, 3 km sharing Rs 600 pays Rs 300, 200, 100.

## Notes
- Identity documents are stored on the private `local` disk, not the public disk.
- Phone OTP is not built yet; it comes with Firebase Auth in a later step.
- Coordinates are plain decimals so migrations run on SQLite and PostgreSQL. PostGIS comes in the matching step.
- Payments will use a test gateway behind a service class, in the wallet step.
