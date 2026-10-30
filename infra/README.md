# Infraestructura (AWS + Terraform)

Despliegue de la plataforma en AWS mediante **Terraform**, en **dos stacks independientes**:

- **`judge0/`** — el motor de ejecución. Es autónomo: a cada universidad solo se le entregan
  la **URL** y el **token** que este stack imprime al final. Puede vivir en su propia cuenta.
- **`moodle/`** — Moodle con el plugin CodeLab ya instalado, apuntando a un Judge0.

El **bucket S3** de los estados de Terraform (lo único que sobrevive a los `destroy`) ya está
creado y fijado en los backends de ambos stacks:

```
terraform-state-tesis-372814632174213946792314786213798123   (us-east-2, cuenta «tesis» 107758684131)
```

No hace falta `-backend-config` ni el paso de bootstrap. El módulo `bootstrap/` se conserva solo
como referencia para desplegar desde cero en otra cuenta.

## Requisitos

- Cuenta de AWS con credenciales configuradas (`aws configure`).
- **Terraform >= 1.11** (por `use_lockfile`, que bloquea el state en el propio S3 y evita que dos
  personas hagan `apply` a la vez). Si tu equipo tiene una versión anterior, comenta esa línea en
  los dos `providers.tf` y coordinaos a mano.
- Opcional para validar en local: `shellcheck`.

### Antes del primer despliegue en una cuenta (lecciones del 2026-10-30)

1. **Credenciales para Terraform.** Si inicias sesión con `aws login --profile tesis`, el
   proveedor de AWS de Terraform **no** entiende ese tipo de sesión (*No valid credential sources
   found*), y `aws configure export-credentials` da claves que caducan en una hora, a mitad de un
   despliegue. Crea un perfil que las renueve solo y úsalo para todo:
   ```ini
   # ~/.aws/config
   [profile tesis-tf]
   region = us-east-1
   credential_process = aws configure export-credentials --profile tesis --format process
   ```
   ```bash
   export AWS_PROFILE=tesis-tf
   aws sts get-caller-identity      # debe dar la cuenta 107758684131
   ```
2. **Rol de Auto Scaling.** En una cuenta donde nunca se creó un Auto Scaling Group, AWS crea el
   rol `AWSServiceRoleForAutoScaling` en ese momento y el grupo intenta usarlo segundos antes de
   que se propague: el `apply` falla con *Access denied when attempting to assume role …
   AWSServiceRoleForAutoScaling*. Créalo antes (si ya existe, el comando falla sin efecto):
   ```bash
   aws iam create-service-linked-role --aws-service-name autoscaling.amazonaws.com
   ```
   Si te pasa igualmente, basta con repetir `terraform apply`.
3. **El bucket del estado está en `us-east-2`**, aunque los recursos se crean en `us-east-1`.
   Por eso el `backend "s3"` lleva `region = "us-east-2"`: con otra región responde 403.

## Principios de diseño (decisiones tomadas)

- **Sin RDS.** Las bases de datos corren en Docker sobre una EC2 pequeña (`t3.micro`):
  Postgres+Redis para Judge0, MariaDB para Moodle.
- **Instancias pequeñas** + **ALB** + **Auto Scaling Group** con escalado **por CPU**.
- **Sin NAT Gateway ni Elastic IP** (cuestan / persisten): subredes públicas protegidas por
  Security Groups estrictos.
- **Destrucción total:** todo vive dentro de una VPC gestionada por Terraform; los discos se
  borran con la instancia; el ALB no tiene *deletion protection*. `terraform destroy` no deja
  nada salvo el bucket S3 del state.
- **Depuración por SSM**, no por SSH: las instancias llevan un rol IAM con
  `AmazonSSMManagedInstanceCore` (`enable_ssm = true`), así que puedes abrir una shell sin tener
  el puerto 22 abierto. Ver "Si algo falla" más abajo.

## Validar antes de gastar

```bash
cd infra
./validar.sh
```

