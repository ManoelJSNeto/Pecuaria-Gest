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

  <!-- 1. BLOCO SUPERIOR: Importação de XML e Painel de Conferência (Largura Total) -->
  <div class="mb-4">
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
    <div id="painelItensXml" style="display: none;" class="nfe-preview-panel active mt-3">
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
  </div>

  <!-- 2. GRID PRINCIPAL: Formulário Detalhado (8) + Resumo & Ações (4) -->
  <div class="row g-3">
    <!-- Coluna Principal: Formulário & Seções Técnicas -->
    <div class="col-lg-8">

      <!-- Seção 1: Identificação & Documentação da Entrada -->
      <div class="form-section">
        <div class="form-section-title">
          <i class="bi bi-file-earmark-text text-success"></i> Identificação & Documentação da Entrada
        </div>

        <div class="row g-3">
          <!-- Descrição / Identificação da NF-e em DESTAQUE no topo da seção -->
          <div class="col-12">
            <label class="form-label fw-bold text-dark">
              Descrição da NF-e / Identificação do Lote *
            </label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-card-text text-success"></i></span>
              <input type="text" name="descricao" id="compra_descricao" class="form-control fw-600" placeholder="Ex: Lote NF 1234 - BOVINOS MACHOS NELORE PARA RECRIA" autocomplete="off" required value="<?= e($compra['descricao'] ?? '') ?>">
            </div>
            <small class="text-muted" style="font-size:0.72rem;">Preenchido automaticamente ao carregar o XML da NF-e ou digitado manualmente.</small>
          </div>

          <div class="col-md-7">
            <label class="form-label">Fornecedor / Fazenda de Origem</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-building"></i></span>
              <input type="text" name="fornecedor_origem" id="compra_fornecedor" class="form-control" placeholder="Ex: Fazenda Santa Maria, Leilão Terra Boa..." autocomplete="off" value="<?= e($compra['fornecedor_origem'] ?? '') ?>">
            </div>
          </div>

          <div class="col-md-5">
            <label class="form-label">Data da Operação / Entrada *</label>
            <input type="date" name="data_compra" id="compra_data" class="form-control" value="<?= e($compra['data_compra'] ?? date('Y-m-d')) ?>" required>
          </div>

          <div class="col-md-7">
            <label class="form-label">Chave de Acesso da NF-e (44 dígitos)</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-receipt"></i></span>
              <input type="text" name="chave_nfe" id="compra_chave_nfe" class="form-control tabular-nums text-uppercase font-monospace" placeholder="35260900000000000000550010000000001000000000" maxlength="44" autocomplete="off" value="<?= e($compra['chave_nfe'] ?? '') ?>">
            </div>
            <small class="text-muted" style="font-size:0.72rem;">Preenchida automaticamente ao importar o XML acima.</small>
          </div>

          <div class="col-md-5">
            <label class="form-label">Número / Série da GTA *</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-file-earmark-medical text-success"></i></span>
              <input type="text" name="numero_gta" id="compra_gta" class="form-control" placeholder="Ex: 123456/2026" required autocomplete="off" value="<?= e($compra['numero_gta'] ?? '') ?>">
            </div>
            <small class="text-muted" style="font-size:0.72rem;">Guia de Trânsito Animal emitida pelo órgão estadual.</small>
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
            <input type="number" name="quantidade_cabecas" id="compra_qtd" class="form-control tabular-nums fw-600" min="1" value="<?= (int)($compra['quantidade_cabecas'] ?? 10) ?>" required oninput="calcularMediasCompra()">
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
              <input type="number" step="0.01" name="valor_total" id="compra_valor" class="form-control tabular-nums fw-bold text-success fs-6" placeholder="0,00" required value="<?= !empty($compra['valor_total']) ? number_format((float)$compra['valor_total'], 2, '.', '') : '' ?>" oninput="calcularMediasCompra()">
            </div>
          </div>

          <div class="col-12">
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
            <i class="bi bi-tags text-secondary"></i> Entrada de Brincos no Rebanho
          </div>

          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="checkCadastrarAnimais" name="cadastrar_animais" value="1" onchange="toggleAnimaisCompra(this)">
            <label class="form-check-label fw-bold small" for="checkCadastrarAnimais">
              Gerar e cadastrar os brincos individuais deste lote agora
            </label>
          </div>
          <small class="text-muted d-block mb-3">
            Se habilitado, o sistema criará as fichas de cada animal com peso e valor rateados ou discriminados individualmente e alocados no pasto de destino.
          </small>

          <div id="boxEntradaAnimais" style="display: none;" class="pt-3 border-top">
            <!-- SELETOR DE MODO (AUTOMÁTICO vs INDIVIDUAL) -->
            <div class="d-flex align-items-center justify-content-between p-2 mb-3 bg-light rounded border flex-wrap gap-2">
              <div class="d-flex align-items-center gap-2">
                <span class="small fw-bold text-dark"><i class="bi bi-sliders me-1"></i>Modo de Entrada:</span>
                <div class="btn-group btn-group-sm" role="group">
                  <input type="radio" class="btn-check" name="modo_entrada_animais" id="modoEntradaAuto" value="automatico" checked onchange="alternarModoEntradaAnimais('automatico')">
                  <label class="btn btn-outline-success" for="modoEntradaAuto">
                    <i class="bi bi-lightning-charge me-1"></i>Lote Automático (Por Prefixo)
                  </label>

                  <input type="radio" class="btn-check" name="modo_entrada_animais" id="modoEntradaIndiv" value="individual" onchange="alternarModoEntradaAnimais('individual')">
                  <label class="btn btn-outline-success" for="modoEntradaIndiv">
                    <i class="bi bi-list-check me-1"></i>Romaneio Cabeça a Cabeça (Individual)
                  </label>
                </div>
              </div>
              <span class="text-muted small" id="modoEntradaDescricao">Distribui média de peso e gera brincos sequenciais por lote.</span>
            </div>

            <!-- MODO A: LOTE AUTOMÁTICO -->
            <div id="boxModoAutomatico">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Prefixo do Brinco</label>
                  <input type="text" name="prefixo_brinco" id="prefixo_brinco" class="form-control text-uppercase" placeholder="Ex: C-" value="C-">
                  <small class="text-muted" style="font-size:0.7rem;">Ex: C-001, C-002...</small>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Raça Predominante</label>
                  <input type="text" name="raca_animais" id="raca_animais_auto" class="form-control" value="Nelore">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Sexo do Lote</label>
                  <select name="sexo_animais" id="sexo_animais_auto" class="form-select">
                    <option value="M">Macho</option>
                    <option value="F">Fêmea</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- MODO B: ROMANEIO CABEÇA A CABEÇA -->
            <div id="boxModoIndividual" style="display: none;">
              <!-- Barra de Produtividade & Ações em Massa -->
              <div class="card bg-light border mb-2">
                <div class="card-body p-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="small fw-bold text-secondary"><i class="bi bi-magic me-1"></i>Em Massa:</span>
                    
                    <!-- Preencher Raça -->
                    <div class="input-group input-group-sm" style="width: auto;">
                      <select id="massaRacaSelect" class="form-select form-select-sm" style="max-width: 140px;">
                        <option value="Nelore">Nelore</option>
                        <option value="Angus">Angus</option>
                        <option value="Cruzamento Industrial">Cruz. Ind.</option>
                        <option value="Senepol">Senepol</option>
                        <option value="Brahman">Brahman</option>
                        <option value="Brangus">Brangus</option>
                        <option value="Caracu">Caracu</option>
                        <option value="Girolando">Girolando</option>
                        <option value="Mestiço">Mestiço</option>
                      </select>
                      <button type="button" class="btn btn-outline-secondary" onclick="aplicarRacaEmMassa()" title="Aplica a todos os animais">Raça</button>
                    </div>

                    <!-- Preencher Sexo -->
                    <div class="input-group input-group-sm" style="width: auto;">
                      <select id="massaSexoSelect" class="form-select form-select-sm" style="max-width: 100px;">
                        <option value="M">Macho</option>
                        <option value="F">Fêmea</option>
                      </select>
                      <button type="button" class="btn btn-outline-secondary" onclick="aplicarSexoEmMassa()" title="Aplica a todos os animais">Sexo</button>
                    </div>

                    <!-- Distribuir Peso Total -->
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="distribuirPesoTotalEmMassa()" title="Rateia o peso total da nota entre os animais">
                      <i class="bi bi-distribute-vertical me-1"></i>Ratear Peso
                    </button>

                    <!-- Colar Lista de Brincos -->
                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="abrirModalColarBrincos()">
                      <i class="bi bi-clipboard-plus me-1"></i>Colar Brincos
                    </button>
                  </div>

                  <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-success py-1" onclick="adicionarLinhaRomaneio()">
                      <i class="bi bi-plus-circle me-1"></i>Adicionar Boi
                    </button>
                  </div>
                </div>
              </div>

              <!-- Status e Indicador de Balanço do Romaneio -->
              <div class="d-flex align-items-center justify-content-between p-2 mb-2 bg-white rounded border small flex-wrap gap-2">
                <div>
                  <span class="text-muted">Total no Romaneio:</span>
                  <strong id="badgeRomaneioContador" class="text-dark tabular-nums">0 cabeças</strong>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <div>
                    <span class="text-muted">Soma dos Pesos:</span>
                    <strong id="badgeRomaneioSomaPeso" class="tabular-nums text-primary">0,0 kg</strong>
                  </div>
                  <span class="text-muted">•</span>
                  <div>
                    <span class="text-muted">Peso na Nota:</span>
                    <span id="badgeRomaneioPesoNota" class="tabular-nums">0,0 kg</span>
                  </div>
                  <span id="badgeRomaneioDifPeso" class="badge bg-secondary tabular-nums">Dif: 0,0 kg</span>
                </div>
              </div>

              <!-- Tabela do Romaneio -->
              <div class="table-responsive border rounded" style="max-height: 420px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0 romaneio-table" id="tabelaRomaneio">
                  <thead class="table-light sticky-top">
                    <tr>
                      <th style="width: 35px;" class="text-center">#</th>
                      <th style="min-width: 150px;">Brinco / Identificador *</th>
                      <th style="min-width: 140px;">Raça</th>
                      <th style="min-width: 95px;">Sexo</th>
                      <th style="min-width: 120px;" class="text-end">Peso Indiv. (kg)</th>
                      <th style="min-width: 130px;" class="text-end">Custo Indiv. (R$)</th>
                      <th style="width: 40px;" class="text-center"></th>
                    </tr>
                  </thead>
                  <tbody id="tbodyRomaneio">
                    <!-- Linhas dinâmicas -->
                  </tbody>
                </table>
              </div>
              <small class="text-muted mt-1 d-block" style="font-size: 0.72rem;">
                * Dica: Se o peso individual de algum animal for deixado em branco, o sistema utilizará a média proporcional do lote.
              </small>
            </div>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- Coluna Lateral: Resumo de Médias & Ações (Sticky Sidebar) -->
    <div class="col-lg-4">
      <div class="sticky-sidebar">

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
        <div class="card shadow-sm">
          <div class="card-header">
            <h6 class="mb-0"><?= $isEdit ? 'Salvar Alterações' : 'Finalizar Operação' ?></h6>
          </div>
          <div class="card-body">
            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-primary btn-lg py-2 fw-bold">
                <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Salvar e Atualizar Compra' : 'Confirmar e Salvar Compra' ?>
              </button>
              <a href="/compras" class="btn btn-secondary">Cancelar</a>
            </div>
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

