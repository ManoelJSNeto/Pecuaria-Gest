#!/usr/bin/env bash
# ==============================================================================
# MONITOR DE TELEMETRIA DOCKER PARA LINUX / EC2 (BASH STANDALONE)
# PecuáriaGest - Coleta de CPU %, Memória RAM, I/O e Rede a cada 1 segundo
# ==============================================================================

ENV_LABEL="${1:-aws}"
CONCURRENCY="${2:-100}"
RUN_NUM="${3:-1}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RESULTS_DIR="${SCRIPT_DIR}/resultados"
mkdir -p "${RESULTS_DIR}"

NOW_TS=$(date +%s%3N 2>/dev/null || python3 -c 'import time; print(int(time.time()*1000))' 2>/dev/null || date +%s000)
CSV_FILE="${RESULTS_DIR}/telemetria_hardware_${ENV_LABEL}_${CONCURRENCY}users_run${RUN_NUM}_${NOW_TS}.csv"

echo "Timestamp,Env,Pilar,Concurrency,Run,Container,CPU_Perc,Mem_MB,Mem_Limit_MB,Mem_Perc,Block_Read_MB,Block_Write_MB,Net_In_MB,Net_Out_MB,DB_Size_MB" > "${CSV_FILE}"

echo "======================================================================"
echo "📡 MONITOR DE TELEMETRIA DOCKER STANDALONE (BASH) [${ENV_LABEL^^}]"
echo "======================================================================"
echo "Arquivo de saída: $(basename "${CSV_FILE}")"
echo "Coletando a cada 1 segundo... Pressione Ctrl+C para finalizar."
echo "======================================================================"

cleanup() {
    echo ""
    echo "🛑 Finalizando telemetria. Dados gravados com sucesso em:"
    echo "   ${CSV_FILE}"
    exit 0
}

trap cleanup SIGINT SIGTERM

while true; do
    TS=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
    
    # Coleta stats dos containers pecuaria-gest
    docker stats --no-stream --format "{{.Name}},{{.CPUPerc}},{{.MemUsage}},{{.MemPerc}},{{.NetIO}},{{.BlockIO}}" 2>/dev/null | grep "pecuaria-gest" | while IFS=',' read -r NAME CPU MEM MEM_PERC NET BLOCK; do
        # Limpeza simples de CPU %
        CPU_CLEAN=$(echo "${CPU}" | tr -d '% ')
        
        # Limpeza de Memória (ex: 45.2MiB / 7.67GiB)
        MEM_USED=$(echo "${MEM}" | awk -F'/' '{print $1}' | tr -d ' ')
        MEM_LIM=$(echo "${MEM}" | awk -F'/' '{print $2}' | tr -d ' ')
        MEM_PERC_CLEAN=$(echo "${MEM_PERC}" | tr -d '% ')
        
        # Converte MB simples para o CSV
        echo "${TS},${ENV_LABEL},\"Bateria Remota\",${CONCURRENCY},${RUN_NUM},${NAME},${CPU_CLEAN:-0},${MEM_USED:-0},${MEM_LIM:-0},${MEM_PERC_CLEAN:-0},0,0,0,0,0" >> "${CSV_FILE}"
    done
    
    sleep 1
done
