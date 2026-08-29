# 📊 PecuáriaGest — Suíte de Benchmark & Testes de Carga (TCC)

Esta pasta contém o ecossistema completo de testes de desempenho para o comparativo científico entre **Ambiente On-Premise (Servidor Local)** e **Ambiente Nuvem (AWS)**.

---

## 📁 Arquivos Disponíveis

1. **`run_benchmark.js`**: Motor em Node.js de alta precisão que simula conexões concorrentes, mede latência milissegundo a milissegundo com `performance.now()` e gera relatórios automáticos.
2. **`executar_todos_cenarios.ps1`**: Script PowerShell que roda automaticamente os 3 cenários de estresse do TCC (20, 50 e 100 usuários concorrentes).
3. **`plano_teste_jmeter.jmx`**: Arquivo de plano de testes oficial para o **Apache JMeter**, compatível com a interface gráfica e com o modo headless CLI.
4. **`resultados/`**: Diretório onde ficam gravados os arquivos `.csv` e o dashboard interativo `relatorio_benchmark.html`.

---

## 🚀 Como Executar os Testes

### Opção 1: Testar no Servidor Local (On-Premise)
Certifique-se de que o Apache/XAMPP está rodando na porta 8080 e execute:
```powershell
node tests/benchmark/run_benchmark.js --url http://localhost:8080 --concurrency 20 --rounds 5
```
Ou para rodar todos os cenários de uma vez:
```powershell
powershell -ExecutionPolicy Bypass -File tests/benchmark/executar_todos_cenarios.ps1 -Url http://localhost:8080
```

### Opção 2: Testar no Servidor em Nuvem (AWS)
Após realizar o deploy na AWS, basta apontar para o IP/domínio da nuvem:
```powershell
node tests/benchmark/run_benchmark.js --url http://SEU-IP-DA-AWS --concurrency 20 --rounds 5
```

---

## 📈 Métricas Geradas para o TCC

* **Throughput (Vazão):** Requisições completadas por segundo (`req/s`).
* **Latência:** Tempo Mínimo, Médio, Mediana (p50), Percentil 95 (p95) e Máximo (p99).
* **Taxa de Erros:** Percentual de falhas sob alta concorrência.
* **Dashboard HTML:** Gráfico interativo da curva de tempo de resposta pronto para capturas de tela da apresentação.