<!-- Modal Colar Lista de Brincos -->
<div class="modal fade" id="modalColarBrincos" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold text-dark"><i class="bi bi-clipboard-data text-primary me-2"></i>Colar Lista de Brincos</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-2">
          Cole abaixo os números dos brincos (um por linha, ou separados por vírgula/espaço), copiados de bastão eletrônico RFID, leitor ou planilha Excel:
        </p>
        <textarea id="textareaColarBrincos" class="form-control font-monospace text-uppercase" rows="8" placeholder="Exemplo:&#10;BR-1001&#10;BR-1002&#10;BR-1003&#10;..."></textarea>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary" onclick="processarBrincosColados()">
          <i class="bi bi-check2-circle me-1"></i>Importar para o Romaneio
        </button>
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

  atualizarBalancoRomaneio();
}

function toggleAnimaisCompra(cb) {
  const box = document.getElementById('boxEntradaAnimais');
  if (box) box.style.display = cb.checked ? 'block' : 'none';
  if (cb.checked) {
    const isIndiv = document.getElementById('modoEntradaIndiv')?.checked;
    if (isIndiv) {
      sincronizarRomaneioComQtdCabecas();
    }
  }
}

// ── ROMANEIO CABEÇA A CABEÇA (ENTRADA INDIVIDUAL) ──
function alternarModoEntradaAnimais(modo) {
  const boxAuto = document.getElementById('boxModoAutomatico');
  const boxIndiv = document.getElementById('boxModoIndividual');
  const desc = document.getElementById('modoEntradaDescricao');

  if (modo === 'individual') {
    if (boxAuto) boxAuto.style.display = 'none';
    if (boxIndiv) boxIndiv.style.display = 'block';
    if (desc) desc.textContent = 'Permite conferir e definir brinco, raça, sexo e peso exato de cada boi.';
    sincronizarRomaneioComQtdCabecas();
  } else {
    if (boxAuto) boxAuto.style.display = 'block';
    if (boxIndiv) boxIndiv.style.display = 'none';
    if (desc) desc.textContent = 'Distribui média de peso e gera brincos sequenciais por lote.';
  }
}

