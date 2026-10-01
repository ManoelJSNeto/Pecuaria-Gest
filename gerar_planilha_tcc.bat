@echo off
echo ===================================================================
echo   PecuariaGest -- Gerador de Dados Consolidados do TCC (Python)
echo ===================================================================
echo.
echo Processando os 80 arquivos CSV de benchmark...
python "%~dp0tests\benchmark\processar_dados_tcc.py"
echo.
echo ===================================================================
echo   Arquivo gerado com sucesso em:
echo   relatorio\DADOS_CONSOLIDADOS_TCC.xlsx
echo ===================================================================
pause
