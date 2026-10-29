output "judge0_url" {
  description = "URL pública del motor Judge0 (ESTA es la que se entrega a cada universidad)."
  value       = "http://${aws_lb.this.dns_name}"
}

output "judge0_alb_dns" {
  description = "DNS del ALB de Judge0."
  value       = aws_lb.this.dns_name
}

output "data_node_private_ip" {
  description = "IP privada del nodo Postgres+Redis (uso interno)."
  value       = aws_instance.data.private_ip
}

output "health_check_url" {
  description = "Endpoint para comprobar que Judge0 responde (200 sin token, 401 con token)."
  value       = "http://${aws_lb.this.dns_name}/languages"
}

output "auth_required" {
  description = "true si el motor exige X-Auth-Token. Si es false, el endpoint de ejecución está ABIERTO a internet."
  # Solo se exporta si hay token (un booleano), nunca el token: por eso
  # es seguro quitarle la marca de sensible que hereda de la variable.
  value = nonsensitive(var.judge0_auth_token != "")
}

output "siguiente_paso" {
  description = "Qué pasar al stack de Moodle."
  value       = nonsensitive(var.judge0_auth_token == "") ? "En moodle/terraform.tfvars: judge0_url = \"http://${aws_lb.this.dns_name}\"  (ATENCION: sin token, el motor esta abierto a internet)" : "En moodle/terraform.tfvars: judge0_url = \"http://${aws_lb.this.dns_name}\" y judge0_auth_token = <el mismo token de este stack>"
}
