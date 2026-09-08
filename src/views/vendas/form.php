<?php
if (!isset($db)) { $db = getDb(); }

$preAnimalId = (int)($_GET['animal_id'] ?? 0);

// Animais ativos e vivos disponíveis para venda
$animaisStmt = $db->query("
    SELECT a.id, a.brinco, a.nome, a.sexo, a.raca, a.pasto_id, p.nome as pasto_nome,
           (SELECT peso FROM pesagens WHERE animal_id = a.id ORDER BY data DESC, id DESC LIMIT 1) as ultimo_peso,
           a.peso_inicial
    FROM animais a
    LEFT JOIN pastagens p ON a.pasto_id = p.id
    WHERE a.status NOT IN ('vendido', 'morto')
    ORDER BY p.nome NULLS LAST, a.brinco ASC
");
$animaisDisponiveis = $animaisStmt->fetchAll();

$pastos = $db->query("SELECT id, nome FROM pastagens WHERE status='ativa' ORDER BY nome")->fetchAll();
?>

<div class="mb-3">
  <a href="/vendas" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar para Vendas
  </a>
</div>

<form method="POST" action="/vendas/salvar" enctype="multipart/form-data" id="formVenda">
  <?= csrf_field() ?>

  <div class="row g-3">
    <!-- Coluna Principal: Formulário e Seleção de Gado -->
    <div class="col-lg-8">

      <!-- Zona de Importação Inteligente de XML da NF-e -->
      <div class="xml-import-zone" id="dropZoneXmlVenda" onclick="document.getElementById('inputXmlVenda').click()">
        <input type="file" name="arquivo_xml" id="inputXmlVenda" accept=".xml,text/xml" style="display: none;" onchange="handleXmlSelectVenda(this)">
        <i class="bi bi-file-earmark-arrow-up xml-import-icon"></i>
        <h6 class="fw-bold mb-1 text-dark">Importar XML da Nota Fiscal de Venda / Abate</h6>
        <p class="small text-muted mb-0">
          Selecione o arquivo <strong>.xml</strong> emitido pelo frigorífico ou produtor para preencher comprador, chave e valores automaticamente.
        </p>
        <div id="xmlFeedbackVenda" style="display: none;" class="xml-badge-success justify-content-center">
          <i class="bi bi-check-circle-fill text-success fs-6"></i>
          <span id="xmlFeedbackVendaText">XML lido e validado com sucesso!</span>
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
              <input type="text" name="numero_gta" id="venda_gta" class="form-control" placeholder="Ex: 987654/2026" required autocomplete="off">
            </div>
            <small class="text-muted" style="font-size:0.72rem;">Guia de Trânsito Animal emitida para o embarque ou abate.</small>
          </div>

          <div class="col-md-6">
            <label class="form-label">Chave de Acesso da NF-e (44 dígitos)</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-receipt"></i></span>
              <input type="text" name="chave_nfe" id="venda_chave_nfe" class="form-control tabular-nums text-uppercase" placeholder="35260900000000000000550010000000002000000000" maxlength="44" autocomplete="off">
            </div>
            <small class="text-muted" style="font-size:0.72rem;">Preenchida automaticamente ao anexar o XML acima.</small>
          </div>

          <div class="col-md-8">
            <label class="form-label">Comprador / Frigorífico de Destino *</label>
            <input type="text" name="comprador_destino" id="venda_comprador" class="form-control" placeholder="Ex: Frigorífico JBS, Minerva, Fazenda São José..." required autocomplete="off">
          </div>

          <div class="col-md-4">
            <label class="form-label">Data do Embarque / Saída *</label>
            <input type="date" name="data_venda" id="venda_data" class="form-control" value="<?= date('Y-m-d') ?>" required>
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
              <option value="arroba">Por Arroba (R$/@) — Frigorífico</option>
              <option value="peso_vivo_kg">Por Quilo Vivo (R$/kg)</option>
              <option value="cabeca">Por Cabeça (R$/cab)</option>
              <option value="total_fixo">Valor Fechado do Lote</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label" id="label_preco_unit">Preço por Arroba (R$/@) *</label>
            <div class="input-group">
              <span class="input-group-text">R$</span>
              <input type="number" step="0.01" name="preco_unitario" id="venda_preco_unit" class="form-control tabular-nums" placeholder="Ex: 245.00" oninput="atualizarCalculoVenda()">
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Peso Total de Embarque (kg)</label>
            <div class="input-group">
              <input type="number" step="0.1" name="peso_total_kg" id="venda_peso_total" class="form-control tabular-nums" placeholder="0.0" oninput="atualizarCalculoVenda()">
              <span class="input-group-text">kg</span>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Valor Total da Venda (R$) *</label>
            <div class="input-group">
              <span class="input-group-text fw-bold">R$</span>
              <input type="number" step="0.01" name="valor_total" id="venda_valor_total" class="form-control tabular-nums fw-bold fs-5" style="color: var(--earth-green-900);" placeholder="0,00" required>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Descrição / Identificação da Saída</label>
            <input type="text" name="descricao" class="form-control" placeholder="Ex: Embarque de 20 bois gordos para abate" autocomplete="off">
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
                  $pKg = (float)($a['ultimo_peso'] ?: ($a['peso_inicial'] ?: 0));
                  $isChecked = ($preAnimalId > 0 && $a['id'] == $preAnimalId);
                ?>
                <tr class="linha-animal" data-pasto="<?= $a['pasto_id'] ?: 0 ?>" data-peso="<?= $pKg ?>">
                  <td class="text-center">
                    <input type="checkbox" name="animais_ids[]" value="<?= $a['id'] ?>" class="form-check-input check-animal-venda" <?= $isChecked ? 'checked' : '' ?> onchange="atualizarSelecaoAnimais()">
                  </td>
                  <td>
                    <strong class="text-primary"><?= e($a['brinco']) ?></strong>
                    <?php if ($a['nome']): ?><small class="text-muted ms-1">(<?= e($a['nome']) ?>)</small><?php endif; ?>
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
          <h6 class="mb-0">Finalizar Baixa de Venda</h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check2-circle me-1"></i> Confirmar Venda e Dar Baixa
            </button>
            <a href="/vendas" class="btn btn-secondary">Cancelar</a>
          </div>
        </div>
      </div>

    </div>
  </div>
</form>

<script>
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

      // 2. Data
      let dataEmi = '';
      const dhEmi = xmlDoc.getElementsByTagName('dhEmi')[0] || xmlDoc.getElementsByTagName('dEmi')[0];
      if (dhEmi && dhEmi.textContent) {
        dataEmi = dhEmi.textContent.substr(0, 10);
        document.getElementById('venda_data').value = dataEmi;
      }

      // 3. Comprador / Destinatário
      const dest = xmlDoc.getElementsByTagName('dest')[0];
      if (dest) {
        const xNome = dest.getElementsByTagName('xNome')[0];
        if (xNome && xNome.textContent) {
          document.getElementById('venda_comprador').value = xNome.textContent.trim();
        }
      }

      // 4. Valor Total
      const vNF = xmlDoc.getElementsByTagName('vNF')[0] || xmlDoc.getElementsByTagName('vProd')[0];
      if (vNF && vNF.textContent) {
        const val = parseFloat(vNF.textContent) || 0;
        if (val > 0) {
          document.getElementById('venda_valor_total').value = val.toFixed(2);
        }
      }

      // 5. Peso Bruto se houver
      const pesoB = xmlDoc.getElementsByTagName('pesoB')[0] || xmlDoc.getElementsByTagName('pesoL')[0];
      if (pesoB && pesoB.textContent) {
        const pesoKg = parseFloat(pesoB.textContent) || 0;
        if (pesoKg > 0) document.getElementById('venda_peso_total').value = pesoKg.toFixed(1);
      }

      // Feedback visual
      const feedback = document.getElementById('xmlFeedbackVenda');
      const text = document.getElementById('xmlFeedbackVendaText');
      feedback.style.display = 'flex';
      text.textContent = `XML carregado: ${file.name} (Dados preenchidos!)`;

      atualizarCalculoVenda();
    } catch (err) {
      alert('Erro ao processar o arquivo XML.');
    }
  };
  reader.readAsText(file);
}

