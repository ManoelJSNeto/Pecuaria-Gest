<?php
if (!isset($db)) { $db = getDb(); }
$pastos = $db->query("SELECT id, nome, capacidade FROM pastagens WHERE status='ativa' ORDER BY nome")->fetchAll();
?>

<div class="mb-3">
  <a href="/compras" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar para Compras
  </a>
</div>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-truck text-primary fs-5"></i>
          <h5 class="mb-0 fw-bold">Registrar Compra / Entrada de Gado</h5>
        </div>
      </div>
      <div class="card-body">
        <form method="POST" action="/compras/salvar" id="formCompra">
          <?= csrf_field() ?>

          <!-- Bloco 1: Chaves Mestras & Documentação Fiscal/Sanitária -->
          <div class="p-3 mb-3 rounded border" style="background-color: var(--bg-subtle);">
            <h6 class="fw-bold mb-2 text-primary d-flex align-items-center gap-2">
              <i class="bi bi-shield-check text-success"></i> Chaves Mestras (Documentação Obrigatória)
            </h6>
            <p class="small text-muted mb-3">
              Insira as chaves oficiais para garantir rastreabilidade sanitária e fiscal sem precisar digitar endereços ou cadastros longos.
            </p>

            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label small fw-bold">Número / Série da GTA *</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text"><i class="bi bi-file-earmark-medical"></i></span>
                  <input type="text" name="numero_gta" class="form-control" placeholder="Ex: 123456/2026" required autocomplete="off">
                </div>
                <small class="text-muted" style="font-size:0.75rem;">Guia de Trânsito Animal emitida pelo órgão estadual.</small>
              </div>

              <div class="col-md-6">
                <label class="form-label small fw-bold">Chave de Acesso da NF-e (44 dígitos)</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text"><i class="bi bi-receipt"></i></span>
                  <input type="text" name="chave_nfe" id="chave_nfe" class="form-control text-uppercase tabular-nums" placeholder="Ex: 35260900000000000000550010000000001000000000" maxlength="44" autocomplete="off">
                </div>
                <small class="text-muted" style="font-size:0.75rem;">Código de barras da Nota Fiscal Eletrônica.</small>
              </div>

              <div class="col-md-8 mt-2">
                <label class="form-label small fw-bold">Fornecedor / Origem</label>
                <input type="text" name="fornecedor_origem" class="form-control form-control-sm" placeholder="Ex: Fazenda Santa Maria, Leilão Terra Boa..." autocomplete="off">
              </div>

              <div class="col-md-4 mt-2">
                <label class="form-label small fw-bold">Data da Operação / Entrada *</label>
                <input type="date" name="data_compra" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
              </div>
            </div>
          </div>

          <!-- Bloco 2: Métricas do Lote (Qtd, Peso e Valor) -->
          <div class="p-3 mb-3 rounded border">
            <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
              <i class="bi bi-calculator text-secondary"></i> Volume, Peso e Custo da Compra
            </h6>

            <div class="row g-3 align-items-end">
              <div class="col-md-4">
                <label class="form-label small fw-bold">Quantidade de Cabeças *</label>
                <input type="number" name="quantidade_cabecas" id="compra_qtd" class="form-control" min="1" value="10" required oninput="calcularMediasCompra()">
              </div>

              <div class="col-md-4">
                <label class="form-label small fw-bold">Peso Total na Balança (kg)</label>
                <div class="input-group">
                  <input type="number" step="0.1" name="peso_total_kg" id="compra_peso" class="form-control" placeholder="Ex: 3600" oninput="calcularMediasCompra()">
                  <span class="input-group-text small">kg</span>
                </div>
              </div>

              <div class="col-md-4">
                <label class="form-label small fw-bold">Valor Total da Compra (R$) *</label>
                <div class="input-group">
                  <span class="input-group-text small">R$</span>
                  <input type="number" step="0.01" name="valor_total" id="compra_valor" class="form-control fw-bold" placeholder="0,00" required oninput="calcularMediasCompra()">
                </div>
              </div>

              <!-- Painel de Médias Calculadas em Tempo Real -->
              <div class="col-12 mt-2">
                <div class="p-2 rounded bg-light border d-flex justify-content-between flex-wrap gap-2 small text-secondary">
                  <span>Média por cabeça: <strong id="resumo_media_cab" class="text-primary">R$ 0,00</strong></span>
                  <span>Peso médio: <strong id="resumo_peso_cab" class="text-dark">0,0 kg</strong> (<span id="resumo_arr_cab">0,0 @</span>)</span>
                  <span>Custo da Arroba: <strong id="resumo_custo_arr" class="text-success">R$ 0,00/@</strong></span>
                </div>
              </div>

              <div class="col-md-6 mt-3">
                <label class="form-label small fw-bold">Descrição / Identificação do Lote</label>
                <input type="text" name="descricao" class="form-control form-control-sm" placeholder="Ex: Lote de 30 Garrotes Nelore" autocomplete="off">
              </div>

              <div class="col-md-6 mt-3">
                <label class="form-label small fw-bold">Pasto de Destino na Fazenda</label>
                <select name="pasto_destino_id" class="form-select form-select-sm">
                  <option value="">— Não alocar no momento —</option>
                  <?php foreach ($pastos as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= e($p['nome']) ?> (Capacidade: <?= (int)$p['capacidade'] ?> cab)</option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <!-- Bloco 3: Entrada Rápida de Animais (Opcional) -->
          <div class="p-3 mb-3 rounded border" style="background-color: var(--bg-surface);">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="checkCadastrarAnimais" name="cadastrar_animais" value="1" onchange="toggleAnimaisCompra(this)">
              <label class="form-check-label fw-bold small" for="checkCadastrarAnimais">
                <i class="bi bi-tags text-primary me-1"></i> Cadastrar e gerar os brincos individuais deste lote agora
              </label>
            </div>
            <small class="text-muted d-block mb-2">
              Se habilitado, o sistema já adicionará automaticamente as cabeças ao rebanho vinculadas a esta compra e ao pasto selecionado.
            </small>

            <div id="boxEntradaAnimais" style="display: none;" class="pt-2 border-top mt-2">
              <div class="row g-2">
                <div class="col-md-4">
                  <label class="form-label small fw-bold">Prefixo do Brinco</label>
                  <input type="text" name="prefixo_brinco" class="form-control form-control-sm text-uppercase" placeholder="Ex: C-" value="C-">
                  <small class="text-muted" style="font-size:0.7rem;">Ex: C-1, C-2... até a quantidade do lote</small>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold">Raça Predominante</label>
                  <input type="text" name="raca_animais" class="form-control form-control-sm" value="Nelore">
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold">Sexo do Lote</label>
                  <select name="sexo_animais" class="form-select form-select-sm">
                    <option value="M">Macho</option>
                    <option value="F">Fêmea</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-3">
            <a href="/compras" class="btn btn-outline-secondary btn-sm">Cancelar</a>
            <button type="submit" class="btn btn-primary btn-sm px-3">
              <i class="bi bi-check-lg me-1"></i> Confirmar e Salvar Compra
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function calcularMediasCompra() {
  const qtd = parseFloat(document.getElementById('compra_qtd').value) || 0;
  const peso = parseFloat(document.getElementById('compra_peso').value) || 0;
  const valor = parseFloat(document.getElementById('compra_valor').value) || 0;

  const mediaCab = qtd > 0 ? (valor / qtd) : 0;
  const pesoCab = qtd > 0 ? (peso / qtd) : 0;
  const arrobas = (peso * 0.5) / 15; // 1 @ comercial = 30kg peso vivo
  const arrobaCab = qtd > 0 ? (arrobas / qtd) : 0;
  const custoArr = arrobas > 0 ? (valor / arrobas) : 0;

  document.getElementById('resumo_media_cab').textContent = 'R$ ' + mediaCab.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  document.getElementById('resumo_peso_cab').textContent = pesoCab.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' kg';
  document.getElementById('resumo_arr_cab').textContent = arrobaCab.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' @';
  document.getElementById('resumo_custo_arr').textContent = 'R$ ' + custoArr.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '/@';
}

function toggleAnimaisCompra(cb) {
  const box = document.getElementById('boxEntradaAnimais');
  if (box) box.style.display = cb.checked ? 'block' : 'none';
}
</script>
