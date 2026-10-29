# ============================================================
#  Security Groups de Moodle
# ============================================================

resource "aws_security_group" "alb" {
  name        = "${var.project}-moodle-alb"
  description = "ALB de Moodle"
  vpc_id      = aws_vpc.this.id

  ingress {
    description = "HTTP publico"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }
  egress {
    description = "Salida a internet (apt, Docker Hub, descarga de Moodle)"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
  tags = { Name = "${var.project}-moodle-alb" }
}

resource "aws_security_group" "app" {
  name        = "${var.project}-moodle-app"
  description = "Nodos Moodle"
  vpc_id      = aws_vpc.this.id

  ingress {
    description     = "HTTP desde el ALB"
    from_port       = 80
    to_port         = 80
    protocol        = "tcp"
    security_groups = [aws_security_group.alb.id]
  }
  egress {
    description = "Salida a internet (apt, Docker Hub, descarga de Moodle)"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
  tags = { Name = "${var.project}-moodle-app" }
}

resource "aws_security_group" "data" {
  name        = "${var.project}-moodle-data"
  description = "MariaDB de Moodle"
  vpc_id      = aws_vpc.this.id

  ingress {
    description     = "MySQL/MariaDB desde los nodos Moodle"
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [aws_security_group.app.id]
  }
  egress {
    description = "Salida a internet (apt, Docker Hub, descarga de Moodle)"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
  tags = { Name = "${var.project}-moodle-data" }
}

# EFS: NFS (2049) desde los nodos Moodle.
resource "aws_security_group" "efs" {
  name        = "${var.project}-moodle-efs"
  description = "EFS de moodledata/docroot"
  vpc_id      = aws_vpc.this.id

  ingress {
    description     = "NFS desde los nodos Moodle"
    from_port       = 2049
    to_port         = 2049
    protocol        = "tcp"
    security_groups = [aws_security_group.app.id]
  }
  egress {
    description = "Salida a internet (apt, Docker Hub, descarga de Moodle)"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
  tags = { Name = "${var.project}-moodle-efs" }
}

resource "aws_security_group_rule" "ssh_app" {
  count             = var.key_name == "" ? 0 : 1
  type              = "ingress"
  from_port         = 22
  to_port           = 22
  protocol          = "tcp"
  cidr_blocks       = [var.ssh_ingress_cidr]
  security_group_id = aws_security_group.app.id
  description       = "SSH restringido"
}

resource "aws_security_group_rule" "ssh_data" {
  count             = var.key_name == "" ? 0 : 1
  type              = "ingress"
  from_port         = 22
  to_port           = 22
  protocol          = "tcp"
  cidr_blocks       = [var.ssh_ingress_cidr]
  security_group_id = aws_security_group.data.id
  description       = "SSH restringido"
}
