#!/usr/bin/env bash
# =============================================================================
# expense.sh — Expense Manager (Laravel) — nginx + PHP-FPM
#
# SQLite is file-based; there is no database daemon to start or stop.
#
# Usage:
#   bash scripts/expense.sh <command> [service]
#
# Commands:
#   start   [service]  — start php-fpm and nginx (or one)
#   stop    [service]  — stop nginx and php-fpm (or one)
#   restart [service]  — restart (or one)
#   status               — show systemd status + app URL
#   logs    [target]   — tail log(s)
#
# Services:
#   fpm | php | nginx | all (default)
#
# Log targets:
#   nginx | access | laravel | all (default)
# =============================================================================

PHP_VER="8.4"
DEPLOY_DIR="/var/www/expense-manager"

SVC_NGINX="nginx"
SVC_FPM="php${PHP_VER}-fpm"

LOG_NGINX_ERR="/var/log/nginx/expense-manager-error.log"
LOG_NGINX_ACC="/var/log/nginx/expense-manager-access.log"
LOG_LARAVEL="${DEPLOY_DIR}/storage/logs/laravel.log"

# Start order: FPM first, then nginx. Stop: reverse.
ALL_SVCS=(
  "${SVC_FPM}"
  "${SVC_NGINX}"
)

_sudo() { sudo "$@"; }

svc_of() {
  case "$1" in
    nginx)     echo "${SVC_NGINX}" ;;
    fpm|php)   echo "${SVC_FPM}" ;;
    all|"")    echo "${ALL_SVCS[*]}" ;;
    *)
      echo "Unknown service: $1" >&2
      echo "Valid: fpm | php | nginx | all" >&2
      exit 1
      ;;
  esac
}

is_active() {
  systemctl is-active --quiet "$1" 2>/dev/null
}

svc_uptime() {
  local state
  state=$(systemctl is-active "$1" 2>/dev/null || true)
  if [ "${state}" = "active" ]; then
    local since
    since=$(systemctl show "$1" --property=ActiveEnterTimestamp --value 2>/dev/null | sed 's/ UTC.*//')
    if [ -n "${since}" ]; then
      local ts now elapsed
      ts=$(date -d "${since}" +%s 2>/dev/null || date -j -f "%a %Y-%m-%d %H:%M:%S" "${since}" +%s 2>/dev/null || echo 0)
      now=$(date +%s)
      elapsed=$(( now - ts ))
      local hh mm ss
      hh=$(( elapsed / 3600 ))
      mm=$(( (elapsed % 3600) / 60 ))
      ss=$(( elapsed % 60 ))
      if [ ${hh} -gt 0 ]; then
        printf "%dh %02dm" "${hh}" "${mm}"
      elif [ ${mm} -gt 0 ]; then
        printf "%dm %02ds" "${mm}" "${ss}"
      else
        printf "%ds" "${ss}"
      fi
    else
      echo "running"
    fi
  else
    echo "${state}"
  fi
}

cmd_start() {
  local target="${1:-}"
  local svcs
  read -ra svcs <<< "$(svc_of "${target}")"
  echo "Starting services..."
  for svc in "${svcs[@]}"; do
    printf "  %-30s" "${svc}"
    if is_active "${svc}"; then
      echo "already active"
    else
      _sudo systemctl start "${svc}" 2>&1 && echo "started" || echo "FAILED"
    fi
  done
}

