# ============================================================
# PecuáriaGest — Execução Completa dos 3 Cenários de Carga do TCC
# ============================================================

param (
    [string]$Url = "http://localhost:8080"
)

Write-Host "`n🚀 INICIANDO BATERIA DE TESTES DE CARGA DO TCC" -ForegroundColor Green
Write-Host "🎯 Servidor Alvo: $Url`n" -ForegroundColor Cyan

# 1. Cenário 1: 20 Usuários
Write-Host "▶️ Executando Cenário 1 (20 Usuários Simultâneos)..." -ForegroundColor Yellow
node tests/benchmark/run_benchmark.js --url $Url --concurrency 20 --rounds 5 --scenario "Cenário 1: 20 Usuários"

Start-Sleep -Seconds 2

# 2. Cenário 2: 50 Usuários
Write-Host "▶️ Executando Cenário 2 (50 Usuários Simultâneos)..." -ForegroundColor Yellow
node tests/benchmark/run_benchmark.js --url $Url --concurrency 50 --rounds 5 --scenario "Cenário 2: 50 Usuários"

Start-Sleep -Seconds 2

# 3. Cenário 3: 100 Usuários
Write-Host "▶️ Executando Cenário 3 (100 Usuários Simultâneos)..." -ForegroundColor Yellow
node tests/benchmark/run_benchmark.js --url $Url --concurrency 100 --rounds 5 --scenario "Cenário 3: 100 Usuários"

Write-Host "`n🏆 BATERIA DE TESTES CONCLUÍDA COM SUCESSO!" -ForegroundColor Green
Write-Host "📁 Os relatórios e planilhas foram salvos na pasta 'tests/benchmark/resultados/'`n"
