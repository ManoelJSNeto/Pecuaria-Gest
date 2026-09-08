<?php
// Summary stats for reports
$totalAnimais  = $db->query("SELECT COUNT(*) FROM animais")->fetchColumn();
$totalPesagens = $db->query("SELECT COUNT(*) FROM pesagens")->fetchColumn();
$totalSaude    = $db->query("SELECT COUNT(*) FROM saude")->fetchColumn();
$custoSaude    = $db->query("SELECT SUM(custo) FROM saude WHERE custo IS NOT NULL")->fetchColumn();

$pesoMedio = null;
$mensal = [];
try {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'pgsql') {
        $pesoMedio = $db->query("SELECT ROUND(AVG(p.peso)::numeric,1) FROM pesagens p INNER JOIN (SELECT animal_id,MAX(data) md FROM pesagens GROUP BY animal_id) lp ON p.animal_id=lp.animal_id AND p.data=lp.md")->fetchColumn();
        $mensal    = $db->query("SELECT TO_CHAR(data::date, 'YYYY-MM') as mes, COUNT(*) as total, ROUND(AVG(peso::numeric),1) as media FROM pesagens WHERE data::date >= (CURRENT_DATE - INTERVAL '12 months') GROUP BY mes ORDER BY mes")->fetchAll();
    } else {
        $pesoMedio = $db->query("SELECT ROUND(AVG(p.peso), 1) FROM pesagens p INNER JOIN (SELECT animal_id,MAX(data) md FROM pesagens GROUP BY animal_id) lp ON p.animal_id=lp.animal_id AND p.data=lp.md")->fetchColumn();
        $mensal    = $db->query("SELECT substr(data, 1, 7) as mes, COUNT(*) as total, ROUND(AVG(peso), 1) as media FROM pesagens GROUP BY mes ORDER BY mes")->fetchAll();
    }
} catch (Exception $e) {
    $mensal = [];
}

$statusReport  = $db->query("SELECT status, COUNT(*) as total FROM animais GROUP BY status ORDER BY total DESC")->fetchAll();
$racaReport    = $db->query("SELECT raca, COUNT(*) as total FROM animais GROUP BY raca ORDER BY total DESC")->fetchAll();
?>

<div class="mb-3">
  <h5 class="mb-0 fw-bold">Relatórios Gerenciais & Exportação</h5>
  <small class="text-muted">Consolidação estatística e extração de dados tabulares</small>
</div>

<!-- Cockpit de Indicadores Gerenciais -->
<div class="metric-cockpit">
  <div class="metric-cell">
    <span class="metric-label">Base Cadastrada</span>
    <div class="metric-value tabular-nums"><?= (int)$totalAnimais ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">animais</span></div>
    <span class="metric-sub">Total histórico registrado</span>
  </div>
  <div class="metric-cell">
    <span class="metric-label">Aferições Biométricas</span>
    <div class="metric-value tabular-nums"><?= (int)$totalPesagens ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">pesagens</span></div>
    <span class="metric-sub">Peso médio: <?= $pesoMedio ? $pesoMedio . ' kg' : '—' ?></span>
  </div>
  <div class="metric-cell">
    <span class="metric-label">Ocorrências Clínicas</span>
    <div class="metric-value tabular-nums"><?= (int)$totalSaude ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">eventos</span></div>
    <span class="metric-sub">Vacinas e tratamentos</span>
  </div>
  <div class="metric-cell">
    <span class="metric-label">Investimento Sanitário</span>
    <div class="metric-value tabular-nums" style="color:var(--earth-green-800);">
      R$ <?= $custoSaude ? number_format($custoSaude, 2, ',', '.') : '0,00' ?>
    </div>
    <span class="metric-sub">Custo acumulado em insumos</span>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-pie-chart text-secondary me-1"></i>Rebanho por Status</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="border:none; border-radius:0;">
          <table class="table table-sm">
            <tbody>
              <?php foreach ($statusReport as $s): ?>
              <tr>
                <td><?= statusBadge($s['status']) ?></td>
                <td class="fw-bold text-end tabular-nums"><?= (int)$s['total'] ?></td>
                <td class="text-end text-muted small tabular-nums"><?= $totalAnimais ? round($s['total']/$totalAnimais*100) : 0 ?>%</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-tag text-secondary me-1"></i>Animais por Raça</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="border:none; border-radius:0;">
          <table class="table table-sm">
            <tbody>
              <?php foreach ($racaReport as $r): ?>
              <tr>
                <td class="small fw-600"><?= e($r['raca'] ?? 'Sem raça') ?></td>
                <td class="fw-bold text-end tabular-nums"><?= (int)$r['total'] ?></td>
                <td class="text-end text-muted small tabular-nums"><?= $totalAnimais ? round($r['total']/$totalAnimais*100) : 0 ?>%</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-calendar-check text-secondary me-1"></i>Pesagens por Mês</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="border:none; border-radius:0;">
          <table class="table table-sm">
            <tbody>
              <?php foreach (array_slice(array_reverse($mensal), 0, 8) as $m): ?>
              <tr>
                <td class="small fw-600 tabular-nums"><?= $m['mes'] ?></td>
                <td class="text-end">
                  <span class="badge bg-light text-dark border tabular-nums"><?= (int)$m['total'] ?> pesagens</span>
                </td>
                <td class="text-end text-secondary small tabular-nums"><?= $m['media'] ?> kg</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Emissão de Relatórios Oficiais em PDF (A4) -->
