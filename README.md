# Appointment Module — Backend

REST API for an appointment booking platform. Built with **Laravel 13** and uses **Laravel Sanctum** with multiple guards (`customer`, `staff`, `admin`) for role-based authentication.

## Tech Stack

- PHP 8.3+
- Laravel 13
- Laravel Sanctum (token auth)
- SQLite (default) / MySQL / PostgreSQL
- Vite + Tailwind CSS (for the built-in front-end assets)

## Features

- **Three user roles** with separate authentication guards
  - `customer` — register, login, book & cancel appointments, manage profile
  - `staff` — login, view & update assigned appointments, manage profile
  - `admin` — login, full CRUD on categories / services / staff, manage all appointments
- Browse categories, services and staff availability (public endpoints)
- Book appointments with availability checks
- Manage appointment status (staff) and lifecycle (admin)

## Requirements

- PHP **8.3** or higher
- Composer
- Node.js & npm
- SQLite (default) or any other supported DB

## Installation

```bash
# 1. Install PHP dependencies
composer install

# 2. Copy environment file and generate app key
cp .env.example .env
php artisan key:generate

# 3. Configure your DB in .env (SQLite works out of the box)
#    Default: DB_CONNECTION=sqlite
touch database/database.sqlite

# 4. Run migrations
php artisan migrate

# 5. (Optional) Install JS dependencies & build front assets
npm install
npm run build
```

## Running the app

```bash
# Development server (runs server + queue + logs + vite together)
composer dev

# Or run pieces individually
php artisan serve           # API on http://localhost:8000
php artisan queue:listen
php artisan pail            # log tailer
npm run dev                 # vite dev server
```

## API Overview

Base URL: `/api`

### Public — Auth

| Method | Endpoint                  | Description                |
| ------ | ------------------------- | -------------------------- |
| POST   | `/customer/register`      | Register a new customer    |
| POST   | `/customer/login`         | Customer login             |
| POST   | `/staff/login`            | Staff login                |
| POST   | `/admin/login`            | Admin login                |

### Public — Catalog & Availability

| Method | Endpoint                              | Description                       |
| ------ | ------------------------------------- | --------------------------------- |
| GET    | `/categories`                         | List all categories                |
| GET    | `/categories/{category}`              | Show a category                    |
| GET    | `/services`                           | List all services                  |
| GET    | `/services/{service}`                 | Show a service                     |
| GET    | `/services/{service}/staff`           | List staff available for a service |
| GET    | `/categories/{category}/staff`        | List staff in a category           |
| GET    | `/availability`                       | Check availability for a slot      |

### Customer (auth: `customer`)

| Method | Endpoint                          | Description                   |
| ------ | --------------------------------- | ----------------------------- |
| POST   | `/customer/logout`                | Log out                       |
| POST   | `/appointments`                   | Create a new appointment      |
| PATCH  | `/appointments/{id}/cancel`       | Cancel own appointment        |
| GET    | `/my-appointments`                | List own appointments         |
| GET    | `/my-appointments/{id}`           | Show own appointment detail   |
| GET    | `/customer/profile`               | Show profile                  |
| PUT    | `/customer/profile`               | Update profile                |

### Staff (auth: `staff`)

| Method | Endpoint                                   | Description                  |
| ------ | ------------------------------------------ | ---------------------------- |
| POST   | `/staff/logout`                            | Log out                      |
| GET    | `/staff/appointments`                      | List assigned appointments   |
| GET    | `/staff/appointments/{id}`                 | Show assigned appointment    |
| PATCH  | `/staff/appointments/{id}/status`          | Update appointment status    |
| GET    | `/staff/profile`                           | Show profile                 |
| PUT    | `/staff/profile`                           | Update profile               |

### Admin (auth: `admin`)

| Method | Endpoint                         | Description                       |
| ------ | -------------------------------- | --------------------------------- |
| POST   | `/admin/logout`                  | Log out                           |
| GET    | `/admin/profile`                 | Show profile                      |
| PUT    | `/admin/profile`                 | Update profile                    |
| GET/POST/PUT/DELETE | `/categories` (except `index`, `show`) | Manage categories  |
| GET/POST/PUT/DELETE | `/services`   (except `index`, `show`) | Manage services    |
| GET/POST/PUT/DELETE | `/staff-members`                       | Manage staff        |
| GET    | `/appointments`                  | List all appointments             |
| GET    | `/appointments/{id}`             | Show any appointment              |
| PUT    | `/appointments/{id}`             | Update any appointment            |
| DELETE | `/appointments/{id}`             | Delete an appointment             |

All authenticated endpoints expect a Bearer token in the `Authorization` header:
`Authorization: Bearer <token>`

## Project Structure

```
app/
├── Http/Controllers/   # API controllers
└── Models/             # Eloquent models (Admin, Staff, Customer, Appointment, ...)
database/
routes/
├── api.php             # API routes (this is the main one)
├── web.php
└── console.php
config/
```

## Testing

```bash
composer test
# or
php artisan test
```

Tests are written with **Pest**.

## License

MIT
