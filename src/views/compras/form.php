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

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="/compras" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Voltar para Compras
    </a>
    <?php if ($isEdit): ?>
      <span class="badge bg-light text-primary border px-2 py-1">
        <i class="bi bi-pencil-square me-1"></i> Modo Edição • Lote #<?= $compraId ?>
      </span>
    <?php endif; ?>
  </div>

  <?php if ($isEdit): ?>
    <div class="d-flex align-items-center gap-2">
      <a href="/compras/<?= $compraId ?>/pdf" target="_blank" class="btn btn-sm btn-outline-success">
        <i class="bi bi-file-earmark-pdf-fill me-1"></i> Espelho PDF
      </a>
      <?php if (!empty($compra['arquivo_xml'])): ?>
        <a href="/compras/<?= $compraId ?>/nfe" target="_blank" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-receipt-cutoff me-1"></i> Ver NF-e Completa
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<form method="POST" action="<?= $actionUrl ?>" enctype="multipart/form-data" id="formCompra">
  <?= csrf_field() ?>

  <div class="row g-3">
    <!-- Coluna Principal: Formulário & Seções Técnicas -->
    <div class="col-lg-8">

      <!-- Informação de XML existente em modo de Edição -->
      <?php if ($isEdit && !empty($compra['arquivo_xml'])): ?>
        <div class="p-3 mb-3 bg-white rounded border d-flex justify-content-between align-items-center flex-wrap gap-2 shadow-sm">
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle p-2 bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
              <i class="bi bi-file-earmark-check-fill fs-5"></i>
            </div>
            <div>
              <div class="fw-bold text-dark">Nota Fiscal Eletrônica (NF-e) Anexada</div>
              <div class="text-muted small">
                <?php if (!empty($compra['chave_nfe'])): ?>
                  Chave: <span class="font-monospace text-dark"><?= substr($compra['chave_nfe'], 0, 10) ?>...<?= substr($compra['chave_nfe'], -6) ?></span> •
                <?php endif; ?>
                Fornecedor: <strong><?= e($compra['fornecedor_origem'] ?: 'Não informado') ?></strong>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <a href="/compras/<?= $compraId ?>/nfe" target="_blank" class="btn btn-sm btn-outline-success">
              <i class="bi bi-eye me-1"></i> Ver Detalhes da NF-e
            </a>
            <a href="<?= e($compra['arquivo_xml']) ?>" download class="btn btn-sm btn-secondary">
              <i class="bi bi-download me-1"></i> Baixar XML
            </a>
          </div>
        </div>
      <?php endif; ?>

      <!-- Zona de Importação Inteligente de XML da NF-e -->
      <div class="xml-import-zone" id="dropZoneXml" onclick="document.getElementById('inputXmlFile').click()">
        <input type="file" name="arquivo_xml" id="inputXmlFile" accept=".xml,text/xml" style="display: none;" onchange="handleXmlSelect(this)">
        <i class="bi bi-file-earmark-arrow-up xml-import-icon"></i>
        <h6 class="fw-bold mb-1 text-dark"><?= $isEdit ? 'Substituir ou Reimportar XML da NF-e' : 'Importar Arquivo XML da NF-e' ?></h6>
        <p class="small text-muted mb-0">
          Clique ou arraste o arquivo <strong>.xml</strong> da Nota Fiscal para inspecionar os itens na hora e preencher os dados.
        </p>
        <div id="xmlFeedback" style="display: none;" class="xml-badge-success justify-content-center mt-2">
          <i class="bi bi-check-circle-fill text-success fs-6"></i>
          <span id="xmlFeedbackText">XML lido e validado com sucesso!</span>
        </div>
      </div>

      <!-- Painel Dinâmico & Dimensionado de Pré-visualização de Itens da NF-e -->
      <div id="painelItensXml" style="display: none;" class="nfe-preview-panel active">
        <div class="nfe-preview-header">
          <div class="nfe-preview-title">
            <i class="bi bi-receipt-cutoff text-success fs-5"></i>
            <div>
              <h6 id="xmlCabecalhoNota">Nota Fiscal Carregada</h6>
              <small class="text-muted" id="xmlSubcabecalhoNota">Conferência dos itens da NF-e</small>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-light border py-1 px-2" onclick="abrirModalNfeCompleta()" title="Ver todos os detalhes da nota fiscal original">
              <i class="bi bi-eye me-1"></i> Ver NF-e Completa
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2" onclick="limparXmlImportado()" title="Descartar XML">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>
        </div>

        <!-- Chips de Metadados da Nota -->
        <div class="nfe-meta-chips" id="xmlMetaChips">
          <!-- Inserido dinamicamente via JS -->
        </div>

        <!-- Alerta com InfCpl / GTA -->
        <div id="xmlInfCplAlert" style="display: none;" class="p-2 px-3 bg-light border-bottom small d-flex align-items-start gap-2">
          <i class="bi bi-info-circle text-primary mt-1"></i>
          <div class="flex-grow-1">
            <strong class="text-secondary">Observações da NF-e:</strong>
            <span id="xmlInfCplText" class="font-monospace text-dark ms-1"></span>
          </div>
        </div>

        <!-- Tabela Compacta e Dimensionada de Itens -->
        <div class="nfe-table-container">
          <table class="nfe-table" id="tabelaItensXml">
            <thead>
              <tr>
                <th style="width: 36px;" class="text-center" title="Incluir este item no cálculo do lote">Inc.</th>
                <th>Produto / Descrição na NF-e</th>
                <th style="width: 90px;" class="text-end">Qtd</th>
                <th style="width: 110px;" class="text-end">Valor Unit.</th>
                <th style="width: 115px;" class="text-end">Total Item</th>
                <th style="width: 32px;" class="text-center"></th>
              </tr>
            </thead>
            <tbody id="tbodyItensXml">
              <!-- Linhas geradas via JavaScript -->
            </tbody>
          </table>
        </div>

        <!-- Barra de Rodapé / Totais -->
        <div class="nfe-summary-footer">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-light border py-0 px-2" onclick="selecionarTodosItensXml(true)">Marcar Todos</button>
            <button type="button" class="btn btn-sm btn-light border py-0 px-2" onclick="selecionarTodosItensXml(false)">Desmarcar</button>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="adicionarItemManualXml()">
              <i class="bi bi-plus-lg me-1"></i> Adicionar Item
            </button>
            <span class="text-muted small ms-1" id="xmlStatusIgnorados"></span>
          </div>

          <div class="d-flex align-items-center gap-3">
            <div class="text-end">
              <small class="text-muted d-block" style="font-size:0.7rem;">Soma Selecionada:</small>
              <strong class="tabular-nums" id="xmlSomaQtd">0 cab</strong>
              <span class="text-muted">•</span>
              <strong class="tabular-nums text-success" id="xmlSomaValor">R$ 0,00</strong>
            </div>
            <button type="button" class="btn btn-sm btn-success py-1 px-3" onclick="aplicarItensXmlAoFormulario()">
              <i class="bi bi-arrow-repeat me-1"></i> Aplicar ao Lote
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
              <input type="text" name="chave_nfe" id="compra_chave_nfe" class="form-control tabular-nums text-uppercase font-monospace" placeholder="35260900000000000000550010000000001000000000" maxlength="44" autocomplete="off" value="<?= e($compra['chave_nfe'] ?? '') ?>">
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

      <!-- Seção 3: Entrada Automática de Brincos no Rebanho (Ou Animais Vinculados) -->
      <div class="form-section">
        <?php if ($isEdit && !empty($animaisLote)): ?>
          <div class="form-section-title">
            <i class="bi bi-tags-fill text-primary"></i> Animais Vinculados a este Lote (<?= count($animaisLote) ?> cabeças)
          </div>
          <p class="small text-muted mb-2">
            Estes animais foram gerados no recebimento deste lote. Ao salvar alterações no valor total ou pasto, os dados serão automaticamente sincronizados.
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

