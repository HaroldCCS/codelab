#!/usr/bin/env bash
# ============================================================
#  Validación de la infraestructura SIN tocar AWS.
#  Ejecuta:  ./validar.sh      (desde infra/)
#
#  1. terraform fmt      — formato canónico
#  2. terraform validate — sintaxis + esquema de los recursos AWS
#  3. tests/             — renderiza los user_data reales
#  4. shellcheck         — analiza los scripts renderizados
#
#  Los pasos 2 requieren credenciales de AWS solo para `init`
#  (descarga del proveedor); no crean ni consultan recursos.
# ============================================================
set -uo pipefail
cd "$(dirname "$0")" || exit 1
TF=$(command -v terraform || command -v tofu || true)
[ -n "$TF" ] || { echo "ERROR: no encuentro terraform ni tofu en el PATH"; exit 1; }
fallos=0
paso() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
mal()  { echo "  ✗ $*"; fallos=$((fallos+1)); }
bien() { echo "  ✓ $*"; }

paso "1/4  Formato"
if "$TF" fmt -recursive -check -diff . ; then bien "todo formateado"; else mal "hay ficheros sin formatear (arréglalo con: $TF fmt -recursive .)"; fi

paso "2/4  Validación de sintaxis y esquema"
for d in bootstrap judge0 moodle; do
  ( cd "$d" && "$TF" init -backend=false -input=false >/dev/null 2>&1 && "$TF" validate ) \
    && bien "$d" || mal "$d no valida"
done

paso "3/4  Renderizado de los user_data"
( cd tests && "$TF" init -input=false >/dev/null 2>&1 && "$TF" apply -auto-approve >/dev/null 2>&1 ) \
  && bien "las 4 plantillas renderizan" || { mal "alguna plantilla falla al renderizar"; ( cd tests && "$TF" apply -auto-approve 2>&1 | tail -20 ); }

paso "4/4  Análisis de los scripts renderizados"
if command -v shellcheck >/dev/null; then
  mkdir -p /tmp/codelab-userdata
  for o in judge0_data judge0_app moodle_data moodle_app; do
    ( cd tests && "$TF" output -raw "$o" ) > "/tmp/codelab-userdata/$o.sh" 2>/dev/null
    bash -n "/tmp/codelab-userdata/$o.sh" 2>/dev/null || { mal "$o.sh: error de sintaxis de bash"; continue; }
    shellcheck -s bash -S warning "/tmp/codelab-userdata/$o.sh" && bien "$o.sh" || mal "$o.sh tiene avisos"
  done
else
  echo "  (shellcheck no instalado — 'brew install shellcheck' / 'apt install shellcheck')"
fi

printf '\n'
[ "$fallos" -eq 0 ] && { echo "RESULTADO: todo correcto."; exit 0; } || { echo "RESULTADO: $fallos problema(s)."; exit 1; }
