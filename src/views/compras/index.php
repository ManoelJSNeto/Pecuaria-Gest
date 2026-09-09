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

// Métricas Consolidadas do Cockpit
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

<!-- Header Superior da Página -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold">Compras & Entradas de Gado</h5>
    <small class="text-muted tabular-nums">
      Registro de aquisição por lote com chaves fiscais (NF-e/XML) e sanitárias (GTA)
    </small>
  </div>
  <a href="/compras/novo" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i> Registrar Nova Compra
  </a>
</div>

<!-- Cockpit de Indicadores Unificado (Anti-Card Soup) -->
<div class="metric-cockpit">
  <div class="metric-cell">
    <span class="metric-label">Capital Investido</span>
    <div class="metric-value tabular-nums" style="color: var(--earth-green-900);">
      R$ <?= number_format($totalInvestido, 2, ',', '.') ?>
    </div>
    <span class="metric-sub">Total alocado em reposição</span>
  </div>

  <div class="metric-cell">
    <span class="metric-label">Rebanho Adquirido</span>
    <div class="metric-value tabular-nums">
      <?= number_format($totalCabecas, 0, ',', '.') ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">cab</span>
    </div>
    <span class="metric-sub">Média: R$ <?= number_format($custoMedioCab, 2, ',', '.') ?>/cab</span>
  </div>

  <div class="metric-cell">
    <span class="metric-label">Volume de Entrada</span>
    <div class="metric-value tabular-nums">
      <?= number_format($totalArrobas, 1, ',', '.') ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">@</span>
    </div>
    <span class="metric-sub"><?= number_format($totalKg, 0, ',', '.') ?> kg aferidos na balança</span>
  </div>

  <div class="metric-cell">
    <span class="metric-label">Custo Médio da Arroba</span>
    <div class="metric-value tabular-nums" style="color: var(--earth-green-700);">
      R$ <?= number_format($custoMedioArroba, 2, ',', '.') ?> <span style="font-size:0.85rem;font-weight:600;color:var(--text-muted);">/@</span>
    </div>
    <span class="metric-sub">Preço médio de aquisição</span>
  </div>
</div>

<!-- Barra de Filtros e Pesquisa -->
<div class="filter-toolbar">
  <form method="GET" action="/compras" class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 480px;">
    <div class="input-group input-group-sm">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input type="text" name="q" class="form-control" placeholder="Buscar por GTA, Chave NF-e, fornecedor ou lote..." value="<?= e($search) ?>" autocomplete="off">
      <?php if ($search): ?>
        <a href="/compras" class="btn btn-outline-secondary" title="Limpar busca"><i class="bi bi-x-lg"></i></a>
      <?php endif; ?>
    </div>
  </form>

  <div class="text-muted small tabular-nums ms-auto">
    <?= (int)$total ?> lote(s) registrado(s)
  </div>
</div>

<!-- Tabela de Alta Densidade -->
<div class="table-responsive">
  <table class="table">
    <thead>
      <tr>
        <th style="width: 100px;">Data</th>
        <th>Lote / Identificação</th>
        <th>Origem / Vendedor</th>
        <th>Chaves Mestras (GTA / NF-e)</th>
        <th class="text-center">Cabeças</th>
        <th class="text-end">Peso Total</th>
        <th class="text-end">Valor Total</th>
        <th class="text-end">Custo/@</th>
        <th>Pasto</th>
        <th class="text-end" style="width: 90px;">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($compras)): ?>
        <tr>
          <td colspan="10" class="text-center py-5 text-muted">
            <i class="bi bi-truck fs-1 d-block mb-2 opacity-50"></i>
            <strong>Nenhum lote de compra registrado.</strong>
            <p class="small mb-3 text-secondary">Comece importando o XML da NF-e ou registrando a GTA de entrada.</p>
            <a href="/compras/novo" class="btn btn-primary btn-sm">
              <i class="bi bi-plus-lg me-1"></i> Registrar Primeira Compra
            </a>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($compras as $c): ?>
          <?php
            $cab = (int)$c['quantidade_cabecas'];
            $val = (float)$c['valor_total'];
            $peso = (float)$c['peso_total_kg'];
            $arr = kgParaArroba($peso);
            $custoArr = $arr > 0 ? ($val / $arr) : 0;
          ?>
          <tr>
            <td class="tabular-nums fw-600 text-secondary">
              <?= formatDate($c['data_compra']) ?>
            </td>
            <td>
              <strong class="text-primary d-block">
                <?= e($c['descricao'] ?: 'Lote #' . $c['id']) ?>
              </strong>
              <?php if ($c['animais_cadastrados'] > 0): ?>
                <span class="badge-filhote" style="font-size: 0.68rem;">
                  <i class="bi bi-tags"></i> <?= (int)$c['animais_cadastrados'] ?> brincos gerados
                </span>
              <?php endif; ?>
            </td>
            <td class="text-secondary small">
              <?= e($c['fornecedor_origem'] ?: 'Não especificado') ?>
            </td>
            <td>
              <div class="d-flex flex-column gap-1">
                <?php if ($c['numero_gta']): ?>
                  <span class="d-inline-flex align-items-center gap-1 small text-dark fw-600">
                    <i class="bi bi-file-earmark-medical text-success"></i> GTA: <?= e($c['numero_gta']) ?>
                  </span>
                <?php endif; ?>
                <?php if ($c['chave_nfe']): ?>
                  <span class="text-muted tabular-nums" style="font-size: 0.72rem;" title="<?= e($c['chave_nfe']) ?>">
                    <i class="bi bi-receipt me-1"></i><?= substr($c['chave_nfe'], 0, 14) ?>...
                  </span>
                <?php endif; ?>
                <?php if (!empty($c['arquivo_xml'])): ?>
                  <a href="<?= e($c['arquivo_xml']) ?>" download class="badge bg-light text-success border d-inline-flex align-items-center gap-1" style="font-size:0.68rem; width: fit-content;" title="Baixar XML da NF-e">
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
            <td class="text-end tabular-nums fw-bold" style="color: var(--earth-green-900);">
              R$ <?= number_format($val, 2, ',', '.') ?>
            </td>
            <td class="text-end tabular-nums text-secondary small">
              <?= $custoArr > 0 ? 'R$ ' . number_format($custoArr, 2, ',', '.') . '/@' : '—' ?>
            </td>
            <td>
              <?= $c['pasto_nome'] ? '<span class="badge-status ativo">' . e($c['pasto_nome']) . '</span>' : '<span class="badge-status neutro">Sem pasto</span>' ?>
            </td>
            <td class="text-end">
              <div class="d-inline-flex align-items-center gap-1">
                <a href="/compras/<?= $c['id'] ?>/pdf" target="_blank" class="btn btn-sm btn-outline-success" title="Espelho de Compra (PDF)">
                  <i class="bi bi-file-earmark-pdf-fill"></i>
                </a>
                <a href="/compras/<?= $c['id'] ?>/editar" class="btn btn-sm btn-outline-primary" title="Editar Compra">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if (!empty($c['arquivo_xml'])): ?>
                  <a href="<?= e($c['arquivo_xml']) ?>" download class="btn btn-sm btn-secondary" title="Baixar XML da NF-e">
                    <i class="bi bi-download"></i>
                  </a>
                <?php endif; ?>
                <form method="POST" action="/compras/<?= $c['id'] ?>/excluir" style="display:inline;" onsubmit="return confirm('Deseja excluir o registro desta compra? Os animais cadastrados permanecerão no sistema desvinculados.')">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-secondary text-danger" title="Excluir Compra">
                    <i class="bi bi-trash"></i>
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
