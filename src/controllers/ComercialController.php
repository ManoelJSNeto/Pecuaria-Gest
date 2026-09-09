<?php
require_once __DIR__ . '/BaseController.php';

/**
 * PecuáriaGest — ComercialController
 *
 * Gerencia as operações comerciais de compra e venda de gado:
 * registro de lotes com chaves mestras (GTA e NF-e de 44 dígitos),
 * importação inteligente de arquivos XML da SEFAZ, precificação por @ ou cabeça,
 * e baixa automática de animais no rebanho com cálculo de faturamento.
 */
class ComercialController extends BaseController {

    /**
     * Listagem do Cockpit de Compras de Gado (GET /compras)
     */
    public function comprasIndex(): void {
        $this->requireLogin();
        $this->render('compras/index', 'Compras de Gado', 'compras');
    }

    /**
     * Formulário de Registro de Compra de Gado com importador XML (GET /compras/novo)
     */
    public function comprasNovo(): void {
        $this->requireLogin();
        $this->render('compras/form', 'Registrar Compra', 'compras');
    }

    /**
     * Salva o lote de compra e cadastra animais automaticamente se solicitado (POST /compras/salvar)
     */
    public function comprasSalvar(): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido. Tente novamente.');
            $this->redirect('/compras/novo');
        }

        $numeroGta = trim($_POST['numero_gta'] ?? '');
        $chaveNfe = preg_replace('/\D/', '', trim($_POST['chave_nfe'] ?? ''));
        $fornecedor = trim($_POST['fornecedor_origem'] ?? '') ?: null;
        $dataCompra = trim($_POST['data_compra'] ?? '') ?: date('Y-m-d');
        $qtdCabecas = max(1, (int)($_POST['quantidade_cabecas'] ?? 1));
        $pesoTotal = !empty($_POST['peso_total_kg']) ? (float)$_POST['peso_total_kg'] : null;
        $valorTotal = (float)($_POST['valor_total'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '') ?: null;
        $pastoDestinoId = !empty($_POST['pasto_destino_id']) ? (int)$_POST['pasto_destino_id'] : null;

        if (empty($numeroGta)) {
            flash('error', 'O número ou série da GTA é obrigatório.');
            $this->redirect('/compras/novo');
        }
        if ($valorTotal <= 0) {
            flash('error', 'O valor total da compra deve ser informado.');
            $this->redirect('/compras/novo');
        }

        $arquivoXml = salvarUploadDocumento($_FILES['arquivo_xml'] ?? null, 'documentos');

        $stmt = $this->db->prepare("
            INSERT INTO compras (numero_gta, chave_nfe, arquivo_xml, fornecedor_origem, data_compra, quantidade_cabecas, peso_total_kg, valor_total, descricao, pasto_destino_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$numeroGta, $chaveNfe ?: null, $arquivoXml, $fornecedor, $dataCompra, $qtdCabecas, $pesoTotal, $valorTotal, $descricao, $pastoDestinoId]);
        $compraId = (int)$this->db->lastInsertId();

        // Cadastro automático de animais do lote se habilitado
        if (!empty($_POST['cadastrar_animais'])) {
            $prefixo = trim($_POST['prefixo_brinco'] ?? 'C-') ?: 'C-';
            $raca = trim($_POST['raca_animais'] ?? 'Nelore') ?: 'Nelore';
            $sexo = in_array($_POST['sexo_animais'] ?? '', ['M', 'F']) ? $_POST['sexo_animais'] : 'M';

            $pesoIndiv = ($pesoTotal && $qtdCabecas > 0) ? round($pesoTotal / $qtdCabecas, 2) : null;
            $valorIndiv = $qtdCabecas > 0 ? round($valorTotal / $qtdCabecas, 2) : null;

            $stmtAnimal = $this->db->prepare("
                INSERT INTO animais (brinco, sexo, raca, status, pasto_id, peso_inicial, data_nascimento, compra_id, valor_compra_individual, observacao)
                VALUES (?, ?, ?, 'ativo', ?, ?, ?, ?, ?, ?)
            ");

            $brincosInseridos = 0;
            $seq = 1;
            while ($brincosInseridos < $qtdCabecas && $seq <= ($qtdCabecas + 5000)) {
                $brincoGerado = $prefixo . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
                $chk = $this->db->prepare("SELECT id FROM animais WHERE brinco = ? LIMIT 1");
                $chk->execute([$brincoGerado]);
                if (!$chk->fetchColumn()) {
                    $obs = "Lote de Compra #$compraId (GTA: $numeroGta)";
                    $stmtAnimal->execute([$brincoGerado, $sexo, $raca, $pastoDestinoId, $pesoIndiv, $dataCompra, $compraId, $valorIndiv, $obs]);
                    $brincosInseridos++;
                }
                $seq++;
            }
        }

        flash('success', "Compra de {$qtdCabecas} cabeças registrada com sucesso!");
        $this->redirect('/compras');
    }

    /**
     * Formulário de Edição de Compra de Gado (GET /compras/{id}/editar)
     */
    public function comprasEditar(int $id): void {
        $this->requireLogin();
        $stmt = $this->db->prepare("SELECT * FROM compras WHERE id = ?");
        $stmt->execute([$id]);
        $compra = $stmt->fetch();
        if (!$compra) {
            flash('error', 'Lote de compra não encontrado.');
            $this->redirect('/compras');
        }

        $pastos = $this->db->query("SELECT id, nome, capacidade FROM pastagens WHERE status='ativa' ORDER BY nome")->fetchAll();

        $animaisStmt = $this->db->prepare("
            SELECT id, brinco, nome, sexo, raca, status, peso_inicial, valor_compra_individual
            FROM animais
            WHERE compra_id = ?
            ORDER BY brinco ASC
        ");
        $animaisStmt->execute([$id]);
        $animaisLote = $animaisStmt->fetchAll();

        $this->render('compras/form', 'Editar Compra #' . $compra['numero_gta'], 'compras', [
            'compra' => $compra,
            'pastos' => $pastos,
            'animaisLote' => $animaisLote
        ]);
    }

    /**
     * Atualiza um lote de compra e ajusta rateio/pastos dos animais (POST /compras/{id}/atualizar)
     */
    public function comprasAtualizar(int $id): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido. Tente novamente.');
            $this->redirect("/compras/{$id}/editar");
        }

        $stmt = $this->db->prepare("SELECT * FROM compras WHERE id = ?");
        $stmt->execute([$id]);
        $compra = $stmt->fetch();
        if (!$compra) {
            flash('error', 'Lote de compra não encontrado.');
            $this->redirect('/compras');
        }

        $numeroGta = trim($_POST['numero_gta'] ?? '');
        $chaveNfe = preg_replace('/\D/', '', trim($_POST['chave_nfe'] ?? ''));
        $fornecedor = trim($_POST['fornecedor_origem'] ?? '') ?: null;
        $dataCompra = trim($_POST['data_compra'] ?? '') ?: date('Y-m-d');
        $qtdCabecas = max(1, (int)($_POST['quantidade_cabecas'] ?? 1));
        $pesoTotal = !empty($_POST['peso_total_kg']) ? (float)$_POST['peso_total_kg'] : null;
        $valorTotal = (float)($_POST['valor_total'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '') ?: null;
        $pastoDestinoId = !empty($_POST['pasto_destino_id']) ? (int)$_POST['pasto_destino_id'] : null;

        if (empty($numeroGta)) {
            flash('error', 'O número ou série da GTA é obrigatório.');
            $this->redirect("/compras/{$id}/editar");
        }
        if ($valorTotal <= 0) {
            flash('error', 'O valor total da compra deve ser informado.');
            $this->redirect("/compras/{$id}/editar");
        }

        $novoXml = salvarUploadDocumento($_FILES['arquivo_xml'] ?? null, 'documentos');
        $arquivoXml = $novoXml ?: ($compra['arquivo_xml'] ?? null);

        $upd = $this->db->prepare("
            UPDATE compras
            SET numero_gta = ?, chave_nfe = ?, arquivo_xml = ?, fornecedor_origem = ?,
                data_compra = ?, quantidade_cabecas = ?, peso_total_kg = ?, valor_total = ?,
                descricao = ?, pasto_destino_id = ?
            WHERE id = ?
        ");
        $upd->execute([$numeroGta, $chaveNfe ?: null, $arquivoXml, $fornecedor, $dataCompra, $qtdCabecas, $pesoTotal, $valorTotal, $descricao, $pastoDestinoId, $id]);

        // Atualiza rateio e pasto dos animais já vinculados ao lote
        $valorIndiv = $qtdCabecas > 0 ? round($valorTotal / $qtdCabecas, 2) : null;

        $updAnimais = $this->db->prepare("
            UPDATE animais
            SET valor_compra_individual = ?,
                pasto_id = COALESCE(?, pasto_id)
            WHERE compra_id = ?
        ");
        $updAnimais->execute([$valorIndiv, $pastoDestinoId, $id]);

        flash('success', 'Lote de compra atualizado com sucesso!');
        $this->redirect('/compras');
    }

    /**
     * Exclui um registro de compra desvinculando os animais associados (POST /compras/{id}/excluir)
     */
    public function comprasExcluir(int $id): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/compras');
        }

        $this->db->prepare("UPDATE animais SET compra_id = NULL, valor_compra_individual = NULL WHERE compra_id = ?")->execute([$id]);
        $this->db->prepare("DELETE FROM compras WHERE id = ?")->execute([$id]);
        flash('success', 'Registro de compra excluído com sucesso.');
        $this->redirect('/compras');
    }

    /**
     * Listagem do Cockpit de Vendas de Gado (GET /vendas)
     */
    public function vendasIndex(): void {
        $this->requireLogin();
        $this->render('vendas/index', 'Vendas de Gado', 'vendas');
    }

    /**
     * Formulário de Registro de Venda com cálculo de arrobas e XML (GET /vendas/novo)
     */
    public function vendasNovo(): void {
        $this->requireLogin();
        $this->render('vendas/form', 'Registrar Venda', 'vendas');
    }

    /**
     * Salva o registro de venda e executa a baixa no rebanho (POST /vendas/salvar)
     */
    public function vendasSalvar(): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido. Tente novamente.');
            $this->redirect('/vendas/novo');
        }

        $numeroGta = trim($_POST['numero_gta'] ?? '');
        $chaveNfe = preg_replace('/\D/', '', trim($_POST['chave_nfe'] ?? ''));
        $comprador = trim($_POST['comprador_destino'] ?? '');
        $dataVenda = trim($_POST['data_venda'] ?? '') ?: date('Y-m-d');
        $tipoPrecificacao = trim($_POST['tipo_precificacao'] ?? 'arroba');
        $precoUnitario = !empty($_POST['preco_unitario']) ? (float)$_POST['preco_unitario'] : null;
        $pesoTotal = !empty($_POST['peso_total_kg']) ? (float)$_POST['peso_total_kg'] : null;
        $valorTotal = (float)($_POST['valor_total'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '') ?: null;
        $animaisIds = $_POST['animais_ids'] ?? [];

        if (empty($numeroGta)) {
            flash('error', 'O número da GTA de saída é obrigatório.');
            $this->redirect('/vendas/novo');
        }
        if (empty($comprador)) {
            flash('error', 'O comprador ou frigorífico de destino é obrigatório.');
            $this->redirect('/vendas/novo');
        }
        if ($valorTotal <= 0) {
            flash('error', 'O valor total da venda deve ser informado.');
            $this->redirect('/vendas/novo');
        }

        $arquivoXml = salvarUploadDocumento($_FILES['arquivo_xml'] ?? null, 'documentos');
        $qtdCabecas = (!empty($animaisIds) && is_array($animaisIds)) ? count($animaisIds) : max(1, (int)($_POST['quantidade_cabecas'] ?? 1));

        $stmt = $this->db->prepare("
            INSERT INTO vendas (numero_gta, chave_nfe, arquivo_xml, comprador_destino, data_venda, quantidade_cabecas, peso_total_kg, valor_total, preco_unitario, tipo_precificacao, descricao)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$numeroGta, $chaveNfe ?: null, $arquivoXml, $comprador, $dataVenda, $qtdCabecas, $pesoTotal, $valorTotal, $precoUnitario, $tipoPrecificacao, $descricao]);
        $vendaId = (int)$this->db->lastInsertId();

        // Baixa comercial dos animais selecionados
        if (!empty($animaisIds) && is_array($animaisIds)) {
            $valorIndiv = $qtdCabecas > 0 ? round($valorTotal / $qtdCabecas, 2) : 0;
            $pesoIndiv = ($pesoTotal && $qtdCabecas > 0) ? round($pesoTotal / $qtdCabecas, 2) : null;

            $updAnimal = $this->db->prepare("
                UPDATE animais 
                SET status = 'vendido',
                    pasto_id = NULL,
                    venda_id = ?,
                    valor_venda_individual = ?,
                    peso_venda = COALESCE(?, peso_venda),
                    data_venda = ?
                WHERE id = ?
            ");

            foreach ($animaisIds as $aid) {
                $aid = (int)$aid;
                if ($aid > 0) {
                    $updAnimal->execute([$vendaId, $valorIndiv, $pesoIndiv, $dataVenda, $aid]);
                }
            }
        }

        $totalBaixados = (!empty($animaisIds) && is_array($animaisIds)) ? count($animaisIds) : 0;
        flash('success', "Venda registrada com sucesso! {$totalBaixados} animal(is) baixado(s) do rebanho.");
        $this->redirect('/vendas');
    }

    /**
     * Formulário de Edição de Venda de Gado (GET /vendas/{id}/editar)
     */
    public function vendasEditar(int $id): void {
        $this->requireLogin();
        $stmt = $this->db->prepare("SELECT * FROM vendas WHERE id = ?");
        $stmt->execute([$id]);
        $venda = $stmt->fetch();
        if (!$venda) {
            flash('error', 'Registro de venda não encontrado.');
            $this->redirect('/vendas');
        }

        // Animais vinculados a esta venda
        $stmtVinc = $this->db->prepare("SELECT id FROM animais WHERE venda_id = ?");
        $stmtVinc->execute([$id]);
        $animaisVendaIds = $stmtVinc->fetchAll(PDO::FETCH_COLUMN);

        // Animais disponíveis para compor a venda (ativos OU os já vinculados a ela)
        $animaisStmt = $this->db->prepare("
            SELECT a.id, a.brinco, a.nome, a.sexo, a.raca, a.pasto_id, a.venda_id, p.nome as pasto_nome,
                   (SELECT peso FROM pesagens WHERE animal_id = a.id ORDER BY data DESC, id DESC LIMIT 1) as ultimo_peso,
                   a.peso_inicial
            FROM animais a
            LEFT JOIN pastagens p ON a.pasto_id = p.id
            WHERE (a.status NOT IN ('vendido', 'morto')) OR (a.venda_id = ?)
            ORDER BY p.nome NULLS LAST, a.brinco ASC
        ");
        $animaisStmt->execute([$id]);
        $animaisDisponiveis = $animaisStmt->fetchAll();

        $pastos = $this->db->query("SELECT id, nome FROM pastagens WHERE status='ativa' ORDER BY nome")->fetchAll();

        $this->render('vendas/form', 'Editar Venda #' . $venda['numero_gta'], 'vendas', [
            'venda' => $venda,
            'animaisVendaIds' => $animaisVendaIds,
            'animaisDisponiveis' => $animaisDisponiveis,
            'pastos' => $pastos
        ]);
    }

    /**
     * Atualiza um registro de venda e sincroniza os animais baixados (POST /vendas/{id}/atualizar)
     */
    public function vendasAtualizar(int $id): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido. Tente novamente.');
            $this->redirect("/vendas/{$id}/editar");
        }

        $stmt = $this->db->prepare("SELECT * FROM vendas WHERE id = ?");
        $stmt->execute([$id]);
        $venda = $stmt->fetch();
        if (!$venda) {
            flash('error', 'Registro de venda não encontrado.');
            $this->redirect('/vendas');
        }

        $numeroGta = trim($_POST['numero_gta'] ?? '');
        $chaveNfe = preg_replace('/\D/', '', trim($_POST['chave_nfe'] ?? ''));
        $comprador = trim($_POST['comprador_destino'] ?? '');
        $dataVenda = trim($_POST['data_venda'] ?? '') ?: date('Y-m-d');
        $tipoPrecificacao = trim($_POST['tipo_precificacao'] ?? 'arroba');
        $precoUnitario = !empty($_POST['preco_unitario']) ? (float)$_POST['preco_unitario'] : null;
        $pesoTotal = !empty($_POST['peso_total_kg']) ? (float)$_POST['peso_total_kg'] : null;
        $valorTotal = (float)($_POST['valor_total'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '') ?: null;
        $animaisIds = array_map('intval', $_POST['animais_ids'] ?? []);

        if (empty($numeroGta)) {
            flash('error', 'O número da GTA de saída é obrigatório.');
            $this->redirect("/vendas/{$id}/editar");
        }
        if (empty($comprador)) {
            flash('error', 'O comprador ou frigorífico de destino é obrigatório.');
            $this->redirect("/vendas/{$id}/editar");
        }
        if ($valorTotal <= 0) {
            flash('error', 'O valor total da venda deve ser informado.');
            $this->redirect("/vendas/{$id}/editar");
        }

        $novoXml = salvarUploadDocumento($_FILES['arquivo_xml'] ?? null, 'documentos');
        $arquivoXml = $novoXml ?: ($venda['arquivo_xml'] ?? null);
        $qtdCabecas = !empty($animaisIds) ? count($animaisIds) : max(1, (int)($_POST['quantidade_cabecas'] ?? $venda['quantidade_cabecas']));

        $updVenda = $this->db->prepare("
            UPDATE vendas
            SET numero_gta = ?, chave_nfe = ?, arquivo_xml = ?, comprador_destino = ?,
                data_venda = ?, quantidade_cabecas = ?, peso_total_kg = ?, valor_total = ?,
                preco_unitario = ?, tipo_precificacao = ?, descricao = ?
            WHERE id = ?
        ");
        $updVenda->execute([$numeroGta, $chaveNfe ?: null, $arquivoXml, $comprador, $dataVenda, $qtdCabecas, $pesoTotal, $valorTotal, $precoUnitario, $tipoPrecificacao, $descricao, $id]);

        // Sincronização dos animais:
        // 1. Desvincula animais que foram desmarcados
        if (!empty($animaisIds)) {
            $inClause = implode(',', array_fill(0, count($animaisIds), '?'));
            $params = array_merge([$id], $animaisIds);
            $stmtRelease = $this->db->prepare("
                UPDATE animais 
                SET status = 'ativo', venda_id = NULL, valor_venda_individual = NULL, peso_venda = NULL, data_venda = NULL
                WHERE venda_id = ? AND id NOT IN ($inClause)
            ");
            $stmtRelease->execute($params);
        } else {
            $this->db->prepare("
                UPDATE animais 
                SET status = 'ativo', venda_id = NULL, valor_venda_individual = NULL, peso_venda = NULL, data_venda = NULL
                WHERE venda_id = ?
            ")->execute([$id]);
        }

        // 2. Vincula/atualiza os animais selecionados
        if (!empty($animaisIds)) {
            $valorIndiv = $qtdCabecas > 0 ? round($valorTotal / $qtdCabecas, 2) : 0;
            $pesoIndiv = ($pesoTotal && $qtdCabecas > 0) ? round($pesoTotal / $qtdCabecas, 2) : null;

            $updAnimal = $this->db->prepare("
                UPDATE animais 
                SET status = 'vendido',
                    pasto_id = NULL,
                    venda_id = ?,
                    valor_venda_individual = ?,
                    peso_venda = COALESCE(?, peso_venda),
                    data_venda = ?
                WHERE id = ?
            ");

            foreach ($animaisIds as $aid) {
                if ($aid > 0) {
                    $updAnimal->execute([$id, $valorIndiv, $pesoIndiv, $dataVenda, $aid]);
                }
            }
        }

        flash('success', 'Registro de venda e animais baixados atualizados com sucesso!');
        $this->redirect('/vendas');
    }

    /**
     * Estorna o registro de venda e retorna os animais para o status ativo (POST /vendas/{id}/excluir)
     */
    public function vendasExcluir(int $id): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token inválido.');
            $this->redirect('/vendas');
        }

        // Restaura animais vinculados para ativo
        $this->db->prepare("
            UPDATE animais 
            SET status = 'ativo',
                venda_id = NULL,
                valor_venda_individual = NULL,
                peso_venda = NULL,
                data_venda = NULL
            WHERE venda_id = ?
        ")->execute([$id]);

        $this->db->prepare("DELETE FROM vendas WHERE id = ?")->execute([$id]);
        flash('success', 'Venda estornada com sucesso! Os animais retornaram ao rebanho ativo.');
        $this->redirect('/vendas');
    }

    /**
     * Emite o Espelho Oficial de Compra de Gado em A4/PDF (GET /compras/{id}/pdf)
     */
    public function comprasPdf(int $id): void {
        $this->requireLogin();
        $stmt = $this->db->prepare("SELECT * FROM compras WHERE id = ?");
        $stmt->execute([$id]);
        $compra = $stmt->fetch();
        if (!$compra) {
            flash('error', 'Lote de compra não encontrado.');
            $this->redirect('/compras');
        }

        $pasto = null;
        if (!empty($compra['pasto_destino_id'])) {
            $pst = $this->db->prepare("SELECT nome FROM pastagens WHERE id = ?");
            $pst->execute([$compra['pasto_destino_id']]);
            $pasto = $pst->fetch() ?: null;
        }

        $animaisStmt = $this->db->prepare("
            SELECT id, brinco, nome, sexo, raca, status, peso_inicial, valor_compra_individual
            FROM animais
            WHERE compra_id = ?
            ORDER BY brinco ASC
        ");
        $animaisStmt->execute([$id]);
        $animais = $animaisStmt->fetchAll();

        $this->renderPrint('compras/pdf', 'Espelho de Compra #' . $compra['numero_gta'], [
            'c' => $compra,
            'pasto' => $pasto,
            'animais' => $animais,
        ]);
    }

    /**
     * Emite o Comprovante Oficial de Venda de Gado em A4/PDF (GET /vendas/{id}/pdf)
     */
    public function vendasPdf(int $id): void {
        $this->requireLogin();
        $stmt = $this->db->prepare("SELECT * FROM vendas WHERE id = ?");
        $stmt->execute([$id]);
        $venda = $stmt->fetch();
        if (!$venda) {
            flash('error', 'Registro de venda não encontrado.');
            $this->redirect('/vendas');
        }

        $animaisStmt = $this->db->prepare("
            SELECT id, brinco, nome, sexo, raca, peso_inicial, peso_venda, valor_compra_individual, valor_venda_individual
            FROM animais
            WHERE venda_id = ?
            ORDER BY brinco ASC
        ");
        $animaisStmt->execute([$id]);
        $animais = $animaisStmt->fetchAll();

        $this->renderPrint('vendas/pdf', 'Comprovante de Venda #' . $venda['numero_gta'], [
            'v' => $venda,
            'animais' => $animais,
        ]);
    }
}
