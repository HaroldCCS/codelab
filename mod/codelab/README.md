# CodeLab — Editor y evaluación automática de código para Moodle

CodeLab es un **módulo de actividad** de Moodle que permite a los estudiantes escribir, ejecutar
y entregar código directamente en la plataforma, con **calificación automática** basada en casos
de prueba definidos por el docente. Es una plataforma de evaluación automática de ejercicios de
programación integrada de forma nativa en Moodle.

## Características

- Editor de código en el navegador con múltiples lenguajes (Python, JavaScript, Java, C, C++,
  PHP, C#, Ruby, Go, Kotlin).
- Casos de prueba con entrada estándar y salida esperada; puntuación ponderada por caso.
- Ejecución del código en un **motor aislado** (Judge0), en infraestructura separada de Moodle,
  con límites de tiempo, memoria, procesos y red.
- Integración con el **libro de calificaciones** de Moodle.
- Métodos de calificación: mejor intento, último, primero o promedio.
- Casos de prueba visibles u ocultos para el estudiante.
- Soporte de **copia de seguridad/restauración** y de la **Privacy API** de Moodle.
- Interfaz en español e inglés.

## Requisitos

- Moodle 4.0 o superior (probado en 4.5).
- Un motor de ejecución **Judge0 auto-hospedado** (Docker), local o en un host/VM propio.

El motor se configura después de instalar, sin tocar el código del plugin. El plugin es genérico
y admite cualquier endpoint compatible con Judge0; **este proyecto usa siempre uno auto-hospedado**
(no servicios gestionados de terceros).

## Instalación

### Opción A — Desde un archivo ZIP (recomendada)

1. Descarga o genera el ZIP del plugin (ver "Empaquetado" abajo).
2. En Moodle: **Administración del sitio → Plugins → Instalar plugins**.
3. Sube el ZIP y sigue el asistente hasta "Actualizar base de datos de Moodle".

### Opción B — Manual

1. Copia esta carpeta a `TU_MOODLE/mod/codelab`.
2. Entra como administrador a **Administración del sitio → Notificaciones**.
3. Confirma la actualización de la base de datos.

## Configuración del motor de ejecución

En **Administración del sitio → Plugins → Módulos de actividad → CodeLab**. Desde la v1.2.0 el
plugin solo tiene estos dos ajustes para el motor (se retiraron «Host de la API» e «Instancia
auto-alojada»: el motor es siempre auto-hospedado, así que sobraban):

| Ajuste | Valor |
|---|---|
| URL de la API Judge0 | `http://judge0_server:2358` (overlay local) o `http://TU_HOST_JUDGE0:2358` |
| Clave API de Judge0 | el token que exige tu servidor (`X-Auth-Token`); vacío si no exige ninguno |

Si la URL queda vacía, el plugin no ejecuta nada: una entrega falla con un mensaje claro en vez
de salir a un servicio de terceros por su cuenta.

## Empaquetado (generar el ZIP)

El ZIP debe contener la carpeta `codelab/` en la raíz. Desde el directorio que contiene `codelab`:

```bash
zip -r codelab.zip codelab -x '*/.DS_Store' -x '*/.git/*'
```

En este repositorio también puedes usar `make plugin-zip` desde la raíz.

## Licencia

GNU GPL v3 o posterior — <http://www.gnu.org/copyleft/gpl.html>