let romaneioIndexSeq = 0;

function sincronizarRomaneioComQtdCabecas() {
  const qtdDesejada = parseInt(document.getElementById('compra_qtd')?.value) || 1;
  const tbody = document.getElementById('tbodyRomaneio');
  if (!tbody) return;

  const linhasAtuais = tbody.querySelectorAll('tr').length;
  if (linhasAtuais === 0) {
    const prefixo = document.getElementById('prefixo_brinco')?.value?.trim() || 'C-';
    const racaPadrao = document.getElementById('raca_animais_auto')?.value?.trim() || 'Nelore';
    const sexoPadrao = document.getElementById('sexo_animais_auto')?.value || 'M';
    const pesoTotal = parseFloat(document.getElementById('compra_peso')?.value) || 0;
    const valorTotal = parseFloat(document.getElementById('compra_valor')?.value) || 0;
    const pesoMedio = qtdDesejada > 0 && pesoTotal > 0 ? (pesoTotal / qtdDesejada).toFixed(1) : '';
    const valorMedio = qtdDesejada > 0 && valorTotal > 0 ? (valorTotal / qtdDesejada).toFixed(2) : '';

    for (let i = 1; i <= qtdDesejada; i++) {
      const brincoSugerido = prefixo + String(i).padStart(3, '0');
      adicionarLinhaRomaneio({
        brinco: brincoSugerido,
        raca: racaPadrao,
        sexo: sexoPadrao,
        peso: pesoMedio,
        valor: valorMedio
      });
    }
  } else if (linhasAtuais < qtdDesejada) {
    const prefixo = document.getElementById('prefixo_brinco')?.value?.trim() || 'C-';
    for (let i = linhasAtuais + 1; i <= qtdDesejada; i++) {
      adicionarLinhaRomaneio({
        brinco: prefixo + String(i).padStart(3, '0')
      });
    }
  }
  atualizarBalancoRomaneio();
}

