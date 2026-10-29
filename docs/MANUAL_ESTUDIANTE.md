# Manual del estudiante — CodeLab

Guía rápida para resolver ejercicios de programación evaluados automáticamente dentro de Moodle.

---

## 1. Abrir el ejercicio

En tu curso, entra a la actividad **CodeLab** que asignó el docente. Verás:

- El **enunciado** del ejercicio.
- Un **editor de código** en el navegador.
- Los **casos de prueba** visibles (algunos pueden estar ocultos).

## 2. Escribir y probar tu código

1. Elige el **lenguaje** (si el docente permite varios).
2. Escribe tu solución en el editor. Puede venir con un **código inicial** de plantilla.
3. Usa **Ejecutar / Probar** para correr tu código contra los casos visibles **sin entregar**.
   Verás, por cada caso, si pasó o falló y la salida obtenida.
4. Ajusta tu código y vuelve a probar las veces que necesites.

> Tu código se ejecuta en un entorno **aislado y seguro**, con límites de tiempo y memoria. No
> tiene acceso a Internet.

## 3. Entregar

Cuando estés conforme, pulsa **Entregar**. El sistema:

1. Ejecuta tu código contra **todos** los casos (incluidos los ocultos).
2. Calcula tu **nota** según los puntos de los casos superados.
3. Registra la nota en tu libro de calificaciones.

Según la configuración del docente puede haber un **número máximo de intentos** y un **método de
calificación** (que se quede con tu mejor intento, el último, etc.). También puede haber una
**fecha de corte**: pasada esa fecha la actividad se cierra y ya no puedes entregar (verás
"❌ La actividad está cerrada. No se aceptan más entregas."); una fecha de entrega, si la hay, es
solo orientativa y no te bloquea.

## 4. Entender el resultado

- **Pasó / Falló** por caso: si tu salida coincidió con la esperada.
- **Salida obtenida vs esperada**: compara para encontrar diferencias (¡ojo con espacios y saltos
  de línea!).
- **Estados posibles**: `passed`, `failed` (salida distinta), `timeout` (tardó demasiado),
  `compile_error` (no compila), `error` (error en ejecución).
- **Recomendaciones por IA** (si el docente las activó): consejos para mejorar tu código. **No
  cambian tu nota**; son solo orientación.

## 5. Consejos

- La comparación ignora espacios al inicio/fin y saltos de línea finales, pero **no** los
  espacios internos: imprime exactamente lo pedido.
- Si usas "código envolvente", quizá solo debas escribir una **función**; lee bien el enunciado.
- Si te da `timeout`, revisa bucles que no terminan o algoritmos muy lentos.
- Prueba con los casos visibles antes de entregar para no gastar intentos.
