# Resultados Finais de Benchmark + Estudo de Caso de Segurança — PecuáriaGest TCC

> Dados da bateria oficial simétrica (18 execuções, N=3 por cenário, reset de banco entre repetições, auditoria estrita de gravação). Validado a partir dos CSVs brutos, não apenas da tabela consolidada.

---

## 1. Tabela consolidada de desempenho (dado final, validado)

| Ambiente | Concorrência | N | Vazão (req/s) | DP Vazão | Latência média (200 OK) | DP Latência | p50 | p95 | Erro real | Auditoria |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 🏠 Local (SQLite WAL) | 20 | 3 | **101,55 req/s** | ±2,64 | 188,6 ms | ±5,0 | 189,2 ms | 253,5 ms | 0,00% | 100% |
| 🏠 Local (SQLite WAL) | 50 | 3 | **72,36 req/s** | ±14,86 | 665,7 ms | ±128,2 | 698,4 ms | 990,9 ms | 0,00% | 100% |
| 🏠 Local (SQLite WAL) | 100 | 3 | **49,62 req/s** | ±2,69 | 1.925,5 ms | ±112,3 | 2.023,4 ms | 3.042,7 ms | 0,00% | 100% |
| ☁️ AWS (t3.micro + RDS) | 20 | 3 | **28,26 req/s** | ±0,78 | 692,1 ms | ±17,6 | 681,7 ms | 860,8 ms | 0,00% | 100% |
| ☁️ AWS (t3.micro + RDS) | 50 | 3 | **27,66 req/s** | ±0,31 | 1.741,6 ms | ±23,5 | 1.776,0 ms | 2.044,6 ms | 0,00% | 100% |
| ☁️ AWS (t3.micro + RDS) | 100 | 3 | **25,70 req/s** | ±0,92 | 3.726,3 ms | ±144,8 | 3.745,3 ms | 5.181,2 ms | 0,00% | 100% |

**Validado por reprocessamento independente dos 18 CSVs brutos** (não apenas da tabela gerada pelo script): taxa de erro HTTP real (`Status != 200`) confirmada em 0,00% em todos os cenários; auditoria de contadores de gravação confirmada em 100%; ausência de tendência de crescimento entre `run1`→`run3` dentro de cada cenário (o reset de banco eliminou o viés de acúmulo observado na bateria anterior).

---

## 2. Análise dos resultados

### 2.1 Achado principal — comportamento assimétrico sob carga

- **Local degrada com a carga**: vazão cai de 101,55 → 49,62 req/s (queda de ~51%) conforme a concorrência sobe de 20 para 100 usuários.
- **AWS mantém um teto estável**: vazão varia pouco (28,26 → 25,70 req/s, queda de apenas ~9%) no mesmo intervalo de carga.
- Isso inverte a leitura simplista de "nuvem sempre mais rápida": em termos de **vazão absoluta, o ambiente local é mais rápido em todos os níveis testados**. O diferencial da AWS não é velocidade, é **estabilidade/previsibilidade sob carga crescente** — um platô de capacidade bem definido, típico de infraestrutura gerenciada com recursos dimensionados (vCPU/RAM fixos), enquanto o ambiente local (SQLite com single-writer lock) sofre contenção de escrita mais acentuada conforme mais processos competem pelo mesmo arquivo de banco.

### 2.2 Por que a AWS é mais lenta em termos absolutos

A latência da AWS é sistematicamente maior que a do local em todos os cenários (692 ms vs 189 ms a 20 usuários, por exemplo). Isso é esperado e documentável: soma-se (a) RTT de rede entre o cliente e `us-east-1`, (b) I/O de rede entre EC2 e RDS (bancos em hosts separados, diferente do SQLite que é um arquivo local), e (c) a limitação de CPU/RAM da instância `t3.micro` (2 vCPU / 1 GB). Nenhum desses fatores é surpreendente — o ponto interessante para a discussão é que, **mesmo com essa desvantagem de latência**, a AWS não colapsa sob concorrência crescente, o que sugere maior previsibilidade operacional (relevante para um sistema de produção real, mesmo que não seja o mais rápido em bancada).

