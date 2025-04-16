# Backup and restore

## Bare Metal

### Backup

```bash
PGPASSWORD="YOUR_PASSWORD" pg_dump -U mbin mbin > dump.sql
```

### Restore

```bash
psql -U mbin mbin < dump.sql
```

## Docker

### Backup:

```bash
docker compose exec -it postgres pg_dump -U mbin mbin > dump.sql
```

### Restore:

```bash
docker compose exec -T postgres psql -U mbin mbin < dump.sql
```
