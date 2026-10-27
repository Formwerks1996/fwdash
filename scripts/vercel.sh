#!/usr/bin/env bash
# Runs the Vercel CLI against the fwdash project with the token from .env, so no
# `vercel login` or `vercel link` is needed. Usage: scripts/vercel.sh <args>
set -euo pipefail

cd "$(dirname "$0")/.."

env_value() {
    sed -n "s/^$1=//p" .env | tail -1 | sed -e 's/^"//' -e 's/"$//'
}

export VERCEL_TOKEN="$(env_value VERCEL_TOKEN)"
export VERCEL_ORG_ID="$(env_value VERCEL_ORG_ID)"
export VERCEL_PROJECT_ID="$(env_value VERCEL_PROJECT_ID)"

if [ -z "$VERCEL_TOKEN" ]; then
    echo "VERCEL_TOKEN is empty in .env" >&2
    exit 1
fi

exec vercel "$@"
