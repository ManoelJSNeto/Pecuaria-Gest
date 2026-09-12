<?php
$animaisStmt = $db->query("
    SELECT a.id, a.brinco, a.nome, a.sexo, a.raca, a.data_nascimento, a.status, a.foto_url,
           p.nome as pasto_nome
    FROM animais a
    LEFT JOIN pastagens p ON a.pasto_id = p.id
    WHERE a.status NOT IN ('morto','vendido')
    ORDER BY a.brinco
");
$animais = $animaisStmt->fetchAll();
$preAnimal = (int)($_GET['animal_id'] ?? 0);

// Mapa JSON para alimentar o cockpit do animal em tempo real
$animaisMap = [];
foreach ($animais as $an) {
    $animaisMap[$an['id']] = [
        'id'       => $an['id'],
        'brinco'   => $an['brinco'],
        'nome'     => $an['nome'] ?: 'Sem apelido',
        'sexo'     => $an['sexo'] === 'M' ? 'Macho' : 'Fêmea',
        'raca'     => $an['raca'] ?: 'Nelore',
        'status'   => ucfirst($an['status']),
        'pasto'    => $an['pasto_nome'] ?: 'Sem pastagem',
        'foto_url' => $an['foto_url'] ?: null,
        'idade'    => calcIdade($an['data_nascimento'])
    ];
}
?>

<!-- Barra Superior com Breadcrumbs e Ajuda -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="/alertas" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
    <span class="text-muted small">
      Alertas &gt; Novo Alerta Operacional
    </span>
  </div>

  <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" onclick="abrirAjudaAlertas()">
    <i class="bi bi-info-circle"></i> <span>Instruções deste Painel</span>
  </button>
</div>

<!-- Layout Widescreen em 2 Colunas (Estilo AWS Cloudscape) -->
<div class="row g-4">
  <!-- Coluna Esquerda: Formulário de Lançamento -->
  <div class="col-lg-7">
    <div class="aws-container h-100">
      <div class="aws-container-header">
        <h6><i class="bi bi-bell-fill text-warning"></i> Registrar Alerta ou Aviso Sanitário</h6>
        <span class="text-muted small">Notificação aos colaboradores</span>
      </div>

      <div class="aws-container-body">
        <form method="POST" action="/alertas/salvar" id="formAlerta">
          <?= csrf_field() ?>

          <div class="row g-3">
            <!-- 1. Animal Alvo (Opcional) -->
            <div class="col-12">
              <label class="form-label fw-bold">Animal Alvo do Alerta (Opcional)</label>
              <select name="animal_id" id="selectAnimalAlerta" class="form-select">
                <option value="">— Alerta Geral / Rebanho Todo —</option>
                <?php foreach ($animais as $a): ?>
                  <option value="<?= $a['id'] ?>" <?= $preAnimal === (int)$a['id'] ? 'selected' : '' ?>>
                    Brinco <?= e($a['brinco']) ?><?= $a['nome'] ? ' — '.e($a['nome']) : '' ?> (<?= e($a['raca'] ?: 'Nelore') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="aws-form-hint">Selecione o brinco se o aviso for referente a um animal específico. A ficha abrirá ao lado.</div>
            </div>

            <!-- 2. Tipo de Alerta -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Categoria do Alerta *</label>
              <select name="tipo" class="form-select" required>
                <option value="saude">🏥 Saúde / Doença / Quarentena</option>
                <option value="vacina">💉 Vacina / Reforço Sanitário</option>
                <option value="pesagem">⚖️ Pesagem / Balança / GMD</option>
                <option value="reproducao">🧬 Reprodução / Parto / Monta</option>
                <option value="geral" selected>📢 Geral / Manejo na Fazenda</option>
              </select>
              <div class="aws-form-hint">Define o ícone e a prioridade de exibição.</div>
            </div>

            <!-- 3. Mensagem / Descrição -->
            <div class="col-12">
              <label class="form-label fw-bold">Descrição do Alerta *</label>
              <textarea name="mensagem" class="form-control" rows="4" required
                        placeholder="Ex: Animal apresentou manqueira no piquete 2; aguardar visita do veterinário amanhã cedo."></textarea>
              <div class="aws-form-hint">Descreva claramente a ocorrência ou instrução para quem consultar o painel.</div>
            </div>
          </div>

          <div class="aws-wizard-actions">
            <a href="/alertas" class="aws-btn-secondary">Cancelar</a>
            <button type="submit" class="aws-btn-primary">
              <i class="bi bi-check-lg"></i> Criar Alerta Operacional
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Coluna Direita: Cockpit do Animal ou Painel Geral de Alertas -->
  <div class="col-lg-5">
    <div class="aws-container h-100">
      <div class="aws-container-header">
        <h6><i class="bi bi-info-circle-fill text-primary"></i> Contexto do Alerta</h6>
        <span class="badge bg-light text-secondary border" id="badgeAlertaAlvo">Geral</span>
      </div>

      <div class="aws-container-body" id="cockpitAlertaContainer">
        <!-- Estado 1: Alerta Geral (Sem Animal) -->
        <div id="cockpitAlertaGeral">
          <div class="text-center py-3 border-bottom mb-3">
            <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-inline-flex align-items-center justify-content-center mb-2" style="width:54px; height:54px;">
              <i class="bi bi-megaphone-fill fs-3"></i>
            </div>
            <h6 class="fw-bold mb-1">Aviso Geral para Toda a Equipe</h6>
            <p class="small text-muted mb-0">Disparado para todos os operadores da fazenda.</p>
          </div>

          <div class="small text-muted mb-3">
            <div class="d-flex align-items-start gap-2 mb-2">
              <i class="bi bi-check2-circle text-success mt-1"></i>
              <span>Aparece no topo do <strong>Dashboard</strong> de todos os usuários.</span>
            </div>
            <div class="d-flex align-items-start gap-2 mb-2">
              <i class="bi bi-check2-circle text-success mt-1"></i>
              <span>Sincroniza automaticamente com o <strong>App Mobile</strong> no curral.</span>
            </div>
            <div class="d-flex align-items-start gap-2">
              <i class="bi bi-check2-circle text-success mt-1"></i>
              <span>Permanece na central até ser marcado como resolvido/lido.</span>
            </div>
          </div>

          <div class="p-2 rounded bg-light border small text-muted">
            <i class="bi bi-shield-check text-primary me-1"></i>
            Para direcionar o alerta a uma vaca ou boi específico, selecione o brinco no formulário ao lado.
          </div>
        </div>

        <!-- Estado 2: Ficha do Animal Selecionado -->
        <div id="cockpitAlertaAnimal" class="d-none">
          <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
            <div id="cockpitFotoWrapper" class="rounded border d-flex align-items-center justify-content-center bg-light" style="width:60px; height:60px; overflow:hidden; flex-shrink:0;">
              <i class="bi bi-image text-muted fs-3" id="cockpitNoFotoIcon"></i>
              <img id="cockpitFotoImg" src="" alt="Foto" class="d-none w-100 h-100" style="object-fit:cover;">
            </div>
            <div>
              <h5 class="mb-0 fw-bold text-dark" id="cockpitBrinco">—</h5>
              <small class="text-muted d-block" id="cockpitNome">—</small>
            </div>
          </div>

          <table class="aws-review-table mb-3">
            <tr>
              <td class="label-cell">Raça / Sexo:</td>
              <td class="value-cell" id="cockpitRacaSexo">—</td>
            </tr>
            <tr>
              <td class="label-cell">Idade Estimada:</td>
              <td class="value-cell" id="cockpitIdade">—</td>
            </tr>
            <tr>
              <td class="label-cell">Pastagem Atual:</td>
              <td class="value-cell text-success" id="cockpitPasto">—</td>
            </tr>
            <tr>
              <td class="label-cell">Status Cadastral:</td>
              <td class="value-cell" id="cockpitStatus">—</td>
            </tr>
          </table>

          <div class="p-2 rounded bg-light border small text-muted">
            <i class="bi bi-pin-angle text-warning me-1"></i>
            Este alerta ficará afixado diretamente no <strong>Prontuário Individual</strong> deste animal.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const animaisData = <?= json_encode($animaisMap) ?>;

document.addEventListener('DOMContentLoaded', function() {
  const selectAnimal = document.getElementById('selectAnimalAlerta');
  const geralBox = document.getElementById('cockpitAlertaGeral');
  const animalBox = document.getElementById('cockpitAlertaAnimal');
  const badgeAlvo = document.getElementById('badgeAlertaAlvo');
  const brincoEl = document.getElementById('cockpitBrinco');
  const nomeEl = document.getElementById('cockpitNome');
  const racaSexoEl = document.getElementById('cockpitRacaSexo');
  const idadeEl = document.getElementById('cockpitIdade');
  const pastoEl = document.getElementById('cockpitPasto');
  const statusEl = document.getElementById('cockpitStatus');
  const fotoImg = document.getElementById('cockpitFotoImg');
  const noFotoIcon = document.getElementById('cockpitNoFotoIcon');

  function atualizarCockpitAlerta() {
    const animalId = selectAnimal.value;
    const a = animaisData[animalId];

    if (a) {
      geralBox.classList.add('d-none');
      animalBox.classList.remove('d-none');
      badgeAlvo.textContent = 'Animal: ' + a.brinco;
      badgeAlvo.className = 'badge bg-primary text-white';

      brincoEl.textContent = a.brinco;
      nomeEl.textContent = a.nome;
      racaSexoEl.textContent = `${a.raca} • ${a.sexo}`;
      idadeEl.textContent = a.idade;
      pastoEl.textContent = '🌿 ' + a.pasto;
      statusEl.textContent = a.status;

      if (a.foto_url) {
        fotoImg.src = a.foto_url;
        fotoImg.classList.remove('d-none');
        noFotoIcon.classList.add('d-none');
      } else {
        fotoImg.classList.add('d-none');
        noFotoIcon.classList.remove('d-none');
      }
    } else {
      geralBox.classList.remove('d-none');
      animalBox.classList.add('d-none');
      badgeAlvo.textContent = 'Geral';
      badgeAlvo.className = 'badge bg-light text-secondary border';
    }
  }

  selectAnimal.addEventListener('change', atualizarCockpitAlerta);
  atualizarCockpitAlerta();
});

// Configuração da Ajuda Lateral AWS para Alertas
window.abrirAjudaAlertas = function() {
  const title = 'Guia: Central de Alertas e Notificações';
  const html = `
    <div class="aws-help-section">
      <h7><i class="bi bi-bell-fill text-warning"></i> Para que servem os Alertas?</h7>
      <p>A central de alertas sincroniza a comunicação entre o escritório e o curral, evitando que animais doentes, carências sanitárias ou pesagens atrasadas passem despercebidos.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-tag"></i> Alertas Vinculados a Animais</h7>
      <p>Ao selecionar um brinco, a notificação fica visível tanto na lista geral quanto no <strong>Prontuário Individual</strong> do próprio animal. Ao inspecionar o animal no pasto ou na balança, o alerta surge em evidência.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-check2-all"></i> Resolução de Alertas</h7>
      <div class="aws-help-tip-box">
        Assim que o manejo recomendado for executado (ex: o animal tomou a vacina ou o piquete foi vistoriado), clique em <strong>Marcar como Lido</strong> para arquivar a pendência.
      </div>
    </div>
  `;
  openAwsHelpDrawer(title, html);
};

document.addEventListener('DOMContentLoaded', () => {
  const btnTop = document.getElementById('btnTopHelp');
  if (btnTop) btnTop.onclick = abrirAjudaAlertas;
});
</script>
