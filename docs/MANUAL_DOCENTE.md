# Manual del docente — CodeLab

Guía para crear y calificar ejercicios de programación con evaluación automática dentro de
Moodle. Este manual asume que el plugin **ya está instalado** (si no, ver `docs/GUIA_DESPLIEGUE.md`).

---

## 1. Crear una actividad CodeLab

1. Entra al curso y **activa la edición** (botón "Activar edición").
2. En la sección deseada, **"Añadir una actividad o recurso"** → elige **CodeLab**.
3. Completa el formulario (secciones abajo) y guarda.

## 2. Ajustes del ejercicio

### General
- **Nombre** y **Descripción** del ejercicio (el enunciado que verá el estudiante).

### Editor y lenguajes
- **Lenguaje por defecto**: el que aparece seleccionado al abrir el editor.
- **Lenguajes permitidos**: los que el estudiante podrá elegir (Python, JavaScript, Java, C,
  C++, PHP, C#, Ruby, Go, Kotlin).
- **Código inicial** (*starter code*): plantilla que aparece en el editor.
- **Código envolvente** (*runner*, opcional): código que rodea al del estudiante usando el
  marcador `{{CODE}}`. Útil para que el estudiante solo escriba una función y tú te encargues de
  leer la entrada e imprimir la salida.

### Límites de ejecución
- **Tiempo límite** (segundos) y **memoria** (MB) por ejecución. Se aplican en el sandbox.

### Entregas y calificación
- **Intentos máximos** (0 = ilimitados).
- **Método de calificación**: mejor, último, primero o promedio de los intentos enviados.
- **Casos de prueba visibles**: si el estudiante ve o no los casos.
- **Fecha de entrega**: informativa — aparece en el listado de la actividad, pero no impide
  entregar después.
- **Fecha de corte** ("sin entregas después de esta fecha"): esta sí es dura. Pasada esa fecha,
  el estudiante ya no puede entregar (el botón se desactiva y, si lo intenta igual, el servidor
  también lo rechaza).

### Retroalimentación por IA (opcional)
- Si activas **Recomendaciones por IA**, el sistema genera comentarios cualitativos sobre el
  código del estudiante. **Nunca afectan la nota** (la nota siempre sale de los casos de prueba).
- Requiere que el administrador haya configurado un proveedor de IA (ver guía de despliegue).

## 3. Definir los casos de prueba

Cada caso tiene:

| Campo | Significado |
|---|---|
| **Nombre** | Etiqueta del caso (p. ej. "Suma positivos") |
| **Entrada (stdin)** | Lo que recibe el programa por entrada estándar |
| **Salida esperada** | La salida exacta con la que se compara (se ignoran espacios al inicio/fin y saltos de línea finales) |
| **Puntos** | Peso del caso en la nota |
| **Oculto** | Si el estudiante no debe ver este caso |

La **nota** es proporcional a los puntos de los casos superados sobre el total, escalada a la
nota máxima. Ejemplo: casos de 60 y 40 puntos, pasa solo el primero → 60/100.

> Consejo: combina casos **visibles** (para guiar) con casos **ocultos** (para evitar que el
> estudiante "programe para el test").

## 4. Revisar entregas y notas

- La actividad muestra las entregas de los estudiantes, cuántos casos pasaron y la nota.
- La nota se refleja automáticamente en el **libro de calificaciones** del curso.
- Puedes **sobrescribir la nota** manualmente si lo necesitas. La corrección no se pierde, pero
  **no sustituye directamente la nota final si tu método de calificación no es "último intento"**:
  entra como la nota de esa entrega concreta y es el **método de calificación** (mejor, último,
  primero o promedio) el que decide cuál de las entregas del estudiante cuenta al final. Al
  guardar, el propio sistema te lo confirma con un mensaje como *"Calificación guardada. Nota
  registrada en el libro de calificaciones: 80 (método: Mejor intento)."* — si esa nota no es la
  que acabas de teclear, es porque el método eligió otra entrega, no un error.

### Estadísticas del curso

Encima de la lista de entregas, la actividad muestra cuatro indicadores: **Estudiantes totales**,
**Entregas totales**, **Nota media** y **Tasa de aprobación**, y debajo una tabla de
**rendimiento por caso de prueba** (qué porcentaje de estudiantes superó cada caso).

> **La tasa de aprobación puede aparecer como "Sin definir".** Esto pasa cuando el curso **no
> tiene configurada una nota para aprobar** en esta actividad: en ese caso el sistema no inventa
> un porcentaje (antes usaba un 60 % fijo que no tenía respaldo; ya no). Debajo de los
> indicadores verás un aviso explicándolo. Para que la tasa se calcule, configúrala en **el
> libro de calificaciones del curso → Configuración del ítem de calificación → Nota para
> aprobar** de esta actividad CodeLab; en cuanto quede definida, el porcentaje aparece solo, sin
> tocar nada más aquí.

## 5. Ejemplo rápido (Suma)

- **Enunciado:** "Lee dos enteros y muestra su suma."
- **Código inicial (Python):**
  ```python
  a, b = map(int, input().split())
  # escribe tu solución
  ```
- **Casos:**

  | Nombre | Entrada | Salida | Puntos |
  |---|---|---|---|
  | Positivos | `3 7` | `10` | 40 |
  | Negativos | `-5 3` | `-2` | 30 |
  | Cero | `0 0` | `0` | 30 |

Más ejemplos por lenguaje en `EJEMPLOS.md`.

## 6. Preguntas frecuentes

- **La ejecución da "timeout".** El código superó el tiempo límite; súbelo en los ajustes o
  revisa bucles infinitos.
- **"compile_error".** El código no compila (lenguajes compilados); el estudiante verá el
  mensaje del compilador.
- **No aparece la nota en el libro.** Verifica que la entrega esté en estado "enviada" y que el
  motor de ejecución esté configurado y accesible.
