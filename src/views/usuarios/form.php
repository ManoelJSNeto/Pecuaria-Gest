<?php
$isEdit = !empty($usuario['id']);
$userPerms = [];
if ($isEdit) {
    $userPerms = json_decode($usuario['permissoes'] ?? '{}', true) ?: [];
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="/usuarios" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
    <span class="text-muted small">
      Equipe &gt; <?= $isEdit ? 'Editar ' . e($usuario['nome'] ?? 'Colaborador') : 'Novo Colaborador' ?>
    </span>
  </div>

  <div class="d-flex align-items-center gap-2">
    <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" onclick="abrirAjudaUsuarios()">
      <i class="bi bi-info-circle"></i> <span>Instruções de Acesso</span>
    </button>
  </div>
</div>

<!-- Header do Wizard Corporativo AWS -->
<div class="aws-wizard-header">
  <div class="aws-wizard-stepper" id="stepperContainer">
    <!-- Etapa 1 -->
    <div class="aws-step-item active" id="stepIndicator1" onclick="irParaPasso(1)">
      <div class="aws-step-badge">1</div>
      <div class="aws-step-text">
        <span class="aws-step-title">1. Dados de Acesso</span>
        <span class="aws-step-desc">Identificação e login</span>
      </div>
    </div>
    <div class="aws-step-divider"></div>

    <!-- Etapa 2 -->
    <div class="aws-step-item" id="stepIndicator2" onclick="irParaPasso(2)">
      <div class="aws-step-badge">2</div>
      <div class="aws-step-text">
        <span class="aws-step-title">2. Nível & Permissões</span>
        <span class="aws-step-desc">Papéis e privilégios</span>
      </div>
    </div>
    <div class="aws-step-divider"></div>

    <!-- Etapa 3 -->
    <div class="aws-step-item" id="stepIndicator3" onclick="irParaPasso(3)">
      <div class="aws-step-badge">3</div>
      <div class="aws-step-text">
        <span class="aws-step-title">3. Revisão e Confirmação</span>
        <span class="aws-step-desc">Conferência final</span>
      </div>
    </div>
  </div>
</div>

<form method="POST" action="<?= $isEdit ? '/usuarios/' . $usuario['id'] . '/salvar' : '/usuarios/criar' ?>" id="formUsuario">
  <?= csrf_field() ?>

  <!-- ══════════════════════════════════════════════════════
       PASSO 1: DADOS DE ACESSO & IDENTIFICAÇÃO
  ══════════════════════════════════════════════════════ -->
  <div class="aws-container wizard-step-pane" id="stepPane1">
    <div class="aws-container-header">
      <h6><i class="bi bi-person-vcard text-primary"></i> Etapa 1 de 3: Identificação & Credenciais de Acesso</h6>
      <span class="badge bg-light text-secondary border">Credenciais</span>
    </div>

    <div class="aws-container-body">
      <p class="text-muted small mb-4">
        Informe o nome, cargo na fazenda e as credenciais que o colaborador utilizará para autenticar no sistema web e no aplicativo do curral.
      </p>

      <div class="row g-3">
        <!-- Nome Completo -->
        <div class="col-md-6">
          <label class="form-label fw-bold">Nome Completo *</label>
          <input type="text" name="nome" id="inputNome" class="form-control" required
                 value="<?= e($usuario['nome'] ?? '') ?>" placeholder="Ex: Carlos Eduardo de Souza">
          <div class="aws-form-hint">Nome de exibição nos relatórios, laudos e auditorias de manejo.</div>
        </div>

        <!-- E-mail de Login -->
        <div class="col-md-6">
          <label class="form-label fw-bold">E-mail de Login *</label>
          <input type="email" name="email" id="inputEmail" class="form-control" required
                 value="<?= e($usuario['email'] ?? '') ?>" placeholder="Ex: carlos.souza@fazenda.com">
          <div class="aws-form-hint">Utilizado para efetuar o login e receber alertas operacionais.</div>
        </div>

        <!-- Cargo / Função -->
        <div class="col-md-6">
          <label class="form-label fw-bold">Cargo / Função na Fazenda</label>
          <input type="text" name="cargo" id="inputCargo" class="form-control" list="cargosList"
                 value="<?= e($usuario['cargo'] ?? 'Colaborador') ?>" placeholder="Ex: Zootecnista, Gerente, Campeiro">
          <datalist id="cargosList">
            <option value="Gerente Operacional">
            <option value="Médico Veterinário">
            <option value="Zootecnista">
            <option value="Capataz / Campeiro">
            <option value="Operador de Balança">
            <option value="Consultor / Auditor">
          </datalist>
          <div class="aws-form-hint">Função ocupada na propriedade (identificada nos laudos gerados).</div>
        </div>

        <!-- Senha de Acesso -->
        <div class="col-md-6">
          <label class="form-label fw-bold">
            Senha de Acesso <?= $isEdit ? '<span class="text-muted fw-normal small">(opcional se mantiver atual)</span>' : '*' ?>
          </label>
          <div class="input-group">
            <input type="password" name="senha" id="inputSenha" class="form-control"
                   <?= $isEdit ? '' : 'required' ?> placeholder="<?= $isEdit ? 'Deixe em branco para manter a atual' : 'Mínimo de 6 dígitos' ?>" autocomplete="new-password">
            <button class="btn btn-outline-secondary" type="button" onclick="toggleVisibilidadeSenha()" title="Mostrar/ocultar senha">
              <i class="bi bi-eye" id="iconOlhoSenha"></i>
            </button>
          </div>
          <div class="aws-form-hint">Recomenda-se uma senha segura com letras e números.</div>
        </div>

        <!-- Status da Conta -->
        <div class="col-12 mt-4 pt-3 border-top">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="ativo" id="checkAtivo" value="1" <?= (!isset($usuario['ativo']) || $usuario['ativo'] == 1) ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold" for="checkAtivo">Conta Ativa e Habilitada para Login</label>
          </div>
          <div class="aws-form-hint">Se desmarcado, o usuário será bloqueado temporariamente sem perder seu histórico.</div>
        </div>
      </div>

      <div class="d-flex justify-content-end mt-4 pt-3 border-top">
        <button type="button" class="btn btn-primary px-4 fw-bold" onclick="validarPasso1EAvancar()">
          Próximo: Nível & Permissões <i class="bi bi-arrow-right ms-1"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       PASSO 2: NÍVEL DE ACESSO & MATRIZ DE PERMISSÕES (RBAC)
  ══════════════════════════════════════════════════════ -->
  <div class="aws-container wizard-step-pane d-none" id="stepPane2">
    <div class="aws-container-header">
      <h6><i class="bi bi-shield-lock text-warning"></i> Etapa 2 de 3: Perfil de Acesso & Privilégios Granulares</h6>
      <span class="badge bg-light text-secondary border">Segurança RBAC</span>
    </div>

    <div class="aws-container-body">
      <!-- Seletor de Tipo de Conta -->
      <div class="p-3 mb-4 rounded border bg-light">
        <label class="form-label fw-bold mb-1">Nível Hierárquico de Conta *</label>
        <select name="tipo" id="selectTipo" class="form-select mb-2" onchange="toggleAdminNotice(this.value)">
          <option value="usuario" <?= ($usuario['tipo'] ?? 'usuario') === 'usuario' ? 'selected' : '' ?>>Colaborador Padrão (Permissões Customizadas abaixo)</option>
          <option value="admin" <?= ($usuario['tipo'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador Mestre (Acesso Total e Irrestrito)</option>
        </select>

        <div id="adminNoticeBox" class="alert alert-primary mb-0 p-2 small" style="display: <?= ($usuario['tipo'] ?? '') === 'admin' ? 'block' : 'none' ?>;">
          <i class="bi bi-info-circle-fill me-1"></i> <strong>Atenção:</strong> O nível <strong>Administrador Mestre</strong> possui acesso irrestrito a todos os módulos, compras, vendas, relatórios financeiros e exclusões. As opções abaixo são pré-concedidas automaticamente.
        </div>
      </div>

      <div id="matrixSection">
        <!-- Presets Rápidos Padronizados -->
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 pb-2 border-bottom">
          <div>
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-key-fill text-warning me-1"></i> Privilégios por Módulo</h6>
            <small class="text-muted">Marque os acessos específicos ou aplique um perfil pré-configurado:</small>
          </div>

          <div class="d-flex align-items-center gap-1 flex-wrap">
            <span class="small text-muted me-1">Perfis Rápidos:</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="applyPreset('gerente')"><i class="bi bi-briefcase me-1"></i>Gerente</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="applyPreset('veterinario')"><i class="bi bi-heart-pulse me-1"></i>Veterinário</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="applyPreset('campo')"><i class="bi bi-phone me-1"></i>Vaqueiro / Campo</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="applyPreset('leitura')"><i class="bi bi-eye me-1"></i>Consulta / Leitura</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="applyPreset('all')"><i class="bi bi-check-all me-1"></i>Marcar Tudo</button>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="applyPreset('none')"><i class="bi bi-x-circle me-1"></i>Limpar</button>
          </div>
        </div>

        <div class="row g-3">
          <!-- Módulo: Animais -->
          <div class="col-md-6">
            <div class="p-3 bg-light rounded border h-100">
              <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
                <i class="bi bi-tag-fill text-success"></i>
                <strong class="text-dark small">1. Gestão do Rebanho (Animais)</strong>
              </div>
              <div class="row g-2">
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[ver_animais]" value="1" id="p_ver_animais" <?= !empty($userPerms['ver_animais']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_ver_animais">Visualizar</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[criar_animais]" value="1" id="p_criar_animais" <?= !empty($userPerms['criar_animais']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_criar_animais">Cadastrar</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[editar_animais]" value="1" id="p_editar_animais" <?= !empty($userPerms['editar_animais']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_editar_animais">Editar</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[excluir_animais]" value="1" id="p_excluir_animais" <?= !empty($userPerms['excluir_animais']) ? 'checked' : '' ?>>
                    <label class="form-check-label small text-danger fw-bold" for="p_excluir_animais">Excluir</label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Módulo: Pesagens -->
          <div class="col-md-6">
            <div class="p-3 bg-light rounded border h-100">
              <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
                <i class="bi bi-rulers text-primary"></i>
                <strong class="text-dark small">2. Pesagens & Balança</strong>
              </div>
              <div class="row g-2">
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[ver_pesagens]" value="1" id="p_ver_pesagens" <?= !empty($userPerms['ver_pesagens']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_ver_pesagens">Visualizar</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[criar_pesagens]" value="1" id="p_criar_pesagens" <?= !empty($userPerms['criar_pesagens']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_criar_pesagens">Lançar</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[editar_pesagens]" value="1" id="p_editar_pesagens" <?= !empty($userPerms['editar_pesagens']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_editar_pesagens">Editar</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[excluir_pesagens]" value="1" id="p_excluir_pesagens" <?= !empty($userPerms['excluir_pesagens']) ? 'checked' : '' ?>>
                    <label class="form-check-label small text-danger fw-bold" for="p_excluir_pesagens">Excluir</label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Módulo: Saúde -->
          <div class="col-md-6">
            <div class="p-3 bg-light rounded border h-100">
              <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
                <i class="bi bi-heart-pulse text-danger"></i>
                <strong class="text-dark small">3. Saúde, Vacinas & Manejos</strong>
              </div>
              <div class="row g-2">
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[ver_saude]" value="1" id="p_ver_saude" <?= !empty($userPerms['ver_saude']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_ver_saude">Visualizar</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[criar_saude]" value="1" id="p_criar_saude" <?= !empty($userPerms['criar_saude']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_criar_saude">Lançar</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[editar_saude]" value="1" id="p_editar_saude" <?= !empty($userPerms['editar_saude']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_editar_saude">Editar</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[excluir_saude]" value="1" id="p_excluir_saude" <?= !empty($userPerms['excluir_saude']) ? 'checked' : '' ?>>
                    <label class="form-check-label small text-danger fw-bold" for="p_excluir_saude">Excluir</label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Módulo: Pastagens -->
          <div class="col-md-6">
            <div class="p-3 bg-light rounded border h-100">
              <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
                <i class="bi bi-tree text-success"></i>
                <strong class="text-dark small">4. Pastagens & Lotes</strong>
              </div>
              <div class="row g-2">
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[ver_pastagens]" value="1" id="p_ver_pastagens" <?= !empty($userPerms['ver_pastagens']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_ver_pastagens">Visualizar</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[criar_pastagens]" value="1" id="p_criar_pastagens" <?= !empty($userPerms['criar_pastagens']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_criar_pastagens">Cadastrar</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[editar_pastagens]" value="1" id="p_editar_pastagens" <?= !empty($userPerms['editar_pastagens']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_editar_pastagens">Editar</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[excluir_pastagens]" value="1" id="p_excluir_pastagens" <?= !empty($userPerms['excluir_pastagens']) ? 'checked' : '' ?>>
                    <label class="form-check-label small text-danger fw-bold" for="p_excluir_pastagens">Excluir</label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Módulo: Reprodução -->
          <div class="col-md-6">
            <div class="p-3 bg-light rounded border h-100">
              <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
                <i class="bi bi-diagram-3 text-info"></i>
                <strong class="text-dark small">5. Reprodução & Inseminação</strong>
              </div>
              <div class="row g-2">
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[ver_reproducao]" value="1" id="p_ver_reproducao" <?= !empty($userPerms['ver_reproducao']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_ver_reproducao">Visualizar</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[criar_reproducao]" value="1" id="p_criar_reproducao" <?= !empty($userPerms['criar_reproducao']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_criar_reproducao">Lançar</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[editar_reproducao]" value="1" id="p_editar_reproducao" <?= !empty($userPerms['editar_reproducao']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_editar_reproducao">Editar</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[excluir_reproducao]" value="1" id="p_excluir_reproducao" <?= !empty($userPerms['excluir_reproducao']) ? 'checked' : '' ?>>
                    <label class="form-check-label small text-danger fw-bold" for="p_excluir_reproducao">Excluir</label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Módulo: Relatórios & Sistema -->
          <div class="col-md-6">
            <div class="p-3 bg-light rounded border h-100">
              <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
                <i class="bi bi-sliders text-secondary"></i>
                <strong class="text-dark small">6. Relatórios & App Mobile</strong>
              </div>
              <div class="row g-2">
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[ver_relatorios]" value="1" id="p_ver_relatorios" <?= !empty($userPerms['ver_relatorios']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_ver_relatorios">Ver Relatórios</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[exportar_relatorios]" value="1" id="p_exportar_relatorios" <?= !empty($userPerms['exportar_relatorios']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_exportar_relatorios">Exportar CSV</label>
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[pode_sincronizar_mobile]" value="1" id="p_mobile" <?= !empty($userPerms['pode_sincronizar_mobile']) ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-bold text-success" for="p_mobile"><i class="bi bi-phone me-1"></i>Sincronizar no App Mobile</label>
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[gerenciar_alertas]" value="1" id="p_alertas" <?= !empty($userPerms['gerenciar_alertas']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="p_alertas">Gerenciar Alertas Sanitários</label>
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-cb" type="checkbox" name="perm[gerenciar_usuarios]" value="1" id="p_gerenciar_usuarios" <?= !empty($userPerms['gerenciar_usuarios']) ? 'checked' : '' ?>>
                    <label class="form-check-label small text-primary fw-bold" for="p_gerenciar_usuarios"><i class="bi bi-people me-1"></i>Gerenciar Equipe / Usuários</label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-between mt-4 pt-3 border-top">
        <button type="button" class="btn btn-outline-secondary px-4" onclick="irParaPasso(1)">
          <i class="bi bi-arrow-left me-1"></i> Voltar: Dados de Acesso
        </button>
        <button type="button" class="btn btn-primary px-4 fw-bold" onclick="irParaPasso(3)">
          Próximo: Revisão <i class="bi bi-arrow-right ms-1"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       PASSO 3: REVISÃO & CONFIRMAÇÃO (AWS REVIEW)
  ══════════════════════════════════════════════════════ -->
  <div class="aws-container wizard-step-pane d-none" id="stepPane3">
    <div class="aws-container-header">
      <h6><i class="bi bi-check-circle text-success"></i> Etapa 3 de 3: Conferência e Confirmação de Cadastro</h6>
      <span class="badge bg-success-subtle text-success border border-success">Pronto para Gravação</span>
    </div>

    <div class="aws-container-body">
      <p class="text-muted small mb-4">
        Verifique abaixo se as credenciais e os níveis de permissão foram configurados corretamente antes de finalizar.
      </p>

      <div class="row g-4">
        <!-- Resumo das Credenciais -->
        <div class="col-md-6">
          <div class="p-3 bg-light rounded border">
            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
              <strong class="text-dark small"><i class="bi bi-person-vcard text-primary me-1"></i> Identificação e Acesso</strong>
              <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" onclick="irParaPasso(1)">Editar</button>
            </div>
            <table class="aws-review-table">
              <tr>
                <td class="label-cell">Nome Completo:</td>
                <td class="value-cell" id="revNome">—</td>
              </tr>
              <tr>
                <td class="label-cell">E-mail de Login:</td>
                <td class="value-cell" id="revEmail">—</td>
              </tr>
              <tr>
                <td class="label-cell">Cargo / Função:</td>
                <td class="value-cell" id="revCargo">—</td>
              </tr>
              <tr>
                <td class="label-cell">Senha de Acesso:</td>
                <td class="value-cell text-muted" id="revSenhaStatus">Definida</td>
              </tr>
              <tr>
                <td class="label-cell">Situação da Conta:</td>
                <td class="value-cell" id="revAtivo">Ativa</td>
              </tr>
            </table>
          </div>
        </div>

        <!-- Resumo dos Privilégios -->
        <div class="col-md-6">
          <div class="p-3 bg-light rounded border">
            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
              <strong class="text-dark small"><i class="bi bi-shield-lock text-warning me-1"></i> Nível & Privilégios</strong>
              <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" onclick="irParaPasso(2)">Editar</button>
            </div>
            <table class="aws-review-table">
              <tr>
                <td class="label-cell">Nível Hierárquico:</td>
                <td class="value-cell" id="revTipo">—</td>
              </tr>
              <tr>
                <td class="label-cell">Privilégios Concedidos:</td>
                <td class="value-cell" id="revPermCount">—</td>
              </tr>
              <tr>
                <td class="label-cell">Acesso App Mobile:</td>
                <td class="value-cell" id="revMobile">—</td>
              </tr>
              <tr>
                <td class="label-cell">Gestão de Equipe:</td>
                <td class="value-cell" id="revGerenciar">—</td>
              </tr>
            </table>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-between mt-4 pt-3 border-top">
        <button type="button" class="btn btn-outline-secondary px-4" onclick="irParaPasso(2)">
          <i class="bi bi-arrow-left me-1"></i> Voltar: Permissões
        </button>

        <button type="submit" class="btn btn-success px-5 fw-bold shadow-sm">
          <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Salvar Alterações' : 'Concluir Cadastro de Colaborador' ?>
        </button>
      </div>
    </div>
  </div>
</form>

<script>
let passoAtual = 1;

function irParaPasso(p) {
  if (p < 1 || p > 3) return;

  // Atualiza os painéis
  for (let i = 1; i <= 3; i++) {
    const pane = document.getElementById('stepPane' + i);
    const ind = document.getElementById('stepIndicator' + i);
    if (pane) {
      if (i === p) {
        pane.classList.remove('d-none');
      } else {
        pane.classList.add('d-none');
      }
    }
    if (ind) {
      if (i === p) {
        ind.className = 'aws-step-item active';
      } else if (i < p) {
        ind.className = 'aws-step-item completed';
      } else {
        ind.className = 'aws-step-item';
      }
    }
  }

  passoAtual = p;

  if (p === 3) {
    preencherRevisao();
  }

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function validarPasso1EAvancar() {
  const nome = document.getElementById('inputNome').value.trim();
  const email = document.getElementById('inputEmail').value.trim();
  const senha = document.getElementById('inputSenha').value;
  const isEdit = <?= json_encode($isEdit) ?>;

  if (!nome) {
    alert('Por favor, informe o Nome Completo do colaborador.');
    document.getElementById('inputNome').focus();
    return;
  }
  if (!email || !email.includes('@')) {
    alert('Por favor, informe um E-mail válido de login.');
    document.getElementById('inputEmail').focus();
    return;
  }
  if (!isEdit && (!senha || senha.length < 4)) {
    alert('Por favor, cadastre uma senha com pelo menos 4 dígitos para o primeiro acesso.');
    document.getElementById('inputSenha').focus();
    return;
  }

  irParaPasso(2);
}

function preencherRevisao() {
  const nome = document.getElementById('inputNome').value.trim() || '—';
  const email = document.getElementById('inputEmail').value.trim() || '—';
  const cargo = document.getElementById('inputCargo').value.trim() || 'Colaborador';
  const senha = document.getElementById('inputSenha').value;
  const isEdit = <?= json_encode($isEdit) ?>;
  const ativo = document.getElementById('checkAtivo').checked;
  const tipo = document.getElementById('selectTipo').value;

  document.getElementById('revNome').textContent = nome;
  document.getElementById('revEmail').textContent = email;
  document.getElementById('revCargo').textContent = cargo;

  if (senha) {
    document.getElementById('revSenhaStatus').textContent = '•••••••• (Alterada)';
  } else if (isEdit) {
    document.getElementById('revSenhaStatus').textContent = 'Inalterada (mantém atual)';
  } else {
    document.getElementById('revSenhaStatus').textContent = 'Não preenchida';
  }

  document.getElementById('revAtivo').innerHTML = ativo 
    ? '<span class="badge bg-success-subtle text-success border border-success">Ativa</span>'
    : '<span class="badge bg-danger-subtle text-danger border border-danger">Inativa / Bloqueada</span>';

  if (tipo === 'admin') {
    document.getElementById('revTipo').innerHTML = '<span class="badge bg-primary">Administrador Mestre</span>';
    document.getElementById('revPermCount').textContent = 'Acesso Total (Irrestrito)';
    document.getElementById('revMobile').textContent = 'Sim (Total)';
    document.getElementById('revGerenciar').textContent = 'Sim (Acesso Total)';
  } else {
    document.getElementById('revTipo').innerHTML = '<span class="badge bg-light text-dark border">Colaborador Padrão</span>';
    const checks = document.querySelectorAll('.perm-cb:checked');
    document.getElementById('revPermCount').textContent = checks.length + ' permissão(ões) ativas';
    
    const podeMobile = document.getElementById('p_mobile').checked;
    document.getElementById('revMobile').innerHTML = podeMobile
      ? '<span class="text-success fw-bold">✓ Autorizado</span>'
      : '<span class="text-muted">✗ Sem acesso mobile</span>';

    const podeGerenciar = document.getElementById('p_gerenciar_usuarios').checked;
    document.getElementById('revGerenciar').innerHTML = podeGerenciar
      ? '<span class="text-primary fw-bold">✓ Sim</span>'
      : '<span class="text-muted">✗ Não</span>';
  }
}

function toggleVisibilidadeSenha() {
  const inSenha = document.getElementById('inputSenha');
  const icone = document.getElementById('iconOlhoSenha');
  if (inSenha.type === 'password') {
    inSenha.type = 'text';
    icone.className = 'bi bi-eye-slash';
  } else {
    inSenha.type = 'password';
    icone.className = 'bi bi-eye';
  }
}

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
    gerente: [
      'p_ver_animais', 'p_criar_animais', 'p_editar_animais',
      'p_ver_pesagens', 'p_criar_pesagens', 'p_editar_pesagens',
      'p_ver_saude', 'p_criar_saude', 'p_editar_saude',
      'p_ver_pastagens', 'p_criar_pastagens', 'p_editar_pastagens',
      'p_ver_reproducao', 'p_criar_reproducao', 'p_editar_reproducao',
      'p_alertas', 'p_mobile', 'p_ver_relatorios', 'p_exportar_relatorios'
    ],
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

// ── Guia e Ajuda Lateral AWS para Usuários & Equipe ──
window.abrirAjudaUsuarios = function() {
  const title = 'Guia: Perfis de Acesso e Gestão de Equipe';
  const html = `
    <div class="aws-help-section">
      <h7><i class="bi bi-shield-check"></i> Controle de Acesso Baseado em Funções (RBAC)</h7>
      <p>O PecuáriaGest permite atribuir exatamente o que cada membro da fazenda pode fazer, garantindo segurança contra exclusões acidentais de dados de animais ou pesagens.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-people"></i> Perfis Sugeridos</h7>
      <ul>
        <li><strong>Gerente:</strong> Acesso a relatórios, pesagens, compras e vendas, sem permissão de excluir o banco.</li>
        <li><strong>Veterinário:</strong> Focado em saúde, protocolos sanitários, carência de medicamentos e reprodução.</li>
        <li><strong>Vaqueiro / Curral:</strong> Acesso à balança, marcação de brincos e sincronização com o celular.</li>
        <li><strong>Consulta / Leitura:</strong> Ideal para consultores e contabilidade externa inspecionarem relatórios sem alterar registros.</li>
      </ul>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-phone"></i> Permissão Mobile</h7>
      <div class="aws-help-tip-box">
        Para que um funcionário consiga autenticar no aplicativo Android/iOS no curral (mesmo offline), a opção <strong>"Sincronizar no App Mobile"</strong> deve estar marcada.
      </div>
    </div>
  `;
  openAwsHelpDrawer(title, html);
};

document.addEventListener('DOMContentLoaded', () => {
  const btnTop = document.getElementById('btnTopHelp');
  if (btnTop) btnTop.onclick = abrirAjudaUsuarios;
});
</script>
