# ============================================================
#  Nodo de datos: EC2 pequeña con MariaDB en Docker (SIN RDS).
# ============================================================

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
  ami                         = data.aws_ami.ubuntu.id
  instance_type               = var.data_instance_type
  subnet_id                   = aws_subnet.public[0].id
  vpc_security_group_ids      = [aws_security_group.data.id]
  key_name                    = var.key_name == "" ? null : var.key_name
  iam_instance_profile        = local.instance_profile
  associate_public_ip_address = true

  # Secretos en base64: así ningún carácter especial rompe el script.
  user_data = templatefile("${path.module}/templates/data.sh.tftpl", {
    db_name_b64      = base64encode(var.db_name)
    db_user_b64      = base64encode(var.db_user)
    db_pass_b64      = base64encode(var.db_password)
    db_root_pass_b64 = base64encode(var.db_root_password)
    mariadb_image    = var.mariadb_image
  })

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

  tags = { Name = "${var.project}-moodle-data" }
}
