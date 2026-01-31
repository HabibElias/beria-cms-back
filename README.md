## Beria CMS (Back-end)

Laravel API for the Beria CMS project. This repository contains the backend application, migrations, seeders, and API endpoints used by the frontend.

### Features

- REST API for books, categories, checkouts, and returns
- JWT-based authentication
- PostgreSQL database support
- Database seeding for local/dev

### Requirements

- PHP 8.2+
- Composer
- PostgreSQL 13+

### Quick Start

1. Install dependencies

```bash
composer install
```

2. Create your environment file

```bash
cp .env.example .env
```

3. Generate app key

```bash
php artisan key:generate
```

4. Configure your database in .env

Use a direct PostgreSQL host (not a pooler) for migrations. Example:

```env
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_PORT=5432
DB_DATABASE=your-db
DB_USERNAME=your-user
DB_PASSWORD=your-password
```

5. Run migrations and seeders

```bash
php artisan migrate:fresh --seed
```

6. Start the server

```bash
php artisan serve --host=0.0.0.0 --port=8001
```

### Frontend Integration

The frontend is expected to run on http://localhost:3000 or a Vite dev server. Update `FRONT_END_URL` in .env and ensure CORS allows the frontend origin.

### CORS

Allowed origins are configured in config/cors.php. If your frontend runs on a different host/port, add it to the `allowed_origins` list and clear the config cache:

```bash
php artisan config:clear
```

### JWT

Generate a JWT secret if needed:

```bash
php artisan jwt:secret
```

### Useful Commands

```bash
php artisan migrate
php artisan db:seed
php artisan route:list
```

### License

MIT
