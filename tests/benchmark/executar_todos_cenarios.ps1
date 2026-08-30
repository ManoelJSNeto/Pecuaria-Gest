param (
    [string]$Url = "http://localhost:8080"
)

Write-Host "INICIANDO BATERIA DE TESTES DE CARGA DO TCC" -ForegroundColor Green
Write-Host "Servidor Alvo: $Url" -ForegroundColor Cyan

# 1. Cenário 1: 20 Usuários
Write-Host "Executando Cenario 1 (20 Usuarios Simultaneos)..." -ForegroundColor Yellow
node tests/benchmark/run_benchmark.js --url $Url --concurrency 20 --rounds 5 --scenario "Cenario 1: 20 Usuarios (AWS RDS)"

Start-Sleep -Seconds 2

# 2. Cenário 2: 50 Usuários
Write-Host "Executando Cenario 2 (50 Usuarios Simultaneos)..." -ForegroundColor Yellow
node tests/benchmark/run_benchmark.js --url $Url --concurrency 50 --rounds 5 --scenario "Cenario 2: 50 Usuarios (AWS RDS)"

Start-Sleep -Seconds 2

# 3. Cenário 3: 100 Usuários
Write-Host "Executando Cenario 3 (100 Usuarios Simultaneos)..." -ForegroundColor Yellow
node tests/benchmark/run_benchmark.js --url $Url --concurrency 100 --rounds 5 --scenario "Cenario 3: 100 Usuarios (AWS RDS)"

Write-Host "BATERIA DE TESTES CONCLUIDA COM SUCESSO!" -ForegroundColor Green
Write-Host "Os relatorios e planilhas foram salvos na pasta 'tests/benchmark/resultados/'"
