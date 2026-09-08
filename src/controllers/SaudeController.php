<?php
/**
 * Controlador de Gestão Sanitária e Saúde Animal
 * PecuáriaGest - Arquitetura Limpa MVC
 */

class SaudeController extends BaseController {

    /**
     * Listagem geral de eventos de saúde
     */
    public function index(): void {
        $this->requireLogin();
        $this->render('saude/index', 'Saúde & Sanidade', 'saude');
    }

    /**
     * Formulário de novo evento de saúde
     */
    public function novo(): void {
        $this->requireLogin();
        $this->render('saude/form', 'Registrar Evento de Saúde', 'saude');
    }

    /**
     * Gravação de novo evento de saúde (POST /saude/salvar)
     */
    public function salvar(): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/saude/novo');
        }

        $aid = (int)($_POST['animal_id'] ?? 0);
        $tipo = (string)($_POST['tipo'] ?? '');

        $stmt = $this->db->prepare("INSERT INTO saude (animal_id,tipo,descricao,data,proxima_data,custo,medicamento,dose,veterinario,observacao,origem) VALUES (?,?,?,?,?,?,?,?,?,?,'web')");
        $stmt->execute([
            $aid,
            $tipo,
            $_POST['descricao'] ?? '',
            $_POST['data'] ?? date('Y-m-d'),
            !empty($_POST['proxima_data']) ? $_POST['proxima_data'] : null,
            !empty($_POST['custo']) ? $_POST['custo'] : null,
            !empty($_POST['medicamento']) ? $_POST['medicamento'] : null,
            !empty($_POST['dose']) ? $_POST['dose'] : null,
            !empty($_POST['veterinario']) ? $_POST['veterinario'] : null,
            trim($_POST['observacao'] ?? '') ?: null,
        ]);

        // Atualização automática de status (óbito -> morto, tratamento -> doente, alta -> ativo, parto -> ativo)
        if (function_exists('atualizarStatusAnimalPorSaude')) {
            atualizarStatusAnimalPorSaude($this->db, $aid, $tipo);
        }

        flash('success', 'Evento de saúde registrado!');
        $back = !empty($aid) ? "/animais/{$aid}" : '/saude';
        $this->redirect($back);
    }

    /**
     * Formulário de edição de evento de saúde
     */
    public function editar(int $id): void {
        $this->requireLogin();
        $stmt = $this->db->prepare("SELECT * FROM saude WHERE id = ?");
        $stmt->execute([$id]);
        $saude = $stmt->fetch() ?: null;

        if (!$saude) {
            flash('error', 'Registro de saúde não encontrado.');
            $this->redirect('/saude');
        }

        $this->render('saude/form', 'Editar Evento de Saúde', 'saude', ['saude' => $saude]);
    }

    /**
     * Atualiza evento de saúde existente (POST /saude/{id}/atualizar)
     */
    public function atualizar(int $id): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect("/saude/{$id}/editar");
        }

        $aid = (int)($_POST['animal_id'] ?? 0);
        $tipo = (string)($_POST['tipo'] ?? '');

        $stmt = $this->db->prepare("UPDATE saude SET animal_id=?,tipo=?,descricao=?,data=?,proxima_data=?,custo=?,medicamento=?,dose=?,veterinario=?,observacao=? WHERE id=?");
        $stmt->execute([
            $aid,
            $tipo,
            $_POST['descricao'] ?? '',
            $_POST['data'] ?? date('Y-m-d'),
            !empty($_POST['proxima_data']) ? $_POST['proxima_data'] : null,
            !empty($_POST['custo']) ? $_POST['custo'] : null,
            !empty($_POST['medicamento']) ? $_POST['medicamento'] : null,
            !empty($_POST['dose']) ? $_POST['dose'] : null,
            !empty($_POST['veterinario']) ? $_POST['veterinario'] : null,
            trim($_POST['observacao'] ?? '') ?: null,
            $id,
        ]);

        if (function_exists('atualizarStatusAnimalPorSaude')) {
            atualizarStatusAnimalPorSaude($this->db, $aid, $tipo);
        }

        flash('success', 'Evento de saúde atualizado!');
        $back = !empty($aid) ? "/animais/{$aid}" : '/saude';
        $this->redirect($back);
    }

    /**
     * Exclui registro de saúde (POST /saude/{id}/excluir)
     */
    public function excluir(int $id): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/saude');
        }

        $stmt = $this->db->prepare("SELECT animal_id FROM saude WHERE id = ?");
        $stmt->execute([$id]);
        $saude = $stmt->fetch();

        $del = $this->db->prepare("DELETE FROM saude WHERE id = ?");
        $del->execute([$id]);

        flash('success', 'Registro excluído.');
        $back = ($saude && !empty($saude['animal_id'])) ? "/animais/{$saude['animal_id']}" : '/saude';
        $this->redirect($back);
    }
}
