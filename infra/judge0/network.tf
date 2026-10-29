# ============================================================
#  Red — VPC propia con subredes PÚBLICAS (sin NAT, por costo)
#  Las instancias tienen IP pública pero están protegidas por
#  Security Groups. Todo es gestionado por Terraform → destroy
#  total sin recursos huérfanos.
# ============================================================

data "aws_availability_zones" "available" {
  state = "available"
}

resource "aws_vpc" "this" {
  cidr_block           = var.vpc_cidr
  enable_dns_support   = true
  enable_dns_hostnames = true
  tags                 = { Name = "${var.project}-judge0-vpc" }
}

resource "aws_internet_gateway" "this" {
  vpc_id = aws_vpc.this.id
  tags   = { Name = "${var.project}-judge0-igw" }
}

# Dos subredes públicas en dos AZ (requisito del ALB).
resource "aws_subnet" "public" {
  count                   = 2
  vpc_id                  = aws_vpc.this.id
  cidr_block              = cidrsubnet(var.vpc_cidr, 8, count.index)
  availability_zone       = data.aws_availability_zones.available.names[count.index]
  map_public_ip_on_launch = true
  tags                    = { Name = "${var.project}-judge0-public-${count.index}" }
}

resource "aws_route_table" "public" {
  vpc_id = aws_vpc.this.id
  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.this.id
  }
  tags = { Name = "${var.project}-judge0-rt-public" }
}

resource "aws_route_table_association" "public" {
  count          = 2
  subnet_id      = aws_subnet.public[count.index].id
  route_table_id = aws_route_table.public.id
}

# El SG por defecto de una VPC nueva permite todo el trafico entre sus
# miembros. No lo usamos, pero lo dejamos explicitamente cerrado para que
# nada acabe ahi por accidente. Terraform lo *adopta*, no lo crea.
resource "aws_default_security_group" "this" {
  vpc_id = aws_vpc.this.id
  tags   = { Name = "${var.project}-judge0-default-cerrado" }
}
