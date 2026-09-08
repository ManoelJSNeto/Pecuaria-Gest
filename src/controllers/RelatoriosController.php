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

        $this->render('relatorios/index', 'Relatórios Gerenciais', 'relatorios');
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
     */
    public function pdf(): void {
        $this->requireLogin();
        $tipo = $_GET['tipo'] ?? 'rebanho';

        if ($tipo === 'saude') {
            // Totais e KPIs Sanitários
            $totaisStmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_eventos,
                    COALESCE(SUM(custo), 0) as custo_total,
                    COUNT(DISTINCT animal_id) as animais_atendidos
                FROM saude
            ");
            $totais = $totaisStmt->fetch();

            // Por tipo de manejo
            $porTipoStmt = $this->db->query("
                SELECT tipo, COUNT(*) as qtd, COALESCE(SUM(custo), 0) as total_custo
                FROM saude
                GROUP BY tipo
                ORDER BY qtd DESC
            ");
            $porTipo = $porTipoStmt->fetchAll();

            // Lista completa de ocorrências com brinco e nome do animal
            $eventosStmt = $this->db->query("
                SELECT s.*, a.brinco, a.nome as animal_nome, a.raca
                FROM saude s
                JOIN animais a ON s.animal_id = a.id
                ORDER BY s.data DESC, s.id DESC
            ");
            $eventos = $eventosStmt->fetchAll();

            $this->renderPrint('relatorios/pdf_saude', 'Laudo Sanitário & Manejo Clínico', [
                'totais' => $totais,
                'porTipo' => $porTipo,
                'eventos' => $eventos,
            ]);
            return;
        }

        // Tipo Padrão: Rebanho (Inventário Geral & Lotação)
        $totaisStmt = $this->db->query("
            SELECT 
                COUNT(*) as total_animais,
                COUNT(CASE WHEN status = 'ativo' THEN 1 END) as ativos,
                COUNT(CASE WHEN status = 'vendido' THEN 1 END) as vendidos,
                COUNT(CASE WHEN status = 'morto' THEN 1 END) as mortos,
                COUNT(CASE WHEN sexo = 'M' AND status = 'ativo' THEN 1 END) as machos,
                COUNT(CASE WHEN sexo = 'F' AND status = 'ativo' THEN 1 END) as femeas,
                COALESCE(AVG(CASE WHEN status = 'ativo' THEN peso_inicial END), 0) as peso_medio
            FROM animais
        ");
        $totais = $totaisStmt->fetch();

        // Ocupação por Pastagem
        $pastagensStmt = $this->db->query("
            SELECT 
                p.id, p.nome, p.area_ha, p.capacidade, p.status,
                COUNT(a.id) as total_alocados
            FROM pastagens p
            LEFT JOIN animais a ON a.pasto_id = p.id AND a.status = 'ativo'
            GROUP BY p.id, p.nome, p.area_ha, p.capacidade, p.status
            ORDER BY p.nome ASC
        ");
        $pastagens = $pastagensStmt->fetchAll();

        // Lista de animais ativos com último peso
        $animaisStmt = $this->db->query("
            SELECT a.*, p.nome as pasto_nome,
                   COALESCE(
                       (SELECT peso FROM pesagens WHERE animal_id = a.id ORDER BY data DESC, id DESC LIMIT 1),
                       a.peso_inicial
                   ) as peso_atual
            FROM animais a
            LEFT JOIN pastagens p ON a.pasto_id = p.id
            WHERE a.status = 'ativo'
            ORDER BY p.nome ASC NULLS LAST, a.brinco ASC
        ");
        $animais = $animaisStmt->fetchAll();

        // Recalcular peso médio com o peso_atual real se houver animais
        if (!empty($animais)) {
            $somaPesos = 0.0;
            $countPesos = 0;
            foreach ($animais as $an) {
                if (!empty($an['peso_atual'])) {
                    $somaPesos += (float)$an['peso_atual'];
                    $countPesos++;
                }
            }
            if ($countPesos > 0) {
                $totais['peso_medio'] = $somaPesos / $countPesos;
            }
        }

        $this->renderPrint('relatorios/pdf_rebanho', 'Inventário Geral do Rebanho & Lotação', [
            'totais' => $totais,
            'pastagens' => $pastagens,
            'animais' => $animais,
        ]);
    }
}
