# ============================================================
#  Rol IAM para las instancias — SSM Session Manager.
#
#  ¿Por qué es importante? Con key_name = "" (el valor por defecto)
#  no hay SSH: si el primer despliegue falla, no habría forma de
#  entrar a mirar los logs. SSM te da una shell desde la consola de
#  AWS o con `aws ssm start-session`, sin abrir el puerto 22.
#
#  Comandos útiles cuando algo no arranca:
#    aws ssm start-session --target <instance-id>
#    sudo tail -f /var/log/cloud-init-output.log
#    sudo docker ps -a && sudo docker logs judge0-server
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
  name               = "${var.project}-judge0-instance"
  assume_role_policy = data.aws_iam_policy_document.ec2_assume[0].json
}

resource "aws_iam_role_policy_attachment" "ssm" {
  count      = var.enable_ssm ? 1 : 0
  role       = aws_iam_role.instance[0].name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}

resource "aws_iam_instance_profile" "instance" {
  count = var.enable_ssm ? 1 : 0
  name  = "${var.project}-judge0-instance"
  role  = aws_iam_role.instance[0].name
}

locals {
  instance_profile = var.enable_ssm ? aws_iam_instance_profile.instance[0].name : null
  judge0_image     = "judge0/judge0:${var.judge0_version}"
}
