<?php
if (!isset($db)) { $db = getDb(); }
// Filtro estrito: Matrizes reprodutivas devem ser fêmeas ativas
$animaisStmt = $db->query("SELECT id, brinco, nome, sexo FROM animais WHERE sexo = 'F' AND status != 'morto' ORDER BY brinco");
$animais     = $animaisStmt->fetchAll();
// Touros reprodutores machos da propriedade para sugestão de cobertura
$tourosStmt  = $db->query("SELECT brinco, nome FROM animais WHERE sexo = 'M' AND status != 'morto' ORDER BY brinco");
$touros      = $tourosStmt->fetchAll();

$isEdit      = isset($reproducao);
$r           = $reproducao ?? [];
$preAnimal   = $r['animal_id'] ?? ($_GET['animal_id'] ?? null);

// Se for edição e a matriz não estiver na lista (ex: foi vendida), carrega para manter integridade apenas se for fêmea
if (!empty($preAnimal)) {
    $found = false;
    foreach ($animais as $a) {
        if ($a['id'] == $preAnimal) { $found = true; break; }
    }
    if (!$found) {
        $extraA = $db->prepare("SELECT id, brinco, nome, sexo FROM animais WHERE id = ?");
        $extraA->execute([$preAnimal]);
        $ea = $extraA->fetch();
        if ($ea && $ea['sexo'] === 'F') {
            $animais[] = $ea;
        } else {
            $preAnimal = null; // Ignora se não for fêmea
        }
    }
}
?>
<div class="mb-3">
  <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/reproducao' ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar
  </a>
</div>
<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="form-section">
      <div class="form-section-title">
        <i class="bi bi-diagram-3 text-info"></i> <?= $isEdit ? 'Editar Evento Reprodutivo' : 'Registrar Evento Reprodutivo' ?>
      </div>
      <form method="POST" action="<?= $isEdit ? '/reproducao/'.$r['id'].'/atualizar' : '/reproducao/salvar' ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-bold small"><i class="bi bi-gender-female text-danger me-1"></i> Matriz / Fêmea *</label>
            <select name="animal_id" class="form-select" required>
              <option value="">— Selecione a Fêmea —</option>
              <?php foreach ($animais as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $preAnimal == $a['id'] ? 'selected' : '' ?>>
                  <?= e($a['brinco']) ?> (Fêmea)<?= $a['nome']?' — '.e($a['nome']):'' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted" style="font-size:0.75rem;">Apenas fêmeas ativas podem ser registradas.</small>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold small">Tipo de Evento *</label>
            <select name="tipo" class="form-select" required>
              <option value="">— Selecione —</option>
              <?php foreach (['Inseminação Artificial','Cobertura Natural','Diagnóstico de Gestação','Parto','Aborto','Desmame','Outro'] as $t): ?>
                <option value="<?= $t ?>" <?= ($r['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold small">Data do Evento *</label>
            <input type="date" name="data" class="form-control" required value="<?= e($r['data'] ?? date('Y-m-d')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold small"><i class="bi bi-gender-male text-primary me-1"></i> Touro / Sêmen do Pai</label>
            <input type="text" name="touro_brinco" class="form-control text-uppercase" list="tourosReproList" placeholder="Ex: TO0042 ou selecione..." value="<?= e($r['touro_brinco'] ?? '') ?>" autocomplete="off">
            <datalist id="tourosReproList">
              <?php foreach ($touros as $t): ?>
                <option value="<?= e($t['brinco']) ?>"><?= e($t['brinco']) ?><?= $t['nome'] ? ' — '.e($t['nome']) : '' ?> (Touro da Fazenda)</option>
              <?php endforeach; ?>
            </datalist>
            <small class="text-muted" style="font-size:0.75rem;">Selecione touro macho ou digite sêmen.</small>
          </div>
          <div class="col-12">
            <label class="form-label">Resultado</label>
            <input type="text" name="resultado" class="form-control" list="resultList" placeholder="Ex: Positivo, Prenha, Gemelar..." value="<?= e($r['resultado'] ?? '') ?>">
            <datalist id="resultList">
              <option value="Positivo"><option value="Negativo"><option value="Prenha">
              <option value="Nascimento normal"><option value="Nascimento gemelar">
            </datalist>
          </div>
          <div class="col-12">
            <label class="form-label">Observação</label>
            <textarea name="observacao" class="form-control" rows="2" placeholder="Detalhes..."><?= e($r['observacao'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Salvar Alterações' : 'Salvar Evento' ?>
              </button>
              <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/reproducao' ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.querySelector('form');
  const selectMatriz = document.querySelector('select[name="animal_id"]');
  const inputTouro = document.querySelector('input[name="touro_brinco"]');

  // Mapa de matrizes fêmeas da propriedade
  const femeasMap = <?= json_encode(array_column($animais, 'brinco', 'id')) ?>;
  const femeasBrincos = <?= json_encode(array_values(array_filter(array_map(fn($f) => strtoupper(trim($f['brinco'] ?? '')), $animais)))) ?>;

  form.addEventListener('submit', function(e) {
    const matrizId = selectMatriz.value;
    const matrizBrinco = (femeasMap[matrizId] || '').trim().toUpperCase();
    const touroBrinco = (inputTouro.value || '').trim().toUpperCase();

    // 1. Touro não pode ser a própria matriz
    if (matrizBrinco && touroBrinco && matrizBrinco === touroBrinco) {
      e.preventDefault();
      alert('Inconsistência: A matriz fêmea (' + matrizBrinco + ') não pode ser informada como o próprio touro reprodutor.');
      inputTouro.focus();
      return;
    }

    // 2. O touro não pode ser uma fêmea do rebanho
    if (touroBrinco && femeasBrincos.includes(touroBrinco)) {
      e.preventDefault();
      alert('Inconsistência Zootécnica: O brinco "' + touroBrinco + '" pertence a uma FÊMEA do rebanho e não pode ser indicado como touro reprodutor.');
      inputTouro.focus();
      return;
    }
  });
});
</script>
