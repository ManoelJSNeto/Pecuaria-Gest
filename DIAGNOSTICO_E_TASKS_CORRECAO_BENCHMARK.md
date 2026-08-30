# Diagnóstico do Benchmark e Plano de Correção — PecuáriaGest TCC

> **Instância AWS real:** Amazon EC2 `t3.micro` (Ubuntu 24.04 LTS) — *não* `t2.micro`. Esse dado deve ser corrigido em qualquer texto/dossiê que ainda cite `t2.micro`, incluindo a "Guia de Defesa" do dossiê e a tabela de arquitetura.

---

## 1. Diagnóstico — o que deu errado

Os testes de carga já executados (`benchmark/resultados/*.csv`) mostraram taxas de erro HTTP altas (14% no local, até 40% na AWS). A causa **não é falta de capacidade do servidor** — é um **bug de payload no próprio script de benchmark**, que envia requisições fora do contrato documentado da API.

### Evidência

Comparando `benchmark/run_benchmark.js` e `benchmark/plano_teste_jmeter.jmx` com a especificação oficial (`documentacao_tecnica/API.md`):

| Campo | Documentado na API (`API.md`) | Enviado pelo benchmark |
|---|---|---|
| Autenticação | Header `X-API-KEY: pecuaria-mobile-key` | Nenhum header de API key. Em vez disso, `auth_email` / `auth_senha` dentro do corpo JSON |
| Lista de animais novos | `"animais": [...]` | `"animais_novos": [...]` |
| Referência do animal em pesagens/saúde | `"animal_id": 1` (inteiro) | `"brinco": "BR0001"` (string) |

### Como isso aparece nos dados

- `GET /api/animais` (não usa esse payload): **0% de erro** em todos os cenários testados.
- `POST /api/sync` (usa o payload incompatível): até **80% de erro** (404 falhas em 500 requisições, no cenário de 100 usuários).
- No primeiro teste (10 usuários), o padrão de erro mudou de **401 Unauthorized** (round 1, sem `X-API-KEY`) para **500 Internal Server Error** (round 2, após inclusão de `auth_email`/`auth_senha` no corpo) — sinal de que o backend não reconhece esse mecanismo de autenticação e provavelmente quebra ao tentar ler campos que não existem (`animal_id` ausente, `animais_novos` não mapeado).

**Conclusão:** os números de throughput/latência coletados até agora não representam o limite real de capacidade da instância `t3.micro`. Representam, majoritariamente, requisições sendo rejeitadas por payload malformado. É necessário corrigir o script e refazer os testes antes de usar qualquer número no capítulo de Resultados do TCC.

---

## 2. Tasks para o agente (Antigravity) — correção do benchmark

Execute na ordem. Cada task tem critério de aceite — não avance para a próxima sem confirmar o critério.

### Task 1 — Corrigir o payload de `POST /api/sync` em `benchmark/run_benchmark.js`

- [ ] Adicionar o header `'X-API-KEY': 'pecuaria-mobile-key'` (ou o valor real da variável `API_KEY` configurada no `.env` do servidor) na chamada `fetch` do `POST /api/sync`.
- [ ] Remover `auth_email` e `auth_senha` do corpo do payload (não fazem parte do contrato da API).
- [ ] Renomear a chave `animais_novos` para `animais`.
- [ ] Nos objetos de `pesagens` e `saude`, trocar o campo `brinco` (string) por `animal_id` (inteiro). Isso exige usar o `id` retornado pelo `GET /api/animais` anterior (o script já busca essa lista — só precisa usar `list[i].id` em vez de `list[i].brinco`).
- **Critério de aceite:** o payload enviado por `POST /api/sync` bate campo a campo com o exemplo em `documentacao_tecnica/API.md`.

### Task 2 — Aplicar a mesma correção no `benchmark/plano_teste_jmeter.jmx`

- [ ] Repetir os 4 ajustes da Task 1 no corpo da requisição JMeter e no header manager do plano de teste.
- **Critério de aceite:** abrir o `.jmx` no JMeter e conferir visualmente que o header `X-API-KEY` está presente e o corpo bate com o padrão documentado.

### Task 3 — Validar manualmente antes de testar em carga

- [ ] Rodar **um único request manual** (via `curl` ou Postman) contra o `POST /api/sync` com o payload corrigido, tanto no ambiente local quanto na AWS.
- [ ] Confirmar resposta `200 OK` com o corpo `{"status": "success", ...}` conforme documentado.
- **Critério de aceite:** taxa de sucesso 100% nesse teste manual antes de qualquer teste de carga. Se ainda der erro, depurar o motivo antes de prosseguir — não rodar carga em cima de um endpoint que ainda falha no caso trivial.

### Task 4 — Reexecutar os testes de carga com desenho simétrico

- [ ] Rodar o benchmark corrigido nos **mesmos níveis de concorrência nos dois ambientes**: 20, 50 e 100 usuários — tanto local quanto AWS (hoje só existe local em 20 usuários).
- [ ] Rodar **2 a 3 repetições por cenário** (não apenas uma execução), para poder reportar média e desvio-padrão.
- [ ] Manter o mesmo número de rounds/requisições por worker em todos os cenários comparáveis (hoje há divergência: local com 120 reqs vs AWS com 200 reqs no "mesmo" cenário de 20 usuários).
- **Critério de aceite:** para cada nível de carga, existe 1 conjunto de CSVs do ambiente local e 1 do ambiente AWS, com o mesmo total de requisições configurado e ao menos 2 repetições.

### Task 5 — Corrigir o script de geração de relatório para separar sucesso de erro

- [ ] No cálculo de throughput e percentis de latência (`avgLatency`, `p50`, `p90`, `p95`, `p99`), gerar duas versões: (a) sobre **todas** as requisições, e (b) apenas sobre requisições com status `200`.
- [ ] Reportar a **taxa de erro por cenário** como uma métrica própria e visível na tabela de saída (não só no console), separada por endpoint (`GET /api/animais` vs `POST /api/sync`).
- **Critério de aceite:** o relatório final (`RESULTADOS_CONSOLIDADOS_TCC.md` e o dashboard HTML) exibe, para cada cenário: throughput/latência apenas de sucesso + taxa de erro isolada, sem misturar as duas coisas em uma média só.

### Task 6 — Corrigir referências à instância AWS

- [ ] Buscar e substituir todas as ocorrências de `t2.micro` por `t3.micro` no dossiê, no `RESULTADOS_CONSOLIDADOS_TCC.md` e no rascunho do TCC.
- **Critério de aceite:** nenhuma menção residual a `t2.micro` em nenhum arquivo do pacote.

---

## 3. Depois de refeito

Só depois que as Tasks 1–6 estiverem concluídas e os novos CSVs gerados:
- Recalcular a tabela consolidada de desempenho com números limpos (sucesso vs erro separados).
- Se ainda houver erro relevante sob carga alta **mesmo com payload correto**, esse já é um resultado legítimo de limite de capacidade da `t3.micro` — pode (e deve) ser discutido no capítulo de Resultados como achado real, não como ruído de teste.
