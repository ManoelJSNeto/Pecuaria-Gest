<?php
// Summary stats for reports
$totalAnimais  = $db->query("SELECT COUNT(*) FROM animais")->fetchColumn();
$totalPesagens = $db->query("SELECT COUNT(*) FROM pesagens")->fetchColumn();
$totalSaude    = $db->query("SELECT COUNT(*) FROM saude")->fetchColumn();
$custoSaude    = $db->query("SELECT SUM(custo) FROM saude WHERE custo IS NOT NULL")->fetchColumn();
$pesoMedio     = $db->query("SELECT ROUND(AVG(p.peso),1) FROM pesagens p INNER JOIN (SELECT animal_id,MAX(data) md FROM pesagens GROUP BY animal_id) lp ON p.animal_id=lp.animal_id AND p.data=lp.md")->fetchColumn();

$statusReport  = $db->query("SELECT status, COUNT(*) as total FROM animais GROUP BY status ORDER BY total DESC")->fetchAll();
$racaReport    = $db->query("SELECT raca, COUNT(*) as total FROM animais GROUP BY raca ORDER BY total DESC")->fetchAll();
$mensal        = $db->query("SELECT strftime('%Y-%m', data) as mes, COUNT(*) as total, ROUND(AVG(peso),1) as media FROM pesagens WHERE data >= date('now','-12 months') GROUP BY mes ORDER BY mes")->fetchAll();
?>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card"><div class="stat-icon" style="background:#e8f5ee">🐄</div><div><div class="stat-value"><?= $totalAnimais ?></div><div class="stat-label">Animais Cadastrados</div></div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card"><div class="stat-icon" style="background:#e3f2fd">⚖️</div><div><div class="stat-value"><?= $totalPesagens ?></div><div class="stat-label">Total de Pesagens</div><div class="stat-sub">Peso médio: <?= $pesoMedio ? $pesoMedio . ' kg' : '—' ?></div></div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card"><div class="stat-icon" style="background:#fce4ec">⚕️</div><div><div class="stat-value"><?= $totalSaude ?></div><div class="stat-label">Eventos de Saúde</div></div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card"><div class="stat-icon" style="background:#fff3cd">💰</div><div><div class="stat-value">R$ <?= $custoSaude ? number_format($custoSaude,0,',','.') : '0' ?></div><div class="stat-label">Custo Saúde Total</div></div></div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-bar-chart text-primary"></i><h6>Rebanho por Status</h6></div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <tbody>
            <?php foreach ($statusReport as $s): ?>
            <tr>
              <td><?= statusBadge($s['status']) ?></td>
              <td class="fw-700 text-end"><?= $s['total'] ?></td>
              <td class="text-end text-muted small"><?= $totalAnimais ? round($s['total']/$totalAnimais*100) : 0 ?>%</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-bar-chart-fill text-success"></i><h6>Animais por Raça</h6></div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <tbody>
            <?php foreach ($racaReport as $r): ?>
            <tr>
              <td class="small fw-600"><?= e($r['raca'] ?? 'Sem raça') ?></td>
              <td class="fw-700 text-end"><?= $r['total'] ?></td>
              <td class="text-end text-muted small"><?= $totalAnimais ? round($r['total']/$totalAnimais*100) : 0 ?>%</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-calendar-month text-info"></i><h6>Pesagens por Mês</h6></div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <tbody>
            <?php foreach (array_slice(array_reverse($mensal), 0, 8) as $m): ?>
            <tr>
              <td class="small fw-600"><?= $m['mes'] ?></td>
              <td class="text-end"><span class="badge bg-primary"><?= $m['total'] ?> pesagens</span></td>
              <td class="text-end text-muted small"><?= $m['media'] ?> kg</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-download text-secondary"></i><h6>Exportar Dados (CSV)</h6></div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4 col-lg-2-4" style="flex: 0 0 auto; width: 20%; min-width: 180px;">
        <div class="border rounded p-3 text-center h-100 d-flex flex-column align-items-center justify-content-between gap-2">
          <div><div class="fs-2">🐄</div><h6 class="fw-700">Animais</h6><p class="text-muted small mb-0">Rebanho completo com dados cadastrais</p></div>
          <a href="/relatorios?export=animais" class="btn btn-primary btn-sm w-100"><i class="bi bi-download me-1"></i> Baixar CSV</a>
        </div>
      </div>
      <div class="col-md-4 col-lg-2-4" style="flex: 0 0 auto; width: 20%; min-width: 180px;">
        <div class="border rounded p-3 text-center h-100 d-flex flex-column align-items-center justify-content-between gap-2">
          <div><div class="fs-2">⚖️</div><h6 class="fw-700">Pesagens</h6><p class="text-muted small mb-0">Histórico de pesagens e ganho de peso</p></div>
          <a href="/relatorios?export=pesagens" class="btn btn-primary btn-sm w-100"><i class="bi bi-download me-1"></i> Baixar CSV</a>
        </div>
      </div>
      <div class="col-md-4 col-lg-2-4" style="flex: 0 0 auto; width: 20%; min-width: 180px;">
        <div class="border rounded p-3 text-center h-100 d-flex flex-column align-items-center justify-content-between gap-2">
          <div><div class="fs-2">⚕️</div><h6 class="fw-700">Saúde</h6><p class="text-muted small mb-0">Tratamentos, vacinas e medicamentos</p></div>
          <a href="/relatorios?export=saude" class="btn btn-primary btn-sm w-100"><i class="bi bi-download me-1"></i> Baixar CSV</a>
        </div>
      </div>
      <div class="col-md-4 col-lg-2-4" style="flex: 0 0 auto; width: 20%; min-width: 180px;">
        <div class="border rounded p-3 text-center h-100 d-flex flex-column align-items-center justify-content-between gap-2">
          <div><div class="fs-2">🧬</div><h6 class="fw-700">Reprodução</h6><p class="text-muted small mb-0">Inseminações, partos e gestações</p></div>
          <a href="/relatorios?export=reproducao" class="btn btn-primary btn-sm w-100"><i class="bi bi-download me-1"></i> Baixar CSV</a>
        </div>
      </div>
      <div class="col-md-4 col-lg-2-4" style="flex: 0 0 auto; width: 20%; min-width: 180px;">
        <div class="border rounded p-3 text-center h-100 d-flex flex-column align-items-center justify-content-between gap-2">
          <div><div class="fs-2">🌿</div><h6 class="fw-700">Pastagens</h6><p class="text-muted small mb-0">Capacidades, ocupação e áreas</p></div>
          <a href="/relatorios?export=pastagens" class="btn btn-primary btn-sm w-100"><i class="bi bi-download me-1"></i> Baixar CSV</a>
        </div>
      </div>
    </div>
  </div>
</div>
