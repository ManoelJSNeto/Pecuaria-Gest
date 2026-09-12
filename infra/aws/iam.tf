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
# No AWS Academy Learner Lab, a criação de novas roles IAM é bloqueada por SCP.
# O parâmetro 'use_aws_academy_lab_role = true' utiliza automaticamente o perfil 'LabInstanceProfile'.
resource "aws_iam_role" "ec2_ssm_role" {
  count       = var.use_aws_academy_lab_role ? 0 : 1
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
resource "aws_iam_role_policy_attachment" "ssm_core" {
  count      = var.use_aws_academy_lab_role ? 0 : 1
  role       = aws_iam_role.ec2_ssm_role[0].name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}

# 3. Instance Profile (O contêiner de identidade que é anexado diretamente à EC2)
resource "aws_iam_instance_profile" "ec2_profile" {
  count = var.use_aws_academy_lab_role ? 0 : 1
  name  = "${var.environment}-ec2-instance-profile"
  role  = aws_iam_role.ec2_ssm_role[0].name

  tags = {
    Name = "${var.environment}-ec2-instance-profile"
  }
}