function adicionarLinhaRomaneio(dados = {}) {
  const tbody = document.getElementById('tbodyRomaneio');
  if (!tbody) return;

  romaneioIndexSeq++;
  const idx = romaneioIndexSeq;
  const numLinha = tbody.querySelectorAll('tr').length + 1;

  const brinco = dados.brinco || '';
  const raca = dados.raca || document.getElementById('massaRacaSelect')?.value || 'Nelore';
  const sexo = dados.sexo || document.getElementById('massaSexoSelect')?.value || 'M';
  const peso = dados.peso !== undefined ? dados.peso : '';
  const valor = dados.valor !== undefined ? dados.valor : '';

  const tr = document.createElement('tr');
  tr.id = `linhaRomaneio_${idx}`;
  tr.innerHTML = `
    <td class="text-center text-muted tabular-nums romaneio-num">${numLinha}</td>
    <td>
      <input type="text" name="animais_individuais[${idx}][brinco]" class="form-control form-control-sm romaneio-input romaneio-brinco font-monospace text-uppercase fw-600" value="${escapeHtml(brinco)}" placeholder="Ex: BR-1001" required autocomplete="off">
    </td>
    <td>
      <select name="animais_individuais[${idx}][raca]" class="form-select form-select-sm romaneio-input romaneio-raca">
        <option value="Nelore" ${raca === 'Nelore' ? 'selected' : ''}>Nelore</option>
        <option value="Angus" ${raca === 'Angus' ? 'selected' : ''}>Angus</option>
        <option value="Cruzamento Industrial" ${raca === 'Cruzamento Industrial' ? 'selected' : ''}>Cruzamento Industrial</option>
        <option value="Senepol" ${raca === 'Senepol' ? 'selected' : ''}>Senepol</option>
        <option value="Brahman" ${raca === 'Brahman' ? 'selected' : ''}>Brahman</option>
        <option value="Brangus" ${raca === 'Brangus' ? 'selected' : ''}>Brangus</option>
        <option value="Caracu" ${raca === 'Caracu' ? 'selected' : ''}>Caracu</option>
        <option value="Girolando" ${raca === 'Girolando' ? 'selected' : ''}>Girolando</option>
        <option value="Mestiço" ${raca === 'Mestiço' ? 'selected' : ''}>Mestiço</option>
      </select>
    </td>
    <td>
      <select name="animais_individuais[${idx}][sexo]" class="form-select form-select-sm romaneio-input romaneio-sexo">
        <option value="M" ${sexo === 'M' ? 'selected' : ''}>Macho</option>
        <option value="F" ${sexo === 'F' ? 'selected' : ''}>Fêmea</option>
      </select>
    </td>
    <td>
      <input type="number" step="0.1" name="animais_individuais[${idx}][peso]" class="form-control form-control-sm romaneio-input romaneio-peso text-end tabular-nums" value="${peso}" placeholder="0,0" oninput="atualizarBalancoRomaneio()">
    </td>
    <td>
      <input type="number" step="0.01" name="animais_individuais[${idx}][valor]" class="form-control form-control-sm romaneio-input romaneio-valor text-end tabular-nums" value="${valor}" placeholder="0,00">
    </td>
    <td class="text-center">
      <button type="button" class="btn btn-sm btn-outline-danger p-0 px-1" onclick="removerLinhaRomaneio(this)" title="Remover este boi">
        <i class="bi bi-x"></i>
      </button>
    </td>
  `;

  tbody.appendChild(tr);
  renumerarRomaneio();
  atualizarBalancoRomaneio();
}

