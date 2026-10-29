# ============================================================
#  Atajos del proyecto. Uso: make <objetivo>
#  (make viene preinstalado en Mac y Linux)
# ============================================================

# Versión leída desde el plugin para nombrar el zip.
PLUGIN_RELEASE := $(shell grep -oE "release\s*=\s*'[^']+'" mod/codelab/version.php | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")
ZIP_NAME := codelab-v$(PLUGIN_RELEASE).zip

# Versión INTERNA ($plugin->version). Es la que Moodle compara para decidir si
# actualiza, y la que cambia sin que cambie el nombre del ZIP: por eso un ZIP
# caducado es indistinguible a simple vista y hay que comprobarlo.
PLUGIN_VERSION := $(shell grep -oE '\$$plugin->version[[:space:]]*=[[:space:]]*[0-9]+' mod/codelab/version.php | grep -oE '[0-9]+$$')

# Archivos del plugin que entran en el ZIP (y de los que depende el ZIP).
FIND_EXCL   := ! -name '.DS_Store' ! -name '.gitignore' ! -path '*/.git/*' ! -path '*/node_modules/*'
PLUGIN_FILES := $(shell find mod/codelab -type f $(FIND_EXCL))

# Dónde se aparta lo obsoleto cuando no hay permiso de borrado (ver
# .claude/ENTORNO.md). Está en la raíz del proyecto, fuera de este repositorio.
OBSOLETO := ../_obsoleto-borrar

# Puerto web local, leído de .env (8080 si no está) solo para los mensajes.
MOODLE_PORT := $(or $(shell grep -E '^MOODLE_PORT=' .env 2>/dev/null | cut -d= -f2),8080)

.DEFAULT_GOAL := help

# Guardas: sin .env no hay contraseñas (no tienen valor por defecto) y sin
# judge0/judge0.conf el motor arrancaría sin token o Docker crearía una
# carpeta con ese nombre. Fallar aquí es más barato que depurar después.
check-env:
	@test -f .env || { \
		echo "ERROR: falta .env (ahí van las contraseñas)."; \
		echo "       Ejecuta:  cp .env.example .env   y rellena los valores."; \
		exit 1; }

check-judge0-conf: check-env
	@test -f judge0/judge0.conf || { \
		echo "ERROR: falta judge0/judge0.conf (contiene AUTHN_TOKEN)."; \
		echo "       Ejecuta:  make judge0-conf"; \
		exit 1; }
	@grep -qE '^AUTHN_TOKEN=.+' judge0/judge0.conf || { \
		echo "ERROR: judge0/judge0.conf no define AUTHN_TOKEN: el motor quedaria ABIERTO."; \
		echo "       Genera un token (openssl rand -hex 24), ponlo en .env y ejecuta: make judge0-conf"; \
		exit 1; }

judge0-conf: ## Genera judge0/judge0.conf (token y claves) desde .env
	@./scripts/gen-judge0-conf.sh

help: ## Muestra esta ayuda
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

up: check-env ## Levanta Moodle + BD + instala el plugin automáticamente
	docker compose up -d
	@echo "Moodle: http://localhost:$(MOODLE_PORT)  (usuario y clave: MOODLE_ADMIN_USER / MOODLE_ADMIN_PASS de tu .env)"
	@echo "Instalación del plugin en curso -> make install-logs"

install-logs: ## Sigue en vivo la instalación automática del plugin
	docker compose logs -f plugin_installer

redeploy: ## Re-copia el plugin y reinstala (tras editar el código)
	docker compose up -d --force-recreate --no-deps plugin_installer
	docker compose logs -f plugin_installer

verify: ## Comprueba si el plugin quedó instalado en Moodle (y con qué PHP)
	docker compose exec -u www-data moodle php -r 'define("CLI_SCRIPT",true); require("/var/www/html/config.php"); $$v=get_config("mod_codelab","version"); echo "PHP ".PHP_VERSION." | session.upload_progress.enabled=".var_export((bool)ini_get("session.upload_progress.enabled"),true)."\n"; echo $$v ? ("mod_codelab INSTALADO v".$$v."\n") : ("mod_codelab NO instalado\n");'

test: ## Ejecuta PHPUnit de mod_codelab dentro del contenedor (la 1.ª vez prepara el entorno)
	docker compose exec -u www-data -e COMPOSER_HOME=/tmp/composer -w /var/www/html moodle sh -c '\
		set -e; \
		[ -f /tmp/composer.phar ] || curl -fsSL https://getcomposer.org/download/latest-stable/composer.phar -o /tmp/composer.phar; \
		[ -x vendor/bin/phpunit ] || php /tmp/composer.phar install --no-interaction --no-progress --quiet; \
		php admin/tool/phpunit/cli/init.php >/tmp/phpunit-init.log 2>&1 || { tail -40 /tmp/phpunit-init.log; exit 1; }; \
		vendor/bin/phpunit --testsuite mod_codelab_testsuite $(ARGS)'

up-judge0: check-judge0-conf ## Levanta Moodle + BD + motor Judge0 auto-alojado
	docker compose -f docker-compose.yml -f docker-compose.judge0.yml up -d

logs: ## Muestra los logs en vivo
	docker compose logs -f

down: ## Detiene los contenedores (conserva los datos)
	docker compose -f docker-compose.yml -f docker-compose.judge0.yml down

reset: ## Detiene y BORRA los datos (empieza de cero)
	docker compose -f docker-compose.yml -f docker-compose.judge0.yml down -v

# El ZIP es un archivo que DEPENDE del código del plugin: si cualquier archivo de
# mod/codelab/ es más nuevo que el ZIP, make lo regenera solo. Así deja de hacer
# falta acordarse de regenerarlo tras cada cambio.
$(ZIP_NAME): $(PLUGIN_FILES)
	@# El ZIP se arma FUERA del proyecto y se vuelca con `cat >`, que trunca y
	@# escribe sin borrar: asi este objetivo no necesita permiso de borrado
	@# dentro de la carpeta del proyecto (ver .claude/ENTORNO.md).
	@tmp="$${TMPDIR:-/tmp}/codelab-zip-$$$$"; mkdir -p "$$tmp"; \
	( cd mod && zip -q -r -X "$$tmp/$(ZIP_NAME)" codelab \
		-x '*/.DS_Store' -x '*/.git/*' -x '*/node_modules/*' -x '*/.gitignore' ) \
	&& cat "$$tmp/$(ZIP_NAME)" > $@; \
	est=$$?; rm -rf "$$tmp"; [ $$est -eq 0 ] || exit $$est
	@# Aparta los ZIP de OTRAS versiones: si quedan dos, es facil subir a Moodle
	@# el equivocado. Si no se pueden borrar, se mueven en vez de fallar.
	@for z in codelab-v*.zip codelab.zip; do \
		if [ -f "$$z" ] && [ "$$z" != "$@" ]; then \
			rm -f "$$z" 2>/dev/null \
			|| { mkdir -p $(OBSOLETO)/zip-y-temporales \
			     && mv "$$z" $(OBSOLETO)/zip-y-temporales/ \
			     && echo "  Sin permiso de borrado: $$z apartado a $(OBSOLETO)/zip-y-temporales/"; }; \
		fi; \
	done; true
	@echo "Generado: $@"

plugin-zip: $(ZIP_NAME) ## Genera (si hace falta) y VERIFICA el ZIP del plugin
	@$(MAKE) --no-print-directory check-zip
	@echo "$(ZIP_NAME) listo: súbelo en Administración del sitio → Plugins → Instalar plugins"
	@echo ""
	@echo "  INSTALAR NO ES SOLO COPIAR. Tras subir el ZIP (o copiar mod/codelab/ a mano),"
	@echo "  entra en Administración del sitio → Notificaciones y deja que Moodle ejecute la"
	@echo "  actualización: es ahí donde corre db/upgrade.php y se aplican los cambios de"
	@echo "  esquema (índices y columnas nuevas). Si te saltas ese paso, el código queda"
	@echo "  actualizado y la base de datos no, y el fallo aparece más tarde y en otro sitio."
	@echo ""
	@echo "  ANTES DE ACTUALIZAR UN MOODLE EN USO (desde la 1.6.0): el ajuste «Permitir"
	@echo "  entregas desde» pasa a aplicarse de verdad, y lo hace sobre las actividades que"
	@echo "  ya existen. Donde un docente dejó ahí una fecha futura mientras el ajuste no"
	@echo "  hacía nada, sus estudiantes dejarán de poder entregar en cuanto actualices."
	@echo "  Es el comportamiento correcto, pero nadie puede distinguir esa fecha de una"
	@echo "  puesta a propósito: revísalas antes y avisa a los docentes afectados."
	@echo "    SELECT id, course, name, allow_submissions_from_date FROM mdl_codelab"
	@echo "     WHERE allow_submissions_from_date > UNIX_TIMESTAMP();"
	@echo ""
	@echo "ZIPs presentes:"; ls -1 codelab-v*.zip

hooks: ## Instala el hook de git que verifica el ZIP antes de cada commit
	@git config core.hooksPath scripts/git-hooks
	@echo "Hook instalado: scripts/git-hooks/pre-commit"
	@echo "  Antes de cada commit que toque mod/codelab/, se ejecuta 'make check-zip'."
	@echo "  Desactivarlo:  git config --unset core.hooksPath"

check-zip: ## Comprueba que el ZIP empaqueta EXACTAMENTE el código actual
	@test -f $(ZIP_NAME) || { \
		echo "ERROR: no existe $(ZIP_NAME). Ejecuta:  make plugin-zip"; exit 1; }
	@zipver=$$(unzip -p $(ZIP_NAME) codelab/version.php \
		| grep -oE 'version[[:space:]]*=[[:space:]]*[0-9]+' | grep -oE '[0-9]+$$'); \
	if [ "$$zipver" != "$(PLUGIN_VERSION)" ]; then \
		echo "ERROR: $(ZIP_NAME) empaqueta la version interna $$zipver, pero el codigo va por la $(PLUGIN_VERSION)."; \
		echo "       El nombre del ZIP NO cambia al subir la version interna, asi que un ZIP"; \
		echo "       caducado es indistinguible a simple vista. Ejecuta:  make plugin-zip"; \
		exit 1; \
	fi; \
	echo "  ✓ version interna del ZIP = $(PLUGIN_VERSION)"
	@fallos=0; \
	for f in $$(cd mod && find codelab -type f $(FIND_EXCL)); do \
		unzip -p $(ZIP_NAME) "$$f" 2>/dev/null | cmp -s - "mod/$$f" \
			|| { echo "  ✗ falta o difiere en el ZIP: $$f"; fallos=$$((fallos+1)); }; \
	done; \
	for f in $$(unzip -Z1 $(ZIP_NAME) | grep -v '/$$'); do \
		[ -f "mod/$$f" ] || { echo "  ✗ sobra en el ZIP: $$f"; fallos=$$((fallos+1)); }; \
	done; \
	if [ $$fallos -gt 0 ]; then \
		echo "ERROR: el ZIP no corresponde al codigo actual. Ejecuta:  make plugin-zip"; exit 1; \
	fi; \
	echo "  ✓ $$(cd mod && find codelab -type f $(FIND_EXCL) | wc -l | tr -d ' ') archivos identicos al arbol de trabajo"
	@unzip -Z1 $(ZIP_NAME) | cut -d/ -f1 | sort -u | grep -qx codelab \
		&& echo "  ✓ raiz unica codelab/" \
		|| { echo "  ✗ la raiz del ZIP no es codelab/"; exit 1; }
	@# Sin estos dos, el plugin instala en limpio pero rompe al ACTUALIZAR, y eso
	@# no se descubre hasta que le pasa a alguien con datos dentro.
	@for f in codelab/db/install.xml codelab/db/upgrade.php; do \
		unzip -Z1 $(ZIP_NAME) | grep -qx "$$f" \
			|| { echo "  ✗ el ZIP no lleva $$f (el esquema no se podria crear ni actualizar)"; exit 1; }; \
	done; \
	echo "  ✓ esquema empaquetado: db/install.xml y db/upgrade.php"

.PHONY: help up install-logs redeploy verify test up-judge0 logs down reset plugin-zip \
	check-env check-judge0-conf judge0-conf check-zip hooks
