variable "aws_region" {
  type    = string
  default = "us-east-1"
}

variable "project" {
  type    = string
  default = "codelab"

  validation {
    condition     = can(regex("^[a-z][a-z0-9-]{1,12}$", var.project))
    error_message = "project: minúsculas, dígitos y guiones, 2-13 caracteres (límite de 32 en nombres de ALB de AWS)."
  }
}

variable "vpc_cidr" {
  type    = string
  default = "10.30.0.0/16"

  validation {
    condition     = can(cidrsubnet(var.vpc_cidr, 8, 1))
    error_message = "vpc_cidr debe ser un CIDR válido con espacio para subredes /24."
  }
}

variable "app_instance_type" {
  description = "Instancia para los nodos Moodle (Apache+PHP)."
  type        = string
  default     = "t3.small"
}

variable "data_instance_type" {
  description = "Instancia para el nodo MariaDB (sin RDS)."
  type        = string
  default     = "t3.micro"
}

variable "asg_min_size" {
  type    = number
  default = 1
}

variable "asg_max_size" {
  type    = number
  default = 3
}

variable "asg_desired_capacity" {
  type    = number
  default = 1
}

variable "cpu_target_percent" {
  type    = number
  default = 65

  validation {
    condition     = var.cpu_target_percent > 0 && var.cpu_target_percent <= 100
    error_message = "cpu_target_percent debe estar entre 1 y 100."
  }
}

variable "key_name" {
  type    = string
  default = ""
}

variable "ssh_ingress_cidr" {
  type    = string
  default = "127.0.0.1/32"
}

variable "enable_ssm" {
  description = "Rol IAM con SSM Session Manager para depurar sin SSH. Recomendado en el primer despliegue."
  type        = bool
  default     = true
}

# ---- Base de datos de Moodle (MariaDB en EC2) ----
variable "db_name" {
  type    = string
  default = "moodle"
}

variable "db_user" {
  type    = string
  default = "moodle"
}

variable "db_password" {
  description = "Contraseña de MariaDB. Cámbiala."
  type        = string
  default     = "ChangeMe_Moodle_Db_2026"
  sensitive   = true

  validation {
    condition     = length(var.db_password) >= 12
    error_message = "db_password debe tener al menos 12 caracteres."
  }
}

variable "db_root_password" {
  type      = string
  default   = "ChangeMe_Moodle_Root_2026"
  sensitive = true

  validation {
    condition     = length(var.db_root_password) >= 12
    error_message = "db_root_password debe tener al menos 12 caracteres."
  }
}

# ---- Admin de Moodle ----
variable "moodle_admin_user" {
  type    = string
  default = "admin"
}

variable "moodle_admin_password" {
  description = "Debe cumplir la política de Moodle: 8+ caracteres, con minúscula, mayúscula, dígito y símbolo."
  type        = string
  # SIN valor por defecto a propósito: una contraseña por defecto escrita en el
  # repositorio es la contraseña real del sitio si nadie la cambia (SEC-09).
  # Ponla en terraform.tfvars, que está en .gitignore.
  sensitive = true

  validation {
    # Si no cumple la política, admin/cli/install.php aborta y el
    # despliegue entero se cae a mitad. Mejor fallar en `plan`.
    condition = (
      length(var.moodle_admin_password) >= 8 &&
      can(regex("[a-z]", var.moodle_admin_password)) &&
      can(regex("[A-Z]", var.moodle_admin_password)) &&
      can(regex("[0-9]", var.moodle_admin_password)) &&
      can(regex("[^A-Za-z0-9]", var.moodle_admin_password))
    )
    error_message = "moodle_admin_password no cumple la política de Moodle (8+ caracteres con minúscula, mayúscula, dígito y símbolo). install.php la rechazaría a mitad del despliegue."
  }
}

variable "moodle_admin_email" {
  type    = string
  default = "admin@codelab.local"

  validation {
    condition     = can(regex("^[^@[:space:]]+@[^@[:space:]]+\\.[A-Za-z]{2,}$", var.moodle_admin_email))
    error_message = "moodle_admin_email debe ser un correo válido (Moodle lo valida en la instalación)."
  }
}

variable "moodle_version" {
  description = "Rama de descarga de Moodle (p.ej. 405 = 4.5)."
  type        = string
  default     = "405"

  validation {
    condition     = can(regex("^[0-9]{3}$", var.moodle_version))
    error_message = "moodle_version son 3 dígitos, p. ej. \"405\" para Moodle 4.5."
  }
}

# ---- Plugin CodeLab ----
variable "plugin_git_url" {
  description = "Repositorio git desde donde clonar el plugin (debe contener mod/codelab y ser accesible SIN credenciales)."
  type        = string
  default     = "https://github.com/HaroldCCS/codelab.git"
}

variable "plugin_git_ref" {
  description = "Rama o etiqueta del repositorio del plugin. Fíjala a una etiqueta para que el despliegue sea reproducible."
  type        = string
  default     = "main"
}

# ---- Conexión al motor Judge0 (outputs del stack judge0) ----
variable "judge0_url" {
  description = "URL del ALB de Judge0 (output 'judge0_url' del stack judge0)."
  type        = string

  validation {
    condition     = can(regex("^https?://[^/[:space:]]+$", var.judge0_url))
    error_message = "judge0_url debe ser http(s)://host SIN barra final, p. ej. http://codelab-judge0-alb-123.us-east-1.elb.amazonaws.com"
  }
}

variable "judge0_auth_token" {
  description = "El MISMO token que pusiste en el stack judge0. Se guarda en el ajuste judge0_api_key del plugin y viaja en la cabecera X-Auth-Token."
  type        = string
  default     = ""
  sensitive   = true
}

# ---- Imágenes de contenedor ----
variable "moodle_image" {
  description = "Imagen Apache+PHP para Moodle. Moodle 4.5 admite PHP 8.1-8.3; se usa 8.3 porque 8.2 deja de tener soporte el 31-dic-2026. El arranque aborta si el PHP real es anterior a los parches de PHP2Shell (8.2.35 / 8.3.36 / 8.4.27 / 8.5.12)."
  type        = string
  default     = "moodlehq/moodle-php-apache:8.3"

  validation {
    # 8.1 y anteriores no tienen version oficial corregida de
    # CVE-2026-109618 / CVE-2026-109619: se rechazan ya en `plan`.
    condition     = !can(regex(":(5\\.|7\\.|8\\.[01]([^0-9]|$))", var.moodle_image))
    error_message = "moodle_image usa una rama de PHP sin parche oficial de PHP2Shell (CVE-2026-109618/109619). Usa moodlehq/moodle-php-apache:8.3 (o 8.2.35+)."
  }
}

variable "mariadb_image" {
  description = "Imagen de MariaDB. Moodle 4.5 soporta MariaDB 10.6.7+."
  type        = string
  default     = "mariadb:10.11"
}
