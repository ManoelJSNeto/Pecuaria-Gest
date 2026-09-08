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

<div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="card">
      <div class="card-header">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-cash-coin text-success fs-5"></i>
          <h5 class="mb-0 fw-bold">Registrar Venda / Saída de Gado</h5>
        </div>
      </div>
      <div class="card-body">
        <form method="POST" action="/vendas/salvar" id="formVenda">
          <?= csrf_field() ?>

          <!-- Bloco 1: Chaves Mestras de Saída (GTA e NF-e) -->
          <div class="p-3 mb-3 rounded border" style="background-color: var(--bg-subtle);">
            <h6 class="fw-bold mb-2 text-success d-flex align-items-center gap-2">
              <i class="bi bi-shield-check text-success"></i> Chaves Mestras de Saída
            </h6>
            <p class="small text-muted mb-3">
              Informe os dados fiscais e a GTA emitida para dar baixa sanitária automática no rebanho ativo.
            </p>

            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label small fw-bold">Número da GTA de Saída *</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text"><i class="bi bi-file-earmark-medical"></i></span>
                  <input type="text" name="numero_gta" class="form-control" placeholder="Ex: 987654/2026" required autocomplete="off">
                </div>
                <small class="text-muted" style="font-size:0.75rem;">Guia de Trânsito Animal emitida para o transporte/abate.</small>
              </div>

              <div class="col-md-6">
                <label class="form-label small fw-bold">Chave de Acesso da NF-e (44 dígitos)</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text"><i class="bi bi-receipt"></i></span>
                  <input type="text" name="chave_nfe" class="form-control text-uppercase tabular-nums" placeholder="Ex: 35260900000000000000550010000000002000000000" maxlength="44" autocomplete="off">
                </div>
                <small class="text-muted" style="font-size:0.75rem;">Nota Fiscal de Produtor ou emitente do frigorífico.</small>
              </div>

              <div class="col-md-8 mt-2">
                <label class="form-label small fw-bold">Comprador / Frigorífico de Destino *</label>
                <input type="text" name="comprador_destino" class="form-control form-control-sm" placeholder="Ex: Frigorífico JBS, Frigorífico Minerva, Fazenda São José..." required autocomplete="off">
              </div>

              <div class="col-md-4 mt-2">
                <label class="form-label small fw-bold">Data do Embarque / Saída *</label>
                <input type="date" name="data_venda" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
              </div>
            </div>
          </div>

          <!-- Bloco 2: Precificação Comercial & Balança -->
          <div class="p-3 mb-3 rounded border">
            <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
              <i class="bi bi-tag-fill text-primary"></i> Negociação Comercial & Balança
            </h6>

            <div class="row g-3 align-items-end">
              <div class="col-md-4">
                <label class="form-label small fw-bold">Forma de Precificação</label>
                <select name="tipo_precificacao" id="tipo_precificacao" class="form-select form-select-sm" onchange="atualizarCalculoVenda()">
                  <option value="arroba">Por Arroba (R$/@) — Frigorífico</option>
                  <option value="peso_vivo_kg">Por Quilo Vivo (R$/kg)</option>
                  <option value="cabeca">Por Cabeça (R$/cab)</option>
                  <option value="total_fixo">Valor Fechado do Lote</option>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label small fw-bold" id="label_preco_unit">Preço por Arroba (R$/@) *</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text">R$</span>
                  <input type="number" step="0.01" name="preco_unitario" id="venda_preco_unit" class="form-control" placeholder="Ex: 245.00" oninput="atualizarCalculoVenda()">
                </div>
              </div>

              <div class="col-md-4">
                <label class="form-label small fw-bold">Peso Total de Embarque (kg)</label>
                <div class="input-group input-group-sm">
                  <input type="number" step="0.1" name="peso_total_kg" id="venda_peso_total" class="form-control" placeholder="0.0" oninput="atualizarCalculoVenda()">
                  <span class="input-group-text">kg</span>
                </div>
              </div>

              <div class="col-md-6 mt-2">
                <label class="form-label small fw-bold">Valor Total da Venda (R$) *</label>
                <div class="input-group">
                  <span class="input-group-text fw-bold">R$</span>
                  <input type="number" step="0.01" name="valor_total" id="venda_valor_total" class="form-control fw-bold text-success fs-5" placeholder="0,00" required>
                </div>
              </div>

              <div class="col-md-6 mt-2">
                <label class="form-label small fw-bold">Descrição / Lote da Venda</label>
                <input type="text" name="descricao" class="form-control" placeholder="Ex: Embarque de 20 bois gordos para abate" autocomplete="off">
              </div>

              <!-- Painel Dinâmico de Arrobas e Médias -->
              <div class="col-12 mt-2">
                <div class="p-2 rounded bg-light border d-flex justify-content-between flex-wrap gap-2 small text-secondary">
                  <span>Animais selecionados: <strong id="resumo_qtd_selecionada" class="text-primary">0 cab</strong></span>
                  <span>Volume em Arrobas: <strong id="resumo_arr_total" class="text-dark">0,0 @</strong></span>
                  <span>Valor médio por cabeça: <strong id="resumo_media_cab" class="text-success">R$ 0,00</strong></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Bloco 3: Seleção dos Animais do Embarque -->
          <div class="p-3 mb-3 rounded border">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
              <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-check2-square text-primary"></i> Selecionar Animais que Estão Saindo (<?= count($animaisDisponiveis) ?> disponíveis)
              </h6>
              <div class="d-flex align-items-center gap-2">
                <select id="filtroPastoVenda" class="form-select form-select-sm" style="max-width: 180px;" onchange="filtrarTabelaAnimais(this.value)">
                  <option value="">Todos os Pastos</option>
                  <?php foreach ($pastos as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= e($p['nome']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="marcarTodosVisiveis(true)">Marcar Todos</button>
                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="marcarTodosVisiveis(false)">Desmarcar</button>
              </div>
            </div>

            <div class="table-responsive" style="max-height: 280px; overflow-y: auto; border: 1px solid var(--border-subtle); border-radius: var(--radius-sm);">
              <table class="table table-sm table-hover mb-0" id="tabelaAnimaisVenda">
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
                      <td class="small text-secondary"><?= e($a['pasto_nome'] ?: 'Sem pasto') ?></td>
                      <td class="text-end small tabular-nums fw-600">
                        <?= $pKg > 0 ? number_format($pKg, 1, ',', '.') . ' kg' : '—' ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <small class="text-muted d-block mt-2" style="font-size:0.75rem;">
              <i class="bi bi-info-circle me-1"></i> Os animais selecionados terão o status alterado para <strong>Vendido</strong> e serão desvinculados dos pastos automaticamente.
            </small>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-3">
            <a href="/vendas" class="btn btn-outline-secondary btn-sm">Cancelar</a>
            <button type="submit" class="btn btn-success btn-sm px-4 fw-bold">
              <i class="bi bi-check2-circle me-1"></i> Confirmar Venda e Dar Baixa
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function atualizarSelecaoAnimais() {
  const checkboxes = document.querySelectorAll('.check-animal-venda:checked');
  let qtd = checkboxes.length;
  let somaPeso = 0;

  checkboxes.forEach(cb => {
    const tr = cb.closest('tr');
    const peso = parseFloat(tr.getAttribute('data-peso')) || 0;
    somaPeso += peso;
  });

  // Se o usuário ainda não digitou peso manual na balança, preenche com a soma estimada dos animais
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
  const arrobas = (pesoTotal * 0.5) / 15; // 30kg PV = 1 @

  // Atualiza label de acordo com o tipo
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

// Inicialização se veio pré-selecionado
document.addEventListener('DOMContentLoaded', () => {
  atualizarSelecaoAnimais();
});
</script>
