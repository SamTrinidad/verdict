# Verdict — Claude Code Instructions

## Common Commands

### Start the stack (includes Vite HMR dev server)
```bash
docker compose up -d
```

### Restart the stack (after docker-compose.yml changes)
```bash
docker compose down && docker compose up -d
```

### Rebuild the Docker image (after Dockerfile changes)
```bash
docker compose build
docker compose up -d
```

### Run database migrations and seed
```bash
docker exec verdict-app-1 //bin/sh -c "php /var/www/html/artisan migrate:fresh --seed --force"
```

### Run tests
```bash
docker exec verdict-app-1 //bin/sh -c "php /var/www/html/artisan test"
```

### Run specific test file
```bash
docker exec verdict-app-1 //bin/sh -c "php /var/www/html/artisan test tests/Feature/ContentSetTest.php"
```

### Clear Laravel caches (after controller/view changes)
```bash
docker exec verdict-app-1 //bin/sh -c "php /var/www/html/artisan cache:clear && php /var/www/html/artisan view:clear"
```

## Notes

- **PHP file changes** take effect immediately — OPcache is set to revalidate on every request in dev (`docker/php/opcache-dev.ini`).
- **Svelte/JS changes** are hot-reloaded instantly via the `node` service (Vite HMR on port 5173). No rebuild needed.
- **Run all docker exec commands with `//bin/sh`** (double slash) to prevent Git Bash from mangling Unix paths on Windows.