<div class="card mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #f0f7f2 0%, #ffffff 100%); border: 1px solid var(--border) !important;">
  <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
    <div>
      <h6 class="mb-0 fw-bold" style="color: var(--primary);">
        <i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>Relatórios Oficiais em PDF (A4 para Impressão)
      </h6>
      <small class="text-muted">Documentos técnicos formatados com cabeçalho institucional, indicadores zootécnicos e assinatura</small>
    </div>
    <span class="badge bg-success" style="font-size: 0.72rem;">Pronto para Impressão / PDF</span>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <!-- Card Inventário Geral do Rebanho -->
      <div class="col-md-6">
        <div class="p-3 bg-white border rounded h-100 d-flex flex-column justify-content-between shadow-xs">
          <div>
            <div class="d-flex align-items-center gap-2 mb-2">
              <div class="p-2 rounded" style="background: #eef7f0; color: var(--primary);">
                <i class="bi bi-clipboard2-data-fill fs-5"></i>
              </div>
              <div>
                <strong class="d-block" style="color: var(--earth-green-950);">Inventário Geral do Rebanho & Lotação</strong>
                <span class="badge-status ativo" style="font-size: 0.68rem;">Zootécnico & Pastoreio</span>
              </div>
            </div>
            <p class="small text-muted mb-3">
              Consolidação de animais ativos, balanço de ocupação por pastagem/piquete, densidade (cab/ha), categorias zootécnicas e médias de peso em kg e arrobas (@).
            </p>
          </div>
          <div class="d-flex gap-2">
            <a href="/relatorios/pdf?tipo=rebanho" target="_blank" class="btn btn-outline-success btn-sm w-100 fw-600">
              <i class="bi bi-printer me-1"></i> Visualizar & Imprimir PDF
            </a>
          </div>
        </div>
      </div>

      <!-- Card Laudo Sanitário -->
      <div class="col-md-6">
        <div class="p-3 bg-white border rounded h-100 d-flex flex-column justify-content-between shadow-xs">
          <div>
            <div class="d-flex align-items-center gap-2 mb-2">
              <div class="p-2 rounded" style="background: #fdf2e9; color: #d35400;">
                <i class="bi bi-heart-pulse-fill fs-5"></i>
              </div>
              <div>
                <strong class="d-block" style="color: var(--earth-green-950);">Laudo Sanitário & Manejo Clínico</strong>
                <span class="badge-status ativo" style="font-size: 0.68rem; background: #fdf2e9; color: #d35400; border-color: #f5cba7;">Sanitário & Fármacos</span>
              </div>
            </div>
            <p class="small text-muted mb-3">
              Prontuário sanitário consolidado com histórico cronológico de aplicações, custos com vacinas e medicamentos, distribuição por tipo e controle por veterinário.
            </p>
          </div>
          <div class="d-flex gap-2">
            <a href="/relatorios/pdf?tipo=saude" target="_blank" class="btn btn-outline-success btn-sm w-100 fw-600">
              <i class="bi bi-printer me-1"></i> Visualizar & Imprimir PDF
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Exportação de Dados em CSV -->
<div class="card">
  <div class="card-header">
    <h6 class="mb-0"><i class="bi bi-download text-secondary me-1"></i>Exportar Bases de Dados (Formato CSV)</h6>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-lg-4 col-md-6">
        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between" style="background:var(--bg-subtle);">
          <div>
            <div class="fs-4 text-primary mb-1"><i class="bi bi-tag"></i></div>
            <strong class="d-block mb-1">Animais</strong>
            <small class="text-muted d-block mb-3">Rebanho completo com dados cadastrais e genealogia.</small>
          </div>
          <a href="/relatorios?export=animais" class="btn btn-secondary btn-sm w-100">
            <i class="bi bi-download me-1"></i> Baixar CSV
          </a>
        </div>
      </div>

      <div class="col-lg-4 col-md-6">
        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between" style="background:var(--bg-subtle);">
          <div>
            <div class="fs-4 text-primary mb-1"><i class="bi bi-rulers"></i></div>
            <strong class="d-block mb-1">Pesagens</strong>
            <small class="text-muted d-block mb-3">Histórico completo de pesagens e evolução de peso.</small>
          </div>
          <a href="/relatorios?export=pesagens" class="btn btn-secondary btn-sm w-100">
            <i class="bi bi-download me-1"></i> Baixar CSV
          </a>
        </div>
      </div>

      <div class="col-lg-4 col-md-6">
        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between" style="background:var(--bg-subtle);">
          <div>
            <div class="fs-4 text-primary mb-1"><i class="bi bi-heart-pulse"></i></div>
            <strong class="d-block mb-1">Saúde & Vacinas</strong>
            <small class="text-muted d-block mb-3">Ocorrências sanitárias, medicamentos e custos.</small>
          </div>
          <a href="/relatorios?export=saude" class="btn btn-secondary btn-sm w-100">
            <i class="bi bi-download me-1"></i> Baixar CSV
          </a>
        </div>
      </div>

      <div class="col-lg-4 col-md-6">
        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between" style="background:var(--bg-subtle);">
          <div>
            <div class="fs-4 text-primary mb-1"><i class="bi bi-tree"></i></div>
            <strong class="d-block mb-1">Pastagens</strong>
            <small class="text-muted d-block mb-3">Capacidade, área e taxa de ocupação dos piquetes.</small>
          </div>
          <a href="/relatorios?export=pastagens" class="btn btn-secondary btn-sm w-100">
            <i class="bi bi-download me-1"></i> Baixar CSV
          </a>
        </div>
      </div>

      <div class="col-lg-4 col-md-6">
        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between" style="background:var(--bg-subtle);">
          <div>
            <div class="fs-4 text-primary mb-1"><i class="bi bi-truck"></i></div>
            <strong class="d-block mb-1">Compras & Entradas</strong>
            <small class="text-muted d-block mb-3">Lotes adquiridos com GTA, NF-e, custos e médias.</small>
          </div>
          <a href="/relatorios?export=compras" class="btn btn-secondary btn-sm w-100">
            <i class="bi bi-download me-1"></i> Baixar CSV
          </a>
        </div>
      </div>

      <div class="col-lg-4 col-md-6">
        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between" style="background:var(--bg-subtle);">
          <div>
            <div class="fs-4 text-success mb-1"><i class="bi bi-cash-coin"></i></div>
            <strong class="d-block mb-1">Vendas & Saídas</strong>
            <small class="text-muted d-block mb-3">Abates e comercialização com GTA, NF-e e apuração.</small>
          </div>
          <a href="/relatorios?export=vendas" class="btn btn-secondary btn-sm w-100">
            <i class="bi bi-download me-1"></i> Baixar CSV
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
