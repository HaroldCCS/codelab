# Guía de despliegue — CodeLab

Cómo poner en marcha el plugin en distintos entornos: desde una prueba local hasta un servidor
institucional de producción. El plugin es autónomo; el motor de ejecución y (opcionalmente) el
proveedor de IA se configuran desde la administración de Moodle, sin tocar el código.

---

## 1. Componentes

| Componente | Rol | Obligatorio |
|---|---|---|
| **Moodle** + plugin `mod_codelab` | Interfaz, evaluación, notas | Sí |
| **Motor de ejecución** (Judge0) | Ejecuta el código no confiable en sandbox | Sí |
| **Proveedor de IA** | Recomendaciones cualitativas | No (opcional) |

El motor Judge0 es **siempre auto-hospedado** (Docker), en local o en un host/VM propio.
**Política del proyecto:** no se usan servicios gestionados de terceros; la única dependencia
externa aceptada es el proveedor de IA (opcional). Ver `contexto.md` §5.1.

---

## 2. Instalar el plugin

**Opción A (ZIP):** *Administración del sitio → Plugins → Instalar plugins* → sube
`codelab-vX.Y.Z.zip` → "Actualizar base de datos de Moodle".

**Opción B (manual):** copia `mod/codelab/` a `TU_MOODLE/mod/codelab/` y entra a
*Administración del sitio → Notificaciones*.

> **No des ese paso por trámite: en la 1.5.0 sí hay algo que migrar.** Es el primer paquete desde
> julio que cambia el esquema de base de datos (`db/upgrade.php` añade un índice a
> `codelab_submission_results`, paso `2026091801`). Si subes el código nuevo y no completas
> "Actualizar base de datos de Moodle" en Notificaciones, el plugin queda funcionando con el
> código de la 1.5.0 pero el esquema de la versión anterior — y eso **no falla con un mensaje
> claro**: las consultas siguen funcionando, solo más lentas o con datos que no cuadran, un
> síntoma que nadie relaciona con "me faltó pasar por Notificaciones". Verifícalo entrando otra
> vez a *Administración del sitio → Notificaciones* después de instalar: no debe quedar ningún
> "Actualizar base de datos" pendiente.

Requisitos: Moodle 4.0–4.5, PHP 8.1+ (según tu versión de Moodle).

---

## 3. Configurar el motor de ejecución

En *Administración del sitio → Plugins → Módulos de actividad → CodeLab*, el plugin solo tiene
dos ajustes para el motor (desde la v1.2.0 ya no existen «Instancia auto-alojada» ni «Host de la
API»: el motor es siempre auto-hospedado, así que sobraban):

| Ajuste | Valor |
|---|---|
| URL de la API Judge0 | `http://judge0_server:2358` (overlay local) o `http://TU_HOST_JUDGE0:2358` |
| Clave API de Judge0 | el token que exige tu servidor (`X-Auth-Token`); vacío si el servidor propio no exige ninguno |

> **Si dejas la URL vacía, el complemento no sale a ningún servicio externo.** Una entrega falla
> con un mensaje claro ("El servidor de ejecución de código no está configurado…") en vez de
> enviar el código del estudiante a un tercero. No hay valor por defecto gestionado: hay que
> apuntar la URL a tu propio Judge0 (ver sección 4).

---

## 4. Entorno local de prueba (Docker)

Desde la raíz del repositorio:

```bash
cp .env.example .env              # OBLIGATORIO — ver nota abajo
docker compose up -d                                                     # Moodle + BD
docker compose -f docker-compose.yml -f docker-compose.judge0.yml up -d  # + motor Judge0
```

> **`.env` ya no es opcional.** Los `docker-compose*.yml` no traen contraseñas por defecto (para
> que ningún secreto quede escrito en un archivo versionado): si falta `.env`, Compose se detiene
> y dice qué variable falta. Antes de levantar el overlay de Judge0 hace falta además generar un
> token (`openssl rand -hex 24`), ponerlo en `JUDGE0_AUTH_TOKEN` de `.env` y ejecutar
> `make judge0-conf` — el detalle completo está en `../README.md`, sección 3.

Usuario y contraseña de Moodle: **las que pongas** en `MOODLE_ADMIN_USER` / `MOODLE_ADMIN_PASS`
de tu `.env` — Compose ya no trae ningún valor por defecto y se detiene si faltan (ver el aviso de
arriba). `.env.example` solo trae `admin` / `Admin1234!` como sugerencia de partida para este
entorno local de pruebas; para que valgan tienes que copiarlas a `.env` tú mismo.

> **Apple Silicon (Mac):** Judge0 auto-alojado es inestable bajo emulación. Como no usamos la nube
> gestionada, corre el motor en un **host/VM Linux x86 propio** (máquina del equipo, servidor de la
> universidad o VM que administres tú) y apunta la *URL de la API* a ese host.

---

## 5. Producción institucional

Recomendaciones para un despliegue real:

1. **Separar el motor de Moodle.** Ejecuta Judge0 en un host/VM Linux distinto del servidor de
   Moodle. El código no confiable nunca debe correr en la máquina de Moodle.
2. **Red y firewall.** Expón la API de Judge0 solo a la red interna donde está Moodle; no la
   publiques a Internet. Mantén `ENABLE_NETWORK=false` en `judge0.conf` (el código del estudiante
   sin salida de red).
3. **Recursos.** Ajusta en `judge0.conf` los límites (`CPU_TIME_LIMIT`, `MEMORY_LIMIT`,
   `MAX_PROCESSES_AND_OR_THREADS`, `MAX_QUEUE_SIZE`) y el número de `workers` (`COUNT`) según la
   carga esperada (número de estudiantes simultáneos).
4. **HTTPS.** Sirve Moodle tras HTTPS (proxy inverso). Configura `wwwroot` correctamente.
5. **Copias de seguridad.** Respalda la base de datos de Moodle (incluye ejercicios, casos y
   entregas) y `moodledata`.
6. **Cron de Moodle.** Asegura el cron para tareas en segundo plano (incluida la generación
   asíncrona de recomendaciones por IA si está activa).
7. **Secretos.** No dejes contraseñas por defecto: cámbialas en `.env` / `judge0.conf` y en la
   configuración de Moodle. Guarda las claves de API (Judge0, IA) de forma segura.

---

## 6. Configurar IA (opcional)

Si vas a usar las recomendaciones por IA:

1. En los ajustes de administración de CodeLab, selecciona el **proveedor** y proporciona la
   **clave de API** correspondiente.
2. El docente debe **activar** las recomendaciones en cada actividad donde las quiera.
3. Ten en cuenta la **privacidad**: al activarse, el código y el enunciado se envían al proveedor
   de IA (documentado en la Privacy API del plugin). Informa a los usuarios según tu normativa.

---

## 7. Verificación post-despliegue

- [ ] El plugin aparece en *Administración → Plugins → Módulos de actividad*.
- [ ] Se puede crear una actividad CodeLab en un curso.
- [ ] Una entrega de prueba se ejecuta y devuelve resultado (motor accesible).
- [ ] La nota llega al libro de calificaciones.
- [ ] Ejecutar el checklist de seguridad del sandbox (`mod/codelab/tests/SECURITY_TESTS.md`).
- [ ] (Si aplica) Las recomendaciones por IA se generan correctamente.
