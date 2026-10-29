# CodeLab — Editor y evaluación automática de código para Moodle

Plataforma de evaluación automática de ejercicios de programación **integrada de forma nativa en
Moodle**. El docente crea un ejercicio y define casos de prueba (entradas y salidas esperadas); el
estudiante escribe y prueba su código desde el navegador; el código se ejecuta en un **motor
aislado** y, si pasa los casos, se obtiene una **nota** en el libro de calificaciones de Moodle.

Este repositorio contiene **dos cosas independientes**:

1. **El plugin** (`mod/codelab/`) — lo que se instala en cualquier Moodle.
2. **Un entorno de desarrollo local** (Docker) — solo para probar el plugin en tu máquina.

---

## 1. Instalar el plugin en tu Moodle

El plugin es autónomo: no depende de este entorno Docker ni de una base de datos externa. Usa la
base de datos propia de Moodle y un motor de ejecución que configuras después de instalar.

### Opción A — Subir el ZIP (recomendada)

1. Genera el ZIP con `make plugin-zip`. **No des por bueno un `codelab-vX.Y.Z.zip` que ya esté
   en la raíz sin ejecutar esto:** el nombre no cambia cuando solo sube la versión interna del
   plugin, así que un ZIP caducado es indistinguible a simple vista. `make plugin-zip` regenera
   el ZIP si algún archivo del plugin es más nuevo y verifica que su contenido coincide
   exactamente con `mod/codelab/`:
   ```bash
   make plugin-zip
   ```
2. En Moodle: **Administración del sitio → Plugins → Instalar plugins** → sube el ZIP.
3. Sigue el asistente hasta "Actualizar base de datos de Moodle".

> Para no depender de acordarse: `make hooks` instala un hook de git que ejecuta
> `make check-zip` antes de cualquier commit que toque `mod/codelab/` y lo aborta si el ZIP
> no corresponde al código. Se desactiva con `git config --unset core.hooksPath` y se salta
> puntualmente con `git commit --no-verify`.

### Opción B — Copia manual

Copia `mod/codelab/` a `TU_MOODLE/mod/codelab/` y entra a **Administración del sitio →
Notificaciones**.

> **No des ese paso por trámite, sobre todo si vienes de una versión anterior.** La 1.5.0 es el
> primer paquete desde julio que cambia el esquema de base de datos (un índice nuevo en
> `codelab_submission_results`, ver `mod/codelab/db/upgrade.php`). Si copias el código nuevo y no
> completas "Actualizar base de datos de Moodle" en Notificaciones, el plugin queda con el código
> de la 1.5.0 corriendo contra el esquema viejo — y eso no da un error claro: las pantallas
> siguen cargando, solo con un comportamiento raro (más lento, datos que no cuadran) que nadie
> relaciona con la causa. Vuelve a entrar a Notificaciones después de instalar y confirma que no
> queda nada pendiente.

> Para publicarlo en el **directorio de plugins de Moodle** (moodle.org/plugins) se sube ese
> mismo ZIP. La carpeta raíz del ZIP debe llamarse `codelab/` (así lo genera `make plugin-zip`).

---

## 2. Probar en local con Docker (Mac / Linux / Windows)

