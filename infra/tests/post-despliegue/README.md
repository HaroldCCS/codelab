# Pruebas después de desplegar

Dos comprobaciones que se ejecutan contra el despliegue **real** en AWS, cuando el `apply` ya
terminó y el ALB de cada stack responde. Se usaron por primera vez el 2026-10-30; sus salidas de
ese día están en `evidencias-despliegue-aws/logs/` (raíz del vault).

## 1. Motor de ejecución — `pruebas-motor.py`

Desde tu equipo, sin dependencias (solo Python 3):

```bash
cd infra
JUDGE0_URL="$(cd judge0 && terraform output -raw judge0_url)" \
JUDGE0_TOKEN="$(grep '^judge0_auth_token' judge0/terraform.tfvars | cut -d'"' -f2)" \
python3 tests/post-despliegue/pruebas-motor.py > salida-motor.json
```

Qué debe salir (resultado del 2026-10-30, Judge0 1.13.1 en `t3.small`):

| Bloque | Esperado |
|---|---|
| `AUTH` | sin token **401**, token falso **401**, token correcto **200** |
| `FUNC` | los cinco lenguajes `Accepted` con salida `8`; la respuesta mala, `Wrong Answer` |
| `SEC-01` … `SEC-07` | `Time Limit Exceeded`, `MemoryError`, `BlockingIOError`, `Network is unreachable`, solo la caja de `isolate`, archivo que no persiste, `File too large` |
| `salud_final` | `Accepted`: el motor sigue vivo tras las pruebas |

Si **todo** sale `Internal Error`, el sandbox no está en cgroup v1: ver «Si algo falla» en
`infra/README.md`.

## 2. Moodle de extremo a extremo — `pruebas-moodle-e2e.php`

Comprueba la cadena entera: el estudiante ejecuta y entrega con la clase real del plugin
(`execution_request`), el motor de AWS califica y la nota llega al libro de calificaciones. Actúa
como el estudiante desde la CLI, **sin contraseñas**, y necesita antes el curso de demostración
(`scripts/seed-demo.php`, que crea el curso, el usuario `estudiante` y la actividad).

Se ejecuta en el nodo Moodle por SSM (el repositorio del plugin queda clonado en `/tmp/plugin`
tras la instalación):

```bash
export AWS_PROFILE=tesis-tf
I=$(aws autoscaling describe-auto-scaling-groups --auto-scaling-group-names codelab-moodle-asg \
      --query 'AutoScalingGroups[0].Instances[0].InstanceId' --output text)
aws ssm start-session --target "$I"
# dentro de la sesión:
sudo docker cp /tmp/plugin/scripts/seed-demo.php moodle:/tmp/seed-demo.php
sudo docker exec -u www-data moodle php /tmp/seed-demo.php
sudo docker cp /tmp/plugin/infra/tests/post-despliegue/pruebas-moodle-e2e.php moodle:/tmp/e2e.php
sudo docker exec -u www-data moodle php /tmp/e2e.php
```

Qué debe salir: `1_ejecutar_codigo_correcto` con 3/3 y nota 100, `2_ejecutar_codigo_incorrecto`
con 0/3, `3_entregar_codigo_correcto` con `submitted: true`, una fila en `4_entregas_en_bd` y
**100** en `5_libro_de_calificaciones`.

> **Cuidado:** el curso de demostración crea un usuario con una contraseña conocida (está en el
> repositorio, que es público). Úsalo solo en despliegues de prueba y destrúyelos al terminar.
