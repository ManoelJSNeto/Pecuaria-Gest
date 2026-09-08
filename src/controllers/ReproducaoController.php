<?php
/**
 * Controlador de Manejo Reprodutivo e Genealogia
 * PecuáriaGest - Arquitetura Limpa MVC
 */

class ReproducaoController extends BaseController {

    /**
     * Listagem geral de manejos reprodutivos
     */
    public function index(): void {
        $this->requireLogin();
        $this->requirePermission('ver_reproducao');
        $this->render('reproducao/index', 'Manejo Reprodutivo', 'reproducao');
    }

    /**
     * Formulário de novo evento reprodutivo
     */
    public function novo(): void {
        $this->requireLogin();
        $this->requirePermission('ver_reproducao');
        $this->render('reproducao/form', 'Registrar Evento Reprodutivo', 'reproducao');
    }

    /**
     * Gravação de novo manejo reprodutivo (POST /reproducao/salvar)
     */
    public function salvar(): void {
        $this->requireLogin();
        $this->requirePermission('ver_reproducao');

        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/reproducao/novo');
        }

        $animalId = (int)($_POST['animal_id'] ?? 0);
        $touroBrinco = trim($_POST['touro_brinco'] ?? '') ?: null;
        $tipo = trim($_POST['tipo'] ?? '');
        $data = $_POST['data'] ?? date('Y-m-d');
        $resultado = trim($_POST['resultado'] ?? '') ?: null;
        $obs = trim($_POST['observacao'] ?? '') ?: null;

        $matrizStmt = $this->db->prepare("SELECT id, brinco, sexo FROM animais WHERE id = ?");
        $matrizStmt->execute([$animalId]);
        $matriz = $matrizStmt->fetch();

        if (!$matriz) {
            flash('error', 'Animal não encontrado para o manejo reprodutivo.');
            $this->redirect('/reproducao/novo');
        }

        if ($matriz['sexo'] !== 'F') {
            flash('error', 'Inconsistência zootécnica: A matriz reprodutiva selecionada deve ser obrigatoriamente uma FÊMEA.');
            $this->redirect('/reproducao/novo');
        }

        if ($touroBrinco) {
            if (strtoupper($matriz['brinco']) === strtoupper($touroBrinco)) {
                flash('error', 'Inconsistência: A matriz reprodutiva não pode ser o próprio touro da cobertura.');
                $this->redirect('/reproducao/novo');
            }

            $tStmt = $this->db->prepare("SELECT id, sexo FROM animais WHERE UPPER(brinco) = UPPER(?)");
            $tStmt->execute([$touroBrinco]);
            $touro = $tStmt->fetch();

            if ($touro && $touro['sexo'] === 'F') {
                flash('error', 'Inconsistência zootécnica: O animal com brinco "' . $touroBrinco . '" é uma FÊMEA e não pode ser informado como touro reprodutor.');
                $this->redirect('/reproducao/novo');
            }
        }

        $stmt = $this->db->prepare("INSERT INTO reproducao (animal_id, tipo, data, resultado, touro_brinco, observacao) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$animalId, $tipo, $data, $resultado, $touroBrinco, $obs]);

        // Atualização automática de status da fêmea (prenha / parto / aborto / desmame)
        if (function_exists('atualizarStatusAnimalPorReproducao')) {
            atualizarStatusAnimalPorReproducao($this->db, $animalId, $tipo, $resultado);
        }

        flash('success', 'Evento reprodutivo registrado com sucesso!');
        $back = !empty($animalId) ? "/animais/{$animalId}" : '/reproducao';
        $this->redirect($back);
    }

    /**
     * Formulário de edição de evento reprodutivo
     */
    public function editar(int $id): void {
        $this->requireLogin();
        $this->requirePermission('ver_reproducao');

        $stmt = $this->db->prepare("SELECT * FROM reproducao WHERE id = ?");
        $stmt->execute([$id]);
        $reproducao = $stmt->fetch() ?: null;

        if (!$reproducao) {
            flash('error', 'Registro reprodutivo não encontrado.');
            $this->redirect('/reproducao');
        }

        $this->render('reproducao/form', 'Editar Evento Reprodutivo', 'reproducao', ['reproducao' => $reproducao]);
    }

