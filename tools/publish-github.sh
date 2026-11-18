#!/usr/bin/env bash
# Publishes main to the public GitHub repo without the private paths below.
#
# Bitbucket (origin) holds the full history, private notes included.
# GitHub (github) gets the same history with PRIVATE_PATHS removed from every commit.
# git filter-repo is deterministic, so the rewritten commits keep the same hashes
# from run to run and each publish is a fast-forward push on GitHub.
#
# Usage: tools/publish-github.sh   (run from anywhere inside the repo)
set -euo pipefail

PRIVATE_PATHS=(memory)
BRANCH=main

root=$(git rev-parse --show-toplevel)
remote_url=$(git -C "$root" remote get-url github)
command -v git-filter-repo >/dev/null || { echo "git-filter-repo is not installed" >&2; exit 1; }

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

git clone -q --no-local --single-branch --branch "$BRANCH" "$root" "$work/repo"
cd "$work/repo"

args=()
for p in "${PRIVATE_PATHS[@]}"; do args+=(--path "$p"); done
git filter-repo --force --invert-paths "${args[@]}" >/dev/null

for p in "${PRIVATE_PATHS[@]}"; do
  if git log --all --name-only --format= | grep -q "^$p/\|^$p\$"; then
    echo "Private path '$p' is still in the filtered history; nothing was pushed." >&2
    exit 1
  fi
done

git push "$remote_url" "$BRANCH:$BRANCH"
