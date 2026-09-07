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
$chartLabels   = json_encode(array_map(fn($p) => formatDate($p['data']), $chartPesagens));
$chartData     = json_encode(array_map(fn($p) => (float)$p['peso'], $chartPesagens));

$pesoAtual = !empty($pesagens) ? $pesagens[0]['peso'] : $animal['peso_inicial'];
$isPuppy   = isFilhote($animal['data_nascimento']);
?>

<!-- Barra de Navegação Superior -->
<div class="mb-3 d-flex justify-content-between align-items-center">
  <a href="/animais" class="btn btn-sm btn-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar ao Rebanho
  </a>
  <?php if ($fotoFilhote && !$isPuppy): ?>
    <span class="badge-status ativo" style="background:#e8f0e5; border-color:#c6dfbd;">
      <i class="bi bi-stars text-success me-1"></i> Memória de Filhote disponível
    </span>
  <?php endif; ?>
</div>

<!-- Ficha / Prontuário Técnico do Animal (Anti-Generic Hero Card) -->
<div class="card mb-3">
  <div class="card-body p-3">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
      <div class="d-flex align-items-center gap-3">
        <?php if (!empty($animal['foto_url'])): ?>
          <img src="<?= e($animal['foto_url']) ?>" alt="Foto" class="animal-hero-thumb" onclick="abrirFotoZoom('<?= e($animal['foto_url']) ?>', 'Animal <?= e($animal['brinco']) ?>', 'Foto de Identificação / Perfil')" title="Clique para ampliar">
        <?php else: ?>
          <div class="table-animal-avatar" style="width: 76px; height: 76px; font-size: 1.4rem;">
            <?= e(substr($animal['brinco'], 0, 3)) ?>
          </div>
        <?php endif; ?>

        <div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <h3 class="mb-0 fw-800 tabular-nums" style="color:var(--earth-green-950); letter-spacing:-0.03em;">
              <?= e($animal['brinco']) ?>
            </h3>
            <?php if ($animal['nome']): ?>
              <span class="text-secondary fw-600">"<?= e($animal['nome']) ?>"</span>
            <?php endif; ?>
            <?= statusBadge($animal['status']) ?>
            <?php if ($isPuppy): ?>
              <span class="badge-status ativo"><i class="bi bi-stars me-1"></i>Bezerro</span>
            <?php endif; ?>
          </div>

          <!-- Metadados em Pílulas Técnicas -->
          <div class="mt-2 d-flex flex-wrap gap-2 text-secondary small">
            <span class="badge bg-light text-dark border"><i class="bi bi-tag me-1"></i><?= e($animal['raca'] ?? 'Nelore') ?></span>
            <span class="badge bg-light text-dark border"><i class="bi bi-gender-ambiguous me-1"></i><?= sexoLabel($animal['sexo']) ?></span>
            <span class="badge bg-light text-dark border tabular-nums"><i class="bi bi-calendar3 me-1"></i><?= calcIdade($animal['data_nascimento']) ?></span>
            <span class="badge bg-light text-dark border"><i class="bi bi-tree me-1"></i>Pasto: <?= e($pasto['nome'] ?? 'Não alocado') ?></span>
          </div>
        </div>
      </div>

      <!-- Ações de Manejo Direto -->
      <div class="d-flex gap-2 flex-wrap">
        <a href="/animais/<?= $animal['id'] ?>/editar" class="btn btn-secondary btn-sm">
          <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <a href="/pesagens/novo?animal_id=<?= $animal['id'] ?>" class="btn btn-primary btn-sm">
          <i class="bi bi-rulers me-1"></i>Pesar
        </a>
        <a href="/saude/novo?animal_id=<?= $animal['id'] ?>" class="btn btn-secondary btn-sm">
          <i class="bi bi-heart-pulse me-1"></i>Saúde
        </a>
        <?php if ($animal['sexo'] === 'F'): ?>
          <a href="/reproducao/novo?animal_id=<?= $animal['id'] ?>" class="btn btn-secondary btn-sm">
            <i class="bi bi-diagram-3 me-1"></i>Reprodução
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Coluna Esquerda: Biometria e Genealogia -->
  <div class="col-md-3">
    <!-- Card de Peso Atual & Desempenho -->
    <div class="card text-center p-3 mb-3">
      <span class="metric-label">Peso Atual</span>
      <div class="metric-value my-1 tabular-nums">
        <?= $pesoAtual ? number_format($pesoAtual, 1) : '—' ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">kg</span>
      </div>
      <?php if (count($pesagens) >= 2): ?>
        <?php 
          $diff = $pesagens[0]['peso'] - $pesagens[1]['peso']; 
          $dias = max(1, (new DateTime($pesagens[0]['data']))->diff(new DateTime($pesagens[1]['data']))->days);
          $gmd = $diff / $dias;
        ?>
        <div class="small fw-600 <?= $diff >= 0 ? 'text-success' : 'text-danger' ?> tabular-nums">
          <?= $diff >= 0 ? '+' : '' ?><?= number_format($diff, 1) ?> kg na última pesagem
          <br><small class="text-muted">(GMD: <?= number_format($gmd, 2) ?> kg/dia em <?= $dias ?>d)</small>
        </div>
      <?php else: ?>
        <small class="text-muted">Apenas peso inicial/único</small>
      <?php endif; ?>
    </div>

    <!-- Memória de Filhote -->
    <?php if ($fotoFilhote): ?>
    <div class="card mb-3">
      <div class="card-header py-2">
        <h6 class="small mb-0"><i class="bi bi-stars text-success me-1"></i>Foto de Nascimento</h6>
      </div>
      <div class="card-body p-2 text-center">
        <img src="<?= e($fotoFilhote['foto_url']) ?>" alt="Foto filhote" class="img-fluid rounded border mb-1" style="max-height: 140px; width: 100%; object-fit: cover; cursor: pointer;" onclick="abrirFotoZoom('<?= e($fotoFilhote['foto_url']) ?>', 'Animal <?= e($animal['brinco']) ?> - Nascimento', 'Foto de nascimento / filhote')" title="Clique para ampliar">
        <small class="text-muted d-block tabular-nums"><?= formatDate($fotoFilhote['data']) ?></small>
      </div>
    </div>
    <?php endif; ?>

    <!-- Ficha de Genealogia e Dados Gerais -->
    <div class="card">
      <div class="card-header py-2">
        <h6 class="small mb-0">Genealogia & Origem</h6>
      </div>
      <div class="card-body p-3 small">
        <div class="mb-2">
          <span class="text-muted d-block">Origem</span>
          <strong class="text-primary"><?= e($animal['origem'] ?? 'Própria fazenda') ?></strong>
        </div>
        <div class="mb-2">
          <span class="text-muted d-block">Pai (Touro / Inseminação)</span>
          <strong class="text-primary"><?= e($animal['pai_brinco'] ?? 'Não informado') ?></strong>
        </div>
        <div class="mb-2">
          <span class="text-muted d-block">Mãe (Matriz)</span>
          <strong class="text-primary"><?= e($animal['mae_brinco'] ?? 'Não informada') ?></strong>
        </div>
        <div>
          <span class="text-muted d-block">Observações</span>
          <span class="text-secondary"><?= e($animal['observacao'] ?? 'Sem observações adicionais.') ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Coluna Direita: Curva de Peso, Histórico Clínico e Linha do Tempo -->
  <div class="col-md-9">
    <!-- Linha do Tempo Visual (Fotos) -->
    <div class="card mb-3">
      <div class="card-header justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-camera text-secondary"></i>
          <h6>Linha do Tempo Fotográfica (<?= count($fotos) ?> fotos)</h6>
        </div>
        <div class="d-flex align-items-center gap-2">
          <?php if (!empty($fotos)): ?>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="btnToggleAllCensura" onclick="toggleAllCensura()" title="Alternar censura de todas as fotos clínicas">
              <i class="bi bi-eye me-1"></i> Revelar Todas
            </button>
          <?php endif; ?>
          <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#formFotoCollapse">
            <i class="bi bi-plus-lg me-1"></i> Nova Foto
          </button>
        </div>
      </div>

      <!-- Upload Colapsável -->
      <div class="collapse border-bottom" id="formFotoCollapse">
        <div class="p-3" style="background-color: var(--bg-subtle);">
          <form method="POST" action="/animais/<?= $animal['id'] ?>/foto" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-2 align-items-end">
              <div class="col-md-4">
                <label class="form-label small fw-bold">Arquivo de Imagem *</label>
                <input type="file" name="foto" class="form-control form-control-sm" accept="image/*" capture="environment" required>
              </div>
              <div class="col-md-3">
                <label class="form-label small fw-bold">Tipo de Registro</label>
                <select name="tipo_evento" class="form-select form-select-sm">
                  <option value="perfil">Perfil / Geral</option>
                  <option value="nascimento">Nascimento / Bezerro</option>
                  <option value="pesagem">Pesagem</option>
                  <option value="saude">Tratamento / Manejo</option>
                  <option value="obito">Óbito / Necropsia</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label small fw-bold">Data</label>
                <input type="date" name="data" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label small fw-bold">Observação</label>
                <input type="text" name="observacao" class="form-control form-control-sm" placeholder="Opcional">
              </div>
              <div class="col-12 mt-2 d-flex justify-content-between align-items-center">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="is_sensivel" value="1" id="checkSensivel">
                  <label class="form-check-label small text-secondary" for="checkSensivel">
                    <i class="bi bi-eye-slash text-warning me-1"></i> Censurar por padrão (conteúdo clínico/óbito)
                  </label>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">
                  <i class="bi bi-cloud-arrow-up me-1"></i> Salvar Foto
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <div class="card-body">
        <?php if (empty($fotos)): ?>
          <div class="text-center text-muted py-4 small">
            <i class="bi bi-images fs-3 d-block mb-2 text-muted"></i>
            Nenhuma foto registrada neste prontuário.
          </div>
        <?php else: ?>
          <div class="galeria-grid">
            <?php foreach ($fotos as $f): ?>
              <?php 
                $tipoLower = strtolower(trim($f['tipo_evento'] ?? ''));
                $obsLower  = strtolower(trim($f['observacao'] ?? ''));
                $isCensurada = (
                    $f['is_sensivel'] == 1 ||
                    in_array($tipoLower, ['obito', 'necropsia']) ||
                    str_contains($obsLower, 'óbito') ||
                    str_contains($obsLower, 'obito') ||
                    str_contains($obsLower, 'morte') ||
                    str_contains($obsLower, 'necropsia')
                );
                $tipoIconMap = [
                  'nascimento' => ['icon' => 'bi-stars', 'label' => 'Nascimento'],
                  'pesagem'    => ['icon' => 'bi-rulers', 'label' => 'Pesagem'],
                  'saude'      => ['icon' => 'bi-heart-pulse', 'label' => 'Saúde'],
                  'obito'      => ['icon' => 'bi-exclamation-triangle', 'label' => 'Óbito'],
                  'perfil'     => ['icon' => 'bi-camera', 'label' => 'Perfil']
                ];
                $tipoInfo = $tipoIconMap[$f['tipo_evento']] ?? ['icon' => 'bi-image', 'label' => 'Foto'];
                $labelFoto = $tipoInfo['label'] . ' (' . formatDate($f['data']) . ')';
              ?>
              <div class="foto-card <?= $isCensurada ? 'foto-censurada' : '' ?>" id="foto-card-<?= $f['id'] ?>" data-censurada-original="<?= $isCensurada ? '1' : '0' ?>">
                <div class="foto-thumb-container" onclick="handleThumbClick(<?= $f['id'] ?>, '<?= e($f['foto_url']) ?>', '<?= e($labelFoto) ?>', '<?= e(addslashes($f['observacao'] ?? '')) ?>')" title="<?= $isCensurada ? 'Clique para revelar' : 'Clique para ampliar' ?>">
                  <img src="<?= e($f['foto_url']) ?>" alt="Foto" class="foto-img" loading="lazy">
                  <div class="foto-overlay-censura" style="<?= $isCensurada ? 'display: flex;' : 'display: none;' ?>">
                    <i class="bi bi-eye-slash-fill"></i>
                    <span class="censura-text-title">Conteúdo Sensível</span>
                    <span class="censura-text-sub">Toque para visualizar</span>
                  </div>
                </div>
                <div class="p-2 d-flex flex-column justify-content-between" style="min-height: 75px;">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge bg-light text-dark border small d-inline-flex align-items-center gap-1" style="font-size:.7rem;">
                      <i class="bi <?= $tipoInfo['icon'] ?>"></i> <?= $tipoInfo['label'] ?>
                    </span>
                    <small class="text-muted tabular-nums" style="font-size:.72rem;"><?= formatDate($f['data']) ?></small>
                  </div>
                  <?php if (!empty($f['observacao'])): ?>
                    <div class="text-secondary small text-truncate" title="<?= e($f['observacao']) ?>" style="font-size:.75rem;">
                      <?= e($f['observacao']) ?>
                    </div>
                  <?php endif; ?>
                  <div class="mt-2 pt-1 border-top d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                      <button type="button" class="btn btn-link text-secondary p-0 small text-decoration-none btn-card-revelar" onclick="toggleCensuraCard(<?= $f['id'] ?>)" style="font-size:.72rem;" title="Alternar censura">
                        <i class="bi <?= $isCensurada ? 'bi-eye' : 'bi-eye-slash' ?>"></i> <?= $isCensurada ? 'Revelar' : 'Censurar' ?>
                      </button>
                      <a href="<?= e($f['foto_url']) ?>" download="animal_<?= e($animal['brinco']) ?>_foto_<?= $f['id'] ?>" target="_blank" class="btn btn-link text-primary p-0 small text-decoration-none" style="font-size:.72rem;" title="Baixar foto original em alta resolução">
                        <i class="bi bi-download"></i> Baixar
                      </a>
                    </div>
                    <form method="POST" action="/fotos/<?= $f['id'] ?>/excluir" onsubmit="return confirm('Excluir esta foto?')">
                      <?= csrf_field() ?>
                      <button class="btn btn-link text-danger p-0 small text-decoration-none" style="font-size:.72rem;"><i class="bi bi-trash"></i> Excluir</button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Curva de Crescimento e Tabela de Pesagens -->
    <div class="card mb-3">
      <div class="card-header justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-graph-up text-secondary"></i>
          <h6>Curva de Ganho de Peso</h6>
        </div>
        <a href="/pesagens/novo?animal_id=<?= $animal['id'] ?>" class="btn btn-sm btn-primary">+ Registrar Peso</a>
      </div>
      <div class="card-body">
        <?php if (empty($chartPesagens)): ?>
          <div class="text-center text-muted py-3 small">Nenhuma pesagem lançada para este animal.</div>
        <?php else: ?>
          <div class="chart-container" style="height:170px;"><canvas id="pesoHistChart"></canvas></div>
        <?php endif; ?>
      </div>

      <?php if (!empty($pesagens)): ?>
      <div class="card-body border-top p-0">
        <div class="table-responsive" style="border:none; border-radius:0;">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Data</th>
                <th>Peso</th>
                <th>Ganho</th>
                <th>GMD (kg/dia)</th>
                <th>Origem</th>
                <th>Obs.</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pesagens as $idx => $p): ?>
                <?php 
                  $ganho = ($idx < count($pesagens) - 1) ? ($p['peso'] - $pesagens[$idx + 1]['peso']) : null; 
                  $dias  = ($idx < count($pesagens) - 1) ? max(1, (new DateTime($p['data']))->diff(new DateTime($pesagens[$idx + 1]['data']))->days) : null;
                  $gmd   = ($ganho !== null && $dias) ? ($ganho / $dias) : null;
                ?>
                <tr>
                  <td class="tabular-nums small"><?= formatDate($p['data']) ?></td>
                  <td class="fw-bold tabular-nums"><?= number_format($p['peso'], 1) ?> <small class="text-muted">kg</small></td>
                  <td class="tabular-nums small">
                    <?php if ($ganho !== null): ?>
                      <span class="<?= $ganho >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold' ?>">
                        <?= $ganho >= 0 ? '+' : '' ?><?= number_format($ganho, 1) ?> kg
                      </span>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td class="tabular-nums small text-secondary">
                    <?= $gmd !== null ? number_format($gmd, 2) . ' kg/d' : '—' ?>
                  </td>
                  <td class="small text-muted"><?= e($p['origem'] ?? 'web') ?></td>
                  <td class="small text-muted"><?= e($p['observacao'] ?? '—') ?></td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <a href="/pesagens/<?= $p['id'] ?>/editar" class="btn btn-secondary btn-sm" title="Editar"><i class="bi bi-pencil"></i></a>
                      <form method="POST" action="/pesagens/<?= $p['id'] ?>/excluir" style="display:inline" onsubmit="return confirm('Excluir esta pesagem?')">
                        <?= csrf_field() ?>
                        <button class="btn btn-secondary btn-sm text-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Histórico Clínico & Reprodução -->
    <div class="row g-3">
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-header justify-content-between">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-heart-pulse text-secondary"></i>
              <h6>Manejo Sanitário</h6>
            </div>
            <a href="/saude/novo?animal_id=<?= $animal['id'] ?>" class="btn btn-sm btn-secondary">+ Adicionar</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($saude_list)): ?>
              <div class="text-center text-muted py-4 small">Nenhum evento sanitário registrado.</div>
            <?php else: ?>
              <div class="table-responsive" style="border:none; border-radius:0;">
                <table class="table table-sm">
                  <tbody>
                    <?php foreach ($saude_list as $s): ?>
                    <tr>
                      <td>
                        <div class="fw-bold small"><?= e($s['tipo']) ?></div>
                        <div class="text-secondary small"><?= e($s['descricao']) ?></div>
                        <?php if ($s['medicamento']): ?>
                          <div class="text-muted small mt-1"><i class="bi bi-capsule me-1"></i><?= e($s['medicamento']) ?><?= $s['dose'] ? ' ('.e($s['dose']).')' : '' ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="text-end small tabular-nums text-muted text-nowrap">
                        <?= formatDate($s['data']) ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-header justify-content-between">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-diagram-3 text-secondary"></i>
              <h6>Histórico Reprodutivo</h6>
            </div>
            <a href="/reproducao/novo?animal_id=<?= $animal['id'] ?>" class="btn btn-sm btn-secondary">+ Adicionar</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($repro_list)): ?>
              <div class="text-center text-muted py-4 small">Nenhum evento reprodutivo registrado.</div>
            <?php else: ?>
              <div class="table-responsive" style="border:none; border-radius:0;">
                <table class="table table-sm">
                  <tbody>
                    <?php foreach ($repro_list as $r): ?>
                    <tr>
                      <td>
                        <div class="fw-bold small"><?= e($r['tipo']) ?></div>
                        <div class="text-secondary small"><?= e($r['resultado'] ?? '—') ?></div>
                      </td>
                      <td class="text-end small tabular-nums text-muted text-nowrap">
                        <?= formatDate($r['data']) ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Lightbox / Zoom da Foto -->