Solo necesitas [Docker Desktop](https://www.docker.com/products/docker-desktop/).

```bash
cp .env.example .env                      # OBLIGATORIO: ahí van las contraseñas
docker compose up -d
docker compose logs -f plugin_installer   # ver la instalación del plugin
```

> **Por qué `.env` es obligatorio.** Los `docker-compose*.yml` ya **no** traen contraseñas por
> defecto: ningún secreto debe quedar escrito en un archivo versionado. Si falta `.env`, Docker
> Compose se detiene diciendo qué variable falta (`make up` avisa antes, con el comando exacto).

Se levanta MariaDB y un contenedor de un solo uso (`plugin_installer`) **descarga Moodle 4.5,
lo instala junto con el plugin CodeLab** y siembra un curso de prueba; después arranca el
servidor web. Cuando en los logs veas `✅ mod_codelab INSTALADO`, ya está:

- **URL:** http://localhost:8080 (o el `MOODLE_PORT` de tu `.env`)
- **Usuario y contraseña:** los de `MOODLE_ADMIN_USER` / `MOODLE_ADMIN_PASS` de tu `.env`
  (en `.env.example` vienen como `admin` / `Admin1234!`).

Tras **editar el código** del plugin, vuelve a desplegarlo con:

```bash
make redeploy      # o: docker compose up -d --force-recreate plugin_installer
```

> 🔒 **PHP parcheado (desde el 2026-10-29).** El entorno usa la misma imagen que AWS,
> `moodlehq/moodle-php-apache:8.3` (PHP 8.3.36+), y el instalador **aborta** si el PHP de la imagen
> no tiene el parche de PHP2Shell (CVE-2026-109618 / CVE-2026-109619, `scripts/check-php-cve.php`).
> La imagen anterior, `bitnamilegacy/moodle:4.5`, traía PHP 8.1.32: vulnerable y sin parche posible
> (8.1 ya no recibe versiones y esa imagen está archivada). `config/php/99-codelab-seguridad.ini`
> apaga además `session.upload_progress` (CVE-2026-115560, aún sin versión corregida).
> Compruébalo con `make verify`.

> ⚠️ El plugin **no** se monta como volumen dentro del contenedor de Moodle: se **copia** con
> `plugin_installer` y luego se ejecuta el actualizador de Moodle, que es lo que aplica los cambios
> de esquema (`db/upgrade.php`). Montarlo en caliente se saltaría ese paso.

Atajos con `make` (opcional):

```bash
make up           # levanta Moodle + BD en segundo plano
make logs         # ver el progreso / logs
make down         # detener (conserva datos)
make reset        # borrar todo y empezar de cero
make judge0-conf  # genera judge0/judge0.conf (token y claves) desde .env
make verify       # ¿plugin instalado? ¿con qué PHP?
make test         # PHPUnit de mod_codelab dentro del contenedor
make plugin-zip   # generar (si hace falta) y verificar el ZIP del plugin
make check-zip    # solo verificar: ¿el ZIP corresponde al código actual?
make hooks        # instala el hook de git que ejecuta check-zip antes de commitear
```

Toda la configuración del entorno local vive en `.env` (copia de `.env.example`): puerto,
usuario/clave de admin, nombre del sitio, contraseñas de las bases de datos y el token del motor.
`.env` está en `.gitignore`; **nunca se sube al repositorio**.

---

## 3. Motor de ejecución (dónde corre el código del estudiante)

El código del estudiante es **no confiable** y **siempre** se ejecuta aislado (contenedor con
límites de tiempo, memoria, procesos y red).

> **Política del proyecto:** el motor Judge0 es **siempre auto-hospedado vía Docker** (en local o
> en un host/VM propio). **No** se usan servicios gestionados de terceros (p. ej. RapidAPI). La
> única dependencia externa aceptada es la IA de recomendaciones (opcional). Ver `contexto.md` §5.1.

### Auto-alojado con Docker (única vía)

**Paso 1 — genera el token del motor.** Judge0 solo exige autenticación si `AUTHN_TOKEN` tiene
valor. Sin token, cualquiera que alcance el puerto 2358 ejecuta el código que quiera en esa
máquina: no es un detalle de configuración, es la puerta del edificio.

```bash
openssl rand -hex 24          # copia la salida
```

Pega ese valor en `JUDGE0_AUTH_TOKEN` de tu `.env` y genera la configuración real del motor:

```bash
make judge0-conf              # crea judge0/judge0.conf desde .env
```

`judge0/judge0.conf` **no se versiona** (está en `.gitignore`): contiene el token y las
contraseñas. Lo versionado es la plantilla `judge0/judge0.conf.example`. Si prefieres hacerlo a
mano: `cp judge0/judge0.conf.example judge0/judge0.conf` y rellena `AUTHN_TOKEN`,
`POSTGRES_PASSWORD` y `REDIS_PASSWORD` con los mismos valores de `.env`.

**Paso 2 — levanta Moodle y el motor juntos:**

```bash
make up-judge0
# equivalente a: docker compose -f docker-compose.yml -f docker-compose.judge0.yml up -d
```

`make up-judge0` se niega a arrancar si falta `.env` o si `judge0/judge0.conf` no define un
`AUTHN_TOKEN`. El puerto 2358 se publica **solo en `127.0.0.1`**, así que el motor no queda
expuesto al Wi-Fi; Moodle le habla por la red interna de Compose (`http://judge0_server:2358`),
que no depende de esa publicación.

**Paso 3 — en Moodle: Administración del sitio → Plugins → Módulos de actividad → CodeLab**:

| Ajuste | Valor |
|---|---|
| URL de la API | `http://judge0_server:2358` |
| Clave API de Judge0 | el **mismo** valor de `JUDGE0_AUTH_TOKEN` |

> Desde la v1.2.0 el plugin ya no tiene los ajustes «Instancia auto-alojada» ni «Host de la API»:
> el motor es siempre auto-hospedado, así que sobraban. Si los buscas, no están.

**Paso 4 — comprueba que el token está en vigor** (desde el Mac, con el motor arriba):

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:2358/languages
# esperado: 401

curl -s -o /dev/null -w '%{http_code}\n' -H "X-Auth-Token: $JUDGE0_AUTH_TOKEN" \
     http://localhost:2358/languages
# esperado: 200
```

Y desde **otro equipo de la misma red**, `curl http://<ip-del-mac>:2358/languages` debe fallar por
conexión rechazada. Si alguna de las tres comprobaciones no da eso, **no sigas**: el motor está
abierto.

> **Mac (Apple Silicon):** las imágenes de Judge0 son amd64 y necesitan contenedores privilegiados
> con cgroups; bajo emulación son inestables. Como **no** recurrimos a la nube gestionada, corre el
> overlay de Judge0 en un **host/VM Linux x86 propio** (una máquina del equipo, un servidor de la
> universidad, o una VM en la nube que administres tú) y apunta la *URL de la API* a ese host. El
> editor y el resto del plugin sí funcionan en el Mac.

---

## Estructura del repositorio

```
.
├── docker-compose.yml           ← entorno local: solo Moodle + BD (docker compose up)
├── docker-compose.judge0.yml    ← overlay opcional: motor Judge0 auto-alojado
├── judge0/judge0.conf.example   ← plantilla del motor (el .conf real NO se versiona)
├── .env.example                 ← variables y secretos (copiar a .env — obligatorio)
├── scripts/gen-judge0-conf.sh   ← genera judge0/judge0.conf desde .env
├── scripts/git-hooks/pre-commit ← verifica el ZIP antes de commitear (make hooks)
├── Makefile                     ← atajos: up, down, logs, judge0-conf, plugin-zip…
├── EJEMPLOS.md                  ← ejemplos de actividades por lenguaje
└── mod/
    └── codelab/                 ← EL PLUGIN (esto es lo que se instala/publica)
        ├── version.php, lib.php, mod_form.php, view.php…
        ├── db/install.xml       ← esquema (XMLDB, 4 tablas)
        ├── classes/             ← executor, grader, submission_manager, privacy…
        ├── backup/moodle2/      ← copia de seguridad/restauración
        ├── lang/es, lang/en     ← idiomas
        ├── README.md, CHANGELOG.md
        └── ...
```

## Requisitos del plugin

- Moodle 4.0+ (probado en 4.5).
- Un motor Judge0 **auto-hospedado** (Docker), local o en un host propio.

## Licencia

GNU GPL v3 o posterior.
