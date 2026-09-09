<?php
if (!isset($db)) { $db = getDb(); }
if (!isset($pastos)) {
    $pastos = $db->query("SELECT id, nome, capacidade FROM pastagens WHERE status='ativa' ORDER BY nome")->fetchAll();
}

$isEdit = isset($compra) && !empty($compra['id']);
$compraId = $isEdit ? (int)$compra['id'] : 0;
$actionUrl = $isEdit ? "/compras/{$compraId}/atualizar" : "/compras/salvar";
$animaisLote = $animaisLote ?? [];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <a href="/compras" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar para Compras
  </a>
  <?php if ($isEdit): ?>
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-light text-primary border px-2 py-1">
        <i class="bi bi-pencil-square me-1"></i> Modo Edição • Lote #<?= $compraId ?>
      </span>
      <a href="/compras/<?= $compraId ?>/pdf" target="_blank" class="btn btn-sm btn-outline-success">
        <i class="bi bi-file-earmark-pdf-fill me-1"></i> Espelho PDF
      </a>
    </div>
  <?php endif; ?>
</div>

<form method="POST" action="<?= $actionUrl ?>" enctype="multipart/form-data" id="formCompra">
  <?= csrf_field() ?>

  <div class="row g-3">
    <!-- Coluna Principal: Formulário & Seções Técnicas -->
    <div class="col-lg-8">

      <?php if ($isEdit && !empty($compra['arquivo_xml'])): ?>
        <div class="p-3 mb-3 bg-light rounded border d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-code fs-4 text-success"></i>
            <div>
              <strong class="d-block text-dark">Arquivo XML da NF-e Anexado</strong>
              <small class="text-muted">Você pode baixar a nota atual ou carregar outro arquivo abaixo para substituir/reler.</small>
            </div>
          </div>
          <a href="<?= e($compra['arquivo_xml']) ?>" download class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i> Baixar XML Atual
          </a>
        </div>
      <?php endif; ?>

      <!-- Zona de Importação Inteligente de XML da NF-e -->
      <div class="xml-import-zone" id="dropZoneXml" onclick="document.getElementById('inputXmlFile').click()">
        <input type="file" name="arquivo_xml" id="inputXmlFile" accept=".xml,text/xml" style="display: none;" onchange="handleXmlSelect(this)">
        <i class="bi bi-file-earmark-arrow-up xml-import-icon"></i>
        <h6 class="fw-bold mb-1 text-dark"><?= $isEdit ? 'Substituir / Reimportar XML da NF-e' : 'Importar Arquivo XML da NF-e' ?></h6>
        <p class="small text-muted mb-0">
          Clique ou arraste o arquivo <strong>.xml</strong> da Nota Fiscal para inspecionar e preencher os dados instantaneamente.
        </p>
        <div id="xmlFeedback" style="display: none;" class="xml-badge-success justify-content-center mt-2">
          <i class="bi bi-check-circle-fill text-success fs-6"></i>
          <span id="xmlFeedbackText">XML lido e validado com sucesso!</span>
        </div>
      </div>

      <!-- Painel Interativo de Pré-visualização & Edição de Itens da NF-e -->
      <div id="painelItensXml" style="display: none;" class="card mb-3 border-success shadow-sm">
        <div class="card-header bg-success-subtle text-success-emphasis d-flex justify-content-between align-items-center py-2">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-spreadsheet-fill text-success fs-5"></i>
            <div>
              <strong id="xmlCabecalhoNota">Nota Fiscal Carregada</strong>
              <div class="small text-muted" id="xmlSubcabecalhoNota">Conferência e edição de itens da NF-e</div>
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-danger" onclick="limparXmlImportado()" title="Descartar importação">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>

        <div class="card-body p-3">
          <!-- Observações e Dados Adicionais da NF-e (infCpl) -->
          <div id="xmlInfCplAlert" style="display: none;" class="alert alert-info py-2 px-3 mb-3 small d-flex align-items-start gap-2">
            <i class="bi bi-info-circle-fill mt-1 fs-6"></i>
            <div class="flex-grow-1">
              <strong>Observações / Inf. Complementares da NF-e:</strong>
              <span id="xmlInfCplText" class="d-block mt-1 font-monospace"></span>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div class="small fw-bold text-dark">
              <i class="bi bi-card-checklist text-primary me-1"></i> Itens / Produtos Discriminados na NF-e
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-light border" onclick="selecionarTodosItensXml(true)">Marcar Todos</button>
              <button type="button" class="btn btn-sm btn-light border" onclick="selecionarTodosItensXml(false)">Desmarcar</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="adicionarItemManualXml()">
                <i class="bi bi-plus-lg me-1"></i> Adicionar Item
              </button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-2" id="tabelaItensXml">
              <thead class="table-light small">
                <tr>
                  <th style="width: 35px;" class="text-center" title="Incluir no cálculo do lote">Inc.</th>
                  <th style="width: 85px;">Cód.</th>
                  <th>Descrição do Produto / Lote</th>
                  <th style="width: 65px;" class="text-center">Un.</th>
                  <th style="width: 95px;" class="text-end">Qtd</th>
                  <th style="width: 125px;" class="text-end">Valor Unit. (R$)</th>
                  <th style="width: 130px;" class="text-end">Total Item (R$)</th>
                  <th style="width: 40px;" class="text-center"></th>
                </tr>
              </thead>
              <tbody id="tbodyItensXml">
                <!-- Linhas preenchidas via JavaScript -->
              </tbody>
              <tfoot class="table-light fw-bold small">
                <tr>
                  <td colspan="4" class="text-end">Totais Selecionados:</td>
                  <td class="text-end tabular-nums" id="xmlSomaQtd">0</td>
                  <td></td>
                  <td class="text-end tabular-nums text-success" id="xmlSomaValor">R$ 0,00</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="d-flex justify-content-between align-items-center pt-2 border-top flex-wrap gap-2">
            <small class="text-muted" style="font-size:0.75rem;">
              <i class="bi bi-shield-check text-success me-1"></i>
              Edite as quantidades ou valores unitários caso haja desconto, refugo ou rateio diferenciado. O formulário abaixo é atualizado automaticamente.
            </small>
            <button type="button" class="btn btn-sm btn-success text-nowrap" onclick="aplicarItensXmlAoFormulario()">
              <i class="bi bi-check2-all me-1"></i> Aplicar ao Formulário
            </button>
          </div>
        </div>
      </div>

      <!-- Seção 1: Chaves Mestras e Origem -->
      <div class="form-section">
        <div class="form-section-title">
          <i class="bi bi-shield-check text-success"></i> Chaves Mestras (Documentação Obrigatória)
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Número / Série da GTA *</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-file-earmark-medical text-success"></i></span>
              <input type="text" name="numero_gta" id="compra_gta" class="form-control" placeholder="Ex: 123456/2026" required autocomplete="off" value="<?= e($compra['numero_gta'] ?? '') ?>">
            </div>
            <small class="text-muted" style="font-size:0.72rem;">Guia de Trânsito Animal emitida pelo órgão estadual.</small>
          </div>

          <div class="col-md-6">
            <label class="form-label">Chave de Acesso da NF-e (44 dígitos)</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-receipt"></i></span>
              <input type="text" name="chave_nfe" id="compra_chave_nfe" class="form-control tabular-nums text-uppercase" placeholder="35260900000000000000550010000000001000000000" maxlength="44" autocomplete="off" value="<?= e($compra['chave_nfe'] ?? '') ?>">
            </div>
            <small class="text-muted" style="font-size:0.72rem;">Preenchida automaticamente ao importar o XML acima.</small>
          </div>

          <div class="col-md-8">
            <label class="form-label">Fornecedor / Fazenda de Origem</label>
            <input type="text" name="fornecedor_origem" id="compra_fornecedor" class="form-control" placeholder="Ex: Fazenda Santa Maria, Leilão Terra Boa..." autocomplete="off" value="<?= e($compra['fornecedor_origem'] ?? '') ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Data da Operação / Entrada *</label>
            <input type="date" name="data_compra" id="compra_data" class="form-control" value="<?= e($compra['data_compra'] ?? date('Y-m-d')) ?>" required>
          </div>
        </div>
      </div>

      <!-- Seção 2: Dados Zootécnicos e Valores -->
      <div class="form-section">
        <div class="form-section-title">
          <i class="bi bi-calculator text-primary"></i> Volume, Peso e Custo do Lote
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Quantidade de Cabeças *</label>
            <input type="number" name="quantidade_cabecas" id="compra_qtd" class="form-control tabular-nums" min="1" value="<?= (int)($compra['quantidade_cabecas'] ?? 10) ?>" required oninput="calcularMediasCompra()">
          </div>

          <div class="col-md-4">
            <label class="form-label">Peso Total na Balança (kg)</label>
            <div class="input-group">
              <input type="number" step="0.1" name="peso_total_kg" id="compra_peso" class="form-control tabular-nums" placeholder="Ex: 3600" value="<?= !empty($compra['peso_total_kg']) ? (float)$compra['peso_total_kg'] : '' ?>" oninput="calcularMediasCompra()">
              <span class="input-group-text">kg</span>
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Valor Total da Compra (R$) *</label>
            <div class="input-group">
              <span class="input-group-text fw-bold">R$</span>
              <input type="number" step="0.01" name="valor_total" id="compra_valor" class="form-control tabular-nums fw-bold" placeholder="0,00" required value="<?= !empty($compra['valor_total']) ? number_format((float)$compra['valor_total'], 2, '.', '') : '' ?>" oninput="calcularMediasCompra()">
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Descrição / Identificação do Lote</label>
            <input type="text" name="descricao" id="compra_descricao" class="form-control" placeholder="Ex: Lote de 30 Garrotes Nelore" autocomplete="off" value="<?= e($compra['descricao'] ?? '') ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Pasto de Destino na Fazenda</label>
            <select name="pasto_destino_id" class="form-select">
              <option value="">— Selecionar Pasto Posteriormente —</option>
              <?php foreach ($pastos as $p): ?>
                <?php $selPasto = ($isEdit && $compra['pasto_destino_id'] == $p['id']) ? 'selected' : ''; ?>
                <option value="<?= $p['id'] ?>" <?= $selPasto ?>><?= e($p['nome']) ?> (Capacidade: <?= (int)$p['capacidade'] ?> cab)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Seção 3: Entrada Automática de Brincos no Rebanho (Ou Lista de Animais Cadastrados) -->
      <div class="form-section">
        <?php if ($isEdit && !empty($animaisLote)): ?>
          <div class="form-section-title">
            <i class="bi bi-tags-fill text-primary"></i> Animais Vinculados a este Lote (<?= count($animaisLote) ?> cabeças)
          </div>
          <p class="small text-muted mb-2">
            Estes animais foram gerados na entrada desta compra. Ao salvar alterações no valor total ou pasto, os dados serão automaticamente sincronizados.
          </p>
          <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light small">
                <tr>
                  <th>Brinco</th>
                  <th>Nome / Identificador</th>
                  <th>Raça / Sexo</th>
                  <th class="text-end">Peso Inicial</th>
                  <th class="text-end">Custo Individual</th>
                  <th class="text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($animaisLote as $al): ?>
                  <tr>
                    <td>
                      <a href="/animais/<?= $al['id'] ?>" target="_blank" class="fw-bold text-primary text-decoration-none">
                        <?= e($al['brinco']) ?> <i class="bi bi-box-arrow-up-right small"></i>
                      </a>
                    </td>
                    <td class="small text-secondary"><?= e($al['nome'] ?: '—') ?></td>
                    <td class="small"><?= e($al['raca'] ?: 'Nelore') ?> • <?= $al['sexo'] === 'M' ? 'Macho' : 'Fêmea' ?></td>
                    <td class="text-end tabular-nums small"><?= $al['peso_inicial'] > 0 ? number_format((float)$al['peso_inicial'], 1, ',', '.') . ' kg' : '—' ?></td>
                    <td class="text-end tabular-nums fw-600 small">R$ <?= number_format((float)$al['valor_compra_individual'], 2, ',', '.') ?></td>
                    <td class="text-center">
                      <span class="badge-status <?= $al['status'] === 'ativo' ? 'ativo' : 'neutro' ?>" style="font-size: 0.7rem;">
                        <?= e(ucfirst($al['status'])) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="form-section-title">
            <i class="bi bi-tags text-secondary"></i> Entrada Automática de Brincos no Rebanho
          </div>

          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="checkCadastrarAnimais" name="cadastrar_animais" value="1" onchange="toggleAnimaisCompra(this)">
            <label class="form-check-label fw-bold small" for="checkCadastrarAnimais">
              Gerar e cadastrar os brincos individuais deste lote agora
            </label>
          </div>
          <small class="text-muted d-block mb-3">
            Se habilitado, o sistema criará as fichas de cada animal com peso e valor rateados e alocados no pasto de destino.
          </small>

          <div id="boxEntradaAnimais" style="display: none;" class="pt-3 border-top">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Prefixo do Brinco</label>
                <input type="text" name="prefixo_brinco" class="form-control text-uppercase" placeholder="Ex: C-" value="C-">
                <small class="text-muted" style="font-size:0.7rem;">Ex: C-001, C-002...</small>
              </div>
              <div class="col-md-4">
                <label class="form-label">Raça Predominante</label>
                <input type="text" name="raca_animais" class="form-control" value="Nelore">
              </div>
              <div class="col-md-4">
                <label class="form-label">Sexo do Lote</label>
                <select name="sexo_animais" class="form-select">
                  <option value="M">Macho</option>
                  <option value="F">Fêmea</option>
                </select>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- Coluna Lateral: Resumo de Médias & Ações -->
    <div class="col-lg-4">

      <!-- Card de Conferência em Tempo Real -->
      <div class="card mb-3">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-graph-up-arrow text-primary me-1"></i>Conferência Zootécnica</h6>
        </div>
        <div class="card-body p-3">
          <div class="summary-line">
            <span class="summary-label">Custo Médio / Cab:</span>
            <span class="summary-value tabular-nums" id="resumo_media_cab" style="color: var(--earth-green-800);">R$ 0,00</span>
          </div>
          <div class="summary-line">
            <span class="summary-label">Peso Médio Estimado:</span>
            <span class="summary-value tabular-nums" id="resumo_peso_cab">0,0 kg</span>
          </div>
          <div class="summary-line">
            <span class="summary-label">Volume por Cabeça:</span>
            <span class="summary-value tabular-nums" id="resumo_arr_cab">0,0 @</span>
          </div>
          <div class="summary-line">
            <span class="summary-label">Custo da Arroba (@):</span>
            <span class="summary-value tabular-nums" id="resumo_custo_arr" style="color: var(--earth-green-700);">R$ 0,00/@</span>
          </div>

          <div class="p-2 mt-3 rounded bg-light border" style="font-size:0.75rem; color:var(--text-secondary);">
            <i class="bi bi-info-circle text-primary me-1"></i> Conversão padrão: 30 kg de peso vivo equivalem a 1 @ com 50% de rendimento de carcaça.
          </div>
        </div>
      </div>

      <!-- Card de Confirmação e Ação -->
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0"><?= $isEdit ? 'Salvar Alterações' : 'Finalizar Operação' ?></h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Salvar e Atualizar Compra' : 'Confirmar e Salvar Compra' ?>
            </button>
            <a href="/compras" class="btn btn-secondary">Cancelar</a>
          </div>
        </div>
      </div>

    </div>
  </div>