    /**
     * Atualização de evento reprodutivo (POST /reproducao/{id}/atualizar)
     */
    public function atualizar(int $id): void {
        $this->requireLogin();
        $this->requirePermission('ver_reproducao');

        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect("/reproducao/{$id}/editar");
        }

        $animalId = (int)($_POST['animal_id'] ?? 0);
        $touroBrinco = trim($_POST['touro_brinco'] ?? '') ?: null;
        $tipo = trim($_POST['tipo'] ?? '');
        $data = $_POST['data'] ?? date('Y-m-d');
        $resultado = trim($_POST['resultado'] ?? '') ?: null;
        $obs = trim($_POST['observacao'] ?? '') ?: null;

        $matrizStmt = $this->db->prepare("SELECT id, brinco, sexo FROM animais WHERE id = ?");
        $matrizStmt->execute([$animalId]);
        $matriz = $matrizStmt->fetch();

        if (!$matriz) {
            flash('error', 'Animal não encontrado para o manejo reprodutivo.');
            $this->redirect("/reproducao/{$id}/editar");
        }

        if ($matriz['sexo'] !== 'F') {
            flash('error', 'Inconsistência zootécnica: A matriz reprodutiva selecionada deve ser obrigatoriamente uma FÊMEA.');
            $this->redirect("/reproducao/{$id}/editar");
        }

        if ($touroBrinco) {
            if (strtoupper($matriz['brinco']) === strtoupper($touroBrinco)) {
                flash('error', 'Inconsistência: A matriz reprodutiva não pode ser o próprio touro da cobertura.');
                $this->redirect("/reproducao/{$id}/editar");
            }

            $tStmt = $this->db->prepare("SELECT id, sexo FROM animais WHERE UPPER(brinco) = UPPER(?)");
            $tStmt->execute([$touroBrinco]);
            $touro = $tStmt->fetch();

            if ($touro && $touro['sexo'] === 'F') {
                flash('error', 'Inconsistência zootécnica: O animal com brinco "' . $touroBrinco . '" é uma FÊMEA e não pode ser informado como touro reprodutor.');
                $this->redirect("/reproducao/{$id}/editar");
            }
        }

        $stmt = $this->db->prepare("UPDATE reproducao SET animal_id=?, tipo=?, data=?, resultado=?, touro_brinco=?, observacao=? WHERE id=?");
        $stmt->execute([$animalId, $tipo, $data, $resultado, $touroBrinco, $obs, $id]);

        if (function_exists('atualizarStatusAnimalPorReproducao')) {
            atualizarStatusAnimalPorReproducao($this->db, $animalId, $tipo, $resultado);
        }

        flash('success', 'Registro reprodutivo atualizado!');
        $back = !empty($animalId) ? "/animais/{$animalId}" : '/reproducao';
        $this->redirect($back);
    }

    /**
     * Exclusão de evento reprodutivo (POST /reproducao/{id}/excluir)
     */
    public function excluir(int $id): void {
        $this->requireLogin();
        $this->requirePermission('ver_reproducao');

        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/reproducao');
        }

        $stmt = $this->db->prepare("SELECT animal_id FROM reproducao WHERE id = ?");
        $stmt->execute([$id]);
        $reproducao = $stmt->fetch();

        $del = $this->db->prepare("DELETE FROM reproducao WHERE id = ?");
        $del->execute([$id]);

        flash('success', 'Registro reprodutivo excluído.');
        $back = ($reproducao && !empty($reproducao['animal_id'])) ? "/animais/{$reproducao['animal_id']}" : '/reproducao';
        $this->redirect($back);
    }
}
