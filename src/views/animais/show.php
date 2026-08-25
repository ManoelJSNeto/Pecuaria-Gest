<?php
$pesagens   = $db->prepare("SELECT * FROM pesagens WHERE animal_id=? ORDER BY data DESC LIMIT 20");
$pesagens->execute([$animal['id']]);
$pesagens   = $pesagens->fetchAll();

$saude_list = $db->prepare("SELECT * FROM saude WHERE animal_id=? ORDER BY data DESC LIMIT 10");
$saude_list->execute([$animal['id']]);
$saude_list = $saude_list->fetchAll();

$repro_list = $db->prepare("SELECT * FROM reproducao WHERE animal_id=? ORDER BY data DESC LIMIT 6");
$repro_list->execute([$animal['id']]);
$repro_list = $repro_list->fetchAll();

$pastoStmt = $db->prepare("SELECT * FROM pastagens WHERE id=?");
$pastoStmt->execute([$animal['pasto_id'] ?: 0]);
$pasto = $animal['pasto_id'] ? ($pastoStmt->fetch() ?: null) : null;

$chartStmt = $db->prepare("SELECT data, peso FROM pesagens WHERE animal_id=? ORDER BY data ASC LIMIT 12");
$chartStmt->execute([$animal['id']]);
$chartPesagens = $chartStmt->fetchAll();
$chartLabels   = json_encode(array_map(fn($p) => $p['data'], $chartPesagens));
$chartData     = json_encode(array_map(fn($p) => $p['peso'], $chartPesagens));

$pesoAtual = !empty($pesagens) ? $pesagens[0]['peso'] : $animal['peso_inicial'];
$sexoEmoji = $animal['sexo'] === 'M' ? '🐂' : '🐄';
?>
<div class="mb-3">
  <a href="/animais" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</div>

