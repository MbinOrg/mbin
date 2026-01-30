# Troubleshooting Bare Metal

## Logs

RabbitMQ:

- `sudo tail -f /var/log/rabbitmq/rabbit@*.log`

Supervisor:

- `sudo tail -f /var/log/supervisor/supervisord.log`

Supervisor jobs (Mercure and Messenger):

- `sudo tail -f /var/log/supervisor/mercure*.log`
- `sudo tail -f /var/log/supervisor/messenger*.log`

The separate Mercure log:

- `sudo tail -f /var/www/mbin/var/log/mercure.log`

Application Logs (prod or dev logs):

- `tail -f /var/www/mbin/var/log/prod-{YYYY-MM-DD}.log`

Or:

- `tail -f /var/www/mbin/var/log/dev-{YYYY-MM-DD}.log`

Web-server (Nginx):

- Normal access log: `sudo tail -f /var/log/nginx/mbin_access.log`
- Inbox access log: `sudo tail -f /var/log/nginx/mbin_inbox.log`
- Error log: `sudo tail -f /var/log/nginx/mbin_error.log`

### A useful command to view the logs

If you have `tail`, `jq` and `awk` installed you can run this command to turn the json log into a color coded
human-readable log:

```shell
tail -f /var/www/mbin/var/log/prod-2025-08-11.log  | jq -r '"\(.datetime) \(.level_name) \(.channel): \(.message)"' | awk '
{
  level=$2
  color_reset="\033[0m"
  color_info="\033[36m"      # Cyan
  color_warning="\033[33m"   # Yellow
  color_error="\033[31m"     # Red
  color_debug="\033[35m"     # Magenta

  if (level == "INFO") color=color_info
  else if (level == "WARNING") color=color_warning
  else if (level == "ERROR") color=color_error
  else if (level == "DEBUG") color=color_debug
  else color=color_reset

  print color $0 color_reset
}'
```

## Debugging

**Please, check the logs above first.** If you are really stuck, visit to our [Matrix space](https://matrix.to/#/%23mbin:melroy.org), there is a 'General' room and dedicated room for 'Issues/Support'.

Test PostgreSQL connections if using a remote server, same with Redis (or KeyDB is you are using that instead). Ensure no firewall rules blocking are any incoming or out-coming traffic (eg. port on 80 and 443).
