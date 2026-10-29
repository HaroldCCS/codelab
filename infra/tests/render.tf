terraform { required_version = ">= 1.5.0" }

# Valores deliberadamente "hostiles": contraseñas con $ , comillas,
# espacios y backticks, para demostrar que ya no rompen los scripts.
locals {
  pg_pass    = "Pg$ecret 'con' \"comillas\" `y` backticks!"
  redis_pass = "Rd$ecret"
  db_pass    = "Db$ecret 'x' \"y\" `z`"
  root_pass  = "Root$ecret"
  admin_pass = "Adm1n$ '\"`!"
  token      = "0123456789abcdef0123456789abcdef"

  judge0_data = templatefile("${path.module}/../judge0/templates/data.sh.tftpl", {
    pg_db_b64      = base64encode("judge0")
    pg_user_b64    = base64encode("judge0")
    pg_pass_b64    = base64encode(local.pg_pass)
    redis_pass_b64 = base64encode(local.redis_pass)
    postgres_image = "postgres:13"
    redis_image    = "redis:7.2.4"
  })
  judge0_app = templatefile("${path.module}/../judge0/templates/app.sh.tftpl", {
    data_ip        = "10.20.0.55"
    pg_db_b64      = base64encode("judge0")
    pg_user_b64    = base64encode("judge0")
    pg_pass_b64    = base64encode(local.pg_pass)
    redis_pass_b64 = base64encode(local.redis_pass)
    auth_token_b64 = base64encode(local.token)
    judge0_image   = "judge0/judge0:1.13.1"
    worker_count   = 2
  })
  moodle_data = templatefile("${path.module}/../moodle/templates/data.sh.tftpl", {
    db_name_b64      = base64encode("moodle")
    db_user_b64      = base64encode("moodle")
    db_pass_b64      = base64encode(local.db_pass)
    db_root_pass_b64 = base64encode(local.root_pass)
    mariadb_image    = "mariadb:10.11"
  })
  moodle_app = templatefile("${path.module}/../moodle/templates/app.sh.tftpl", {
    efs_id           = "fs-0abc123"
    aws_region       = "us-east-1"
    data_ip          = "10.30.0.77"
    db_name_b64      = base64encode("moodle")
    db_user_b64      = base64encode("moodle")
    db_pass_b64      = base64encode(local.db_pass)
    admin_user_b64   = base64encode("admin")
    admin_pass_b64   = base64encode(local.admin_pass)
    admin_email_b64  = base64encode("a@b.com")
    judge0_token_b64 = base64encode(local.token)
    moodle_url       = "http://codelab-moodle-alb-123.us-east-1.elb.amazonaws.com"
    moodle_version   = "405"
    moodle_image     = "moodlehq/moodle-php-apache:8.3"
    plugin_git_url   = "https://github.com/x/y.git"
    plugin_git_ref   = "main"
    judge0_url       = "http://codelab-judge0-alb-9.us-east-1.elb.amazonaws.com"
  })
}

output "judge0_data" { value = local.judge0_data }
output "judge0_app" { value = local.judge0_app }
output "moodle_data" { value = local.moodle_data }
output "moodle_app" { value = local.moodle_app }
output "secretos_b64" {
  value = {
    pg    = base64encode(local.pg_pass)
    admin = base64encode(local.admin_pass)
  }
}
