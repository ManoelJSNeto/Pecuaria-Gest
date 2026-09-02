<?php
$totalAnimais   = $db->query("SELECT COUNT(*) FROM animais WHERE status != 'morto' AND status != 'vendido'")->fetchColumn();
$totalAtivos    = $db->query("SELECT COUNT(*) FROM animais WHERE status = 'ativo'")->fetchColumn();
$totalDoentes   = $db->query("SELECT COUNT(*) FROM animais WHERE status = 'doente'")->fetchColumn();
$totalPrenhas   = $db->query("SELECT COUNT(*) FROM animais WHERE status = 'prenha'")->fetchColumn();
$totalPastagens = $db->query("SELECT COUNT(*) FROM pastagens WHERE status = 'ativa'")->fetchColumn();
$alertasAtivos  = $db->query("SELECT COUNT(*) FROM alertas WHERE lido = 0")->fetchColumn();
$ultimaPesagem  = $db->query("SELECT MAX(data) FROM pesagens")->fetchColumn();
$pesoMedio      = $db->query("
  SELECT AVG(peso_final) FROM (
    SELECT COALESCE(
      (SELECT peso FROM pesagens WHERE animal_id=a.id ORDER BY data DESC, id DESC LIMIT 1),
      a.peso_inicial
    ) as peso_final
    FROM animais a
    WHERE a.status NOT IN ('morto','vendido')
  ) WHERE peso_final IS NOT NULL
")->fetchColumn();

$recentAnimais  = $db->query("SELECT a.*, p.nome as pasto_nome FROM animais a LEFT JOIN pastagens p ON a.pasto_id=p.id ORDER BY a.created_at DESC LIMIT 6")->fetchAll();
$recentPesagens = $db->query("SELECT pe.*, a.brinco, a.nome as animal_nome FROM pesagens pe JOIN animais a ON pe.animal_id=a.id ORDER BY pe.data DESC, pe.created_at DESC LIMIT 6")->fetchAll();
$alertas        = $db->query("SELECT al.*, a.brinco, a.nome as animal_nome FROM alertas al LEFT JOIN animais a ON al.animal_id=a.id WHERE al.lido=0 ORDER BY al.created_at DESC LIMIT 5")->fetchAll();
$racas          = $db->query("SELECT raca, COUNT(*) as total FROM animais GROUP BY raca ORDER BY total DESC LIMIT 6")->fetchAll();

$pesoTrend      = $db->query("SELECT TO_CHAR(data::date, 'YYYY-MM') as mes, ROUND(AVG(peso::numeric),1) as media FROM pesagens WHERE data::date >= (CURRENT_DATE - INTERVAL '6 months') GROUP BY mes ORDER BY mes")->fetchAll();
if (empty($pesoTrend)) {
    $pesoTrend  = $db->query("SELECT mes, media FROM (SELECT TO_CHAR(data::date, 'YYYY-MM') as mes, ROUND(AVG(peso::numeric),1) as media FROM pesagens GROUP BY mes ORDER BY mes DESC LIMIT 6) sub ORDER BY mes ASC")->fetchAll();
}
?>

<!-- Cockpit de Indicadores Chave (Anti-Card Soup) -->
<div class="metric-cockpit">
  <div class="metric-cell">
    <span class="metric-label">Total no Rebanho</span>
    <div class="metric-value"><?= (int)$totalAnimais ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">cab</span></div>
    <span class="metric-sub"><i class="bi bi-check2 text-success me-1"></i><?= (int)$totalAtivos ?> animais ativos</span>
  </div>
  <div class="metric-cell">
    <span class="metric-label">Sanidade & Reprodução</span>
    <div class="metric-value" style="color:<?= $totalDoentes > 0 ? '#991b1b' : 'var(--earth-green-900)' ?>;">
      <?= (int)$totalDoentes ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">em tratamento</span>
    </div>
    <span class="metric-sub"><?= (int)$totalPrenhas ?> matrizes prenhas</span>
  </div>
  <div class="metric-cell">
    <span class="metric-label">Peso Médio Estimado</span>
    <div class="metric-value"><?= $pesoMedio ? number_format($pesoMedio, 1) : '—' ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">kg</span></div>
    <span class="metric-sub">Última pesagem: <?= formatDate($ultimaPesagem) ?></span>
  </div>
  <div class="metric-cell">
    <span class="metric-label">Alertas & Estrutura</span>
    <div class="metric-value" style="color:<?= $alertasAtivos > 0 ? '#991b1b' : 'var(--earth-green-900)' ?>;">
      <?= (int)$alertasAtivos ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">pendentes</span>
    </div>
    <span class="metric-sub"><?= (int)$totalPastagens ?> piquetes/pastagens ativos</span>
  </div>
</div>

<!-- Gráficos de Tendência e Distribuição -->
<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-graph-up text-secondary"></i>
          <h6>Evolução de Peso Médio (Últimos 6 Meses)</h6>
        </div>
      </div>
      <div class="card-body">
        <div class="chart-container" style="height:220px;">
          <canvas id="pesoChart"></canvas>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-pie-chart text-secondary"></i>
          <h6>Composição Racial do Rebanho</h6>
        </div>
      </div>
      <div class="card-body d-flex align-items-center">
        <div class="chart-container w-100" style="height:210px;">
          <canvas id="racaChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Grade de Atividades Recentes e Alertas -->
<div class="row g-3">
  <!-- Alertas Ativos -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-bell-fill text-secondary"></i>
          <h6>Alertas Ativos</h6>
        </div>
        <a href="/alertas" class="btn btn-sm btn-secondary">Ver todos</a>
      </div>
      <div class="card-body p-3">
        <?php if (empty($alertas)): ?>
          <div class="text-center text-muted py-4 small">
            <i class="bi bi-shield-check text-success fs-3 d-block mb-2"></i>
            Nenhum alerta pendente no momento.
          </div>
        <?php else: ?>
          <?php foreach ($alertas as $al): ?>
            <?php
              $alType = match($al['tipo']) { 'saude'=>'danger','vacina'=>'warning','pesagem'=>'info',default=>'info' };
              $alIcon = match($al['tipo']) { 'saude'=>'bi-heart-pulse','vacina'=>'bi-shield-plus','pesagem'=>'bi-rulers','reproducao'=>'bi-diagram-3',default=>'bi-exclamation-circle' };
            ?>
            <div class="alert-item <?= $alType ?>">
              <i class="bi <?= $alIcon ?> fs-5"></i>
              <div style="flex:1; min-width:0;">
                <div class="fw-bold small"><?= e($al['brinco'] ?? '') ?> <?= e($al['animal_nome'] ? '— ' . $al['animal_nome'] : '') ?></div>
                <div class="text-secondary small"><?= e($al['mensagem']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Últimas Pesagens -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-rulers text-secondary"></i>
          <h6>Últimas Pesagens</h6>
        </div>
        <a href="/pesagens" class="btn btn-sm btn-secondary">Histórico</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="border:none; border-radius:0;">
          <table class="table">
            <thead>
              <tr>
                <th>Brinco</th>
                <th class="text-end">Peso</th>
                <th class="text-end">Data</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentPesagens)): ?>
                <tr><td colspan="3" class="text-center text-muted py-3 small">Nenhuma pesagem recente.</td></tr>
              <?php else: ?>
                <?php foreach ($recentPesagens as $p): ?>
                <tr>
                  <td>
                    <a href="/animais/<?= $p['animal_id'] ?>" class="fw-bold text-primary">
                      <?= e($p['brinco']) ?>
                    </a>
                  </td>
                  <td class="text-end fw-bold tabular-nums">
                    <?= number_format($p['peso'], 1) ?> <span class="text-muted small">kg</span>
                  </td>
                  <td class="text-end text-muted small tabular-nums">
                    <?= formatDate($p['data']) ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Animais Recentes -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-tag text-secondary"></i>
          <h6>Lotes Recentes</h6>
        </div>
        <a href="/animais/novo" class="btn btn-sm btn-primary">+ Novo</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="border:none; border-radius:0;">
          <table class="table">
            <thead>
              <tr>
                <th>Brinco</th>
                <th>Raça</th>
                <th class="text-end">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentAnimais)): ?>
                <tr><td colspan="3" class="text-center text-muted py-3 small">Nenhum animal cadastrado.</td></tr>
              <?php else: ?>
                <?php foreach ($recentAnimais as $a): ?>
                <tr>
                  <td>
                    <a href="/animais/<?= $a['id'] ?>" class="fw-bold text-primary">
                      <?= e($a['brinco']) ?>
                    </a>
                  </td>
                  <td class="text-secondary small"><?= e($a['raca'] ?? '-') ?></td>
                  <td class="text-end"><?= statusBadge($a['status']) ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$pesoLabels = json_encode(array_column($pesoTrend, 'mes'));
