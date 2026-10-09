#!/usr/bin/env bash
# Installe OpenAI Codex CLI + configure l'agent ticket (macOS / Linux).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
ENV_LOCAL="${PROJECT_DIR}/.env.local"
MARKER="# --- codex-ticket-agent (install-codex-cli.sh) ---"

log() { printf '\033[1;34m→\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m!\033[0m %s\n' "$*"; }
err() { printf '\033[1;31m✗\033[0m %s\n' "$*" >&2; }

require_cmd() {
    if ! command -v "$1" >/dev/null 2>&1; then
        return 1
    fi
}

find_npm() {
    if require_cmd npm; then
        command -v npm
        return 0
    fi
    for candidate in \
        /opt/homebrew/bin/npm \
        /usr/local/bin/npm \
        "$HOME/.nvm/versions/node/$(ls "$HOME/.nvm/versions/node" 2>/dev/null | tail -1)/bin/npm" \
        ; do
        if [[ -x "$candidate" ]]; then
            echo "$candidate"
            return 0
        fi
    done
    return 1
}

log "Projet : ${PROJECT_DIR}"

# --- Node.js ---
if ! NPM="$(find_npm)"; then
    warn "npm introuvable."
    if [[ "$(uname -s)" == "Darwin" ]] && command -v brew >/dev/null 2>&1; then
        log "Installation Node via Homebrew (node@22)…"
        brew install node@22 || brew install node
        brew link --overwrite node@22 2>/dev/null || true
    else
        err "Installe Node.js 22+ : https://nodejs.org/ puis relance ce script."
        exit 1
    fi
    NPM="$(find_npm)" || { err "npm toujours absent après installation."; exit 1; }
fi

NODE_BIN="$(dirname "$NPM")/node"
log "Node : $($NODE_BIN --version) · npm : $($NPM --version)"

# --- OpenAI Codex CLI ---
log "Installation @openai/codex (global npm)…"
"$NPM" install -g @openai/codex@latest

CODEX_BIN="$(dirname "$NPM")/codex"
if ! [[ -x "$CODEX_BIN" ]]; then
    err "Binaire codex non trouvé après npm install -g."
    exit 1
fi
log "Codex CLI : $("$CODEX_BIN" --version 2>/dev/null || echo "$CODEX_BIN")"

# --- jq (pour le wrapper) ---
if ! require_cmd jq; then
    if [[ "$(uname -s)" == "Darwin" ]] && command -v brew >/dev/null 2>&1; then
        log "Installation jq…"
        brew install jq
    else
        err "Installe jq (brew install jq / apt install jq)."
        exit 1
    fi
fi

# --- Wrapper projet ---
chmod +x "${PROJECT_DIR}/bin/codex-ticket-run"
log "Wrapper : ${PROJECT_DIR}/bin/codex-ticket-run"

# --- .env.local ---
WRAPPER_PATH="${PROJECT_DIR}/bin/codex-ticket-run"
if [[ -f "$ENV_LOCAL" ]] && grep -q "$MARKER" "$ENV_LOCAL"; then
    log ".env.local déjà configuré (section Codex présente)."
else
    log "Mise à jour de .env.local…"
    {
        echo ""
        echo "$MARKER"
        echo "AGENT_PROFILE=codex_default"
        echo "OPENAI_CODEX_BIN=${CODEX_BIN}"
        echo "# Binaire invoqué par CodexCliRunner (interface run --request --prompt --output)"
        echo "AGENT_BINARY=${WRAPPER_PATH}"
    } >> "$ENV_LOCAL"
fi

# --- Auth ---
if ! "$CODEX_BIN" login status 2>/dev/null | grep -qi 'logged in'; then
    warn "Codex n'est pas encore authentifié."
    echo ""
    echo "  Lance maintenant :"
    echo "    $CODEX_BIN login"
    echo ""
    echo "  (navigateur ou clé API selon le flux OpenAI)"
else
    log "Codex : authentification OK."
fi

echo ""
log "Installation terminée."
echo ""
echo "Vérifications :"
echo "  ${WRAPPER_PATH} --help 2>/dev/null || true"
echo "  cd ${PROJECT_DIR} && php bin/console debug:container CodexCliRunner"
echo ""
echo "Test ticket (2 terminaux) :"
echo "  Terminal A : php bin/console messenger:consume async -vv"
echo "  Terminal B :"
echo "    php bin/console app:demo-ingest T-CODEX-TEST --body=\"formulaire de contact en erreur 500\""
echo "  Puis ouvre https://localhost:8001/tickets/T-CODEX-TEST"
