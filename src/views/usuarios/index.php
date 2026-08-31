<?php
$stmt = $db->query("SELECT * FROM usuarios ORDER BY tipo ASC, nome ASC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
$loggedUser = currentUser();
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h5 class="mb-1 fw-bold text-dark"><i class="bi bi-people-fill me-2 text-primary"></i>Gestão de Equipe & Permissões</h5>
    <small class="text-muted">Cadastre colaboradores e defina exatamente o que cada membro pode visualizar, lançar ou editar</small>
  </div>
  <?php if (can('gerenciar_usuarios')): ?>
    <a href="/usuarios/novo" class="btn btn-primary btn-sm fw-bold px-3">
      <i class="bi bi-person-plus-fill me-1"></i> Novo Colaborador
    </a>
  <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Usuário</th>
            <th>Cargo / Função</th>
            <th>Nível</th>
            <th>Permissões Ativas</th>
            <th>Status</th>
            <th>Último Acesso</th>
            <th class="text-end pe-3">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usuarios as $u): ?>
            <?php 
              $isSelf = ($loggedUser && $loggedUser['id'] == $u['id']);
              $isAdmin = ($u['tipo'] === 'admin');
              $perms = json_decode($u['permissoes'] ?? '{}', true) ?: [];
              $permCount = count(array_filter($perms));
              $userInitial = strtoupper(substr($u['nome'] ?? 'U', 0, 1));
            ?>
            <tr class="<?= !$u['ativo'] ? 'opacity-50' : '' ?>">
              <td class="ps-3">
                <div class="d-flex align-items-center gap-2">
                  <div class="rounded-circle bg-<?= $isAdmin ? 'primary' : 'success' ?> bg-opacity-10 text-<?= $isAdmin ? 'primary' : 'success' ?> fw-bold d-flex align-items-center justify-content-center" style="width:36px;height:36px;font-size:0.9rem;">
                    <?= $userInitial ?>
                  </div>
                  <div>
                    <strong class="d-block text-dark"><?= e($u['nome']) ?> <?= $isSelf ? '<span class="badge bg-secondary" style="font-size:0.65rem;">Você</span>' : '' ?></strong>
                    <small class="text-muted"><?= e($u['email']) ?></small>
                  </div>
                </div>
              </td>

              <td>
                <span class="badge bg-light text-dark border"><?= e($u['cargo'] ?? 'Colaborador') ?></span>
              </td>

              <td>
                <?php if ($isAdmin): ?>
                  <span class="badge bg-primary"><i class="bi bi-shield-lock-fill me-1"></i>Administrador</span>
                <?php else: ?>
                  <span class="badge bg-info text-dark"><i class="bi bi-person-badge me-1"></i>Colaborador</span>
                <?php endif; ?>
              </td>

              <td>
                <?php if ($isAdmin): ?>
                  <span class="badge bg-success bg-opacity-10 text-success fw-bold"><i class="bi bi-check-all me-1"></i>Acesso Total Irrestrito</span>
                <?php elseif ($permCount > 0): ?>
                  <span class="badge bg-primary bg-opacity-10 text-primary fw-bold">
                    <i class="bi bi-key-fill me-1"></i><?= $permCount ?> permissões ativas
                  </span>
                <?php else: ?>
                  <span class="badge bg-warning bg-opacity-10 text-warning fw-bold">Nenhuma permissão</span>
                <?php endif; ?>
              </td>

              <td>
                <?php if ($u['ativo']): ?>
                  <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Ativo</span>
                <?php else: ?>
                  <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Inativo</span>
                <?php endif; ?>
              </td>

              <td class="small text-muted">
                <?= $u['ultimo_acesso'] ? formatDateTime($u['ultimo_acesso']) : '<span class="text-muted">Nunca acessou</span>' ?>
              </td>

              <td class="text-end pe-3">
                <div class="btn-group btn-group-sm">
                  <?php if (can('gerenciar_usuarios')): ?>
                    <a href="/usuarios/<?= $u['id'] ?>/editar" class="btn btn-outline-secondary" title="Editar Usuário e Permissões">
                      <i class="bi bi-pencil-square"></i>
                    </a>

                    <?php if (!$isSelf): ?>
                      <form method="POST" action="/usuarios/<?= $u['id'] ?>/toggle-status" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-<?= $u['ativo'] ? 'warning' : 'success' ?>" title="<?= $u['ativo'] ? 'Desativar acesso' : 'Ativar acesso' ?>">
                          <i class="bi bi-<?= $u['ativo'] ? 'pause-fill' : 'play-fill' ?>"></i>
                        </button>
                      </form>
                      
                      <button type="button" class="btn btn-outline-danger" onclick="confirmarExclusaoUsuario(<?= $u['id'] ?>, '<?= e(addslashes($u['nome'])) ?>')" title="Excluir Usuário">
                        <i class="bi bi-trash-fill"></i>
                      </button>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<form id="formDeleteUser" method="POST" action="" style="display:none;">
  <?= csrf_field() ?>
</form>

<script>
function confirmarExclusaoUsuario(id, nome) {
  if (confirm('Tem certeza que deseja excluir o usuário "' + nome + '"? Esta ação não pode ser desfeita.')) {
    const form = document.getElementById('formDeleteUser');
    form.action = '/usuarios/' + id + '/excluir';
    form.submit();
  }
}
</script>
