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
# RDB snapshotting: seconds / minimum changes since the last snapshot (any condition triggers a save)
save 3600 1 300 100 60 10000

# Optional: enable AOF for greater durability
appendonly yes
appendfsync everysec
```

**AOF (Append Only File) is optional** and logs writes for recovery after a restart. Enable it with `appendonly yes` and `appendfsync everysec`; see [Valkey's persistence documentation](https://valkey.io/topics/persistence/).

### Existing instances

> [!WARNING]
> If persistence is disabled, initialize it on the running server **before restarting** to avoid losing current sessions. Use your server's CLI (`valkey-cli`, `redis-cli`, or `keydb-cli`) with the appropriate connection, database and authentication options:

```bash
valkey-cli CONFIG SET save "3600 1 300 100 60 10000"
valkey-cli BGSAVE
valkey-cli INFO persistence
```

Before restarting, wait for the snapshot to complete: `rdb_bgsave_in_progress` must be `0`, `rdb_last_bgsave_status` must be `ok`, and `rdb_last_save_time` must reflect the new save. Resolve errors and save the matching settings in the configuration file: `CONFIG SET` alone does not survive restarts.

If also enabling optional AOF, run `CONFIG SET appendfsync everysec` and `CONFIG SET appendonly yes` using the same CLI. Before restarting, check `INFO persistence`: `aof_enabled` must be `1`, `aof_rewrite_in_progress` and `aof_rewrite_scheduled` both `0`, and `aof_last_bgrewrite_status` and `aof_last_write_status` both `ok`. Persist these settings in the configuration file too. See [Valkey's live AOF migration instructions](https://valkey.io/topics/persistence/#how-i-can-switch-to-aof-if-im-currently-using-dumprdb-snapshots).

Persistence does not prevent session expiry or memory-pressure eviction: `volatile-*` policies can evict sessions because they expire. Monitor memory and provide capacity for both sessions and cache.
