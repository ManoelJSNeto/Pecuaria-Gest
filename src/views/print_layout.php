<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Relatório Oficial') ?> — PecuáriaGest</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/bootstrap-icons.css">

  <style>
    :root {
      --primary: #1a4d2e;
      --primary-light: #2d7a4e;
      --primary-soft: #f0f7f2;
      --text: #1a261c;
      --text-muted: #5c6e60;
      --border: #d4e0d6;
      --border-light: #e8ede9;
      --surface: #ffffff;
      --bg: #f4f6f4;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      color: var(--text);
      background-color: var(--bg);
      font-size: 13px;
      line-height: 1.45;
      -webkit-font-smoothing: antialiased;
    }

    /* Barra de Ações Superior (apenas na tela) */
    .print-toolbar {
      position: sticky;
      top: 0;
      z-index: 1000;
      background: #ffffff;
      border-bottom: 1px solid var(--border);
      padding: 10px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .toolbar-title {
      font-weight: 700;
      font-size: 14px;
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .toolbar-actions {
      display: flex;
      gap: 10px;
    }

    .btn-action {
      font-family: inherit;
      font-size: 13px;
      font-weight: 600;
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      border: 1px solid transparent;
      text-decoration: none;
      transition: all 0.15s ease;
    }

    .btn-print {
      background: var(--primary);
      color: #ffffff;
    }

    .btn-print:hover {
      background: var(--primary-light);
    }

    .btn-back {
      background: #ffffff;
      color: var(--text);
      border-color: var(--border);
    }

    .btn-back:hover {
      background: var(--bg);
    }

    /* Folha A4 para Impressão */
    .sheet {
      background: #ffffff;
      max-width: 210mm;
      min-height: 297mm;
      margin: 20px auto;
      padding: 18mm 20mm;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
      position: relative;
    }

    /* Cabeçalho Institucional do Documento */
    .doc-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 2px solid var(--primary);
      padding-bottom: 14px;
      margin-bottom: 18px;
    }

    .doc-brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .doc-logo-img {
      width: 44px;
      height: 44px;
      border-radius: 9px;
      object-fit: contain;
      background: var(--primary);
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
      flex-shrink: 0;
    }

    .doc-logo-badge {
      width: 44px;
      height: 44px;
      background: var(--primary);
      color: #ffffff;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      font-weight: 800;
    }

    /* Personalizador de Impressão Granular */
    .toolbar-customizer {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
    }

    .customizer-label {
      font-size: 11px;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.4px;
      margin-right: 2px;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .customizer-toggle {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 12px;
      font-weight: 500;
      color: var(--text);
      background: #f4f6f4;
      padding: 4px 9px;
      border-radius: 5px;
      border: 1px solid var(--border);
      cursor: pointer;
      user-select: none;
      transition: all 0.15s ease;
    }

    .customizer-toggle:hover {
      background: #e8ede9;
    }

    .customizer-toggle input {
      accent-color: var(--primary);
      cursor: pointer;
      margin: 0;
    }

    .hide-print-section {
      display: none !important;
    }

    .doc-brand-title {
      font-size: 18px;
      font-weight: 800;
      color: var(--primary);
      letter-spacing: -0.5px;
      line-height: 1.1;
    }

    .doc-brand-sub {
      font-size: 11px;
      color: var(--text-muted);
      font-weight: 500;
    }

    .doc-meta {
      text-align: right;
      font-size: 11px;
      color: var(--text-muted);
      line-height: 1.4;
    }

    .doc-title-block {
      background: var(--primary-soft);
      border-left: 4px solid var(--primary);
      padding: 10px 14px;
      border-radius: 4px;
      margin-bottom: 18px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .doc-title-text {
      font-size: 16px;
      font-weight: 700;
      color: var(--primary);
    }

    .doc-badge {
      font-size: 11px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 4px;
      background: #ffffff;
      border: 1px solid var(--border);
      color: var(--primary);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Seções e Blocos */
    .section-title {
      font-size: 13px;
      font-weight: 700;
      color: var(--primary);
      border-bottom: 1px solid var(--border);
      padding-bottom: 4px;
      margin: 16px 0 10px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    /* Grids e Métricas */
    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 8px 14px;
      margin-bottom: 14px;
    }

    .info-box {
      background: #fafbfa;
      border: 1px solid var(--border-light);
      border-radius: 6px;
      padding: 8px 10px;
    }

    .info-label {
      font-size: 10px;
      font-weight: 600;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.4px;
      margin-bottom: 2px;
    }

    .info-val {
      font-size: 13px;
      font-weight: 700;
      color: var(--text);
    }

    /* Tabelas Formais */
    .doc-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
      margin-bottom: 14px;
    }

    .doc-table th {
      background: var(--primary-soft);
      color: var(--primary);
      font-weight: 700;
      text-align: left;
      padding: 6px 10px;
      border-top: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .doc-table td {
      padding: 6px 10px;
      border-bottom: 1px solid var(--border-light);
      color: var(--text);
    }

    .doc-table tr:nth-child(even) td {
      background: #fdfdfd;
    }

    .tabular-nums {
      font-variant-numeric: tabular-nums;
    }

    /* Assinatura / Rodapé */
    .doc-footer {
      margin-top: 30px;
      border-top: 1px dashed var(--border);
      padding-top: 16px;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      font-size: 10px;
      color: var(--text-muted);
    }

    .signature-box {
      text-align: center;
      width: 200px;
      border-top: 1px solid var(--text);
      padding-top: 4px;
      font-size: 11px;
      font-weight: 600;
      color: var(--text);
    }

    /* Regras Estritas de Impressão (@media print) */
    @media print {
      body {
        background: #ffffff !important;
      }

      .no-print, .print-toolbar {
        display: none !important;
      }

      .sheet {
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        max-width: 100% !important;
        min-height: auto !important;
      }

      @page {
        size: A4 portrait;
        margin: 12mm 14mm;
      }

      * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      .page-break {
        page-break-before: always;
      }

      table, tr, td, th, .info-box, .section-title {
        page-break-inside: avoid;
      }
    }
  </style>
</head>
<body>

  <!-- Barra de Ações na Tela -->
  <div class="print-toolbar no-print">
    <div class="toolbar-title">
      <img src="/favicon.svg" alt="PecuáriaGest" style="width: 24px; height: 24px; border-radius: 5px; flex-shrink: 0;">
      <span>Visualizador Oficial & Impressão</span>
    </div>

    <!-- Seletor Granular Dinâmico de Seções -->
    <div class="toolbar-customizer" id="toolbarCustomizer"></div>

    <div class="toolbar-actions">
      <button onclick="window.print()" class="btn-action btn-print">
        <i class="bi bi-printer-fill"></i> Imprimir / Salvar como PDF
      </button>
      <button onclick="window.close(); if(history.length > 1) history.back();" class="btn-action btn-back">
        <i class="bi bi-arrow-left"></i> Voltar
      </button>
    </div>
  </div>

  <!-- Folha A4 -->
  <div class="sheet">
    <!-- Cabeçalho Oficial -->
    <header class="doc-header">
      <div class="doc-brand">
        <img src="/favicon.svg" alt="Logo PecuáriaGest" class="doc-logo-img">
        <div>
          <div class="doc-brand-title">PecuáriaGest</div>
          <div class="doc-brand-sub">Sistema de Gestão Agropecuária & Rastreabilidade Zootécnica</div>
        </div>
      </div>
      <div class="doc-meta">
        <div><strong>Emissão:</strong> <?= date('d/m/Y H:i:s') ?></div>
        <div><strong>Operador:</strong> <?= htmlspecialchars($_SESSION['user']['nome'] ?? 'Administrador') ?></div>
        <div><strong>Ambiente:</strong> Produção / Fazenda Central</div>
      </div>
    </header>

    <!-- Conteúdo do Documento -->
    <main>
      <?= $content ?? '' ?>
    </main>

    <!-- Rodapé -->
    <footer class="doc-footer">
      <div>
        Documento oficial emitido eletronicamente via <strong>PecuáriaGest</strong>.<br>
        Registro com integridade auditada no banco de dados relacional.
      </div>
      <div class="signature-box">
        Responsável Técnico / Emissor
      </div>
    </footer>
  </div>

  <script>
    // Auto-descoberta de seções com data-printable-section para personalização granular
    document.addEventListener('DOMContentLoaded', () => {
      const sections = document.querySelectorAll('[data-printable-section]');
      const customizer = document.getElementById('toolbarCustomizer');
      if (sections.length > 0 && customizer) {
        customizer.innerHTML = '<span class="customizer-label"><i class="bi bi-sliders"></i> Exibir:</span>';
        sections.forEach((sec, idx) => {
          const name = sec.getAttribute('data-printable-section');
          const id = sec.id || ('printable-sec-' + idx);
          sec.id = id;

          const isDefaultHidden = sec.hasAttribute('data-default-hidden');
          if (isDefaultHidden) {
            sec.classList.add('hide-print-section');
          }

          const label = document.createElement('label');
          label.className = 'customizer-toggle';
          label.title = 'Marque ou desmarque para incluir/remover este bloco do PDF';
          label.innerHTML = `<input type="checkbox" ${isDefaultHidden ? '' : 'checked'} data-target="${id}"> <span>${name}</span>`;

          const cb = label.querySelector('input');
          cb.addEventListener('change', (e) => {
            sec.classList.toggle('hide-print-section', !e.target.checked);
          });
          customizer.appendChild(label);
        });
      }
    });

    // Se requisitado auto_print via query string, dispara imediatamente
    if (window.location.search.includes('auto_print=1')) {
      window.addEventListener('load', () => {
        setTimeout(() => window.print(), 300);
      });
    }
  </script>
</body>
</html>