### 2.3 Limitação metodológica a declarar explicitamente

O arquivo `cpu_credits_metrics.json` **não contém dados reais** de `CPUCreditBalance`/`CPUUtilization` — todas as 12 entradas trazem apenas a mensagem `"AWS CLI credentials not bound in local shell"`, sem nenhum valor numérico coletado. Isso significa que **não é possível confirmar nem descartar esgotamento de créditos de CPU burstable como fator explicativo do teto de vazão da AWS**. Essa limitação deve ser declarada no texto, não omitida — é uma lacuna honesta, não um erro de execução do benchmark em si (a taxa de erro e a auditoria de gravação continuam 100% confiáveis).

### 2.4 Confundidor arquitetural a declarar

O ambiente Local usa **SQLite (WAL)** e a AWS usa **PostgreSQL (RDS)** — motores de banco diferentes, não apenas infraestruturas diferentes. Parte da diferença de comportamento sob concorrência (SQLite com contenção de escrita mais restritiva vs PostgreSQL com MVCC) é atribuível à escolha do banco, não exclusivamente ao ambiente cloud vs on-premise. Isso deve constar na seção de limitações/ameaças à validade.

---

## 3. Como documentar isso no capítulo 6 (Resultados e Discussões)

Sugestão de estrutura para a subseção de Desempenho:

1. **Apresentar a tabela consolidada** (seção 1 acima) como está — já é a versão limpa e auditada.
2. **Um gráfico de linha** com vazão (eixo Y) por nível de concorrência (eixo X), duas séries (Local/AWS) — visualiza imediatamente o cruzamento de comportamento (local degrada, AWS platô).
3. **Um gráfico de barras** com latência p95 por cenário — mostra o custo de cauda, mais informativo que só a média para argumentar sobre experiência do usuário sob pico de uso.
4. **Texto de discussão em 3 parágrafos**, nessa ordem:
   - O que os números mostram objetivamente (vazão, latência, sem interpretação ainda).
   - Explicação técnica do porquê (RTT de rede, I/O separado banco/app na AWS, single-writer lock do SQLite).
   - Implicação prática: para o caso de uso real do PecuáriaGest (sincronização assíncrona em background, não bloqueando o usuário na tela), a latência mais alta da AWS é menos crítica do que pareceria à primeira vista — mas a estabilidade sob carga é mais relevante para um sistema multiusuário em crescimento.
5. **Parágrafo de limitações** cobrindo os dois pontos da seção 2.3 e 2.4 acima (CPU credit não coletado; motores de banco diferentes) — isso demonstra rigor metodológico e antecipa perguntas da banca.

---

## 4. O pilar de Segurança — vocês já geraram a evidência, só falta documentá-la

Você está certo: o processo de proteger o endpoint `/api/benchmark/reset` **é, ele mesmo, um estudo de caso de segurança real**, com timeline, vulnerabilidade genuína encontrada, e mitigação em camadas. Isso é mais forte como evidência acadêmica do que qualquer descrição estática de "temos Security Groups", porque é **empírico e documentado no histórico de commits**.

### 4.1 Reconstituindo o estudo de caso a partir do que já aconteceu