function removerLinhaRomaneio(btn) {
  const tr = btn.closest('tr');
  if (tr) {
    tr.remove();
    renumerarRomaneio();
    atualizarBalancoRomaneio();
  }
}

function renumerarRomaneio() {
  const tbody = document.getElementById('tbodyRomaneio');
  if (!tbody) return;
  const linhas = tbody.querySelectorAll('tr');
  linhas.forEach((linha, i) => {
    const tdNum = linha.querySelector('.romaneio-num');
    if (tdNum) tdNum.textContent = i + 1;
  });
  const contador = document.getElementById('badgeRomaneioContador');
  if (contador) contador.textContent = `${linhas.length} cabeças`;
}

function atualizarBalancoRomaneio() {
  const tbody = document.getElementById('tbodyRomaneio');
  if (!tbody) return;

  let somaPesos = 0;
  const pesosInputs = tbody.querySelectorAll('.romaneio-peso');
  pesosInputs.forEach(input => {
    const val = parseFloat(input.value);
    if (!isNaN(val) && val > 0) {
      somaPesos += val;
    }
  });

  const pesoNota = parseFloat(document.getElementById('compra_peso')?.value) || 0;
  const dif = somaPesos - pesoNota;

  const elSoma = document.getElementById('badgeRomaneioSomaPeso');
  const elNota = document.getElementById('badgeRomaneioPesoNota');
  const elDif = document.getElementById('badgeRomaneioDifPeso');

  if (elSoma) elSoma.textContent = somaPesos.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' kg';
  if (elNota) elNota.textContent = pesoNota.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' kg';

  if (elDif) {
    if (pesoNota === 0 || somaPesos === 0) {
      elDif.className = 'badge bg-secondary tabular-nums';
      elDif.textContent = 'Dif: 0,0 kg';
    } else if (Math.abs(dif) < 0.05) {
      elDif.className = 'badge bg-success tabular-nums';
      elDif.textContent = 'Balanço 100% Exato';
    } else if (dif > 0) {
      elDif.className = 'badge bg-warning text-dark tabular-nums';
      elDif.textContent = '+' + dif.toFixed(1) + ' kg acima da NF';
    } else {
      elDif.className = 'badge bg-info text-dark tabular-nums';
      elDif.textContent = dif.toFixed(1) + ' kg abaixo da NF';
    }
  }
}

