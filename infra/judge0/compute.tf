# ============================================================
#  Cómputo: ALB + Launch Template + Auto Scaling Group
#  Los nodos Judge0 (server+workers) escalan horizontalmente
#  por CPU. El ALB expone la URL pública de ejecución.
# ============================================================

# ---------- ALB ----------
resource "aws_lb" "this" {
  name                       = "${var.project}-judge0-alb"
  load_balancer_type         = "application"
  internal                   = false
  security_groups            = [aws_security_group.alb.id]
  subnets                    = aws_subnet.public[*].id
  enable_deletion_protection = false
  drop_invalid_header_fields = true # para poder destruir sin bloqueos
  tags                       = { Name = "${var.project}-judge0-alb" }
}

resource "aws_lb_target_group" "this" {
  # name_prefix (no name): con create_before_destroy, un nombre fijo
  # provoca DuplicateTargetGroupName al reemplazar el recurso.
  # AWS limita name_prefix a 6 caracteres.
  name_prefix = "j0-tg-"
  port        = 2358
  protocol    = "HTTP"
  vpc_id      = aws_vpc.this.id
  target_type = "instance"

  health_check {
    path     = "/languages"
    port     = "traffic-port"
    protocol = "HTTP"
    # 401 = Judge0 vivo pero exigiendo X-Auth-Token (cuando
    # judge0_auth_token está definido). También indica "sano".
    matcher             = "200,401"
    interval            = 30
    timeout             = 10
    healthy_threshold   = 2
    unhealthy_threshold = 5
  }

  deregistration_delay = 30

  lifecycle {
    create_before_destroy = true
  }

  tags = { Name = "${var.project}-judge0-tg" }
}

resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.this.arn
  port              = 80
  protocol          = "HTTP"

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.this.arn
  }
}

# ---------- Launch Template ----------
resource "aws_launch_template" "app" {
  name_prefix   = "${var.project}-judge0-"
  image_id      = data.aws_ami.ubuntu.id
  instance_type = var.app_instance_type
  key_name      = var.key_name == "" ? null : var.key_name

  vpc_security_group_ids = [aws_security_group.app.id]

  dynamic "iam_instance_profile" {
    for_each = local.instance_profile == null ? [] : [local.instance_profile]
    content {
      name = iam_instance_profile.value
    }
  }

  user_data = base64encode(templatefile("${path.module}/templates/app.sh.tftpl", {
    data_ip        = aws_instance.data.private_ip
    pg_db_b64      = base64encode(var.postgres_db)
    pg_user_b64    = base64encode(var.postgres_user)
    pg_pass_b64    = base64encode(var.postgres_password)
    redis_pass_b64 = base64encode(var.redis_password)
    auth_token_b64 = base64encode(var.judge0_auth_token)
    judge0_image   = local.judge0_image
    worker_count   = var.worker_count
  }))

  block_device_mappings {
    device_name = "/dev/sda1"
    ebs {
      volume_size           = 30 # la imagen de Judge0 pesa varios GB
      volume_type           = "gp3"
      encrypted             = true
      delete_on_termination = true
    }
  }

  metadata_options {
    http_endpoint = "enabled"
    http_tokens   = "required" # IMDSv2
  }

  tag_specifications {
    resource_type = "instance"
    tags          = { Name = "${var.project}-judge0-app" }
  }

  lifecycle {
    create_before_destroy = true
  }
}

# ---------- Auto Scaling Group ----------
resource "aws_autoscaling_group" "app" {
  name                = "${var.project}-judge0-asg"
  min_size            = var.asg_min_size
  max_size            = var.asg_max_size
  desired_capacity    = var.asg_desired_capacity
  vpc_zone_identifier = aws_subnet.public[*].id
  target_group_arns   = [aws_lb_target_group.this.arn]
  health_check_type   = "ELB"

  # Un nodo nuevo hace: apt + reboot por cgroup v1 + pull de la imagen
  # de Judge0 (varios GB) + migraciones. En t3.small eso pasa de 10 min;
  # con una gracia corta el ASG mata la instancia antes de que arranque
  # y entra en un bucle de reemplazos.
  health_check_grace_period = 1200

  # Que `apply` no dé por buena la creación hasta que haya un nodo sano.
  wait_for_capacity_timeout = "25m"

  # Durante este tiempo la CPU de un nodo nuevo no cuenta para la métrica del
  # grupo. Medido en CloudWatch el 2026-10-30 (t3.small): un arranque sano llega
  # a ~56 % de media en 5 min y baja a ~1,3 % en menos de 15 min; 900 s lo cubre.
  # Aquel día el grupo subió a 4 instancias sin tráfico, pero la causa fue el
  # bucle de reinicios del servidor (judge0.conf ilegible: 83-95 % sostenido),
  # no el arranque. Esto es un margen de seguridad, no el arreglo de aquello.
  default_instance_warmup = 900

  launch_template {
    id      = aws_launch_template.app.id
    version = aws_launch_template.app.latest_version
  }

  instance_refresh {
    strategy = "Rolling"
    preferences {
      min_healthy_percentage = 0 # con 1 sola instancia no hay margen
    }
  }

  tag {
    key                 = "Name"
    value               = "${var.project}-judge0-app"
    propagate_at_launch = true
  }

  lifecycle {
    precondition {
      condition     = var.asg_desired_capacity >= var.asg_min_size && var.asg_desired_capacity <= var.asg_max_size
      error_message = "asg_desired_capacity debe estar entre asg_min_size y asg_max_size."
    }
  }

  depends_on = [aws_lb_listener.http]
}

# ---------- Escalado por CPU (crecimiento horizontal) ----------
resource "aws_autoscaling_policy" "cpu" {
  name                   = "${var.project}-judge0-cpu-target"
  autoscaling_group_name = aws_autoscaling_group.app.name
  policy_type            = "TargetTrackingScaling"

  target_tracking_configuration {
    predefined_metric_specification {
      predefined_metric_type = "ASGAverageCPUUtilization"
    }
    target_value = var.cpu_target_percent
  }
}
