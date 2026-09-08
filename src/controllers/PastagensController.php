<?php
/**
 * Controlador de Gestão de Pastagens e Lotação
 * PecuáriaGest - Arquitetura Limpa MVC
 */

class PastagensController extends BaseController {

    /**
     * Listagem geral de pastagens e piquetes
     */
    public function index(): void {
        $this->requireLogin();
        $this->render('pastagens/index', 'Pastagens & Piquetes', 'pastagens');
    }

    /**
     * Formulário de cadastro de pastagem
     */
    public function novo(): void {
        $this->requireLogin();
        $this->render('pastagens/form', 'Cadastrar Pastagem', 'pastagens');
    }

    /**
     * Gravação de nova pastagem (POST /pastagens/salvar)
     */
    public function salvar(): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/pastagens/novo');
        }

        $stmt = $this->db->prepare("INSERT INTO pastagens (nome,area_ha,capacidade,status,observacao) VALUES (?,?,?,?,?)");
        $stmt->execute([
            trim($_POST['nome'] ?? ''),
            !empty($_POST['area_ha']) ? $_POST['area_ha'] : null,
            !empty($_POST['capacidade']) ? $_POST['capacidade'] : null,
            $_POST['status'] ?? 'ativa',
            trim($_POST['observacao'] ?? '') ?: null,
        ]);

        flash('success', 'Pastagem cadastrada com sucesso!');
        $this->redirect('/pastagens');
    }

    /**
     * Exibe os animais alocados na pastagem (GET /pastagens/{id})
     */
    public function show(int $id): void {
        $this->requireLogin();
        $stmt = $this->db->prepare("SELECT id FROM pastagens WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            flash('error', 'Pastagem não encontrada.');
            $this->redirect('/pastagens');
        }

        $this->redirect('/animais?pasto_id=' . $id);
    }

    /**
     * Formulário de edição de pastagem (GET /pastagens/{id}/editar)
     */
    public function editar(int $id): void {
        $this->requireLogin();
        $stmt = $this->db->prepare("SELECT * FROM pastagens WHERE id = ?");
        $stmt->execute([$id]);
        $pastagem = $stmt->fetch() ?: null;

        if (!$pastagem) {
            flash('error', 'Pastagem não encontrada.');
            $this->redirect('/pastagens');
        }

        $this->render('pastagens/form', 'Editar Pastagem', 'pastagens', ['pastagem' => $pastagem]);
    }

    /**
     * Atualização de pastagem (POST /pastagens/{id}/atualizar)
     */
    public function atualizar(int $id): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect("/pastagens/{$id}/editar");
        }

        $stmt = $this->db->prepare("UPDATE pastagens SET nome=?,area_ha=?,capacidade=?,status=?,observacao=? WHERE id=?");
        $stmt->execute([
            trim($_POST['nome'] ?? ''),
            !empty($_POST['area_ha']) ? $_POST['area_ha'] : null,
            !empty($_POST['capacidade']) ? $_POST['capacidade'] : null,
            $_POST['status'] ?? 'ativa',
            trim($_POST['observacao'] ?? '') ?: null,
            $id,
        ]);

        flash('success', 'Pastagem atualizada com sucesso!');
        $this->redirect('/pastagens');
    }

    /**
     * Exclusão de pastagem (POST /pastagens/{id}/excluir)
     */
    public function excluir(int $id): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/pastagens');
        }

        $del = $this->db->prepare("DELETE FROM pastagens WHERE id = ?");
        $del->execute([$id]);

        flash('success', 'Pastagem removida com sucesso.');
        $this->redirect('/pastagens');
    }
}
