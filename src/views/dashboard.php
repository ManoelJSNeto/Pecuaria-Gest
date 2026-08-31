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
$recentAnimais  = $db->query("SELECT a.*, p.nome as pasto_nome FROM animais a LEFT JOIN pastagens p ON a.pasto_id=p.id ORDER BY a.created_at DESC LIMIT 8")->fetchAll();
$recentPesagens = $db->query("SELECT pe.*, a.brinco, a.nome as animal_nome FROM pesagens pe JOIN animais a ON pe.animal_id=a.id ORDER BY pe.data DESC, pe.created_at DESC LIMIT 6")->fetchAll();
$alertas        = $db->query("SELECT al.*, a.brinco, a.nome as animal_nome FROM alertas al LEFT JOIN animais a ON al.animal_id=a.id WHERE al.lido=0 ORDER BY al.created_at DESC LIMIT 5")->fetchAll();
$racas          = $db->query("SELECT raca, COUNT(*) as total FROM animais GROUP BY raca ORDER BY total DESC LIMIT 6")->fetchAll();

$pesoTrend      = $db->query("SELECT TO_CHAR(data::date, 'YYYY-MM') as mes, ROUND(AVG(peso::numeric),1) as media FROM pesagens WHERE data::date >= (CURRENT_DATE - INTERVAL '6 months') GROUP BY mes ORDER BY mes")->fetchAll();
if (empty($pesoTrend)) {
    $pesoTrend  = $db->query("SELECT mes, media FROM (SELECT TO_CHAR(data::date, 'YYYY-MM') as mes, ROUND(AVG(peso::numeric),1) as media FROM pesagens GROUP BY mes ORDER BY mes DESC LIMIT 6) sub ORDER BY mes ASC")->fetchAll();
}
?>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#e8f5ee"><i class="bi bi-tag-fill text-success fs-4"></i></div>
      <div>
        <div class="stat-value"><?= $totalAnimais ?></div>
        <div class="stat-label">Total no Rebanho</div>
        <div class="stat-sub"><?= $totalAtivos ?> ativos</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:<?= $totalDoentes > 0 ? '#fee2e2' : '#f0fdf4' ?>"><i class="bi bi-heart-pulse-fill <?= $totalDoentes > 0 ? 'text-danger' : 'text-success' ?> fs-4"></i></div>
      <div>
        <div class="stat-value" style="color:<?= $totalDoentes > 0 ? '#dc3545' : '#1a4d2e' ?>"><?= $totalDoentes ?></div>
        <div class="stat-label">Em Tratamento</div>
        <div class="stat-sub"><?= $totalPrenhas ?> prenhas</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#e0f2fe"><i class="bi bi-rulers text-primary fs-4"></i></div>
      <div>
        <div class="stat-value"><?= $pesoMedio ? number_format($pesoMedio, 0) : '—' ?></div>
        <div class="stat-label">Peso Médio (kg)</div>
        <div class="stat-sub">Última: <?= formatDate($ultimaPesagem) ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:<?= $alertasAtivos > 0 ? '#fee2e2' : '#f0fdf4' ?>"><i class="bi bi-bell-fill <?= $alertasAtivos > 0 ? 'text-danger' : 'text-success' ?> fs-4"></i></div>
      <div>
        <div class="stat-value" style="color:<?= $alertasAtivos > 0 ? '#dc3545' : '#1a4d2e' ?>"><?= $alertasAtivos ?></div>
        <div class="stat-label">Alertas Ativos</div>
        <div class="stat-sub"><?= $totalPastagens ?> pastagens ativas</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-graph-up text-success"></i><h6>Evolução do Peso Médio (6 meses)</h6></div>
      <div class="card-body"><div class="chart-container"><canvas id="pesoChart"></canvas></div></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-pie-chart text-primary"></i><h6>Rebanho por Raça</h6></div>
      <div class="card-body d-flex align-items-center">
        <div class="chart-container w-100" style="height:200px"><canvas id="racaChart"></canvas></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header justify-content-between">
        <div class="d-flex align-items-center gap-2"><i class="bi bi-bell-fill text-warning"></i><h6>Alertas Ativos</h6></div>
        <a href="/alertas" class="btn btn-sm btn-outline-secondary">Ver todos</a>
      </div>
      <div class="card-body p-3">
        <?php if (empty($alertas)): ?>
          <div class="text-center text-muted py-3 small"><i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>Nenhum alerta pendente!</div>
        <?php else: ?>
          <?php foreach ($alertas as $al): ?>
            <?php
              $alType = match($al['tipo']) { 'saude'=>'danger','vacina'=>'warning','pesagem'=>'info',default=>'info' };
              $alIcon = match($al['tipo']) { 'saude'=>'bi-heart-pulse-fill','vacina'=>'bi-shield-plus','pesagem'=>'bi-rulers','reproducao'=>'bi-diagram-3',default=>'bi-exclamation-circle' };
            ?>
            <div class="alert-item <?= $alType ?>">
              <i class="bi <?= $alIcon ?> mt-1"></i>
              <div>
                <div class="fw-600 small"><?= e($al['brinco'] ?? '') ?> <?= e($al['animal_nome'] ?? '') ?></div>
                <div class="text-muted" style="font-size:.78rem"><?= e($al['mensagem']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header justify-content-between">
        <div class="d-flex align-items-center gap-2"><i class="bi bi-rulers text-primary"></i><h6>Últimas Pesagens</h6></div>
        <a href="/pesagens" class="btn btn-sm btn-outline-secondary">Ver todas</a>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
          <thead><tr><th>Animal</th><th>Peso</th><th>Data</th></tr></thead>
          <tbody>
            <?php foreach ($recentPesagens as $p): ?>
            <tr>
              <td><a href="/animais/<?= $p['animal_id'] ?>" class="text-decoration-none fw-600 small"><?= e($p['brinco']) ?></a></td>
              <td class="fw-700"><?= number_format($p['peso'],1) ?> <small class="text-muted">kg</small></td>
              <td class="text-muted small"><?= formatDate($p['data']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header justify-content-between">
        <div class="d-flex align-items-center gap-2"><i class="bi bi-plus-circle text-success"></i><h6>Animais Recentes</h6></div>
        <a href="/animais/novo" class="btn btn-sm btn-primary">+ Cadastrar</a>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
          <thead><tr><th>Brinco</th><th>Raça</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($recentAnimais as $a): ?>
            <tr>
              <td><a href="/animais/<?= $a['id'] ?>" class="text-decoration-none fw-600 small"><?= e($a['brinco']) ?></a></td>
              <td class="small text-muted"><?= e($a['raca'] ?? '-') ?></td>
              <td><?= statusBadge($a['status']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php
$pesoLabels = json_encode(array_column($pesoTrend,'mes'));
$pesoData   = json_encode(array_column($pesoTrend,'media'));
$racaLabels = json_encode(array_column($racas,'raca'));
$racaData   = json_encode(array_column($racas,'total'));
$scripts = <<<JS
<script>
new Chart(document.getElementById('pesoChart'),{type:'line',data:{labels:$pesoLabels,datasets:[{label:'Peso médio (kg)',data:$pesoData,borderColor:'#2d7a4e',backgroundColor:'rgba(45,122,78,0.08)',tension:0.4,fill:true,pointRadius:5,pointBackgroundColor:'#2d7a4e'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:false,grid:{color:'#f0f4f0'}},x:{grid:{display:false}}}}});
new Chart(document.getElementById('racaChart'),{type:'doughnut',data:{labels:$racaLabels,datasets:[{data:$racaData,backgroundColor:['#1a4d2e','#2d7a4e','#4caf78','#a8d5b7','#8b5e3c','#c4956a'],borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right',labels:{font:{size:11},padding:10}}}}});
</script>
JS;