Comprueba formato, sintaxis/esquema, que las cuatro plantillas de `user_data` **renderizan de
verdad**, y pasa `shellcheck` sobre los scripts resultantes. Ver `tests/README.md`.

## Paso 1 — Desplegar Judge0

```bash
cd judge0
cp terraform.tfvars.example terraform.tfvars

# Genera los secretos (solo [A-Za-z0-9_.-]: Judge0 lee judge0.conf
# como fichero de entorno y otros símbolos lo rompen):
openssl rand -hex 16   # -> postgres_password
openssl rand -hex 16   # -> redis_password
openssl rand -hex 24   # -> judge0_auth_token

terraform init
terraform apply        # tarda ~15 min: los nodos reinician por cgroup v1
```

Outputs importantes: **`judge0_url`** y **`auth_required`**.

> ⚠️ **`judge0_auth_token` es obligatorio.** Ya no tiene valor por defecto: un `plan` sin él
> falla en vez de publicar un ejecutor de código abierto. El ALB de Judge0 está abierto a
> internet (esa es su función: dar servicio a otras universidades). Sin token, cualquiera puede
> enviar código arbitrario a tu infraestructura y ejecutarlo, con cargo a tu cuenta de AWS. El
> plugin CodeLab ya envía el token en la cabecera `X-Auth-Token`
> (`classes/executor/judge0_executor.php`), así que activarlo no cuesta nada.

> `moodle_admin_password` tampoco tiene ya valor por defecto: ponla en `terraform.tfvars`
> (ignorado por git). Una contraseña por defecto escrita en el repositorio acaba siendo la
> contraseña real del sitio.

## Paso 2 — Desplegar Moodle

```bash
cd ../moodle
cp terraform.tfvars.example terraform.tfvars
#  - judge0_url        = el output del paso 1 (SIN barra final)
#  - judge0_auth_token = el MISMO token del paso 1
#  - cambia los demás secretos
terraform init
terraform apply        # la infraestructura, ~5 min; Moodle tarda ~30 min más en instalarse
```

Output: **`moodle_url`**. La primera instancia descarga Moodle, copia el plugin, instala y
configura el motor Judge0 automáticamente. **Medido el 2026-10-30 en `t3.small`: unos 29 minutos
desde que arranca la instancia** (solo descomprimir Moodle 4.5 en EFS y darle dueño, ~17 min).
Hasta entonces el ALB responde 502: es normal. Por eso la gracia del ASG es de 3600 s.

> **Navegador:** el despliegue sirve solo HTTP. Chrome con «Usar siempre conexiones seguras»
> convierte la URL a `https://` y muestra una página de error (el puerto 443 no escucha). Desactívalo
> para ese sitio o usa otro navegador hasta que haya HTTPS (ver Limitaciones).

## Paso 3 — Comprobar el despliegue

Dos pruebas automáticas, en `tests/post-despliegue/` (ver su README):

```bash
# Motor: token, cinco lenguajes y los 7 casos de SECURITY_TESTS.md
JUDGE0_URL=<judge0_url> JUDGE0_TOKEN=<token> python3 tests/post-despliegue/pruebas-motor.py

# Moodle -> plugin -> Judge0 -> libro de calificaciones (se ejecuta en el nodo Moodle, por SSM)
```

## Cómo arranca todo (para entender los tiempos)

Los `user_data` no son "un script que corre y ya"; hay dependencias entre máquinas:

```
  nodo de datos                nodo de aplicación
  ─────────────                ──────────────────
  apt + docker                 apt + docker
  docker run postgres/redis    ├─ espera a que el nodo de datos escuche  ← clave
  (2-4 min)                    ├─ (judge0) reboot por cgroup v1
                               ├─ docker pull  (imagen grande)
                               └─ arranca / instala Moodle
                                        ↓
                               el ALB lo marca "healthy"  → apply termina
```

Los nodos de aplicación **esperan activamente** a que la base de datos esté escuchando
(bucle con `nc`, hasta 20 min). Sin esa espera, la instalación falla en el primer intento porque
los dos grupos de instancias arrancan a la vez.