// Drag & drop no dropzone de vendas
const dropZoneVenda = document.getElementById('dropZoneXmlVenda');
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

function atualizarSelecaoAnimais() {
  const checkboxes = document.querySelectorAll('.check-animal-venda:checked');
  let qtd = checkboxes.length;
  let somaPeso = 0;

  checkboxes.forEach(cb => {
    const tr = cb.closest('tr');
    const peso = parseFloat(tr.getAttribute('data-peso')) || 0;
    somaPeso += peso;
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

  let valorTotal = 0;
  if (tipo === 'arroba') {
    valorTotal = arrobas * precoUnit;
  } else if (tipo === 'peso_vivo_kg') {
    valorTotal = pesoTotal * precoUnit;
  } else if (tipo === 'cabeca') {
    valorTotal = qtd * precoUnit;
  }

  const inputTotal = document.getElementById('venda_valor_total');
  if (tipo !== 'total_fixo' && valorTotal > 0) {
    inputTotal.value = valorTotal.toFixed(2);
  }

  const finalTotal = parseFloat(inputTotal.value) || valorTotal;
  const mediaCab = qtd > 0 ? (finalTotal / qtd) : 0;

  document.getElementById('resumo_qtd_selecionada').textContent = qtd + ' cab';
  document.getElementById('resumo_arr_total').textContent = arrobas.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' @';
  document.getElementById('resumo_media_cab').textContent = 'R$ ' + mediaCab.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  document.getElementById('resumo_total_destaque').textContent = 'R$ ' + finalTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
