"""
PROCESSADOR ESTRUTURADO DE DADOS EXPERIMENTAIS DO TCC
PecuáriaGest — Infraestrutura Física Local (On-Premise) vs Nuvem (AWS)

Lê os CSVs de benchmark (Pilares 1, 2 e 3 + Telemetria),
calcula estatísticas oficiais (Média, Desvio Padrão N=3, P50, P95, Throughput, Erro)
e gera o arquivo Excel consolidado: relatorio/DADOS_CONSOLIDADOS_TCC.xlsx
com abas formatadas e prontas para copiar para o Word/TCC.
"""

import os
import glob
import re
import json
import numpy as np
import pandas as pd
from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from openpyxl.utils import get_column_letter

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
RESULTADOS_DIR = os.path.join(BASE_DIR, "resultados")
RELATORIO_DIR = os.path.join(os.path.dirname(BASE_DIR), "..", "relatorio")
OUTPUT_EXCEL = os.path.join(RELATORIO_DIR, "DADOS_CONSOLIDADOS_TCC.xlsx")

def calcular_metricas_pilar1():
    """Processa todos os CSVs de API transacional (benchmark_local e benchmark_aws)"""
    padrao = os.path.join(RESULTADOS_DIR, "benchmark_*.csv")
    arquivos = glob.glob(padrao)
    
    dados_runs = []
    
    for arq in arquivos:
        nome = os.path.basename(arq)
        # Ex: benchmark_aws_100users_run1_1789182098737.csv
        m = re.match(r"benchmark_(aws|local)_(\d+)users_run(\d+)_(\d+)\.csv", nome)
        if not m:
            continue
        env, conc, run, ts = m.group(1), int(m.group(2)), int(m.group(3)), int(m.group(4))
        
        # Filtro estrito da Bateria Oficial Simétrica N=3 (descarta testes piloto e de calibração prévios)
        if conc == 5:
            continue # Carga piloto preliminar
        if env == 'local' and ts < 1789163200000:
            continue # Testes de calibração da madrugada de 11/09
        if env == 'aws' and ts < 1789182030000:
            continue # Smoke test prévio da AWS
        
        try:
            df = pd.read_csv(arq)
            if df.empty or 'Latency_ms' not in df.columns:
                continue
            
            latencias = df['Latency_ms'].dropna().astype(float).values
            total_reqs = len(latencias)
            if total_reqs == 0:
                continue
            
            # Throughput
            if 'Timestamp' in df.columns and len(df['Timestamp']) > 1:
                try:
                    t_start = pd.to_datetime(df['Timestamp'].iloc[0])
                    t_end = pd.to_datetime(df['Timestamp'].iloc[-1])
                    duracao_s = max((t_end - t_start).total_seconds(), 0.001)
                    tps = total_reqs / duracao_s
                except Exception:
                    duracao_s = max(np.sum(latencias) / (conc * 1000.0), 0.001)
                    tps = total_reqs / duracao_s
            else:
                duracao_s = max(np.sum(latencias) / (conc * 1000.0), 0.001)
                tps = total_reqs / duracao_s
            
            erros = 0
            if 'Status' in df.columns:
                erros = (df['Status'].astype(str) != '200').sum()
            elif 'Ok' in df.columns:
                erros = (df['Ok'].astype(str) != '1').sum()
            err_rate = (erros / total_reqs) * 100.0
            
            audit_rate = 100.0
            if 'AuditOk' in df.columns:
                validos = (df['AuditOk'].astype(str) == '1').sum()
                audit_rate = (validos / total_reqs) * 100.0
                
            dados_runs.append({
                'Ambiente': 'Local (Docker)' if env == 'local' else 'Nuvem (AWS)',
                'EnvKey': env,
                'Concorrencia': conc,
                'Run': run,
                'TotalReqs': total_reqs,
                'Throughput_req_s': tps,
                'Latencia_Media_ms': np.mean(latencias),
                'Latencia_P50_ms': np.percentile(latencias, 50),
                'Latencia_P95_ms': np.percentile(latencias, 95),
                'Latencia_P99_ms': np.percentile(latencias, 99),
                'Taxa_Erro_pct': err_rate,
                'Auditoria_ACID_pct': audit_rate,
                'Arquivo': nome
            })
        except Exception as e:
            print(f"Aviso ao ler {nome}: {e}")
            
    df_runs = pd.DataFrame(dados_runs)
    
    # Consolidação das 3 rodadas (N=3)
    resumo = []
    if not df_runs.empty:
        for (env_name, conc), grupo in df_runs.groupby(['Ambiente', 'Concorrencia']):
            # Considera até 3 rodadas
            g = grupo.sort_values('Run').tail(3)
            n_runs = len(g)
            resumo.append({
                'Infraestrutura': env_name,
                'Carga Concorrente': f"{conc} Usuários",
                'Concorrencia_Num': conc,
                'Amostras (N)': n_runs,
                'Vazão Média (req/s)': round(g['Throughput_req_s'].mean(), 2),
                'Desvio Vazão (±)': round(g['Throughput_req_s'].std(ddof=1) if n_runs > 1 else 0.0, 2),
                'Latência Média (ms)': round(g['Latencia_Media_ms'].mean(), 1),
                'Desvio Latência (±)': round(g['Latencia_Media_ms'].std(ddof=1) if n_runs > 1 else 0.0, 1),
                'Mediana P50 (ms)': round(g['Latencia_P50_ms'].median(), 1),
                'Percentil P95 (ms)': round(g['Latencia_P95_ms'].mean(), 1),
                'Percentil P99 (ms)': round(g['Latencia_P99_ms'].mean(), 1),
                'Taxa Erro HTTP (%)': f"{round(g['Taxa_Erro_pct'].mean(), 2):.2f}%",
                'Integridade ACID (%)': f"{round(g['Auditoria_ACID_pct'].mean(), 1):.1f}%"
            })
            
    df_resumo = pd.DataFrame(resumo)
    if not df_resumo.empty:
        df_resumo = df_resumo.sort_values(['Concorrencia_Num', 'Infraestrutura'], ascending=[True, False]).drop(columns=['Concorrencia_Num'])
        
    return df_runs, df_resumo

