# ☁️ Caderno de Computação em Nuvem, Benchmarks & Infraestrutura

> **Finalidade:** Registrar a fundamentação experimental, o isolamento das variáveis científicas, o desenho dos testes de estresse com Apache JMeter e a arquitetura segura na AWS para a monografia do TCC.

---

## 1. O Problema da Infraestrutura Rural e a Hipótese da Nuvem

### 1.1 A Inviabilidade Técnica do Servidor On-Premise na Fazenda
Uma propriedade pecuária média no Brasil enfrenta restrições severas de infraestrutura física:
* **Instabilidade Elétrica:** Quedas constantes de energia, picos de tensão provocados por raios e ausência de nobreaks com autonomia prolongada.
* **Ambiente Hostil:** Alta incidência de poeira, umidade e calor no barracão/sede, acelerando a degradação de hardware comum.
* **Ausência de Suporte Local:** A fazenda não possui profissional de TI para realizar rotinas de backup, aplicar patches de segurança do sistema operacional ou restabelecer serviços caídos.
* **Link de Internet Oscilante:** Conexões rurais (via rádio ou satélite) possuem IP dinâmico sob CGNAT, impedindo o redirecionamento de portas (port forwarding) e o acesso remoto estável do proprietário pela cidade.

### 1.2 A Hipótese Científica da Pesquisa
Uma infraestrutura gerenciada em computação em nuvem (**Amazon Web Services — AWS**), quando combinada com uma arquitetura móvel *offline-first*, é capaz de:
1. Absorver as rajadas de sincronização em lote enviadas pelos dispositivos de campo com menor latência e zero perda de pacotes;
2. Proporcionar alta disponibilidade (99,9%+) e tolerância a desastres através de backups automatizados;
3. Apresentar viabilidade financeira (OpEx previsível em nuvem versus o custo de aquisição e manutenção de hardware dedicado — CapEx).

---

## 2. Rigor Científico: O Isolamento da Variável Independente

### 2.1 A Crítica Metodológica do Orientador
Nos testes preliminares apresentados à orientação, identificou-se uma fragilidade no desenho experimental:
* O ambiente local utilizava banco de dados em arquivo (**SQLite**), enquanto a proposta de nuvem previa um banco relacional gerenciado (**PostgreSQL RDS**).
* **O Viés Metodológico:** A diferença de desempenho observada nas requisições concorrentes não refletia puramente a eficiência da nuvem versus local, mas sim as discrepâncias intrínsecas entre os motores de banco (bloqueio de arquivo do SQLite vs concorrência MVCC do PostgreSQL).

### 2.2 A Correção da Variável de Teste
Para garantir o rigor acadêmico exigido em bancas de Ciência da Computação:
* **Padronização do Banco:** O SQLite foi completamente erradicado do código-fonte. O sistema foi refatorado para operar exclusivamente com **PostgreSQL 16 nativo**.
* **Equiparação dos Ambientes:** A stack de contêineres desacoplados (Nginx + PHP 8.3-FPM + PostgreSQL 16) roda idêntica tanto no servidor local quanto na instância em nuvem.
* **Variável Independente Isolada:** O único elemento que varia no experimento é o **ambiente de hospedagem** (On-Premise x Nuvem AWS), validando cientificamente as métricas de comparação.

---

## 3. Expertise Técnica e Escolha da Plataforma AWS

A seleção da **Amazon Web Services (AWS)** fundamenta-se em dois pilares estratégicos:
1. **Maturidade e Padrão de Indústria:** A AWS é a líder global em serviços de nuvem pública, dispondo de zonas de disponibilidade locais (região `sa-east-1` em São Paulo) com baixa latência para o território nacional.
2. **Qualificação da Equipe:** Dois integrantes do grupo de pesquisa são competidores de Computação em Nuvem pelo **SENAI** (preparados para olimpíadas técnicas de TI), trazendo domínio prático de arquitetura em nuvem e suporte de mentores especializados para além do currículo tradicional da ETEC.

---

## 4. Arquitetura de Nuvem com Foco em Segurança Avançada

Para além da performance de rede, o projeto implementa padrões corporativos de segurança em nuvem recomendados pela AWS (*Well-Architected Framework*):

### 4.1 Eliminação de Portas de Gerenciamento Expostas (SSH ➔ AWS SSM)
* **A Vulnerabilidade Tradicional:** Abrir a porta TCP 22 (SSH) para a internet expõe o servidor a varreduras automatizadas e ataques de força bruta, além do risco de vazamento de chaves privadas `.pem`.
* **A Solução Adotada (AWS Systems Manager — SSM Session Manager):** O acesso administrativo à instância EC2 ocorre via canal criptografado da AWS através do IAM, **com a porta 22 totalmente fechada nos Security Groups**. Não há necessidade de IP público direto ou chaves SSH estáticas. Toda sessão é auditada e registrada no **AWS CloudTrail**.

### 4.2 Políticas de Menor Privilégio (AWS IAM)
* Definição de papéis (*IAM Roles*) restritos vinculados à instância EC2, permitindo que a aplicação execute apenas ações essenciais (como envio de logs para o CloudWatch e leitura de parâmetros protegidos no SSM Parameter Store), sem credenciais fixadas em código (`hardcoded credentials`).

### 4.3 Isolamento de Rede em Camadas (VPC & Subnets)
* A instância web/aplicação fica em camada acessível externamente via HTTPS (porta 443 com certificado TLS).
* O banco de dados gerenciado (**Amazon RDS PostgreSQL**) reside em sub-rede privada, sem IP público, comunicando-se exclusivamente com a instância EC2 por regra restrita de Security Group na porta 5432.

---

## 5. Protocolo Experimental com Apache JMeter

### 5.1 O Cenário de Carga
Simulação de múltiplos trabalhadores de campo retornando simultaneamente do pasto para o alcance do sinal (Wi-Fi da sede ou 4G) e disparando o descarregamento de lotes pendentes no endpoint `/api/sync`.

### 5.2 Níveis de Concorrência
* **Cenário A (Rotina Normal):** 20 usuários concorrentes sincronizando lotes de 10 pesagens cada.
* **Cenário B (Pico de Manejo):** 50 usuários concorrentes simulando múltiplos vaqueiros fechando o lote do dia.
* **Cenário C (Ponto de Estresse):** 100 conexões simultâneas com payload completo (dados alfanuméricos + imagens comprimidas em Base64).

### 5.3 Métricas Monitoradas e Coletadas
1. **Latência / Tempo Médio de Resposta (ms):** Média, Mediana, P90 e P95.
2. **Vazão (Throughput / RPS):** Quantidade de lotes processados por segundo.
3. **Taxa de Erro (%):** Falhas de conexão, timeouts ou erros HTTP 500.
4. **Estresse Computacional:** Consumo de CPU (%) e Memória (MB) monitorados via `docker stats` (Local) e **Amazon CloudWatch** (AWS).
