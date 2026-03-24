# Verdict

A collaborative real-time rating app. Groups join a session and rate items together — starting with baby names, extensible to images and videos.

## Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11 |
| Frontend | Svelte 5 via Inertia.js |
| Real-Time | Laravel Reverb (WebSockets) |
| Database | MySQL 8.0 |
| Cache / Queue | Redis 7 |
| Local Dev | Docker Compose |
| CI/CD | GitHub Actions |

## Getting Started

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- Git

### Setup

```bash
git clone git@github.com:SamTrinidad/verdict.git
cd verdict

cp .env.example .env
```

Edit `.env` and set a strong `APP_KEY`, `DB_PASSWORD`, and `REDIS_PASSWORD`.

### Start the stack

```bash
docker compose build
docker compose up -d
```

### Run migrations and seed

```bash
docker exec verdict-app-1 //bin/sh -c "php /var/www/html/artisan migrate:fresh --seed --force"
```

Visit [http://localhost](http://localhost).

## Development

### Build frontend assets

```bash
docker run --rm -v "$(pwd):/app" -w //app node:22-alpine sh -c "npm run build"
docker cp public/build/. verdict-app-1:/var/www/html/public/build/
```

> On Windows use the full path: `-v "C:/Users/you/Projects/verdict:/app"`

### Run tests

```bash
docker exec verdict-app-1 //bin/sh -c "php /var/www/html/artisan test"
```

### Common artisan commands

```bash
# Tail logs
docker exec verdict-app-1 //bin/sh -c "php /var/www/html/artisan pail"

# Clear caches
docker exec verdict-app-1 //bin/sh -c "php /var/www/html/artisan cache:clear"

# Run a specific test file
docker exec verdict-app-1 //bin/sh -c "php /var/www/html/artisan test tests/Feature/ContentSetTest.php"
```

### Rebuild after Dockerfile changes

```bash
docker compose build
docker compose up -d
```

## Architecture

```
Browser (Svelte 5)
  |-- HTTP/XHR (Inertia)      --> Nginx --> PHP 8.4-FPM (Laravel) --> MySQL
  |-- WebSocket (Echo/Reverb) --> Nginx --> Reverb Server         --> Redis
  |-- Static assets            --> Nginx (served directly)
```

## Services

| Service | Port | Purpose |
|---|---|---|
| nginx | 80 | Reverse proxy + static assets |
| app | — | Laravel PHP-FPM |
| reverb | — | WebSocket server |
| worker | — | Queue worker |
| mysql | 3306 | Primary database |
| redis | 6379 | Cache, queues, pub/sub |
| mailpit | 8025 | Dev mail UI |

## License

MIT
