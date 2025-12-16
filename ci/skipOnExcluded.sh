#!/usr/bin/env bash

ignoredPatterns="$(cat ./ci/ignoredPaths.txt)"
if [[ "$(git rev-parse --abbrev-ref HEAD)" == 'main' ]]; then
    changedFiles="$(git diff --name-only HEAD^ HEAD)"
else
    changedFiles="$(git diff --name-only main)"
fi

doSkip=1
while read -r path; do
    while read -r pattern; do
        [[ "$pattern" == '' ]] && continue
        [[ "$pattern" == \#* ]] && continue

        # shellcheck disable=SC2053
        if [[ "$path" == $pattern ]]; then
            continue 2
        fi
    done <<< "$ignoredPatterns"

    doSkip=0
    break
done <<< "$changedFiles"

if [[ "$doSkip" == 1 ]]; then
    echo skipping actions because diff only affects ignored paths
    exit 0
else
    echo running actions
    echo
    exec "$@"
fi
