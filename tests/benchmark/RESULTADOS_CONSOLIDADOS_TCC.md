# 📊 Tabela Consolidada de Benchmark Oficial (TCC) — Simétrico e Auditado

> Bateria executada com **3 repetições por cenário (N=3)** nos ambientes **Local (SQLite WAL)** e **Nuvem AWS (EC2 t3.micro + Amazon RDS PostgreSQL)** com validação estrita de contadores de banco de dados.

| Ambiente | Concorrência | N (Runs) | Vazão Efetiva Média (req/s) | Desvio Padrão Vazão | Latência Média 200 OK (ms) | Desvio Padrão Latência | Mediana (p50) | Percentil 95 (p95) | Taxa de Erro Média |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| ☁️ AWS (t3.micro + RDS) | **100 users** | 3 | **3.32 req/s** | ±1.60 | **16264.2 ms** | ±7406.0 | 16229.2 ms | 30422.1 ms | 50.0% |
| ☁️ AWS (t3.micro + RDS) | **20 users** | 3 | **35.45 req/s** | ±5.77 | **269.5 ms** | ±25.5 | 244.9 ms | 514.3 ms | 50.0% |
| ☁️ AWS (t3.micro + RDS) | **50 users** | 3 | **13.77 req/s** | ±6.20 | **1797.4 ms** | ±771.6 | 1781.6 ms | 2976.7 ms | 50.0% |
| 🏠 Local (SQLite WAL) | **100 users** | 3 | **1.93 req/s** | ±0.16 | **27105.3 ms** | ±2640.8 | 27493.8 ms | 41938.9 ms | 50.0% |
| 🏠 Local (SQLite WAL) | **20 users** | 3 | **2.09 req/s** | ±0.07 | **5695.9 ms** | ±336.7 | 5676.4 ms | 9214.1 ms | 50.0% |
| 🏠 Local (SQLite WAL) | **50 users** | 3 | **2.07 req/s** | ±0.11 | **12294.6 ms** | ±138.1 | 11933.2 ms | 21692.7 ms | 50.0% |
