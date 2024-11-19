# PostgreSQL

PostgreSQL is used as the database for Mbin.

For production, you **do** want to change the default PostgreSQL settings (since the default settings are _not_ recommended).

Edit your PostgreSQL configuration file (assuming you're running PostgreSQL v16 or up):

```bash
sudo nano /etc/postgresql/16/main/postgresql.conf
```

These settings below are more **an indication and heavily depends on your server specifications**. As well as if you are running other services on the same server.

However, the following settings are a good starting point when your serve is around 12 vCPUs and 32GB of RAM. Be sure to fune-tune these settings to your needs.

```ini
# Increase max connections
max_connections = 100

# Increase shared buffers
shared_buffers = 8GB
# Enable huge pages (Be sure to check the note down below in order to enable huge pages!)
# This will fail if you didn't configure huge pages under Linux
# (if you do NOT want to use huge pages, set it to: "try" instead of: "on")
huge_pages = on

# Increase work memory
work_mem = 15MB
# Increase maintenance work memory
maintenance_work_mem = 2GB

# Should be posix under Linux anyway, just to be sure...
dynamic_shared_memory_type = posix

# Increase the number of IO current disk operations (especially useful for SSDs)
effective_io_concurrency = 200

# Increase the number of work processes (do not exceed your number of CPU cores)
# Adjusting this setting, means you should also change:
# max_parallel_workers, max_parallel_maintenance_workers and max_parallel_workers_per_gather
max_worker_processes = 16

# Increase parallel workers per gather
max_parallel_workers_per_gather = 4
max_parallel_maintenance_workers = 4
# Maximum number of work processes that can be used in parallel operations (we set it the same as max_worker_processes)
# You should *not* increase this value more than max_worker_processes
max_parallel_workers = 16

# Write ahead log sizes (unless you expect to write more than 1GB/hour of data in the DB)
max_wal_size = 8GB
min_wal_size = 2GB

# Group write commits to combine multiple transactions by a single flush (this is a time delay in ms)
commit_delay = 300

checkpoint_completion_target = 0.9

# Query tuning
# Set to 1.1 for SSDs.
# Increase this number (eg. 4.0) if you are running on slow spinning disks
random_page_cost = 1.1

# Increase the cache size, increasing the likelihood of index scans (if we have enough RAM memory)
# Try to aim for: RAM size * 0.8 (on a dedicated DB server)
effective_cache_size = 24GB
```

For reference check out [PGTune](https://pgtune.leopard.in.ua/)

> [!NOTE]
> We try to set `huge_pages` to: `on` in PostgreSQL, in order to make this work you will need to [enable huge pages under Linux (click here)](https://www.enterprisedb.com/blog/tuning-debian-ubuntu-postgresql) as well! Please follow that guide. And play around with your kernel configurations.
