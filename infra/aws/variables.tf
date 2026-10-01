# ==============================================================================
# VARIÁVEIS DE CONFIGURAÇÃO (AWS)
# PecuáriaGest - Arquitetura em Nuvem para TCC
# ==============================================================================
# Este arquivo centraliza todas as variáveis que você pode customizar sem precisar
# mexer no código de infraestrutura. Todas possuem valores padrão seguros,
# dimensionados para o Nível Gratuito (AWS Free Tier - Custo Zero).
# ==============================================================================

variable "aws_region" {
  description = "Região da AWS onde os recursos serão criados. Para contas de estudante do AWS Academy Learner Lab, a região obrigatória suportada pelo laboratório é 'us-east-1' (N. Virginia)."
  type        = string
  default     = "us-east-1"
}

variable "use_aws_academy_lab_role" {
  description = "Define se deve utilizar o perfil 'LabInstanceProfile' pré-existente do AWS Academy Learner Lab (evitando bloqueios de SCP de criação de Role IAM)."
  type        = bool
  default     = true
}

variable "environment" {
  description = "Identificador do ambiente. Utilizado para nomear recursos e tags."
  type        = string
  default     = "tcc-benchmark"
}

# ── Redes (VPC e Subnets) ─────────────────────────────────────────────────────

variable "vpc_cidr" {
  description = "Bloco de IPs privados da VPC principal."
  type        = string
  default     = "10.0.0.0/16"
}

variable "subnet_az1_cidr" {
  description = "Bloco de IPs da Subnet na Zona de Disponibilidade A."
  type        = string
  default     = "10.0.1.0/24"
}

variable "subnet_az2_cidr" {
  description = "Bloco de IPs da Subnet na Zona de Disponibilidade B/C (Necessária para o RDS Subnet Group)."
  type        = string
  default     = "10.0.2.0/24"
}

# ── Camada de Computação (EC2 - Aplicação) ────────────────────────────────────

variable "ec2_instance_type" {
  description = "Tamanho da máquina virtual EC2. 't3.micro' possui 2 vCPUs e 1 GB de RAM, 100% coberta pelo Free Tier (750 horas/mês)."
  type        = string
  default     = "t3.micro"
}

variable "ec2_disk_size_gb" {
  description = "Tamanho do disco SSD (EBS gp3) da máquina virtual. O Free Tier cobre até 30 GB no total."
  type        = number
  default     = 20
}

variable "app_port" {
  description = "Porta TCP exposta pelo Nginx na máquina virtual para acesso à aplicação."
  type        = number
  default     = 8080
}

# ── Camada de Dados (Amazon RDS - PostgreSQL 16) ──────────────────────────────

variable "rds_instance_class" {
  description = "Classe da instância do banco de dados gerenciado. 'db.t3.micro' possui 2 vCPUs e 1 GB de RAM, elegível ao Free Tier do RDS."
  type        = string
  default     = "db.t3.micro"
}

variable "db_name" {
  description = "Nome do banco de dados relacional que será criado automaticamente."
  type        = string
  default     = "pecuaria"
}

variable "db_username" {
  description = "Nome de usuário mestre (administrador) do banco de dados PostgreSQL."
  type        = string
  default     = "pecuaria_user"
}

variable "db_password" {
  description = "Senha do banco de dados. Marcada como sensível para não aparecer em texto puro nos logs do Terraform."
  type        = string
  sensitive   = true
  default     = "pecuaria_pass_2026"
}

# ── Parâmetros da Aplicação & Deploy Automático ────────────────────────────────

variable "github_repo_url" {
  description = "URL do repositório Git que a EC2 clonará durante o bootstrap."
  type        = string
  default     = "https://github.com/ManoelJSNeto/Pecuaria-Gest.git"
}

variable "git_branch" {
  description = "Branch que será clonada para deploy na nuvem."
  type        = string
  default     = "feature/benchmark-tcc-final"
}

variable "api_key" {
  description = "Chave secreta para autenticação de dispositivos móveis e testes de benchmark."
  type        = string
  default     = "pecuaria-mobile-key"
}

variable "benchmark_secret" {
  description = "Chave secreta de proteção do endpoint de reset simétrico (/api/benchmark/reset)."
  type        = string
  default     = "pecuaria-benchmark-secret-2026"
}
