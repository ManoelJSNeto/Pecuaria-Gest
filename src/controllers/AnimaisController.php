<?php
require_once __DIR__ . '/BaseController.php';

/**
 * PecuáriaGest — AnimaisController
 *
 * Gerencia o inventário do rebanho: cadastro, validações zootécnicas
 * de maternidade e paternidade, prontuário individual, linha do tempo
 * fotográfica (com detecção de filhote) e atualizações biológicas.
 */
class AnimaisController extends BaseController {

    /**
     * Listagem geral de animais com filtros e busca (GET /animais)
     */
    public function index(): void {
        $this->requireLogin();
        $this->render('animais/index', 'Animais', 'animais');
    }

    /**
     * Formulário de cadastro de novo animal (GET /animais/novo)
     */
    public function novo(): void {
        $this->requireLogin();
        $this->render('animais/form', 'Cadastrar Animal', 'animais');
    }

    /**
     * Processa a criação de um novo animal (POST /animais/salvar)
     */
    public function salvar(): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/animais/novo');
        }

        $brinco = trim($_POST['brinco'] ?? '');
        $maeId = !empty($_POST['mae_id']) ? (int)$_POST['mae_id'] : null;
        $paiBrinco = trim($_POST['pai_brinco'] ?? '') ?: null;

        // Validação estrita da Mãe: deve ser fêmea e não pode ser ela mesma
        if ($maeId) {
            $chkMae = $this->db->prepare("SELECT id, brinco, sexo FROM animais WHERE id = ?");
            $chkMae->execute([$maeId]);
            $mae = $chkMae->fetch();
            if (!$mae) {
                flash('error', 'A mãe biológica informada não foi encontrada no rebanho.');
                $this->redirect('/animais/novo');
            }
            if ($mae['sexo'] !== 'F') {
                flash('error', 'Inconsistência zootécnica: A mãe biológica deve ser obrigatoriamente uma FÊMEA.');
                $this->redirect('/animais/novo');
            }
            if (strtoupper($mae['brinco']) === strtoupper($brinco)) {
                flash('error', 'Inconsistência genealógica: O animal não pode ser a mãe de si mesmo.');
                $this->redirect('/animais/novo');
            }
        }

        // Validação estrita do Pai: não pode ser o próprio animal nem uma fêmea
        if ($paiBrinco) {
            if (strtoupper($paiBrinco) === strtoupper($brinco)) {
                flash('error', 'Inconsistência genealógica: O animal não pode ser o pai de si mesmo.');
                $this->redirect('/animais/novo');
            }
            if ($maeId && isset($mae) && strtoupper($paiBrinco) === strtoupper($mae['brinco'])) {
                flash('error', 'Inconsistência zootécnica: O pai e a mãe biológicos não podem ser o mesmo animal.');
                $this->redirect('/animais/novo');
            }
            $chkPai = $this->db->prepare("SELECT id, sexo FROM animais WHERE UPPER(brinco) = UPPER(?)");
            $chkPai->execute([$paiBrinco]);
            $pai = $chkPai->fetch();
            if ($pai && $pai['sexo'] === 'F') {
                flash('error', 'Inconsistência zootécnica: O animal com brinco "' . $paiBrinco . '" é uma FÊMEA e não pode ser informado como pai/touro reprodutor.');
                $this->redirect('/animais/novo');
            }
        }

        $fotoUrl = !empty($_FILES['foto']) ? uploadFoto($_FILES['foto']) : null;
        $stmt = $this->db->prepare("
            INSERT INTO animais (brinco, nome, sexo, raca, data_nascimento, peso_inicial, status, pasto_id, origem, mae_id, pai_brinco, observacao, foto_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        try {
            $stmt->execute([
                $brinco,
                trim($_POST['nome'] ?? '') ?: null,
                $_POST['sexo'] ?? 'M',
                trim($_POST['raca'] ?? '') ?: null,
                $_POST['data_nascimento'] ?: null,
                $_POST['peso_inicial'] ?: null,
                $_POST['status'] ?? 'ativo',
                $_POST['pasto_id'] ?: null,
                trim($_POST['origem'] ?? '') ?: null,
                $maeId,
                $paiBrinco,
                trim($_POST['observacao'] ?? '') ?: null,
                $fotoUrl
            ]);
            $newId = (int)$this->db->lastInsertId();
            if ($fotoUrl && $newId) {
                $isPuppy = isFilhote($_POST['data_nascimento'] ?: null);
                $this->db->prepare("INSERT INTO fotos_animais (animal_id, foto_url, tipo_evento, fase, data, observacao) VALUES (?, ?, ?, ?, ?, ?)")
                         ->execute([$newId, $fotoUrl, 'nascimento', $isPuppy ? 'filhote' : 'adulto', $_POST['data_nascimento'] ?: date('Y-m-d'), 'Foto de cadastro inicial']);
            }
            flash('success', 'Animal cadastrado com sucesso!');
            $this->redirect('/animais/' . $newId);
        } catch (Exception $e) {
            flash('error', 'Erro: brinco já existe ou dados inválidos.');
            $this->redirect('/animais/novo');
        }
    }

    /**
     * Exibe o prontuário completo de um animal (GET /animais/{id})
     */
    public function show(int $id): void {
        $this->requireLogin();
        $animal = $this->buscarAnimal($id);
        $this->render('animais/show', 'Animal #' . $animal['brinco'], 'animais', ['animal' => $animal]);
    }

    /**
     * Emite a Ficha Cadastral e Prontuário Individual do Animal em A4/PDF (GET /animais/{id}/pdf)
     */
    public function pdf(int $id): void {
        $this->requireLogin();
        $animal = $this->buscarAnimal($id);

        $pastoNome = 'Sem pasto definido';
        if (!empty($animal['pasto_id'])) {
            $pst = $this->db->prepare("SELECT nome FROM pastagens WHERE id = ?");
            $pst->execute([$animal['pasto_id']]);
            $pRow = $pst->fetch();
            if ($pRow) {
                $pastoNome = $pRow['nome'];
            }
        }

        $mae = null;
        if (!empty($animal['mae_id'])) {
            $mStmt = $this->db->prepare("SELECT id, brinco, nome FROM animais WHERE id = ?");
            $mStmt->execute([$animal['mae_id']]);
            $mae = $mStmt->fetch() ?: null;
        }

        $pai = null;
        if (!empty($animal['pai_brinco'])) {
            $paiStmt = $this->db->prepare("SELECT id, brinco, nome FROM animais WHERE brinco = ?");
            $paiStmt->execute([$animal['pai_brinco']]);
            $pai = $paiStmt->fetch() ?: null;
        }

        $compra = null;
        if (!empty($animal['compra_id'])) {
            $cStmt = $this->db->prepare("SELECT * FROM compras WHERE id = ?");
            $cStmt->execute([$animal['compra_id']]);
            $compra = $cStmt->fetch() ?: null;
        }

        // Histórico de pesagens em ordem cronológica com cálculo de GMD
        $pStmt = $this->db->prepare("SELECT * FROM pesagens WHERE animal_id = ? ORDER BY data ASC, id ASC");
        $pStmt->execute([$id]);
        $rawPesagens = $pStmt->fetchAll();

        $pesagens = [];
        $prevPeso = $animal['peso_inicial'] !== null ? (float)$animal['peso_inicial'] : null;
        $prevData = !empty($animal['data_nascimento']) ? $animal['data_nascimento'] : null;

        foreach ($rawPesagens as $p) {
            $curPeso = (float)$p['peso'];
            $curData = $p['data'];
            $ganho = null;
            $dias = null;
            $gmd = null;

            if ($prevPeso !== null && $prevData !== null) {
                try {
                    $d1 = new DateTime($prevData);
                    $d2 = new DateTime($curData);
                    $diff = (int)$d1->diff($d2)->format('%r%a');
                    if ($diff > 0) {
                        $ganho = $curPeso - $prevPeso;
                        $dias = $diff;
                        $gmd = $ganho / $diff;
                    }
                } catch (Exception $e) {}
            }

            $p['ganho'] = $ganho;
            $p['dias'] = $dias;
            $p['gmd'] = $gmd;
            $pesagens[] = $p;

            $prevPeso = $curPeso;
            $prevData = $curData;
        }

        // Histórico sanitário
        $sStmt = $this->db->prepare("SELECT * FROM saude WHERE animal_id = ? ORDER BY data DESC, id DESC");
        $sStmt->execute([$id]);
        $saude = $sStmt->fetchAll();

        // Histórico reprodutivo
        $rStmt = $this->db->prepare("SELECT * FROM reproducao WHERE animal_id = ? ORDER BY data DESC, id DESC");
        $rStmt->execute([$id]);
        $reproducao = $rStmt->fetchAll();

        $this->renderPrint('animais/pdf', 'Ficha Cadastral #' . $animal['brinco'], [
            'a' => $animal,
            'pastoNome' => $pastoNome,
            'mae' => $mae,
            'pai' => $pai,
            'compra' => $compra,
            'pesagens' => $pesagens,
            'saude' => $saude,
            'reproducao' => $reproducao,
        ]);
    }

    /**
     * Formulário de edição cadastral (GET /animais/{id}/editar)
     */
    public function editar(int $id): void {
        $this->requireLogin();
        $animal = $this->buscarAnimal($id);
        $this->render('animais/form', 'Editar Animal', 'animais', ['animal' => $animal]);
    }

    /**
     * Adiciona nova foto à linha do tempo do animal (POST /animais/{id}/foto)
     */
    public function adicionarFoto(int $id): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect("/animais/$id");
        }

        $animal = $this->buscarAnimal($id);

        if (!empty($_FILES['foto']['tmp_name'])) {
            $fUrl = uploadFoto($_FILES['foto']);
            if ($fUrl) {
                $tipo = $_POST['tipo_evento'] ?? 'geral';
                $isSensivel = (!empty($_POST['is_sensivel']) || $tipo === 'obito' || $animal['status'] === 'morto') ? 1 : 0;
                $fase = $tipo === 'obito' ? 'obito' : (isFilhote($animal['data_nascimento']) ? 'filhote' : 'adulto');
                $obs = trim($_POST['observacao'] ?? '') ?: null;
                $dataFoto = $_POST['data'] ?: date('Y-m-d');

                $this->db->prepare("INSERT INTO fotos_animais (animal_id, foto_url, tipo_evento, fase, is_sensivel, data, observacao) VALUES (?, ?, ?, ?, ?, ?, ?)")
                         ->execute([$id, $fUrl, $tipo, $fase, $isSensivel, $dataFoto, $obs]);

                if (empty($animal['foto_url']) || !empty($_POST['definir_principal'])) {
                    $this->db->prepare("UPDATE animais SET foto_url = ? WHERE id = ?")->execute([$fUrl, $id]);
                }
                flash('success', 'Foto adicionada com sucesso à galeria do animal!');
            } else {
                flash('error', 'Formato de imagem inválido (use JPG, PNG ou WEBP).');
            }
        }
        $this->redirect("/animais/$id");
    }

    /**
     * Atualiza os dados cadastrais e genealógicos do animal (POST /animais/{id}/atualizar)
     */
    public function atualizar(int $id): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect("/animais/$id/editar");
        }

        $animal = $this->buscarAnimal($id);
        $brinco = trim($_POST['brinco'] ?? '');
        $maeId = !empty($_POST['mae_id']) ? (int)$_POST['mae_id'] : null;
        $paiBrinco = trim($_POST['pai_brinco'] ?? '') ?: null;

        // Validação estrita da Mãe
        if ($maeId) {
            if ($maeId === $id) {
                flash('error', 'Inconsistência genealógica: O animal não pode ser a mãe de si mesmo.');
                $this->redirect("/animais/$id/editar");
            }
            $chkMae = $this->db->prepare("SELECT id, brinco, sexo FROM animais WHERE id = ?");
            $chkMae->execute([$maeId]);
            $mae = $chkMae->fetch();
            if (!$mae) {
                flash('error', 'A mãe selecionada não foi encontrada no rebanho.');
                $this->redirect("/animais/$id/editar");
            }
            if ($mae['sexo'] !== 'F') {
                flash('error', 'Inconsistência zootécnica: A mãe biológica deve ser obrigatoriamente uma FÊMEA.');
                $this->redirect("/animais/$id/editar");
            }
            if (strtoupper($mae['brinco']) === strtoupper($brinco)) {
                flash('error', 'Inconsistência genealógica: O animal não pode ser a mãe de si mesmo.');
                $this->redirect("/animais/$id/editar");
            }
        }

        // Validação estrita do Pai
        if ($paiBrinco) {
            if (strtoupper($paiBrinco) === strtoupper($brinco)) {
                flash('error', 'Inconsistência genealógica: O animal não pode ser o pai de si mesmo.');
                $this->redirect("/animais/$id/editar");
            }
            if ($maeId && isset($mae) && strtoupper($paiBrinco) === strtoupper($mae['brinco'])) {
                flash('error', 'Inconsistência zootécnica: O pai e a mãe biológicos não podem ser o mesmo animal.');
                $this->redirect("/animais/$id/editar");
            }
            $chkPai = $this->db->prepare("SELECT id, sexo FROM animais WHERE UPPER(brinco) = UPPER(?)");
            $chkPai->execute([$paiBrinco]);
            $pai = $chkPai->fetch();
            if ($pai) {
                if ($pai['id'] === $id) {
                    flash('error', 'Inconsistência genealógica: O animal não pode ser o pai de si mesmo.');
                    $this->redirect("/animais/$id/editar");
                }
                if ($pai['sexo'] === 'F') {
                    flash('error', 'Inconsistência zootécnica: O animal com brinco "' . $paiBrinco . '" é uma FÊMEA e não pode ser informado como pai/touro reprodutor.');
                    $this->redirect("/animais/$id/editar");
                }
            }
        }

        $fotoUrl = $animal['foto_url'];
        if (!empty($_FILES['foto']['tmp_name'])) {
            $newPhoto = uploadFoto($_FILES['foto']);
            if ($newPhoto) {
                $fotoUrl = $newPhoto;
                $this->db->prepare("INSERT INTO fotos_animais (animal_id, foto_url, tipo_evento, fase, data, observacao) VALUES (?, ?, 'perfil', ?, ?, ?)")
                         ->execute([$id, $newPhoto, isFilhote($_POST['data_nascimento'] ?: null) ? 'filhote' : 'adulto', date('Y-m-d'), 'Atualização de foto de perfil']);
            }
        }

        $this->db->prepare("
            UPDATE animais 
            SET brinco = ?, nome = ?, sexo = ?, raca = ?, data_nascimento = ?, peso_inicial = ?, status = ?, pasto_id = ?, origem = ?, mae_id = ?, pai_brinco = ?, observacao = ?, foto_url = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([
            $brinco,
            trim($_POST['nome'] ?? '') ?: null,
            $_POST['sexo'] ?? 'M',
            trim($_POST['raca'] ?? '') ?: null,
            $_POST['data_nascimento'] ?: null,
            $_POST['peso_inicial'] ?: null,
            $_POST['status'] ?? 'ativo',
            $_POST['pasto_id'] ?: null,
            trim($_POST['origem'] ?? '') ?: null,
            $maeId,
            $paiBrinco,
            trim($_POST['observacao'] ?? '') ?: null,
            $fotoUrl,
            $id,
        ]);

        flash('success', 'Animal atualizado!');
        $this->redirect("/animais/$id");
    }

    /**
     * Remove um animal do rebanho (POST /animais/{id}/excluir)
     */
    public function excluir(int $id): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/animais');
        }

        $this->db->prepare("DELETE FROM animais WHERE id = ?")->execute([$id]);
        flash('success', 'Animal removido.');
        $this->redirect('/animais');
    }

    /**
     * Remove uma foto da linha do tempo do animal (POST /fotos/{id}/excluir)
     */
    public function excluirFoto(int $fotoId): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/animais');
        }

        $fStmt = $this->db->prepare("SELECT animal_id, foto_url FROM fotos_animais WHERE id = ?");
        $fStmt->execute([$fotoId]);
        $f = $fStmt->fetch();

        if ($f) {
            $this->db->prepare("DELETE FROM fotos_animais WHERE id = ?")->execute([$fotoId]);
            flash('success', 'Foto removida do histórico.');
            $this->redirect('/animais/' . $f['animal_id']);
        } else {
            $this->redirect('/animais');
        }
    }

    /**
     * Helper privado para buscar animal garantindo existência
     */
    private function buscarAnimal(int $id): array {
        $stmt = $this->db->prepare("SELECT * FROM animais WHERE id = ?");
        $stmt->execute([$id]);
        $animal = $stmt->fetch();
        if (!$animal) {
            flash('error', 'Animal não encontrado.');
            $this->redirect('/animais');
        }
        return $animal;
    }
}
