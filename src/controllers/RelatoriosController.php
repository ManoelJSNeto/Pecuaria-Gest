<?php
/**
 * Controlador de Relatórios Gerenciais, Exportações CSV e Auditoria de Sincronizações
 * PecuáriaGest - Arquitetura Limpa MVC
 */

class RelatoriosController extends BaseController {

    /**
     * Central de relatórios gerenciais e download de exportações CSV
     */
    public function index(): void {
        $this->requireLogin();

        $export = $_GET['export'] ?? '';

        if ($export === 'compras') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="compras_' . date('Ymd') . '.csv"');
            echo "\xEF\xBB\xBF";
            $rows = $this->db->query("SELECT c.numero_gta, c.chave_nfe, c.fornecedor_origem, c.data_compra, c.quantidade_cabecas, c.peso_total_kg, c.valor_total, p.nome as pasto_destino, c.descricao FROM compras c LEFT JOIN pastagens p ON c.pasto_destino_id = p.id ORDER BY c.data_compra DESC")->fetchAll();
            echo "GTA,Chave NFe,Fornecedor,Data Compra,Cabecas,Peso Total (kg),Valor Total (R$),Pasto Destino,Descricao\n";
            foreach ($rows as $r) echo implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', $r)) . "\n";
            exit;
        }

