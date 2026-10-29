#!/usr/bin/env bash
# ============================================================
#  Instalador automático de Moodle + CodeLab (entorno de dev)
# ------------------------------------------------------------
#  Lo ejecuta el servicio "plugin_installer" del docker-compose,
#  con la misma imagen Apache+PHP que sirve Moodle
#  (moodlehq/moodle-php-apache) y el mismo flujo que el despliegue
#  en AWS (infra/moodle/templates/app.sh.tftpl):
#
#    0. Comprueba que el PHP de la imagen tiene el parche de
#       PHP2Shell (CVE-2026-109618/109619). Si no, aborta.
#    1. Descarga el código de Moodle al volumen si está vacío.
#    2. Espera a MariaDB (sondeando, sin sleep fijo).
#    3. Copia el plugin a mod/codelab.
#    4. Primera vez: install.php (core + plugin). Después: upgrade.php.
#    5. Conecta el plugin al motor Judge0 si hay token en .env.
#    6. Verifica y siembra los datos de demostración.
#
#  CLAVE (permisos): el contenedor corre como root, pero Apache
#  atiende como www-data, que es quien tiene que poder escribir en
#  moodledata. Por eso los comandos de Moodle se ejecutan COMO
#  www-data: la caché que crean nace con el dueño correcto.
#
#  Es idempotente: se puede volver a correr (→ `make redeploy`).
# ============================================================
set -euo pipefail

MOODLE_DIR="/var/www/html"
DATA_DIR="/var/www/moodledata"
PLUGIN_SRC="/plugin-src"
PLUGIN_DEST="${MOODLE_DIR}/mod/codelab"
CONFIG="${MOODLE_DIR}/config.php"
WEBUSER="www-data"
MOODLE_BRANCH="${MOODLE_BRANCH:-405}"     # 405 = Moodle 4.5 LTS, igual que en AWS
MOODLE_URL="http://localhost:${MOODLE_PORT:-8080}"
MAX_WAIT=300          # espera máxima a la BD en segundos (5 min)
INTERVAL=5

run_as_web() { runuser -u "${WEBUSER}" -- "$@"; }

# --- 0. PHP parcheado o no seguimos ---------------------------------
php /check-php-cve.php

# --- 1. Código de Moodle --------------------------------------------
if [ ! -f "${MOODLE_DIR}/version.php" ]; then
  echo "[installer] Descargando Moodle (rama ${MOODLE_BRANCH})..."
  curl -fsSL --retry 5 --retry-delay 5 \
    "https://download.moodle.org/download.php/direct/stable${MOODLE_BRANCH}/moodle-latest-${MOODLE_BRANCH}.tgz" \
    -o /tmp/moodle.tgz
  tar -xzf /tmp/moodle.tgz -C /tmp
  cp -a /tmp/moodle/. "${MOODLE_DIR}/"
  rm -rf /tmp/moodle /tmp/moodle.tgz
fi
grep -m1 '^\$release' "${MOODLE_DIR}/version.php" | sed 's/^/[installer] Moodle: /'

# --- 2. Esperar a MariaDB -------------------------------------------
echo "[installer] Esperando a MariaDB..."
elapsed=0
until php -r '
    mysqli_report(MYSQLI_REPORT_OFF);
    exit(@mysqli_connect("mariadb", getenv("DB_USER"), getenv("DB_PASSWORD"), getenv("DB_NAME")) ? 0 : 1);'; do
  if [ "${elapsed}" -ge "${MAX_WAIT}" ]; then
    echo "[installer] ERROR: MariaDB no respondió tras ${MAX_WAIT}s." >&2
    exit 1
  fi
  sleep "${INTERVAL}"
  elapsed=$((elapsed + INTERVAL))
done

# --- 3. Copiar el plugin --------------------------------------------
echo "[installer] Copiando el plugin a mod/codelab..."
rm -rf "${PLUGIN_DEST}"
cp -r "${PLUGIN_SRC}" "${PLUGIN_DEST}"
chown -R "${WEBUSER}:${WEBUSER}" "${MOODLE_DIR}" "${DATA_DIR}" /var/www/phpunitdata

# --- 4. Instalar o actualizar (COMO www-data) ------------------------
if [ ! -f "${CONFIG}" ]; then
  echo "[installer] Instalando Moodle + CodeLab (primera vez, tarda unos minutos)..."
  run_as_web php "${MOODLE_DIR}/admin/cli/install.php" \
    --non-interactive --agree-license --lang=es \
    --wwwroot="${MOODLE_URL}" --dataroot="${DATA_DIR}" \
    --dbtype=mariadb --dbhost=mariadb --dbport=3306 \
    --dbname="${DB_NAME}" --dbuser="${DB_USER}" --dbpass="${DB_PASSWORD}" \
    --fullname="${MOODLE_SITE_NAME:-CodeLab}" --shortname="codelab" \
    --adminuser="${MOODLE_ADMIN_USER}" --adminpass="${MOODLE_ADMIN_PASS}" \
    --adminemail="${MOODLE_ADMIN_EMAIL}"
  # Solo dev: deja preparado el entorno de PHPUnit (make test).
  sed -i "0,/^require_once/s##\$CFG->phpunit_prefix = 'phpu_';\n\$CFG->phpunit_dataroot = '/var/www/phpunitdata';\nrequire_once#" "${CONFIG}"
else
  echo "[installer] Moodle ya instalado: ejecutando el actualizador..."
  run_as_web php "${MOODLE_DIR}/admin/cli/upgrade.php" --non-interactive
fi

# --- 5. Conectar el plugin al motor (overlay docker-compose.judge0.yml) ---
if [ -n "${JUDGE0_AUTH_TOKEN:-}" ]; then
  run_as_web php "${MOODLE_DIR}/admin/cli/cfg.php" --component=mod_codelab --name=judge0_api_url --set="http://judge0_server:2358"
  run_as_web php "${MOODLE_DIR}/admin/cli/cfg.php" --component=mod_codelab --name=judge0_api_key --set="${JUDGE0_AUTH_TOKEN}"
  echo "[installer] Plugin conectado a http://judge0_server:2358 con el token de .env."
fi

echo "[installer] Purgando cachés..."
run_as_web php "${MOODLE_DIR}/admin/cli/purge_caches.php" || true

# --- 6. Verificación y datos de demostración -------------------------
echo "[installer] Verificación final:"
run_as_web php -r '
    define("CLI_SCRIPT", true);
    require($argv[1]);
    $v = get_config("mod_codelab", "version");
    echo $v ? ("  ✅ mod_codelab INSTALADO (version " . $v . ")\n")
            : ("  ❌ mod_codelab NO instalado\n");
    exit($v ? 0 : 1);
  ' "${CONFIG}"

SEED_FILE="/seed-demo.php"
if [ "${SEED_DEMO:-1}" = "1" ] && [ -f "${SEED_FILE}" ]; then
  echo "[installer] Sembrando datos de demostración..."
  run_as_web php "${SEED_FILE}" || echo "[installer] AVISO: el seed falló (no bloquea la instalación)."
else
  echo "[installer] Seed de demostración omitido (SEED_DEMO=${SEED_DEMO:-1})."
fi

echo "[installer] Hecho. Moodle en ${MOODLE_URL}"
