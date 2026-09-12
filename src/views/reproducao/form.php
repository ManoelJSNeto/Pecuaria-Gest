<?php
if (!isset($db)) { $db = getDb(); }

// Filtro estrito: Matrizes reprodutivas devem ser fêmeas ativas com dados para o cockpit lateral
$animaisStmt = $db->query("
    SELECT a.id, a.brinco, a.nome, a.sexo, a.raca, a.data_nascimento, a.status, a.foto_url,
           p.nome as pasto_nome,
           (SELECT data FROM reproducao WHERE animal_id = a.id ORDER BY data DESC LIMIT 1) as ultimo_repro_data,
           (SELECT tipo FROM reproducao WHERE animal_id = a.id ORDER BY data DESC LIMIT 1) as ultimo_repro_tipo,
           (SELECT resultado FROM reproducao WHERE animal_id = a.id ORDER BY data DESC LIMIT 1) as ultimo_repro_resultado
    FROM animais a
    LEFT JOIN pastagens p ON a.pasto_id = p.id
    WHERE a.sexo = 'F' AND a.status != 'morto'
    ORDER BY a.brinco
");
$animais     = $animaisStmt->fetchAll();

// Touros reprodutores machos da propriedade para sugestão de cobertura
$tourosStmt  = $db->query("SELECT brinco, nome FROM animais WHERE sexo = 'M' AND status != 'morto' ORDER BY brinco");
$touros      = $tourosStmt->fetchAll();

$isEdit      = isset($reproducao);
$r           = $reproducao ?? [];
$preAnimal   = $r['animal_id'] ?? ($_GET['animal_id'] ?? null);

if (!empty($preAnimal)) {
    $found = false;
    foreach ($animais as $a) {
        if ($a['id'] == $preAnimal) { $found = true; break; }
    }
    if (!$found) {
        $extraA = $db->prepare("
            SELECT a.id, a.brinco, a.nome, a.sexo, a.raca, a.data_nascimento, a.status, a.foto_url,
                   p.nome as pasto_nome, NULL as ultimo_repro_data, NULL as ultimo_repro_tipo, NULL as ultimo_repro_resultado
            FROM animais a
            LEFT JOIN pastagens p ON a.pasto_id = p.id
            WHERE a.id = ?
        ");
        $extraA->execute([$preAnimal]);
        $ea = $extraA->fetch();
        if ($ea && $ea['sexo'] === 'F') {
            $animais[] = $ea;
        } else {
            $preAnimal = null;
        }
    }
}

$matrizesMap = [];
foreach ($animais as $an) {
    $matrizesMap[$an['id']] = [
        'id'          => $an['id'],
        'brinco'      => $an['brinco'],
        'nome'        => $an['nome'] ?: 'Sem nome',
        'raca'        => $an['raca'] ?: 'Nelore',
        'status'      => ucfirst($an['status']),
        'pasto'       => $an['pasto_nome'] ?: 'Sem pasto',
        'foto_url'    => $an['foto_url'] ?: null,
        'idade'       => calcIdade($an['data_nascimento']),
        'ultimo_data' => !empty($an['ultimo_repro_data']) ? formatDate($an['ultimo_repro_data']) : 'Nenhum registro anterior',
        'ultimo_tipo' => $an['ultimo_repro_tipo'] ?: 'Nenhum',
        'ultimo_res'  => $an['ultimo_repro_resultado'] ?: 'Sem diagnóstico'
    ];
}
?>
<!-- Topo com Breadcrumbs e Ajuda -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/reproducao' ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
    <span class="text-muted small">
      Reprodução &gt; <?= $isEdit ? 'Editar Manejo Reprodutivo' : 'Novo Evento Reprodutivo' ?>
    </span>
  </div>
  <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" onclick="abrirAjudaReproducao()">
    <i class="bi bi-info-circle"></i> <span>Instruções de Reprodução</span>
  </button>
</div>

<!-- Layout 2 Colunas Widescreen -->
<div class="row g-3">
  <!-- Coluna Esquerda: Formulário de Reprodução -->
  <div class="col-lg-7">
    <div class="aws-container">
      <div class="aws-container-header">
        <h6><i class="bi bi-diagram-3 text-primary"></i> <?= $isEdit ? 'Editar Evento Reprodutivo' : 'Registrar Cobertura, Inseminação ou Parto' ?></h6>
        <span class="text-muted small">Manejo zootécnico e controle de prenhez</span>
      </div>

      <div class="aws-container-body">
        <form method="POST" action="<?= $isEdit ? '/reproducao/'.$r['id'].'/atualizar' : '/reproducao/salvar' ?>" id="formReproducao">
          <?= csrf_field() ?>

          <div class="row g-3">
            <!-- 1. Matriz / Fêmea -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Matriz / Fêmea *</label>
              <select name="animal_id" id="selectMatrizRepro" class="form-select" required>
                <option value="">— Selecione a Fêmea —</option>
                <?php foreach ($animais as $a): ?>
                  <option value="<?= $a['id'] ?>" <?= $preAnimal == $a['id'] ? 'selected' : '' ?>>
                    <?= e($a['brinco']) ?> (Fêmea)<?= $a['nome']?' — '.e($a['nome']):'' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="aws-form-hint">Apenas matrizes fêmeas ativas são listadas.</div>
            </div>

            <!-- 2. Tipo de Evento -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Tipo de Manejo *</label>
              <select name="tipo" id="selectTipoRepro" class="form-select" required>
                <option value="">— Selecione —</option>
                <?php foreach (['Inseminação Artificial','Cobertura Natural','Diagnóstico de Gestação','Parto','Aborto','Desmame','Outro'] as $t): ?>
                  <option value="<?= $t ?>" <?= ($r['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
              <div class="aws-form-hint">Estágio do ciclo reprodutivo.</div>
            </div>

            <!-- 3. Data do Evento -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Data do Evento *</label>
              <input type="date" name="data" class="form-control" required value="<?= e($r['data'] ?? date('Y-m-d')) ?>">
            </div>

            <!-- 4. Touro / Sêmen -->
            <div class="col-md-6">
              <label class="form-label">Touro / Código do Sêmen</label>
              <input type="text" name="touro_brinco" id="inputTouroRepro" class="form-control text-uppercase" list="tourosReproList"
                     placeholder="Ex: TO-0042 ou palheta" value="<?= e($r['touro_brinco'] ?? '') ?>" autocomplete="off">
              <datalist id="tourosReproList">
                <?php foreach ($touros as $t): ?>
                  <option value="<?= e($t['brinco']) ?>"><?= e($t['brinco']) ?><?= $t['nome'] ? ' — '.e($t['nome']) : '' ?> (Touro da Fazenda)</option>
                <?php endforeach; ?>
              </datalist>
              <div class="aws-form-hint">Touro da propriedade ou código do sêmen utilizado.</div>
            </div>

            <!-- 5. Resultado -->
            <div class="col-12">
              <label class="form-label">Resultado / Diagnóstico</label>
              <input type="text" name="resultado" class="form-control" list="resultList"
                     placeholder="Ex: Positivo, Prenha, Nascimento normal..." value="<?= e($r['resultado'] ?? '') ?>">
              <datalist id="resultList">
                <option value="Positivo">
                <option value="Negativo">
                <option value="Prenha">
                <option value="Nascimento normal">
                <option value="Nascimento gemelar">
                <option value="Vazia">
              </datalist>
              <div class="aws-form-hint">Se confirmado 'Prenha', o status do animal será monitorado automaticamente.</div>
            </div>

            <!-- 6. Observação -->
            <div class="col-12">
              <label class="form-label">Observações Clínicas (Opcional)</label>
              <textarea name="observacao" class="form-control" rows="2" placeholder="Ex: Inseminador, escore corporal da vaca, protocolo hormonal..."><?= e($r['observacao'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="aws-wizard-actions">
            <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/reproducao' ?>" class="aws-btn-secondary">Cancelar</a>
            <button type="submit" class="aws-btn-primary">
              <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Salvar Alterações' : 'Gravar Evento Reprodutivo' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Coluna Direita: Ficha Reprodutiva da Matriz -->
  <div class="col-lg-5">
    <div class="aws-container h-100">
      <div class="aws-container-header">
        <h6><i class="bi bi-gender-female text-danger"></i> Ficha da Matriz Reprodutiva</h6>
        <span class="badge bg-light text-secondary border" id="badgeReproStatus">Aguardando seleção</span>
      </div>

      <div class="aws-container-body" id="cockpitReproContainer">
        <!-- Estado Vazio -->
        <div id="cockpitReproEmpty" class="text-center text-muted py-4">
          <i class="bi bi-diagram-3 fs-2 d-block mb-2 text-muted" style="opacity: 0.5;"></i>
          <p class="small mb-0">Selecione uma matriz ao lado para carregar o histórico reprodutivo, pasto e último parto.</p>
        </div>

        <!-- Estado Preenchido -->
        <div id="cockpitReproFilled" class="d-none">
          <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
            <div id="cockpitReproFotoWrapper" class="rounded border d-flex align-items-center justify-content-center bg-light" style="width:60px; height:60px; overflow:hidden; flex-shrink:0;">
              <i class="bi bi-image text-muted fs-3" id="cockpitReproNoFoto"></i>
              <img id="cockpitReproFotoImg" src="" alt="Foto" class="d-none w-100 h-100" style="object-fit:cover;">
            </div>
            <div>
              <h5 class="mb-0 fw-bold text-dark" id="cockpitReproBrinco">—</h5>
              <small class="text-muted d-block" id="cockpitReproNome">—</small>
            </div>
          </div>

          <table class="aws-review-table mb-3">
            <tr>
              <td class="label-cell">Raça / Idade:</td>
              <td class="value-cell" id="cockpitReproRacaIdade">—</td>
            </tr>
            <tr>
              <td class="label-cell">Pasto Atual:</td>
              <td class="value-cell text-success" id="cockpitReproPasto">—</td>
            </tr>
            <tr>
              <td class="label-cell">Último Manejo:</td>
              <td class="value-cell" id="cockpitReproUltimoTipo">—</td>
            </tr>
            <tr>
              <td class="label-cell">Diagnóstico:</td>
              <td class="value-cell" id="cockpitReproUltimoRes">—</td>
            </tr>
            <tr>
              <td class="label-cell">Data do Último:</td>
              <td class="value-cell text-muted small" id="cockpitReproUltimaData">—</td>
            </tr>
          </table>

          <div class="p-2 rounded bg-light border small text-muted">
            <i class="bi bi-info-circle text-primary me-1"></i>
            O ciclo gestacional de bovinos é de aproximadamente <strong>285 a 290 dias</strong> (9 meses e meio).
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const matrizesData = <?= json_encode($matrizesMap) ?>;

document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('formReproducao');
  const selectMatriz = document.getElementById('selectMatrizRepro');
  const inputTouro = document.getElementById('inputTouroRepro');

  const emptyState = document.getElementById('cockpitReproEmpty');
  const filledState = document.getElementById('cockpitReproFilled');
  const badgeStatus = document.getElementById('badgeReproStatus');
  const brincoEl = document.getElementById('cockpitReproBrinco');
  const nomeEl = document.getElementById('cockpitReproNome');
  const racaIdadeEl = document.getElementById('cockpitReproRacaIdade');
  const pastoEl = document.getElementById('cockpitReproPasto');
  const ultimoTipoEl = document.getElementById('cockpitReproUltimoTipo');
  const ultimoResEl = document.getElementById('cockpitReproUltimoRes');
  const ultimaDataEl = document.getElementById('cockpitReproUltimaData');
  const fotoImg = document.getElementById('cockpitReproFotoImg');
  const noFotoIcon = document.getElementById('cockpitReproNoFoto');

  function atualizarPerfilMatriz() {
    const animalId = selectMatriz.value;
    const a = matrizesData[animalId];

    if (a) {
      emptyState.classList.add('d-none');
      filledState.classList.remove('d-none');

      badgeStatus.textContent = a.status;
      badgeStatus.className = 'badge ' + (a.status === 'Prenha' ? 'bg-primary text-white' : 'bg-success-subtle text-success border-success');

      brincoEl.textContent = a.brinco;
      nomeEl.textContent = a.nome;
      racaIdadeEl.textContent = `${a.raca} • ${a.idade}`;
      pastoEl.textContent = '🌿 ' + a.pasto;
      ultimoTipoEl.textContent = a.ultimo_tipo;
      ultimoResEl.textContent = a.ultimo_res;
      ultimaDataEl.textContent = a.ultimo_data;

      if (a.foto_url) {
        fotoImg.src = a.foto_url;
        fotoImg.classList.remove('d-none');
        noFotoIcon.classList.add('d-none');
      } else {
        fotoImg.classList.add('d-none');
        noFotoIcon.classList.remove('d-none');
      }
    } else {
      emptyState.classList.remove('d-none');
      filledState.classList.add('d-none');
      badgeStatus.textContent = 'Aguardando seleção';
      badgeStatus.className = 'badge bg-light text-secondary border';
    }
  }

  selectMatriz.addEventListener('change', atualizarPerfilMatriz);
  atualizarPerfilMatriz();

  // Validação Zootécnica
  const femeasBrincos = <?= json_encode(array_values(array_filter(array_map(fn($f) => strtoupper(trim($f['brinco'] ?? '')), $animais)))) ?>;
  const femeasMap = <?= json_encode(array_column($animais, 'brinco', 'id')) ?>;

  form.addEventListener('submit', function(e) {
    const matrizId = selectMatriz.value;
    const matrizBrinco = (femeasMap[matrizId] || '').trim().toUpperCase();
    const touroBrinco = (inputTouro.value || '').trim().toUpperCase();

    if (matrizBrinco && touroBrinco && matrizBrinco === touroBrinco) {
      e.preventDefault();
      alert('Inconsistência Zootécnica: A matriz fêmea (' + matrizBrinco + ') não pode ser informada como o próprio touro reprodutor.');
      inputTouro.focus();
      return;
    }

    if (touroBrinco && femeasBrincos.includes(touroBrinco)) {
      e.preventDefault();
      alert('Inconsistência Zootécnica: O brinco "' + touroBrinco + '" pertence a uma FÊMEA do rebanho e não pode ser indicado como touro reprodutor.');
      inputTouro.focus();
      return;
    }
  });
});

window.abrirAjudaReproducao = function() {
  const title = 'Guia: Manejo Reprodutivo e Estação de Monta';
  const html = `
    <div class="aws-help-section">
      <h7><i class="bi bi-diagram-3"></i> Controle Reprodutivo</h7>
      <p>Gerenciar os cruzamentos, inseminações e partos garante a taxa de prenhez da fazenda e permite prever a época de nascimento dos bezerros.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-gender-female"></i> Ficha da Matriz em Tempo Real</h7>
      <p>Ao escolher a vaca, a coluna direita mostra a idade, a situação cadastral e quando ocorreu a última cobertura para evitar duplicidades.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-calendar-event"></i> Cálculo da Gestação</h7>
      <div class="aws-help-tip-box">
        O período médio de gestação em bovinos é de <strong>285 dias</strong>. Ao lançar um diagnóstico <strong>Prenha</strong> ou <strong>Inseminação</strong>, a matriz ganha destaque automático no painel de reprodução.
      </div>
    </div>
  `;
  openAwsHelpDrawer(title, html);
};

document.addEventListener('DOMContentLoaded', () => {
  const btnTop = document.getElementById('btnTopHelp');
  if (btnTop) btnTop.onclick = abrirAjudaReproducao;
});
</script>