$pesoData   = json_encode(array_column($pesoTrend, 'media'));
$racaLabels = json_encode(array_column($racas, 'raca'));
$racaData   = json_encode(array_column($racas, 'total'));

$scripts = <<<JS
<script>
// Gráfico de Evolução de Peso — Paleta Verde Terroso
new Chart(document.getElementById('pesoChart'), {
  type: 'line',
  data: {
    labels: $pesoLabels,
    datasets: [{
      label: 'Peso Médio (kg)',
      data: $pesoData,
      borderColor: '#33592a',
      backgroundColor: 'rgba(51, 89, 42, 0.08)',
      borderWidth: 2,
      tension: 0.25,
      fill: true,
      pointRadius: 3,
      pointHoverRadius: 5,
      pointBackgroundColor: '#33592a'
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#1c1917',
        titleFont: { family: 'Inter', size: 12, weight: 'bold' },
        bodyFont: { family: 'Inter', size: 12 },
        padding: 8,
        cornerRadius: 4,
        displayColors: false
      }
    },
    scales: {
      y: {
        beginAtZero: false,
        grid: { color: '#e6e4dc' },
        ticks: { font: { family: 'Inter', size: 11 }, color: '#78716c' }
      },
      x: {
        grid: { display: false },
        ticks: { font: { family: 'Inter', size: 11 }, color: '#78716c' }
      }
    }
  }
});

// Gráfico de Distribuição por Raça
new Chart(document.getElementById('racaChart'), {
  type: 'doughnut',
  data: {
    labels: $racaLabels,
    datasets: [{
      data: $racaData,
      backgroundColor: ['#1b3116', '#33592a', '#558b47', '#7c9e6e', '#8b5e3c', '#bfa07d'],
      borderWidth: 1,
      borderColor: '#ffffff'
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'right',
        labels: {
          font: { family: 'Inter', size: 11 },
          color: '#44403c',
          padding: 10,
          boxWidth: 12,
          usePointStyle: true
        }
      }
    },
    cutout: '68%'
  }
});
</script>
JS;
