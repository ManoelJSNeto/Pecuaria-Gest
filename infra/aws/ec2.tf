# ==============================================================================
# MÁQUINA VIRTUAL DE APLICAÇÃO (AMAZON EC2 - T3.MICRO)
# PecuáriaGest - Arquitetura em Nuvem para TCC
# ==============================================================================
# Este arquivo provisiona a máquina virtual EC2 que executará a aplicação
# web e os contêineres Docker (Nginx + PHP 8.3 FPM).
# O sistema operacional selecionado é o Ubuntu Server 24.04 LTS oficial (Canonical).
# ==============================================================================

# 1. Consulta dinâmica da AMI Oficial mais recente do Ubuntu 24.04 LTS (Noble Numbat)
# Isso elimina a necessidade de codificar "IDs de AMI" fixos que mudam entre regiões
data "aws_ami" "ubuntu" {
  most_recent = true
  owners      = ["099720109477"] # ID da conta oficial da Canonical (criadora do Ubuntu)

  filter {
    name   = "name"
    values = ["ubuntu/images/hvm-ssd-gp3/ubuntu-noble-24.04-amd64-server-*"]
  }

  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }
}

# 2. Instância EC2 (Servidor Web & Aplicação)
resource "aws_instance" "app_server" {
  ami           = data.aws_ami.ubuntu.id
  instance_type = var.ec2_instance_type # t3.micro (2 vCPUs, 1 GB de RAM - Free Tier)

  # Alocação de Rede
  subnet_id                   = aws_subnet.subnet_a.id
  vpc_security_group_ids      = [aws_security_group.web.id]
  associate_public_ip_address = true # Recebe IP público IPv4 para o teste de benchmark

  # Segurança & Gerenciamento via AWS Systems Manager
  # Anexa o perfil IAM que permite login no terminal sem SSH e sem chave .pem
  iam_instance_profile = aws_iam_instance_profile.ec2_profile.name

  # Armazenamento em Disco SSD (EBS)
  root_block_device {
    volume_type           = "gp3"
    volume_size           = var.ec2_disk_size_gb # 20 GB (Totalmente gratuito no Free Tier)
    delete_on_termination = true                 # Apaga o disco automaticamente no terraform destroy
    encrypted             = true                 # Criptografia de dados em repouso ativada

    tags = {
      Name = "${var.environment}-ec2-root-disk"
    }
  }

  # Script de Inicialização (User Data / Cloud-Init)
  # Injeta as variáveis de banco de dados e endpoints dinamicamente no script bash
  user_data = templatefile("${path.module}/user_data.sh", {
    GIT_BRANCH       = var.git_branch
    GITHUB_REPO_URL  = var.github_repo_url
    APP_PORT         = var.app_port
    API_KEY          = var.api_key
    BENCHMARK_SECRET = var.benchmark_secret
    DB_HOST          = aws_db_instance.postgres.address # Pega automaticamente o endpoint do RDS
    DB_NAME          = var.db_name
    DB_USER          = var.db_username
    DB_PASS          = var.db_password
  })

  # ORDEM CRÍTICA DE PROVISIONAMENTO:
  # A EC2 depende que o RDS já esteja pronto ("available") antes de rodar o script
  # de inicialização, evitando erros de conexão do PHP durante a subida dos contêineres.
  depends_on = [aws_db_instance.postgres]

  tags = {
    Name = "${var.environment}-ec2-app"
  }
}
