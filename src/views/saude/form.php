<?php
$animaisStmt = $db->query("
    SELECT a.id, a.brinco, a.nome, a.sexo, a.raca, a.data_nascimento, a.status, a.foto_url,
           p.nome as pasto_nome,
           (SELECT data FROM saude WHERE animal_id = a.id ORDER BY data DESC LIMIT 1) as ultimo_manejo_data,
           (SELECT tipo FROM saude WHERE animal_id = a.id ORDER BY data DESC LIMIT 1) as ultimo_manejo_tipo,
           (SELECT medicamento FROM saude WHERE animal_id = a.id ORDER BY data DESC LIMIT 1) as ultimo_medicamento
    FROM animais a
    LEFT JOIN pastagens p ON a.pasto_id = p.id
    WHERE a.status != 'morto'
    ORDER BY a.brinco
");
$animais   = $animaisStmt->fetchAll();
$isEdit    = isset($saude);
$s         = $saude ?? [];
$preAnimal = $s['animal_id'] ?? ($_GET['animal_id'] ?? null);

$animaisMap = [];
foreach ($animais as $an) {
    $animaisMap[$an['id']] = [
        'id'                 => $an['id'],
        'brinco'             => $an['brinco'],
        'nome'               => $an['nome'] ?: 'Sem nome',
        'sexo'               => $an['sexo'] === 'M' ? 'Macho' : 'Fêmea',
        'raca'               => $an['raca'] ?: 'Nelore',
        'status'             => ucfirst($an['status']),
        'pasto'              => $an['pasto_nome'] ?: 'Sem pasto',
        'foto_url'           => $an['foto_url'] ?: null,
        'ultimo_data'        => !empty($an['ultimo_manejo_data']) ? formatDate($an['ultimo_manejo_data']) : 'Nenhum registro prévio',
        'ultimo_tipo'        => $an['ultimo_manejo_tipo'] ?: 'Nenhum',
        'ultimo_medicamento' => $an['ultimo_medicamento'] ?: 'Nenhum',
        'idade'              => calcIdade($an['data_nascimento'])
    ];
}
?>
<!-- Topo com Breadcrumbs e Ajuda -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/saude' ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
    <span class="text-muted small">
      Saúde &gt; <?= $isEdit ? 'Editar Evento' : 'Novo Registro Sanitário' ?>
    </span>
  </div>
  <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" onclick="abrirAjudaSaude()">
    <i class="bi bi-info-circle"></i> <span>Instruções de Sanidade</span>
  </button>
</div>

<!-- Layout 2 Colunas Widescreen (Aproveitamento Total de Tela) -->
<div class="row g-3">
  <!-- Coluna Esquerda: Formulário de Saúde -->
  <div class="col-lg-7">
    <div class="aws-container">
      <div class="aws-container-header">
        <h6><i class="bi bi-heart-pulse text-danger"></i> <?= $isEdit ? 'Editar Evento de Saúde' : 'Registrar Vacinação ou Tratamento' ?></h6>
        <span class="text-muted small">Manejo sanitário e controle de carência</span>
      </div>

      <div class="aws-container-body">
        <form method="POST" action="<?= $isEdit ? '/saude/'.$s['id'].'/atualizar' : '/saude/salvar' ?>" id="formSaude">
          <?= csrf_field() ?>

          <div class="row g-3">
            <!-- 1. Qual é o animal? -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Animal (Brinco) *</label>
              <select name="animal_id" id="selectAnimalSaude" class="form-select" required>
                <option value="">— Selecione o brinco —</option>
                <?php foreach ($animais as $a): ?>
                  <option value="<?= $a['id'] ?>" <?= $preAnimal == $a['id'] ? 'selected' : '' ?>>
                    <?= e($a['brinco']) ?><?= $a['nome'] ? ' — '.e($a['nome']) : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="aws-form-hint">A ficha cadastral e histórico sanitário abrem ao lado.</div>
            </div>

            <!-- 2. Tipo de Manejo Sanitário -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Tipo de Evento *</label>
              <select name="tipo" id="selectTipo" class="form-select" required>
                <option value="">— Selecione o tipo —</option>
                <?php foreach (['Vacinação','Vermifugação','Tratamento','Curativo','Cirurgia','Exame','Parto','Recuperado / Alta','Óbito','Outro'] as $t): ?>
                  <option value="<?= $t ?>" <?= ($s['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
              <div class="aws-form-hint">Classificação para laudo e auditoria sanitária.</div>
            </div>

            <!-- 3. Descrição -->
            <div class="col-12">
              <label class="form-label fw-bold">Descrição do Procedimento *</label>
              <input type="text" name="descricao" id="inputDescricao" class="form-control" required
                     placeholder="Ex: Vacinação contra febre aftosa — dose semestral"
                     value="<?= e($s['descricao'] ?? '') ?>">
            </div>

            <!-- 4. Medicamento e Dose -->
            <div class="col-md-6">
              <label class="form-label">Medicamento / Princípio Ativo</label>
              <input type="text" name="medicamento" id="inputMedicamento" class="form-control" list="medList"
                     placeholder="Ex: Ivermectina, Aftosa..." value="<?= e($s['medicamento'] ?? '') ?>">
              <datalist id="medList">
                <?php foreach (['Aftosa','Brucelose','Carbúnculo','Clostridiose','Botulismo','Ivermectina','Vitamina ADE','Antibiótico','Vermífugo','Cicatrizante'] as $m): ?>
                  <option value="<?= $m ?>">
                <?php endforeach; ?>
              </datalist>
            </div>

            <div class="col-md-3">
              <label class="form-label">Dose Aplicada</label>
              <input type="text" name="dose" id="inputDose" class="form-control" placeholder="Ex: 5ml" value="<?= e($s['dose'] ?? '') ?>">
            </div>

            <div class="col-md-3">
              <label class="form-label">Custo (R$)</label>
              <input type="number" step="0.01" name="custo" class="form-control" placeholder="0.00" value="<?= e($s['custo'] ?? '') ?>">
            </div>

            <!-- 5. Datas e Veterinário -->
            <div class="col-md-4">
              <label class="form-label fw-bold">Data da Aplicação *</label>
              <input type="date" name="data" class="form-control" required value="<?= e($s['data'] ?? date('Y-m-d')) ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label">Próxima Dose / Retorno</label>
              <input type="date" name="proxima_data" class="form-control" value="<?= e($s['proxima_data'] ?? '') ?>">
              <div class="aws-form-hint">Gera lembrete automático no painel de alertas.</div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Aplicador / Veterinário</label>
              <input type="text" name="veterinario" class="form-control" placeholder="Nome do profissional" value="<?= e($s['veterinario'] ?? '') ?>">
            </div>

            <!-- 6. Observações Adicionais -->
            <div class="col-12">
              <label class="form-label">Observações Clínicas (Opcional)</label>
              <textarea name="observacao" class="form-control" rows="2" placeholder="Reação adversa, lote do medicamento, local da injeção..."><?= e($s['observacao'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="aws-wizard-actions">
            <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/saude' ?>" class="aws-btn-secondary">Cancelar</a>
            <button type="submit" class="aws-btn-primary">
              <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Salvar Alterações' : 'Gravar Registro Sanitário' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Coluna Direita: Ficha Sanitária do Animal -->
  <div class="col-lg-5">
    <div class="aws-container h-100">
      <div class="aws-container-header">
        <h6><i class="bi bi-shield-plus text-success"></i> Perfil Sanitário do Animal</h6>
        <span class="badge bg-light text-secondary border" id="badgeSaudeStatus">Aguardando seleção</span>
      </div>

      <div class="aws-container-body" id="cockpitSaudeContainer">
        <!-- Estado Vazio -->
        <div id="cockpitSaudeEmpty" class="text-center text-muted py-4">
          <i class="bi bi-heart-pulse fs-2 d-block mb-2 text-muted" style="opacity: 0.5;"></i>
          <p class="small mb-0">Selecione o brinco do animal ao lado para conferir a situação de saúde e último manejo registrado.</p>
        </div>

        <!-- Estado Preenchido -->
        <div id="cockpitSaudeFilled" class="d-none">
          <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
            <div id="cockpitSaudeFotoWrapper" class="rounded border d-flex align-items-center justify-content-center bg-light" style="width:60px; height:60px; overflow:hidden; flex-shrink:0;">
              <i class="bi bi-image text-muted fs-3" id="cockpitSaudeNoFoto"></i>
              <img id="cockpitSaudeFotoImg" src="" alt="Foto" class="d-none w-100 h-100" style="object-fit:cover;">
            </div>
            <div>
              <h5 class="mb-0 fw-bold text-dark" id="cockpitSaudeBrinco">—</h5>
              <small class="text-muted d-block" id="cockpitSaudeNome">—</small>
            </div>
          </div>

          <table class="aws-review-table mb-3">
            <tr>
              <td class="label-cell">Raça / Sexo:</td>
              <td class="value-cell" id="cockpitSaudeRacaSexo">—</td>
            </tr>
            <tr>
              <td class="label-cell">Pasto Atual:</td>
              <td class="value-cell text-success" id="cockpitSaudePasto">—</td>
            </tr>
            <tr>
              <td class="label-cell">Último Manejo:</td>
              <td class="value-cell" id="cockpitSaudeUltimoTipo">—</td>
            </tr>
            <tr>
              <td class="label-cell">Medicamento:</td>
              <td class="value-cell" id="cockpitSaudeUltimoMed">—</td>
            </tr>
            <tr>
              <td class="label-cell">Data do Manejo:</td>
              <td class="value-cell text-muted small" id="cockpitSaudeUltimaData">—</td>
            </tr>
          </table>

          <div class="p-2 rounded bg-light border small text-muted">
            <i class="bi bi-shield-check text-success me-1"></i>
            Ao registrar este procedimento, o animal atualiza o <strong>Laudo Sanitário</strong> oficial e notifica a equipe caso haja prazo de carência para abate.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const animaisSaudeData = <?= json_encode($animaisMap) ?>;

document.addEventListener('DOMContentLoaded', function() {
  const selectAnimal = document.getElementById('selectAnimalSaude');
  const emptyState = document.getElementById('cockpitSaudeEmpty');
  const filledState = document.getElementById('cockpitSaudeFilled');
  const badgeStatus = document.getElementById('badgeSaudeStatus');
  const brincoEl = document.getElementById('cockpitSaudeBrinco');
  const nomeEl = document.getElementById('cockpitSaudeNome');
  const racaSexoEl = document.getElementById('cockpitSaudeRacaSexo');
  const pastoEl = document.getElementById('cockpitSaudePasto');
  const ultimoTipoEl = document.getElementById('cockpitSaudeUltimoTipo');
  const ultimoMedEl = document.getElementById('cockpitSaudeUltimoMed');
  const ultimaDataEl = document.getElementById('cockpitSaudeUltimaData');
  const fotoImg = document.getElementById('cockpitSaudeFotoImg');
  const noFotoIcon = document.getElementById('cockpitSaudeNoFoto');

  function atualizarPerfilSanitario() {
    const animalId = selectAnimal.value;
    const a = animaisSaudeData[animalId];

    if (a) {
      emptyState.classList.add('d-none');
      filledState.classList.remove('d-none');

      badgeStatus.textContent = a.status;
      badgeStatus.className = 'badge ' + (a.status === 'Doente' ? 'bg-danger text-white' : 'bg-success-subtle text-success border-success');

      brincoEl.textContent = a.brinco;
      nomeEl.textContent = a.nome;
      racaSexoEl.textContent = `${a.raca} • ${a.sexo}`;
      pastoEl.textContent = '🌿 ' + a.pasto;
      ultimoTipoEl.textContent = a.ultimo_tipo;
      ultimoMedEl.textContent = a.ultimo_medicamento;
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

  selectAnimal.addEventListener('change', atualizarPerfilSanitario);
  atualizarPerfilSanitario();
});

window.abrirAjudaSaude = function() {
  const title = 'Guia: Manejo Sanitário e Vacinas';
  const html = `
    <div class="aws-help-section">
      <h7><i class="bi bi-heart-pulse"></i> Controle Sanitário</h7>
      <p>Registrar vacinas e medicamentos garante a rastreabilidade do rebanho e o cumprimento dos períodos de carência exigidos pelos frigoríficos antes do abate.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-person-vcard"></i> Ficha Lateral Automática</h7>
      <p>Ao selecionar o brinco do animal, a coluna direita carrega a localização atual (pasto) e a data da última medicação aplicada para evitar sobredoses.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-calendar-check"></i> Próxima Dose e Alertas</h7>
      <p>Se o medicamento exigir reforço (como vacinas anuais ou vermifugações periódicas), preencha o campo <strong>Próxima Dose</strong>. O sistema colocará um aviso automático no Dashboard quando a data estiver próxima.</p>
    </div>
  `;
  openAwsHelpDrawer(title, html);
};

document.addEventListener('DOMContentLoaded', () => {
  const btnTop = document.getElementById('btnTopHelp');
  if (btnTop) btnTop.onclick = abrirAjudaSaude;
});
</script>
