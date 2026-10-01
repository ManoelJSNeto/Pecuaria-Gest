# ==============================================================================
# VERSÕES DO TERRAFORM E PROVEDORES OFICIAIS (AWS)
# PecuáriaGest - Arquitetura em Nuvem para TCC
# ==============================================================================
# Este arquivo define as versões mínimas necessárias do Terraform CLI e dos
# plugins de nuvem ("providers"). Isso garante que o código execute de forma
# idêntica e previsível em qualquer computador (Windows, Linux ou macOS).
# ==============================================================================

terraform {
  # Exige Terraform versão 1.5.0 ou superior (suporte a recursos modernos e funções)
  required_version = ">= 1.5.0"

  required_providers {
    # Provedor oficial da AWS mantido pela HashiCorp
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }

    # Provedor para gerar strings aleatórias ou senhas seguras se necessário
    random = {
      source  = "hashicorp/random"
      version = "~> 3.5"
    }
  }
}

# Configuração do provedor AWS
provider "aws" {
  # Região padrão definida no arquivo variables.tf (padrão: sa-east-1 / São Paulo)
  region = var.aws_region

  # Tags padrão aplicadas automaticamente a TODOS os recursos criados na AWS.
  # Isso é fundamental para rastreabilidade de custos no painel da AWS.
  default_tags {
    tags = {
      Projeto     = "PecuariaGest"
      Finalidade  = "TCC-Benchmark-Cientifico"
      Ambiente    = var.environment
      Gerenciado  = "Terraform"
      Repositorio = "https://github.com/ManoelJSNeto/Pecuaria-Gest"
    }
  }
}
