<?php
$alertas = $db->query("
    SELECT al.*, a.brinco, a.nome as animal_nome, p.nome as pasto_nome
    FROM alertas al
    LEFT JOIN animais a ON al.animal_id = a.id
    LEFT JOIN pastagens p ON a.pasto_id = p.id
    ORDER BY al.lido ASC, al.created_at DESC
")->fetchAll();

$totalAlertas = count($alertas);
$naoLidos = array_filter($alertas, fn($al) => !$al['lido']);
$totalPendentes = count($naoLidos);
$totalLidos = $totalAlertas - $totalPendentes;
?>

<!-- Barra Superior com Breadcrumbs e Ações (Padrão AWS) -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold">Central de Alertas & Notificações</h5>
    <small class="text-muted">Avisos sanitários, pendências de vacinação e recados operacionais da fazenda</small>
  </div>

  <div class="d-flex align-items-center gap-2 flex-wrap">
    <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" onclick="abrirAjudaAlertas()">
      <i class="bi bi-info-circle"></i> <span>Instruções deste Painel</span>
    </button>

    <?php if ($totalPendentes > 0): ?>
      <form method="POST" action="/alertas/ler-todos" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-success fw-bold">
          <i class="bi bi-check-all me-1"></i> Marcar Todos como Lidos
        </button>
      </form>
    <?php endif; ?>

    <a href="/alertas/novo" class="btn btn-sm btn-primary fw-bold">
      <i class="bi bi-plus-lg me-1"></i> Novo Alerta
    </a>
  </div>
</div>

<!-- Cockpit de Indicadores dos Alertas -->
<div class="metric-cockpit mb-4">
  <div class="metric-cell">
    <span class="metric-label">Alertas Pendentes</span>
    <div class="metric-value tabular-nums <?= $totalPendentes > 0 ? 'text-warning' : 'text-success' ?>">
      <?= $totalPendentes ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">pendências</span>
    </div>
    <span class="metric-sub"><?= $totalPendentes > 0 ? 'Requerem atenção no curral/gestão' : 'Tudo em dia na propriedade' ?></span>
  </div>

  <div class="metric-cell">
    <span class="metric-label">Histórico Resolvido</span>
    <div class="metric-value tabular-nums" style="color:var(--text-secondary);">
      <?= $totalLidos ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">lidos</span>
    </div>
    <span class="metric-sub">Alertas processados e arquivados</span>
  </div>

  <div class="metric-cell">
    <span class="metric-label">Total Registrado</span>
    <div class="metric-value tabular-nums" style="color:var(--earth-green-800);">
      <?= $totalAlertas ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">registros</span>
    </div>
    <span class="metric-sub">Ocorrências sanitárias e rotinas</span>
  </div>
</div>

<!-- Container AWS com Tabela de Alertas -->
<div class="aws-container">
  <div class="aws-container-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
      <h6 class="mb-0 fw-bold"><i class="bi bi-bell-fill text-warning me-1"></i> Ocorrências Registradas</h6>
      <span class="badge bg-light text-dark border"><?= $totalAlertas ?> no total</span>
    </div>

    <!-- Filtro Rápido em Abas -->
    <div class="btn-group btn-group-sm" role="group" id="filterAlertasGroup">
      <button type="button" class="btn btn-outline-secondary active" onclick="filtrarTabelaAlertas('todos', this)">Todos (<?= $totalAlertas ?>)</button>
      <button type="button" class="btn btn-outline-secondary" onclick="filtrarTabelaAlertas('pendentes', this)">Pendentes (<?= $totalPendentes ?>)</button>
      <button type="button" class="btn btn-outline-secondary" onclick="filtrarTabelaAlertas('lidos', this)">Lidos (<?= $totalLidos ?>)</button>
    </div>
  </div>

  <div class="aws-container-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" id="tabelaAlertas">
        <thead class="table-light">
          <tr>
            <th class="ps-3" style="width: 110px;">Status</th>
            <th style="width: 200px;">Alvo / Animal</th>
            <th style="width: 140px;">Categoria</th>
            <th>Mensagem / Instrução</th>
            <th style="width: 150px;">Data & Hora</th>
            <th class="text-end pe-3" style="width: 110px;">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($alertas)): ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-5">
                <i class="bi bi-shield-check fs-2 d-block mb-2 text-success"></i>
                Nenhum alerta registrado no momento. A fazenda está sem pendências!
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($alertas as $al): ?>
              <?php
                $tipoIcon = match($al['tipo']) {
                  'saude'         => 'bi-heart-pulse text-danger',
                  'vacina'        => 'bi-shield-plus text-success',
                  'pesagem'       => 'bi-rulers text-primary',
                  'reproducao'    => 'bi-diagram-3 text-info',
                  'sincronizacao' => 'bi-cloud-arrow-down text-secondary',
                  default         => 'bi-info-circle text-primary',
                };
                $rowStatusClass = $al['lido'] ? 'row-lido opacity-75' : 'row-pendente bg-warning-subtle bg-opacity-10';
              ?>
              <tr class="<?= $rowStatusClass ?>" data-status="<?= $al['lido'] ? 'lido' : 'pendente' ?>">
                <td class="ps-3">
                  <?php if ($al['lido']): ?>
                    <span class="badge bg-light text-muted border">Lido</span>
                  <?php else: ?>
                    <span class="badge bg-warning text-dark border border-warning">
                      <i class="bi bi-exclamation-circle-fill me-1"></i> Pendente
                    </span>
                  <?php endif; ?>
                </td>

                <td>
                  <?php if ($al['animal_id']): ?>
                    <a href="/animais/<?= $al['animal_id'] ?>" class="fw-bold text-dark text-decoration-none">
                      <i class="bi bi-tag-fill text-success me-1"></i> Brinco <?= e($al['brinco'] ?? '') ?>
                    </a>
                    <small class="text-muted d-block">
                      <?= e($al['animal_nome'] ?: 'Sem nome') ?>
                      <?php if (!empty($al['pasto_nome'])): ?>
                        • <span class="text-success">🌿 <?= e($al['pasto_nome']) ?></span>
                      <?php endif; ?>
                    </small>
                  <?php else: ?>
                    <span class="text-dark fw-bold"><i class="bi bi-megaphone-fill text-warning me-1"></i> Geral da Fazenda</span>
                    <small class="text-muted d-block">Notificação para toda a equipe</small>
                  <?php endif; ?>
                </td>

                <td>
                  <span class="badge bg-light text-dark border">
                    <i class="bi <?= $tipoIcon ?> me-1"></i><?= ucfirst(e($al['tipo'])) ?>
                  </span>
                </td>

                <td>
                  <div class="small fw-500 text-dark"><?= e($al['mensagem']) ?></div>
                </td>

                <td class="small tabular-nums text-muted text-nowrap">
                  <?= formatDateTime($al['created_at']) ?>
                </td>

                <td class="text-end pe-3">
                  <div class="btn-group btn-group-sm">
                    <?php if (!$al['lido']): ?>
                      <form method="POST" action="/alertas/<?= $al['id'] ?>/ler" style="display:inline;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-success" title="Marcar como resolvido / lido">
                          <i class="bi bi-check-lg"></i>
                        </button>
                      </form>
                    <?php endif; ?>
                    <form method="POST" action="/alertas/<?= $al['id'] ?>/excluir" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir permanentemente este alerta?')">
                      <?= csrf_field() ?>
                      <button type="submit" class="btn btn-outline-danger" title="Excluir alerta">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function filtrarTabelaAlertas(filtro, btn) {
  const grupo = document.getElementById('filterAlertasGroup');
  grupo.querySelectorAll('button').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  const linhas = document.querySelectorAll('#tabelaAlertas tbody tr');
  linhas.forEach(row => {
    const status = row.dataset.status;
    if (!status) return;

    if (filtro === 'todos') {
      row.style.display = '';
    } else if (filtro === 'pendentes') {
      row.style.display = (status === 'pendente') ? '' : 'none';
    } else if (filtro === 'lidos') {
      row.style.display = (status === 'lido') ? '' : 'none';
    }
  });
}

// Configuração da Ajuda Lateral AWS para Alertas
window.abrirAjudaAlertas = function() {
  const title = 'Guia: Central de Alertas e Notificações';
  const html = `
    <div class="aws-help-section">
      <h7><i class="bi bi-bell-fill text-warning"></i> Para que serve a Central de Alertas?</h7>
      <p>A central de notificações organiza pendências críticas como períodos de carência sanitária, animais com manqueira, vacinações vencendo ou alertas de pesagem atrasada.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-phone"></i> Sincronização Mobile</h7>
      <p>Alertas cadastrados aqui aparecem instantaneamente no aplicativo dos vaqueiros no curral. Quando um animal é colocado na balança, os alertas dele são destacados na tela do celular.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-check2-all"></i> Resolução de Pendências</h7>
      <div class="aws-help-tip-box">
        Assim que a pendência for resolvida no campo, clique no botão de <strong>check verde</strong> para marcar como resolvido e desocupar o painel.
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
