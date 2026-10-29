# Pruebas de CodeLab

Este directorio contiene la batería de pruebas del plugin.

## Contenido

| Archivo | Tipo | Qué cubre |
|---|---|---|
| `execution_result_test.php` | Unitaria (sin BD) | Comparación de salida (trim, CRLF, espacios) y serialización |
| `executor_test.php` | Unitaria (sin BD, salvo una prueba) | Lenguajes soportados, mapeo a IDs del motor, límites de recursos, y (única prueba con BD del archivo) que `judge0_executor` real, sin `judge0_api_url` configurado, corte antes de cualquier petición HTTP (SEC-02 / INC-58) |
| `executor_live_test.php` | Integración real, `@large` | Ejecuta contra un Judge0 real: se salta si falta `CODELAB_JUDGE0_URL` |
| `grader_test.php` | Unitaria + BD | Aritmética de la nota, mapeo de estados, métodos best/last/average en el gradebook |
| `submission_manager_test.php` | Unitaria + BD | Borradores, envíos, límite de intentos, estadísticas, y desempate por `id` (más el `NOT EXISTS` que evita filas duplicadas) en `get_best_submission()`/`get_last_submission()`/`get_all_latest_submissions()` cuando el `timemodified` coincide |
| `lib_test.php` | Unitaria + BD | Alta/edición/baja de instancia, casos de prueba, notas de usuario, `supports()` |
| `integration_test.php` | Integración | Flujo completo: borrador → envío → calificación → libro de notas |
| `grade_test.php` | Unitaria + BD | `\mod_codelab\grade_request` (la lógica de `grade.php`, calificación manual): capacidad `mod/codelab:grade`, `sesskey`, escritura en `codelab_submissions` y propagación al libro de calificaciones, rechazo de un `grade_override` fuera de rango |
| `execution_request_test.php` | Unitaria + BD | `\mod_codelab\execution_request` (la lógica de `execute.php`): capacidad `mod/codelab:execute`, `sesskey`, lenguaje no permitido, límite de 64 KB, casos de prueba vacíos, máximo de intentos, fecha de corte y «Permitir entregas desde» (bloquea `action=submit` antes de la fecha, sin bloquear `action=run`), y que una entrega rechazada **no llega al motor** (motor simulado inyectado) |
| `privacy_test.php` | Unitaria + BD | Privacy API (GDPR/Ley 1581): metadatos, contextos, exportación —incluyendo `codelab_submission_results`, INC-62— y borrado |
| `backup_test.php` | Integración + BD | Copia de seguridad y restauración: la actividad, sus casos de prueba y (con datos de usuario incluidos) la entrega y su `codelab_submission_results` se conservan (MDL-01) |
| `ai_provider_test.php` | Unitaria + BD (sin red) | Fábrica de proveedores de IA: selección según ajustes del sitio, sin llamadas reales |
| `ai_criteria_test.php` | Unitaria (sin BD) | Catálogo de criterios cualitativos que la IA puede evaluar |
| `ai_feedback_generator_test.php` | Unitaria + BD (proveedor simulado) | Armado del prompt, aislamiento de fallos y que la nota **nunca** cambia por la IA |
| `upgrade_test.php` | Unitaria + BD | `db/upgrade.php`: el paso de esquema 2026091801 (índice `idx_testcase`) se aplica cuando falta y no falla si ya existe (MDL-08) |
| `behat/codelab.feature` | Aceptación (Behat) | El docente crea la actividad; el estudiante la ve |
| `fixtures/mock_executor.php` | Utilidad | Motor de ejecución **simulado** (sin red) para pruebas deterministas |
| `fixtures/mock_ai_provider.php` | Utilidad | Proveedor de IA **simulado** (sin red): registra el prompt y devuelve una respuesta fija o lanza una excepción |
| `generator/lib.php` | Utilidad | Generador de instancias y casos de prueba para las pruebas |
| `SECURITY_TESTS.md` | Manual | Verificación del aislamiento del sandbox (timeout, memoria, red, disco) |

*(Este archivo estuvo desactualizado un tiempo: no listaba `privacy_test.php`, `backup_test.php`,
los tres de IA, `executor_live_test.php` ni `fixtures/mock_ai_provider.php`, pese a que ya
existían. Reconciliado el 2026-09-08 junto con la incorporación de `grade_test.php`.)

## Requisitos de diseño

Las pruebas **no llaman a Judge0**: usan `mock_executor`, que devuelve resultados programados. Así
son rápidas, deterministas y no dependen de la red. La verificación real del sandbox se hace por
separado con `SECURITY_TESTS.md` contra un motor Judge0 real.

**Única excepción:** `executor_test.php::test_execute_sin_judge0_configurado_devuelve_error_interno_sin_red`
instancia el `judge0_executor` real (no el mock) a propósito: el defecto que fija (SEC-02/INC-58, caída
por defecto a un servicio de terceros) está en la lógica de configuración de esa clase, que `mock_executor`
no reproduce por ser una implementación aparte. Sigue sin llamar a la red: la prueba solo ejercita la
rama que corta *antes* de la petición HTTP.

## Historia: INC-98, por qué `execute.php` no tenía pruebas y cómo se resolvió

Vale la pena dejar esto escrito porque es un patrón que puede repetirse en otro script de
entrada: **un endpoint que lee su entrada de una fuente que no se puede simular en pruebas es, por
definición, imposible de probar**, sin importar cuánto se quiera cubrir.

