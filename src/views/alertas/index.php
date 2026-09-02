<?php
$alertas = $db->query("SELECT al.*, a.brinco, a.nome as animal_nome FROM alertas al LEFT JOIN animais a ON al.animal_id=a.id ORDER BY al.lido ASC, al.created_at DESC")->fetchAll();
$naoLidos = array_filter($alertas, fn($al) => !$al['lido']);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h5 class="mb-0 fw-bold">Central de Alertas & Notificações</h5>
    <small class="text-muted tabular-nums"><?= count($alertas) ?> alertas no histórico • <?= count($naoLidos) ?> pendentes</small>
  </div>
  <div class="d-flex gap-2">
    <?php if (count($naoLidos) > 0): ?>
      <form method="POST" action="/alertas/ler-todos" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-secondary">
          <i class="bi bi-check-all me-1"></i> Marcar Todos como Lidos
        </button>
      </form>
    <?php endif; ?>
    <a href="/alertas/novo" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i> Novo Alerta
    </a>
  </div>
</div>

<div class="table-responsive">
  <table class="table">
    <thead>
      <tr>
        <th style="width: 100px;">Status</th>
        <th style="width: 180px;">Animal / Alvo</th>
        <th>Tipo</th>
        <th>Mensagem</th>
        <th>Data & Hora</th>
        <th class="text-end" style="width: 100px;">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($alertas)): ?>
        <tr>
          <td colspan="6" class="text-center text-muted py-5">
            <i class="bi bi-shield-check fs-2 d-block mb-2 text-success"></i>
            Nenhum alerta cadastrado no momento.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($alertas as $al): ?>
        <?php
          $tipoIcon = match($al['tipo']) {
            'saude'         => 'bi-heart-pulse',
            'vacina'        => 'bi-shield-plus',
            'pesagem'       => 'bi-rulers',
            'reproducao'    => 'bi-diagram-3',
            'sincronizacao' => 'bi-cloud-arrow-down',
            default         => 'bi-info-circle',
          };
        ?>
        <tr style="<?= !$al['lido'] ? 'background:var(--bg-subtle);' : 'opacity:0.75;' ?>">
          <td>
            <?php if ($al['lido']): ?>
              <span class="badge-status neutro">Lido</span>
            <?php else: ?>
              <span class="badge-status doente" style="background:#fef3c7; color:#92400e; border-color:#fde68a;">
                <i class="bi bi-circle-fill" style="font-size:.45rem"></i> Novo
              </span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($al['animal_id']): ?>
              <a href="/animais/<?= $al['animal_id'] ?>" class="fw-bold text-primary text-decoration-none">
                <?= e($al['brinco'] ?? '') ?>
              </a>
              <?php if (!empty($al['animal_nome'])): ?>
                <small class="text-muted d-block"><?= e($al['animal_nome']) ?></small>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-secondary small fw-600">Geral do Sistema</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge bg-light text-dark border">
              <i class="bi <?= $tipoIcon ?> me-1"></i><?= ucfirst(e($al['tipo'])) ?>
            </span>
          </td>
          <td class="small text-primary">
            <?= e($al['mensagem']) ?>
          </td>
          <td class="small tabular-nums text-muted text-nowrap">
            <?= formatDateTime($al['created_at']) ?>
          </td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <?php if (!$al['lido']): ?>
                <form method="POST" action="/alertas/<?= $al['id'] ?>/ler" style="display:inline;">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-secondary btn-sm" title="Marcar como lido">
                    <i class="bi bi-check-lg"></i>
                  </button>
                </form>
              <?php endif; ?>
              <form method="POST" action="/alertas/<?= $al['id'] ?>/excluir" style="display:inline;" onsubmit="return confirm('Excluir este alerta?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm text-danger" title="Excluir">
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