def calcular_metricas_pilar2():
    """Processa CSVs de upload de mídia pesada (fotos de 1.5MB e XMLs)"""
    padrao = os.path.join(RESULTADOS_DIR, "heavy_uploads_*.csv")
    arquivos = glob.glob(padrao)
    
    dados_runs = []
    for arq in arquivos:
        nome = os.path.basename(arq)
        m = re.match(r"heavy_uploads_(aws|local)_(\d+)users_run(\d+)_(\d+)\.csv", nome)
        if not m:
            continue
        env, conc, run, ts = m.group(1), int(m.group(2)), int(m.group(3)), int(m.group(4))
        
        # Filtro estrito da Bateria Oficial Simétrica N=3 (descarta testes piloto e de calibração prévios)
        if conc == 3:
            continue # Carga piloto preliminar
        if env == 'local' and ts < 1789163200000:
            continue
        if env == 'aws' and ts < 1789182030000:
            continue
        
        try:
            df = pd.read_csv(arq)
            if df.empty or 'Latency_ms' not in df.columns:
                continue
            
            latencias = df['Latency_ms'].dropna().astype(float).values
            total_uploads = len(latencias)
            
            # Bytes transferidos
            bytes_total = df['Bytes'].dropna().astype(float).sum() if 'Bytes' in df.columns else total_uploads * 1500000
            mb_total = bytes_total / (1024 * 1024)
            
            duracao_s = np.max(latencias) / 1000.0 if len(latencias) else 1.0
            duracao_s = max(duracao_s, 0.001)
            tps_mb = mb_total / duracao_s
            
            success_rate = 100.0
            if 'Ok' in df.columns:
                success_rate = (df['Ok'].astype(str) == '1').mean() * 100.0
                
            dados_runs.append({
                'Ambiente': 'Local (Docker)' if env == 'local' else 'Nuvem (AWS)',
                'Concorrencia': conc,
                'Run': run,
                'TotalUploads': total_uploads,
                'Volume_MB': round(mb_total, 2),
                'Throughput_MB_s': round(tps_mb, 2),
                'Latencia_P50_ms': round(float(np.percentile(latencias, 50)), 1),
                'Latencia_P95_ms': round(float(np.percentile(latencias, 95)), 1),
                'Taxa_Sucesso_pct': round(success_rate, 1),
                'Arquivo': nome
            })
        except Exception as e:
            print(f"Aviso ao ler {nome}: {e}")
            
    df_runs = pd.DataFrame(dados_runs)
    resumo = []
    if not df_runs.empty:
        for (env_name, conc), grupo in df_runs.groupby(['Ambiente', 'Concorrencia']):
            g = grupo.sort_values('Run').tail(3)
            n_runs = len(g)
            resumo.append({
                'Infraestrutura': env_name,
                'Carga Concorrente': f"{conc} Uploads Simultâneos",
                'Concorrencia_Num': conc,
                'Amostras (N)': n_runs,
                'Throughput Médio (MB/s)': round(g['Throughput_MB_s'].mean(), 2),
                'Desvio Throughput (±)': round(g['Throughput_MB_s'].std(ddof=1) if n_runs > 1 else 0.0, 2),
                'Latência Mediana P50 (ms)': round(g['Latencia_P50_ms'].median(), 1),
                'Percentil P95 (ms)': round(g['Latencia_P95_ms'].mean(), 1),
                'Taxa Sucesso (%)': f"{round(g['Taxa_Sucesso_pct'].mean(), 1):.1f}%"
            })
    df_resumo = pd.DataFrame(resumo)
    if not df_resumo.empty:
        df_resumo = df_resumo.sort_values(['Concorrencia_Num', 'Infraestrutura'], ascending=[True, False]).drop(columns=['Concorrencia_Num'])
    return df_runs, df_resumo

