<?php
$isEdit = !empty($usuario['id']);
$userPerms = [];
if ($isEdit) {
    $userPerms = json_decode($usuario['permissoes'] ?? '{}', true) ?: [];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h5 class="mb-1 fw-bold text-dark">
      <i class="bi bi-person-badge-fill me-2 text-primary"></i>
      <?= $isEdit ? 'Editar Colaborador & Permissões' : 'Cadastrar Novo Colaborador' ?>
    </h5>
    <small class="text-muted">Defina as credenciais de acesso e marque os privilégios granulares deste usuário</small>
  </div>
  <a href="/usuarios" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i> Voltar à Lista
  </a>
</div>

<form method="POST" action="<?= $isEdit ? '/usuarios/' . $usuario['id'] . '/salvar' : '/usuarios/criar' ?>">
  <?= csrf_field() ?>

  <div class="row g-4">
    <!-- Bloco 1: Dados Cadastrais & Credenciais -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm sticky-top" style="top: 80px;">
        <div class="card-header bg-white py-3 border-bottom">
          <h6 class="mb-0 fw-bold"><i class="bi bi-person-vcard me-2 text-primary"></i>Dados de Acesso</h6>
        </div>
        <div class="card-body p-3">
          <div class="mb-3">
            <label class="form-label fw-bold small">Nome Completo *</label>
            <input type="text" name="nome" class="form-control" required value="<?= e($usuario['nome'] ?? '') ?>" placeholder="Ex: Carlos Eduardo">
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold small">E-mail de Login *</label>
            <input type="email" name="email" class="form-control" required value="<?= e($usuario['email'] ?? '') ?>" placeholder="colaborador@fazenda.com">
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold small">Cargo / Função</label>
            <input type="text" name="cargo" class="form-control" value="<?= e($usuario['cargo'] ?? 'Colaborador') ?>" placeholder="Ex: Zootecnista, Campeiro, Gerente">
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold small">
              Senha de Acesso <?= $isEdit ? '<span class="text-muted fw-normal">(opcional)</span>' : '*' ?>
            </label>
            <input type="password" name="senha" class="form-control" <?= $isEdit ? '' : 'required' ?> placeholder="<?= $isEdit ? 'Deixe em branco para manter' : '••••••••' ?>" autocomplete="new-password">
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold small">Nível de Conta</label>
            <select name="tipo" id="selectTipo" class="form-select" onchange="toggleAdminNotice(this.value)">
              <option value="usuario" <?= ($usuario['tipo'] ?? 'usuario') === 'usuario' ? 'selected' : '' ?>>Colaborador (Permissões Customizadas)</option>
              <option value="admin" <?= ($usuario['tipo'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador Mestre (Acesso Total)</option>
            </select>
          </div>

          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="ativo" id="checkAtivo" value="1" <?= (!isset($usuario['ativo']) || $usuario['ativo'] == 1) ? 'checked' : '' ?>>
            <label class="form-check-label small fw-bold" for="checkAtivo">Conta Ativa</label>
          </div>

          <div id="adminNoticeBox" class="alert alert-primary bg-opacity-10 border-primary border-opacity-25 p-2 small mb-3" style="display: <?= ($usuario['tipo'] ?? '') === 'admin' ? 'block' : 'none' ?>;">
            <i class="bi bi-info-circle-fill me-1 text-primary"></i> <strong>Administrador Mestre</strong> possui acesso total e irrestrito a todos os módulos, ignorando as caixas de seleção.
          </div>

          <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
            <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Salvar Alterações' : 'Cadastrar Colaborador' ?>
          </button>
        </div>
      </div>
    </div>

    <!-- Bloco 2: Matriz de Permissões Granulares -->
    <div class="col-lg-8" id="permissionsMatrixContainer">
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2 border-bottom">
          <div>
            <h6 class="mb-0 fw-bold"><i class="bi bi-key-fill me-2 text-warning"></i>Matriz de Permissões de Acesso</h6>
            <small class="text-muted">Marque exatamente os privilégios concedidos a este colaborador</small>
          </div>
          
          <!-- Botões de Presets Rápidos -->
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" onclick="applyPreset('all')" title="Marcar tudo">⚡ Tudo</button>
            <button type="button" class="btn btn-outline-secondary" onclick="applyPreset('none')" title="Desmarcar tudo">🧹 Limpar</button>
            <button type="button" class="btn btn-outline-primary" onclick="applyPreset('veterinario')" title="Animais, Saúde, Reprodução e Pesagens">🩺 Veterinário</button>
            <button type="button" class="btn btn-outline-success" onclick="applyPreset('campo')" title="Pesagens, Bezerros, Saúde e App">🤠 Campo</button>
            <button type="button" class="btn btn-outline-info" onclick="applyPreset('leitura')" title="Apenas visualização">👁️ Leitura</button>
          </div>
        </div>

        <div class="card-body p-4">

          <!-- MÓDULO: ANIMAIS -->
          <div class="mb-4 p-3 bg-light rounded-3 border">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="fs-5">🐂</span>
              <h6 class="mb-0 fw-bold text-dark">Gestão do Rebanho (Animais)</h6>
            </div>
            <div class="row g-2">
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[ver_animais]" value="1" id="p_ver_animais" <?= !empty($userPerms['ver_animais']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_ver_animais">Visualizar</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[criar_animais]" value="1" id="p_criar_animais" <?= !empty($userPerms['criar_animais']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_criar_animais">Cadastrar</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[editar_animais]" value="1" id="p_editar_animais" <?= !empty($userPerms['editar_animais']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_editar_animais">Editar</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[excluir_animais]" value="1" id="p_excluir_animais" <?= !empty($userPerms['excluir_animais']) ? 'checked' : '' ?>>
                  <label class="form-check-label small text-danger fw-bold" for="p_excluir_animais">Excluir</label>
                </div>
              </div>
            </div>
          </div>

          <!-- MÓDULO: PESAGENS -->
          <div class="mb-4 p-3 bg-light rounded-3 border">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="fs-5">⚖️</span>
              <h6 class="mb-0 fw-bold text-dark">Pesagens & Balança</h6>
            </div>
            <div class="row g-2">
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[ver_pesagens]" value="1" id="p_ver_pesagens" <?= !empty($userPerms['ver_pesagens']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_ver_pesagens">Visualizar</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[criar_pesagens]" value="1" id="p_criar_pesagens" <?= !empty($userPerms['criar_pesagens']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_criar_pesagens">Lançar</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[editar_pesagens]" value="1" id="p_editar_pesagens" <?= !empty($userPerms['editar_pesagens']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_editar_pesagens">Editar</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[excluir_pesagens]" value="1" id="p_excluir_pesagens" <?= !empty($userPerms['excluir_pesagens']) ? 'checked' : '' ?>>
                  <label class="form-check-label small text-danger fw-bold" for="p_excluir_pesagens">Excluir</label>
                </div>
              </div>
            </div>
          </div>

          <!-- MÓDULO: SAÚDE & VACINAS -->
          <div class="mb-4 p-3 bg-light rounded-3 border">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="fs-5">⚕️</span>
              <h6 class="mb-0 fw-bold text-dark">Saúde, Vacinas & Manejos</h6>
            </div>
            <div class="row g-2">
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[ver_saude]" value="1" id="p_ver_saude" <?= !empty($userPerms['ver_saude']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_ver_saude">Visualizar</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[criar_saude]" value="1" id="p_criar_saude" <?= !empty($userPerms['criar_saude']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_criar_saude">Lançar</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[editar_saude]" value="1" id="p_editar_saude" <?= !empty($userPerms['editar_saude']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_editar_saude">Editar</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[excluir_saude]" value="1" id="p_excluir_saude" <?= !empty($userPerms['excluir_saude']) ? 'checked' : '' ?>>
                  <label class="form-check-label small text-danger fw-bold" for="p_excluir_saude">Excluir</label>
                </div>
              </div>
            </div>
          </div>

          <!-- MÓDULO: PASTAGENS -->
          <div class="mb-4 p-3 bg-light rounded-3 border">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="fs-5">🌿</span>
              <h6 class="mb-0 fw-bold text-dark">Pastagens & Lotes</h6>
            </div>
            <div class="row g-2">
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[ver_pastagens]" value="1" id="p_ver_pastagens" <?= !empty($userPerms['ver_pastagens']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_ver_pastagens">Visualizar</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[criar_pastagens]" value="1" id="p_criar_pastagens" <?= !empty($userPerms['criar_pastagens']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_criar_pastagens">Cadastrar</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[editar_pastagens]" value="1" id="p_editar_pastagens" <?= !empty($userPerms['editar_pastagens']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_editar_pastagens">Editar</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[excluir_pastagens]" value="1" id="p_excluir_pastagens" <?= !empty($userPerms['excluir_pastagens']) ? 'checked' : '' ?>>
                  <label class="form-check-label small text-danger fw-bold" for="p_excluir_pastagens">Excluir</label>
                </div>
              </div>
            </div>
          </div>

          <!-- MÓDULO: REPRODUÇÃO -->
          <div class="mb-4 p-3 bg-light rounded-3 border">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="fs-5">🧬</span>
              <h6 class="mb-0 fw-bold text-dark">Reprodução & Inseminação</h6>
            </div>
            <div class="row g-2">
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[ver_reproducao]" value="1" id="p_ver_reproducao" <?= !empty($userPerms['ver_reproducao']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_ver_reproducao">Visualizar</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[criar_reproducao]" value="1" id="p_criar_reproducao" <?= !empty($userPerms['criar_reproducao']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_criar_reproducao">Lançar</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[editar_reproducao]" value="1" id="p_editar_reproducao" <?= !empty($userPerms['editar_reproducao']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_editar_reproducao">Editar</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[excluir_reproducao]" value="1" id="p_excluir_reproducao" <?= !empty($userPerms['excluir_reproducao']) ? 'checked' : '' ?>>
                  <label class="form-check-label small text-danger fw-bold" for="p_excluir_reproducao">Excluir</label>
                </div>
              </div>
            </div>
          </div>

          <!-- MÓDULO: RELATÓRIOS, ALERTAS & MOBILE -->
          <div class="p-3 bg-light rounded-3 border">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="fs-5">📊</span>
              <h6 class="mb-0 fw-bold text-dark">Relatórios, App Mobile & Sistema</h6>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[ver_relatorios]" value="1" id="p_ver_relatorios" <?= !empty($userPerms['ver_relatorios']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_ver_relatorios">Ver Relatórios Gerenciais</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[exportar_relatorios]" value="1" id="p_exportar_relatorios" <?= !empty($userPerms['exportar_relatorios']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_exportar_relatorios">Exportar Planilhas CSV</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[pode_sincronizar_mobile]" value="1" id="p_mobile" <?= !empty($userPerms['pode_sincronizar_mobile']) ? 'checked' : '' ?>>
                  <label class="form-check-label small fw-bold text-success" for="p_mobile">📱 Permitir Sincronização pelo App Mobile</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[receber_notificacao_email]" value="1" id="p_email" <?= !empty($userPerms['receber_notificacao_email']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_email">📧 Receber Notificações por E-mail</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[gerenciar_alertas]" value="1" id="p_alertas" <?= !empty($userPerms['gerenciar_alertas']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="p_alertas">Gerenciar Alertas Sanitários</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch">
                  <input class="form-check-input perm-cb" type="checkbox" name="perm[gerenciar_usuarios]" value="1" id="p_gerenciar_usuarios" <?= !empty($userPerms['gerenciar_usuarios']) ? 'checked' : '' ?>>
                  <label class="form-check-label small text-primary fw-bold" for="p_gerenciar_usuarios">👥 Gerenciar Equipe / Usuários</label>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</form>

<script>
function toggleAdminNotice(val) {
  const box = document.getElementById('adminNoticeBox');
  if (box) box.style.display = (val === 'admin') ? 'block' : 'none';
}

function applyPreset(preset) {
  const checkboxes = document.querySelectorAll('.perm-cb');
  
  if (preset === 'none') {
    checkboxes.forEach(cb => cb.checked = false);
    return;
  }
  
  if (preset === 'all') {
    checkboxes.forEach(cb => cb.checked = true);
    return;
  }

  // Desmarca tudo primeiro
  checkboxes.forEach(cb => cb.checked = false);

  const presets = {
    veterinario: [
      'p_ver_animais', 'p_criar_animais', 'p_editar_animais',
      'p_ver_pesagens', 'p_criar_pesagens', 'p_editar_pesagens',
      'p_ver_saude', 'p_criar_saude', 'p_editar_saude',
      'p_ver_pastagens',
      'p_ver_reproducao', 'p_criar_reproducao', 'p_editar_reproducao',
      'p_alertas', 'p_mobile', 'p_ver_relatorios'
    ],
    campo: [
      'p_ver_animais', 'p_criar_animais',
      'p_ver_pesagens', 'p_criar_pesagens',
      'p_ver_saude', 'p_criar_saude',
      'p_ver_pastagens',
      'p_mobile'
    ],
    leitura: [
      'p_ver_animais', 'p_ver_pesagens', 'p_ver_saude', 
      'p_ver_pastagens', 'p_ver_reproducao', 'p_ver_relatorios', 'p_alertas'
    ]
  };

  const list = presets[preset] || [];
  list.forEach(id => {
    const el = document.getElementById(id);
    if (el) el.checked = true;
  });
}
</script>
