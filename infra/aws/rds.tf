# ==============================================================================
# BANCO DE DADOS GERENCIADO: AMAZON RDS (POSTGRESQL 16)
# PecuáriaGest - Arquitetura em Nuvem para TCC
# ==============================================================================
# O Amazon Relational Database Service (RDS) abstrai o provisionamento de hardware,
# patches de sistema operacional e backups automáticos.
# Todas as configurações abaixo foram calibradas para atender rigorosamente às
# regras do Nível Gratuito (AWS Free Tier - Custo Zero por 12 meses).
# ==============================================================================

resource "aws_db_instance" "postgres" {
  identifier = "${var.environment}-postgres16"

  # Motor de Banco de Dados: PostgreSQL 16 oficial
  # Garante simetria exata com a imagem "postgres:16-alpine" utilizada no Docker local
  engine         = "postgres"
  engine_version = "16.3"

  # Dimensionamento do Hardware (Elegível ao Free Tier do RDS: 750 horas/mês gratuitas)
  instance_class = var.rds_instance_class # db.t3.micro (2 vCPUs, 1 GB de RAM)

  # Armazenamento em Disco
  allocated_storage = 20 # 20 GB de armazenamento SSD (gp3) gratuito
  storage_type      = "gp3"

  # TRAVA FINANCEIRA CRÍTICA PARA O TCC (Proteção de Gastos):
  # A AWS por padrão tenta ativar o "Storage Autoscaling" que aumenta o disco para 1000GB
  # quando enche, gerando cobranças. Definir max_allocated_storage = 20 bloqueia o crescimento
  # além dos 20 GB gratuitos do Free Tier!
  max_allocated_storage = 20

  # Credenciais e Nome da Base
  db_name  = var.db_name
  username = var.db_username
  password = var.db_password
  port     = 5432

  # Isolamento de Rede e Firewall
  db_subnet_group_name   = aws_db_subnet_group.rds.name
  vpc_security_group_ids = [aws_security_group.db.id]

  # SEGURANÇA ESTRITA: O banco NÃO possui IP público e NÃO pode ser acessado de fora da VPC
  publicly_accessible = false

  # Alta Disponibilidade (Multi-AZ)
  # Deixamos desativado (false) porque Multi-AZ dobra o custo e não é coberto pelo Free Tier
  multi_az = false

  # Políticas de Descarte e Limpeza (Crucial para o Terraform):
  # "skip_final_snapshot = true" permite que o comando "terraform destroy" apague o banco
  # em 1 minuto sem ficar esperando criar uma cópia de segurança desnecessária.
  skip_final_snapshot       = true
  final_snapshot_identifier = "${var.environment}-final-snapshot"
  deletion_protection       = false

  # Backups diários com retenção mínima de 1 dia (requisito para criação do RDS)
  backup_retention_period = 1

  # Congela a versão para evitar que a AWS atualize o PostgreSQL no meio de um benchmark
  auto_minor_version_upgrade = false

  tags = {
    Name = "${var.environment}-postgres16"
  }
}