cmd_stop() {
  local target="${1:-}"
  local svcs
  read -ra svcs <<< "$(svc_of "${target}")"
  if [ -z "${target}" ]; then
    local reversed=()
    for (( i=${#ALL_SVCS[@]}-1; i>=0; i-- )); do
      reversed+=("${ALL_SVCS[$i]}")
    done
    svcs=("${reversed[@]}")
  fi
  echo "Stopping services..."
  for svc in "${svcs[@]}"; do
    printf "  %-30s" "${svc}"
    if ! is_active "${svc}"; then
      echo "already stopped"
    else
      _sudo systemctl stop "${svc}" 2>&1 && echo "stopped" || echo "FAILED"
    fi
  done
}

cmd_restart() {
  local target="${1:-}"
  local svcs
  read -ra svcs <<< "$(svc_of "${target}")"
  echo "Restarting services..."
  for svc in "${svcs[@]}"; do
    printf "  %-30s" "${svc}"
    _sudo systemctl restart "${svc}" 2>&1 && echo "restarted" || echo "FAILED"
  done
}

cmd_status() {
  local WSL_IP
  WSL_IP=$(hostname -I | awk '{print $1}')

  printf "\n  %-30s %-12s %s\n" "SERVICE" "STATE" "UPTIME"
  printf "  %-30s %-12s %s\n" "-------" "-----" "------"
  for svc in "${ALL_SVCS[@]}"; do
    local state
    state=$(systemctl is-active "${svc}" 2>/dev/null || echo "unknown")
    printf "  %-30s %-12s %s\n" "${svc}" "${state}" "$(svc_uptime "${svc}")"
  done

  echo
  echo "  App:     http://expense.com  (WSL: http://${WSL_IP} with Host: expense.com)"
  echo "  SQLite:  ${DEPLOY_DIR}/database/database.sqlite"
  echo
}

cmd_logs() {
  local target="${1:-all}"

  case "${target}" in
    nginx|web|error)
      echo "Tailing nginx error log (Ctrl-C)..."
      sudo tail -f "${LOG_NGINX_ERR}"
      ;;
    access)
      echo "Tailing nginx access log (Ctrl-C)..."
      sudo tail -f "${LOG_NGINX_ACC}"
      ;;
    laravel|app|php)
      echo "Tailing Laravel log (Ctrl-C)..."
      touch "${LOG_LARAVEL}" 2>/dev/null || true
      tail -f "${LOG_LARAVEL}" 2>/dev/null || sudo tail -f "${LOG_LARAVEL}"
      ;;
    all|"")
      echo "Tailing all expense-manager logs (Ctrl-C)..."
      [ -f "${LOG_LARAVEL}" ] || touch "${LOG_LARAVEL}" 2>/dev/null || sudo touch "${LOG_LARAVEL}"
      echo "  error:  ${LOG_NGINX_ERR}"
      echo "  access: ${LOG_NGINX_ACC}"
      echo "  app:    ${LOG_LARAVEL}"
      echo ""
      sudo tail -f "${LOG_NGINX_ERR}" "${LOG_NGINX_ACC}" "${LOG_LARAVEL}"
      ;;
    *)
      echo "Unknown log target: ${target}"
      echo "Valid: nginx | access | laravel | all"
      exit 1
      ;;
  esac
}

usage() {
  cat <<'USAGE'
Expense Manager — nginx + PHP-FPM helper

Usage: bash scripts/expense.sh <command> [service|log-target]

Commands:
  start   [service]   Start php8.4-fpm and/or nginx
  stop    [service]   Stop nginx and/or fpm
  restart [service]   Restart
  status              Show service status
  logs    [target]    Tail logs (default: all)

Services:  fpm | php | nginx | all (default)
Log targets: nginx (error) | access | laravel | all (default)

Examples:
  bash scripts/expense.sh start
  bash scripts/expense.sh restart fpm
  bash scripts/expense.sh stop nginx
  bash scripts/expense.sh status
  bash scripts/expense.sh logs
  bash scripts/expense.sh logs laravel
USAGE
}

CMD="${1:-}"
ARG="${2:-}"

case "${CMD}" in
  start)   cmd_start   "${ARG}" ;;
  stop)    cmd_stop    "${ARG}" ;;
  restart) cmd_restart "${ARG}" ;;
  status)  cmd_status ;;
  logs)    cmd_logs    "${ARG}" ;;
  ""|help|--help|-h) usage ;;
  *)
    echo "Unknown command: ${CMD}" >&2
    echo "" >&2
    usage
    exit 1
    ;;
esac
