# Redis / KeyDB

This documentation is valid for both Redis as well as KeyDB. KeyDB is a fork of Redis, but should work mostly in the same manner.

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

Configure multiple threads in Redis by enabling the following two lines:

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

## Redis as a cache

_Optionally:_ If you are using this Redis instance only for Mbin as a cache, you can disable snapshots in Redis. Which will no longer dump the database to disk and reduce the amount of disk space used as well the disk I/O.

First comment out existing "save lines" in the Redis configuration file:

```ruby
#save 900 1
#save 300 10
#save 60 10000
```

Then add the following line to disable snapshots fully:

```ruby
save ""
```
