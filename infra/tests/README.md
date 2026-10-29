# tests/ — validación de la infraestructura sin gastar en AWS

Aquí no se crea nada en AWS. Es un módulo de Terraform **sin proveedores**
cuyo único trabajo es **renderizar los `user_data` reales** con el mismo motor
de plantillas que usará el `apply`, para poder inspeccionarlos y analizarlos.

## Por qué existe

Los `user_data` son el punto más frágil del despliegue: son scripts de bash que
Terraform genera por sustitución de texto, y un error ahí no se ve hasta que la
instancia ya arrancó (y falla en silencio). Dos ejemplos reales que este arnés
detectó en la primera versión de estos ficheros:

1. Un `${...}` dentro de un **comentario** de `moodle/templates/app.sh.tftpl`:
   Terraform intentaba evaluarlo como expresión y **el stack de Moodle ni
   siquiera llegaba a `plan`**.
2. Contraseñas inyectadas **sin comillas** en los `docker run` y dentro de un
   *heredoc* sin entrecomillar: cualquier contraseña con `$`, espacios o
   comillas se corrompía y Judge0 arrancaba con credenciales equivocadas.

Ambos se detectan en segundos aquí y en 15 minutos (y con factura) en AWS.

## Cómo se ejecuta

Desde `infra/`:

```bash
./validar.sh
```

O a mano, desde `infra/tests/`:

```bash
terraform init
terraform apply -auto-approve          # no crea nada: solo calcula outputs
terraform output -raw moodle_app > /tmp/moodle_app.sh
shellcheck -S style /tmp/moodle_app.sh
```

Los valores de prueba son **deliberadamente hostiles** (contraseñas con `$`,
comillas simples y dobles, espacios y backticks) para comprobar que el
tratamiento de secretos aguanta cualquier entrada.