<div class="modal fade" id="modalFotoZoom" tabindex="-1" aria-labelledby="modalFotoZoomLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background-color: var(--bg-surface); border: 1px solid var(--border-subtle); overflow: hidden;">
      <div class="modal-header border-bottom py-2">
        <h6 class="modal-title d-flex align-items-center gap-2" id="modalFotoZoomLabel">
          <i class="bi bi-image text-primary"></i> <span id="modalFotoZoomTitulo">Visualização da Foto</span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body p-2 text-center" style="background: #0d140e;">
        <img src="" id="modalFotoZoomImg" class="modal-foto-zoom-img" alt="Foto Ampliada">
        <p id="modalFotoZoomObs" class="text-light small mt-2 mb-1 px-3 text-center" style="opacity: 0.88;"></p>
      </div>
      <div class="modal-footer border-top py-2 d-flex justify-content-between align-items-center">
        <span class="small text-muted" id="modalFotoZoomAnimal">Animal: <strong><?= e($animal['brinco']) ?></strong></span>
        <div class="d-flex gap-2">
          <a href="#" id="modalFotoZoomDownload" download target="_blank" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-download me-1"></i> Baixar Original
          </a>
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function handleThumbClick(id, url, titulo, obs) {
  const card = document.getElementById('foto-card-' + id);
  if (!card) return;
  // Se está com censura ativa, o clique revela a foto
  if (card.classList.contains('foto-censurada')) {
    toggleCensuraCard(id);
  } else {
    // Se já está visível, abre no Lightbox modal em alta resolução
    abrirFotoZoom(url, titulo, obs);
  }
}