        if ($export === 'vendas') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="vendas_' . date('Ymd') . '.csv"');
            echo "\xEF\xBB\xBF";
            $rows = $this->db->query("SELECT v.numero_gta, v.chave_nfe, v.comprador_destino, v.data_venda, v.quantidade_cabecas, v.peso_total_kg, v.valor_total, v.tipo_precificacao, v.preco_unitario, v.descricao FROM vendas v ORDER BY v.data_venda DESC")->fetchAll();
            echo "GTA,Chave NFe,Comprador,Data Venda,Cabecas,Peso Total (kg),Valor Total (R$),Tipo Precificacao,Preco Unitario,Descricao\n";
            foreach ($rows as $r) echo implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', $r)) . "\n";
            exit;
        }

        if ($export === 'animais') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="animais_' . date('Ymd') . '.csv"');
            echo "\xEF\xBB\xBF";
            $rows = $this->db->query("SELECT a.brinco,a.nome,a.sexo,a.raca,a.data_nascimento,a.status,a.peso_inicial,p.nome as pasto FROM animais a LEFT JOIN pastagens p ON a.pasto_id=p.id ORDER BY a.brinco")->fetchAll();
            echo "Brinco,Nome,Sexo,Raça,Nascimento,Status,Peso Inicial,Pastagem\n";
            foreach ($rows as $r) echo implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', $r)) . "\n";
            exit;
        }

        if ($export === 'pesagens') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="pesagens_' . date('Ymd') . '.csv"');
            echo "\xEF\xBB\xBF";
            $rows = $this->db->query("SELECT a.brinco,a.nome,pe.peso,pe.data,pe.observacao,pe.origem FROM pesagens pe JOIN animais a ON pe.animal_id=a.id ORDER BY pe.data DESC")->fetchAll();
            echo "Brinco,Nome,Peso(kg),Data,Observação,Origem\n";
            foreach ($rows as $r) echo implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', $r)) . "\n";
            exit;
        }

        if ($export === 'saude') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="saude_' . date('Ymd') . '.csv"');
            echo "\xEF\xBB\xBF";
            $rows = $this->db->query("SELECT a.brinco,s.tipo,s.descricao,s.data,s.medicamento,s.dose,s.veterinario,s.custo FROM saude s JOIN animais a ON s.animal_id=a.id ORDER BY s.data DESC")->fetchAll();
            echo "Brinco,Tipo,Descrição,Data,Medicamento,Dose,Veterinário,Custo(R$)\n";
            foreach ($rows as $r) echo implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', $r)) . "\n";
            exit;
        }

        if ($export === 'reproducao') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="reproducao_' . date('Ymd') . '.csv"');
            echo "\xEF\xBB\xBF";
            $rows = $this->db->query("SELECT a.brinco, a.nome, r.tipo, r.data, r.touro_brinco, r.resultado, r.observacao FROM reproducao r JOIN animais a ON r.animal_id=a.id ORDER BY r.data DESC")->fetchAll();
            echo "Brinco,Nome,Tipo,Data,Touro/Pai,Resultado,Observação\n";
            foreach ($rows as $r) echo implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', $r)) . "\n";
            exit;
        }

        if ($export === 'pastagens') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="pastagens_' . date('Ymd') . '.csv"');
            echo "\xEF\xBB\xBF";
            $rows = $this->db->query("SELECT p.nome, p.area_ha, p.capacidade, p.status, COUNT(a.id) as total_animais, p.observacao FROM pastagens p LEFT JOIN animais a ON a.pasto_id=p.id AND a.status NOT IN ('vendido','morto') GROUP BY p.id ORDER BY p.nome")->fetchAll();
            echo "Nome,Área(ha),Capacidade,Status,Total Animais,Observação\n";
            foreach ($rows as $r) echo implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', $r)) . "\n";
            exit;
        }

        $pastos = $this->db->query("SELECT id, nome FROM pastagens ORDER BY nome ASC")->fetchAll();
        $racas = $this->db->query("SELECT DISTINCT raca FROM animais WHERE raca IS NOT NULL AND raca != '' ORDER BY raca ASC")->fetchAll(PDO::FETCH_COLUMN);
        $tiposSaude = $this->db->query("SELECT DISTINCT tipo FROM saude WHERE tipo IS NOT NULL AND tipo != '' ORDER BY tipo ASC")->fetchAll(PDO::FETCH_COLUMN);
        $todosAnimais = $this->db->query("
            SELECT a.id, a.brinco, a.nome, a.raca, a.sexo, p.nome as pasto_nome
            FROM animais a
            LEFT JOIN pastagens p ON a.pasto_id = p.id
            WHERE a.status = 'ativo'
            ORDER BY a.brinco ASC
            LIMIT 500
        ")->fetchAll();

        $this->render('relatorios/index', 'Relatórios Gerenciais', 'relatorios', [
            'pastos' => $pastos,
            'racas' => $racas,
            'tiposSaude' => $tiposSaude,
            'todosAnimais' => $todosAnimais,
        ]);
    }

    /**
     * Histórico e auditoria de sincronizações mobile
     */
    public function sincronizacoes(): void {
        $this->requireLogin();
        $this->render('sincronizacoes/index', 'Sincronização Mobile', 'sincronizacoes');
    }

    /**
     * Emite Relatórios Oficiais Consolidados em A4/PDF (GET /relatorios/pdf?tipo=rebanho|saude)
     * Suporta filtragem granular por pasto, sexo, raça, categoria, status e período.
     */
    public function pdf(): void {
        $this->requireLogin();
        $tipo = $_GET['tipo'] ?? 'rebanho';

        if ($tipo === 'saude') {
            $where = [];
            $params = [];
            $filtrosTxt = [];

            // Tipo de manejo
            $tipoManejo = trim($_GET['tipo_manejo'] ?? '') ?: null;
            if ($tipoManejo) {
                $where[] = "s.tipo = ?";
                $params[] = $tipoManejo;
                $filtrosTxt[] = "Tipo: " . $tipoManejo;
            }

            // Período
            $dataInicio = trim($_GET['data_inicio'] ?? '') ?: null;
            if ($dataInicio) {
                $where[] = "s.data >= ?";
                $params[] = $dataInicio;
                $filtrosTxt[] = "De: " . formatDate($dataInicio);
            }

            $dataFim = trim($_GET['data_fim'] ?? '') ?: null;
            if ($dataFim) {
                $where[] = "s.data <= ?";
                $params[] = $dataFim;
                $filtrosTxt[] = "Até: " . formatDate($dataFim);
            }

            // Veterinário
            $vet = trim($_GET['veterinario'] ?? '') ?: null;
            if ($vet) {
                $where[] = "LOWER(s.veterinario) LIKE LOWER(?)";
                $params[] = "%$vet%";
                $filtrosTxt[] = "Veterinário: " . $vet;
            }

            // Animal / Brinco / Prefixo
            $brinco = trim($_GET['brinco'] ?? $_GET['prefixo'] ?? '') ?: null;
            if ($brinco) {
                $where[] = "(UPPER(a.brinco) LIKE ? OR UPPER(a.nome) LIKE ?)";
                $params[] = '%' . strtoupper($brinco) . '%';
                $params[] = '%' . strtoupper($brinco) . '%';
                $filtrosTxt[] = "Brinco/Prefixo: " . $brinco;
            }

            $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

            // Lista completa de ocorrências filtradas
            $eventosStmt = $this->db->prepare("
                SELECT s.*, a.brinco, a.nome as animal_nome, a.raca
                FROM saude s
                JOIN animais a ON s.animal_id = a.id
                $whereSql
                ORDER BY s.data DESC, s.id DESC
            ");
            $eventosStmt->execute($params);
            $eventos = $eventosStmt->fetchAll();

            // Totais e KPIs calculados a partir dos dados filtrados
            $custoTotal = 0.0;
            $animaisIds = [];
            $contagemPorTipo = [];
            foreach ($eventos as $ev) {
                $custoTotal += (float)($ev['custo'] ?? 0);
                $animaisIds[$ev['animal_id']] = true;
                $tNome = $ev['tipo'] ?: 'Outros';
                if (!isset($contagemPorTipo[$tNome])) {
                    $contagemPorTipo[$tNome] = ['tipo' => $tNome, 'qtd' => 0, 'total_custo' => 0.0];
                }
                $contagemPorTipo[$tNome]['qtd']++;
                $contagemPorTipo[$tNome]['total_custo'] += (float)($ev['custo'] ?? 0);
            }
            usort($contagemPorTipo, fn($a, $b) => $b['qtd'] <=> $a['qtd']);

            $totais = [
                'total_eventos' => count($eventos),
                'custo_total' => $custoTotal,
                'animais_atendidos' => count($animaisIds),
            ];

            $this->renderPrint('relatorios/pdf_saude', 'Laudo Sanitário & Manejo Clínico', [
                'totais' => $totais,
                'porTipo' => $contagemPorTipo,
                'eventos' => $eventos,
                'filtrosAplicados' => $filtrosTxt,
            ]);
            return;
        }

        // ── Tipo Padrão: Rebanho (Inventário Geral & Lotação) ──
        $where = [];
        $params = [];
        $filtrosTxt = [];

        // Status
        $statusF = trim($_GET['status'] ?? 'ativo');
        if ($statusF !== 'todos') {
            $where[] = "a.status = ?";
            $params[] = $statusF;
            if ($statusF !== 'ativo') {
                $filtrosTxt[] = "Status: " . ucfirst($statusF);
            }
        } else {
            $filtrosTxt[] = "Status: Todos (Ativos, Vendidos e Baixados)";
        }

        // Pasto
        $pastoId = !empty($_GET['pasto_id']) ? (int)$_GET['pasto_id'] : null;
        if ($pastoId) {
            $where[] = "a.pasto_id = ?";
            $params[] = $pastoId;
            $pstQuery = $this->db->prepare("SELECT nome FROM pastagens WHERE id = ?");
            $pstQuery->execute([$pastoId]);
            $pNome = $pstQuery->fetchColumn();
            $filtrosTxt[] = "Pasto: " . ($pNome ?: "#$pastoId");
        }

        // Sexo
        $sexo = in_array($_GET['sexo'] ?? '', ['M', 'F']) ? $_GET['sexo'] : null;
        if ($sexo) {
            $where[] = "a.sexo = ?";
            $params[] = $sexo;
            $filtrosTxt[] = "Sexo: " . ($sexo === 'F' ? 'Fêmeas ♀' : 'Machos ♂');
        }

        // Raça
        $raca = trim($_GET['raca'] ?? '') ?: null;
        if ($raca) {
            $where[] = "a.raca = ?";
            $params[] = $raca;
            $filtrosTxt[] = "Raça: " . $raca;
        }

        // Categoria (Bezerro <= 12 meses vs Adulto)
        $categoria = trim($_GET['categoria'] ?? '') ?: null;
        $dataLimite12m = date('Y-m-d', strtotime('-12 months'));
        if ($categoria === 'bezerro') {
            $where[] = "a.data_nascimento IS NOT NULL AND a.data_nascimento >= ?";
            $params[] = $dataLimite12m;
            $filtrosTxt[] = "Categoria: Bezerros / Filhotes (≤ 12 meses)";
        } elseif ($categoria === 'adulto') {
            $where[] = "(a.data_nascimento IS NULL OR a.data_nascimento < ?)";
            $params[] = $dataLimite12m;
            $filtrosTxt[] = "Categoria: Adultos (> 12 meses)";
        }

        // Prefixo / Início do Brinco ou Nome (ex: T001, T002, PG)
        $prefixoInput = trim($_GET['prefixo'] ?? $_GET['busca'] ?? '');
        if ($prefixoInput !== '') {
            $tokens = array_filter(array_map('trim', explode(',', $prefixoInput)));
            if (!empty($tokens)) {
                $orClauses = [];
                foreach ($tokens as $tk) {
                    $orClauses[] = "(UPPER(a.brinco) LIKE ? OR UPPER(a.nome) LIKE ?)";
                    $params[] = strtoupper($tk) . '%';
                    $params[] = strtoupper($tk) . '%';
                }
                $where[] = '(' . implode(' OR ', $orClauses) . ')';
                $filtrosTxt[] = "Prefixo/Início: " . implode(', ', $tokens);
            }
        }

        // Seleção Manual Vaca por Vaca (animais_ids)
        $animaisIds = $_GET['animais_ids'] ?? [];
        if (is_string($animaisIds)) {
            $animaisIds = explode(',', $animaisIds);
        }
        $animaisIds = array_filter(array_map('intval', (array)$animaisIds));
        if (!empty($animaisIds)) {
            $placeholders = implode(',', array_fill(0, count($animaisIds), '?'));
            $where[] = "a.id IN ($placeholders)";
            foreach ($animaisIds as $aid) {
                $params[] = $aid;
            }
            $filtrosTxt[] = count($animaisIds) . " animal(is) selecionado(s) manualmente";
        }

        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Pastagens: se filtrou por pasto, lista apenas aquele; senão lista todas
        if ($pastoId) {
            $pastagensStmt = $this->db->prepare("
                SELECT p.id, p.nome, p.area_ha, p.capacidade, p.status,
                       COUNT(a.id) as total_alocados
                FROM pastagens p
                LEFT JOIN animais a ON a.pasto_id = p.id AND a.status = 'ativo'
                WHERE p.id = ?
                GROUP BY p.id, p.nome, p.area_ha, p.capacidade, p.status
            ");
            $pastagensStmt->execute([$pastoId]);
        } else {
            $pastagensStmt = $this->db->query("
                SELECT p.id, p.nome, p.area_ha, p.capacidade, p.status,
                       COUNT(a.id) as total_alocados
                FROM pastagens p
                LEFT JOIN animais a ON a.pasto_id = p.id AND a.status = 'ativo'
                GROUP BY p.id, p.nome, p.area_ha, p.capacidade, p.status
                ORDER BY p.nome ASC
            ");
        }
        $pastagens = $pastagensStmt->fetchAll();

        // Animais filtrados com peso mais recente
        $animaisStmt = $this->db->prepare("
            SELECT a.*, p.nome as pasto_nome,
                   COALESCE(
                       (SELECT peso FROM pesagens WHERE animal_id = a.id ORDER BY data DESC, id DESC LIMIT 1),
                       a.peso_inicial
                   ) as peso_atual
            FROM animais a
            LEFT JOIN pastagens p ON a.pasto_id = p.id
            $whereSql
            ORDER BY p.nome ASC NULLS LAST, a.brinco ASC
        ");
        $animaisStmt->execute($params);
        $animais = $animaisStmt->fetchAll();

        // KPIs consolidados com base nos animais filtrados
        $somaPesos = 0.0;
        $countPesos = 0;
        $machos = 0;
        $femeas = 0;
        $ativos = 0;
        $vendidos = 0;
        $mortos = 0;

        foreach ($animais as $an) {
            if ($an['status'] === 'ativo') $ativos++;
            elseif ($an['status'] === 'vendido') $vendidos++;
            elseif ($an['status'] === 'morto') $mortos++;

            if ($an['sexo'] === 'M') $machos++;
            elseif ($an['sexo'] === 'F') $femeas++;

            if (!empty($an['peso_atual']) && (float)$an['peso_atual'] > 0) {
                $somaPesos += (float)$an['peso_atual'];
                $countPesos++;
            }
        }

        $totais = [
            'total_animais' => count($animais),
            'ativos' => $ativos,
            'vendidos' => $vendidos,
            'mortos' => $mortos,
            'machos' => $machos,
            'femeas' => $femeas,
            'peso_medio' => $countPesos > 0 ? ($somaPesos / $countPesos) : 0,
        ];

        $semAnimais = !empty($_GET['sem_animais']);

        $this->renderPrint('relatorios/pdf_rebanho', 'Inventário Geral do Rebanho & Lotação', [
            'totais' => $totais,
            'pastagens' => $pastagens,
            'animais' => $animais,
            'filtrosAplicados' => $filtrosTxt,
            'semAnimais' => $semAnimais,
        ]);
    }
}