<div class="animal-profile-header mb-3">
  <div class="d-flex align-items-start gap-3 flex-wrap">
    <div class="animal-big-avatar"><?= $sexoEmoji ?></div>
    <div class="flex-grow-1">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <h3 class="mb-0 fw-800"><?= e($animal['brinco']) ?></h3>
        <?php if ($animal['nome']): ?><span class="opacity-75">"<?= e($animal['nome']) ?>"</span><?php endif; ?>
        <span class="badge bg-white text-dark"><?= statusBadge($animal['status']) ?></span>
      </div>
      <div class="mt-2 d-flex flex-wrap gap-3">
        <span><small class="opacity-60">Raça</small><br><strong><?= e($animal['raca'] ?? '—') ?></strong></span>
        <span><small class="opacity-60">Sexo</small><br><strong><?= sexoLabel($animal['sexo']) ?></strong></span>
        <span><small class="opacity-60">Idade</small><br><strong><?= calcIdade($animal['data_nascimento']) ?></strong></span>
        <span><small class="opacity-60">Nascimento</small><br><strong><?= formatDate($animal['data_nascimento']) ?></strong></span>
        <span><small class="opacity-60">Pastagem</small><br><strong><?= e($pasto['nome'] ?? '—') ?></strong></span>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="/animais/<?= $animal['id'] ?>/editar" class="btn btn-light btn-sm"><i class="bi bi-pencil me-1"></i>Editar</a>
      <a href="/pesagens/novo?animal_id=<?= $animal['id'] ?>" class="btn btn-warning btn-sm"><i class="bi bi-rulers me-1"></i>Pesar</a>
      <a href="/saude/novo?animal_id=<?= $animal['id'] ?>" class="btn btn-danger btn-sm"><i class="bi bi-heart-pulse me-1"></i>Saúde</a>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-3">
    <div class="card text-center py-3">
      <div class="card-body">
        <div style="font-size:2.5rem;font-weight:800;color:#1a4d2e"><?= $pesoAtual ? number_format($pesoAtual,1) : '—' ?></div>
        <div class="text-muted small">kg — Peso Atual</div>
        <?php if (count($pesagens) >= 2): ?>
          <?php $diff = $pesagens[0]['peso'] - $pesagens[1]['peso']; $arrow = $diff >= 0 ? '↑' : '↓'; $color = $diff >= 0 ? 'success' : 'danger'; ?>
          <div class="text-<?= $color ?> small mt-1"><?= $arrow ?> <?= number_format(abs($diff),1) ?> kg na última</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="card mt-3">
      <div class="card-body p-3">
        <div class="info-label">Origem</div>
        <div class="info-value mb-2"><?= e($animal['origem'] ?? '—') ?></div>
        <div class="info-label">Observações</div>
        <div class="info-value"><?= e($animal['observacao'] ?? '—') ?></div>
      </div>
    </div>
  </div>

  <div class="col-md-9">
    <div class="card mb-3">
      <div class="card-header justify-content-between">
        <div class="d-flex align-items-center gap-2"><i class="bi bi-graph-up text-success"></i><h6>Histórico de Peso</h6></div>
        <a href="/pesagens/novo?animal_id=<?= $animal['id'] ?>" class="btn btn-sm btn-primary">+ Pesagem</a>
      </div>
      <div class="card-body">
        <?php if (empty($chartPesagens)): ?>
          <div class="text-center text-muted py-3">Nenhuma pesagem registrada ainda.</div>
        <?php else: ?>
          <div class="chart-container" style="height:180px"><canvas id="pesoHistChart"></canvas></div>
        <?php endif; ?>
      </div>
      <?php if (!empty($pesagens)): ?>
      <div class="card-body border-top p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>Data</th><th>Peso</th><th>Ganho</th><th>Origem</th><th>Obs.</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($pesagens as $idx => $p): ?>
              <?php $ganho = ($idx < count($pesagens)-1) ? ($p['peso'] - $pesagens[$idx+1]['peso']) : null; ?>
              <tr>
                <td class="small"><?= formatDate($p['data']) ?></td>
                <td class="fw-700"><?= number_format($p['peso'],1) ?> kg</td>
                <td class="small">
                  <?php if ($ganho !== null): ?>
                    <span class="text-<?= $ganho >= 0 ? 'success' : 'danger' ?>"><?= $ganho >= 0 ? '+' : '' ?><?= number_format($ganho,1) ?> kg</span>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td class="small text-muted"><?= e($p['origem'] ?? 'web') ?></td>
                <td class="small text-muted"><?= e($p['observacao'] ?? '—') ?></td>
                <td>
                  <form method="POST" action="/pesagens/<?= $p['id'] ?>/excluir" onsubmit="return confirm('Excluir pesagem?')">
                    <?= csrf_field() ?>
                    <button class="btn btn-xs btn-outline-danger btn-sm py-0 px-1"><i class="bi bi-trash" style="font-size:.7rem"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-header justify-content-between">
            <div class="d-flex align-items-center gap-2"><i class="bi bi-heart-pulse text-danger"></i><h6>Saúde</h6></div>
            <a href="/saude/novo?animal_id=<?= $animal['id'] ?>" class="btn btn-sm btn-outline-danger">+ Adicionar</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($saude_list)): ?>
              <div class="text-center text-muted py-3 small">Sem registros de saúde.</div>
            <?php else: ?>
            <table class="table table-sm mb-0">
              <tbody>
                <?php foreach ($saude_list as $s): ?>
                <tr>
                  <td>
                    <div class="small fw-600"><?= e($s['tipo']) ?></div>
                    <div class="text-muted" style="font-size:.75rem"><?= e($s['descricao']) ?></div>
                    <?php if ($s['medicamento']): ?><div class="text-info" style="font-size:.72rem">💊 <?= e($s['medicamento']) ?></div><?php endif; ?>
                  </td>
                  <td class="text-end small text-muted text-nowrap"><?= formatDate($s['data']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-header justify-content-between">
            <div class="d-flex align-items-center gap-2"><i class="bi bi-diagram-3 text-info"></i><h6>Reprodução</h6></div>
            <a href="/reproducao/novo?animal_id=<?= $animal['id'] ?>" class="btn btn-sm btn-outline-info">+ Adicionar</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($repro_list)): ?>
              <div class="text-center text-muted py-3 small">Sem registros reprodutivos.</div>
            <?php else: ?>
            <table class="table table-sm mb-0">
              <tbody>
                <?php foreach ($repro_list as $r): ?>
                <tr>
                  <td>
                    <div class="small fw-600"><?= e($r['tipo']) ?></div>
                    <div class="text-muted" style="font-size:.75rem"><?= e($r['resultado'] ?? '') ?></div>
                  </td>
                  <td class="text-end small text-muted text-nowrap"><?= formatDate($r['data']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($chartPesagens)):
$scripts = <<<JS
<script>
new Chart(document.getElementById('pesoHistChart'),{type:'line',data:{labels:$chartLabels,datasets:[{label:'Peso (kg)',data:$chartData,borderColor:'#2d7a4e',backgroundColor:'rgba(45,122,78,0.07)',tension:0.35,fill:true,pointRadius:4,pointBackgroundColor:'#2d7a4e'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:false,grid:{color:'#f0f4f0'}},x:{grid:{display:false}}}}});
</script>
JS;
endif;
