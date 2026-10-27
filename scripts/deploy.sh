#!/usr/bin/env bash
# Deploy kulik-invite landing to Hosting Ukraine over SSH.
# Uses tar-over-SSH (not macOS rsync): openrsync breaks against GNU rsync here.
#
# Usage:
#   ./scripts/deploy.sh            # upload site → event.kuliksystem.us
#   ./scripts/deploy.sh --dry-run  # list files, upload nothing
#   ./scripts/deploy.sh discover   # find document roots on the server
#   ./scripts/deploy.sh --help

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT/.env.deploy"
MUX=""

usage() {
  cat <<'EOF'
Deploy the event landing to Hosting Ukraine.

  ./scripts/deploy.sh            upload site files
  ./scripts/deploy.sh --dry-run  list files, upload nothing
  ./scripts/deploy.sh discover   list remote document roots
  ./scripts/deploy.sh --help

Target: https://event.kuliksystem.us (DEPLOY_PATH in .env.deploy).

Preserves on the server: config.php, storage/*.jsonl, storage/*.tmp.
Never uploads: .env.deploy, .git, .agents, .cursor, docs, node_modules,
desktop/mobile mockups, or the brief markdown.

Credentials live in .env.deploy (gitignored). Copy from .env.deploy.example.
EOF
}

die() {
  echo "error: $*" >&2
  exit 1
}

load_env() {
  [[ -f "$ENV_FILE" ]] || die "missing $ENV_FILE — copy .env.deploy.example and fill it in"
  # shellcheck disable=SC1090
  source "$ENV_FILE"
  [[ -n "${DEPLOY_HOST:-}" ]] || die "DEPLOY_HOST is empty"
  [[ -n "${DEPLOY_USER:-}" ]] || die "DEPLOY_USER is empty"
  DEPLOY_PORT="${DEPLOY_PORT:-22}"
  DEPLOY_URL="${DEPLOY_URL:-https://event.kuliksystem.us}"
  [[ -n "${DEPLOY_PATH:-}" ]] || die "DEPLOY_PATH is empty — run ./scripts/deploy.sh discover"
  if [[ "${DEPLOY_PATH}" == *"'"* ]]; then
    die "DEPLOY_PATH must not contain a single quote"
  fi
  DEPLOY_PATH="${DEPLOY_PATH%/}"
}

ssh_plain() {
  ssh -p "$DEPLOY_PORT" \
    -o ControlMaster=no \
    -o ControlPath="$MUX" \
    -o StrictHostKeyChecking=accept-new \
    "${DEPLOY_USER}@${DEPLOY_HOST}" "$@"
}

stop_mux() {
  if [[ -n "$MUX" && -S "$MUX" ]]; then
    ssh -p "$DEPLOY_PORT" -o ControlPath="$MUX" -O exit \
      "${DEPLOY_USER}@${DEPLOY_HOST}" >/dev/null 2>&1 || true
    rm -f "$MUX"
  fi
}

start_mux() {
  MUX="${TMPDIR:-/tmp}/kulik-invite-deploy.$$.sock"
  trap stop_mux EXIT

  echo "Connecting to ${DEPLOY_USER}@${DEPLOY_HOST}…"
  if [[ -n "${DEPLOY_PASS:-}" ]]; then
    DEPLOY_PASS="$DEPLOY_PASS" "$ROOT/scripts/ssh-pass.exp" \
      -C \
      -o ControlMaster=yes \
      -o ControlPath="$MUX" \
      -o ControlPersist=120 \
      -o ServerAliveInterval=30 \
      -p "$DEPLOY_PORT" \
      -fN \
      "${DEPLOY_USER}@${DEPLOY_HOST}"
  else
    ssh -C -p "$DEPLOY_PORT" \
      -o StrictHostKeyChecking=accept-new \
      -o ControlMaster=yes \
      -o ControlPath="$MUX" \
      -o ControlPersist=120 \
      -o ServerAliveInterval=30 \
      -fN \
      "${DEPLOY_USER}@${DEPLOY_HOST}"
  fi

  local ok=0
  local i
  for i in 1 2 3 4 5 6 7 8; do
    if ssh -p "$DEPLOY_PORT" -o ControlPath="$MUX" -O check \
      "${DEPLOY_USER}@${DEPLOY_HOST}" >/dev/null 2>&1; then
      ok=1
      break
    fi
    sleep 0.25
  done
  [[ "$ok" == 1 ]] || die "SSH connection failed. ControlMaster may be disabled on this host."
}

remote() {
  ssh_plain "$@"
}

# Archive site files. COPYFILE_DISABLE skips macOS AppleDouble (._*) forks.
site_tar() {
  COPYFILE_DISABLE=1 tar -C "$ROOT" \
    --no-xattrs \
    --no-mac-metadata \
    --exclude='.git' \
    --exclude='.agents' \
    --exclude='.cursor' \
    --exclude='.scratch' \
    --exclude='.DS_Store' \
    --exclude='.env.deploy' \
    --exclude='.env.deploy.example' \
    --exclude='.env*' \
    --exclude='node_modules' \
    --exclude='docs' \
    --exclude='scripts' \
    --exclude='storage/*.jsonl' \
    --exclude='storage/*.tmp' \
    --exclude='config.php' \
    --exclude='desktop.png' \
    --exclude='mobile.png' \
    --exclude='skills-lock.json' \
    --exclude='AGENTS.md' \
    --exclude='GLOSSARY.md' \
    --exclude='*.md' \
    -cf - \
    index.html \
    .htaccess \
    submit.php \
    config.example.php \
    css \
    js \
    fonts \
    assets \
    lib \
    cron \
    storage
}

discover() {
  start_mux
  echo "Looking for document roots on ${DEPLOY_USER}@${DEPLOY_HOST}…"
  echo
  remote 'ls -la "$HOME" /home/ksus 2>/dev/null; echo; ls -la /home/ksus/kuliksystem.us 2>/dev/null || true; echo; find /home/ksus/kuliksystem.us -maxdepth 2 -type d 2>/dev/null | sort'
  echo
  echo "Set DEPLOY_PATH in .env.deploy (expected: /home/ksus/kuliksystem.us/event)."
  echo "Then: ./scripts/deploy.sh --dry-run"
}

upload_site() {
  local dest="$1"

  remote "mkdir -p '$dest' '$dest/storage'"

  echo "Uploading site → ${DEPLOY_USER}@${DEPLOY_HOST}:${dest}/"
  site_tar | remote "
    set -e
    cd '$dest'
    rm -rf .deploy-new .deploy-old
    mkdir .deploy-new
    tar -xf - -C .deploy-new
    # Keep live secrets and guest log if present.
    if [ -f config.php ]; then
      cp -p config.php .deploy-new/config.php
    fi
    if [ -d storage ]; then
      mkdir -p .deploy-new/storage
      for f in storage/*.jsonl storage/*.tmp; do
        [ -e \"\$f\" ] || continue
        cp -p \"\$f\" .deploy-new/storage/
      done
    fi
    # Swap: move current aside only for files we ship; leave unknown server files alone.
    for item in index.html .htaccess submit.php config.example.php css js fonts assets lib cron; do
      if [ -e \"\$item\" ]; then
        mkdir -p .deploy-old
        mv \"\$item\" .deploy-old/
      fi
      if [ -e .deploy-new/\"\$item\" ]; then
        mv .deploy-new/\"\$item\" .
      fi
    done
    # storage/.gitkeep (and empty storage dir) from tarball; data already merged above
    mkdir -p storage
    if [ -f .deploy-new/storage/.gitkeep ]; then
      mv .deploy-new/storage/.gitkeep storage/
    fi
    for f in .deploy-new/storage/*; do
      [ -e \"\$f\" ] || continue
      base=\$(basename \"\$f\")
      [ \"\$base\" = .gitkeep ] && continue
      if [ ! -e storage/\"\$base\" ]; then
        mv \"\$f\" storage/
      fi
    done
    rm -rf .deploy-new .deploy-old
    chmod 750 storage 2>/dev/null || true
  "

  if ! remote "test -f '$dest/config.php'"; then
    echo
    echo "Note: no config.php on the server yet."
    echo "  ssh in and: cp config.example.php config.php && edit secrets"
    echo "  Or upload a filled config.php once (deploy never overwrites it)."
  fi

  echo "Uploaded to ${DEPLOY_URL}"
}

deploy() {
  local dry_run="${1:-0}"

  echo
  echo "Target: ${DEPLOY_URL}"
  echo "Path:   ${DEPLOY_PATH}"
  echo
  echo "Archive contents:"
  site_tar | tar -tf - | sed 's|^|  |'
  echo

  if [[ "$dry_run" == "1" ]]; then
    echo "Dry run — nothing uploaded."
    echo "Would upload to ${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_PATH}/"
    return
  fi

  start_mux
  upload_site "$DEPLOY_PATH"
}

main() {
  local dry_run=0
  local cmd="deploy"

  while [[ $# -gt 0 ]]; do
    case "$1" in
      -h|--help) usage; exit 0 ;;
      --dry-run) dry_run=1 ;;
      discover) cmd="discover" ;;
      deploy) cmd="deploy" ;;
      *)
        usage
        die "unknown argument: $1"
        ;;
    esac
    shift
  done

  load_env

  case "$cmd" in
    discover) discover ;;
    deploy) deploy "$dry_run" ;;
  esac
}

main "$@"
