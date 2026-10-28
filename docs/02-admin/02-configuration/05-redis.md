# Redis / KeyDB / Valkey

This documentation is valid for both Redis as well as KeyDB and Valkey. Both Valkey and KeyDB are forks of Redis, but should work mostly in the same manner.

## Configure Redis

Edit the Redis instance for Mbin: `sudo nano /etc/redis/redis.conf`:

```ruby
# NETWORK
timeout 300
tcp-keepalive 300

# MEMORY MANAGEMENT
maxmemory 1gb
maxmemory-policy volatile-ttl

# LAZY FREEING
lazyfree-lazy-eviction yes
lazyfree-lazy-expire yes
lazyfree-lazy-server-del yes
replica-lazy-flush yes
```

Feel free to adjust the memory settings to your liking.

> [!WARNING]
> Mbin (more specifically Symfony RedisTagAwareAdapter) only support `noeviction` and `volatile-*` settings for the `maxmemory-policy` Redis setting.

## Multithreading

Configure multiple threads in Redis/Valkey by setting the following two lines:

```ruby
# THREADED I/O
io-threads 4
io-threads-do-reads yes
```

However, when using **KeyDB**, you need to update the following line (`io-threads` doesn't exists in KeyDB):

```ruby
# WORKER THREADS
server-threads 4
```

## Persistence

Since [Mbin v1.11.0](https://github.com/MbinOrg/mbin/releases/tag/v1.11.0), Redis/KeyDB/Valkey also stores sessions. To preserve them across restarts, replace `save ""` and any existing snapshot schedule with this recommended production configuration:

```ruby
# RDB snapshots: seconds / minimum changes since the last snapshot (any condition triggers a save)
save 3600 1 300 100 60 10000

# Append-only file (AOF)
appendonly yes
appendfsync everysec
```

Snapshots alone can lose changes since the last snapshot after a crash; AOF with `everysec` limits potential loss to roughly one second of writes, with additional disk I/O and storage. Adjust the schedule to your workload; see [Valkey's persistence and backup guidance](https://valkey.io/topics/persistence/). Use a writable data directory with sufficient disk space and, for containers, a persistent volume.

### Existing instances

> [!WARNING]
> If persistence is disabled, initialize it on the running server **before restarting** to avoid losing current sessions. Use your server's CLI (`valkey-cli`, `redis-cli`, or `keydb-cli`) with the appropriate connection, database and authentication options:

```bash
valkey-cli CONFIG SET appendfsync everysec
valkey-cli CONFIG SET appendonly yes
valkey-cli CONFIG SET save "3600 1 300 100 60 10000"
valkey-cli INFO persistence
```

Before restarting, check `INFO persistence`: `aof_enabled` must be `1`, `aof_rewrite_in_progress` and `aof_rewrite_scheduled` both `0`, and `aof_last_bgrewrite_status` and `aof_last_write_status` both `ok`. Resolve errors and save the matching settings in the configuration file: `CONFIG SET` alone does not survive restarts. See [Valkey's live AOF migration instructions](https://valkey.io/topics/persistence/#how-i-can-switch-to-aof-if-im-currently-using-dumprdb-snapshots).

Persistence does not prevent session expiry or memory-pressure eviction: `volatile-*` policies can evict sessions because they expire. Monitor memory and provide capacity for both sessions and cache.
