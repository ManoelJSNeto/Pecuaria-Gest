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

// Métricas Consolidadas de Vendas
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

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold">Vendas & Saídas de Gado</h5>
    <small class="text-muted tabular-nums">Baixa comercial do rebanho ativo com registro de GTA, NF-e e apuração de receita</small>
  </div>
  <a href="/vendas/novo" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i> Registrar Nova Venda
  </a>
</div>

<!-- Cockpit de Indicadores de Venda -->
<div class="metric-cockpit mb-4">
  <div class="metric-cell">
    <span class="metric-label">Faturamento Total em Vendas</span>
    <div class="metric-value text-success">R$ <?= number_format($faturamentoTotal, 2, ',', '.') ?></div>
    <span class="metric-sub"><i class="bi bi-graph-up-arrow text-success me-1"></i>Receita bruta comercializada</span>
  </div>
  <div class="metric-cell">
    <span class="metric-label">Cabeças Vendidas / Abatidas</span>
    <div class="metric-value"><?= number_format($totalCabecas, 0, ',', '.') ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">cab</span></div>
    <span class="metric-sub">Desembarques e saídas do rebanho</span>
  </div>
  <div class="metric-cell">
    <span class="metric-label">Volume Comercializado (@)</span>
    <div class="metric-value tabular-nums">
      <?= number_format($totalArrobas, 1, ',', '.') ?> <span style="font-size:0.9rem;font-weight:700;color:var(--earth-green-700);">@</span>
    </div>
    <span class="metric-sub">Equivalente a <?= number_format($totalKg, 0, ',', '.') ?> kg vivo</span>
  </div>
  <div class="metric-cell">
    <span class="metric-label">Preço Médio da Arroba Vendida</span>
    <div class="metric-value">
      R$ <?= $precoMedioArroba > 0 ? number_format($precoMedioArroba, 2, ',', '.') : '—' ?>
    </div>
    <span class="metric-sub">
      Média por cabeça: <strong>R$ <?= number_format($precoMedioCab, 2, ',', '.') ?></strong>
    </span>
  </div>
</div>

<!-- Barra de Filtros -->
<div class="filter-toolbar mb-3">
  <form method="GET" class="d-flex align-items-center gap-2 w-100" style="max-width: 500px;">
    <div class="input-group input-group-sm">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por GTA, NF-e ou Frigorífico..." value="<?= e($search) ?>" autocomplete="off">
      <button class="btn btn-secondary btn-sm" type="submit">
        <i class="bi bi-search"></i>
      </button>
      <?php if ($search): ?>
        <a href="/vendas" class="btn btn-outline-secondary btn-sm" title="Limpar Busca">
          <i class="bi bi-x-lg"></i>
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Tabela de Vendas Realizadas -->
<div class="table-responsive">
  <table class="table">
    <thead>
      <tr>
        <th>Data</th>
        <th>Descrição / Lote</th>
        <th>Documentos (GTA & NF-e)</th>
        <th class="text-center">Cabeças</th>
        <th>Volume (@ / kg)</th>
        <th>Precificação</th>
        <th>Valor Total (R$)</th>
        <th>Destino / Comprador</th>
        <th class="text-end" style="width: 100px;">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($vendas)): ?>
        <tr>
          <td colspan="9" class="text-center text-muted py-5">
            <i class="bi bi-cash-coin fs-3 d-block mb-2 text-muted"></i>
            Nenhuma venda de gado registrada até o momento.
            <div class="mt-2">
              <a href="/vendas/novo" class="btn btn-sm btn-primary">Registrar Primeira Venda</a>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($vendas as $v): ?>
          <?php 
            $arrobasLote = kgParaArroba($v['peso_total_kg']);
            $mediaCab = $v['quantidade_cabecas'] > 0 ? ($v['valor_total'] / $v['quantidade_cabecas']) : 0;
          ?>
          <tr>
            <td class="tabular-nums small text-secondary">
              <?= formatDate($v['data_venda']) ?>
            </td>
            <td>
              <strong class="d-block text-primary"><?= e($v['descricao'] ?: 'Lote de Venda #'.$v['id']) ?></strong>
              <small class="text-muted"><?= e($v['comprador_destino'] ?: 'Comprador não informado') ?></small>
            </td>
            <td>
              <?php if (!empty($v['numero_gta'])): ?>
                <span class="badge bg-light text-dark border me-1" title="Guia de Trânsito Animal de Saída">
                  <i class="bi bi-file-earmark-medical text-success me-1"></i>GTA: <?= e($v['numero_gta']) ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($v['chave_nfe'])): ?>
                <span class="badge bg-light text-secondary border tabular-nums" style="font-size: 0.68rem;" title="<?= e($v['chave_nfe']) ?>">
                  <i class="bi bi-receipt me-1"></i>NF-e: <?= substr($v['chave_nfe'], 0, 10) ?>...
                </span>
              <?php endif; ?>
            </td>
            <td class="text-center tabular-nums">
              <span class="badge bg-success px-2 py-1"><?= (int)$v['quantidade_cabecas'] ?> cab</span>
              <?php if ($v['animais_vinculados'] > 0): ?>
                <br><small class="text-muted" style="font-size:0.7rem;"><?= $v['animais_vinculados'] ?> baixados</small>
              <?php endif; ?>
            </td>
            <td class="tabular-nums">
              <strong><?= number_format($arrobasLote, 1, ',', '.') ?> @</strong>
              <br><small class="text-muted"><?= number_format($v['peso_total_kg'], 1, ',', '.') ?> kg</small>
            </td>
            <td class="small">
              <?php if ($v['tipo_precificacao'] === 'arroba'): ?>
                <span class="badge bg-light text-dark border">R$ <?= number_format($v['preco_unitario'], 2, ',', '.') ?>/@</span>
              <?php elseif ($v['tipo_precificacao'] === 'peso_vivo_kg'): ?>
                <span class="badge bg-light text-dark border">R$ <?= number_format($v['preco_unitario'], 2, ',', '.') ?>/kg</span>
              <?php else: ?>
                <span class="badge bg-light text-dark border">Preço Fechado</span>
              <?php endif; ?>
            </td>
            <td class="tabular-nums">
              <strong class="text-success">R$ <?= number_format($v['valor_total'], 2, ',', '.') ?></strong>
              <br><small class="text-muted" style="font-size:0.72rem;">R$ <?= number_format($mediaCab, 2, ',', '.') ?>/cab</small>
            </td>
            <td class="small text-secondary">
              <?= e($v['comprador_destino'] ?: '—') ?>
            </td>
            <td class="text-end">
              <form method="POST" action="/vendas/<?= $v['id'] ?>/excluir" style="display:inline;" onsubmit="return confirm('Deseja cancelar esta venda? Os animais vinculados terão o status restaurado para ATIVO.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar Venda">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
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
    <small class="text-muted">Página <?= $page ?> de <?= $totalPages ?></small>
    <div class="btn-group btn-group-sm">
      <?php if ($page > 1): ?>
        <a href="/vendas?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>" class="btn btn-outline-secondary">&laquo; Anterior</a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a href="/vendas?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>" class="btn btn-outline-secondary">Próxima &raquo;</a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
