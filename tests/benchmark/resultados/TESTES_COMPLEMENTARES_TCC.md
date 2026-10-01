# 🧪 Relatório de Testes Complementares de Alto Valor Científico (TCC)

> **Data de Emissão:** 01/10/2026, 19:10:43  
> **Objetivo:** Complementar a Tríade de Benchmark com testes de caso real (Descarregamento de Campo em Lote, Compilação de Relatórios e Auditoria de Segurança de Nuvem).

---

## 1. Simulação de Lote Real de Campo (5 Vaqueiros Concorrentes)

*Cenário:* 5 operadores de campo sincronizando simultaneamente o trabalho de um dia inteiro via `POST /api/sync` (cada vaqueiro enviando **30 pesagens + 10 manejos sanitários + 5 novos animais** cadastrados offline).

| Métrica Avaliada | Ambiente Local (Docker) | Nuvem AWS (EC2 + RDS) | Análise Técnica |
|---|:---:|:---:|---|
| **Taxa de Sucesso dos Vaqueiros** | **100%** (5/5) | **100%** (5/5) | Transações atômicas sem conflito de lock |
| **Total de Registros Inseridos (ACID)** | **225 registros** | **225 registros** | Integridade referencial 100% mantida |
| **Tempo Total de Descarregamento** | **0.48 s** | **0.99 s** | Concorrência paralela em rede externa |
| **Latência Média por Vaqueiro** | **395.9 ms** | **799.8 ms** | RTT WAN Brasil ➔ Virgínia/EUA |
| **Vazão Efetiva de Dados** | **400.2 Kbps** | **194 Kbps** | Throughput de transmissão de pacotes JSON |

---

## 2. Compilação de Relatórios Oficiais do Rebanho

*Cenário:* Emissão de documentos formais para fiscalização zootécnica e crédito bancário através da renderização server-side.

| Relatório Emitido | Tamanho do Documento | Local (Docker) | Nuvem AWS (EC2 + RDS) |
|---|:---:|:---:|:---:|
| **Inventário Geral do Rebanho & Lotação** | ~3.6 KB | **62.5 ms** | **399.6 ms** |
| **Prontuário Individual do Animal** | ~3.6 KB | **70.4 ms** | **342.6 ms** |

---

## 3. Auditoria de Cibersegurança & Isolamento de Rede (Amazon RDS)

*Cenário:* Teste de penetração contra a porta do banco de dados relacional a partir da internet pública para validação de conformidade com a LGPD e boas práticas de nuvem.

* **Alvo Inspecionado:** `tcc-benchmark-postgres16.c9zbftik2z4d.us-east-1.rds.amazonaws.com:5432`
* **Status da Conexão Externa:** **🛡️ TOTALMENTE BLOQUEADA / INACESSÍVEL**
* **Comportamento do Firewall (AWS Security Group):** *TIMEOUT (Pacote descartado silenciosamente pelo Security Group da AWS)*
* **Conclusão:** O banco de dados relacional PostgreSQL 16 encontra-se estritamente isolado na VPC privada, sem exposição de IP público, aceitando tráfego exclusivamente originado da instância EC2 de aplicação através do `sg-tcc-benchmark-db`.
