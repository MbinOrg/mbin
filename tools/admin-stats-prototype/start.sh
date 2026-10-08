#!/bin/sh
set -eu
task_root=$(CDPATH='' cd -- "$(dirname -- "$0")/../.." && pwd)
cd "$task_root"
exec php -S 127.0.0.1:8002 -t public tools/admin-stats-prototype/router.php
