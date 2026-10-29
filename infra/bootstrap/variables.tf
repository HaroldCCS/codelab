variable "aws_region" {
  description = "Región de AWS donde se crea el bucket de state."
  type        = string
  default     = "us-east-1"
}

variable "project" {
  description = "Prefijo de nombres del proyecto."
  type        = string
  default     = "codelab"
}
