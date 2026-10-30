terraform {
  # >= 1.11 por 'use_lockfile' (bloqueo del state en S3, sin DynamoDB).
  required_version = ">= 1.11.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }

  # Backend S3 independiente del de Judge0 (otro key en el mismo bucket).
  backend "s3" {
    # El bucket vive en us-east-2 (cuenta «tesis», 107758684131); con us-east-1 el backend
    # responde 403/301. Los recursos se crean en var.aws_region, que puede ser otra.
    bucket       = "terraform-state-tesis-372814632174213946792314786213798123"
    key          = "moodle/terraform.tfstate"
    region       = "us-east-2"
    encrypt      = true
    use_lockfile = true
  }
}

provider "aws" {
  region = var.aws_region

  default_tags {
    tags = {
      Project   = var.project
      Stack     = "moodle"
      ManagedBy = "terraform"
    }
  }
}
