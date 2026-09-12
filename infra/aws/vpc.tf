# ==============================================================================
# REDES VIRTUAIS: VPC, SUBNETS, ROTEAMENTO E GATEWAY DE INTERNET
# PecuáriaGest - Arquitetura em Nuvem para TCC
# ==============================================================================
# Uma VPC (Virtual Private Cloud) é a sua "fazenda digital" isolada dentro da AWS.
# Nenhum tráfego entra ou sai sem que você expressamente autorize via rotas e firewalls.
# ==============================================================================

# Consulta dinâmica das Zonas de Disponibilidade (AZs) ativas na região selecionada
# (Ex: em sa-east-1 temos sa-east-1a, sa-east-1b e sa-east-1c)
data "aws_availability_zones" "available" {
  state = "available"
}

# 1. VPC Principal Dedicada
resource "aws_vpc" "main" {
  cidr_block           = var.vpc_cidr
  enable_dns_support   = true  # Permite que instâncias resolvam nomes DNS internos
  enable_dns_hostnames = true  # Atribui nomes DNS públicos e privados aos servidores

  tags = {
    Name = "${var.environment}-vpc"
  }
}

# 2. Internet Gateway (A porta de entrada e saída da VPC para a Internet pública)
resource "aws_internet_gateway" "gw" {
  vpc_id = aws_vpc.main.id

  tags = {
    Name = "${var.environment}-igw"
  }
}

# 3. Subnet Pública na Zona de Disponibilidade A (Onde ficará a EC2 da aplicação)
resource "aws_subnet" "subnet_a" {
  vpc_id                  = aws_vpc.main.id
  cidr_block              = var.subnet_az1_cidr
  availability_zone       = data.aws_availability_zones.available.names[0]
  map_public_ip_on_launch = true # A EC2 precisa receber um IP público para ser acessada pelo benchmark

  tags = {
    Name = "${var.environment}-subnet-public-a"
  }
}

# 4. Subnet Pública na Zona de Disponibilidade B/C
# NOTA TÉCNICA (Regra de Ouro da AWS):
# O serviço Amazon RDS exige OBRIGATORIAMENTE que o "DB Subnet Group" contenha subnets
# em pelo menos DUAS zonas de disponibilidade distintas, mesmo em instâncias Single-AZ.
# Isso garante que se um data center físico sofrer falha, a AWS saiba para onde migrar.
resource "aws_subnet" "subnet_b" {
  vpc_id                  = aws_vpc.main.id
  cidr_block              = var.subnet_az2_cidr
  availability_zone       = data.aws_availability_zones.available.names[1]
  map_public_ip_on_launch = true

  tags = {
    Name = "${var.environment}-subnet-public-b"
  }
}

# 5. Tabela de Roteamento Pública (Conecta as Subnets ao Internet Gateway)
resource "aws_route_table" "public" {
  vpc_id = aws_vpc.main.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.gw.id
  }

  tags = {
    Name = "${var.environment}-rt-public"
  }
}

# 6. Associações da Tabela de Roteamento às duas subnets
resource "aws_route_table_association" "a" {
  subnet_id      = aws_subnet.subnet_a.id
  route_table_id = aws_route_table.public.id
}

resource "aws_route_table_association" "b" {
  subnet_id      = aws_subnet.subnet_b.id
  route_table_id = aws_route_table.public.id
}

# 7. Subnet Group do Banco de Dados RDS
# Agrupa as subnets da VPC para que o PostgreSQL possa ser provisionado com segurança
resource "aws_db_subnet_group" "rds" {
  name        = "${var.environment}-rds-subnet-group"
  description = "Grupo de subnets da VPC dedicadas ao Amazon RDS PostgreSQL 16"
  subnet_ids  = [aws_subnet.subnet_a.id, aws_subnet.subnet_b.id]

  tags = {
    Name = "${var.environment}-rds-subnet-group"
  }
}