<!-- Modal com Todos os Detalhes da NF-e e XML -->
<div class="modal fade" id="modalNfeCompleta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2 bg-light">
        <h6 class="modal-title fw-bold text-dark" id="modalNfeTitulo">
          <i class="bi bi-receipt-cutoff text-success me-1"></i> Detalhes Completos da Nota Fiscal Eletrônica (NF-e)
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3">
        <!-- Chave -->
        <div class="mb-3">
          <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size:0.7rem;">Chave de Acesso da NF-e</small>
          <div class="nfe-key-display">
            <span id="modalNfeChave" class="tabular-nums"></span>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="copiarTextoModal('modalNfeChave')">
              <i class="bi bi-clipboard me-1"></i> Copiar Chave
            </button>
          </div>
        </div>

        <!-- Cards Emitente e Destinatário -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <div class="p-3 bg-light rounded border h-100">
              <small class="text-secondary fw-bold text-uppercase d-block mb-1" style="font-size:0.7rem;">Emitente / Fornecedor</small>
              <strong id="modalNfeEmitNome" class="text-dark d-block"></strong>
              <div id="modalNfeEmitDoc" class="small text-muted mt-1 font-monospace"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 bg-light rounded border h-100">
              <small class="text-secondary fw-bold text-uppercase d-block mb-1" style="font-size:0.7rem;">Destinatário / Receptora</small>
              <strong id="modalNfeDestNome" class="text-dark d-block"></strong>
              <div id="modalNfeDestDoc" class="small text-muted mt-1 font-monospace"></div>
            </div>
          </div>
        </div>

        <!-- Tabela Completa de Itens -->
        <h6 class="fw-bold mb-2 text-dark" style="font-size:0.88rem;">Todos os Produtos Discriminados na NF-e</h6>
        <div class="table-responsive mb-3 border rounded">
          <table class="table table-sm table-bordered align-middle mb-0" style="font-size:0.82rem;">
            <thead class="table-light">
              <tr>
                <th style="width: 35px;" class="text-center">#</th>
                <th style="width: 90px;">Código</th>
                <th>Descrição do Produto</th>
                <th style="width: 80px;">NCM</th>
                <th style="width: 50px;" class="text-center">Un.</th>
                <th style="width: 80px;" class="text-end">Qtd</th>
                <th style="width: 105px;" class="text-end">Valor Unit.</th>
                <th style="width: 110px;" class="text-end">Valor Total</th>
              </tr>
            </thead>
            <tbody id="modalNfeTbodyItens"></tbody>
          </table>
        </div>

        <!-- Observações / infCpl -->
        <div id="modalNfeInfCplBox" class="p-2 px-3 bg-light rounded border small mb-3">
          <strong class="text-dark d-block mb-1">Informações Complementares da NF-e:</strong>
          <span id="modalNfeInfCpl" class="font-monospace text-secondary"></span>
        </div>

        <!-- XML Bruto -->
        <div>
          <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#modalNfeCollapseXml">
            <i class="bi bi-code-slash me-1"></i> Visualizar Estrutura XML Bruta
          </button>
          <div class="collapse mt-2" id="modalNfeCollapseXml">
            <pre class="p-3 bg-dark text-light rounded small" style="max-height: 250px; overflow: auto; font-size:0.72rem;" id="modalNfeXmlPre"></pre>
          </div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<script>
