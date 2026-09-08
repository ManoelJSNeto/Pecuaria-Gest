<?php
if (!isset($db)) { $db = getDb(); }
$pastos = $db->query("SELECT id, nome, capacidade FROM pastagens WHERE status='ativa' ORDER BY nome")->fetchAll();
?>

<div class="mb-3">
  <a href="/compras" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar para Compras
  </a>
</div>

<form method="POST" action="/compras/salvar" enctype="multipart/form-data" id="formCompra">
  <?= csrf_field() ?>

  <div class="row g-3">
    <!-- Coluna Principal: Formulário & Seções Técnicas -->
    <div class="col-lg-8">

      <!-- Zona de Importação Inteligente de XML da NF-e -->
      <div class="xml-import-zone" id="dropZoneXml" onclick="document.getElementById('inputXmlFile').click()">
        <input type="file" name="arquivo_xml" id="inputXmlFile" accept=".xml,text/xml" style="display: none;" onchange="handleXmlSelect(this)">
        <i class="bi bi-file-earmark-arrow-up xml-import-icon"></i>
        <h6 class="fw-bold mb-1 text-dark">Importar Arquivo XML da NF-e</h6>
        <p class="small text-muted mb-0">
          Clique ou arraste o arquivo <strong>.xml</strong> da Nota Fiscal para preencher todos os dados automaticamente.
        </p>
        <div id="xmlFeedback" style="display: none;" class="xml-badge-success justify-content-center">
          <i class="bi bi-check-circle-fill text-success fs-6"></i>
          <span id="xmlFeedbackText">XML lido e validado com sucesso!</span>
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
              <input type="text" name="numero_gta" id="compra_gta" class="form-control" placeholder="Ex: 123456/2026" required autocomplete="off">
            </div>
            <small class="text-muted" style="font-size:0.72rem;">Guia de Trânsito Animal emitida pelo órgão estadual.</small>
          </div>

          <div class="col-md-6">
            <label class="form-label">Chave de Acesso da NF-e (44 dígitos)</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-receipt"></i></span>
              <input type="text" name="chave_nfe" id="compra_chave_nfe" class="form-control tabular-nums text-uppercase" placeholder="35260900000000000000550010000000001000000000" maxlength="44" autocomplete="off">
            </div>
            <small class="text-muted" style="font-size:0.72rem;">Preenchida automaticamente ao importar o XML acima.</small>
          </div>

          <div class="col-md-8">
            <label class="form-label">Fornecedor / Fazenda de Origem</label>
            <input type="text" name="fornecedor_origem" id="compra_fornecedor" class="form-control" placeholder="Ex: Fazenda Santa Maria, Leilão Terra Boa..." autocomplete="off">
          </div>

          <div class="col-md-4">
            <label class="form-label">Data da Operação / Entrada *</label>
            <input type="date" name="data_compra" id="compra_data" class="form-control" value="<?= date('Y-m-d') ?>" required>
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
            <input type="number" name="quantidade_cabecas" id="compra_qtd" class="form-control tabular-nums" min="1" value="10" required oninput="calcularMediasCompra()">
          </div>

          <div class="col-md-4">
            <label class="form-label">Peso Total na Balança (kg)</label>
            <div class="input-group">
              <input type="number" step="0.1" name="peso_total_kg" id="compra_peso" class="form-control tabular-nums" placeholder="Ex: 3600" oninput="calcularMediasCompra()">
              <span class="input-group-text">kg</span>
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Valor Total da Compra (R$) *</label>
            <div class="input-group">
              <span class="input-group-text fw-bold">R$</span>
              <input type="number" step="0.01" name="valor_total" id="compra_valor" class="form-control tabular-nums fw-bold" placeholder="0,00" required oninput="calcularMediasCompra()">
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Descrição / Identificação do Lote</label>
            <input type="text" name="descricao" id="compra_descricao" class="form-control" placeholder="Ex: Lote de 30 Garrotes Nelore" autocomplete="off">
          </div>

          <div class="col-md-6">
            <label class="form-label">Pasto de Destino na Fazenda</label>
            <select name="pasto_destino_id" class="form-select">
              <option value="">— Selecionar Pasto Posteriormente —</option>
              <?php foreach ($pastos as $p): ?>
                <option value="<?= $p['id'] ?>"><?= e($p['nome']) ?> (Capacidade: <?= (int)$p['capacidade'] ?> cab)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Seção 3: Entrada Automática de Brincos no Rebanho -->
      <div class="form-section">
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
          <h6 class="mb-0">Finalizar Operação</h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check-lg me-1"></i> Confirmar e Salvar Compra
            </button>
            <a href="/compras" class="btn btn-secondary">Cancelar</a>
          </div>
        </div>
      </div>

    </div>
  </div>