</form>

<script>
// Estado em memória dos itens extraídos do XML
let itensXmlCarregados = [];

// Leitura e Parsing Inteligente de XML da NF-e
function handleXmlSelect(input) {
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

      // 1. Chave de Acesso de 44 Dígitos
      let chave = '';
      const infNFe = xmlDoc.getElementsByTagName('infNFe')[0];
      if (infNFe && infNFe.getAttribute('Id')) {
        chave = infNFe.getAttribute('Id').replace(/\D/g, '');
      }
      if (!chave) {
        const chNFe = xmlDoc.getElementsByTagName('chNFe')[0];
        if (chNFe) chave = chNFe.textContent.trim();
      }
      if (chave) document.getElementById('compra_chave_nfe').value = chave;

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
        document.getElementById('compra_data').value = dataEmi;
      }

      // 4. Emitente / Fornecedor
      let emitenteNome = '';
      let emitenteDoc = '';
      const emit = xmlDoc.getElementsByTagName('emit')[0];
      if (emit) {
        const xNome = emit.getElementsByTagName('xNome')[0];
        if (xNome && xNome.textContent) emitenteNome = xNome.textContent.trim();
        const cnpj = emit.getElementsByTagName('CNPJ')[0] || emit.getElementsByTagName('CPF')[0];
        if (cnpj && cnpj.textContent) emitenteDoc = cnpj.textContent.trim();
      }
      if (emitenteNome) {
        document.getElementById('compra_fornecedor').value = emitenteNome;
      }

      // 5. Peso Bruto / Líquido
      const pesoB = xmlDoc.getElementsByTagName('pesoB')[0] || xmlDoc.getElementsByTagName('pesoL')[0];
      if (pesoB && pesoB.textContent) {
        const pesoKg = parseFloat(pesoB.textContent) || 0;
        if (pesoKg > 0) document.getElementById('compra_peso').value = pesoKg.toFixed(1);
      }

      // 6. Informações Complementares (infCpl) - Ex: Menção à GTA
      let infCplText = '';
      const infCpl = xmlDoc.getElementsByTagName('infCpl')[0];
      if (infCpl && infCpl.textContent) {
        infCplText = infCpl.textContent.trim();
        document.getElementById('xmlInfCplText').textContent = infCplText;
        document.getElementById('xmlInfCplAlert').style.display = 'flex';

        // Auto-detecção de GTA no texto
        const matchGta = infCplText.match(/gta\s*[:#ºn\.\-]?\s*([0-9a-zA-Z\/\.\-]+)/i);
        if (matchGta && matchGta[1] && !document.getElementById('compra_gta').value) {
          document.getElementById('compra_gta').value = matchGta[1].replace(/[\.\,]+$/, '');
        }
      } else {
        document.getElementById('xmlInfCplAlert').style.display = 'none';
      }

      // 7. Extração Detalhada de Itens (<det> / <prod>)
      itensXmlCarregados = [];
      const dets = xmlDoc.getElementsByTagName('det');
      for (let i = 0; i < dets.length; i++) {
        const det = dets[i];
        const cProd = det.getElementsByTagName('cProd')[0]?.textContent?.trim() || `ITEM-${i+1}`;
        const xProd = det.getElementsByTagName('xProd')[0]?.textContent?.trim() || 'PRODUTO BOVINO';
        const uCom = (det.getElementsByTagName('uCom')[0]?.textContent?.trim() || 'CAB').toUpperCase();
        const qCom = parseFloat(det.getElementsByTagName('qCom')[0]?.textContent) || 1;
        const vUnCom = parseFloat(det.getElementsByTagName('vUnCom')[0]?.textContent) || 0;
        const vProd = parseFloat(det.getElementsByTagName('vProd')[0]?.textContent) || (qCom * vUnCom);

        // Identifica se é frete, serviço ou insumo não bovino
        const xProdLower = xProd.toLowerCase();
        const naoEhGado = xProdLower.includes('frete') || xProdLower.includes('transporte') ||
                          xProdLower.includes('servico') || xProdLower.includes('pedagio') ||
                          xProdLower.includes('vacina') || xProdLower.includes('medicamento');

        itensXmlCarregados.push({
          incluir: !naoEhGado,
          codigo: cProd,
          descricao: xProd,
          unidade: uCom,
          quantidade: qCom,
          valorUnitario: vUnCom,
          valorTotal: vProd
        });
      }

      // Atualiza Cabeçalhos do Painel
      document.getElementById('xmlCabecalhoNota').textContent = `NF-e nº ${numNfe || 'S/N'} Série ${serieNfe || '1'}`;
      document.getElementById('xmlSubcabecalhoNota').textContent = `Emitente: ${emitenteNome || 'Não informado'} ${emitenteDoc ? '(' + emitenteDoc + ')' : ''} • Emissão: ${dataEmi || 'Hoje'}`;

      // Renderiza a Tabela de Itens e aplica ao formulário
      renderTabelaItensXml();
      aplicarItensXmlAoFormulario();

      // Feedback visual
      const feedback = document.getElementById('xmlFeedback');
      const text = document.getElementById('xmlFeedbackText');
      feedback.style.display = 'flex';
      text.textContent = `XML carregado com sucesso: ${file.name} (${itensXmlCarregados.length} item(ns) encontrado(s))`;
      document.getElementById('painelItensXml').style.display = 'block';

      // Sugere descrição do lote se estiver vazia
      const descInput = document.getElementById('compra_descricao');
      if (!descInput.value && itensXmlCarregados.length > 0) {
        const primeiroGado = itensXmlCarregados.find(it => it.incluir) || itensXmlCarregados[0];
        descInput.value = `Lote NF ${numNfe} - ${primeiroGado.descricao.substr(0, 35)}`;
      }

    } catch (err) {
      alert('Erro ao processar o arquivo XML. Verifique se é uma NF-e SEFAZ válida.');
      console.error(err);
    }
  };
  reader.readAsText(file);
}

