output "state_bucket_name" {
  description = "Nombre del bucket S3 para los tfstate. Úsalo en los backend.tf de judge0/ y moodle/."
  value       = aws_s3_bucket.tfstate.bucket
}

output "state_bucket_region" {
  description = "Región del bucket de state."
  value       = var.aws_region
}
