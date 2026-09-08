<?php
/**
 * Controlador do Painel e Notificações de Alertas
 * PecuáriaGest - Arquitetura Limpa MVC
 */

class AlertasController extends BaseController {

    /**
     * Listagem de alertas
     */
    public function index(): void {
        $this->requireLogin();
        $this->render('alertas/index', 'Painel de Alertas', 'alertas');
    }

    /**
     * Marca todos os alertas como lidos (POST /alertas/ler-todos)
     */
    public function lerTodos(): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/alertas');
        }

        $this->db->exec("UPDATE alertas SET lido=1 WHERE lido=0");
        flash('success', 'Todos os alertas foram marcados como lidos.');
        $this->redirect('/alertas');
    }

    /**
     * Formulário de novo alerta manual (GET /alertas/novo)
     */
    public function novo(): void {
        $this->requireLogin();
        $this->render('alertas/form', 'Criar Alerta', 'alertas');
    }

    /**
     * Salva novo alerta manual (POST /alertas/salvar)
     */
    public function salvar(): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/alertas/novo');
        }

        $stmt = $this->db->prepare("INSERT INTO alertas (animal_id,tipo,mensagem) VALUES (?,?,?)");
        $stmt->execute([
            !empty($_POST['animal_id']) ? (int)$_POST['animal_id'] : null,
            $_POST['tipo'] ?? 'aviso',
            $_POST['mensagem'] ?? '',
        ]);

        flash('success', 'Alerta criado com sucesso!');
        $this->redirect('/alertas');
    }

    /**
     * Marca um alerta individual como lido (POST /alertas/{id}/ler)
     */
    public function marcarLido(int $id): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/alertas');
        }

        $stmt = $this->db->prepare("UPDATE alertas SET lido=1 WHERE id=?");
        $stmt->execute([$id]);

        flash('success', 'Alerta marcado como lido.');
        $this->redirect('/alertas');
    }

    /**
     * Exclui um alerta (POST /alertas/{id}/excluir)
     */
    public function excluir(int $id): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/alertas');
        }

        $stmt = $this->db->prepare("DELETE FROM alertas WHERE id=?");
        $stmt->execute([$id]);

        flash('success', 'Alerta excluído.');
        $this->redirect('/alertas');
    }
}
