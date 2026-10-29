output "moodle_url" {
  description = "URL pública de Moodle."
  value       = "http://${aws_lb.this.dns_name}"
}

output "moodle_alb_dns" {
  value = aws_lb.this.dns_name
}

output "judge0_url_configured" {
  description = "URL de Judge0 que quedó configurada en el plugin."
  value       = var.judge0_url
}

output "judge0_auth_configured" {
  description = "true si el plugin quedó configurado con el token X-Auth-Token de Judge0."
  # Solo se exporta si hay token (un booleano), nunca el token: por eso
  # es seguro quitarle la marca de sensible que hereda de la variable.
  value = nonsensitive(var.judge0_auth_token != "")
}

output "data_node_private_ip" {
  value = aws_instance.data.private_ip
}

output "primer_acceso" {
  description = "Cómo entrar la primera vez."
  value       = "Abre http://${aws_lb.this.dns_name} e inicia sesión con el usuario '${var.moodle_admin_user}' y la contraseña de moodle_admin_password. La primera instancia tarda ~10-15 min en quedar sana."
}
