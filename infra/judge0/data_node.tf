# ============================================================
#  Nodo de datos: EC2 pequeña con Postgres + Redis en Docker
#  (SIN RDS por costo). Los datos viven en su EBS; al hacer
#  destroy se elimina con la instancia.
# ============================================================

# AMI Ubuntu 22.04 (amd64) más reciente.
data "aws_ami" "ubuntu" {
  most_recent = true
  owners      = ["099720109477"] # Canonical

  filter {
    name   = "name"
    values = ["ubuntu/images/hvm-ssd/ubuntu-jammy-22.04-amd64-server-*"]
  }
  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }
  filter {
    name   = "state"
    values = ["available"]
  }
}

resource "aws_instance" "data" {
  ami                    = data.aws_ami.ubuntu.id
  instance_type          = var.data_instance_type
  subnet_id              = aws_subnet.public[0].id
  vpc_security_group_ids = [aws_security_group.data.id]
  key_name               = var.key_name == "" ? null : var.key_name
  iam_instance_profile   = local.instance_profile

  associate_public_ip_address = true

  # Los secretos van en base64 para que ningún carácter especial
  # rompa el script de arranque (ver templates/data.sh.tftpl).
  user_data = templatefile("${path.module}/templates/data.sh.tftpl", {
    pg_db_b64      = base64encode(var.postgres_db)
    pg_user_b64    = base64encode(var.postgres_user)
    pg_pass_b64    = base64encode(var.postgres_password)
    redis_pass_b64 = base64encode(var.redis_password)
    postgres_image = var.postgres_image
    redis_image    = var.redis_image
  })

  # Si cambia el script de arranque, la instancia se recrea.
  user_data_replace_on_change = true

  metadata_options {
    http_endpoint = "enabled"
    http_tokens   = "required" # IMDSv2
  }

  root_block_device {
    volume_size           = 20
    volume_type           = "gp3"
    encrypted             = true
    delete_on_termination = true
  }

  tags = { Name = "${var.project}-judge0-data" }
}
