# Troubleshooting Docker

## Debugging / Logging

1. List the running service containers with `docker compose ps`.
2. You can see the logs with `docker compose logs -f <service>` (use `-f` to follow the output).
3. For `php` and `messenger` services, the application log is also available at `storage/php_logs/` & `storage/messenger_logs/` on the host.