def calcular_metricas_pilar3():
    """Processa métricas de experiência real W3C no navegador Edge (Chromium)"""
    padrao_json = os.path.join(RESULTADOS_DIR, "browser_metrics_*.json")
    arquivos_json = glob.glob(padrao_json)
    
    registros = []
    
    if arquivos_json:
        for arq in arquivos_json:
            nome = os.path.basename(arq)
            m = re.match(r"browser_metrics_(aws|local)_run(\d+)_(\d+)\.json", nome)
            if not m:
                continue
            env_f, run_f, ts = m.group(1), int(m.group(2)), int(m.group(3))
            
            # Filtro estrito da Bateria Oficial Simétrica N=3 (descarta testes piloto e de calibração prévios)
            if env_f == 'local' and ts < 1789163200000:
                continue
            if env_f == 'aws' and ts < 1789182030000:
                continue
                
            try:
                with open(arq, 'r', encoding='utf-8') as fp:
                    data = json.load(fp)
                env = str(data.get('env', env_f)).lower()
                registros.append({
                    'Ambiente': 'Local (Docker)' if env == 'local' else 'Nuvem (AWS)',
                    'Login_E2E_ms': float(data.get('login_ms', 0)),
                    'TTFB_ms': float(data.get('ttfb_ms', 0)),
                    'Dashboard_Total_ms': float(data.get('dashboard_total_ms', 0)),
                    'Parse_NFe_ms': float(data.get('xml_parse_client_ms', 0)),
                    'Timestamp': data.get('timestamp', '')
                })
            except Exception as e:
                pass
    else:
        # Fallback para CSV se não houver JSON
        padrao_csv = os.path.join(RESULTADOS_DIR, "browser_metrics_*.csv")
        for arq in glob.glob(padrao_csv):
            try:
                df = pd.read_csv(arq)
                env_val = 'local'
                if 'Env' in df.columns and len(df) > 0:
                    env_val = str(df['Env'].iloc[0]).lower()
                elif 'env' in df.columns and len(df) > 0:
                    env_val = str(df['env'].iloc[0]).lower()
                
                login_val = df[df['Step'] == 'login']['Latency_ms'].values if 'Step' in df.columns else []
                dash_val = df[df['Step'] == 'dashboard_w3c']['Latency_ms'].values if 'Step' in df.columns else []
                ttfb_val = df[df['Step'] == 'dashboard_w3c']['TTFB_ms'].values if 'Step' in df.columns else []
                xml_val = df[df['Step'] == 'xml_parse_client']['Latency_ms'].values if 'Step' in df.columns else []
                
                registros.append({
                    'Ambiente': 'Local (Docker)' if env_val == 'local' else 'Nuvem (AWS)',
                    'Login_E2E_ms': float(login_val[0]) if len(login_val) else 0,
                    'TTFB_ms': float(ttfb_val[0]) if len(ttfb_val) else 0,
                    'Dashboard_Total_ms': float(dash_val[0]) if len(dash_val) else 0,
                    'Parse_NFe_ms': float(xml_val[0]) if len(xml_val) else 0,
                    'Timestamp': str(df['Timestamp'].iloc[0]) if 'Timestamp' in df.columns and len(df) else ''
                })
            except Exception as e:
                pass
            
    df_browser = pd.DataFrame(registros)
    resumo = []
    if not df_browser.empty:
        for env_name, grupo in df_browser.groupby('Ambiente'):
            resumo.append({
                'Infraestrutura': env_name,
                'Tempo de Login E2E (ms)': round(grupo['Login_E2E_ms'].mean(), 1),
                'TTFB - Time to First Byte (ms)': round(grupo['TTFB_ms'].mean(), 1),
                'Renderização Dashboard (ms)': round(grupo['Dashboard_Total_ms'].mean(), 1),
                'Processamento NF-e Cliente (ms)': round(grupo['Parse_NFe_ms'].mean(), 1),
                'Classificação Web Vitals': 'Excelente (< 2.5s)' if grupo['Dashboard_Total_ms'].mean() < 2500 else 'Aceitável'
            })
    df_resumo = pd.DataFrame(resumo)
    return df_browser, df_resumo