**El problema.** `execute.php` leía su entrada JSON **exclusivamente** de `php://input`, y la
primera comprobación del script —el `sesskey`, antes incluso de `require_login()`— dependía de
ese `$input`. Bajo la CLI de PHPUnit, `file_get_contents('php://input')` siempre devuelve `''`
(no hay una petición HTTP real detrás; se comprobó con PHP puro, sin Moodle, antes de dar el
diagnóstico por bueno). Eso significaba que **cualquier** inclusión del script en una prueba
cortaba en esa primera comprobación con `exit()`, antes de llegar a `require_login()`, a la
capacidad, al tamaño de 64 KB o al lenguaje permitido. Además, el script definía una función
global (`send_json_error()`), así que ni siquiera se podía incluir dos veces en el mismo proceso
de PHPUnit sin un error fatal de «no se puede redeclarar».

**Las dos salidas que se evaluaron entonces** (sustituir el *stream wrapper* `php` para inyectar
contenido en `php://input`, descartada por el riesgo de romper cualquier otro uso de `php://...`
en el mismo proceso; o extraer la lógica a una clase con entrada inyectable) se dejaron
documentadas aquí para que `dev-agent` decidiera.

**Lo que se aplicó: la segunda.** `execute.php` pasó de 182 a 48 líneas y ahora es solo la
envoltura HTTP: lee `php://input`, decodifica y delega en `\mod_codelab\execution_request`, cuyo
`handle()` recibe el array de entrada **por constructor** — el mismo patrón que ya usaban
`grader` (ejecutor inyectable) y `submission_manager` (actividad y contexto por constructor). Los
errores de validación ya no imprimen ni hacen `exit()`: se lanzan como `execution_exception`
(hereda de `moodle_exception`, conserva `$errorcode`), y quien invoque decide cómo representarlos
— JSON en el endpoint, aserción en una prueba. De paso se corrigió el orden de las comprobaciones
a `require_login()` → `require_capability()` → `confirm_sesskey()` (SEC-18): antes el `sesskey`
se validaba el primero de todos, que era además lo que impedía alcanzar el resto desde una prueba.

**Resultado:** `execution_request_test.php` cubre las siete comprobaciones que antes eran
inalcanzables (capacidad, `sesskey`, lenguaje, 64 KB, casos de prueba vacíos, máximo de intentos,
fecha de corte), sin tocar la red, sin `@runInSeparateProcess` y sin ningún truco de flujo: son
pruebas unitarias normales contra una clase normal. Al afirmar el resultado, se usa
`$e->errorcode` (p. ej. `'code_too_large'`), nunca el texto traducido del mensaje, que cambia con
el idioma del sitio.

**Ejecutada por primera vez el 2026-10-29** (Moodle 4.5.15, PHP 8.3.36, MariaDB 10.11): la
batería completa da **103 pruebas, 279 aserciones, 0 errores, 0 fallos, 2 omitidas** (las de
`executor_live_test.php`, que necesitan un Judge0 real). Antes de llegar ahí, la primera
ejecución dio 18 errores y 5 fallos: cuatro eran defectos del complemento (ver `CHANGELOG.md`,
1.6.1 y 1.6.2) y el resto, de las propias pruebas. Los más instructivos:

- `execution_request_test.php` llamaba al **motor real** sin saberlo: `execution_request` creaba
  su `grader` sin ejecutor inyectado. Ahora recibe el ejecutor por constructor, como `grader`.
- `grade_test.php` incluía `grade.php` con `include`, y su `require('../../config.php')` es
  relativo al directorio actual: bajo PHPUnit **nunca se resolvía**, y dos pruebas se tragaban
  ese error en un `catch (\Throwable)` y comparaban contra una base de datos intacta. La lógica
  pasó a `\mod_codelab\grade_request`; ya no hace falta `@runInSeparateProcess`.
- Las aserciones estrictas (`assertSame`, `assertContains`) fallan con ids leídos de la BD, que
  llegan como **cadenas**: hay que convertirlos con `(int)` antes de comparar.

Lección: escribir pruebas sin ejecutarlas no prueba nada. Ejecútalas siempre con `make test`.

## Cómo ejecutar (en un Moodle de desarrollo)

Las pruebas siguen el estándar de Moodle y se ejecutan con PHPUnit y Behat desde una instalación
de Moodle que tenga el plugin. **No** se ejecutan de forma aislada fuera de Moodle.

### PHPUnit

Con el entorno Docker del repositorio, un solo comando prepara todo (Composer, `init.php`) y
ejecuta la batería dentro del contenedor:

```bash
make test                         # desde repositorio/
make test ARGS="--filter grade"   # solo un subconjunto
```

En un Moodle de desarrollo propio:

```bash
# 1. Inicializar el entorno de pruebas (una vez)
php admin/tool/phpunit/cli/init.php

# 2. Ejecutar solo las pruebas de este plugin
vendor/bin/phpunit --filter mod_codelab
#   o por archivo:
vendor/bin/phpunit mod/codelab/tests/grader_test.php
```

### Behat

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags @mod_codelab
```

## Nota

Estas pruebas fueron redactadas conforme a la convención de Moodle (`advanced_testcase`,
generador de datos y `mock_executor`), pero **deben ejecutarse dentro de un Moodle real** para
obtener el resultado verde; no fue posible ejecutarlas en el entorno donde se escribieron.