// Renderiza a tabela de conferência dinâmica
function renderTabelaItensXml() {
  const tbody = document.getElementById('tbodyItensXml');
  tbody.innerHTML = '';

  let somaQtd = 0;
  let somaValor = 0;

  itensXmlCarregados.forEach((item, idx) => {
    if (item.incluir) {
      somaQtd += item.quantidade;
      somaValor += (item.quantidade * item.valorUnitario);
    }

    const tr = document.createElement('tr');
    if (!item.incluir) tr.classList.add('table-secondary', 'opacity-75');

    tr.innerHTML = `
      <td class="text-center">
        <input type="checkbox" class="form-check-input" ${item.incluir ? 'checked' : ''} onchange="toggleItemXml(${idx}, this.checked)">
      </td>
      <td class="text-muted small tabular-nums">${escapeHtml(item.codigo)}</td>
      <td>
        <input type="text" class="form-control form-control-sm" value="${escapeHtml(item.descricao)}" onchange="editarItemXml(${idx}, 'descricao', this.value)">
      </td>
      <td class="text-center">
        <span class="badge bg-light text-dark border">${escapeHtml(item.unidade)}</span>
      </td>
      <td>
        <input type="number" step="1" min="1" class="form-control form-control-sm text-end tabular-nums" value="${item.quantidade}" oninput="editarItemXml(${idx}, 'quantidade', parseFloat(this.value) || 0)">
      </td>
      <td>
        <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end tabular-nums" value="${item.valorUnitario.toFixed(2)}" oninput="editarItemXml(${idx}, 'valorUnitario', parseFloat(this.value) || 0)">
      </td>
      <td class="text-end tabular-nums fw-600 ${item.incluir ? 'text-success' : 'text-muted'}">
        R$ ${(item.quantidade * item.valorUnitario).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removerItemXml(${idx})" title="Excluir item">
          <i class="bi bi-trash"></i>
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  });

  document.getElementById('xmlSomaQtd').textContent = Math.round(somaQtd) + ' cab';
  document.getElementById('xmlSomaValor').textContent = 'R$ ' + somaValor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function toggleItemXml(idx, checked) {
  if (itensXmlCarregados[idx]) {
    itensXmlCarregados[idx].incluir = checked;
    renderTabelaItensXml();
    aplicarItensXmlAoFormulario();
  }
}

function editarItemXml(idx, campo, valor) {
  if (itensXmlCarregados[idx]) {
    itensXmlCarregados[idx][campo] = valor;
    if (campo === 'quantidade' || campo === 'valorUnitario') {
      itensXmlCarregados[idx].valorTotal = itensXmlCarregados[idx].quantidade * itensXmlCarregados[idx].valorUnitario;
      renderTabelaItensXml();
      aplicarItensXmlAoFormulario();
    }
  }
}

function removerItemXml(idx) {
  if (confirm('Deseja remover este item da conferência?')) {
    itensXmlCarregados.splice(idx, 1);
    renderTabelaItensXml();
    aplicarItensXmlAoFormulario();
  }
}

function adicionarItemManualXml() {
  itensXmlCarregados.push({
    incluir: true,
    codigo: 'NOVO',
    descricao: 'LOTE ADICIONAL DE BOVINOS',
    unidade: 'CAB',
    quantidade: 1,
    valorUnitario: 0,
    valorTotal: 0
  });
  renderTabelaItensXml();
}

function selecionarTodosItensXml(marcar) {
  itensXmlCarregados.forEach(item => item.incluir = marcar);
  renderTabelaItensXml();
  aplicarItensXmlAoFormulario();
}

function limparXmlImportado() {
  if (confirm('Deseja fechar o painel de conferência do XML?')) {
    itensXmlCarregados = [];
    document.getElementById('painelItensXml').style.display = 'none';
    document.getElementById('xmlFeedback').style.display = 'none';
    document.getElementById('inputXmlFile').value = '';
  }
}

// Aplica a soma dos itens marcados aos campos principais do formulário
function aplicarItensXmlAoFormulario() {
  let qtdTotal = 0;
  let valorTotal = 0;

  itensXmlCarregados.forEach(item => {
    if (item.incluir) {
      qtdTotal += item.quantidade;
      valorTotal += (item.quantidade * item.valorUnitario);
    }
  });

  if (qtdTotal > 0) {
    document.getElementById('compra_qtd').value = Math.round(qtdTotal);
  }
  if (valorTotal > 0) {
    document.getElementById('compra_valor').value = valorTotal.toFixed(2);
  }

  calcularMediasCompra();
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text || '';
  return div.innerHTML;
}

// Drag & Drop no dropzone
const dropZone = document.getElementById('dropZoneXml');
if (dropZone) {
  ['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => { e.preventDefault(); dropZone.classList.add('dragover'); }, false);
  });
  ['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => { e.preventDefault(); dropZone.classList.remove('dragover'); }, false);
  });
  dropZone.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    const files = dt.files;
    if (files && files.length) {
      document.getElementById('inputXmlFile').files = files;
      handleXmlSelect(document.getElementById('inputXmlFile'));
    }
  });
}

function calcularMediasCompra() {
  const qtd = parseFloat(document.getElementById('compra_qtd')?.value) || 0;
  const peso = parseFloat(document.getElementById('compra_peso')?.value) || 0;
  const valor = parseFloat(document.getElementById('compra_valor')?.value) || 0;

  const mediaCab = qtd > 0 ? (valor / qtd) : 0;
  const pesoCab = qtd > 0 ? (peso / qtd) : 0;
  const arrobas = (peso * 0.5) / 15;
  const arrobaCab = qtd > 0 ? (arrobas / qtd) : 0;
  const custoArr = arrobas > 0 ? (valor / arrobas) : 0;

  const elMedia = document.getElementById('resumo_media_cab');
  const elPeso = document.getElementById('resumo_peso_cab');
  const elArr = document.getElementById('resumo_arr_cab');
  const elCustoArr = document.getElementById('resumo_custo_arr');

  if (elMedia) elMedia.textContent = 'R$ ' + mediaCab.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  if (elPeso) elPeso.textContent = pesoCab.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' kg';
  if (elArr) elArr.textContent = arrobaCab.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' @';
  if (elCustoArr) elCustoArr.textContent = 'R$ ' + custoArr.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '/@';
}

function toggleAnimaisCompra(cb) {
  const box = document.getElementById('boxEntradaAnimais');
  if (box) box.style.display = cb.checked ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', () => {
  calcularMediasCompra();
});
</script>
