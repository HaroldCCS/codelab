# ============================================================
#  ALB + Launch Template + ASG para Moodle (con EFS compartido).
# ============================================================

resource "aws_lb" "this" {
  name                       = "${var.project}-moodle-alb"
  load_balancer_type         = "application"
  internal                   = false
  security_groups            = [aws_security_group.alb.id]
  subnets                    = aws_subnet.public[*].id
  enable_deletion_protection = false
  drop_invalid_header_fields = true
  idle_timeout               = 120 # Moodle tiene páginas lentas (backup, upgrade)
  tags                       = { Name = "${var.project}-moodle-alb" }
}

resource "aws_lb_target_group" "this" {
  # name_prefix, no name: con create_before_destroy un nombre fijo da
  # DuplicateTargetGroupName al reemplazar. AWS limita el prefijo a 6.
  name_prefix = "ml-tg-"
  port        = 80
  protocol    = "HTTP"
  vpc_id      = aws_vpc.this.id
  target_type = "instance"

  health_check {
    path                = "/login/index.php"
    port                = "traffic-port"
    protocol            = "HTTP"
    matcher             = "200,303"
    interval            = 30
    timeout             = 10
    healthy_threshold   = 2
    unhealthy_threshold = 5
  }

  # Sesiones "pegajosas" como salvaguarda (aunque las sesiones van por EFS).
  stickiness {
    type            = "lb_cookie"
    enabled         = true
    cookie_duration = 86400
  }

  deregistration_delay = 30

  lifecycle {
    create_before_destroy = true
  }

  tags = { Name = "${var.project}-moodle-tg" }
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

resource "aws_launch_template" "app" {
  name_prefix            = "${var.project}-moodle-"
  image_id               = data.aws_ami.ubuntu.id
  instance_type          = var.app_instance_type
  key_name               = var.key_name == "" ? null : var.key_name
  vpc_security_group_ids = [aws_security_group.app.id]

  dynamic "iam_instance_profile" {
    for_each = local.instance_profile == null ? [] : [local.instance_profile]
    content {
      name = iam_instance_profile.value
    }
  }

  user_data = base64encode(templatefile("${path.module}/templates/app.sh.tftpl", {
    efs_id           = aws_efs_file_system.this.id
    aws_region       = var.aws_region
    data_ip          = aws_instance.data.private_ip
    db_name_b64      = base64encode(var.db_name)
    db_user_b64      = base64encode(var.db_user)
    db_pass_b64      = base64encode(var.db_password)
    admin_user_b64   = base64encode(var.moodle_admin_user)
    admin_pass_b64   = base64encode(var.moodle_admin_password)
    admin_email_b64  = base64encode(var.moodle_admin_email)
    judge0_token_b64 = base64encode(var.judge0_auth_token)
    moodle_url       = "http://${aws_lb.this.dns_name}"
    moodle_version   = var.moodle_version
    moodle_image     = var.moodle_image
    plugin_git_url   = var.plugin_git_url
    plugin_git_ref   = var.plugin_git_ref
    judge0_url       = var.judge0_url
  }))

  block_device_mappings {
    device_name = "/dev/sda1"
    ebs {
      volume_size           = 20
      volume_type           = "gp3"
      encrypted             = true
      delete_on_termination = true
    }
  }

  metadata_options {
    http_endpoint = "enabled"
    http_tokens   = "required"
  }

  tag_specifications {
    resource_type = "instance"
    tags          = { Name = "${var.project}-moodle-app" }
  }

  lifecycle {
    create_before_destroy = true
  }

  # El ALB debe existir antes (su DNS va en el user_data como wwwroot)
  # y el EFS debe estar montable antes de que arranque la instancia.
  depends_on = [aws_lb.this, aws_efs_mount_target.this, aws_instance.data]
}

resource "aws_autoscaling_group" "app" {
  name                = "${var.project}-moodle-asg"
  min_size            = var.asg_min_size
  max_size            = var.asg_max_size
  desired_capacity    = var.asg_desired_capacity
  vpc_zone_identifier = aws_subnet.public[*].id
  target_group_arns   = [aws_lb_target_group.this.arn]
  health_check_type   = "ELB"

  # La primera instancia descarga Moodle (~80 MB), lo instala en EFS
  # (NFS, lento) y actualiza los plugins. Con gracia corta el ASG la
  # mata a mitad de la instalación. Medido en AWS el 2026-10-30 (t3.small):
  # solo descomprimir Moodle 4.5 en EFS y darle dueño tardó ~17 min, y con
  # 1200 s la instancia murió en plena instalación, dejando la BD a medias y
  # el lock de EFS huérfano. Una hora deja margen para install.php.
  health_check_grace_period = 3600
  wait_for_capacity_timeout = "65m"

  # La instalación en EFS deja la CPU alta muchos minutos; sin calentamiento el
  # escalado por CPU lo confunde con carga y lanza nodos que solo esperan el
  # lock (mismo efecto que se vio en Judge0 el 2026-10-30).
  default_instance_warmup = 1800

  launch_template {
    id      = aws_launch_template.app.id
    version = aws_launch_template.app.latest_version
  }

  instance_refresh {
    strategy = "Rolling"
    preferences {
      min_healthy_percentage = 0
    }
  }

  tag {
    key                 = "Name"
    value               = "${var.project}-moodle-app"
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

resource "aws_autoscaling_policy" "cpu" {
  name                   = "${var.project}-moodle-cpu-target"
  autoscaling_group_name = aws_autoscaling_group.app.name
  policy_type            = "TargetTrackingScaling"

  target_tracking_configuration {
    predefined_metric_specification {
      predefined_metric_type = "ASGAverageCPUUtilization"
    }
    target_value = var.cpu_target_percent
  }
}
