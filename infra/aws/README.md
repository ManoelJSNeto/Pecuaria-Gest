# ☁️ Infraestrutura como Código (IaC) — Amazon AWS (TCC)
## PecuáriaGest: Ambiente Automatizado para Benchmark Científico

Este diretório contém a especificação completa de **Infraestrutura como Código (Terraform)** para provisionar o ambiente do **PecuáriaGest** na nuvem da **Amazon Web Services (AWS)** de forma 100% automatizada, reprodutível e dentro do **Nível Gratuito (Free Tier)**.

---

## 🏛️ Topologia Arquitetural

```
               [ Cliente / Máquina de Benchmark do Pesquisador ]
                                       │
                                       │ (Tráfego de Teste HTTP Port 8080)
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ AWS VPC (10.0.0.0/16) — Região: sa-east-1 (São Paulo)                       │
│                                                                             │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ 🌐 Subnet Pública A (10.0.1.0/24)                                     │  │
│  │   ┌─────────────────────────────────────────────────────────────────┐ │  │
│  │   │ EC2 t3.micro (Ubuntu 24.04 LTS — 2 vCPUs / 1 GB RAM)            │ │  │
│  │   │  • Docker Engine + Docker Compose:                              │ │  │
│  │   │     - Container Nginx (Proxy Reverso na porta 8080)             │ │  │
│  │   │     - Container PHP 8.3 FPM (PecuáriaGest Engine)               │ │  │
│  │   │  • IAM Role: AmazonSSMManagedInstanceCore (Zero SSH / Zero .pem)│ │  │
│  │   │  • Root Disk: 20 GB gp3 criptografado                           │ │  │
│  │   └───────────────────────────────┬─────────────────────────────────┘ │  │
│  │                                   │ SG: sg-pecuaria-web               │  │
│  └───────────────────────────────────┼───────────────────────────────────┘  │
│                                      │ Conexão TCP 5432 (Interna)           │
│  ┌───────────────────────────────────┼───────────────────────────────────┐  │
│  │ 🔒 Subnet Pública B / RDS Group   │                                   │  │
│  │   ┌───────────────────────────────▼─────────────────────────────────┐ │  │
│  │   │ Amazon RDS PostgreSQL 16 (db.t3.micro — 2 vCPUs / 1 GB RAM)     │ │  │
│  │   │  • Storage: 20 GB gp3 (Trava de Autoscaling ativa = 20 GB)      │ │  │
│  │   │  • Firewall: Aceita tráfego EXCLUSIVO da EC2 Web                │ │  │
│  │   │  • Acesso Público: DESATIVADO (Invisível na Internet)           │ │  │
│  │   └─────────────────────────────────────────────────────────────────┘ │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 🛡️ Garantias de Custo Zero (AWS Free Tier)

Todo o código foi desenhado para **evitar qualquer cobrança indevida**:
1. **EC2 `t3.micro`:** Coberta pelas 750 horas mensais gratuitas da AWS.
2. **RDS `db.t3.micro`:** Coberta pelas 750 horas mensais gratuitas do Amazon RDS para PostgreSQL.
3. **Storage de 20 GB gp3:** Limite gratuito do RDS é de até 20 GB. O código define `max_allocated_storage = 20` para impedir que a AWS aumente o disco automaticamente.
4. **Destruição com 1 Comando:** Ao finalizar a coleta de métricas, basta executar `terraform destroy` para apagar todos os recursos.

---

## 🚀 Passo a Passo: Do Zero ao Deploy em 5 Minutos

### 1. Pré-Requisitos na sua Máquina Windows

Abra o seu terminal **PowerShell** como Administrador e instale o Terraform e o AWS CLI:

```powershell
# 1. Instala o Terraform CLI oficial (HashiCorp)
winget install Hashicorp.Terraform

# 2. Instala o AWS CLI oficial (Amazon)
winget install Amazon.AWSCLI
```

> *Após a instalação, feche e abra o terminal novamente para atualizar as variáveis de ambiente.*

### 2. Autenticar sua Conta AWS

No painel da AWS (AWS Management Console):
1. Vá em **IAM ➔ Users ➔ Create User** (ou use seu usuário administrador).
2. Na aba **Security credentials**, clique em **Create access key** (escolha *CLI*).
3. No terminal PowerShell da sua máquina, digite:
   ```powershell
   aws configure
   ```
4. Cole o seu **AWS Access Key ID**, seu **AWS Secret Access Key**, digite `sa-east-1` como região padrão e aperte Enter.

---

### 3. Provisionar a Infraestrutura com Terraform

Navegue até a pasta da AWS e execute os comandos:

```powershell
# 1. Entre na pasta da infraestrutura
cd c:\xampp\htdocs\ondeSalvaWeb_XAMPP\Pecuaria-Gest\Pecuaria-Gest\infra\aws