function aplicarRacaEmMassa() {
  const raca = document.getElementById('massaRacaSelect')?.value || 'Nelore';
  document.querySelectorAll('.romaneio-raca').forEach(sel => sel.value = raca);
}

function aplicarSexoEmMassa() {
  const sexo = document.getElementById('massaSexoSelect')?.value || 'M';
  document.querySelectorAll('.romaneio-sexo').forEach(sel => sel.value = sexo);
}

function distribuirPesoTotalEmMassa() {
  const pesoNota = parseFloat(document.getElementById('compra_peso')?.value) || 0;
  const linhas = document.querySelectorAll('.romaneio-peso');
  if (linhas.length > 0 && pesoNota > 0) {
    const pesoPorCab = (pesoNota / linhas.length).toFixed(1);
    linhas.forEach(input => input.value = pesoPorCab);
    atualizarBalancoRomaneio();
  }
}

function abrirModalColarBrincos() {
  const modalEl = document.getElementById('modalColarBrincos');
  if (modalEl) {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }
}

function processarBrincosColados() {
  const text = document.getElementById('textareaColarBrincos')?.value || '';
  if (!text.trim()) return;

  const brincos = text.split(/[\r\n,;\t]+/).map(b => b.trim()).filter(b => b.length > 0);
  if (!brincos.length) return;

  const tbody = document.getElementById('tbodyRomaneio');
  const linhasExistentes = tbody.querySelectorAll('tr');

  brincos.forEach((brinco, idx) => {
    if (idx < linhasExistentes.length) {
      const input = linhasExistentes[idx].querySelector('.romaneio-brinco');
      if (input) input.value = brinco.toUpperCase();
    } else {
      adicionarLinhaRomaneio({ brinco: brinco.toUpperCase() });
    }
  });

  const modalEl = document.getElementById('modalColarBrincos');
  if (modalEl) {
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();
  }
}

document.addEventListener('DOMContentLoaded', () => {
  calcularMediasCompra();
  
  // Reage à alteração de quantidade no formulário para ajustar romaneio
  document.getElementById('compra_qtd')?.addEventListener('input', () => {
    if (document.getElementById('modoEntradaIndiv')?.checked && document.getElementById('checkCadastrarAnimais')?.checked) {
      sincronizarRomaneioComQtdCabecas();
    }
  });
});
</script>
