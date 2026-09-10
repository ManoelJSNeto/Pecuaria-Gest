<?php
if (!isset($db)) { $db = getDb(); }

$isEdit = isset($venda) && !empty($venda['id']);
$vendaId = $isEdit ? (int)$venda['id'] : 0;
$actionUrl = $isEdit ? "/vendas/{$vendaId}/atualizar" : "/vendas/salvar";
$animaisVendaIds = $animaisVendaIds ?? [];
$preAnimalId = (int)($_GET['animal_id'] ?? 0);

if (!isset($animaisDisponiveis)) {
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

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="/vendas" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Voltar para Vendas
    </a>
    <?php if ($isEdit): ?>
      <span class="badge bg-light text-primary border px-2 py-1">
        <i class="bi bi-pencil-square me-1"></i> Modo Edição • Venda #<?= $vendaId ?>
      </span>
    <?php endif; ?>
  </div>

  <?php if ($isEdit): ?>
    <div class="d-flex align-items-center gap-2">
      <a href="/vendas/<?= $vendaId ?>/pdf" target="_blank" class="btn btn-sm btn-outline-success">
        <i class="bi bi-file-earmark-pdf-fill me-1"></i> Comprovante PDF
      </a>
      <?php if (!empty($venda['arquivo_xml'])): ?>
        <a href="/vendas/<?= $vendaId ?>/nfe" target="_blank" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-receipt-cutoff me-1"></i> Ver NF-e Completa
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<form method="POST" action="<?= $actionUrl ?>" enctype="multipart/form-data" id="formVenda">
  <?= csrf_field() ?>

  <!-- 1. BLOCO SUPERIOR: Importação de XML e Painel de Conferência (Largura Total) -->
  <div class="mb-4">
    <!-- Informação de XML existente em modo de Edição -->
    <?php if ($isEdit && !empty($venda['arquivo_xml'])): ?>
      <div class="p-3 mb-3 bg-white rounded border d-flex justify-content-between align-items-center flex-wrap gap-2 shadow-sm">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle p-2 bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
            <i class="bi bi-file-earmark-check-fill fs-5"></i>
          </div>
          <div>
            <div class="fw-bold text-dark">Nota Fiscal de Saída / Abate Anexada</div>
            <div class="text-muted small">
              <?php if (!empty($venda['chave_nfe'])): ?>
                Chave: <span class="font-monospace text-dark"><?= substr($venda['chave_nfe'], 0, 10) ?>...<?= substr($venda['chave_nfe'], -6) ?></span> •
              <?php endif; ?>
              Comprador: <strong><?= e($venda['comprador_destino'] ?: 'Não informado') ?></strong>
            </div>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <a href="/vendas/<?= $vendaId ?>/nfe" target="_blank" class="btn btn-sm btn-outline-success">
            <i class="bi bi-eye me-1"></i> Ver Detalhes da NF-e
          </a>
          <a href="<?= e($venda['arquivo_xml']) ?>" download class="btn btn-sm btn-secondary">
            <i class="bi bi-download me-1"></i> Baixar XML
          </a>
        </div>
      </div>
    <?php endif; ?>

    <!-- Zona de Importação Inteligente de XML da NF-e -->
    <div class="xml-import-zone" id="dropZoneXmlVenda" onclick="document.getElementById('inputXmlVenda').click()">
      <input type="file" name="arquivo_xml" id="inputXmlVenda" accept=".xml,text/xml" style="display: none;" onchange="handleXmlSelectVenda(this)">
      <i class="bi bi-file-earmark-arrow-up xml-import-icon"></i>
      <h6 class="fw-bold mb-1 text-dark"><?= $isEdit ? 'Substituir ou Reimportar XML da NF-e' : 'Importar XML da Nota Fiscal de Venda / Abate' ?></h6>
      <p class="small text-muted mb-0">
        Selecione o arquivo <strong>.xml</strong> emitido pelo frigorífico ou comprador para conferência e preenchimento automático.
      </p>
      <div id="xmlFeedbackVenda" style="display: none;" class="xml-badge-success justify-content-center mt-2">
        <i class="bi bi-check-circle-fill text-success fs-6"></i>
        <span id="xmlFeedbackVendaText">XML lido e validado com sucesso!</span>
      </div>
    </div>

    <!-- Painel Dinâmico & Dimensionado de Pré-visualização de Itens da NF-e de Venda -->
    <div id="painelItensXmlVenda" style="display: none;" class="nfe-preview-panel active mt-3">
      <div class="nfe-preview-header">
        <div class="nfe-preview-title">
          <i class="bi bi-receipt-cutoff text-success fs-5"></i>
          <div>
            <h6 id="xmlVendaCabecalho">Nota Fiscal de Saída Carregada</h6>
            <small class="text-muted" id="xmlVendaSubcabecalho">Conferência dos itens discriminados na NF-e</small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-light border py-1 px-2" onclick="abrirModalNfeVendaCompleta()" title="Ver todos os detalhes da nota fiscal original">
            <i class="bi bi-eye me-1"></i> Ver NF-e Completa
          </button>
          <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2" onclick="limparXmlVendaImportado()" title="Descartar XML">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </div>

      <!-- Chips de Metadados -->
      <div class="nfe-meta-chips" id="xmlVendaMetaChips"></div>

      <!-- Alerta com InfCpl / GTA -->
      <div id="xmlVendaInfCplAlert" style="display: none;" class="p-2 px-3 bg-light border-bottom small d-flex align-items-start gap-2">
        <i class="bi bi-info-circle text-primary mt-1"></i>
        <div class="flex-grow-1">
          <strong class="text-secondary">Observações da NF-e:</strong>
          <span id="xmlVendaInfCplText" class="font-monospace text-dark ms-1"></span>
        </div>
      </div>

      <!-- Tabela Compacta de Itens -->
      <div class="nfe-table-container">
        <table class="nfe-table" id="tabelaItensVendaXml">
          <thead>
            <tr>
              <th style="width: 36px;" class="text-center" title="Incluir item no lote de saída">Inc.</th>
              <th>Produto / Descrição na NF-e</th>
              <th style="width: 90px;" class="text-end">Qtd</th>
              <th style="width: 110px;" class="text-end">Valor Unit.</th>
              <th style="width: 115px;" class="text-end">Total Item</th>
              <th style="width: 32px;" class="text-center"></th>
            </tr>
          </thead>
          <tbody id="tbodyItensVendaXml"></tbody>
        </table>
      </div>

      <!-- Barra de Totais -->
      <div class="nfe-summary-footer">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <button type="button" class="btn btn-sm btn-light border py-0 px-2" onclick="selecionarTodosItensVendaXml(true)">Marcar Todos</button>
          <button type="button" class="btn btn-sm btn-light border py-0 px-2" onclick="selecionarTodosItensVendaXml(false)">Desmarcar</button>
          <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="adicionarItemManualVendaXml()">
            <i class="bi bi-plus-lg me-1"></i> Adicionar Item
          </button>
          <span class="text-muted small ms-1" id="xmlVendaStatusIgnorados"></span>
        </div>

        <div class="d-flex align-items-center gap-3">
          <div class="text-end">
            <small class="text-muted d-block" style="font-size:0.7rem;">Faturamento dos Itens:</small>
            <strong class="tabular-nums text-success fs-6" id="xmlVendaSomaValor">R$ 0,00</strong>
          </div>
          <button type="button" class="btn btn-sm btn-success py-1 px-3" onclick="aplicarItensXmlAoFormularioVenda()">
            <i class="bi bi-arrow-repeat me-1"></i> Aplicar Valores
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- 2. GRID PRINCIPAL: Formulário Detalhado (8) + Resumo & Ações (4) -->
  <div class="row g-3">
    <!-- Coluna Principal: Formulário e Seleção de Gado -->
    <div class="col-lg-8">

      <!-- Seção 1: Chaves Mestras de Saída -->
      <div class="form-section">
        <div class="form-section-title">
          <i class="bi bi-file-earmark-text text-success"></i> Identificação & Documentação da Saída
        </div>

        <div class="row g-3">
          <!-- Descrição / Identificação da Saída em DESTAQUE no topo da seção -->
          <div class="col-12">
            <label class="form-label fw-bold text-dark">
              Descrição da NF-e / Identificação da Saída *
            </label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-card-text text-success"></i></span>
              <input type="text" name="descricao" id="venda_descricao" class="form-control fw-600" placeholder="Ex: Embarque de 20 bois gordos para abate" autocomplete="off" required value="<?= e($venda['descricao'] ?? '') ?>">
            </div>
            <small class="text-muted" style="font-size:0.72rem;">Preenchido automaticamente a partir da NF-e ou digitado manualmente.</small>
          </div>

          <div class="col-md-7">
            <label class="form-label">Comprador / Frigorífico de Destino *</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-building"></i></span>
              <input type="text" name="comprador_destino" id="venda_comprador" class="form-control" placeholder="Ex: Frigorífico JBS, Minerva, Fazenda São José..." required autocomplete="off" value="<?= e($venda['comprador_destino'] ?? '') ?>">
            </div>
          </div>

          <div class="col-md-5">
            <label class="form-label">Data do Embarque / Saída *</label>
            <input type="date" name="data_venda" id="venda_data" class="form-control" value="<?= e($venda['data_venda'] ?? date('Y-m-d')) ?>" required>
          </div>

          <div class="col-md-7">
            <label class="form-label">Chave de Acesso da NF-e (44 dígitos)</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-receipt"></i></span>
              <input type="text" name="chave_nfe" id="venda_chave_nfe" class="form-control tabular-nums text-uppercase font-monospace" placeholder="35260900000000000000550010000000002000000000" maxlength="44" autocomplete="off" value="<?= e($venda['chave_nfe'] ?? '') ?>">
            </div>
            <small class="text-muted" style="font-size:0.72rem;">Preenchida automaticamente ao anexar o XML acima.</small>
          </div>

          <div class="col-md-5">
            <label class="form-label">Número / Série da GTA de Saída *</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-file-earmark-medical text-success"></i></span>
              <input type="text" name="numero_gta" id="venda_gta" class="form-control" placeholder="Ex: 987654/2026" required autocomplete="off" value="<?= e($venda['numero_gta'] ?? '') ?>">
            </div>
            <small class="text-muted" style="font-size:0.72rem;">Guia de Trânsito Animal emitida para o embarque ou abate.</small>
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

          <div class="col-12">
            <label class="form-label">Valor Total da Venda (R$) *</label>
            <div class="input-group">
              <span class="input-group-text fw-bold">R$</span>
              <input type="number" step="0.01" name="valor_total" id="venda_valor_total" class="form-control tabular-nums fw-bold fs-5 text-success" placeholder="0,00" required value="<?= !empty($venda['valor_total']) ? number_format((float)$venda['valor_total'], 2, '.', '') : '' ?>" oninput="atualizarCalculoVenda()">
            </div>
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

    <!-- Coluna Lateral: Resumo do Embarque & Confirmação (Sticky Sidebar) -->
    <div class="col-lg-4">
      <div class="sticky-sidebar">

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
        <div class="card shadow-sm">
          <div class="card-header">
            <h6 class="mb-0"><?= $isEdit ? 'Salvar Alterações' : 'Finalizar Baixa de Venda' ?></h6>
          </div>
          <div class="card-body">
            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-primary btn-lg py-2 fw-bold">
                <i class="bi bi-check2-circle me-1"></i> <?= $isEdit ? 'Salvar Alterações na Venda' : 'Confirmar Venda e Dar Baixa' ?>
              </button>
              <a href="/vendas" class="btn btn-secondary">Cancelar</a>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</form>

<!-- Modal com Detalhes Completos da NF-e de Venda -->
<div class="modal fade" id="modalNfeVendaCompleta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2 bg-light">
        <h6 class="modal-title fw-bold text-dark" id="modalNfeVendaTitulo">
          <i class="bi bi-receipt-cutoff text-success me-1"></i> Detalhes da NF-e de Venda / Abate
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3">
        <div class="mb-3">
          <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size:0.7rem;">Chave de Acesso da NF-e</small>
          <div class="nfe-key-display">
            <span id="modalNfeVendaChave" class="tabular-nums"></span>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="copiarTextoModal('modalNfeVendaChave')">
              <i class="bi bi-clipboard me-1"></i> Copiar Chave
            </button>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <div class="p-3 bg-light rounded border h-100">
              <small class="text-secondary fw-bold text-uppercase d-block mb-1" style="font-size:0.7rem;">Emitente / Origem</small>
              <strong id="modalNfeVendaEmitNome" class="text-dark d-block"></strong>
              <div id="modalNfeVendaEmitDoc" class="small text-muted mt-1 font-monospace"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 bg-light rounded border h-100">
              <small class="text-secondary fw-bold text-uppercase d-block mb-1" style="font-size:0.7rem;">Destinatário / Frigorífico</small>
              <strong id="modalNfeVendaDestNome" class="text-dark d-block"></strong>
              <div id="modalNfeVendaDestDoc" class="small text-muted mt-1 font-monospace"></div>
            </div>
          </div>
        </div>

        <h6 class="fw-bold mb-2 text-dark" style="font-size:0.88rem;">Itens Discriminados na NF-e</h6>
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
            <tbody id="modalNfeVendaTbodyItens"></tbody>
          </table>
        </div>

        <div id="modalNfeVendaInfCplBox" class="p-2 px-3 bg-light rounded border small mb-3">
          <strong class="text-dark d-block mb-1">Informações Complementares da NF-e:</strong>
          <span id="modalNfeVendaInfCpl" class="font-monospace text-secondary"></span>
        </div>

        <div>
          <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#modalNfeVendaCollapseXml">
            <i class="bi bi-code-slash me-1"></i> Visualizar Estrutura XML Bruta
          </button>
          <div class="collapse mt-2" id="modalNfeVendaCollapseXml">
            <pre class="p-3 bg-dark text-light rounded small" style="max-height: 250px; overflow: auto; font-size:0.72rem;" id="modalNfeVendaXmlPre"></pre>
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
let nfeVendaAtualObjeto = null;
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

      // 2. Número e Série
      const numNfe = xmlDoc.getElementsByTagName('nNF')[0]?.textContent?.trim() || 'S/N';
      const serieNfe = xmlDoc.getElementsByTagName('serie')[0]?.textContent?.trim() || '1';

      // 3. Data de Emissão
      let dataEmi = '';
      const dhEmi = xmlDoc.getElementsByTagName('dhEmi')[0] || xmlDoc.getElementsByTagName('dEmi')[0];
      if (dhEmi && dhEmi.textContent) {
        dataEmi = dhEmi.textContent.substr(0, 10);
        document.getElementById('venda_data').value = dataEmi;
      }

      // 4. Comprador / Destinatário
      let compradorNome = '';
      let compradorDoc = '';
      const dest = xmlDoc.getElementsByTagName('dest')[0];
      if (dest) {
        compradorNome = dest.getElementsByTagName('xNome')[0]?.textContent?.trim() || '';
        compradorDoc = dest.getElementsByTagName('CNPJ')[0]?.textContent?.trim() ||
                       dest.getElementsByTagName('CPF')[0]?.textContent?.trim() || '';
      }
      if (compradorNome) {
        document.getElementById('venda_comprador').value = compradorNome;
      }

      // 5. Emitente
      let emitNome = '';
      let emitDoc = '';
      const emit = xmlDoc.getElementsByTagName('emit')[0];
      if (emit) {
        emitNome = emit.getElementsByTagName('xNome')[0]?.textContent?.trim() || '';
        emitDoc = emit.getElementsByTagName('CNPJ')[0]?.textContent?.trim() ||
                  emit.getElementsByTagName('CPF')[0]?.textContent?.trim() || '';
      }

      // 6. Peso Balança
      const pesoB = xmlDoc.getElementsByTagName('pesoB')[0] || xmlDoc.getElementsByTagName('pesoL')[0];
      if (pesoB && pesoB.textContent) {
        const pesoKg = parseFloat(pesoB.textContent) || 0;
        if (pesoKg > 0) {
          const inputPeso = document.getElementById('venda_peso_total');
          inputPeso.value = pesoKg.toFixed(1);
          inputPeso.dataset.manual = 'true';
        }
      }

      // 7. infCpl / GTA
      let infCplText = xmlDoc.getElementsByTagName('infCpl')[0]?.textContent?.trim() || '';
      let gtaDetectada = '';
      if (infCplText) {
        document.getElementById('xmlVendaInfCplText').textContent = infCplText;
        document.getElementById('xmlVendaInfCplAlert').style.display = 'flex';
        const matchGta = infCplText.match(/gta\s*[:#ºn\.\-]?\s*([0-9a-zA-Z\/\.\-]+)/i);
        if (matchGta && matchGta[1] && !document.getElementById('venda_gta').value) {
          gtaDetectada = matchGta[1].replace(/[\.\,]+$/, '');
          document.getElementById('venda_gta').value = gtaDetectada;
        }
      } else {
        document.getElementById('xmlVendaInfCplAlert').style.display = 'none';
      }

      // 8. Itens
      itensXmlVendaCarregados = [];
      const dets = xmlDoc.getElementsByTagName('det');
      for (let i = 0; i < dets.length; i++) {
        const det = dets[i];
        const prod = det.getElementsByTagName('prod')[0];
        if (!prod) continue;

        const cProd = prod.getElementsByTagName('cProd')[0]?.textContent?.trim() || `ITEM-${i+1}`;
        const xProd = prod.getElementsByTagName('xProd')[0]?.textContent?.trim() || 'BOVINO PARA ABATE';
        const ncm = prod.getElementsByTagName('NCM')[0]?.textContent?.trim() || '';
        const uCom = (prod.getElementsByTagName('uCom')[0]?.textContent?.trim() || 'CAB').toUpperCase();
        const qCom = parseFloat(prod.getElementsByTagName('qCom')[0]?.textContent) || 1;
        const vUnCom = parseFloat(prod.getElementsByTagName('vUnCom')[0]?.textContent) || 0;
        const vProd = parseFloat(prod.getElementsByTagName('vProd')[0]?.textContent) || (qCom * vUnCom);

        const xLower = xProd.toLowerCase();
        const naoEhGado = xLower.includes('frete') || xLower.includes('transporte') ||
                          xLower.includes('servico') || xLower.includes('serviço') ||
                          xLower.includes('pedagio');

        itensXmlVendaCarregados.push({
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

      nfeVendaAtualObjeto = {
        chave: chave,
        numero: numNfe,
        serie: serieNfe,
        dataEmissao: dataEmi,
        emitente: { nome: emitNome, doc: emitDoc },
        destinatario: { nome: compradorNome, doc: compradorDoc },
        infCpl: infCplText,
        itens: itensXmlVendaCarregados,
        rawXml: xmlText
      };

      const chipsEl = document.getElementById('xmlVendaMetaChips');
      chipsEl.innerHTML = `
        <span class="nfe-chip"><i class="bi bi-receipt text-success"></i> NF-e: <strong>${escapeHtml(numNfe)}</strong></span>
        <span class="nfe-chip"><i class="bi bi-tag text-muted"></i> Série: <strong>${escapeHtml(serieNfe)}</strong></span>
        <span class="nfe-chip"><i class="bi bi-calendar-event text-muted"></i> Emissão: <strong>${escapeHtml(dataEmi || 'Hoje')}</strong></span>
        <span class="nfe-chip"><i class="bi bi-truck text-muted"></i> Comprador: <strong>${escapeHtml(compradorNome || 'Não informado')}</strong></span>
        ${gtaDetectada ? `<span class="nfe-chip text-success border-success"><i class="bi bi-shield-check text-success"></i> GTA: <strong>${escapeHtml(gtaDetectada)}</strong></span>` : ''}
      `;

      document.getElementById('xmlVendaCabecalho').textContent = `NF-e Saída nº ${numNfe} — Série ${serieNfe}`;
      document.getElementById('xmlVendaSubcabecalho').textContent = `${compradorNome || 'Comprador'} • ${itensXmlVendaCarregados.length} item(ns) discriminado(s)`;

      renderTabelaItensXmlVenda();
      aplicarItensXmlAoFormularioVenda();

      document.getElementById('painelItensXmlVenda').style.display = 'block';
      const feedback = document.getElementById('xmlFeedbackVenda');
      const text = document.getElementById('xmlFeedbackVendaText');
      feedback.style.display = 'flex';
      text.textContent = `XML processado com sucesso: ${file.name} (${itensXmlVendaCarregados.length} itens extraídos)`;

      const descInput = document.getElementById('venda_descricao');
      if (!descInput.value && itensXmlVendaCarregados.length > 0) {
        const primeiro = itensXmlVendaCarregados.find(it => it.incluir) || itensXmlVendaCarregados[0];
        descInput.value = `Venda NF ${numNfe} - ${primeiro.descricao.substr(0, 32)}`;
      }

    } catch (err) {
      alert('Erro ao processar o arquivo XML. Verifique se é uma NF-e válida.');
      console.error(err);
    }
  };
  reader.readAsText(file);
}

function renderTabelaItensXmlVenda() {
  const tbody = document.getElementById('tbodyItensXmlVenda');
  tbody.innerHTML = '';

  let somaValor = 0;
  let ignoradosCount = 0;

  itensXmlVendaCarregados.forEach((item, idx) => {
    if (item.incluir) {
      somaValor += (item.quantidade * item.valorUnitario);
    } else {
      ignoradosCount++;
    }

    const tr = document.createElement('tr');
    if (!item.incluir) tr.classList.add('row-ignored');

    tr.innerHTML = `
      <td class="text-center">
        <input type="checkbox" class="form-check-input" ${item.incluir ? 'checked' : ''} onchange="toggleItemVendaXml(${idx}, this.checked)">
      </td>
      <td>
        <div class="d-flex align-items-center gap-1">
          <span class="badge bg-light text-secondary border font-monospace" style="font-size:0.68rem;" title="Código do produto">${escapeHtml(item.codigo)}</span>
          <span class="badge bg-light text-dark border" style="font-size:0.68rem;">${escapeHtml(item.unidade)}</span>
          <input type="text" class="nfe-cell-input fw-600 flex-grow-1" value="${escapeHtml(item.descricao)}" onchange="editarItemVendaXml(${idx}, 'descricao', this.value)">
        </div>
        ${!item.incluir ? '<span class="badge bg-light text-muted border mt-1" style="font-size:0.65rem;">Ignorado na venda (Frete / Insumo)</span>' : ''}
      </td>
      <td>
        <input type="number" step="0.1" min="0" class="nfe-cell-input text-end tabular-nums" value="${item.quantidade}" oninput="editarItemVendaXml(${idx}, 'quantidade', parseFloat(this.value) || 0)">
      </td>
      <td>
        <input type="number" step="0.01" min="0" class="nfe-cell-input text-end tabular-nums" value="${item.valorUnitario.toFixed(2)}" oninput="editarItemVendaXml(${idx}, 'valorUnitario', parseFloat(this.value) || 0)">
      </td>
      <td class="text-end tabular-nums fw-bold ${item.incluir ? 'text-success' : 'text-muted'}">
        R$ ${(item.quantidade * item.valorUnitario).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-link text-danger p-0 border-0" onclick="removerItemVendaXml(${idx})" title="Remover item">
          <i class="bi bi-x-circle"></i>
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  });

  document.getElementById('xmlVendaSomaValor').textContent = 'R$ ' + somaValor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  const statusIgn = document.getElementById('xmlVendaStatusIgnorados');
  if (ignoradosCount > 0) {
    statusIgn.textContent = `(${ignoradosCount} item(ns) não-gado desmarcado(s))`;
  } else {
    statusIgn.textContent = '';
  }
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
    item: itensXmlVendaCarregados.length + 1,
    incluir: true,
    codigo: 'NOVO',
    descricao: 'BOVINOS PARA ABATE',
    ncm: '01022990',
    unidade: 'CAB',
    quantidade: 1,
    valorUnitario: 0,
    valorTotal: 0,
    isGado: true
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
    nfeVendaAtualObjeto = null;
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

function abrirModalNfeVendaCompleta() {
  if (!nfeVendaAtualObjeto) return;
  
  document.getElementById('modalNfeVendaTitulo').innerHTML = `<i class="bi bi-receipt-cutoff text-success me-1"></i> NF-e de Saída nº ${escapeHtml(nfeVendaAtualObjeto.numero)} (Série ${escapeHtml(nfeVendaAtualObjeto.serie)})`;
  document.getElementById('modalNfeVendaChave').textContent = nfeVendaAtualObjeto.chave ? nfeVendaAtualObjeto.chave.replace(/(.{4})/g, '$1 ').trim() : 'NÃO INFORMADA';
  document.getElementById('modalNfeVendaEmitNome').textContent = nfeVendaAtualObjeto.emitente.nome || 'PECUÁRIA GEST';
  document.getElementById('modalNfeVendaEmitDoc').textContent = nfeVendaAtualObjeto.emitente.doc ? `CNPJ/CPF: ${nfeVendaAtualObjeto.emitente.doc}` : '';
  document.getElementById('modalNfeVendaDestNome').textContent = nfeVendaAtualObjeto.destinatario.nome || 'Não informado';
  document.getElementById('modalNfeVendaDestDoc').textContent = nfeVendaAtualObjeto.destinatario.doc ? `CNPJ/CPF: ${nfeVendaAtualObjeto.destinatario.doc}` : '';
  
  const infCplBox = document.getElementById('modalNfeVendaInfCplBox');
  if (nfeVendaAtualObjeto.infCpl) {
    document.getElementById('modalNfeVendaInfCpl').textContent = nfeVendaAtualObjeto.infCpl;
    infCplBox.style.display = 'block';
  } else {
    infCplBox.style.display = 'none';
  }

  const tbody = document.getElementById('modalNfeVendaTbodyItens');
  tbody.innerHTML = '';
  nfeVendaAtualObjeto.itens.forEach((it, i) => {
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

  document.getElementById('modalNfeVendaXmlPre').textContent = nfeVendaAtualObjeto.rawXml || '';

  const modal = new bootstrap.Modal(document.getElementById('modalNfeVendaCompleta'));
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