// Armazena objeto completo da NF-e lida
let nfeAtualObjeto = null;
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

      // 1. Chave de 44 Dígitos
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

      // 2. Número e Série
      const numNfe = xmlDoc.getElementsByTagName('nNF')[0]?.textContent?.trim() || 'S/N';
      const serieNfe = xmlDoc.getElementsByTagName('serie')[0]?.textContent?.trim() || '1';

      // 3. Data de Emissão
      let dataEmi = '';
      const dhEmi = xmlDoc.getElementsByTagName('dhEmi')[0] || xmlDoc.getElementsByTagName('dEmi')[0];
      if (dhEmi && dhEmi.textContent) {
        dataEmi = dhEmi.textContent.substr(0, 10);
        document.getElementById('compra_data').value = dataEmi;
      }

      // 4. Emitente
      let emitNome = '';
      let emitDoc = '';
      const emit = xmlDoc.getElementsByTagName('emit')[0];
      if (emit) {
        emitNome = emit.getElementsByTagName('xNome')[0]?.textContent?.trim() || '';
        emitDoc = emit.getElementsByTagName('CNPJ')[0]?.textContent?.trim() ||
                  emit.getElementsByTagName('CPF')[0]?.textContent?.trim() || '';
      }
      if (emitNome) {
        document.getElementById('compra_fornecedor').value = emitNome;
      }

      // 5. Destinatário
      let destNome = '';
      let destDoc = '';
      const dest = xmlDoc.getElementsByTagName('dest')[0];
      if (dest) {
        destNome = dest.getElementsByTagName('xNome')[0]?.textContent?.trim() || '';
        destDoc = dest.getElementsByTagName('CNPJ')[0]?.textContent?.trim() ||
                  dest.getElementsByTagName('CPF')[0]?.textContent?.trim() || '';
      }

      // 6. Peso Balança
      const pesoB = xmlDoc.getElementsByTagName('pesoB')[0] || xmlDoc.getElementsByTagName('pesoL')[0];
      if (pesoB && pesoB.textContent) {
        const pesoKg = parseFloat(pesoB.textContent) || 0;
        if (pesoKg > 0) document.getElementById('compra_peso').value = pesoKg.toFixed(1);
      }

      // 7. infCpl / GTA
      let infCplText = xmlDoc.getElementsByTagName('infCpl')[0]?.textContent?.trim() || '';
      let gtaDetectada = '';
      if (infCplText) {
        document.getElementById('xmlInfCplText').textContent = infCplText;
        document.getElementById('xmlInfCplAlert').style.display = 'flex';
        const matchGta = infCplText.match(/gta\s*[:#ºn\.\-]?\s*([0-9a-zA-Z\/\.\-]+)/i);
        if (matchGta && matchGta[1] && !document.getElementById('compra_gta').value) {
          gtaDetectada = matchGta[1].replace(/[\.\,]+$/, '');
          document.getElementById('compra_gta').value = gtaDetectada;
        }
      } else {
        document.getElementById('xmlInfCplAlert').style.display = 'none';
      }

      // 8. Itens (<det>)
      itensXmlCarregados = [];
      const dets = xmlDoc.getElementsByTagName('det');
      for (let i = 0; i < dets.length; i++) {
        const det = dets[i];
        const prod = det.getElementsByTagName('prod')[0];
        if (!prod) continue;

        const cProd = prod.getElementsByTagName('cProd')[0]?.textContent?.trim() || `ITEM-${i+1}`;
        const xProd = prod.getElementsByTagName('xProd')[0]?.textContent?.trim() || 'BOVINOS';
        const ncm = prod.getElementsByTagName('NCM')[0]?.textContent?.trim() || '';
        const uCom = (prod.getElementsByTagName('uCom')[0]?.textContent?.trim() || 'CAB').toUpperCase();
        const qCom = parseFloat(prod.getElementsByTagName('qCom')[0]?.textContent) || 1;
        const vUnCom = parseFloat(prod.getElementsByTagName('vUnCom')[0]?.textContent) || 0;
        const vProd = parseFloat(prod.getElementsByTagName('vProd')[0]?.textContent) || (qCom * vUnCom);

        const xLower = xProd.toLowerCase();
        const naoEhGado = xLower.includes('frete') || xLower.includes('transporte') ||
                          xLower.includes('servico') || xLower.includes('serviço') ||
                          xLower.includes('pedagio') || xLower.includes('vacina');

        itensXmlCarregados.push({
          item: i + 1,
          incluir: !naoEhGado,
          codigo: cProd,
          descricao: xProd,
          ncm: ncm,
          unidade: uCom,
          quantidade: qCom,
          valorUnitario: vUnCom,
          valorTotal: vProd,
          isGado: !naoEhGado
        });
      }

      // Guarda objeto estruturado para o Modal Completo
      nfeAtualObjeto = {
        chave: chave,
        numero: numNfe,
        serie: serieNfe,
        dataEmissao: dataEmi,
        emitente: { nome: emitNome, doc: emitDoc },
        destinatario: { nome: destNome, doc: destDoc },
        infCpl: infCplText,
        itens: itensXmlCarregados,
        rawXml: xmlText
      };

      // Monta chips de metadados
      const chipsEl = document.getElementById('xmlMetaChips');
      chipsEl.innerHTML = `
        <span class="nfe-chip"><i class="bi bi-receipt text-success"></i> NF-e: <strong>${escapeHtml(numNfe)}</strong></span>
        <span class="nfe-chip"><i class="bi bi-tag text-muted"></i> Série: <strong>${escapeHtml(serieNfe)}</strong></span>
        <span class="nfe-chip"><i class="bi bi-calendar-event text-muted"></i> Emissão: <strong>${escapeHtml(dataEmi || 'Hoje')}</strong></span>
        <span class="nfe-chip"><i class="bi bi-truck text-muted"></i> Emitente: <strong>${escapeHtml(emitNome || 'Não informado')}</strong></span>
        ${gtaDetectada ? `<span class="nfe-chip text-success border-success"><i class="bi bi-shield-check text-success"></i> GTA Detectada: <strong>${escapeHtml(gtaDetectada)}</strong></span>` : ''}
      `;

      document.getElementById('xmlCabecalhoNota').textContent = `NF-e nº ${numNfe} — Série ${serieNfe}`;
      document.getElementById('xmlSubcabecalhoNota').textContent = `${emitNome || 'Emitente'} • ${itensXmlCarregados.length} item(ns) discriminado(s)`;

      renderTabelaItensXml();
      aplicarItensXmlAoFormulario();

      document.getElementById('painelItensXml').style.display = 'block';
      const feedback = document.getElementById('xmlFeedback');
      const text = document.getElementById('xmlFeedbackText');
      feedback.style.display = 'flex';
      text.textContent = `XML processado com sucesso: ${file.name} (${itensXmlCarregados.length} itens extraídos)`;

      // Sugestão de descrição
      const descInput = document.getElementById('compra_descricao');
      if (!descInput.value && itensXmlCarregados.length > 0) {
        const primeiro = itensXmlCarregados.find(it => it.incluir) || itensXmlCarregados[0];
        descInput.value = `Lote NF ${numNfe} - ${primeiro.descricao.substr(0, 32)}`;
      }

    } catch (err) {
      alert('Erro ao processar o arquivo XML. Verifique se é uma NF-e SEFAZ válida.');
      console.error(err);
    }
  };
  reader.readAsText(file);
}

