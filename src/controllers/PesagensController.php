<?php
require_once __DIR__ . '/BaseController.php';

/**
 * PecuáriaGest — PesagensController
 *
 * Gerencia o controle ponderal do rebanho: registro de pesagens avulsas,
 * cálculo de evolução de peso, edição de medições e histórico individual.
 */
class PesagensController extends BaseController {

    /**
     * Histórico geral de pesagens (GET /pesagens)
     */
    public function index(): void {
        $this->requireLogin();
        $this->render('pesagens/index', 'Pesagens', 'pesagens');
    }

    /**
     * Formulário de registro de pesagem rápida (GET /pesagens/novo)
     */
    public function novo(): void {
        $this->requireLogin();
        $this->render('pesagens/form', 'Registrar Pesagem', 'pesagens');
    }

    /**
     * Processa a gravação de uma nova pesagem (POST /pesagens/salvar)
     */
    public function salvar(): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/pesagens/novo');
        }

        $animalId = (int)($_POST['animal_id'] ?? 0);
        $peso = (float)($_POST['peso'] ?? 0);
        $data = trim($_POST['data'] ?? '') ?: date('Y-m-d');
        $obs = trim($_POST['observacao'] ?? '') ?: null;

        $stmt = $this->db->prepare("INSERT INTO pesagens (animal_id, peso, data, observacao, origem) VALUES (?, ?, ?, ?, 'web')");
        $stmt->execute([$animalId, $peso, $data, $obs]);

        flash('success', 'Pesagem registrada!');
        $back = ($animalId > 0) ? "/animais/{$animalId}" : '/pesagens';
        $this->redirect($back);
    }

    /**
     * Formulário de edição de pesagem existente (GET /pesagens/{id}/editar)
     */
    public function editar(int $id): void {
        $this->requireLogin();
        $pesagem = $this->buscarPesagem($id);
        $this->render('pesagens/form', 'Editar Pesagem', 'pesagens', ['pesagem' => $pesagem]);
    }

    /**
     * Atualiza os dados de uma pesagem existente (POST /pesagens/{id}/atualizar)
     */
    public function atualizar(int $id): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect("/pesagens/$id/editar");
        }

        $animalId = (int)($_POST['animal_id'] ?? 0);
        $peso = (float)($_POST['peso'] ?? 0);
        $data = trim($_POST['data'] ?? '') ?: date('Y-m-d');
        $obs = trim($_POST['observacao'] ?? '') ?: null;

        $this->db->prepare("UPDATE pesagens SET animal_id = ?, peso = ?, data = ?, observacao = ? WHERE id = ?")
                 ->execute([$animalId, $peso, $data, $obs, $id]);

        flash('success', 'Pesagem atualizada com sucesso!');
        $back = ($animalId > 0) ? "/animais/{$animalId}" : '/pesagens';
        $this->redirect($back);
    }

    /**
     * Exclui um registro de pesagem (POST /pesagens/{id}/excluir)
     */
    public function excluir(int $id): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/pesagens');
        }

        $pesagem = $this->buscarPesagem($id);
        $this->db->prepare("DELETE FROM pesagens WHERE id = ?")->execute([$id]);
        flash('success', 'Pesagem excluída.');

        $back = (!empty($pesagem['animal_id'])) ? "/animais/{$pesagem['animal_id']}" : '/pesagens';
        $this->redirect($back);
    }

    /**
     * Helper privado para buscar pesagem garantindo existência
     */
    private function buscarPesagem(int $id): array {
        $stmt = $this->db->prepare("SELECT * FROM pesagens WHERE id = ?");
        $stmt->execute([$id]);
        $pesagem = $stmt->fetch();
        if (!$pesagem) {
            flash('error', 'Pesagem não encontrada.');
            $this->redirect('/pesagens');
        }
        return $pesagem;
    }
}
