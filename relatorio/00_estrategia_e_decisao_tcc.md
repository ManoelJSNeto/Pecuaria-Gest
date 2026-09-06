# 🧭 Memória de Decisão Estratégica: O Direcionamento do TCC

> **Documento de Alinhamento:** Registra a evolução da proposta do TCC, os dilemas enfrentados, a análise de viabilidade acadêmica e a decisão estratégica final aprovada pela equipe.

---

## 1. O Dilema: Separar em Dois TCCs ou Manter Unificado?

### O Cenário Inicial
O projeto **PecuáriaGest** nasceu como um instrumento de apoio: uma aplicação representativa (workload real) criada com o objetivo de gerar tráfego e requisições para testar ambientes de **Computação em Nuvem** versus **Servidores Locais (On-Premise)**.

### A Mudança de Dinâmica
À medida que o desenvolvimento avançou, a aplicação ganhou maturidade profissional:
* Criação de um aplicativo Android nativo (Capacitor 8) com motor offline;
* Implementação de regras zootécnicas e sanitárias complexas;
* Design System dedicado para legibilidade sob sol forte;
* Desacoplamento da arquitetura em contêineres Docker (Nginx, PHP 8.3-FPM e PostgreSQL 16).

Com isso, o software tornou-se robusto e visualmente atraente, gerando uma dúvida na equipe:  
> *"Deveríamos abandonar os testes de nuvem e produzir um novo TCC focado 100% no desenvolvimento de software do PecuáriaGest?"*

---

## 2. Análise Crítica dos Riscos de Separação

Ao analisar os impactos acadêmicos e operacionais, identificou-se que separar o trabalho ou abandonar o comparativo de nuvem traria sérios prejuízos:

1. **Alinhamento com a Orientação:** O professor orientador solicitou especificamente a **reexecução dos testes de carga** aproveitando a nova arquitetura desacoplada (Docker + PostgreSQL nativo). Descartar esses testes quebraria o direcionamento dado pelo orientador.
2. **Risco de Avaliação da Banca:** Trabalhos focados exclusivamente em "construção de software comercial/CRUD" enfrentam críticas frequentes em bancas de Ciência da Computação e Engenharia por falta de rigor empírico ou contribuição científica mensurável.
3. **Duplicação de Esforço e Prazos:** Iniciar a escrita de um TCC do zero consumiria meses de retrabalho com prazos de entrega em andamento.

---

## 3. A Decisão Tomada: União Sinérgica (O Modelo Campeão)

A equipe decidiu **manter os temas unificados em um único trabalho de alto impacto**, estruturado da seguinte forma:

```
┌────────────────────────────────────────────────────────────────────────┐
│                          TEMA CENTRAL DO TCC                           │
│  Análise Comparativa de Desempenho e Custos de uma Arquitetura         │
│  Distribuída para Gestão Agropecuária: On-Premise vs Nuvem (AWS)       │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
         ┌──────────────────────────┴──────────────────────────┐
         ▼                                                     ▼
 📦 O ARTEFATO EMPÍRICO                                🔬 O EXPERIMENTO CIENTÍFICO
   O Sistema PecuáriaGest                               Testes de Carga & Nuvem
 • App Mobile Offline (Capacitor)                     • Simulações Concorrentes (JMeter)
 • Painel Web & Gestão Remota                         • Local (Docker) vs AWS (EC2 + RDS)
 • Solução para a ausência de TI rural                • Latência, Throughput, CPU/RAM
 • Validação com produtores reais                     • Análise de Viabilidade Financeira
```

### O Argumento Irrefutável de Campo (A Justificativa da Nuvem)
A própria realidade da fazenda justifica academicamente o uso da nuvem:
* Uma propriedade rural não dispõe de infraestrutura física de datacenter (falta de nobreak industrial, rede elétrica instável, poeira, link de internet oscilante, ausência de equipe de suporte local).
* Manter um servidor físico na sede da fazenda representa risco de indisponibilidade e perda de dados.
* O modelo proposto resolve a equação: **o vaqueiro opera offline no curral** e a **nuvem gerenciada (AWS)** recebe as sincronizações com alta disponibilidade, permitindo que o gestor/proprietário acompanhe a fazenda mesmo estando na cidade.

---

## 4. O Roadmap de Execução Consolidado

1. **Cadernos de Relatório:** Alimentar a documentação nas pastas `relatorio/` estruturando cada parte da monografia.
2. **Reexecução dos Testes com JMeter:** Executar a bateria de testes de carga simulando 20, 50 e 100 sincronizações simultâneas contra o ambiente Docker local e contra a AWS.
3. **Refinamento de Usabilidade Mobile:** Conectar via login direto e manter o vaqueiro logado para garantir trabalho contínuo no curral.
4. **Fechamento do Artigo/Monografia:** Tabulação dos resultados, gráficos de desempenho e análise de custos.
