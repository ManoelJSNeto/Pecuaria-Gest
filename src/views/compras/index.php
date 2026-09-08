<?php
if (!isset($db)) { $db = getDb(); }

$search = $_GET['q'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
if ($search) {
    $where[] = "(c.descricao LIKE ? OR c.numero_gta LIKE ? OR c.chave_nfe LIKE ? OR c.fornecedor_origem LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}
$whereStr = implode(' AND ', $where);

// Contagem e lista paginada
$countStmt = $db->prepare("SELECT COUNT(*) FROM compras c WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stmt = $db->prepare("
    SELECT c.*, p.nome as pasto_nome,
           (SELECT COUNT(*) FROM animais a WHERE a.compra_id = c.id) as animais_cadastrados
    FROM compras c
    LEFT JOIN pastagens p ON c.pasto_destino_id = p.id
    WHERE $whereStr
    ORDER BY c.data_compra DESC, c.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$compras = $stmt->fetchAll();

// Métricas Consolidadas de Compras
$metricStmt = $db->query("
    SELECT 
        COALESCE(SUM(valor_total), 0) as total_investido,
        COALESCE(SUM(quantidade_cabecas), 0) as total_cabecas,
        COALESCE(SUM(peso_total_kg), 0) as total_kg
    FROM compras
");
$metrics = $metricStmt->fetch();
$totalInvestido = (float)$metrics['total_investido'];
$totalCabecas = (int)$metrics['total_cabecas'];
$totalKg = (float)$metrics['total_kg'];

$custoMedioCab = $totalCabecas > 0 ? ($totalInvestido / $totalCabecas) : 0.0;
$pesoMedioCab = $totalCabecas > 0 ? ($totalKg / $totalCabecas) : 0.0;
$totalArrobas = kgParaArroba($totalKg);
$custoMedioArroba = $totalArrobas > 0 ? ($totalInvestido / $totalArrobas) : 0.0;
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold">Compras & Entradas de Gado</h5>
    <small class="text-muted tabular-nums">Registro de aquisição por lote com chaves fiscais (NF-e) e sanitárias (GTA)</small>
  </div>
  <a href="/compras/novo" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i> Registrar Nova Compra
  </a>
</div>

<!-- Cockpit de Indicadores de Compra -->
<div class="metric-cockpit mb-4">
  <div class="metric-cell">
    <span class="metric-label">Total Investido em Gado</span>
    <div class="metric-value">R$ <?= number_format($totalInvestido, 2, ',', '.') ?></div>
    <span class="metric-sub"><i class="bi bi-wallet2 text-success me-1"></i>Capital alocado em reposição</span>
  </div>
  <div class="metric-cell">
    <span class="metric-label">Cabeças Adquiridas</span>
    <div class="metric-value"><?= number_format($totalCabecas, 0, ',', '.') ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">cab</span></div>
    <span class="metric-sub">Total acumulado de entradas</span>
  </div>
  <div class="metric-cell">
    <span class="metric-label">Custo Médio por Cabeça</span>
    <div class="metric-value">R$ <?= number_format($custoMedioCab, 2, ',', '.') ?></div>
    <span class="metric-sub">Média ponderada por animal</span>
  </div>
  <div class="metric-cell">
    <span class="metric-label">Peso Médio & Custo/@</span>
    <div class="metric-value">
      <span class="peso-hero-kg"><?= number_format($pesoMedioCab, 1, ',', '.') ?> <small class="text-muted" style="font-size:0.85rem;">kg</small></span>
      <span class="peso-hero-arr" style="display:none;"><?= number_format(kgParaArroba($pesoMedioCab), 2, ',', '.') ?> <small class="text-success fw-bold" style="font-size:0.85rem;">@</small></span>
    </div>
    <span class="metric-sub">
      Custo Arroba: <strong>R$ <?= $custoMedioArroba > 0 ? number_format($custoMedioArroba, 2, ',', '.') : '—' ?>/@</strong>
    </span>
  </div>
</div>

<!-- Barra de Filtros -->
<div class="filter-toolbar mb-3">
  <form method="GET" class="d-flex align-items-center gap-2 w-100" style="max-width: 500px;">
    <div class="input-group input-group-sm">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por GTA, NF-e ou Fornecedor..." value="<?= e($search) ?>" autocomplete="off">
      <button class="btn btn-secondary btn-sm" type="submit">
        <i class="bi bi-search"></i>
      </button>
      <?php if ($search): ?>
        <a href="/compras" class="btn btn-outline-secondary btn-sm" title="Limpar Busca">
          <i class="bi bi-x-lg"></i>
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Tabela de Lotes Comprados -->
<div class="table-responsive">
  <table class="table">
    <thead>
      <tr>
        <th>Data</th>
        <th>Descrição / Lote</th>
        <th>Documentos (GTA & NF-e)</th>
        <th class="text-center">Cabeças</th>
        <th>Peso Médio Entrada</th>
        <th>Valor Total (R$)</th>
        <th>Pasto de Entrada</th>
        <th class="text-end" style="width: 100px;">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($compras)): ?>
        <tr>
          <td colspan="8" class="text-center text-muted py-5">
            <i class="bi bi-truck fs-3 d-block mb-2 text-muted"></i>
            Nenhuma compra de gado registrada até o momento.
            <div class="mt-2">
              <a href="/compras/novo" class="btn btn-sm btn-primary">Registrar Primeira Compra</a>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($compras as $c): ?>
          <?php 
            $mediaKg = $c['quantidade_cabecas'] > 0 ? ($c['peso_total_kg'] / $c['quantidade_cabecas']) : 0;
            $custoCab = $c['quantidade_cabecas'] > 0 ? ($c['valor_total'] / $c['quantidade_cabecas']) : 0;
          ?>
          <tr>
            <td class="tabular-nums small text-secondary">
              <?= formatDate($c['data_compra']) ?>
            </td>
            <td>
              <strong class="d-block text-primary"><?= e($c['descricao'] ?: 'Lote de Compra #'.$c['id']) ?></strong>
              <small class="text-muted"><?= e($c['fornecedor_origem'] ?: 'Origem não informada') ?></small>
            </td>
            <td>
              <?php if (!empty($c['numero_gta'])): ?>
                <span class="badge bg-light text-dark border me-1" title="Guia de Trânsito Animal">
                  <i class="bi bi-file-earmark-medical text-success me-1"></i>GTA: <?= e($c['numero_gta']) ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($c['chave_nfe'])): ?>
                <span class="badge bg-light text-secondary border tabular-nums" style="font-size: 0.68rem;" title="<?= e($c['chave_nfe']) ?>">
                  <i class="bi bi-receipt me-1"></i>NF-e: <?= substr($c['chave_nfe'], 0, 10) ?>...
                </span>
              <?php endif; ?>
              <?php if (empty($c['numero_gta']) && empty($c['chave_nfe'])): ?>
                <span class="text-muted small">Sem docs</span>
              <?php endif; ?>
            </td>
            <td class="text-center tabular-nums">
              <span class="badge bg-primary px-2 py-1"><?= (int)$c['quantidade_cabecas'] ?> cab</span>
              <?php if ($c['animais_cadastrados'] > 0): ?>
                <br><small class="text-muted" style="font-size:0.7rem;"><?= $c['animais_cadastrados'] ?> brincos vinculados</small>
              <?php endif; ?>
            </td>
            <td>
              <?= renderPesoBadge($mediaKg) ?>
            </td>
            <td class="tabular-nums">
              <span class="fw-bold text-dark">R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></span>
              <br><small class="text-muted" style="font-size:0.72rem;">R$ <?= number_format($custoCab, 2, ',', '.') ?>/cab</small>
            </td>
            <td class="small text-secondary">
              <?= !empty($c['pasto_nome']) ? '<i class="bi bi-tree text-success me-1"></i>'.e($c['pasto_nome']) : '—' ?>
            </td>
            <td class="text-end">
              <form method="POST" action="/compras/<?= $c['id'] ?>/excluir" style="display:inline;" onsubmit="return confirm('Deseja excluir o registro desta compra? Os animais cadastrados permanecerão no sistema desvinculados.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir Compra">
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
        <a href="/compras?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>" class="btn btn-outline-secondary">&laquo; Anterior</a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a href="/compras?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>" class="btn btn-outline-secondary">Próxima &raquo;</a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
