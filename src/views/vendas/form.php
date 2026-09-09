<?php
if (!isset($db)) { $db = getDb(); }

$isEdit = isset($venda) && !empty($venda['id']);
$vendaId = $isEdit ? (int)$venda['id'] : 0;
$actionUrl = $isEdit ? "/vendas/{$vendaId}/atualizar" : "/vendas/salvar";
$animaisVendaIds = $animaisVendaIds ?? [];
$preAnimalId = (int)($_GET['animal_id'] ?? 0);

if (!isset($animaisDisponiveis)) {
    // Animais ativos e vivos disponíveis para venda (ou já vinculados a esta venda se em edição)
    if ($isEdit) {
        $animaisStmt = $db->prepare("
            SELECT a.id, a.brinco, a.nome, a.sexo, a.raca, a.pasto_id, a.venda_id, p.nome as pasto_nome,
                   (SELECT peso FROM pesagens WHERE animal_id = a.id ORDER BY data DESC, id DESC LIMIT 1) as ultimo_peso,
                   a.peso_inicial, a.peso_venda
            FROM animais a
            LEFT JOIN pastagens p ON a.pasto_id = p.id
            WHERE (a.status NOT IN ('vendido', 'morto')) OR (a.venda_id = ?)
            ORDER BY p.nome NULLS LAST, a.brinco ASC
        ");
        $animaisStmt->execute([$vendaId]);
        $animaisDisponiveis = $animaisStmt->fetchAll();
    } else {
        $animaisStmt = $db->query("
            SELECT a.id, a.brinco, a.nome, a.sexo, a.raca, a.pasto_id, NULL as venda_id, p.nome as pasto_nome,
                   (SELECT peso FROM pesagens WHERE animal_id = a.id ORDER BY data DESC, id DESC LIMIT 1) as ultimo_peso,
                   a.peso_inicial, NULL as peso_venda
            FROM animais a
            LEFT JOIN pastagens p ON a.pasto_id = p.id
            WHERE a.status NOT IN ('vendido', 'morto')
            ORDER BY p.nome NULLS LAST, a.brinco ASC
        ");
        $animaisDisponiveis = $animaisStmt->fetchAll();
    }
}

if (!isset($pastos)) {
    $pastos = $db->query("SELECT id, nome FROM pastagens WHERE status='ativa' ORDER BY nome")->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <a href="/vendas" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar para Vendas
  </a>
  <?php if ($isEdit): ?>
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-light text-primary border px-2 py-1">
        <i class="bi bi-pencil-square me-1"></i> Modo Edição • Venda #<?= $vendaId ?>
      </span>
      <a href="/vendas/<?= $vendaId ?>/pdf" target="_blank" class="btn btn-sm btn-outline-success">
        <i class="bi bi-file-earmark-pdf-fill me-1"></i> Comprovante PDF
      </a>
    </div>
  <?php endif; ?>
</div>

<form method="POST" action="<?= $actionUrl ?>" enctype="multipart/form-data" id="formVenda">
  <?= csrf_field() ?>

  <div class="row g-3">
    <!-- Coluna Principal: Formulário e Seleção de Gado -->
    <div class="col-lg-8">

      <?php if ($isEdit && !empty($venda['arquivo_xml'])): ?>
        <div class="p-3 mb-3 bg-light rounded border d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-code fs-4 text-success"></i>
            <div>
              <strong class="d-block text-dark">Arquivo XML da NF-e Anexado</strong>
              <small class="text-muted">Nota fiscal de abate/saída já salva no sistema.</small>
            </div>
          </div>
          <a href="<?= e($venda['arquivo_xml']) ?>" download class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i> Baixar XML Atual
          </a>
        </div>
      <?php endif; ?>

      <!-- Zona de Importação Inteligente de XML da NF-e -->
      <div class="xml-import-zone" id="dropZoneXmlVenda" onclick="document.getElementById('inputXmlVenda').click()">
        <input type="file" name="arquivo_xml" id="inputXmlVenda" accept=".xml,text/xml" style="display: none;" onchange="handleXmlSelectVenda(this)">
        <i class="bi bi-file-earmark-arrow-up xml-import-icon"></i>
        <h6 class="fw-bold mb-1 text-dark"><?= $isEdit ? 'Substituir / Reimportar XML da NF-e' : 'Importar XML da Nota Fiscal de Venda / Abate' ?></h6>
        <p class="small text-muted mb-0">
          Selecione o arquivo <strong>.xml</strong> emitido pelo frigorífico ou comprador para conferência e preenchimento automático.
        </p>
        <div id="xmlFeedbackVenda" style="display: none;" class="xml-badge-success justify-content-center mt-2">
          <i class="bi bi-check-circle-fill text-success fs-6"></i>
          <span id="xmlFeedbackVendaText">XML lido e validado com sucesso!</span>
        </div>
      </div>

      <!-- Painel Interativo de Pré-visualização & Edição de Itens da NF-e de Venda -->
      <div id="painelItensXmlVenda" style="display: none;" class="card mb-3 border-success shadow-sm">
        <div class="card-header bg-success-subtle text-success-emphasis d-flex justify-content-between align-items-center py-2">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-spreadsheet-fill text-success fs-5"></i>
            <div>
              <strong id="xmlVendaCabecalho">Nota Fiscal de Saída / Abate Carregada</strong>
              <div class="small text-muted" id="xmlVendaSubcabecalho">Conferência e edição dos itens discriminados na NF-e</div>
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-danger" onclick="limparXmlVendaImportado()" title="Descartar importação">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>

        <div class="card-body p-3">
          <!-- Observações / infCpl -->
          <div id="xmlVendaInfCplAlert" style="display: none;" class="alert alert-info py-2 px-3 mb-3 small d-flex align-items-start gap-2">
            <i class="bi bi-info-circle-fill mt-1 fs-6"></i>
            <div class="flex-grow-1">
              <strong>Observações / Inf. Complementares da NF-e:</strong>
              <span id="xmlVendaInfCplText" class="d-block mt-1 font-monospace"></span>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div class="small fw-bold text-dark">
              <i class="bi bi-card-checklist text-primary me-1"></i> Itens Faturados pelo Frigorífico / Comprador
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-light border" onclick="selecionarTodosItensVendaXml(true)">Marcar Todos</button>
              <button type="button" class="btn btn-sm btn-light border" onclick="selecionarTodosItensVendaXml(false)">Desmarcar</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="adicionarItemManualVendaXml()">
                <i class="bi bi-plus-lg me-1"></i> Adicionar Item
              </button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-2" id="tabelaItensXmlVenda">
              <thead class="table-light small">
                <tr>
                  <th style="width: 35px;" class="text-center" title="Incluir no faturamento da venda">Inc.</th>
                  <th style="width: 85px;">Cód.</th>
                  <th>Descrição do Produto / Lote</th>
                  <th style="width: 65px;" class="text-center">Un.</th>
                  <th style="width: 95px;" class="text-end">Qtd</th>
                  <th style="width: 125px;" class="text-end">Valor Unit. (R$)</th>
                  <th style="width: 130px;" class="text-end">Total Item (R$)</th>
                  <th style="width: 40px;" class="text-center"></th>
                </tr>
              </thead>
              <tbody id="tbodyItensXmlVenda">
                <!-- Linhas preenchidas via JavaScript -->
              </tbody>
              <tfoot class="table-light fw-bold small">
                <tr>
                  <td colspan="4" class="text-end">Totais Selecionados:</td>
                  <td class="text-end tabular-nums" id="xmlVendaSomaQtd">0</td>
                  <td></td>
                  <td class="text-end tabular-nums text-success" id="xmlVendaSomaValor">R$ 0,00</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="d-flex justify-content-between align-items-center pt-2 border-top flex-wrap gap-2">
            <small class="text-muted" style="font-size:0.75rem;">
              <i class="bi bi-shield-check text-success me-1"></i>
              Você pode ajustar qualquer valor unitário, arroba ou quantidade. O total de faturamento é sincronizado ao formulário.
            </small>
            <button type="button" class="btn btn-sm btn-success text-nowrap" onclick="aplicarItensXmlAoFormularioVenda()">
              <i class="bi bi-check2-all me-1"></i> Aplicar Valores ao Formulário
            </button>
          </div>
        </div>
      </div>

      <!-- Seção 1: Chaves Mestras de Saída -->
      <div class="form-section">
        <div class="form-section-title">
          <i class="bi bi-shield-check text-success"></i> Chaves Mestras de Saída & Documentação
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Número / Série da GTA de Saída *</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-file-earmark-medical text-success"></i></span>
              <input type="text" name="numero_gta" id="venda_gta" class="form-control" placeholder="Ex: 987654/2026" required autocomplete="off" value="<?= e($venda['numero_gta'] ?? '') ?>">
            </div>
            <small class="text-muted" style="font-size:0.72rem;">Guia de Trânsito Animal emitida para o embarque ou abate.</small>
          </div>

          <div class="col-md-6">
            <label class="form-label">Chave de Acesso da NF-e (44 dígitos)</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-receipt"></i></span>
              <input type="text" name="chave_nfe" id="venda_chave_nfe" class="form-control tabular-nums text-uppercase" placeholder="35260900000000000000550010000000002000000000" maxlength="44" autocomplete="off" value="<?= e($venda['chave_nfe'] ?? '') ?>">
            </div>
            <small class="text-muted" style="font-size:0.72rem;">Preenchida automaticamente ao anexar o XML acima.</small>
          </div>

          <div class="col-md-8">
            <label class="form-label">Comprador / Frigorífico de Destino *</label>
            <input type="text" name="comprador_destino" id="venda_comprador" class="form-control" placeholder="Ex: Frigorífico JBS, Minerva, Fazenda São José..." required autocomplete="off" value="<?= e($venda['comprador_destino'] ?? '') ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Data do Embarque / Saída *</label>
            <input type="date" name="data_venda" id="venda_data" class="form-control" value="<?= e($venda['data_venda'] ?? date('Y-m-d')) ?>" required>
          </div>
        </div>
      </div>

      <!-- Seção 2: Negociação Comercial & Balança -->
      <div class="form-section">
        <div class="form-section-title">
          <i class="bi bi-tag-fill text-primary"></i> Negociação Comercial & Balança
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Forma de Precificação</label>
            <select name="tipo_precificacao" id="tipo_precificacao" class="form-select" onchange="atualizarCalculoVenda()">
              <?php $tp = $venda['tipo_precificacao'] ?? 'arroba'; ?>
              <option value="arroba" <?= $tp === 'arroba' ? 'selected' : '' ?>>Por Arroba (R$/@) — Frigorífico</option>
              <option value="peso_vivo_kg" <?= $tp === 'peso_vivo_kg' ? 'selected' : '' ?>>Por Quilo Vivo (R$/kg)</option>
              <option value="cabeca" <?= $tp === 'cabeca' ? 'selected' : '' ?>>Por Cabeça (R$/cab)</option>
              <option value="total_fixo" <?= $tp === 'total_fixo' ? 'selected' : '' ?>>Valor Fechado do Lote</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label" id="label_preco_unit">Preço por Arroba (R$/@) *</label>
            <div class="input-group">
              <span class="input-group-text">R$</span>
              <input type="number" step="0.01" name="preco_unitario" id="venda_preco_unit" class="form-control tabular-nums" placeholder="Ex: 245.00" value="<?= !empty($venda['preco_unitario']) ? (float)$venda['preco_unitario'] : '' ?>" oninput="atualizarCalculoVenda()">
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Peso Total de Embarque (kg)</label>
            <div class="input-group">
              <input type="number" step="0.1" name="peso_total_kg" id="venda_peso_total" class="form-control tabular-nums" placeholder="0.0" value="<?= !empty($venda['peso_total_kg']) ? (float)$venda['peso_total_kg'] : '' ?>" oninput="atualizarCalculoVenda()">
              <span class="input-group-text">kg</span>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Valor Total da Venda (R$) *</label>
            <div class="input-group">
              <span class="input-group-text fw-bold">R$</span>
              <input type="number" step="0.01" name="valor_total" id="venda_valor_total" class="form-control tabular-nums fw-bold fs-5" style="color: var(--earth-green-900);" placeholder="0,00" required value="<?= !empty($venda['valor_total']) ? number_format((float)$venda['valor_total'], 2, '.', '') : '' ?>" oninput="atualizarCalculoVenda()">
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Descrição / Identificação da Saída</label>
            <input type="text" name="descricao" id="venda_descricao" class="form-control" placeholder="Ex: Embarque de 20 bois gordos para abate" autocomplete="off" value="<?= e($venda['descricao'] ?? '') ?>">
          </div>
        </div>
      </div>

      <!-- Seção 3: Seleção dos Animais do Embarque -->
      <div class="form-section">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div class="form-section-title mb-0 border-0 p-0">
            <i class="bi bi-check2-square text-primary"></i> Selecionar Animais que Estão Saindo (<?= count($animaisDisponiveis) ?> disponíveis)
          </div>
          <div class="d-flex align-items-center gap-2">
            <select id="filtroPastoVenda" class="form-select form-select-sm" style="max-width: 170px;" onchange="filtrarTabelaAnimais(this.value)">
              <option value="">Todos os Pastos</option>
              <?php foreach ($pastos as $p): ?>
                <option value="<?= $p['id'] ?>"><?= e($p['nome']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-secondary btn-sm" onclick="marcarTodosVisiveis(true)">Marcar Todos</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="marcarTodosVisiveis(false)">Desmarcar</button>
          </div>
        </div>

        <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
          <table class="table table-hover mb-0" id="tabelaAnimaisVenda">
            <thead class="sticky-top bg-light" style="z-index: 2;">
              <tr>
                <th style="width: 40px;" class="text-center">#</th>
                <th>Brinco</th>
                <th>Raça / Sexo</th>
                <th>Pasto Atual</th>
                <th class="text-end">Último Peso</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($animaisDisponiveis as $a): ?>
                <?php 
                  $pKg = (float)($a['peso_venda'] ?: ($a['ultimo_peso'] ?: ($a['peso_inicial'] ?: 0)));
                  $isChecked = in_array($a['id'], $animaisVendaIds) || ($preAnimalId > 0 && $a['id'] == $preAnimalId);
                ?>
                <tr class="linha-animal <?= $isChecked ? 'table-success-subtle' : '' ?>" data-pasto="<?= $a['pasto_id'] ?: 0 ?>" data-peso="<?= $pKg ?>">
                  <td class="text-center">
                    <input type="checkbox" name="animais_ids[]" value="<?= $a['id'] ?>" class="form-check-input check-animal-venda" <?= $isChecked ? 'checked' : '' ?> onchange="atualizarSelecaoAnimais()">
                  </td>
                  <td>
                    <strong class="text-primary"><?= e($a['brinco']) ?></strong>
                    <?php if ($a['nome']): ?><small class="text-muted ms-1">(<?= e($a['nome']) ?>)</small><?php endif; ?>
                    <?php if ($isEdit && in_array($a['id'], $animaisVendaIds)): ?>
                      <span class="badge bg-success-subtle text-success border ms-1" style="font-size:0.65rem;">Vinculado à Venda</span>
                    <?php endif; ?>
                  </td>
                  <td class="small text-secondary"><?= e($a['raca'] ?: 'Nelore') ?> • <?= $a['sexo'] === 'M' ? 'Macho' : 'Fêmea' ?></td>
                  <td>
                    <?= $a['pasto_nome'] ? '<span class="badge-status ativo">' . e($a['pasto_nome']) . '</span>' : '<span class="badge-status neutro">Sem pasto</span>' ?>
                  </td>
                  <td class="text-end tabular-nums fw-600">
                    <?= $pKg > 0 ? number_format($pKg, 1, ',', '.') . ' kg' : '—' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <small class="text-muted d-block mt-2" style="font-size:0.75rem;">
          <i class="bi bi-info-circle me-1"></i> Os animais selecionados terão o status atualizado para <strong>Vendido</strong> e serão desvinculados dos pastos automaticamente.
        </small>
      </div>

    </div>

    <!-- Coluna Lateral: Resumo do Embarque & Confirmação -->
    <div class="col-lg-4">

      <!-- Card de Resumo Financeiro da Venda -->
      <div class="card mb-3">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-cash-stack text-success me-1"></i>Resumo do Embarque</h6>
        </div>
        <div class="card-body p-3">
          <div class="summary-line">
            <span class="summary-label">Animais Marcados:</span>
            <span class="summary-value tabular-nums" id="resumo_qtd_selecionada" style="color: var(--earth-green-800);">0 cab</span>
          </div>
          <div class="summary-line">
            <span class="summary-label">Volume Total:</span>
            <span class="summary-value tabular-nums" id="resumo_arr_total">0,0 @</span>
          </div>
          <div class="summary-line">
            <span class="summary-label">Média por Cabeça:</span>
            <span class="summary-value tabular-nums" id="resumo_media_cab">R$ 0,00</span>
          </div>

          <div class="p-3 mt-3 rounded border" style="background: var(--earth-green-50);">
            <small class="text-muted d-block text-uppercase fw-bold" style="font-size:0.68rem;">Faturamento Previsto</small>
            <div class="fs-4 fw-bold tabular-nums" id="resumo_total_destaque" style="color: var(--earth-green-900);">
              R$ 0,00
            </div>
          </div>
        </div>
      </div>

      <!-- Card de Confirmação e Ação -->
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0"><?= $isEdit ? 'Salvar Alterações' : 'Finalizar Baixa de Venda' ?></h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check2-circle me-1"></i> <?= $isEdit ? 'Salvar Alterações na Venda' : 'Confirmar Venda e Dar Baixa' ?>
            </button>
            <a href="/vendas" class="btn btn-secondary">Cancelar</a>
          </div>
        </div>
      </div>

    </div>
  </div>
</form>

<script>
// Estado em memória dos itens do XML de Venda
let itensXmlVendaCarregados = [];

// Leitura e Parsing Inteligente de XML de Venda / Abate
function handleXmlSelectVenda(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];

  const reader = new FileReader();
  reader.onload = function(e) {
    try {
      const xmlText = e.target.result;
      const parser = new DOMParser();
      const xmlDoc = parser.parseFromString(xmlText, "text/xml");

      if (xmlDoc.getElementsByTagName("parsererror").length > 0) {
        throw new Error("Arquivo XML mal formatado.");
      }

      // 1. Chave da NF-e
      let chave = '';
      const infNFe = xmlDoc.getElementsByTagName('infNFe')[0];
      if (infNFe && infNFe.getAttribute('Id')) {
        chave = infNFe.getAttribute('Id').replace(/\D/g, '');
      }
      if (!chave) {
        const chNFe = xmlDoc.getElementsByTagName('chNFe')[0];
        if (chNFe) chave = chNFe.textContent.trim();
      }
      if (chave) document.getElementById('venda_chave_nfe').value = chave;

      // 2. Número da NF-e e Série
      let numNfe = '';
      let serieNfe = '';
      const nNF = xmlDoc.getElementsByTagName('nNF')[0];
      if (nNF) numNfe = nNF.textContent.trim();
      const serie = xmlDoc.getElementsByTagName('serie')[0];
      if (serie) serieNfe = serie.textContent.trim();

      // 3. Data de Emissão
      let dataEmi = '';
      const dhEmi = xmlDoc.getElementsByTagName('dhEmi')[0] || xmlDoc.getElementsByTagName('dEmi')[0];
      if (dhEmi && dhEmi.textContent) {
        dataEmi = dhEmi.textContent.substr(0, 10);
        document.getElementById('venda_data').value = dataEmi;
      }

      // 4. Comprador / Frigorífico (destinatário ou emitente no caso de emissão do próprio produtor)
      let compradorNome = '';
      const dest = xmlDoc.getElementsByTagName('dest')[0];
      if (dest) {
        const xNome = dest.getElementsByTagName('xNome')[0];
        if (xNome && xNome.textContent) compradorNome = xNome.textContent.trim();
      }
      if (!compradorNome) {
        const emit = xmlDoc.getElementsByTagName('emit')[0];
        if (emit) {
          const xNome = emit.getElementsByTagName('xNome')[0];
          if (xNome && xNome.textContent) compradorNome = xNome.textContent.trim();
        }
      }
      if (compradorNome) {
        document.getElementById('venda_comprador').value = compradorNome;
      }

      // 5. Peso Total da Balança
      const pesoB = xmlDoc.getElementsByTagName('pesoB')[0] || xmlDoc.getElementsByTagName('pesoL')[0];
      if (pesoB && pesoB.textContent) {
        const pesoKg = parseFloat(pesoB.textContent) || 0;
        if (pesoKg > 0) {
          const inputPeso = document.getElementById('venda_peso_total');
          inputPeso.value = pesoKg.toFixed(1);
          inputPeso.dataset.manual = 'true';
        }
      }

      // 6. Observações / infCpl (ex: GTA de Saída)
      const infCpl = xmlDoc.getElementsByTagName('infCpl')[0];
      if (infCpl && infCpl.textContent) {
        const infCplText = infCpl.textContent.trim();
        document.getElementById('xmlVendaInfCplText').textContent = infCplText;
        document.getElementById('xmlVendaInfCplAlert').style.display = 'flex';

        // Auto-detecção de GTA no texto
        const matchGta = infCplText.match(/gta\s*[:#ºn\.\-]?\s*([0-9a-zA-Z\/\.\-]+)/i);
        if (matchGta && matchGta[1] && !document.getElementById('venda_gta').value) {
          document.getElementById('venda_gta').value = matchGta[1].replace(/[\.\,]+$/, '');
        }
      } else {
        document.getElementById('xmlVendaInfCplAlert').style.display = 'none';
      }

      // 7. Extração de Itens / Produtos da NF-e
      itensXmlVendaCarregados = [];
      const dets = xmlDoc.getElementsByTagName('det');
      for (let i = 0; i < dets.length; i++) {
        const det = dets[i];
        const cProd = det.getElementsByTagName('cProd')[0]?.textContent?.trim() || `ITEM-${i+1}`;
        const xProd = det.getElementsByTagName('xProd')[0]?.textContent?.trim() || 'BOVINO PARA ABATE';
        const uCom = (det.getElementsByTagName('uCom')[0]?.textContent?.trim() || 'CAB').toUpperCase();
        const qCom = parseFloat(det.getElementsByTagName('qCom')[0]?.textContent) || 1;
        const vUnCom = parseFloat(det.getElementsByTagName('vUnCom')[0]?.textContent) || 0;
        const vProd = parseFloat(det.getElementsByTagName('vProd')[0]?.textContent) || (qCom * vUnCom);

        const xProdLower = xProd.toLowerCase();
        const naoEhGado = xProdLower.includes('frete') || xProdLower.includes('transporte') ||
                          xProdLower.includes('servico') || xProdLower.includes('pedagio');

        itensXmlVendaCarregados.push({
          incluir: !naoEhGado,
          codigo: cProd,
          descricao: xProd,
          unidade: uCom,
          quantidade: qCom,
          valorUnitario: vUnCom,
          valorTotal: vProd
        });
      }

      document.getElementById('xmlVendaCabecalho').textContent = `NF-e Saída/Abate nº ${numNfe || 'S/N'} Série ${serieNfe || '1'}`;
      document.getElementById('xmlVendaSubcabecalho').textContent = `Comprador/Frigorífico: ${compradorNome || 'Não informado'} • Emissão: ${dataEmi || 'Hoje'}`;

      renderTabelaItensXmlVenda();
      aplicarItensXmlAoFormularioVenda();

      // Feedback visual
      const feedback = document.getElementById('xmlFeedbackVenda');
      const text = document.getElementById('xmlFeedbackVendaText');
      feedback.style.display = 'flex';
      text.textContent = `XML carregado com sucesso: ${file.name} (${itensXmlVendaCarregados.length} item(ns) encontrado(s))`;
      document.getElementById('painelItensXmlVenda').style.display = 'block';

      // Sugere descrição se estiver vazia
      const descInput = document.getElementById('venda_descricao');
      if (!descInput.value && itensXmlVendaCarregados.length > 0) {
        const primeiro = itensXmlVendaCarregados.find(it => it.incluir) || itensXmlVendaCarregados[0];
        descInput.value = `Venda NF ${numNfe} - ${primeiro.descricao.substr(0, 35)}`;
      }

    } catch (err) {
      alert('Erro ao processar o arquivo XML. Verifique se é uma NF-e válida.');
      console.error(err);
    }
  };
  reader.readAsText(file);
}

// Renderiza itens de venda dinamicamente
function renderTabelaItensXmlVenda() {
  const tbody = document.getElementById('tbodyItensXmlVenda');
  tbody.innerHTML = '';

  let somaQtd = 0;
  let somaValor = 0;

  itensXmlVendaCarregados.forEach((item, idx) => {
    if (item.incluir) {
      somaQtd += item.quantidade;
      somaValor += (item.quantidade * item.valorUnitario);
    }

    const tr = document.createElement('tr');
    if (!item.incluir) tr.classList.add('table-secondary', 'opacity-75');

    tr.innerHTML = `
      <td class="text-center">
        <input type="checkbox" class="form-check-input" ${item.incluir ? 'checked' : ''} onchange="toggleItemVendaXml(${idx}, this.checked)">
      </td>
      <td class="text-muted small tabular-nums">${escapeHtml(item.codigo)}</td>
      <td>
        <input type="text" class="form-control form-control-sm" value="${escapeHtml(item.descricao)}" onchange="editarItemVendaXml(${idx}, 'descricao', this.value)">
      </td>
      <td class="text-center">
        <span class="badge bg-light text-dark border">${escapeHtml(item.unidade)}</span>
      </td>
      <td>
        <input type="number" step="0.1" min="0" class="form-control form-control-sm text-end tabular-nums" value="${item.quantidade}" oninput="editarItemVendaXml(${idx}, 'quantidade', parseFloat(this.value) || 0)">
      </td>
      <td>
        <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end tabular-nums" value="${item.valorUnitario.toFixed(2)}" oninput="editarItemVendaXml(${idx}, 'valorUnitario', parseFloat(this.value) || 0)">
      </td>
      <td class="text-end tabular-nums fw-600 ${item.incluir ? 'text-success' : 'text-muted'}">
        R$ ${(item.quantidade * item.valorUnitario).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removerItemVendaXml(${idx})" title="Excluir item">
          <i class="bi bi-trash"></i>
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  });

  document.getElementById('xmlVendaSomaQtd').textContent = somaQtd.toLocaleString('pt-BR', { maximumFractionDigits: 1 });
  document.getElementById('xmlVendaSomaValor').textContent = 'R$ ' + somaValor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function toggleItemVendaXml(idx, checked) {
  if (itensXmlVendaCarregados[idx]) {
    itensXmlVendaCarregados[idx].incluir = checked;
    renderTabelaItensXmlVenda();
    aplicarItensXmlAoFormularioVenda();
  }
}

function editarItemVendaXml(idx, campo, valor) {
  if (itensXmlVendaCarregados[idx]) {
    itensXmlVendaCarregados[idx][campo] = valor;
    if (campo === 'quantidade' || campo === 'valorUnitario') {
      itensXmlVendaCarregados[idx].valorTotal = itensXmlVendaCarregados[idx].quantidade * itensXmlVendaCarregados[idx].valorUnitario;
      renderTabelaItensXmlVenda();
      aplicarItensXmlAoFormularioVenda();
    }
  }
}

function removerItemVendaXml(idx) {
  if (confirm('Deseja remover este item da conferência?')) {
    itensXmlVendaCarregados.splice(idx, 1);
    renderTabelaItensXmlVenda();
    aplicarItensXmlAoFormularioVenda();
  }
}

function adicionarItemManualVendaXml() {
  itensXmlVendaCarregados.push({
    incluir: true,
    codigo: 'NOVO',
    descricao: 'BOVINOS PARA ABATE',
    unidade: 'CAB',
    quantidade: 1,
    valorUnitario: 0,
    valorTotal: 0
  });
  renderTabelaItensXmlVenda();
}

function selecionarTodosItensVendaXml(marcar) {
  itensXmlVendaCarregados.forEach(item => item.incluir = marcar);
  renderTabelaItensXmlVenda();
  aplicarItensXmlAoFormularioVenda();
}

function limparXmlVendaImportado() {
  if (confirm('Deseja fechar o painel de conferência do XML?')) {
    itensXmlVendaCarregados = [];
    document.getElementById('painelItensXmlVenda').style.display = 'none';
    document.getElementById('xmlFeedbackVenda').style.display = 'none';
    document.getElementById('inputXmlVenda').value = '';
  }
}

function aplicarItensXmlAoFormularioVenda() {
  let valorTotal = 0;
  itensXmlVendaCarregados.forEach(item => {
    if (item.incluir) {
      valorTotal += (item.quantidade * item.valorUnitario);
    }
  });

  if (valorTotal > 0) {
    document.getElementById('venda_valor_total').value = valorTotal.toFixed(2);
  }

  atualizarCalculoVenda();
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text || '';
  return div.innerHTML;
}

// Drag & drop no dropzone de vendas
const dropZoneVenda = document.getElementById('dropZoneXmlVenda');
if (dropZoneVenda) {
  ['dragenter', 'dragover'].forEach(eventName => {
    dropZoneVenda.addEventListener(eventName, (e) => { e.preventDefault(); dropZoneVenda.classList.add('dragover'); }, false);
  });
  ['dragleave', 'drop'].forEach(eventName => {
    dropZoneVenda.addEventListener(eventName, (e) => { e.preventDefault(); dropZoneVenda.classList.remove('dragover'); }, false);
  });
  dropZoneVenda.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    const files = dt.files;
    if (files && files.length) {
      document.getElementById('inputXmlVenda').files = files;
      handleXmlSelectVenda(document.getElementById('inputXmlVenda'));
    }
  });
}

function atualizarSelecaoAnimais() {
  const checkboxes = document.querySelectorAll('.check-animal-venda:checked');
  let qtd = checkboxes.length;
  let somaPeso = 0;

  checkboxes.forEach(cb => {
    const tr = cb.closest('tr');
    const peso = parseFloat(tr.getAttribute('data-peso')) || 0;
    somaPeso += peso;
    tr.classList.add('table-success-subtle');
  });

  document.querySelectorAll('.check-animal-venda:not(:checked)').forEach(cb => {
    cb.closest('tr').classList.remove('table-success-subtle');
  });

  const pesoTotalInput = document.getElementById('venda_peso_total');
  if (pesoTotalInput && (!pesoTotalInput.dataset.manual || pesoTotalInput.value === '')) {
    pesoTotalInput.value = somaPeso > 0 ? somaPeso.toFixed(1) : '';
  }

  atualizarCalculoVenda();
}

function atualizarCalculoVenda() {
  const checkboxes = document.querySelectorAll('.check-animal-venda:checked');
  const qtd = checkboxes.length;
  const tipo = document.getElementById('tipo_precificacao').value;
  const precoUnit = parseFloat(document.getElementById('venda_preco_unit').value) || 0;
  const pesoTotal = parseFloat(document.getElementById('venda_peso_total').value) || 0;
  const arrobas = (pesoTotal * 0.5) / 15;

  const labelPreco = document.getElementById('label_preco_unit');
  if (tipo === 'arroba') labelPreco.textContent = 'Preço por Arroba (R$/@) *';
  else if (tipo === 'peso_vivo_kg') labelPreco.textContent = 'Preço por Quilo Vivo (R$/kg) *';
  else if (tipo === 'cabeca') labelPreco.textContent = 'Preço por Cabeça (R$/cab) *';
  else labelPreco.textContent = 'Preço de Referência (Opcional)';

  let valorTotalCalculado = 0;
  if (tipo === 'arroba') {
    valorTotalCalculado = arrobas * precoUnit;
  } else if (tipo === 'peso_vivo_kg') {
    valorTotalCalculado = pesoTotal * precoUnit;
  } else if (tipo === 'cabeca') {
    valorTotalCalculado = qtd * precoUnit;
  }

  const inputTotal = document.getElementById('venda_valor_total');
  if (tipo !== 'total_fixo' && valorTotalCalculado > 0) {
    inputTotal.value = valorTotalCalculado.toFixed(2);
  }

  const finalTotal = parseFloat(inputTotal.value) || valorTotalCalculado;
  const mediaCab = qtd > 0 ? (finalTotal / qtd) : 0;

  const elQtd = document.getElementById('resumo_qtd_selecionada');
  const elArr = document.getElementById('resumo_arr_total');
  const elMedia = document.getElementById('resumo_media_cab');
  const elDestaque = document.getElementById('resumo_total_destaque');

  if (elQtd) elQtd.textContent = qtd + ' cab';
  if (elArr) elArr.textContent = arrobas.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' @';
  if (elMedia) elMedia.textContent = 'R$ ' + mediaCab.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  if (elDestaque) elDestaque.textContent = 'R$ ' + finalTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function filtrarTabelaAnimais(pastoId) {
  const linhas = document.querySelectorAll('.linha-animal');
  linhas.forEach(linha => {
    if (!pastoId || linha.getAttribute('data-pasto') === pastoId) {
      linha.style.display = '';
    } else {
      linha.style.display = 'none';
    }
  });
}

function marcarTodosVisiveis(marcar) {
  const linhas = document.querySelectorAll('.linha-animal');
  linhas.forEach(linha => {
    if (linha.style.display !== 'none') {
      const cb = linha.querySelector('.check-animal-venda');
      if (cb) cb.checked = marcar;
    }
  });
  atualizarSelecaoAnimais();
}

document.getElementById('venda_peso_total')?.addEventListener('input', function() {
  this.dataset.manual = 'true';
});

document.addEventListener('DOMContentLoaded', () => {
  atualizarSelecaoAnimais();
});
</script>