function toggleCensuraCard(id) {
  const card = document.getElementById('foto-card-' + id);
  if (!card) return;
  card.classList.toggle('foto-censurada');
  const isCensured = card.classList.contains('foto-censurada');
  const overlay = card.querySelector('.foto-overlay-censura');
  const btn = card.querySelector('.btn-card-revelar');
  if (overlay) overlay.style.display = isCensured ? 'flex' : 'none';
  if (btn) {
    btn.innerHTML = isCensured ? '<i class="bi bi-eye"></i> Revelar' : '<i class="bi bi-eye-slash"></i> Censurar';
  }
}

function toggleAllCensura() {
  const cards = document.querySelectorAll('.foto-card');
  const btn = document.getElementById('btnToggleAllCensura');
  const anyCensored = Array.from(cards).some(c => c.classList.contains('foto-censurada'));
  cards.forEach(c => {
    const overlay = c.querySelector('.foto-overlay-censura');
    const cardBtn = c.querySelector('.btn-card-revelar');
    if (anyCensored) {
      c.classList.remove('foto-censurada');
      if (overlay) overlay.style.display = 'none';
      if (cardBtn) cardBtn.innerHTML = '<i class="bi bi-eye-slash"></i> Censurar';
    } else {
      if (c.getAttribute('data-censurada-original') === '1') {
        c.classList.add('foto-censurada');
        if (overlay) overlay.style.display = 'flex';
        if (cardBtn) cardBtn.innerHTML = '<i class="bi bi-eye"></i> Revelar';
      }
    }
  });
  if (btn) {
    btn.innerHTML = anyCensored ? '<i class="bi bi-eye-slash me-1"></i> Ocultar Sensíveis' : '<i class="bi bi-eye me-1"></i> Revelar Todas';
  }
}

