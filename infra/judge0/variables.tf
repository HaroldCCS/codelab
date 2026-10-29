variable "aws_region" {
  description = "Región de AWS."
  type        = string
  default     = "us-east-1"
}

variable "project" {
  description = "Prefijo de nombres."
  type        = string
  default     = "codelab"

  validation {
    condition     = can(regex("^[a-z][a-z0-9-]{1,12}$", var.project))
    error_message = "project: minúsculas, dígitos y guiones, entre 2 y 13 caracteres (los nombres de ALB/Target Group de AWS tienen un límite de 32)."
  }
}

variable "vpc_cidr" {
  description = "CIDR de la VPC de Judge0."
  type        = string
  default     = "10.20.0.0/16"

  validation {
    condition     = can(cidrsubnet(var.vpc_cidr, 8, 1))
    error_message = "vpc_cidr debe ser un CIDR válido con espacio para subredes /24 (p. ej. 10.20.0.0/16)."
  }
}

variable "app_instance_type" {
  description = "Tipo de instancia para los nodos Judge0 (server+workers). Pequeña por costo."
  type        = string
  default     = "t3.small"
}

variable "data_instance_type" {
  description = "Tipo de instancia para el nodo de datos (Postgres+Redis). Muy pequeña."
  type        = string
  default     = "t3.micro"
}

variable "asg_min_size" {
  description = "Mínimo de nodos Judge0."
  type        = number
  default     = 1
}

variable "asg_max_size" {
  description = "Máximo de nodos Judge0 (crecimiento horizontal por carga)."
  type        = number
  default     = 4
}

variable "asg_desired_capacity" {
  description = "Nodos Judge0 iniciales."
  type        = number
  default     = 1
}

variable "cpu_target_percent" {
  description = "Umbral de CPU (%) para escalar horizontalmente."
  type        = number
  default     = 60

  validation {
    condition     = var.cpu_target_percent > 0 && var.cpu_target_percent <= 100
    error_message = "cpu_target_percent debe estar entre 1 y 100."
  }
}

variable "worker_count" {
  description = "Workers de Judge0 por nodo (COUNT). Ajústalo al número de vCPU."
  type        = number
  default     = 2
}

variable "key_name" {
  description = "Nombre de un key pair EC2 existente para SSH (opcional). Vacío = sin SSH; para depurar usa SSM Session Manager."
  type        = string
  default     = ""
}

variable "ssh_ingress_cidr" {
  description = "CIDR permitido para SSH (solo si key_name != \"\"). Restríngelo a tu IP."
  type        = string
  default     = "127.0.0.1/32"
}

variable "enable_ssm" {
  description = "Adjunta un rol IAM con SSM Session Manager para poder entrar a las instancias sin SSH ni puerto 22 abierto. Imprescindible para depurar el primer despliegue."
  type        = bool
  default     = true
}

# ---------------------------------------------------------------------------
#  Base de datos y cola de Judge0
# ---------------------------------------------------------------------------
variable "postgres_db" {
  type    = string
  default = "judge0"
}

variable "postgres_user" {
  type    = string
  default = "judge0"
}

variable "postgres_password" {
  description = "Contraseña de Postgres de Judge0. Cámbiala en terraform.tfvars."
  type        = string
  default     = "ChangeMe_Judge0_Pg_2026"
  sensitive   = true

  validation {
    # judge0.conf lo consume la imagen como fichero de entorno: los
    # caracteres especiales lo rompen. Restringimos el juego de caracteres.
    condition     = can(regex("^[A-Za-z0-9_.-]{12,64}$", var.postgres_password))
    error_message = "postgres_password: 12-64 caracteres, solo letras, dígitos, _ . y - (Judge0 lee judge0.conf como fichero de entorno y otros símbolos lo rompen)."
  }
}

variable "redis_password" {
  description = "Contraseña de Redis de Judge0. Cámbiala en terraform.tfvars."
  type        = string
  default     = "ChangeMe_Judge0_Redis_2026"
  sensitive   = true

  validation {
    condition     = can(regex("^[A-Za-z0-9_.-]{12,64}$", var.redis_password))
    error_message = "redis_password: 12-64 caracteres, solo letras, dígitos, _ . y -."
  }
}

variable "judge0_auth_token" {
  description = <<-EOT
    Token que exige Judge0 en la cabecera X-Auth-Token. SIN ÉL, el ALB es un
    endpoint público de ejecución de código arbitrario. Este mismo valor va en
    el ajuste 'judge0_api_key' del plugin CodeLab en Moodle.
    Genera uno con:  openssl rand -hex 24
    Es OBLIGATORIO: no tiene valor por defecto para que un despliegue sin token
    falle en `plan` en vez de publicar un ejecutor de código abierto (SEC-01).
  EOT
  type        = string
  sensitive   = true

  validation {
    condition     = can(regex("^[A-Za-z0-9_.-]{24,128}$", var.judge0_auth_token))
    error_message = "judge0_auth_token: 24-128 caracteres de [A-Za-z0-9_.-]. Genera uno con: openssl rand -hex 24."
  }
}

# ---------------------------------------------------------------------------
#  Imágenes de contenedor (fijadas: un 'latest' rompe despliegues futuros)
# ---------------------------------------------------------------------------
variable "judge0_version" {
  description = "Tag de la imagen de Judge0."
  type        = string
  default     = "1.13.1"
}

variable "postgres_image" {
  description = "Imagen de Postgres. Judge0 1.13.x está probado contra Postgres 13; no lo subas sin verificar las migraciones."
  type        = string
  default     = "postgres:13"
}

variable "redis_image" {
  description = "Imagen de Redis."
  type        = string
  default     = "redis:7.2.4"
}
