# SisterRide

A ride app made up of a **Flutter mobile app** and a **Laravel (PHP) server** with its backend services.

![Flutter](https://img.shields.io/badge/Flutter-02569B?style=for-the-badge&logo=flutter&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)

## Repository structure

| Folder | Contents |
|---|---|
| `app/` | Flutter mobile application |
| `server/` | Laravel server (API) |
| `backend/` | Backend services and supporting code |

## Getting started

### Server (Laravel)

```bash
cd server
composer install
cp .env.example .env
php artisan key:generate
# configure your database in .env, then:
php artisan migrate
php artisan serve
```

### Mobile app (Flutter)

```bash
cd app
flutter pub get
flutter run
```

Point the app at your running server's base URL before running.

## Author

**Waleed Khan** - Full-Stack Developer
[LinkedIn](https://www.linkedin.com/in/waleed-khan-81b4ab371/) · [GitHub](https://github.com/waleedkhanalamgir)