function abrirFotoZoom(url, titulo, obs) {
  const modalEl = document.getElementById('modalFotoZoom');
  if (!modalEl) return;
  const imgEl = document.getElementById('modalFotoZoomImg');
  const titleEl = document.getElementById('modalFotoZoomTitulo');
  const obsEl = document.getElementById('modalFotoZoomObs');
  const dlBtn = document.getElementById('modalFotoZoomDownload');
  
  if (imgEl) imgEl.src = url;
  if (titleEl) titleEl.textContent = titulo || 'Visualização da Foto';
  if (obsEl) {
    if (obs && obs.trim() !== '') {
      obsEl.textContent = obs;
      obsEl.style.display = 'block';
    } else {
      obsEl.textContent = '';
      obsEl.style.display = 'none';
    }
  }
  if (dlBtn) {
    dlBtn.href = url;
    dlBtn.setAttribute('download', 'foto_animal_' + encodeURIComponent(titulo || 'registro'));
  }
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
}
</script>

<?php if (!empty($chartPesagens)):
$scripts = <<<JS
<script>
new Chart(document.getElementById('pesoHistChart'), {
  type: 'line',
  data: {
    labels: $chartLabels,
    datasets: [{
      label: 'Peso (kg)',
      data: $chartData,
      borderColor: '#33592a',
      backgroundColor: 'rgba(51, 89, 42, 0.08)',
      borderWidth: 2,
      tension: 0.25,
      fill: true,
      pointRadius: 3,
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
        titleFont: { family: 'Inter', size: 11 },
        bodyFont: { family: 'Inter', size: 11 }
      }
    },
    scales: {
      y: {
        beginAtZero: false,
        grid: { color: '#e6e4dc' },
        ticks: { font: { family: 'Inter', size: 10 }, color: '#78716c' }
      },
      x: {
        grid: { display: false },
        ticks: { font: { family: 'Inter', size: 10 }, color: '#78716c' }
      }
    }
  }
});
</script>
JS;
endif;
?>