| Etapa | O que foi encontrado | O que foi corrigido |
|---|---|---|
| 1. Vulnerabilidade inicial | Endpoint destrutivo (`TRUNCATE ... CASCADE`) protegido pela **mesma chave de API já documentada publicamente** (`X-API-KEY`) usada pelo endpoint de sincronização normal — qualquer cliente autorizado a sincronizar dados também conseguiria apagar o banco inteiro. | Rota separada, protegida por segredo dedicado (`X-BENCHMARK-SECRET`) e gate por variável de ambiente (`BENCHMARK_MODE`). |
| 2. Falha de defesa em profundidade | A ausência de `BENCHMARK_MODE=true` deveria por si só desativar a rota — mas não havia teste comprovando isso, só a alegação de que "está protegido". | Implementado e executado teste negativo com 3 casos (sem header, chave falsa, chave pública) — todos retornando `403` de fato, com evidência de saída de log. |
| 3. Risco de exposição de segredo | O valor do segredo apareceu em texto claro no chat e em pelo menos um commit — uma vez exposto, deixa de ser confiável mesmo estando "no lugar certo" (`.env`). | Segredo rotacionado (novo valor gerado via `openssl rand -hex 24`), removido de qualquer lugar visível. |
| 4. Risco de segredo empacotado na imagem | Risco identificado preventivamente: se o `.env` fosse copiado via `Dockerfile` (`COPY . .`) em vez de montado via volume, o segredo ficaria gravado na camada da imagem Docker, extraível mesmo sem acesso ao `.env` do host. | Confirmado `.dockerignore` excluindo `.env`/`.env.*`; segredo chega ao container **apenas** via volume mount em tempo de execução. |
| 5. Configuração de runtime insegura | `clear_env=yes` (padrão do PHP-FPM) descartava variáveis de ambiente passadas pelo Docker — falha operacional que, se não detectada, levaria a um "conserto" apressado (ex.: voltar a hardcodar segredo no código só para fazer funcionar). | `clear_env=no` configurado corretamente, com verificação de que não há `phpinfo()`/endpoint de debug e que `display_errors` está desligado em produção, para não reabrir a superfície de exposição que o `clear_env=no` amplia. |

### 4.2 Como escrever isso no TCC

Sugiro uma subseção própria em Resultados, algo como **"6.X — Estudo de caso: blindagem de um endpoint de manutenção"**, estruturada como um mini relato de resposta a incidente:

1. **Contexto**: durante a preparação do ambiente de testes de carga, foi necessário criar um endpoint de reset de dados para viabilizar repetições estatisticamente válidas.
2. **Descoberta**: esse endpoint, em sua primeira versão, herdava o mesmo mecanismo de autenticação do endpoint de produção — uma falha real de controle de acesso (mistura de superfícies de risco distintas sob a mesma credencial).
3. **Mitigação em camadas**: apresentar a tabela da seção 4.1 como está.
4. **Validação empírica**: reportar o teste negativo (3 casos, todos retornando 403) como evidência, não apenas a alegação.
5. **Conclusão**: isso demonstra na prática um princípio de segurança (least privilege / separação de superfícies de autenticação / defesa em profundidade) através de um caso real do próprio desenvolvimento do projeto, e não apenas de teoria descritiva de arquitetura.

Isso é **um resultado de segurança genuíno**, e tem uma vantagem grande sobre qualquer teste sintético: aconteceu de verdade, tem timestamps e commits reais como evidência, e mostra processo de correção — o que costuma impressionar mais numa banca do que um scan padrão.

### 4.3 O que ainda falta para cobrir o eixo "Segurança" por completo

O estudo de caso acima cobre muito bem a dimensão de **segurança de aplicação / controle de acesso**. Mas o título do TCC promete "Segurança" de forma mais ampla, e ainda falta a dimensão de **segurança de infraestrutura/rede** — isso é rápido de gerar e complementa muito bem o estudo de caso acima:

- [ ] Tentar conectar diretamente no endpoint do RDS (porta 5432) a partir de fora da VPC (seu notebook, por exemplo) e mostrar/print que a conexão é recusada — comprova empiricamente o isolamento de rede da subnet privada.
- [ ] Rodar um `nmap` simples contra o IP público da EC2 e mostrar que só as portas esperadas (80/443/22, ou 8080 se for o caso) estão abertas.
- [ ] Confirmar e printar o certificado TLS válido (Let's Encrypt) usado no tráfego HTTPS.

Com isso, o eixo Segurança fica com evidência tanto de aplicação (o estudo de caso do endpoint) quanto de infraestrutura (isolamento de rede), fechando a promessa do título de forma completa e com dados reais nos dois níveis.
