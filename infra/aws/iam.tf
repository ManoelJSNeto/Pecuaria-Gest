# ==============================================================================
# GESTÃO DE IDENTIDADE E ACESSO (IAM & AWS SYSTEMS MANAGER)
# PecuáriaGest - Arquitetura em Nuvem para TCC
# ==============================================================================
# Este arquivo configura o acesso administrativo seguro à máquina virtual sem
# necessidade de abrir portas de firewall (SSH 22) e sem chaves privadas (.pem).
# A autenticação é gerida de ponta a ponta pelo AWS Systems Manager (SSM) e
# auditada no AWS CloudTrail.
# ==============================================================================

# 1. IAM Role (Função de Identidade da EC2)
# Define que o serviço EC2 da AWS tem permissão de assumir esta identidade
resource "aws_iam_role" "ec2_ssm_role" {
  name        = "${var.environment}-ec2-ssm-role"
  description = "Role IAM que permite a EC2 se comunicar com o AWS Systems Manager (SSM)"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "ec2.amazonaws.com"
        }
      }
    ]
  })

  tags = {
    Name = "${var.environment}-ec2-ssm-role"
  }
}

# 2. Anexo da Política Gerenciada Oficial da AWS (AmazonSSMManagedInstanceCore)
# Esta política concede as permissões estritas para:
# - Registrar a instância no console do Systems Manager
# - Permitir abertura de terminais interativos seguros (Session Manager)
# - Enviar comandos remotos automatizados e coletar telemetrias de CPU/RAM
resource "aws_iam_role_policy_attachment" "ssm_core" {
  role       = aws_iam_role.ec2_ssm_role.name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}

# 3. Instance Profile (O contêiner de identidade que é anexado diretamente à EC2)
resource "aws_iam_instance_profile" "ec2_profile" {
  name = "${var.environment}-ec2-instance-profile"
  role = aws_iam_role.ec2_ssm_role.name

  tags = {
    Name = "${var.environment}-ec2-instance-profile"
  }
}
