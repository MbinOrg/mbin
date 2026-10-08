# Admin statistics counter experiment

Local branch prototype for replacing request-time counts with transactionally
maintained totals. The ordinary application service and migrations are unchanged.
Only `router.php` enables the experimental reader.

## Design

- PostgreSQL triggers update counters in the transaction that changes a source row.
- `admin_perf.by_actor` keeps daily counts per user, including deleted users, so
  soft deletion and restoration can adjust the visible totals.
- `admin_perf.totals` keeps daily visible counts across 64 user-ID shards to reduce
  contention. Content locality follows the content; vote locality follows the voter.
- `admin_perf.all_time` keeps separate totals for the default dashboard: at most
  11 source kinds × 2 locality flags × 64 shards = 1,408 rows, independent of history.
- Period filters sum complete days and count the partial boundary day directly,
  preserving the existing strict timestamp cutoff. Boundary queries use timestamp
  indexes; their cost still depends on that day's traffic.

This is persistent database state, with no TTL or cache-warming requirement.
Existing data needs one initial backfill before counters can be read.

## Local setup

Requires installed application dependencies/assets, the existing PostgreSQL test
container on port 5433, and Valkey on port 6380. The populated baseline database
`mbin_admin_perf` from the investigation is retained locally: 1,000,041 favourites
and synthetic users including `adminProbe`, `voterProbe`, and `scale1`–`scale1000`.
The fixture snapshot is **not included in Git**; the mutation and concurrency
scripts require it. Do not run them against arbitrary application data.

Create an isolated copy once, if it does not already exist:

```sh
docker exec mbin-tests-db createdb -U mbin -T mbin_admin_perf mbin_admin_rollup_branch_test
export PROTOTYPE_DATABASE_URL='postgresql://mbin:YOUR_LOCAL_TEST_PASSWORD@127.0.0.1:5433/mbin_admin_rollup_branch_test?serverVersion=18&charset=utf8'
export PROTOTYPE_BASELINE_DATABASE_URL='postgresql://mbin:YOUR_LOCAL_TEST_PASSWORD@127.0.0.1:5433/mbin_admin_perf?serverVersion=18&charset=utf8'
php tools/admin-stats-prototype/install.php
```

The installer refuses remote hosts, ports other than 5433, and database names
outside the experiment. It installs schema, triggers, indexes, and backfills in
one transaction, locking source tables against writes first. It does not replace
an existing counter schema: a second installation fails and rolls back. This
blocking installer is for an idle local database, **not an online production migration**.

Run the development server:

```sh
sh tools/admin-stats-prototype/start.sh
```

Open <http://127.0.0.1:8002/admin> and sign in with the synthetic local admin account.
The router uses the isolated database, disables federation delivery, and uses an
in-memory messenger transport and null mailer. Normal application startup still
uses the existing statistics manager.

## Verification

```sh
php tools/admin-stats-prototype/verify.php
php tools/admin-stats-prototype/page-probe.php
php tools/admin-stats-prototype/concurrency.php
php tools/admin-stats-prototype/bounded-read.php
```

Reports and development cache/log files go under ignored `var/admin-stats-prototype/`.

- `verify.php` compares all six statistics to the existing implementation across
  12 states and 10 period/federation combinations (120 comparisons). Changes are
  rolled back; sequence values can advance.
- `page-probe.php` checks authenticated HTTP 200 responses through KernelBrowser
  and compares all six displayed counts. This is not browser UI acceptance.
- `concurrency.php` runs four workers, each inserting and deleting 100 favourites,
  against baseline and prototype databases. It checks the trigger actually counts
  inserts and verifies final totals. This commits synthetic insert/delete pairs;
  it is a SQL microbenchmark, not a federation throughput test.
- `bounded-read.php` temporarily fills all 1,408 all-time counter combinations
  with large synthetic totals and measures reads, then rolls back. Large counter
  values do not simulate a production-sized source dataset.

For real HTTP verification while the server is running:

```sh
export PROTOTYPE_ADMIN_EMAIL='adminProbe@example.com'
export PROTOTYPE_ADMIN_PASSWORD='YOUR_SYNTHETIC_ADMIN_PASSWORD'
python3 tools/admin-stats-prototype/http-probe.py
```

## Remaining implementation work

This branch preserves the working experiment; it is not deployment-ready.
Before integrating it into normal application startup:

- Design an online backfill, activation, rollback, reconciliation, and upgrade path.
- Review multi-user/bulk transaction lock ordering and deadlock handling. The
  current concurrency probe exercises independent users, not every lock interaction.
- Handle maintenance paths such as TRUNCATE or writes with triggers disabled.
- Review time-zone consistency, concurrent period reads, primary-key changes,
  retained actor state after hard deletion, and summary storage growth.
- Measure write overhead under realistic federation load and boundary-day reads
  at production scale, then verify the full first dashboard load under 2–3 seconds.

No production schema, data, configuration, or services are changed by moving this
experiment into Git. Earlier read-only production measurements found favourite
counts around 9–12 seconds and thread-comment counts around 9 seconds; production
counter performance has not been tested.
