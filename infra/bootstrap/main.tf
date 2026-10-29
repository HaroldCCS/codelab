# ============================================================
#  BOOTSTRAP — Bucket S3 para los estados de Terraform
# ------------------------------------------------------------
#  Esto se aplica UNA sola vez y con state LOCAL. Crea el bucket
#  S3 donde vivirán los tfstate de los stacks judge0 y moodle.
#  Es lo ÚNICO que debe sobrevivir a los `terraform destroy`.
#
#  Uso:
#     cd infra/bootstrap
#     terraform init
#     terraform apply
#     # anota el nombre del bucket que aparece en los outputs
# ============================================================

terraform {
  required_version = ">= 1.5.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.5"
    }
  }
}

provider "aws" {
  region = var.aws_region
}

# Sufijo aleatorio para que el nombre del bucket sea único globalmente.
resource "random_id" "suffix" {
  byte_length = 4
}

locals {
  bucket_name = "${var.project}-tfstate-${random_id.suffix.hex}"
}

resource "aws_s3_bucket" "tfstate" {
  bucket = local.bucket_name

  # NO permitir destruir con contenido por accidente: el state debe sobrevivir.
  force_destroy = false

  tags = {
    Project   = var.project
    Purpose   = "terraform-state"
    ManagedBy = "terraform-bootstrap"
  }
}

# Versionado: permite recuperar estados anteriores (equivale a los backups).
resource "aws_s3_bucket_versioning" "tfstate" {
  bucket = aws_s3_bucket.tfstate.id
  versioning_configuration {
    status = "Enabled"
  }
}

# Cifrado en reposo.
resource "aws_s3_bucket_server_side_encryption_configuration" "tfstate" {
  bucket = aws_s3_bucket.tfstate.id
  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
  }
}

# Bloquear todo acceso público.
resource "aws_s3_bucket_public_access_block" "tfstate" {
  bucket                  = aws_s3_bucket.tfstate.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}