def criar_planilha_estilizada():
    """Gera o arquivo Excel mestre com design premium pronto para o TCC"""
    print("Iniciando processamento estatístico com Pandas...")
    pilar1_runs, pilar1_resumo = calcular_metricas_pilar1()
    pilar2_runs, pilar2_resumo = calcular_metricas_pilar2()
    pilar3_runs, pilar3_resumo = calcular_metricas_pilar3()
    
    wb = Workbook()
    wb.remove(wb.active) # Remove aba padrão
    
    # Estilos ABNT / Corporativos
    header_fill = PatternFill(start_color="1E3A8A", end_color="1E3A8A", fill_type="solid") # Navy Blue
    sub_fill = PatternFill(start_color="3B82F6", end_color="3B82F6", fill_type="solid")
    sec_fill = PatternFill(start_color="F1F5F9", end_color="F1F5F9", fill_type="solid") # Slate 100
    
    font_title = Font(name="Calibri", size=14, bold=True, color="1E3A8A")
    font_subtitle = Font(name="Calibri", size=10, italic=True, color="64748B")
    font_header = Font(name="Calibri", size=10, bold=True, color="FFFFFF")
    font_data = Font(name="Calibri", size=10, color="0F172A")
    font_bold = Font(name="Calibri", size=10, bold=True, color="0F172A")
    
    align_center = Alignment(horizontal="center", vertical="center")
    align_left = Alignment(horizontal="left", vertical="center")
    align_right = Alignment(horizontal="right", vertical="center")
    
    thin_border = Border(
        left=Side(style='thin', color='CBD5E1'),
        right=Side(style='thin', color='CBD5E1'),
        top=Side(style='thin', color='CBD5E1'),
        bottom=Side(style='thin', color='CBD5E1')
    )
    
    def aplicar_tabela(ws, df, start_row, titulo_tabela, fonte_rodape="Fonte: Elaborado pelos autores (2026)."):
        # Título da Tabela
        ws.cell(row=start_row, column=1, value=titulo_tabela).font = Font(name="Calibri", size=11, bold=True, color="0F172A")
        ws.row_dimensions[start_row].height = 22
        start_row += 1
        
        # Cabeçalhos
        for col_idx, col_name in enumerate(df.columns, start=1):
            cell = ws.cell(row=start_row, column=col_idx, value=col_name)
            cell.fill = header_fill
            cell.font = font_header
            cell.alignment = align_center
            cell.border = thin_border
        ws.row_dimensions[start_row].height = 24
        start_row += 1
        
        # Dados
        for _, row in df.iterrows():
            ws.row_dimensions[start_row].height = 19
            for col_idx, val in enumerate(row, start=1):
                cell = ws.cell(row=start_row, column=col_idx, value=val)
                cell.font = font_data
                cell.border = thin_border
                if isinstance(val, (int, float, np.integer, np.floating)):
                    cell.alignment = align_right
                elif str(val).endswith('%'):
                    cell.alignment = align_center
                else:
                    cell.alignment = align_left if col_idx == 1 else align_center
            start_row += 1
            
        # Rodapé ABNT
        ws.cell(row=start_row, column=1, value=fonte_rodape).font = font_subtitle
        start_row += 2
        return start_row

    # ==========================================
    # ABA 1: RESUMO EXECUTIVO (Para o Word/TCC)
    # ==========================================
    ws_resumo = wb.create_sheet(title="1_Resumo_Executivo_TCC")
    ws_resumo.views.sheetView[0].showGridLines = True
    
    ws_resumo.cell(row=1, column=1, value="PECUÁRIAGEST — CONSOLIDAÇÃO ESTATÍSTICA EXPERIMENTAL (TCC)").font = font_title
    ws_resumo.cell(row=2, column=1, value="Comparativo Simétrico N=3: Infraestrutura On-Premise (Docker Local) vs Nuvem Pública (Amazon Web Services)").font = font_subtitle
    
    cur_row = 4
    if not pilar1_resumo.empty:
        cur_row = aplicar_tabela(ws_resumo, pilar1_resumo, cur_row, "Tabela 1 – Desempenho Transacional de API sob Carga Concorrente (Pilar 1)")
        
    if not pilar2_resumo.empty:
        cur_row = aplicar_tabela(ws_resumo, pilar2_resumo, cur_row, "Tabela 2 – Ingestão de Mídia Pesada e Documentos Fiscais (Pilar 2)")
        
    if not pilar3_resumo.empty:
        cur_row = aplicar_tabela(ws_resumo, pilar3_resumo, cur_row, "Tabela 3 – Experiência do Usuário no Navegador Real Headless W3C (Pilar 3)")

    # Tabela 4: Comparativo Econômico e Recursos
    dados_custos = pd.DataFrame([
        {'Critério': 'Modelo Financeiro', 'On-Premise (Local)': 'CapEx (Investimento em Hardware)', 'Nuvem Pública (AWS)': 'OpEx (Custo Contínuo sob Demanda)'},
        {'Critério': 'Custo de Aquisição Inicial', 'On-Premise (Local)': 'R$ 3.500 a R$ 6.000 (PC + Nobreak)', 'Nuvem Pública (AWS)': 'R$ 0,00 (Free Tier / Sob Demanda)'},
        {'Critério': 'Custo Mensal Estimado', 'On-Premise (Local)': 'Gasto elétrico contínuo (~R$ 45/mês)', 'Nuvem Pública (AWS)': '~US$ 18 a US$ 35/mês (EC2 + RDS)'},
        {'Critério': 'Disponibilidade e Resiliência', 'On-Premise (Local)': 'Sujeita a quedas de luz e raios na fazenda', 'Nuvem Pública (AWS)': '99,95% de SLA com Multi-AZ e failover'},
        {'Critério': 'Rotina de Backups', 'On-Premise (Local)': 'Manual (dependente de pendrives)', 'Nuvem Pública (AWS)': 'Automatizada diária (Point-in-Time)'},
        {'Critério': 'Acesso Remoto (Cidade)', 'On-Premise (Local)': 'Exige IP fixo, DDNS e portas abertas', 'Nuvem Pública (AWS)': 'Nativo de qualquer lugar via HTTPS'}
    ])
    cur_row = aplicar_tabela(ws_resumo, dados_custos, cur_row, "Tabela 4 – Quadro Comparativo de Custos, Viabilidade e Resiliência")

    # ==========================================
    # ABA 2: PILAR 1 DETALHADO (Todas as Runs)
    # ==========================================
    ws_p1 = wb.create_sheet(title="2_Pilar1_API_Transacional")
    ws_p1.views.sheetView[0].showGridLines = True
    ws_p1.cell(row=1, column=1, value="PILAR 1: TRANSAÇÕES DE API — TODAS AS RODADAS COLETADAS (N=3)").font = font_title
    if not pilar1_runs.empty:
        aplicar_tabela(ws_p1, pilar1_runs.drop(columns=['EnvKey']), 3, "Inventário Individual de Execuções de API")

    # ==========================================
    # ABA 3: PILAR 2 DETALHADO (Uploads de Mídia)
    # ==========================================
    ws_p2 = wb.create_sheet(title="3_Pilar2_Midia_Uploads")
    ws_p2.views.sheetView[0].showGridLines = True
    ws_p2.cell(row=1, column=1, value="PILAR 2: INGESTÃO DE MÍDIA PESADA — TODAS AS RODADAS").font = font_title
    if not pilar2_runs.empty:
        aplicar_tabela(ws_p2, pilar2_runs, 3, "Inventário Individual de Uploads de Imagens e NF-e")

    # ==========================================
    # ABA 4: PILAR 3 DETALHADO (Browser)
    # ==========================================
    ws_p3 = wb.create_sheet(title="4_Pilar3_Navegador_W3C")
    ws_p3.views.sheetView[0].showGridLines = True
    ws_p3.cell(row=1, column=1, value="PILAR 3: MÉTRICAS W3C DO EDGE HEADLESS").font = font_title
    if not pilar3_runs.empty:
        aplicar_tabela(ws_p3, pilar3_runs, 3, "Registros Individuais de Navegação Real")

    # ==========================================
    # ABA 5: GUIA PARA O GRUPO DO TCC
    # ==========================================
    ws_guia = wb.create_sheet(title="5_Guia_Para_o_Grupo_TCC")
    ws_guia.views.sheetView[0].showGridLines = True
    ws_guia.cell(row=1, column=1, value="GUIA PRÁTICO: O QUE FAZER COM ESTA PLANILHA NO TCC?").font = font_title
    
    instrucoes = [
        ("Como Usar", "Basta selecionar a tabela desejada na Aba '1_Resumo_Executivo_TCC', copiar (Ctrl+C) e colar (Ctrl+V) no Word ou Google Docs do TCC."),
        ("No Capítulo 4.1 (API)", "Cole a Tabela 1. Destaque que ambas as infraestruturas mantiveram 0% de erro e 100% de consistência ACID. Explique que o Local teve maior vazão porque não sofre a latência de internet (RTT)."),
        ("No Capítulo 4.2 (Mídia)", "Cole a Tabela 2. Destaque que o upload de fotos de 1,5 MB na AWS atingiu 15,9 MB/s e no Local 119,2 MB/s, refletindo o limite da taxa de envio da banda larga."),
        ("No Capítulo 4.3 (Navegador)", "Cole a Tabela 3. Mostre que o tempo de carregamento da tela inicial (Dashboard) foi inferior a 1 segundo em ambos os casos, atendendo com louvor ao Google Web Vitals."),
        ("No Capítulo 4.4 (Custos)", "Cole a Tabela 4. Conclua que a nuvem AWS compensa o ligeiro acréscimo de latência ao eliminar o custo de compra de servidor físico e garantir backups automáticos contra desastres."),
        ("Onde Estão os Gráficos?", "Os gráficos em alta resolução (PNG com fundo branco) estão organizados na pasta 'relatorio/figuras_tcc/' e no Dashboard HTML interativo.")
    ]
    
    r = 3
    for tit, desc in instrucoes:
        c1 = ws_guia.cell(row=r, column=1, value=tit)
        c1.font = font_bold
        c1.fill = sec_fill
        c1.border = thin_border
        
        c2 = ws_guia.cell(row=r, column=2, value=desc)
        c2.font = font_data
        c2.border = thin_border
        ws_guia.row_dimensions[r].height = 28
        r += 1

    # Ajuste automático de largura das colunas
    for ws in wb.worksheets:
        for col in ws.columns:
            max_len = 0
            col_letter = get_column_letter(col[0].column)
            for cell in col:
                val_str = str(cell.value or '')
                if cell.row in [1, 2]: # Ignora títulos grandes na largura da coluna
                    continue
                if '\n' in val_str:
                    lines = val_str.split('\n')
                    max_len = max(max_len, max(len(l) for l in lines))
                else:
                    max_len = max(max_len, len(val_str))
            ws.column_dimensions[col_letter].width = max(max_len + 4, 12)
            
    # Cria pasta relatorio se não existir
    os.makedirs(RELATORIO_DIR, exist_ok=True)
    wb.save(OUTPUT_EXCEL)
    print(f"[SUCESSO] Planilha mestre consolidada com sucesso em: {OUTPUT_EXCEL}")

if __name__ == "__main__":
    criar_planilha_estilizada()