</form>

<script>
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

      // 2. Data de Emissão
      let dataEmi = '';
      const dhEmi = xmlDoc.getElementsByTagName('dhEmi')[0] || xmlDoc.getElementsByTagName('dEmi')[0];
      if (dhEmi && dhEmi.textContent) {
        dataEmi = dhEmi.textContent.substr(0, 10);
        document.getElementById('compra_data').value = dataEmi;
      }

      // 3. Emitente / Fornecedor
      const emit = xmlDoc.getElementsByTagName('emit')[0];
      if (emit) {
        const xNome = emit.getElementsByTagName('xNome')[0];
        if (xNome && xNome.textContent) {
          document.getElementById('compra_fornecedor').value = xNome.textContent.trim();
        }
      }

      // 4. Valor Total da Nota
      let valorTotal = 0;
      const vNF = xmlDoc.getElementsByTagName('vNF')[0] || xmlDoc.getElementsByTagName('vProd')[0];
      if (vNF && vNF.textContent) {
        valorTotal = parseFloat(vNF.textContent) || 0;
        if (valorTotal > 0) {
          document.getElementById('compra_valor').value = valorTotal.toFixed(2);
        }
      }

      // 5. Quantidade e Peso dos Itens
      let totalQtd = 0;
      const dets = xmlDoc.getElementsByTagName('det');
      for (let i = 0; i < dets.length; i++) {
        const qCom = dets[i].getElementsByTagName('qCom')[0];
        if (qCom && qCom.textContent) {
          totalQtd += parseFloat(qCom.textContent) || 0;
        }
      }
      if (totalQtd > 0) {
        document.getElementById('compra_qtd').value = Math.round(totalQtd);
      }

      // Peso Bruto
      const pesoB = xmlDoc.getElementsByTagName('pesoB')[0] || xmlDoc.getElementsByTagName('pesoL')[0];
      if (pesoB && pesoB.textContent) {
        const pesoKg = parseFloat(pesoB.textContent) || 0;
        if (pesoKg > 0) document.getElementById('compra_peso').value = pesoKg.toFixed(1);
      }

      // Feedback visual
      const feedback = document.getElementById('xmlFeedback');
      const text = document.getElementById('xmlFeedbackText');
      feedback.style.display = 'flex';
      text.textContent = `XML carregado: ${file.name} (Chave e dados preenchidos!)`;

      calcularMediasCompra();
    } catch (err) {
      alert('Erro ao processar o arquivo XML. Verifique se é um XML de NF-e válido.');
    }
  };
  reader.readAsText(file);
}

// Drag & Drop no dropzone
const dropZone = document.getElementById('dropZoneXml');
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

function calcularMediasCompra() {
  const qtd = parseFloat(document.getElementById('compra_qtd').value) || 0;
  const peso = parseFloat(document.getElementById('compra_peso').value) || 0;
  const valor = parseFloat(document.getElementById('compra_valor').value) || 0;

  const mediaCab = qtd > 0 ? (valor / qtd) : 0;
  const pesoCab = qtd > 0 ? (peso / qtd) : 0;
  const arrobas = (peso * 0.5) / 15;
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

document.addEventListener('DOMContentLoaded', () => {
  calcularMediasCompra();
});
</script>
