# ============================================================
#  EFS — sistema de archivos compartido entre los nodos Moodle
#  (docroot de Moodle + moodledata). Permite que el ASG escale
#  horizontalmente compartiendo código, config.php y datos.
#  Se destruye por completo con `terraform destroy`.
# ============================================================

resource "aws_efs_file_system" "this" {
  creation_token = "${var.project}-moodle-efs"
  encrypted      = true

  performance_mode = "generalPurpose"

  # 'elastic' en vez del modo por defecto: con "bursting" y pocos GB
  # almacenados el caudal base es de apenas unos KB/s y, agotados los
  # créditos, Moodle se vuelve inusable. Elastic cobra por uso real.
  throughput_mode = "elastic"

  tags = { Name = "${var.project}-moodle-efs" }
}

# Un mount target por subred (para que cualquier AZ del ASG lo alcance).
resource "aws_efs_mount_target" "this" {
  count           = 2
  file_system_id  = aws_efs_file_system.this.id
  subnet_id       = aws_subnet.public[count.index].id
  security_groups = [aws_security_group.efs.id]
}
