#!/usr/bin/env bash
# ============================================================
#  Genera judge0/judge0.conf a partir de judge0/judge0.conf.example
#  rellenando los secretos con los valores de .env.
#
#      ./scripts/gen-judge0-conf.sh        (o: make judge0-conf)
#
#  Por qué existe: judge0.conf es el archivo que el motor monta y
#  contiene AUTHN_TOKEN y las contraseñas, así que está en
#  .gitignore. Generarlo desde .env deja UNA sola fuente de verdad
#  y evita que judge0.conf y docker-compose queden desincronizados.
# ============================================================
set -euo pipefail

raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
env_file="$raiz/.env"
plantilla="$raiz/judge0/judge0.conf.example"
destino="$raiz/judge0/judge0.conf"
marca="# Generado por scripts/gen-judge0-conf.sh — NO lo subas al repositorio."

error() { echo "ERROR: $*" >&2; exit 1; }

[ -f "$env_file" ]  || error "no existe .env. Ejecuta:  cp .env.example .env  y rellénalo."
[ -f "$plantilla" ] || error "no encuentro $plantilla"

# Lee una clave de .env sin ejecutar el archivo (hay valores con espacios).
leer_var() {
  local clave="$1" linea valor
  linea="$(grep -E "^[[:space:]]*${clave}=" "$env_file" | tail -n 1 || true)"
  valor="${linea#*=}"
  # Quita comillas envolventes si las hubiera.
  [ "${valor:0:1}" = '"' ] && [ "${valor: -1}" = '"' ] && valor="${valor:1:${#valor}-2}"
  [ "${valor:0:1}" = "'" ] && [ "${valor: -1}" = "'" ] && valor="${valor:1:${#valor}-2}"
  printf '%s' "$valor"
}

TOKEN="$(leer_var JUDGE0_AUTH_TOKEN)"
PG_PASS="$(leer_var JUDGE0_DB_PASSWORD)"
REDIS_PASS="$(leer_var JUDGE0_REDIS_PASSWORD)"

[ -n "$TOKEN" ] || error "JUDGE0_AUTH_TOKEN está vacío en .env.
       Sin token, el motor ejecuta el código de cualquiera que alcance el puerto.
       Genera uno y pégalo en .env:   openssl rand -hex 24
       Después pon el MISMO valor en Moodle: Administración del sitio → Plugins →
       Módulos de actividad → CodeLab → «Clave API de Judge0»."
[ -n "$PG_PASS" ]    || error "JUDGE0_DB_PASSWORD está vacío en .env."
[ -n "$REDIS_PASS" ] || error "JUDGE0_REDIS_PASSWORD está vacío en .env."

# No pisamos un judge0.conf escrito a mano.
if [ -f "$destino" ] && ! head -n 1 "$destino" | grep -qF "$marca"; then
  error "$destino existe y no lo generó este script (lo editaste a mano).
       Muévelo antes de regenerar:  mv judge0/judge0.conf judge0/judge0.conf.bak"
fi

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT
printf '%s\n' "$marca" >"$tmp"

# Copia la plantilla sustituyendo SOLO el valor de las tres claves con secreto.
while IFS= read -r linea || [ -n "$linea" ]; do
  case "$linea" in
    AUTHN_TOKEN=*)       printf 'AUTHN_TOKEN=%s\n'       "$TOKEN"      >>"$tmp" ;;
    POSTGRES_PASSWORD=*) printf 'POSTGRES_PASSWORD=%s\n' "$PG_PASS"    >>"$tmp" ;;
    REDIS_PASSWORD=*)    printf 'REDIS_PASSWORD=%s\n'    "$REDIS_PASS" >>"$tmp" ;;
    *)                   printf '%s\n' "$linea"                        >>"$tmp" ;;
  esac
done <"$plantilla"

# Comprobación: las tres claves quedaron con valor.
for clave in AUTHN_TOKEN POSTGRES_PASSWORD REDIS_PASSWORD; do
  grep -qE "^${clave}=.+" "$tmp" || error "la plantilla no define $clave"
done

mv "$tmp" "$destino"
trap - EXIT
chmod 600 "$destino"
echo "Generado: judge0/judge0.conf  (permisos 600, ignorado por git)"
echo "Recuerda: la «Clave API de Judge0» de Moodle debe valer lo mismo que JUDGE0_AUTH_TOKEN."