# 2. Inicialize os provedores (baixa o plugin oficial da AWS)
terraform init

# 3. Visualize o plano de execução (verificação de segurança)
terraform plan

# 4. Aplique a criação da infraestrutura
terraform apply
```

O terminal exibirá a lista de recursos que serão criados e fará a pergunta:
```text
Do you want to perform these actions?
  Terraform will perform the actions described above.
  Only 'yes' will be accepted to approve.

  Enter a value: yes
```
Digite **`yes`** e aperte **Enter**.

O Terraform provisionará a VPC, as Subnets, o RDS PostgreSQL 16 e a EC2. O processo leva cerca de **3 a 5 minutos** (tempo padrão para a AWS inicializar a instância do RDS).

---

### 4. Conectando e Executando os Testes do TCC

Ao término do `terraform apply`, o terminal exibirá automaticamente os outputs:

```text
Apply complete! Resources: 12 added, 0 changed, 0 destroyed.

Outputs:

app_url = "http://54.232.xxx.xxx:8080"
ec2_public_ip = "54.232.xxx.xxx"
rds_endpoint = "tcc-benchmark-postgres16.cxxxx.sa-east-1.rds.amazonaws.com"

benchmark_triade_command = "node tests/benchmark/executar_triade.js --url http://54.232.xxx.xxx:8080 --env aws --modo oficial"
```

Aguarde cerca de **1 a 2 minutos** após o término para que o script de *bootstrap* interno da EC2 conclua a instalação do Docker e suba os contêineres.

#### 🧪 Disparando a Tríade de Testes contra a AWS:
Copie o comando exibido em `benchmark_triade_command` e execute no seu terminal:

```powershell
# Bateria Científica Completa N=3 contra a Nuvem:
node tests/benchmark/executar_triade.js --url http://SEU-IP-DA-AWS:8080 --env aws --modo oficial
```

#### 📊 Consolidando o Comparativo (Local vs Nuvem):
Após o teste da AWS terminar, execute o consolidador para gerar a tabela e gráficos comparativos lado a lado:

```powershell
node tests/benchmark/consolidar_triade.js
```

Abra o arquivo [`tests/benchmark/resultados/dashboard_comparativo_tcc.html`](../tests/benchmark/resultados/dashboard_comparativo_tcc.html) no seu navegador para ver o gráfico comparando a performance Local (verde) vs AWS (azul)!

---

### 5. Destruindo a Infraestrutura (Custo Zero Garantido)

Assim que você terminar a coleta de dados e os testes do TCC, destrua todos os recursos da nuvem para garantir que não haverá nenhuma cobrança:

```powershell
cd c:\xampp\htdocs\ondeSalvaWeb_XAMPP\Pecuaria-Gest\Pecuaria-Gest\infra\aws
terraform destroy
```

Digite **`yes`** e confirme. O Terraform apagará a EC2, o RDS, os discos EBS e a VPC em cerca de 2 minutos.

---

## 🔧 Estrutura dos Arquivos do Módulo

| Arquivo | Descrição Técnica |
|---|---|
| [`versions.tf`](versions.tf) | Declara as versões mínimas do Terraform (`>= 1.5.0`) e provedor AWS (`~> 5.0`). |
| [`variables.tf`](variables.tf) | Centraliza variáveis configuráveis (região, senhas, portas e instâncias Free Tier). |
| [`vpc.tf`](vpc.tf) | Cria a VPC dedicada `10.0.0.0/16`, Internet Gateway, subnets e o DB Subnet Group. |
| [`security_groups.tf`](security_groups.tf) | Define as regras de firewall (camada Web aberta nas portas 80/8080 e banco 5432 restrito). |
| [`iam.tf`](iam.tf) | Configura a Role IAM com `AmazonSSMManagedInstanceCore` para gerenciar a EC2 sem portas SSH. |
| [`rds.tf`](rds.tf) | Provisiona a instância do Amazon RDS PostgreSQL 16 (`db.t3.micro`, 20GB gp3). |
| [`ec2.tf`](ec2.tf) | Provisiona a máquina virtual EC2 Ubuntu 24.04 LTS com disco de 20GB. |
| [`user_data.sh`](user_data.sh) | Script bash executado no boot da EC2 (instala Docker, clona o Git e inicia os contêineres). |
| [`outputs.tf`](outputs.tf) | Exibe o IP público, URL da aplicação e comandos de benchmark prontos para copiar. |
