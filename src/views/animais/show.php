<?php
$pesagens   = $db->prepare("SELECT * FROM pesagens WHERE animal_id=? ORDER BY data DESC, id DESC LIMIT 20");
$pesagens->execute([$animal['id']]);
$pesagens   = $pesagens->fetchAll();

$saude_list = $db->prepare("SELECT * FROM saude WHERE animal_id=? ORDER BY data DESC, id DESC LIMIT 10");
$saude_list->execute([$animal['id']]);
$saude_list = $saude_list->fetchAll();

$repro_list = $db->prepare("SELECT * FROM reproducao WHERE animal_id=? ORDER BY data DESC, id DESC LIMIT 10");
$repro_list->execute([$animal['id']]);
$repro_list = $repro_list->fetchAll();

$pastoStmt = $db->prepare("SELECT * FROM pastagens WHERE id=?");
$pastoStmt->execute([$animal['pasto_id'] ?: 0]);
$pasto = $animal['pasto_id'] ? ($pastoStmt->fetch() ?: null) : null;

$fotosStmt = $db->prepare("SELECT * FROM fotos_animais WHERE animal_id=? ORDER BY data DESC, id DESC");
$fotosStmt->execute([$animal['id']]);
$fotos = $fotosStmt->fetchAll();

// Encontra foto de nascimento/filhote
$fotoFilhote = null;
foreach ($fotos as $f) {
    if ($f['fase'] === 'filhote' || $f['tipo_evento'] === 'nascimento') {
        $fotoFilhote = $f;
        break;
    }
}

