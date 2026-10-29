# Changelog — CodeLab

Todas las versiones notables de este plugin se documentan aquí.
El formato sigue, de manera aproximada, [Keep a Changelog](https://keepachangelog.com/es-ES/).

## [1.6.2] — 2026-10-29

La batería PHPUnit queda en verde por primera vez: **103 pruebas, 279 aserciones, 0 errores,
0 fallos** (2 omitidas a propósito: las que exigen un Judge0 real). Se ejecuta con `make test`.

### Corregido
- **Una entrega que iba a rechazarse se ejecutaba igualmente en el motor.** `execution_request`
  comprobaba el máximo de intentos, «Permitir entregas desde» y la fecha de corte **después** de
  enviar el código a Judge0, así que un estudiante sin intentos o fuera de plazo seguía consumiendo
  ejecuciones de código no confiable con cada clic en «Entregar». Ahora se valida antes y solo se
  ejecuta lo que se va a guardar. La respuesta al estudiante no cambia.

### Cambiado
- La lógica de `grade.php` pasa a `\mod_codelab\grade_request` (mismo patrón que
  `execution_request` con `execute.php`); `grade.php` solo lee parámetros y redirige. El
  comportamiento es el mismo: se comprobó en el navegador que la nota manual llega al libro de
  calificaciones.
- `execution_request` acepta un ejecutor inyectable (segundo argumento del constructor), como ya
  hacía `grader`. En producción no se pasa y se usa Judge0.

### Pruebas
- `execution_request_test`: usa el motor simulado y comprueba que una entrega rechazada no llega
  al motor. `grade_test`: prueba `grade_request` directamente, sin `include` ni procesos aparte.
- `backup_test`: la copia con datos de usuario usa `MODE_GENERAL` (en `MODE_IMPORT` Moodle
  bloquea el ajuste «users»). `upgrade_test`: carga `upgradelib.php` y retrasa la versión
  registrada para que `upgrade_mod_savepoint()` no aborte.
- `privacy_test` y `submission_manager_test`: comparan ids como enteros (la BD los devuelve como
  cadenas). `ai_feedback_generator_test`: declara el `debugging()` esperado del fallo de la IA.

## [1.6.1] — 2026-10-29

Primera ejecución real de la batería PHPUnit (Moodle 4.5.15, PHP 8.3.36, MariaDB 10.11):
destapó estos defectos del complemento, que ninguna lectura del código había visto.

### Corregido
- **La copia de seguridad de un curso con CodeLab terminaba en error fatal.**
  `backup_codelab_activity_task::encode_content_links(string $content)` estrechaba el tipo del
  parámetro que el padre (`backup_activity_task`) declara sin tipo, y PHP rechaza la clase al
  cargarla. Ahora el parámetro va sin tipo, como en el core.
- **La actividad no se clasificaba como «Evaluación».** `codelab_supports()` declaraba `?bool`
  y PHP convertía `MOD_PURPOSE_ASSESSMENT` (la cadena `'assessment'`) en `true` sin avisar.
  Ahora devuelve `mixed`.
- **Privacy API:** `get_users_in_context()` unía `course_modules` solo por `instance`, sin filtrar
  el módulo; con un `cmid` de otra actividad cuyo `instance` coincidiera, devolvía usuarios de
  CodeLab en un contexto ajeno. Se añade el filtro por `modules.name = 'codelab'`.

### Añadido
- Las 6 cadenas de capacidades (`codelab:view`, `submit`, `grade`, `viewallsubmissions`,
  `addinstance`, `execute`) en `lang/es` y `lang/en`. Faltaban: la pantalla de permisos mostraba
  los identificadores en bruto.

## [1.6.0] — 2026-09-19

### Corregido
- **«Permitir entregas desde» no hacía nada.** El ajuste se ofrecía en el formulario
  (`mod_form.php:140`), se guardaba en la base de datos (`db/install.xml:28`), se incluía en la
  copia de seguridad y estaba traducido a los dos idiomas — y **ninguna línea del complemento lo
  leía**: `grep -rn "allow_submissions_from_date"` no devolvía ni un consumidor fuera del propio
  formulario. El docente fijaba una fecha creyendo que la actividad estaba cerrada hasta entonces
  y los estudiantes podían entregar igual, sin que nada avisara a ninguno de los dos. Es el mismo
  defecto que el umbral del 60 % inventado: **el sistema afirmaba algo que no cumplía**.
  Ahora se comprueba donde de verdad se bloquea una entrega —`execution_request.php`, junto a la
  fecha de corte, que sí funcionaba— y además la vista desactiva el botón «Entregar» y dice desde
  cuándo se podrá. Se implementó en lugar de retirar el ajuste: el dato ya estaba modelado,
  respaldado y traducido, es un contrato estándar de Moodle que el docente reconoce de
  `mod_assign`, y quitarlo habría exigido un cambio destructivo de esquema para ahorrar diez
  líneas de comprobación.

### Añadido
- Cadena `before_allow_from` en `lang/es` y `lang/en` (170 por idioma, en paridad), con la fecha
  como parámetro para que el estudiante vea a partir de cuándo puede entregar.

## [1.5.0] — 2026-09-18

### Corregido
- **La vista del docente deja de escalar con el número de estudiantes** (MDL-07 e INC-107). Hacía
  **2N + 2T + 8** consultas por carga (N = estudiantes con entrega, T = casos de prueba): una por
  estudiante para su nombre, otra por estudiante para su nota final, y dos por caso de prueba para
  las estadísticas. Con 40 estudiantes y 10 casos eran **108 consultas**; con 100 y 10, **228**.
  Ahora son **10, constantes**. Los tres bucles pasan a una consulta cada uno: los usuarios con
  `get_records_list()`, las notas con `grader::compute_final_grades()`, y las estadísticas por
  caso con un `LEFT JOIN … GROUP BY` que conserva los casos sin ejecuciones.
- **Índice sobre `testcase_id` en `codelab_submission_results`** (MDL-08). Es la tabla que más
  crece —una fila por caso, por entrega, por estudiante— y las estadísticas la filtran por esa
  columna. **Este sí es un cambio de esquema**: `db/install.xml` declara el índice y
  `db/upgrade.php` lo añade en el paso `2026091801` con su `upgrade_mod_savepoint()`.
- **Texto de interfaz escrito a mano** (MDL-05): 33 cadenas repartidas por `view.php`,
  `submission.php` y `mod_form.php` (avisos, etiquetas de formulario, encabezados, marcadores de
  posición, títulos de botón y hasta el texto que el botón «Copiar» se pone a sí mismo desde
  JavaScript). Pasan a `get_string()`, con **26 claves nuevas** en `lang/es` y `lang/en`; las 7
  restantes reutilizan claves que ya existían (`testcases`, `student`, `language`, `tc_stdin`,
  `tc_expected_output`, `tc_points`, `tc_hidden`), que estaban definidas y sin usar en ese sitio.
- Un **sexto** resto del 60 % inventado, que el barrido anterior no vio porque no estaba escrito
  como `* 0.6`: `submission.php` coloreaba la barra de progreso con `$pct >= 60`. Ese porcentaje
  es de casos superados, no una nota, así que convertirlo en aprobado era otra cifra sin respaldo.
  Ahora el color solo distingue «todos los casos superados» de «no todos».

- **Un estudiante podía aparecer duplicado en la lista de entregas del docente.**
  `get_all_latest_submissions()` emparejaba con `MAX(timemodified)` mediante un `JOIN`, así que si
  un estudiante tenía dos entregas con el mismo `timemodified` —el caso que el desempate por `id`
  vino a resolver— **las dos empataban con el máximo y salían las dos**. No era un orden ambiguo:
  era una fila de más. Se sustituye por un `NOT EXISTS` que pide «la entrega de este usuario para
  la que no existe ninguna más reciente», con el desempate dentro de la condición, y devuelve
  exactamente una por estudiante.

### Añadido
- `grader::compute_final_grades()`: la versión en lote de `compute_final_grade()`, una consulta
  para muchos usuarios. **No recibe filas ya cargadas a propósito**: eso sería un contrato
  implícito sobre las filas que determinan una nota. Hace su propia consulta, su propio filtro y
  su propio orden, y las dos versiones terminan llamando a `select_by_method()`, que es la única
  que interpreta el método de calificación. Si alguien cambia la regla, la cambia en un sitio.
- `unknown_user`: la lista de entregas ya no asume que el usuario existe, ahora que los carga en
  lote y uno puede haber sido borrado.

### Cambiado
- **Toda consulta que ordena entregas por tiempo desempata ahora por `id`.** Con dos intentos
  creados o modificados en el mismo segundo, el orden quedaba al azar del motor de base de datos y
  podía cambiar entre dos cargas de la misma página: con «primer intento» y «último intento», dos
  notas distintas para los mismos datos. Alcanza a `grader::compute_final_grade()` y
  `compute_final_grades()`, a `submission_manager::get_best_submission()`,
  `get_last_submission()` y `get_all_latest_submissions()`, y a los **siete** ordenamientos por
  `ordering` de los casos de prueba, que tampoco es una columna única por esquema. La regla queda
  definida en `grader::ORDER_ATTEMPTS_ASC`, que la aplican las consultas donde el orden es un
  parámetro; las tres que lo llevan escrito dentro del SQL desempatan a mano con un comentario que
  apunta a esa constante, porque llevan alias de tabla y no pueden reutilizar la cadena.
- La exportación de la Privacy API tiene orden explícito: dos exportaciones de los mismos datos
  producen ahora el mismo documento, que es lo que se espera de una evidencia de cumplimiento.

## [1.4.0] — 2026-09-18

### Corregido
- **Las estadísticas del docente ya no usan un criterio distinto del que califica** (INC-102).
  `submission_manager::get_statistics()`, que `view.php` pinta en la vista del profesor, era una
  **cuarta** implementación del cálculo de la nota y tenía tres defectos a la vez:
  `AVG(grade)` sobre todas las entregas promediaba **intentos, no estudiantes** (quien entregaba
  ocho veces pesaba ocho veces en la media del curso); `MAX(grade)` por usuario codificaba
  **«mejor intento» a fuego**, de modo que en una actividad configurada en «último» o «promedio»
  la tasa de aprobación no correspondía a las notas reales; y el umbral de aprobado era
  `grade * 0.6`, un **60 % fijo** que ignoraba la nota de corte del curso. Ahora la media se
  calcula sobre la nota final de cada estudiante con `grader::compute_final_grade()`, y el umbral
  sale de `gradepass` del ítem del libro de calificaciones, leído en un único sitio
  (`submission_manager::get_passing_grade()`). El 60 % literal aparecía además en un **quinto**
  lugar que no estaba en el parte, `submission.php`, donde decidía el color del distintivo de
  nota: ahora usa el mismo umbral y se queda neutro si no hay ninguno configurado.
- **La calificación manual deja de ser una tercera ruta de cálculo** (INC-101). `grade.php`
  guardaba el `grade_override` y fijaba el libro de notas al valor de *esa* entrega, sin mirar
  `grading_method`: el libro mostraba un número distinto según quién lo hubiera escrito por
  última vez y el primer recálculo lo cambiaba. Ahora usa `grader::compute_final_grade()`, igual
  que la calificación automática y el recálculo. La corrección del docente no se pierde: entra
  como nota efectiva de esa entrega y el método decide qué entrega cuenta. El mensaje de
  confirmación dice ahora qué nota quedó registrada y con qué método, porque con «mejor»,
  «primer» o «promedio» puede no ser la que el docente acaba de teclear.
- **Cabecera GPL completa en los 30 archivos PHP de producción** (INC-105). Estaba solo en 3
  (`lib.php`, `version.php`, `view.php`); los otros 27 llevaban únicamente la línea corta. Es lo
  que el *code prechecks* del Directorio oficial de Moodle comprueba con el sniff
  `moodle.Files.BoilerplateComment.NoBoilerplateComment`. Insertado con un script idempotente que
  detecta la cabecera antes de tocar nada.

### Añadido
- La nota de corte real (`gradepass`) sustituye al 60 % literal. **Si nadie la ha configurado, la
  tasa de aprobación no se calcula**: `get_statistics()` devuelve `null` y la vista lo dice, en
  lugar de enseñar un porcentaje que nadie podría justificar. `get_statistics()` devuelve además
  `passing_grade` y `passed_students` para que la vista pueda explicarse.
- Cadenas `pass_rate_unavailable`, `pass_rate_no_gradepass` y `grade_saved` en `lang/es` y
  `lang/en` (143 por idioma, en paridad). `grade_saved` sustituye además el último texto de
  interfaz escrito a mano en `grade.php`.

### Cambiado
- La tarjeta «Tasa de aprobación» de la vista del docente muestra «Sin definir» y un aviso
  explicativo cuando falta la nota para aprobar, en lugar de un porcentaje inventado.

## [1.3.0] — 2026-09-09

### Corregido
- **El recálculo del libro de notas ya no pisa la corrección manual del docente ni el método de
  calificación** (MDL-03). `codelab_update_grades()` es el gancho que Moodle invoca en un
  «Recalcular todas las notas ahora», en el cron de calificaciones o en un `reset` parcial, y no
  pasa por `grader`. `codelab_get_user_grades()` (`lib.php:194`) hacía siempre `MAX(grade)`:
  ignoraba `grading_method` y no sabía siquiera que `grade_override` existía
  (`grep -n "grade_override" lib.php` → 0). Una actividad con `grading_method='first'` y notas
  40/90/60 pasaba de 40 a 90 sola, y cualquier nota corregida a mano se perdía, en silencio y sin
  que el docente lo viera hasta que un estudiante reclamara. El cálculo vive ahora en un único
  sitio, `grader::compute_final_grade()`, que usan las dos rutas.
- **El backup ya no pierde datos** (MDL-01). `backup_codelab_stepslib.php` no incluía
  `solution_code`, `runner_code`, `ai_feedback_enabled` ni `ai_criteria` de la actividad, ni
  `ai_feedback`, `ai_feedback_status` ni `ai_feedback_time` de cada entrega, y **omitía entera la
  tabla `codelab_submission_results`** — el detalle por caso de prueba, que es la misma
  información que la Privacy API acababa de declarar como dato personal en 1.2.0. Cualquier copia
  de seguridad, restauración, duplicado de actividad o importación entre cursos lo destruía sin
  aviso. `restore_codelab_stepslib.php` recibe la tabla nueva y `process_codelab_submission()`
  registra ahora el mapeo `codelab_submission`, sin el cual los resultados no podían reengancharse
  a su entrega. **No hay cambio de esquema**: `db/install.xml` no se toca y `db/upgrade.php` no
  necesita paso nuevo; lo que cambia es qué se emite y qué se lee.
- **«Restablecer curso» limpia por fin los datos de CodeLab** (MDL-02). Faltaban las tres
  funciones que Moodle busca por nombre (`codelab_reset_course_form_definition`,
  `codelab_reset_course_form_defaults`, `codelab_reset_userdata`), y sin ellas el módulo
  simplemente no se incluye en el restablecimiento: el docente veía «restablecido con éxito» y las
  entregas, notas y recomendaciones de IA del semestre anterior seguían intactas cuando entraba el
  grupo nuevo. Además de funcional, era un problema de privacidad.

### Añadido
- `grader::compute_final_grade()`: método público y estático, fuente **única** del cálculo de la
  nota final. Es estático a propósito, para que el libro de calificaciones lo invoque en un
  recálculo masivo sin construir un cliente del motor de ejecución por cada usuario. Calcular una
  nota no toca ni el motor ni la IA.
- Cadena `reset_submissions` en `lang/es` y `lang/en` (140 por idioma, en paridad).

### Cambiado
- Con `grading_method = 'average'`, la media pasa a incluir el intento que se está entregando en
  ese momento. Antes la ruta en vivo lo excluía (todavía figura como borrador cuando se calcula) y
  el recálculo posterior sí lo incluía, así que las dos rutas daban notas distintas para la misma
  actividad. Es el único cambio de nota visible de esta versión, y es la consecuencia de tener un
  solo cálculo en lugar de dos.

## [1.2.0] — 2026-09-09

### Seguridad
- **`execute.php` valida ahora en el orden canónico de Moodle** (SEC-18): `require_login()` →
  `require_capability()` → `confirm_sesskey()`. Antes el `sesskey` se comprobaba el primero de
  todos, antes incluso de saber quién era el usuario. Ninguna escritura ni ejecución ocurre antes
  de la validación del `sesskey`, que es lo que exige la protección contra CSRF: la primera
  escritura (`save_draft()`) y la llamada al motor ocurren mucho después.
- **La Privacy API vuelve a declarar y exportar todo lo que el plugin almacena** (SEC-10 /
  INC-62). `classes/privacy/provider.php` declaraba **una sola tabla** y solo 7 de los 15 campos
  con datos del estudiante de `codelab_submissions`, y **no declaraba en absoluto**
  `codelab_submission_results`, donde se guardan `actual_output` y `stderr`: la salida del
  programa que escribió el estudiante. `export_user_data()` tampoco los exportaba. El resultado
  era que un estudiante que ejercía su derecho de acceso recibía su código y su nota, pero no
  las salidas que produjo su programa, que sí seguían almacenadas. Ahora se declaran las dos
  tablas al completo y la exportación adjunta a cada entrega sus resultados por caso de prueba.
  El borrado ya era correcto y no se ha tocado. Afecta al cumplimiento de la Ley 1581 de 2012,
  que reconoce el derecho de **acceso**, no solo el de supresión.
- **El motor de ejecución ya no puede caer en un servicio gestionado de terceros** (SEC-02).
  `classes/executor/judge0_executor.php` tenía como valor por defecto la API pública de
  RapidAPI; como el operador `?:` también se dispara con cadena vacía y con el `false` que
  devuelve `get_config()` cuando el ajuste no existe, un Moodle recién instalado enviaba el
  código del estudiante a un tercero sin que nadie se enterase. Ahora la URL y el token se
  normalizan con `trim((string)…)`, no hay ningún valor por defecto externo y, si el motor no
  está configurado, `execute()` devuelve un error claro (`executor_not_configured`) en lugar de
  salir a internet. `get_headers()` conserva solo la rama auto-hospedada (`X-Auth-Token`);
  se eliminaron las cabeceras `X-RapidAPI-*` y las propiedades `$api_host` y `$self_hosted`.
  Cumple la regla dura de `contexto.md` §5.1.
- **Una entrega solo se puede leer desde su propia actividad** (SEC-04).
  `classes/submission_manager.php::get_submission_with_results()` buscaba la entrega solo por
  `id`, mientras que `submission.php` toma el `sid` de la URL y comprueba la capacidad sobre el
  contexto del `cmid`. Un docente podía cambiar el `sid` en la barra de direcciones y leer el
  código, la nota y la retroalimentación de estudiantes de cualquier curso de la instalación.
  La consulta ahora exige también `codelabid`, igual que ya hacía `grade.php:27`.
- **«Mostrar casos de prueba a estudiantes» vuelve a cumplirse** (SEC-05). `view.php` filtraba
  los casos solo por `is_hidden` e ignoraba el ajuste `testcases_visible`, de modo que las
  entradas y las **salidas esperadas** viajaban al navegador dentro de `data-codelab-config` y
  se leían con «Inspeccionar». Con el ajuste desactivado, todos los casos se tratan ahora como
  ocultos: el estudiante sigue viendo cuántos son y cuántos ha superado, pero no su contenido.
  `execute.php` ya lo respetaba en la respuesta AJAX.
- `fullname()` se escapa con `s()` en las vistas del docente (SEC-16): `submission.php` y la
  tabla de entregas de `view.php`, que se construye con `html_writer::table()` y no escapa las
  celdas. Preventivo: hoy Moodle limpia el nombre al guardarlo en el perfil.

### Cambiado
- `view.php` ya no imprime etiquetas `<script>` en línea (INC-13). El editor se carga con
  `$PAGE->requires->js()` y su configuración viaja en el atributo `data-codelab-config` del
  contenedor `#codelab-workspace`, que `js/editor.js` lee al arrancar. Elimina la dependencia
  del orden de carga de scripts y se alinea con las recomendaciones del directorio de plugins
  de Moodle. `js/editor.js` mantiene un respaldo a `window.CODELAB_CFG` por compatibilidad.
- El valor por defecto de la URL del motor pasa a `http://judge0_server:2358`, el servidor del
  overlay `docker-compose.judge0.yml` (antes, la API pública de RapidAPI).
- La espera del resultado deja de ser un tope fijo de 24 s y se calcula a partir del límite de
  tiempo de la actividad más 10 s de margen para cola y compilación (SEC-07, parte del plugin).
  Con el tope anterior, las opciones de 15 s y 30 s del formulario eran inalcanzables y el
  estudiante perdía el intento con «El servidor de ejecución tardó demasiado». La otra mitad
  de la corrección ya está aplicada en el motor: `WALL_TIME_LIMIT=45` en
  `judge0/judge0.conf.example` y en `infra/judge0/templates/app.sh.tftpl`, que antes cortaba
  a los 25 s.
- **`execute.php` se parte en envoltura HTTP y lógica invocable** (INC-98). La lógica y las
  comprobaciones pasan a `classes/execution_request.php`, que recibe la entrada **ya decodificada
  por constructor** en lugar de leerla de `php://input`; `execute.php` queda en 48 líneas que solo
  leen el cuerpo, lo decodifican y delegan. Es el mismo patrón de inyección que ya usaban `grader`
  (ejecutor inyectable) y `submission_manager`. Motivo: mientras la lógica leía del flujo de la
  petición, **ninguna prueba podía alcanzarla** —bajo la CLI de PHPUnit `php://input` siempre está
  vacío, así que el script cortaba en la primera comprobación—, y era el único punto de entrada
  sin cobertura, justo el que recibe el código no confiable del estudiante. Las 14 comprobaciones
  del archivo original están todas presentes, ninguna se ha relajado.
- El antiguo `send_json_error()`, que imprimía y hacía `exit()`, se sustituye por
  `classes/execution_exception.php` (hereda de `moodle_exception`, así que conserva `$errorcode` y
  la traducción). Un `exit()` dentro de la lógica es lo que la volvía imposible de probar: mata el
  proceso de PHPUnit. El JSON de error que ve el navegador es idéntico al de antes.
- Reescritas para el modo auto-hospedado las descripciones `judge0_api_url_desc` y
  `judge0_api_key_desc` en `lang/es` y `lang/en`.
- `README.md` del plugin: la tabla de configuración documenta solo el modo auto-hospedado.
- `$plugin->version` sube de `2026070600` a `2026090900` y `$plugin->release` de `1.1.0` a
  `1.2.0`. El contador ha subido cuatro veces porque la versión se cerró cuatro veces antes de que
  el código lo estuviera: `2026090700` (INC-54, antes de las correcciones de seguridad),
  `2026090701` (INC-67, antes de SEC-10), `2026090702` (INC-62, antes del refactor) y
  `2026090900` (INC-98, esta). Sin el bump, una instalación existente no ejecuta la actualización ni
  refresca el `js/editor.js` servido desde la caché por revisión. **No hay cambios de esquema**
  en esta versión, así que `db/upgrade.php` no necesita un paso nuevo; `$plugin->requires` y
  `$plugin->supported = [400, 405]` se mantienen (el entorno de pruebas es Moodle 4.5).

### Añadido
- Cadena `executor_not_configured` en `lang/es` y `lang/en`: mensaje que ve el estudiante
  cuando no hay servidor de ejecución configurado.
- Cadena `invalidsubmission` en `lang/es` y `lang/en`. `submission.php:37` ya lanzaba
  `moodle_exception('invalidsubmission', 'mod_codelab')`, pero la cadena no existía en ningún
  idioma: el error salía como `[[invalidsubmission]]`. Es justo el camino que activa SEC-04.
- `classes/execution_request.php` y `classes/execution_exception.php`, los dos archivos que
  salen de partir `execute.php`. No añaden dependencias: siguen instalándose en un Moodle limpio
  sin composer ni build.
- **16 cadenas de privacidad por idioma** (32 en total) para los campos que SEC-10 pasa a
  declarar: 8 de `codelab_submissions` y 8 de `codelab_submission_results` (la tabla más sus
  7 campos). Sin ellas, la **página de política de datos del sitio** —la que se enseña en la
  sustentación— mostraría `[[privacy:metadata:…]]` en cada hueco.

### Eliminado
- Los ajustes de administración **«Host de la API»** (`judge0_api_host`) e **«Instancia
  auto-alojada»** (`judge0_self_hosted`) y sus cuatro cadenas de idioma en cada lengua. Existían
  únicamente para hablar con un servicio gestionado de terceros; el modo auto-hospedado ya no es
  opcional. Total de cadenas tras esta versión: **139 en `es` y 139 en `en`**, en paridad
  (eran 125; −4 por idioma al retirar estos dos ajustes, +2 de SEC-02/SEC-04, +16 de SEC-10).

> **Nota sobre el número de versión.** Esta entrega empezó como `1.1.1` (solo INC-54) y se
> renumeró a `1.2.0` al incorporar las correcciones de seguridad: se **retiran dos ajustes de
> administración** y cambia el comportamiento visible para el estudiante cuando
> `testcases_visible` está desactivado. Un parche no debería quitar superficie de configuración.

## [1.1.0] — 2026-07-07

### Añadido
- **Recomendaciones por IA** (opcionales) sobre el código del estudiante, con proveedores
  Claude, Gemini y OpenAI (`classes/ai/`). Se generan al entregar y **nunca afectan la nota**.
- **Criterios cualitativos** configurables como criterios de la IA (`classes/ai/criteria.php`:
  legibilidad, eficiencia, buenas prácticas, nombres, manejo de errores, modularidad).
- Ajustes de administración para la IA (proveedor, clave de API y modelo) en `settings.php`.
- Campo `runner_code` (código envolvente con marcador `{{CODE}}`).
- **Batería de pruebas** completa: PHPUnit (grader, executor, submission_manager, lib,
  integración, privacy, backup/restore, IA), prueba `@large` real contra Judge0, Behat y un
  checklist de seguridad del sandbox.
- `$plugin->supported = [400, 405]` (rango de versiones de Moodle).

### Corregido
- `codelab_delete_instance` pasaba un array como condición a `delete_records` (dejaba resultados
  huérfanos); ahora usa `delete_records_list`.

### Eliminado
- Carpeta `amd/src/` (código muerto, no referenciado). El front se carga desde `js/`.

## [1.0.0] — 2026-04-27

### Añadido
- Módulo de actividad nativo de Moodle (`mod_codelab`).
- Editor de código en el navegador con soporte multi-lenguaje.
- Casos de prueba con entrada/salida esperada y puntuación ponderada.
- Ejecución en motor aislado (Judge0) auto-hospedado vía Docker, configurable desde
  administración.
- Integración con el libro de calificaciones (`grade_item_update`, `update_grades`).
- Métodos de calificación: mejor / último / primero / promedio.
- Copia de seguridad y restauración (Moodle 2 backup API).
- Soporte de la Privacy API.
- Idiomas español e inglés.
