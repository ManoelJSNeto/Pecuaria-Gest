# ==============================================================================
# SAÍDAS E COMANDOS PRONTOS (OUTPUTS)
# PecuáriaGest - Arquitetura em Nuvem para TCC
# ==============================================================================
# Ao finalizar a execução do "terraform apply", o Terraform exibirá no seu terminal
# todas as informações abaixo já formatadas e prontas para uso imediato.
# ==============================================================================

output "app_url" {
  description = "URL direta no seu navegador para acessar o painel web do PecuáriaGest na AWS"
  value       = "http://${aws_instance.app_server.public_ip}:${var.app_port}"
}

output "ec2_public_ip" {
  description = "Endereço IP público da máquina virtual EC2"
  value       = aws_instance.app_server.public_ip
}

output "rds_endpoint" {
  description = "Endpoint privado interno do Amazon RDS PostgreSQL 16"
  value       = aws_db_instance.postgres.address
}

output "rds_port" {
  description = "Porta do banco de dados PostgreSQL"
  value       = aws_db_instance.postgres.port
}

output "ssm_connect_command" {
  description = "Comando para conectar diretamente no terminal da EC2 via AWS Systems Manager (sem SSH / sem .pem)"
  value       = "aws ssm start-session --target ${aws_instance.app_server.id} --region ${var.aws_region}"
}

output "benchmark_triade_command" {
  description = "COMANDO OFICIAL DO TCC: Copie e cole no seu terminal local para disparar a Tríade contra a AWS!"
  value       = "node tests/benchmark/executar_triade.js --url http://${aws_instance.app_server.public_ip}:${var.app_port} --env aws --modo oficial"
}

output "benchmark_modo_rapido_command" {
  description = "Comando de teste rápido (~15s) para validar se a nuvem está respondendo perfeitamente"
  value       = "node tests/benchmark/executar_triade.js --url http://${aws_instance.app_server.public_ip}:${var.app_port} --env aws"
}

output "credenciais_acesso_web" {
  description = "Credenciais padrão para login no painel administrativo"
  value       = "Email: admin@fazenda.com | Senha: admin123"
}