$chartStmt = $db->prepare("
  SELECT data, peso FROM (
    SELECT id, data, peso FROM pesagens WHERE animal_id=? ORDER BY data DESC, id DESC LIMIT 12
  ) sub ORDER BY data ASC, id ASC
");
$chartStmt->execute([$animal['id']]);
$chartPesagens = $chartStmt->fetchAll();
$chartLabels   = json_encode(array_map(fn($p) => $p['data'], $chartPesagens));
$chartData     = json_encode(array_map(fn($p) => (float)$p['peso'], $chartPesagens));

$pesoAtual = !empty($pesagens) ? $pesagens[0]['peso'] : $animal['peso_inicial'];
$sexoEmoji = $animal['sexo'] === 'M' ? '🐂' : '🐄';
$isPuppy   = isFilhote($animal['data_nascimento']);
?>
<div class="mb-3 d-flex justify-content-between align-items-center">
  <a href="/animais" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
  <?php if ($fotoFilhote && !$isPuppy): ?>
    <span class="badge-filhote"><i class="bi bi-stars"></i> Foto de Filhote salva no histórico</span>
  <?php endif; ?>
</div>

<div class="animal-profile-header mb-3">
  <div class="d-flex align-items-start gap-3 flex-wrap">
    <?php if (!empty($animal['foto_url'])): ?>
      <img src="<?= e($animal['foto_url']) ?>" alt="Foto" class="rounded-3 border border-white border-2 shadow" style="width: 76px; height: 76px; object-fit: cover;">
    <?php else: ?>
      <div class="animal-big-avatar"><?= $sexoEmoji ?></div>
    <?php endif; ?>
    <div class="flex-grow-1">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <h3 class="mb-0 fw-800"><?= e($animal['brinco']) ?></h3>
        <?php if ($animal['nome']): ?><span class="opacity-75">"<?= e($animal['nome']) ?>"</span><?php endif; ?>
        <span class="badge bg-white text-dark"><?= statusBadge($animal['status']) ?></span>
        <?php if ($isPuppy): ?>
          <span class="badge bg-success"><i class="bi bi-egg-fried me-1"></i>Bezerro / Filhote</span>
        <?php endif; ?>
      </div>
      <div class="mt-2 d-flex flex-wrap gap-3">
        <span><small class="opacity-60">Raça</small><br><strong><?= e($animal['raca'] ?? '—') ?></strong></span>
        <span><small class="opacity-60">Sexo</small><br><strong><?= sexoLabel($animal['sexo']) ?></strong></span>
        <span><small class="opacity-60">Idade</small><br><strong><?= calcIdade($animal['data_nascimento']) ?></strong></span>
        <span><small class="opacity-60">Nascimento</small><br><strong><?= formatDate($animal['data_nascimento']) ?></strong></span>
        <span><small class="opacity-60">Pastagem</small><br><strong><?= e($pasto['nome'] ?? '—') ?></strong></span>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="/animais/<?= $animal['id'] ?>/editar" class="btn btn-light btn-sm"><i class="bi bi-pencil me-1"></i>Editar</a>
      <a href="/pesagens/novo?animal_id=<?= $animal['id'] ?>" class="btn btn-warning btn-sm"><i class="bi bi-rulers me-1"></i>Pesar</a>
      <a href="/saude/novo?animal_id=<?= $animal['id'] ?>" class="btn btn-danger btn-sm"><i class="bi bi-heart-pulse me-1"></i>Saúde</a>
      <a href="/reproducao/novo?animal_id=<?= $animal['id'] ?>" class="btn btn-info btn-sm"><i class="bi bi-diagram-3 me-1"></i>Reprodução</a>
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

    <!-- Memória de Filhote Card -->
    <?php if ($fotoFilhote): ?>
    <div class="card mt-3 border-success border-opacity-25">
      <div class="card-header bg-success bg-opacity-10 py-2 d-flex align-items-center gap-2">
        <i class="bi bi-stars text-success"></i><h6 class="mb-0 text-success fw-bold small">Memória de Filhote</h6>
      </div>
      <div class="card-body p-2 text-center">
        <img src="<?= e($fotoFilhote['foto_url']) ?>" alt="Foto filhote" class="img-fluid rounded border shadow-sm mb-2" style="max-height: 140px; object-fit: cover;">
        <div class="small text-muted"><?= formatDate($fotoFilhote['data']) ?></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card mt-3">
      <div class="card-body p-3">
        <div class="info-label">Origem</div>
        <div class="info-value mb-2"><?= e($animal['origem'] ?? '—') ?></div>
        <div class="info-label">Pai (Touro)</div>
        <div class="info-value mb-2"><?= e($animal['pai_brinco'] ?? '—') ?></div>
        <div class="info-label">Observações</div>
        <div class="info-value"><?= e($animal['observacao'] ?? '—') ?></div>
      </div>
    </div>
  </div>

  <div class="col-md-9">
    <!-- Linha do Tempo Fotográfica -->
    <div class="card mb-3">
      <div class="card-header justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-camera-fill text-primary"></i>
          <h6>Galeria & Linha do Tempo Visual (<?= count($fotos) ?> fotos)</h6>
        </div>
        <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#formFotoCollapse">
          <i class="bi bi-plus-lg me-1"></i> Nova Foto
        </button>
      </div>

      <!-- Formulário de Upload de Foto (Colapsável) -->
      <div class="collapse border-bottom" id="formFotoCollapse">
        <div class="p-3 bg-light">
          <form method="POST" action="/animais/<?= $animal['id'] ?>/foto" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-2 align-items-end">
              <div class="col-md-4">
                <label class="form-label small fw-bold">Arquivo de Imagem *</label>
                <input type="file" name="foto" class="form-control form-control-sm" accept="image/*" required>
              </div>
              <div class="col-md-3">
                <label class="form-label small fw-bold">Tipo de Evento</label>
                <select name="tipo_evento" class="form-select form-select-sm" id="tipoEventoSelect">
                  <option value="perfil">📸 Perfil / Geral</option>
                  <option value="nascimento">🐣 Nascimento / Filhote</option>
                  <option value="pesagem">⚖️ Pesagem</option>
                  <option value="saude">⚕️ Saúde / Manejo</option>
                  <option value="obito">⚠️ Óbito / Morte</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label small fw-bold">Data</label>
                <input type="date" name="data" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label small fw-bold">Observação</label>
                <input type="text" name="observacao" class="form-control form-control-sm" placeholder="Detalhes...">
              </div>
              <div class="col-12 mt-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="is_sensivel" value="1" id="checkSensivel">
                  <label class="form-check-label small" for="checkSensivel">
                    ⚠️ Censurar por padrão (Desfoque de conteúdo sensível / óbito)
                  </label>
                </div>
                <button type="submit" class="btn btn-sm btn-success">
                  <i class="bi bi-cloud-arrow-up me-1"></i> Salvar Foto
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <div class="card-body">
        <?php if (empty($fotos)): ?>
          <div class="text-center text-muted py-4">
            <i class="bi bi-images fs-2 d-block opacity-50 mb-2"></i>
            Nenhuma foto registrada na linha do tempo deste animal.<br>
            <small>Clique em "Nova Foto" acima para registrar nascimento, pesagem ou manejo.</small>
          </div>
        <?php else: ?>
          <div class="galeria-grid">
            <?php foreach ($fotos as $f): ?>
              <?php 
                $isCensurada = ($f['is_sensivel'] == 1 || $f['tipo_evento'] === 'obito' || $animal['status'] === 'morto');
                $tipoIconMap = [
                  'nascimento' => '🐣 Nascimento',
                  'pesagem'    => '⚖️ Pesagem',
                  'saude'      => '⚕️ Saúde',
                  'obito'      => '⚠️ Óbito',
                  'perfil'     => '📸 Perfil'
                ];
                $tipoTexto = $tipoIconMap[$f['tipo_evento']] ?? '📸 Foto';
              ?>
              <div class="foto-card <?= $isCensurada ? 'foto-censurada' : '' ?>">
                <div class="foto-thumb-container">
                  <img src="<?= e($f['foto_url']) ?>" alt="Foto do animal" class="foto-img">
                  <?php if ($isCensurada): ?>
                    <div class="foto-overlay-censura" onclick="toggleCensura(this)">
                      <i class="bi bi-eye-slash-fill"></i>
                      <span>Conteúdo Sensível<br><small class="opacity-75">Clique para ver</small></span>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="p-2 d-flex flex-column justify-content-between" style="min-height: 70px;">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge bg-light text-dark border small" style="font-size:.7rem;"><?= $tipoTexto ?></span>
                    <small class="text-muted" style="font-size:.72rem;"><?= formatDate($f['data']) ?></small>
                  </div>
                  <?php if (!empty($f['observacao'])): ?>
                    <div class="text-muted small text-truncate" title="<?= e($f['observacao']) ?>" style="font-size:.75rem;">
                      <?= e($f['observacao']) ?>
                    </div>
                  <?php endif; ?>
                  <div class="mt-1 pt-1 border-top d-flex justify-content-end">
                    <form method="POST" action="/fotos/<?= $f['id'] ?>/excluir" onsubmit="return confirm('Remover esta foto?')">
                      <?= csrf_field() ?>
                      <button class="btn btn-link text-danger p-0 small" style="font-size:.75rem;" title="Remover"><i class="bi bi-trash"></i> Excluir</button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

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
          <thead><tr><th>Data</th><th>Peso</th><th>Ganho</th><th>Origem</th><th>Obs.</th><th class="text-end">Ações</th></tr></thead>
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
                <td class="text-end">
                  <div class="d-flex justify-content-end gap-1">
                    <a href="/pesagens/<?= $p['id'] ?>/editar" class="btn btn-sm btn-outline-primary py-0 px-2" title="Editar"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="/pesagens/<?= $p['id'] ?>/excluir" onsubmit="return confirm('Excluir pesagem?')">
                      <?= csrf_field() ?>
                      <button class="btn btn-sm btn-outline-danger py-0 px-2" title="Excluir"><i class="bi bi-trash"></i></button>
                    </form>
                  </div>
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
                    <?php if ($s['medicamento']): ?><div class="text-info" style="font-size:.72rem">💊 <?= e($s['medicamento']) ?><?= $s['dose'] ? ' ('.e($s['dose']).')' : '' ?></div><?php endif; ?>
                  </td>
                  <td class="text-end small text-muted text-nowrap">
                    <div><?= formatDate($s['data']) ?></div>
                    <div class="mt-1 d-flex justify-content-end gap-1">
                      <a href="/saude/<?= $s['id'] ?>/editar" class="btn btn-sm btn-outline-primary py-0 px-1" title="Editar"><i class="bi bi-pencil" style="font-size:.75rem"></i></a>
                      <form method="POST" action="/saude/<?= $s['id'] ?>/excluir" onsubmit="return confirm('Excluir registro de saúde?')">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Excluir"><i class="bi bi-trash" style="font-size:.75rem"></i></button>
                      </form>
                    </div>
                  </td>
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
                    <div class="text-muted" style="font-size:.75rem"><?= e($r['resultado'] ?? '—') ?></div>
                  </td>
                  <td class="text-end small text-muted text-nowrap">
                    <div><?= formatDate($r['data']) ?></div>
                    <div class="mt-1 d-flex justify-content-end gap-1">
                      <a href="/reproducao/<?= $r['id'] ?>/editar" class="btn btn-sm btn-outline-primary py-0 px-1" title="Editar"><i class="bi bi-pencil" style="font-size:.75rem"></i></a>
                      <form method="POST" action="/reproducao/<?= $r['id'] ?>/excluir" onsubmit="return confirm('Excluir registro reprodutivo?')">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Excluir"><i class="bi bi-trash" style="font-size:.75rem"></i></button>
                      </form>
                    </div>
                  </td>
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

<script>
function toggleCensura(el) {
  const card = el.closest('.foto-card');
  if (card) {
    card.classList.toggle('foto-censurada');
    el.style.display = card.classList.contains('foto-censurada') ? 'flex' : 'none';
  }
}
</script>

<?php if (!empty($chartPesagens)):
$scripts = <<<JS
<script>
new Chart(document.getElementById('pesoHistChart'),{type:'line',data:{labels:$chartLabels,datasets:[{label:'Peso (kg)',data:$chartData,borderColor:'#2d7a4e',backgroundColor:'rgba(45,122,78,0.07)',tension:0.35,fill:true,pointRadius:4,pointBackgroundColor:'#2d7a4e'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:false,grid:{color:'#f0f4f0'}},x:{grid:{display:false}}}}});
</script>
JS;
endif;
?>
