#!/usr/bin/env bash

set -eu

# Necessary in the GitHub Action environment
git config --global --add safe.directory "$(realpath "$GITHUB_WORKSPACE")"

ignoredPatterns="$(cat "$GITHUB_WORKSPACE"/ci/ignoredPaths.txt)"
if [[ "${GITHUB_HEAD_REF:-${GITHUB_REF#refs/heads/}}" == 'main' ]]; then
    git fetch origin main:main --depth 2
    changedFiles="$(git diff --name-only main^ HEAD)"
else
    git fetch origin main:main --depth 1
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
    echo "Skipping actions because diff only affects ignored paths"
    exit 0
else
    echo "Running actions"
    echo
    exec "$@"
fi
