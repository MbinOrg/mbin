
# Backup and restore

## Bare Metal

### Backup

```bash
PGPASSWORD="YOUR_PASSWORD" pg_dump -U kbin kbin > dump.sql
```

### Restore

```bash
psql -U kbin kbin < dump.sql
```

## Docker

### Backup:

```bash
docker compose exec -it db pg_dump -U kbin kbin > dump.sql
```

### Restore:

```bash
docker compose exec -T db psql -U kbin kbin < dump.sql
```
