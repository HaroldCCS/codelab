# ============================================================
#  Security Groups
# ============================================================

# ALB: acepta HTTP público (la URL que se entrega a las universidades).
resource "aws_security_group" "alb" {
  name        = "${var.project}-judge0-alb"
  description = "ALB de Judge0"
  vpc_id      = aws_vpc.this.id

  ingress {
    description = "HTTP publico"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }
  egress {
    description = "Salida a internet (apt, Docker Hub)"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
  tags = { Name = "${var.project}-judge0-alb" }
}

# Nodos Judge0 (server+workers): solo reciben del ALB en 2358.
resource "aws_security_group" "app" {
  name        = "${var.project}-judge0-app"
  description = "Nodos Judge0"
  vpc_id      = aws_vpc.this.id

  ingress {
    description     = "API Judge0 desde el ALB"
    from_port       = 2358
    to_port         = 2358
    protocol        = "tcp"
    security_groups = [aws_security_group.alb.id]
  }

  egress {
    description = "Salida a internet (apt, Docker Hub)"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
  tags = { Name = "${var.project}-judge0-app" }
}

# Nodo de datos (Postgres+Redis): solo reciben de los nodos app.
resource "aws_security_group" "data" {
  name        = "${var.project}-judge0-data"
  description = "Postgres+Redis de Judge0"
  vpc_id      = aws_vpc.this.id

  ingress {
    description     = "Postgres desde los nodos Judge0"
    from_port       = 5432
    to_port         = 5432
    protocol        = "tcp"
    security_groups = [aws_security_group.app.id]
  }
  ingress {
    description     = "Redis desde los nodos Judge0"
    from_port       = 6379
    to_port         = 6379
    protocol        = "tcp"
    security_groups = [aws_security_group.app.id]
  }
  egress {
    description = "Salida a internet (apt, Docker Hub)"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
  tags = { Name = "${var.project}-judge0-data" }
}

# SSH opcional (solo si se define key_name). Se añade como regla aparte.
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
