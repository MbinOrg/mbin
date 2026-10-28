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

Since [Mbin v1.11.0](https://github.com/MbinOrg/mbin/releases/tag/v1.11.0), Redis/KeyDB/Valkey stores sessions as well as cache. Enable persistence to preserve sessions across server restarts.

For production, we recommend periodic RDB snapshots together with append-only file (AOF) persistence. Replace the old `save ""` setting and any existing snapshot schedule with:

```ruby
# SNAPSHOTS
save 3600 1
save 300 100
save 60 10000

# APPEND-ONLY FILE
appendonly yes
appendfsync everysec
```

The snapshot conditions are alternatives: save after one hour with at least one change, five minutes with at least 100 changes, or one minute with at least 10,000 changes since the last snapshot.

Snapshots alone can lose changes since the last snapshot after a crash. AOF with `appendfsync everysec` reduces potential loss to roughly one second of writes, at the cost of additional disk I/O and storage. Adjust the snapshot schedule to suit your workload. See the [Valkey persistence documentation](https://valkey.io/topics/persistence/) for the tradeoffs and backup guidance.

Ensure the configured data directory is writable by the service user and has sufficient disk space. For containers, the data directory must use a persistent volume to survive container recreation.

### Enable persistence on an existing instance

> [!WARNING]
> If the running instance has persistence disabled, simply changing the configuration and restarting can lose its current sessions. Initialize persistence on the running instance first.

Use the CLI for your server (`valkey-cli`, `redis-cli`, or `keydb-cli`), with the appropriate connection, database and authentication options. For example, with Valkey:

```bash
valkey-cli CONFIG SET appendfsync everysec
valkey-cli CONFIG SET appendonly yes
valkey-cli CONFIG SET save "3600 1 300 100 60 10000"
valkey-cli INFO persistence
```

Before restarting, verify that `aof_enabled` is `1`, both `aof_rewrite_in_progress` and `aof_rewrite_scheduled` are `0`, and `aof_last_bgrewrite_status` and `aof_last_write_status` are `ok`. Resolve any errors before proceeding. Also save the matching settings shown above in the server configuration file; `CONFIG SET` changes alone do not survive restarts. See [Valkey's instructions for enabling AOF on a running server](https://valkey.io/topics/persistence/#how-i-can-switch-to-aof-if-im-currently-using-dumprdb-snapshots).

Persistence does not prevent normal session expiry or eviction under memory pressure. With a `volatile-*` eviction policy, session keys can also be evicted because they have an expiry. Monitor memory use and allow sufficient capacity for both sessions and cache.
