<?php
/**
 * Controlador de Gestão de Usuários e Controle de Acesso Baseado em Papéis (RBAC)
 * PecuáriaGest - Arquitetura Limpa MVC
 */

class UsuariosController extends BaseController {

    /**
     * Listagem de usuários do sistema
     */
    public function index(): void {
        $this->requireLogin();
        $this->requirePermission('gerenciar_usuarios');
        $this->render('usuarios/index', 'Gestão de Usuários & Permissões', 'usuarios');
    }

    /**
     * Formulário de novo usuário/colaborador
     */
    public function novo(): void {
        $this->requireLogin();
        $this->requirePermission('gerenciar_usuarios');
        $this->render('usuarios/form', 'Novo Colaborador', 'usuarios', ['usuario' => []]);
    }

    /**
     * Gravação de novo colaborador (POST /usuarios/criar)
     */
    public function criar(): void {
        $this->requireLogin();
        $this->requirePermission('gerenciar_usuarios');

        if (!$this->validateCsrf()) {
            flash('error', 'Token de segurança expirado.');
            $this->redirect('/usuarios/novo');
        }

        $nome   = trim($_POST['nome'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $senha  = $_POST['senha'] ?? '';
        $cargo  = trim($_POST['cargo'] ?? 'Colaborador');
        $tipo   = $_POST['tipo'] ?? 'usuario';
        $ativo  = isset($_POST['ativo']) ? 1 : 0;
        $perms  = $_POST['perm'] ?? [];

        if (empty($nome) || empty($email) || empty($senha)) {
            flash('error', 'Nome, e-mail e senha são obrigatórios.');
            $this->redirect('/usuarios/novo');
        }

        // Valida se e-mail já existe
        $exists = $this->db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            flash('error', 'Já existe um colaborador cadastrado com este e-mail.');
            $this->redirect('/usuarios/novo');
        }

        $hash = password_hash($senha, PASSWORD_BCRYPT);
        $permsJson = json_encode($perms);

        $stmt = $this->db->prepare("INSERT INTO usuarios (nome, email, senha, tipo, cargo, permissoes, ativo) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $email, $hash, $tipo, $cargo, $permsJson, $ativo]);

        flash('success', "Colaborador {$nome} cadastrado com sucesso!");
        $this->redirect('/usuarios');
    }

    /**
     * Formulário de edição de colaborador (GET /usuarios/{id}/editar)
     */
    public function editar(int $id): void {
        $this->requireLogin();
        $this->requirePermission('gerenciar_usuarios');

        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$u) {
            flash('error', 'Usuário não encontrado.');
            $this->redirect('/usuarios');
        }

        $this->render('usuarios/form', 'Editar Colaborador', 'usuarios', ['usuario' => $u]);
    }

    /**
     * Atualização de colaborador (POST /usuarios/{id}/salvar)
     */
    public function salvar(int $id): void {
        $this->requireLogin();
        $this->requirePermission('gerenciar_usuarios');

        if (!$this->validateCsrf()) {
            flash('error', 'Token de segurança expirado.');
            $this->redirect("/usuarios/{$id}/editar");
        }

        $nome   = trim($_POST['nome'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $senha  = $_POST['senha'] ?? '';
        $cargo  = trim($_POST['cargo'] ?? 'Colaborador');
        $tipo   = $_POST['tipo'] ?? 'usuario';
        $ativo  = isset($_POST['ativo']) ? 1 : 0;
        $perms  = $_POST['perm'] ?? [];

        if (empty($nome) || empty($email)) {
            flash('error', 'Nome e e-mail são obrigatórios.');
            $this->redirect("/usuarios/{$id}/editar");
        }

        // Valida e-mail duplicado em outro ID
        $check = $this->db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ? LIMIT 1");
        $check->execute([$email, $id]);
        if ($check->fetch()) {
            flash('error', 'Este e-mail já está sendo utilizado por outro colaborador.');
            $this->redirect("/usuarios/{$id}/editar");
        }

        $permsJson = json_encode($perms);

        if (!empty($senha)) {
            $hash = password_hash($senha, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("UPDATE usuarios SET nome = ?, email = ?, senha = ?, tipo = ?, cargo = ?, permissoes = ?, ativo = ? WHERE id = ?");
            $stmt->execute([$nome, $email, $hash, $tipo, $cargo, $permsJson, $ativo, $id]);
        } else {
            $stmt = $this->db->prepare("UPDATE usuarios SET nome = ?, email = ?, tipo = ?, cargo = ?, permissoes = ?, ativo = ? WHERE id = ?");
            $stmt->execute([$nome, $email, $tipo, $cargo, $permsJson, $ativo, $id]);
        }

        // Se editou o próprio usuário logado, atualiza a sessão
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
            $updatedUser = $this->db->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
            $updatedUser->execute([$id]);
            $_SESSION['user'] = $updatedUser->fetch(PDO::FETCH_ASSOC);
        }

        flash('success', "Dados de {$nome} atualizados com sucesso!");
        $this->redirect('/usuarios');
    }

    /**
     * Alterna status ativo/inativo do usuário (POST /usuarios/{id}/toggle-status)
     */
    public function toggleStatus(int $id): void {
        $this->requireLogin();
        $this->requirePermission('gerenciar_usuarios');

        if (!$this->validateCsrf()) {
            flash('error', 'Token de segurança expirado.');
            $this->redirect('/usuarios');
        }

        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
            flash('error', 'Você não pode desativar seu próprio usuário.');
            $this->redirect('/usuarios');
        }

        $stmt = $this->db->prepare("UPDATE usuarios SET ativo = (1 - ativo) WHERE id = ?");
        $stmt->execute([$id]);

        flash('success', 'Status do usuário alterado com sucesso.');
        $this->redirect('/usuarios');
    }

    /**
     * Exclui colaborador (POST /usuarios/{id}/excluir)
     */
    public function excluir(int $id): void {
        $this->requireLogin();
        $this->requirePermission('gerenciar_usuarios');

        if (!$this->validateCsrf()) {
            flash('error', 'Token de segurança expirado.');
            $this->redirect('/usuarios');
        }

        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
            flash('error', 'Você não pode excluir seu próprio usuário.');
            $this->redirect('/usuarios');
        }

        $stmt = $this->db->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);

        flash('success', 'Usuário removido da equipe com sucesso.');
        $this->redirect('/usuarios');
    }
}
