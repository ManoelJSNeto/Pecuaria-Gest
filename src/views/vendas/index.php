<?php
if (!isset($db)) { $db = getDb(); }

$search = $_GET['q'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
if ($search) {
    $where[] = "(v.descricao LIKE ? OR v.numero_gta LIKE ? OR v.chave_nfe LIKE ? OR v.comprador_destino LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}
$whereStr = implode(' AND ', $where);

// Contagem e lista paginada
$countStmt = $db->prepare("SELECT COUNT(*) FROM vendas v WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stmt = $db->prepare("
    SELECT v.*,
           (SELECT COUNT(*) FROM animais a WHERE a.venda_id = v.id) as animais_vinculados
    FROM vendas v
    WHERE $whereStr
    ORDER BY v.data_venda DESC, v.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$vendas = $stmt->fetchAll();

// Métricas Consolidadas do Cockpit
$metricStmt = $db->query("
    SELECT 
        COALESCE(SUM(valor_total), 0) as faturamento_total,
        COALESCE(SUM(quantidade_cabecas), 0) as total_cabecas,
        COALESCE(SUM(peso_total_kg), 0) as total_kg
    FROM vendas
");
$metrics = $metricStmt->fetch();
$faturamentoTotal = (float)$metrics['faturamento_total'];
$totalCabecas = (int)$metrics['total_cabecas'];
$totalKg = (float)$metrics['total_kg'];

$precoMedioCab = $totalCabecas > 0 ? ($faturamentoTotal / $totalCabecas) : 0.0;
$totalArrobas = kgParaArroba($totalKg);
$precoMedioArroba = $totalArrobas > 0 ? ($faturamentoTotal / $totalArrobas) : 0.0;
?>

<!-- Header Superior da Página -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold">Vendas & Saídas de Gado</h5>
    <small class="text-muted tabular-nums">
      Baixa comercial e sanitária do rebanho com registro de GTA, NF-e/XML e apuração de faturamento
    </small>
  </div>
  <a href="/vendas/novo" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i> Registrar Nova Venda
  </a>
</div>

<!-- Cockpit de Indicadores Unificado -->
<div class="metric-cockpit">
  <div class="metric-cell">
    <span class="metric-label">Faturamento Total</span>
    <div class="metric-value tabular-nums" style="color: var(--earth-green-900);">
      R$ <?= number_format($faturamentoTotal, 2, ',', '.') ?>
    </div>
    <span class="metric-sub">Receita bruta comercial</span>
  </div>

  <div class="metric-cell">
    <span class="metric-label">Cabeças Comercializadas</span>
    <div class="metric-value tabular-nums">
      <?= number_format($totalCabecas, 0, ',', '.') ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">cab</span>
    </div>
    <span class="metric-sub">Média: R$ <?= number_format($precoMedioCab, 2, ',', '.') ?>/cab</span>
  </div>

  <div class="metric-cell">
    <span class="metric-label">Volume de Saída</span>
    <div class="metric-value tabular-nums">
      <?= number_format($totalArrobas, 1, ',', '.') ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">@</span>
    </div>
    <span class="metric-sub"><?= number_format($totalKg, 0, ',', '.') ?> kg abatidos/embarcados</span>
  </div>

  <div class="metric-cell">
    <span class="metric-label">Preço Médio da Arroba</span>
    <div class="metric-value tabular-nums" style="color: var(--earth-green-700);">
      R$ <?= number_format($precoMedioArroba, 2, ',', '.') ?> <span style="font-size:0.85rem;font-weight:600;color:var(--text-muted);">/@</span>
    </div>
    <span class="metric-sub">Cotação média realizada</span>
  </div>
</div>

<!-- Barra de Filtros e Pesquisa -->
<div class="filter-toolbar">
  <form method="GET" action="/vendas" class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 480px;">
    <div class="input-group input-group-sm">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input type="text" name="q" class="form-control" placeholder="Buscar por GTA, Chave NF-e, comprador ou descrição..." value="<?= e($search) ?>" autocomplete="off">
      <?php if ($search): ?>
        <a href="/vendas" class="btn btn-outline-secondary" title="Limpar busca"><i class="bi bi-x-lg"></i></a>
      <?php endif; ?>
    </div>
  </form>

  <div class="text-muted small tabular-nums ms-auto">
    <?= (int)$total ?> venda(s) registrada(s)
  </div>
</div>

<!-- Tabela de Alta Densidade -->
<div class="table-responsive">
  <table class="table">
    <thead>
      <tr>
        <th style="width: 100px;">Data</th>
        <th>Lote / Identificação</th>
        <th>Comprador / Destino</th>
        <th>Chaves Mestras (GTA / NF-e)</th>
        <th class="text-center">Cabeças</th>
        <th class="text-end">Peso Total</th>
        <th>Forma Negociação</th>
        <th class="text-end">Faturamento</th>
        <th class="text-end" style="width: 90px;">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($vendas)): ?>
        <tr>
          <td colspan="9" class="text-center py-5 text-muted">
            <i class="bi bi-cash-coin fs-1 d-block mb-2 opacity-50"></i>
            <strong>Nenhuma venda ou saída registrada.</strong>
            <p class="small mb-3 text-secondary">Dê baixa comercial nos animais do rebanho registrando a GTA ou NF-e de saída.</p>
            <a href="/vendas/novo" class="btn btn-primary btn-sm">
              <i class="bi bi-plus-lg me-1"></i> Registrar Primeira Venda
            </a>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($vendas as $v): ?>
          <?php
            $cab = (int)$v['quantidade_cabecas'];
            $val = (float)$v['valor_total'];
            $peso = (float)$v['peso_total_kg'];
            $arr = kgParaArroba($peso);
          ?>
          <tr>
            <td class="tabular-nums fw-600 text-secondary">
              <?= formatDate($v['data_venda']) ?>
            </td>
            <td>
              <strong class="text-primary d-block">
                <?= e($v['descricao'] ?: 'Saída #' . $v['id']) ?>
              </strong>
              <?php if ($v['animais_vinculados'] > 0): ?>
                <span class="badge-status neutro" style="font-size:0.68rem;">
                  <i class="bi bi-check2 me-1 text-success"></i><?= (int)$v['animais_vinculados'] ?> animais baixados
                </span>
              <?php endif; ?>
            </td>
            <td>
              <strong class="text-dark d-block"><?= e($v['comprador_destino']) ?></strong>
            </td>
            <td>
              <div class="d-flex flex-column gap-1">
                <?php if ($v['numero_gta']): ?>
                  <span class="d-inline-flex align-items-center gap-1 small text-dark fw-600">
                    <i class="bi bi-file-earmark-medical text-success"></i> GTA: <?= e($v['numero_gta']) ?>
                  </span>
                <?php endif; ?>
                <?php if ($v['chave_nfe']): ?>
                  <span class="text-muted tabular-nums" style="font-size: 0.72rem;" title="<?= e($v['chave_nfe']) ?>">
                    <i class="bi bi-receipt me-1"></i><?= substr($v['chave_nfe'], 0, 14) ?>...
                  </span>
                <?php endif; ?>
                <?php if (!empty($v['arquivo_xml'])): ?>
                  <a href="<?= e($v['arquivo_xml']) ?>" download class="badge bg-light text-success border d-inline-flex align-items-center gap-1" style="font-size:0.68rem; width: fit-content;" title="Baixar XML da NF-e">
                    <i class="bi bi-file-earmark-code"></i> Baixar XML
                  </a>
                <?php endif; ?>
              </div>
            </td>
            <td class="text-center tabular-nums fw-bold">
              <?= $cab ?> cab
            </td>
            <td class="text-end tabular-nums">
              <?php if ($peso > 0): ?>
                <strong><?= number_format($peso, 1, ',', '.') ?> kg</strong>
                <small class="text-muted d-block" style="font-size:0.75rem;"><?= number_format($arr, 1, ',', '.') ?> @</small>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if ($v['tipo_precificacao'] === 'arroba'): ?>
                <span class="badge bg-light text-dark border">Arroba (R$ <?= number_format((float)$v['preco_unitario'], 2, ',', '.') ?>/@)</span>
              <?php elseif ($v['tipo_precificacao'] === 'peso_vivo_kg'): ?>
                <span class="badge bg-light text-dark border">Quilo (R$ <?= number_format((float)$v['preco_unitario'], 2, ',', '.') ?>/kg)</span>
              <?php elseif ($v['tipo_precificacao'] === 'cabeca'): ?>
                <span class="badge bg-light text-dark border">Cabeça (R$ <?= number_format((float)$v['preco_unitario'], 2, ',', '.') ?>/cab)</span>
              <?php else: ?>
                <span class="badge bg-light text-secondary border">Lote Fixo</span>
              <?php endif; ?>
            </td>
            <td class="text-end tabular-nums fw-bold" style="color: var(--earth-green-900); font-size:0.95rem;">
              R$ <?= number_format($val, 2, ',', '.') ?>
            </td>
            <td class="text-end">
              <div class="d-inline-flex align-items-center gap-1">
                <a href="/vendas/<?= $v['id'] ?>/pdf" target="_blank" class="btn btn-sm btn-outline-success" title="Comprovante de Venda (PDF)">
                  <i class="bi bi-file-earmark-pdf-fill"></i>
                </a>
                <a href="/vendas/<?= $v['id'] ?>/editar" class="btn btn-sm btn-outline-primary" title="Editar Venda">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if (!empty($v['arquivo_xml'])): ?>
                  <a href="<?= e($v['arquivo_xml']) ?>" download class="btn btn-sm btn-secondary" title="Baixar XML da NF-e">
                    <i class="bi bi-download"></i>
                  </a>
                <?php endif; ?>
                <form method="POST" action="/vendas/<?= $v['id'] ?>/excluir" style="display:inline;" onsubmit="return confirm('Deseja estornar esta venda? Os animais vinculados terão o status restaurado para ATIVO no rebanho.')">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-secondary text-danger" title="Estornar Venda">
                    <i class="bi bi-arrow-counterclockwise"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Paginação -->
<?php if ($totalPages > 1): ?>
  <div class="d-flex justify-content-between align-items-center mt-3">
    <small class="text-muted tabular-nums">Página <?= $page ?> de <?= $totalPages ?></small>
    <div class="btn-group btn-group-sm">
      <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-secondary">Anterior</a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-secondary">Próxima</a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
