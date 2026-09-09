<?php
$nfe = $nfe ?? null;
$hasXml = !empty($nfe) && !empty($nfe['chave']);
$chaveFormatada = $hasXml ? chunk_split($nfe['chave'], 4, ' ') : ($v['chave_nfe'] ? chunk_split($v['chave_nfe'], 4, ' ') : 'NÃO INFORMADA');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="/vendas" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Voltar para Vendas
    </a>
    <a href="/vendas/<?= $v['id'] ?>/editar" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-pencil me-1"></i> Editar Venda
    </a>
  </div>
  <div class="d-flex align-items-center gap-2">
    <?php if (!empty($v['arquivo_xml'])): ?>
      <a href="<?= e($v['arquivo_xml']) ?>" download class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-download me-1"></i> Baixar XML Original
      </a>
    <?php endif; ?>
    <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
      <i class="bi bi-printer me-1"></i> Imprimir / Salvar PDF
    </button>
  </div>
</div>

<div class="nfe-danfe-card mb-4">
  <!-- Cabeçalho Fiscal -->
  <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3 flex-wrap gap-3">
    <div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success-subtle text-success border px-2 py-1" style="font-size:0.75rem;">
          <i class="bi bi-shield-check me-1"></i> Documento Fiscal da SEFAZ
        </span>
        <span class="badge bg-light text-secondary border" style="font-size:0.75rem;">
          Modelo 55 (NF-e de Saída / Abate)
        </span>
      </div>
      <h4 class="mt-2 mb-1 fw-bold text-dark">
        NF-e nº <?= e($nfe['numero'] ?? 'S/N') ?> — Série <?= e($nfe['serie'] ?? '1') ?>
      </h4>
      <div class="text-muted small">
        Natureza da Operação: <strong><?= e($nfe['natureza'] ?? 'REMESSA / VENDA DE GADO PARA ABATE') ?></strong>
        <?php if (!empty($nfe['data_emissao'])): ?>
          • Emissão: <strong><?= formatDate(substr($nfe['data_emissao'], 0, 10)) ?></strong>
        <?php endif; ?>
      </div>
    </div>

    <div class="text-end">
      <div class="small text-muted">Registro Interno de Saída:</div>
      <div class="fs-5 fw-bold text-success">Venda #<?= $v['id'] ?></div>
      <span class="badge bg-light text-dark border">
        <i class="bi bi-file-earmark-medical text-success me-1"></i> GTA de Saída: <?= e($v['numero_gta'] ?: ($nfe['gta_detectada'] ?? '—')) ?>
      </span>
    </div>
  </div>

  <!-- Chave de Acesso -->
  <div class="mb-4">
    <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size:0.7rem;">
      Chave de Acesso da NF-e (44 dígitos)
    </small>
    <div class="nfe-key-display">
      <span id="txtChaveNfeVenda"><?= trim($chaveFormatada) ?></span>
      <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="copiarChaveNfeVenda()" id="btnCopiarChaveVenda" title="Copiar chave de acesso">
        <i class="bi bi-clipboard me-1"></i> Copiar
      </button>
    </div>
  </div>

  <!-- Dados do Emitente & Destinatário -->
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="p-3 bg-light rounded border h-100">
        <div class="small fw-bold text-uppercase text-secondary mb-2" style="font-size:0.72rem;">
          <i class="bi bi-house-door me-1"></i> Emitente / Origem do Rebanho
        </div>
        <strong class="d-block text-dark fs-6"><?= e($nfe['emitente']['nome'] ?? 'PECUÁRIA GEST AGROPECUÁRIA') ?></strong>
        <div class="small mt-2 text-secondary">
          <?php if (!empty($nfe['emitente']['doc'])): ?>
            CNPJ/CPF: <span class="tabular-nums fw-600"><?= e($nfe['emitente']['doc']) ?></span><br>
          <?php endif; ?>
          Data de Saída: <span class="tabular-nums"><?= formatDate($v['data_venda']) ?></span>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="p-3 bg-light rounded border h-100">
        <div class="small fw-bold text-uppercase text-secondary mb-2" style="font-size:0.72rem;">
          <i class="bi bi-truck me-1"></i> Comprador / Frigorífico de Destino
        </div>
        <strong class="d-block text-dark fs-6"><?= e($nfe['destinatario']['nome'] ?? ($v['comprador_destino'] ?: 'Não informado')) ?></strong>
        <div class="small mt-2 text-secondary">
          <?php if (!empty($nfe['destinatario']['doc'])): ?>
            CNPJ/CPF: <span class="tabular-nums fw-600"><?= e($nfe['destinatario']['doc']) ?></span><br>
          <?php endif; ?>
          <?php if (!empty($nfe['destinatario']['mun'])): ?>
            Município/UF: <?= e($nfe['destinatario']['mun']) ?> / <?= e($nfe['destinatario']['uf']) ?><br>
          <?php endif; ?>
          Tipo de Negociação: <span class="badge bg-light text-dark border text-uppercase"><?= e($v['tipo_precificacao']) ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Totais e Indicadores Financeiros da Venda -->
  <div class="row g-2 mb-4 text-center">
    <div class="col-sm-3">
      <div class="p-2 rounded border bg-white">
        <small class="text-muted d-block" style="font-size:0.72rem;">Animais Faturados</small>
        <div class="fs-5 fw-bold tabular-nums text-dark"><?= (int)$v['quantidade_cabecas'] ?> cab</div>
      </div>
    </div>
    <div class="col-sm-3">
      <div class="p-2 rounded border bg-white">
        <small class="text-muted d-block" style="font-size:0.72rem;">Peso de Embarque</small>
        <div class="fs-5 fw-bold tabular-nums text-dark">
          <?= (float)$v['peso_total_kg'] > 0 ? number_format((float)$v['peso_total_kg'], 1, ',', '.') . ' kg' : '—' ?>
        </div>
      </div>
    </div>
    <div class="col-sm-3">
      <div class="p-2 rounded border bg-white">
        <small class="text-muted d-block" style="font-size:0.72rem;">Preço Praticado</small>
        <div class="fs-5 fw-bold tabular-nums" style="color:var(--earth-green-800);">
          <?= (float)$v['preco_unitario'] > 0 ? 'R$ ' . number_format((float)$v['preco_unitario'], 2, ',', '.') : '—' ?>
        </div>
      </div>
    </div>
    <div class="col-sm-3">
      <div class="p-2 rounded border" style="background:var(--earth-green-50);">
        <small class="text-muted d-block text-uppercase fw-bold" style="font-size:0.7rem;">Faturamento Total</small>
        <div class="fs-5 fw-bold tabular-nums" style="color:var(--earth-green-950);">
          R$ <?= number_format((float)$v['valor_total'], 2, ',', '.') ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Informações Complementares / Fisco -->
  <?php if (!empty($nfe['inf_complementar'])): ?>
    <div class="alert alert-light border mb-4 p-3 small">
      <div class="fw-bold text-dark mb-1">
        <i class="bi bi-info-circle text-primary me-1"></i> Informações Complementares da NF-e (infCpl):
      </div>
      <div class="text-secondary font-monospace" style="font-size:0.8rem; line-height:1.5;">
        <?= nl2br(e($nfe['inf_complementar'])) ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Tabela Discriminada de Itens da NF-e -->
  <div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="fw-bold mb-0 text-dark">
        <i class="bi bi-list-ol text-success me-1"></i> Itens e Produtos Faturados no XML
      </h6>
      <span class="text-muted small">
        <?= !empty($nfe['itens']) ? count($nfe['itens']) : 0 ?> item(ns) na nota
      </span>
    </div>

    <?php if (!empty($nfe['itens'])): ?>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle">
          <thead class="table-light small">
            <tr>
              <th style="width: 40px;" class="text-center">#</th>
              <th style="width: 90px;">Código</th>
              <th>Descrição do Produto / Lote</th>
              <th style="width: 80px;">NCM</th>
              <th style="width: 60px;" class="text-center">Un.</th>
              <th style="width: 90px;" class="text-end">Quantidade</th>
              <th style="width: 120px;" class="text-end">Valor Unit. (R$)</th>
              <th style="width: 130px;" class="text-end">Valor Total (R$)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($nfe['itens'] as $it): ?>
              <tr>
                <td class="text-center small tabular-nums text-muted"><?= $it['item'] ?></td>
                <td class="small font-monospace"><?= e($it['codigo']) ?></td>
                <td>
                  <strong class="text-dark d-block"><?= e($it['descricao']) ?></strong>
                </td>
                <td class="small text-muted font-monospace"><?= e($it['ncm'] ?: '—') ?></td>
                <td class="text-center small"><span class="badge bg-light text-dark border"><?= e($it['unidade']) ?></span></td>
                <td class="text-end tabular-nums fw-600"><?= number_format($it['quantidade'], 1, ',', '.') ?></td>
                <td class="text-end tabular-nums small">R$ <?= number_format($it['valor_unitario'], 2, ',', '.') ?></td>
                <td class="text-end tabular-nums fw-bold text-success">
                  R$ <?= number_format($it['valor_total'], 2, ',', '.') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light small fw-bold">
            <tr>
              <td colspan="5" class="text-end">Soma dos Itens da NF-e:</td>
              <td class="text-end tabular-nums">
                <?= number_format(array_sum(array_column($nfe['itens'], 'quantidade')), 1, ',', '.') ?>
              </td>
              <td></td>
              <td class="text-end tabular-nums text-success">
                R$ <?= number_format(array_sum(array_column($nfe['itens'], 'valor_total')), 2, ',', '.') ?>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php else: ?>
      <div class="p-4 bg-light rounded border text-center text-muted">
        <i class="bi bi-file-earmark-x fs-2 d-block mb-2 opacity-50"></i>
        Nenhum detalhe de itens foi encontrado no arquivo XML anexado ou a venda foi registrada manualmente.
      </div>
    <?php endif; ?>
  </div>

  <!-- Código XML Bruto da NF-e (Retrátil) -->
  <?php if (!empty($nfe['raw_xml'])): ?>
    <div class="mt-4 pt-3 border-top">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#boxRawXmlVenda">
          <i class="bi bi-code-slash me-1"></i> Exibir Código XML Original da SEFAZ
        </button>
        <button type="button" class="btn btn-sm btn-light border" onclick="copiarXmlVenda()" id="btnCopiarXmlVenda">
          <i class="bi bi-clipboard me-1"></i> Copiar XML Completo
        </button>
      </div>

      <div class="collapse mt-2" id="boxRawXmlVenda">
        <pre class="p-3 bg-dark text-light rounded small" style="max-height: 400px; overflow: auto; font-size:0.75rem;" id="xmlVendaContentPre"><code><?= htmlspecialchars($nfe['raw_xml']) ?></code></pre>
      </div>
    </div>
  <?php endif; ?>

</div>

<script>
function copiarChaveNfeVenda() {
  const chave = document.getElementById('txtChaveNfeVenda').textContent.replace(/\s+/g, '');
  navigator.clipboard.writeText(chave).then(() => {
    const btn = document.getElementById('btnCopiarChaveVenda');
    btn.innerHTML = '<i class="bi bi-check2 text-success me-1"></i> Copiado!';
    setTimeout(() => {
      btn.innerHTML = '<i class="bi bi-clipboard me-1"></i> Copiar';
    }, 2000);
  });
}

function copiarXmlVenda() {
  const code = document.getElementById('xmlVendaContentPre')?.textContent || '';
  navigator.clipboard.writeText(code).then(() => {
    const btn = document.getElementById('btnCopiarXmlVenda');
    btn.innerHTML = '<i class="bi bi-check2 text-success me-1"></i> XML Copiado!';
    setTimeout(() => {
      btn.innerHTML = '<i class="bi bi-clipboard me-1"></i> Copiar XML Completo';
    }, 2000);
  });
}
</script>