Por eso `health_check_grace_period` es de 1200 s y `wait_for_capacity_timeout` de 25 min: con
valores más bajos, el ASG mata las instancias antes de que terminen de arrancar y entra en un
bucle de reemplazos que parece "no funciona" pero es solo impaciencia.

## Actualizar el plugin en un stack ya desplegado

**El arranque de las instancias NO actualiza el plugin.** `moodle/templates/app.sh.tftpl:75`
entra en el bloque de instalación solo `if [ ! -f "$DONE" ]` (la marca `/mnt/efs/.installed`),
así que en cuanto Moodle está instalado ni se vuelve a clonar el repositorio ni se copia
`mod/codelab/` ni se ejecuta ninguna actualización. Un `terraform apply` con un `plugin_git_ref`
nuevo **no** cambia nada dentro de Moodle: solo afecta a instancias que se instalen desde cero.

Y **copiar el código no basta**: desde la 1.5.0 el plugin trae cambios de esquema en
`db/upgrade.php` (el índice `idx_testcase`, savepoint `2026091801`). Si se copia el código sin
ejecutar la actualización, el PHP queda nuevo y la base de datos vieja.

Actualización manual, desde una sesión SSM en cualquier nodo de aplicación
(`enable_ssm = true`; ver §Si algo falla):

```bash
sudo -i
MOODLE_DIR=/mnt/efs/moodle
IMG=$(docker ps --filter name=moodle --format '{{.Image}}' | head -1)

# 1. Código nuevo del plugin (fija el ref a una etiqueta, no a 'main')
rm -rf /tmp/plugin && git clone --depth 1 --branch v1.5.0 <URL-DEL-REPO> /tmp/plugin
rm -rf "$MOODLE_DIR/mod/codelab"
cp -r /tmp/plugin/mod/codelab "$MOODLE_DIR/mod/codelab"
chown -R 33:33 "$MOODLE_DIR"

# 2. Ejecutar la actualización (equivale a la pantalla de Notificaciones)
docker run --rm -v "$MOODLE_DIR":/var/www/html -v /mnt/efs/moodledata:/var/moodledata "$IMG" \
  php /var/www/html/admin/cli/upgrade.php --non-interactive
```

Se hace **una sola vez** aunque el ASG tenga varios nodos: el docroot está compartido en EFS.
Antes de tocar nada, una instantánea del volumen del nodo de datos (§Limitaciones conocidas 4:
no hay backups automáticos).

> ⚠️ Este procedimiento está **escrito, no ejecutado**: el paso de esquema de la 1.5.0 es el
> primero desde julio y `db/upgrade.php` nunca ha corrido de verdad en este proyecto. Pruébalo
> primero en el Moodle local (`make up`, sube el ZIP y pasa por Notificaciones) y solo después
> en AWS.

## Si algo falla

`apply` no termina "OK" hasta que hay un nodo sano en el target group, así que un fallo se nota.
Para mirar dentro:

```bash
aws ssm start-session --target <instance-id>
sudo tail -100 /var/log/cloud-init-output.log   # todo el arranque, con set -x
sudo docker ps -a
sudo docker logs judge0-server                  # o: docker logs moodle
sudo systemctl status judge0                    # solo en los nodos Judge0
```

Casos típicos:

| Síntoma | Causa probable |
|---|---|
| El ALB de Judge0 nunca queda sano | El nodo aún está reiniciando por cgroup v1 o bajando la imagen. Espera; mira `systemctl status judge0`. |
| `GET /languages` devuelve 401 | Normal si definiste `judge0_auth_token`. El health check acepta 200 y 401. |
| Moodle da error de BD | El nodo MariaDB no arrancó. `docker logs moodle-db` en el nodo de datos. |
| Instancias que se reemplazan en bucle | La gracia del ASG se agotó: revisa `cloud-init-output.log` de una instancia antes de que muera. |
| Judge0 ejecuta pero todo da `Internal Error` | El nodo sigue en cgroup v2. Comprueba `stat -fc %T /sys/fs/cgroup` (debe decir `tmpfs`) y `grep unified_cgroup /proc/cmdline`. En la AMI cloud de Ubuntu, `/etc/default/grub.d/50-cloudimg-settings.cfg` pisa lo que se escriba en `/etc/default/grub`; por eso el flag va en `99-codelab-cgroupv1.cfg` (corregido el 2026-10-30). |
| `judge0-server` en bucle con *Connection refused* hacia Postgres | El contenedor (uid 1000) no puede leer `/opt/judge0/judge0.conf` y usa `localhost`. Debe ser `1000:600` (`stat -c %u:%a`). Corregido el 2026-10-30. |
| El grupo de Judge0 sube a 4 instancias sin tráfico | Casi siempre, un nodo en bucle de reinicios (mira `docker ps`: el servidor con pocos segundos de vida). El 2026-10-30 fue el `judge0.conf` ilegible: 83-95 % de CPU sostenido; un arranque sano no pasa de ~56 %. `default_instance_warmup` excluye además los primeros 15 min de cada nodo. |
| `apply` falla con *Access denied … AWSServiceRoleForAutoScaling* | Primera vez en la cuenta; ver «Antes del primer despliegue». Repite el `apply`. |

## Ofrecer Judge0 a otra universidad

Despliega **otro** stack `judge0/` (en su cuenta o con otro `project`) y entrégale el
`judge0_url` **y el token**. Su Moodle los pone en los ajustes del plugin
(`judge0_api_url` y `judge0_api_key`, con "auto-alojado" marcado).

## Destruir TODO (para no gastar)

Orden inverso:

```bash
cd infra/moodle  && terraform destroy
cd ../judge0     && terraform destroy
```

El bucket de state se deja a propósito. Tras los destroy no debe quedar ninguna instancia, ALB,
EFS, VPC ni disco; verifícalo en la consola por si acaso.

Si `terraform destroy -auto-approve` está bloqueado en tu entorno, el camino equivalente y
revisable es: `terraform plan -destroy -out=tfplan-destroy` y luego `terraform apply tfplan-destroy`.

> La consola de EC2 sigue mostrando las instancias **terminadas** durante cerca de una hora. No
> cuestan nada; filtra por «Estado = en ejecución» para ver lo que de verdad está encendido.

## Costos (orden de magnitud, us-east-1)

Con 1 nodo por capa, el gasto dominante son las EC2 (`t3.micro` ≈ $0.0104/h, `t3.small` ≈
$0.0208/h) y los **ALB** (≈ $0.0225/h cada uno + LCU). El EFS está en modo `elastic`: cobra por
uso real. **Apaga con `destroy` cuando no lo uses.**

## Limitaciones conocidas (decisiones conscientes)

Son deliberadas, no descuidos; están aquí para que se puedan defender en la sustentación:

1. **HTTP, no HTTPS.** Los ALB escuchan en el 80. Para producción: certificado en ACM, listener
   443, y `wwwroot` con `https://` en Moodle. Mientras tanto, el token de Judge0 y la contraseña
   de Moodle viajan en claro.
2. **Los secretos van en el `user_data`** (y por tanto en el `tfstate` y visibles para quien
   tenga permiso de leer launch templates). Lo correcto sería guardarlos en SSM Parameter Store
   y que la instancia los lea con su rol IAM — ya tiene rol. Queda como mejora.
3. **El docroot de Moodle vive en EFS (NFS).** Es lo que permite escalar el ASG, pero PHP hace
   miles de `stat()` por petición y NFS es lento. Se mitiga con `$CFG->localcachedir` en el disco
   local de cada instancia. Si va lento con varios usuarios, la solución es servir el docroot
   desde el disco local y dejar en EFS solo `moodledata`.
4. **Un solo nodo de datos, sin réplica ni backups automáticos.** Si esa EC2 muere, se pierde la
   base de datos. Aceptable para un proyecto de grado; no para producción.
5. **Sin WAF, sin VPC flow logs, sin logs de acceso del ALB.** Descartados por costo.
