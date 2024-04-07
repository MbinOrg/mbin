# Troubleshooting Docker

## Debugging / Logging

1. List the running service containers with `docker compose ps`
2. You can see the logs with `docker compose logs -f <service>` (use `-f` to follow the output)
3. for `php`, `messenger` and `messenger_ap` services, the application log is also available at
   `storage/logs` directory on the host, named after the running environment and date
   (e.g. `storage/logs/prod-2023-12-01.log`)
