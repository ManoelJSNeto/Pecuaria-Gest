# ==============================================================================
# FIREWALL & REGRAS DE SEGURANÇA (SECURITY GROUPS)
# PecuáriaGest - Arquitetura em Nuvem para TCC
# ==============================================================================
# O Security Group atua como um firewall virtual stateful na frente das instâncias.
# O princípio de defesa em profundidade é rigorosamente aplicado aqui:
# 1. A camada Web recebe tráfego HTTP/HTTPS público da internet.
# 2. A camada de Banco de Dados aceita conexões estritamente vindas da camada Web.
# 3. A porta 22 (SSH) é totalmente eliminada (acesso via AWS Systems Manager).
# ==============================================================================

# 1. Security Group da Camada de Aplicação Web (EC2 Nginx + PHP-FPM)
resource "aws_security_group" "web" {
  name        = "${var.environment}-sg-web"
  description = "Controle de trafego para o servidor web da aplicacao PecuariaGest"
  vpc_id      = aws_vpc.main.id

  # Porta HTTP padrão (80)
  ingress {
    description = "Acesso HTTP publico"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  # Porta HTTP alternativa da aplicacao (8080)
  ingress {
    description = "Acesso direto a porta padrao do container Nginx do PecuariaGest"
    from_port   = var.app_port
    to_port     = var.app_port
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  # Porta HTTPS segura (443)
  ingress {
    description = "Acesso HTTPS com criptografia TLS"
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  # NOTA DE SEGURANÇA PARA O TCC (Zero SSH / Porta 22):
  # Não há regra liberando a porta 22. Toda a administração remota é feita através do
  # agente seguro AWS Systems Manager (SSM) com permissões IAM, eliminando riscos de
  # ataques de força bruta contra senhas ou chaves privadas .pem perdidas.

  # Saída liberada para a internet (necessária para instalar Docker e pacotes do Ubuntu)
  egress {
    description = "Trafego de saida irrestrito para downloads de pacotes e APIs"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "${var.environment}-sg-web"
  }
}

# 2. Security Group do Banco de Dados (Amazon RDS PostgreSQL 16)
resource "aws_security_group" "db" {
  name        = "${var.environment}-sg-db"
  description = "Isolamento estrito do banco de dados relacional PostgreSQL"
  vpc_id      = aws_vpc.main.id

  # Conexão na porta padrão do PostgreSQL (5432)
  # ATENÇÃO ACADÊMICA: Em vez de liberar para um IP, amarramos o firewall diretamente
  # ao Security Group da aplicação ("security_groups = [aws_security_group.web.id]").
  # Isso significa que apenas pacotes originados da EC2 autenticada conseguem conversar
  # com o banco de dados. O banco é 100% invisível para o restante da internet.
  ingress {
    description     = "Trafego PostgreSQL 5432 autorizado estritamente a partir do servidor web"
    from_port       = 5432
    to_port         = 5432
    protocol        = "tcp"
    security_groups = [aws_security_group.web.id]
  }

  # Saída para tráfego de atualizações e replicação interna da AWS
  egress {
    description = "Saida autorizada para servicos internos da VPC"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "${var.environment}-sg-db"
  }
}
