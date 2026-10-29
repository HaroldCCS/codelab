# ============================================================
#  Rol IAM para las instancias — SSM Session Manager.
#  Con key_name = "" no hay SSH; sin esto no habría forma de entrar
#  a depurar si la instalación de Moodle falla.
#
#    aws ssm start-session --target <instance-id>
#    sudo tail -f /var/log/cloud-init-output.log
#    sudo docker logs moodle
# ============================================================

data "aws_iam_policy_document" "ec2_assume" {
  count = var.enable_ssm ? 1 : 0

  statement {
    actions = ["sts:AssumeRole"]
    principals {
      type        = "Service"
      identifiers = ["ec2.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "instance" {
  count              = var.enable_ssm ? 1 : 0
  name               = "${var.project}-moodle-instance"
  assume_role_policy = data.aws_iam_policy_document.ec2_assume[0].json
}

resource "aws_iam_role_policy_attachment" "ssm" {
  count      = var.enable_ssm ? 1 : 0
  role       = aws_iam_role.instance[0].name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}

resource "aws_iam_instance_profile" "instance" {
  count = var.enable_ssm ? 1 : 0
  name  = "${var.project}-moodle-instance"
  role  = aws_iam_role.instance[0].name
}

locals {
  instance_profile = var.enable_ssm ? aws_iam_instance_profile.instance[0].name : null
}