// Renderiza tabela compacta e dimensionada
function renderTabelaItensXml() {
  const tbody = document.getElementById('tbodyItensXml');
  tbody.innerHTML = '';

  let somaQtd = 0;
  let somaValor = 0;
  let ignoradosCount = 0;

  itensXmlCarregados.forEach((item, idx) => {
    if (item.incluir) {
      somaQtd += item.quantidade;
      somaValor += (item.quantidade * item.valorUnitario);
    } else {
      ignoradosCount++;
    }

    const tr = document.createElement('tr');
    if (!item.incluir) tr.classList.add('row-ignored');

    tr.innerHTML = `
      <td class="text-center">
        <input type="checkbox" class="form-check-input" ${item.incluir ? 'checked' : ''} onchange="toggleItemXml(${idx}, this.checked)">
      </td>
      <td>
        <div class="d-flex align-items-center gap-1">
          <span class="badge bg-light text-secondary border font-monospace" style="font-size:0.68rem;" title="Código do produto">${escapeHtml(item.codigo)}</span>
          <span class="badge bg-light text-dark border" style="font-size:0.68rem;">${escapeHtml(item.unidade)}</span>
          <input type="text" class="nfe-cell-input fw-600 flex-grow-1" value="${escapeHtml(item.descricao)}" onchange="editarItemXml(${idx}, 'descricao', this.value)">
        </div>
        ${!item.incluir ? '<span class="badge bg-light text-muted border mt-1" style="font-size:0.65rem;">Ignorado no lote (Frete / Serviço)</span>' : ''}
      </td>
      <td>
        <input type="number" step="1" min="1" class="nfe-cell-input text-end tabular-nums" value="${item.quantidade}" oninput="editarItemXml(${idx}, 'quantidade', parseFloat(this.value) || 0)">
      </td>
      <td>
        <input type="number" step="0.01" min="0" class="nfe-cell-input text-end tabular-nums" value="${item.valorUnitario.toFixed(2)}" oninput="editarItemXml(${idx}, 'valorUnitario', parseFloat(this.value) || 0)">
      </td>
      <td class="text-end tabular-nums fw-bold ${item.incluir ? 'text-success' : 'text-muted'}">
        R$ ${(item.quantidade * item.valorUnitario).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-link text-danger p-0 border-0" onclick="removerItemXml(${idx})" title="Remover item">
          <i class="bi bi-x-circle"></i>
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  });

  document.getElementById('xmlSomaQtd').textContent = Math.round(somaQtd) + ' cab';
  document.getElementById('xmlSomaValor').textContent = 'R$ ' + somaValor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  const statusIgn = document.getElementById('xmlStatusIgnorados');
  if (ignoradosCount > 0) {
    statusIgn.textContent = `(${ignoradosCount} item(ns) não-gado desmarcado(s))`;
  } else {
    statusIgn.textContent = '';
  }
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
    item: itensXmlCarregados.length + 1,
    incluir: true,
    codigo: 'NOVO',
    descricao: 'BOVINOS ADICIONAIS',
    ncm: '01022990',
    unidade: 'CAB',
    quantidade: 1,
    valorUnitario: 0,
    valorTotal: 0,
    isGado: true
  });
  renderTabelaItensXml();
}

function selecionarTodosItensXml(marcar) {
  itensXmlCarregados.forEach(it => it.incluir = marcar);
  renderTabelaItensXml();
  aplicarItensXmlAoFormulario();
}

function limparXmlImportado() {
  if (confirm('Deseja fechar o painel de conferência do XML?')) {
    itensXmlCarregados = [];
    nfeAtualObjeto = null;
    document.getElementById('painelItensXml').style.display = 'none';
    document.getElementById('xmlFeedback').style.display = 'none';
    document.getElementById('inputXmlFile').value = '';
  }
}

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

// Modal de Visualização da NF-e Completa
function abrirModalNfeCompleta() {
  if (!nfeAtualObjeto) return;
  
  document.getElementById('modalNfeTitulo').innerHTML = `<i class="bi bi-receipt-cutoff text-success me-1"></i> NF-e nº ${escapeHtml(nfeAtualObjeto.numero)} (Série ${escapeHtml(nfeAtualObjeto.serie)})`;
  document.getElementById('modalNfeChave').textContent = nfeAtualObjeto.chave ? nfeAtualObjeto.chave.replace(/(.{4})/g, '$1 ').trim() : 'NÃO INFORMADA';
  document.getElementById('modalNfeEmitNome').textContent = nfeAtualObjeto.emitente.nome || 'Não informado';
  document.getElementById('modalNfeEmitDoc').textContent = nfeAtualObjeto.emitente.doc ? `CNPJ/CPF: ${nfeAtualObjeto.emitente.doc}` : '';
  document.getElementById('modalNfeDestNome').textContent = nfeAtualObjeto.destinatario.nome || 'PECUÁRIA GEST';
  document.getElementById('modalNfeDestDoc').textContent = nfeAtualObjeto.destinatario.doc ? `CNPJ/CPF: ${nfeAtualObjeto.destinatario.doc}` : '';
  
  const infCplBox = document.getElementById('modalNfeInfCplBox');
  if (nfeAtualObjeto.infCpl) {
    document.getElementById('modalNfeInfCpl').textContent = nfeAtualObjeto.infCpl;
    infCplBox.style.display = 'block';
  } else {
    infCplBox.style.display = 'none';
  }

  const tbody = document.getElementById('modalNfeTbodyItens');
  tbody.innerHTML = '';
  nfeAtualObjeto.itens.forEach((it, i) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="text-center text-muted tabular-nums">${i+1}</td>
      <td class="font-monospace small">${escapeHtml(it.codigo)}</td>
      <td><strong>${escapeHtml(it.descricao)}</strong></td>
      <td class="font-monospace small text-muted">${escapeHtml(it.ncm || '—')}</td>
      <td class="text-center"><span class="badge bg-light text-dark border">${escapeHtml(it.unidade)}</span></td>
      <td class="text-end tabular-nums">${it.quantidade}</td>
      <td class="text-end tabular-nums">R$ ${it.valorUnitario.toFixed(2)}</td>
      <td class="text-end tabular-nums fw-bold text-success">R$ ${(it.quantidade * it.valorUnitario).toFixed(2)}</td>
    `;
    tbody.appendChild(tr);
  });

  document.getElementById('modalNfeXmlPre').textContent = nfeAtualObjeto.rawXml || '';

  const modal = new bootstrap.Modal(document.getElementById('modalNfeCompleta'));
  modal.show();
}

function copiarTextoModal(elementId) {
  const txt = document.getElementById(elementId)?.textContent?.replace(/\s+/g, '') || '';
  navigator.clipboard.writeText(txt).then(() => alert('Chave copiada para a área de transferência!'));
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text || '';
  return div.innerHTML;
}

// Drag & drop
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
