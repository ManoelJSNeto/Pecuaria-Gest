<?php
// Handle static files when using PHP built-in server
if (php_sapi_name() === 'cli-server') {
    $file = __DIR__ . $_SERVER['REQUEST_URI'];
    $file = strtok($file, '?');
    if ($file !== __FILE__ && is_file($file)) {
        return false;
    }
}

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';

// Autoloader para controladores em src/controllers/
spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/controllers/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

session_name('pecuaria_session');
session_start();

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// ── Servidor de arquivos estáticos de upload ──
if (str_starts_with($uri, '/uploads/')) {
    $subPath = substr($uri, strlen('/uploads/'));
    $fullPath = UPLOADS_PATH . '/' . $subPath;
    if (is_file($fullPath)) {
        $mime = mime_content_type($fullPath) ?: 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        readfile($fullPath);
        exit;
    }
    http_response_code(404);
    echo 'Arquivo não encontrado';
    exit;
}

// ── Rotas Públicas & Autenticação (AuthController) ──
if ($uri === '/' || $uri === '/login') {
    $auth = new AuthController();
    if ($method === 'POST') {
        $auth->login();
    } else {
        $auth->showLogin();
    }
    exit;
}

if ($uri === '/logout') {
    (new AuthController())->logout();
    exit;
}

// ── API Endpoint (for mobile app & PWA) ──────────────
if (str_starts_with($uri, '/api/')) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!empty($origin)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
    } else {
        header('Access-Control-Allow-Origin: *');
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS, HEAD');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-KEY, Cache-Control');
    header('Content-Type: application/json; charset=utf-8');

    if ($method === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    $db = getDb();

    // Sincronização de dados de campo (Exige autenticação)
    if ($uri === '/api/sync' && $method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        
        // Validação de segurança: Exige sessão ativa OU credenciais válidas OU API Key
        $authOk = isLoggedIn();
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($body['api_key'] ?? '');
        if (!$authOk && !empty($apiKey) && hash_equals(API_KEY, (string)$apiKey)) {
            $authOk = true;
        }

        if (!$authOk && !empty($body['auth_email']) && !empty($body['auth_senha'])) {
            $email = trim($body['auth_email']);
            $senha = (string)$body['auth_senha'];

            // 1. Verifica banco de dados
            $uStmt = $db->prepare("SELECT id, senha FROM usuarios WHERE email=? AND ativo=1 LIMIT 1");
            $uStmt->execute([$email]);
            $userObj = $uStmt->fetch();
            if ($userObj && password_verify($senha, $userObj['senha'])) {
                $authOk = true;
            } elseif (defined('APP_ENV') && APP_ENV === 'development' && $email === DEFAULT_ADMIN_EMAIL && $senha === DEFAULT_ADMIN_PASS) {
                $authOk = true;
            }
        }

        if (!$authOk) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autorizado. Informe uma API Key válida ou credenciais de um usuário ativo.']);
            exit;
        }
        $processados = ['pesagens' => 0, 'saude' => 0, 'animais_novos' => 0, 'fotos' => 0];
        $erros = [];

        // Process new animals
        foreach ($body['animais_novos'] ?? [] as $an) {
            try {
                $stmt = $db->prepare("INSERT INTO animais (brinco,sexo,raca,data_nascimento,nome,origem,status) VALUES (?,?,?,?,?,?,'ativo')");
                $stmt->execute([$an['brinco']??null,$an['sexo']??'M',$an['raca']??null,$an['data_nascimento']??null,$an['nome']??null,'mobile']);
                $aid = null;
                try {
                    $aid = $db->lastInsertId();
                } catch (Exception $ex) {}
                if (!$aid && !empty($an['brinco'])) {
                    $aidStmt = $db->prepare("SELECT id FROM animais WHERE brinco = ? LIMIT 1");
                    $aidStmt->execute([$an['brinco']]);
                    $aid = $aidStmt->fetchColumn() ?: null;
                }
                if ($aid) {
                    $processados['animais_novos']++;
                    if (!empty($an['foto_base64'])) {
                        $fUrl = salvarBase64Foto($an['foto_base64']);
                        if ($fUrl) {
                            $db->prepare("UPDATE animais SET foto_url=? WHERE id=?")->execute([$fUrl, $aid]);
                            $db->prepare("INSERT INTO fotos_animais (animal_id,foto_url,tipo_evento,fase,data,observacao) VALUES (?,?,'nascimento','filhote',?,?)")
                               ->execute([$aid, $fUrl, $an['data_nascimento'] ?? date('Y-m-d'), 'Foto de nascimento (PecuGest-Campo)']);
                            $processados['fotos']++;
                        }
                    }
                }
            } catch (Exception $e) { $erros[] = 'animal:'.$e->getMessage(); }
        }

        // Process weight records
        foreach ($body['pesagens'] ?? [] as $p) {
            try {
                $aidStmt = $db->prepare("SELECT id FROM animais WHERE brinco=?");
                $aidStmt->execute([$p['brinco'] ?? '']);
                $aid = $aidStmt->fetchColumn() ?: null;
                if ($aid) {
                    $stmt = $db->prepare("INSERT INTO pesagens (animal_id,peso,data,observacao,origem) VALUES (?,?,?,?,'mobile')");
                    $stmt->execute([$aid,$p['peso']??0,$p['data']??date('Y-m-d'),$p['observacao']??null]);
                    $processados['pesagens']++;

                    if (!empty($p['foto_base64'])) {
                        $fUrl = salvarBase64Foto($p['foto_base64']);
                        if ($fUrl) {
                            $db->prepare("INSERT INTO fotos_animais (animal_id,foto_url,tipo_evento,fase,data,observacao) VALUES (?,?,'pesagem','adulto',?,?)")
                               ->execute([$aid, $fUrl, $p['data'] ?? date('Y-m-d'), 'Pesagem ' . ($p['peso']??'') . 'kg (PecuGest-Campo)']);
                            $db->prepare("UPDATE animais SET foto_url = COALESCE(foto_url, ?) WHERE id = ?")->execute([$fUrl, $aid]);
                            $processados['fotos']++;
                        }
                    }
                }
            } catch (Exception $e) { $erros[] = 'pesagem:'.$e->getMessage(); }
        }

        // Process health events
        foreach ($body['saude'] ?? [] as $s) {
            try {
                $aidStmt = $db->prepare("SELECT id FROM animais WHERE brinco=?");
                $aidStmt->execute([$s['brinco'] ?? '']);
                $aid = $aidStmt->fetchColumn() ?: null;
                if ($aid) {
                    $stmt = $db->prepare("INSERT INTO saude (animal_id,tipo,descricao,data,medicamento,dose,observacao,origem) VALUES (?,?,?,?,?,?,?,'mobile')");
                    $stmt->execute([$aid,$s['tipo']??'Outro',$s['descricao']??'',$s['data']??date('Y-m-d'),$s['medicamento']??null,$s['dose']??null,$s['observacao']??null]);
                    $processados['saude']++;

                    // Atualização automática de status do animal (óbito -> morto, tratamento -> doente, alta -> ativo, parto -> ativo)
                    atualizarStatusAnimalPorSaude($db, (int)$aid, $s['tipo'] ?? '');

                    $tipoNorm = normalizarTexto($s['tipo'] ?? '');
                    $isObito = in_array($tipoNorm, ['obito', 'morte', 'morreu']);

                    if (!empty($s['foto_base64'])) {
                        $fUrl = salvarBase64Foto($s['foto_base64']);
                        if ($fUrl) {
                            $isSensivel = ($isObito || in_array($tipoNorm, ['curativo', 'ferimento', 'cirurgia', 'obito', 'tratamento']) || !empty($s['is_sensivel'])) ? 1 : 0;
                            $fase = $isObito ? 'obito' : 'adulto';
                            $db->prepare("INSERT INTO fotos_animais (animal_id,foto_url,tipo_evento,fase,is_sensivel,data,observacao) VALUES (?,?,?,?,?,?,?)")
                               ->execute([$aid, $fUrl, $isObito ? 'obito' : 'saude', $fase, $isSensivel, $s['data'] ?? date('Y-m-d'), $s['descricao'] ?? 'Registro de saúde']);
                            $processados['fotos']++;
                        }
                    }
                }
            } catch (Exception $e) { $erros[] = 'saude:'.$e->getMessage(); }
        }

        // Log sync
        $total = array_sum($processados);
        $status = empty($erros) ? 'ok' : ($total > 0 ? 'parcial' : 'erro');
        $db->prepare("INSERT INTO sincronizacoes (dispositivo,ip,dados_recebidos,status,detalhes) VALUES (?,?,?,?,?)")
           ->execute([$body['dispositivo']??'desconhecido',$_SERVER['REMOTE_ADDR']??'',$total,$status,json_encode(['processados'=>$processados,'erros'=>$erros])]);

        // Cria alerta/notificação no Painel Web para o Proprietário
        if ($total > 0) {
            $msgPartes = [];
            if ($processados['pesagens'] > 0)     $msgPartes[] = "{$processados['pesagens']} pesagens";
            if ($processados['animais_novos'] > 0) $msgPartes[] = "{$processados['animais_novos']} novos bezerros";
            if ($processados['saude'] > 0)         $msgPartes[] = "{$processados['saude']} manejos sanitários";
            if ($processados['fotos'] > 0)         $msgPartes[] = "{$processados['fotos']} fotos";

            $resumoMsg = implode(', ', $msgPartes);
            $dispositivoNome = htmlspecialchars($body['dispositivo'] ?? 'App Campo');
            
            try {
                $db->prepare("INSERT INTO alertas (animal_id, tipo, mensagem, lido) VALUES (NULL, 'sincronizacao', ?, 0)")
                   ->execute(["Coleta sincronizada via {$dispositivoNome}: {$resumoMsg}."]);
            } catch (Exception $e) {}

            // Dispara e-mail de notificação formatado para o Proprietário/Gerente
            try {
                notifyOwnerOnSyncEmail($processados, $body['dispositivo'] ?? 'App Campo');
            } catch (Exception $e) {}
        }

        echo json_encode(['status'=>$status,'processados'=>$processados,'erros'=>$erros]);
        exit;
    }

    if ($uri === '/api/animais' && $method === 'GET') {
        $authOk = isLoggedIn();
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '');
        if (!$authOk && !empty($apiKey) && hash_equals(API_KEY, (string)$apiKey)) {
            $authOk = true;
        }

        if (!$authOk) {
            http_response_code(401);
            echo json_encode(['error' => 'Acesso não autorizado. Chave de API ou sessão de usuário obrigatória.']);
            exit;
        }

        $db = getDb();
        $animais = $db->query("
            SELECT a.*, p.nome as pasto_nome,
              COALESCE(
                (SELECT peso FROM pesagens WHERE animal_id=a.id ORDER BY data DESC, id DESC LIMIT 1),
                a.peso_inicial
              ) as peso_atual
            FROM animais a
            LEFT JOIN pastagens p ON a.pasto_id=p.id
            WHERE a.status NOT IN('vendido','morto')
            ORDER BY a.brinco
        ")->fetchAll();
        echo json_encode(['animais' => $animais]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

// ── Helper de Renderização com Layout ──────────
function renderView(string $view, string $title, string $page, array $data = [], ?string $scripts = null): void {
    global $db;
    $pageTitle   = $title;
    $currentPage = $page;
    extract($data);
    ob_start();
    require __DIR__ . "/../src/views/{$view}.php";
    $content = ob_get_clean();
    require __DIR__ . '/../src/views/layout.php';
}

// ── PWA / MODO CAMPO (App Shell Autônomo 100% Mobile sem Layout Desktop) ──
if ($uri === '/campo' || $uri === '/mobile') {
    $db = getDb();
    require __DIR__ . '/../src/views/campo/index.php';
    exit;
}

// ── Protected routes (Painel Administrativo & Gestão) ──
requireLogin();
$db = getDb();

// ── Routing ────────────────────────────────────
// Dashboard
if ($uri === '/dashboard') {
    renderView('dashboard', 'Dashboard', 'dashboard');
    exit;
}

// ── MÓDULO ANIMAIS & REBANHO (AnimaisController) ──
if ($uri === '/animais') {
    (new AnimaisController())->index();
    exit;
}
if ($uri === '/animais/novo') {
    (new AnimaisController())->novo();
    exit;
}
if ($uri === '/animais/salvar' && $method === 'POST') {
    (new AnimaisController())->salvar();
    exit;
}
if (preg_match('#^/animais/(\d+)(/.*)?$#', $uri, $m)) {
    $id  = (int)$m[1];
    $sub = $m[2] ?? '';
    $controller = new AnimaisController();

    if ($sub === '' || $sub === '/') {
        $controller->show($id);
        exit;
    }
    if ($sub === '/editar') {
        $controller->editar($id);
        exit;
    }
    if ($sub === '/foto' && $method === 'POST') {
        $controller->adicionarFoto($id);
        exit;
    }
    if ($sub === '/atualizar' && $method === 'POST') {
        $controller->atualizar($id);
        exit;
    }
    if ($sub === '/excluir' && $method === 'POST') {
        $controller->excluir($id);
        exit;
    }
}
if (preg_match('#^/fotos/(\d+)/excluir$#', $uri, $m) && $method === 'POST') {
    (new AnimaisController())->excluirFoto((int)$m[1]);
    exit;
}

// ── MÓDULO PESAGENS (PesagensController) ──
if ($uri === '/pesagens') {
    (new PesagensController())->index();
    exit;
}
if ($uri === '/pesagens/novo') {
    (new PesagensController())->novo();
    exit;
}
if ($uri === '/pesagens/salvar' && $method === 'POST') {
    (new PesagensController())->salvar();
    exit;
}
if (preg_match('#^/pesagens/(\d+)(/.*)?$#', $uri, $m)) {
    $id  = (int)$m[1];
    $sub = $m[2] ?? '';
    $controller = new PesagensController();

    if ($sub === '/editar') {
        $controller->editar($id);
        exit;
    }
    if ($sub === '/atualizar' && $method === 'POST') {
        $controller->atualizar($id);
        exit;
    }
    if ($sub === '/excluir' && $method === 'POST') {
        $controller->excluir($id);
        exit;
    }
}

// ── MÓDULO SAÚDE (SaudeController) ──
if ($uri === '/saude') {
    (new SaudeController())->index();
    exit;
}
if ($uri === '/saude/novo') {
    (new SaudeController())->novo();
    exit;
}
if ($uri === '/saude/salvar' && $method === 'POST') {
    (new SaudeController())->salvar();
    exit;
}
if (preg_match('#^/saude/(\d+)(/.*)?$#', $uri, $m)) {
    $id  = (int)$m[1];
    $sub = $m[2] ?? '';
    $controller = new SaudeController();

    if ($sub === '/editar') {
        $controller->editar($id);
        exit;
    }
    if ($sub === '/atualizar' && $method === 'POST') {
        $controller->atualizar($id);
        exit;
    }
    if ($sub === '/excluir' && $method === 'POST') {
        $controller->excluir($id);
        exit;
    }
}

// ── MÓDULO PASTAGENS (PastagensController) ──
if ($uri === '/pastagens') {
    (new PastagensController())->index();
    exit;
}
if ($uri === '/pastagens/novo') {
    (new PastagensController())->novo();
    exit;
}
if ($uri === '/pastagens/salvar' && $method === 'POST') {
    (new PastagensController())->salvar();
    exit;
}
if (preg_match('#^/pastagens/(\d+)(/.*)?$#', $uri, $m)) {
    $id  = (int)$m[1];
    $sub = $m[2] ?? '';
    $controller = new PastagensController();

    if ($sub === '' || $sub === '/') {
        $controller->show($id);
        exit;
    }
    if ($sub === '/editar') {
        $controller->editar($id);
        exit;
    }
    if ($sub === '/atualizar' && $method === 'POST') {
        $controller->atualizar($id);
        exit;
    }
    if ($sub === '/excluir' && $method === 'POST') {
        $controller->excluir($id);
        exit;
    }
}

// ── MÓDULO REPRODUÇÃO (ReproducaoController) ──
if ($uri === '/reproducao') {
    (new ReproducaoController())->index();
    exit;
}
if ($uri === '/reproducao/novo') {
    (new ReproducaoController())->novo();
    exit;
}
if ($uri === '/reproducao/salvar' && $method === 'POST') {
    (new ReproducaoController())->salvar();
    exit;
}
if (preg_match('#^/reproducao/(\d+)(/.*)?$#', $uri, $m)) {
    $id  = (int)$m[1];
    $sub = $m[2] ?? '';
    $controller = new ReproducaoController();

    if ($sub === '/editar') {
        $controller->editar($id);
        exit;
    }
    if ($sub === '/atualizar' && $method === 'POST') {
        $controller->atualizar($id);
        exit;
    }
    if ($sub === '/excluir' && $method === 'POST') {
        $controller->excluir($id);
        exit;
    }
}

// ── MÓDULO ALERTAS (AlertasController) ──
if ($uri === '/alertas') {
    (new AlertasController())->index();
    exit;
}
if ($uri === '/alertas/ler-todos' && $method === 'POST') {
    (new AlertasController())->lerTodos();
    exit;
}
if ($uri === '/alertas/novo') {
    (new AlertasController())->novo();
    exit;
}
if ($uri === '/alertas/salvar' && $method === 'POST') {
    (new AlertasController())->salvar();
    exit;
}
if (preg_match('#^/alertas/(\d+)/ler$#', $uri, $m) && $method === 'POST') {
    (new AlertasController())->marcarLido((int)$m[1]);
    exit;
}
if (preg_match('#^/alertas/(\d+)/excluir$#', $uri, $m) && $method === 'POST') {
    (new AlertasController())->excluir((int)$m[1]);
    exit;
}

// ── MÓDULO COMERCIAL: COMPRAS & VENDAS (ComercialController) ──
if ($uri === '/compras') {
    (new ComercialController())->comprasIndex();
}
if ($uri === '/compras/novo') {
    (new ComercialController())->comprasNovo();
}
if ($uri === '/compras/salvar' && $method === 'POST') {
    (new ComercialController())->comprasSalvar();
}
if (preg_match('#^/compras/(\d+)/excluir$#', $uri, $m) && $method === 'POST') {
    (new ComercialController())->comprasExcluir((int)$m[1]);
}

if ($uri === '/vendas') {
    (new ComercialController())->vendasIndex();
}
if ($uri === '/vendas/novo') {
    (new ComercialController())->vendasNovo();
}
if ($uri === '/vendas/salvar' && $method === 'POST') {
    (new ComercialController())->vendasSalvar();
}
if (preg_match('#^/vendas/(\d+)/excluir$#', $uri, $m) && $method === 'POST') {
    (new ComercialController())->vendasExcluir((int)$m[1]);
}

// ── RELATÓRIOS ──
if ($uri === '/relatorios') {
    $export = $_GET['export'] ?? '';
    if ($export === 'compras') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="compras_'.date('Ymd').'.csv"');
        echo "\xEF\xBB\xBF";
        $rows = $db->query("SELECT c.numero_gta, c.chave_nfe, c.fornecedor_origem, c.data_compra, c.quantidade_cabecas, c.peso_total_kg, c.valor_total, p.nome as pasto_destino, c.descricao FROM compras c LEFT JOIN pastagens p ON c.pasto_destino_id = p.id ORDER BY c.data_compra DESC")->fetchAll();
        echo "GTA,Chave NFe,Fornecedor,Data Compra,Cabecas,Peso Total (kg),Valor Total (R$),Pasto Destino,Descricao\n";
        foreach ($rows as $r) echo implode(',', array_map(fn($v)=>'"'.str_replace('"','""',$v??'').'"', $r))."\n";
        exit;
    }
    if ($export === 'vendas') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="vendas_'.date('Ymd').'.csv"');
        echo "\xEF\xBB\xBF";
        $rows = $db->query("SELECT v.numero_gta, v.chave_nfe, v.comprador_destino, v.data_venda, v.quantidade_cabecas, v.peso_total_kg, v.valor_total, v.tipo_precificacao, v.preco_unitario, v.descricao FROM vendas v ORDER BY v.data_venda DESC")->fetchAll();
        echo "GTA,Chave NFe,Comprador,Data Venda,Cabecas,Peso Total (kg),Valor Total (R$),Tipo Precificacao,Preco Unitario,Descricao\n";
        foreach ($rows as $r) echo implode(',', array_map(fn($v)=>'"'.str_replace('"','""',$v??'').'"', $r))."\n";
        exit;
    }
    if ($export === 'animais') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="animais_'.date('Ymd').'.csv"');
        echo "\xEF\xBB\xBF";
        $rows = $db->query("SELECT a.brinco,a.nome,a.sexo,a.raca,a.data_nascimento,a.status,a.peso_inicial,p.nome as pasto FROM animais a LEFT JOIN pastagens p ON a.pasto_id=p.id ORDER BY a.brinco")->fetchAll();
        echo "Brinco,Nome,Sexo,Raça,Nascimento,Status,Peso Inicial,Pastagem\n";
        foreach ($rows as $r) echo implode(',', array_map(fn($v)=>'"'.str_replace('"','""',$v??'').'"', $r))."\n";
        exit;
    }
    if ($export === 'pesagens') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="pesagens_'.date('Ymd').'.csv"');
        echo "\xEF\xBB\xBF";
        $rows = $db->query("SELECT a.brinco,a.nome,pe.peso,pe.data,pe.observacao,pe.origem FROM pesagens pe JOIN animais a ON pe.animal_id=a.id ORDER BY pe.data DESC")->fetchAll();
        echo "Brinco,Nome,Peso(kg),Data,Observação,Origem\n";
        foreach ($rows as $r) echo implode(',', array_map(fn($v)=>'"'.str_replace('"','""',$v??'').'"', $r))."\n";
        exit;
    }
    if ($export === 'saude') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="saude_'.date('Ymd').'.csv"');
        echo "\xEF\xBB\xBF";
        $rows = $db->query("SELECT a.brinco,s.tipo,s.descricao,s.data,s.medicamento,s.dose,s.veterinario,s.custo FROM saude s JOIN animais a ON s.animal_id=a.id ORDER BY s.data DESC")->fetchAll();
        echo "Brinco,Tipo,Descrição,Data,Medicamento,Dose,Veterinário,Custo(R$)\n";
        foreach ($rows as $r) echo implode(',', array_map(fn($v)=>'"'.str_replace('"','""',$v??'').'"', $r))."\n";
        exit;
    }
    if ($export === 'reproducao') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="reproducao_'.date('Ymd').'.csv"');
        echo "\xEF\xBB\xBF";
        $rows = $db->query("SELECT a.brinco, a.nome, r.tipo, r.data, r.touro_brinco, r.resultado, r.observacao FROM reproducao r JOIN animais a ON r.animal_id=a.id ORDER BY r.data DESC")->fetchAll();
        echo "Brinco,Nome,Tipo,Data,Touro/Pai,Resultado,Observação\n";
        foreach ($rows as $r) echo implode(',', array_map(fn($v)=>'"'.str_replace('"','""',$v??'').'"', $r))."\n";
        exit;
    }
    if ($export === 'pastagens') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="pastagens_'.date('Ymd').'.csv"');
        echo "\xEF\xBB\xBF";
        $rows = $db->query("SELECT p.nome, p.area_ha, p.capacidade, p.status, COUNT(a.id) as total_animais, p.observacao FROM pastagens p LEFT JOIN animais a ON a.pasto_id=p.id AND a.status NOT IN ('vendido','morto') GROUP BY p.id ORDER BY p.nome")->fetchAll();
        echo "Nome,Área(ha),Capacidade,Status,Total Animais,Observação\n";
        foreach ($rows as $r) echo implode(',', array_map(fn($v)=>'"'.str_replace('"','""',$v??'').'"', $r))."\n";
        exit;
    }
    renderView('relatorios/index', 'Relatórios', 'relatorios');
    exit;
}

// ── SINCRONIZAÇÕES ──
if ($uri === '/sincronizacoes') {
    renderView('sincronizacoes/index', 'Sincronização Mobile', 'sincronizacoes');
    exit;
}

// ── CONFIGURAÇÕES DO SISTEMA & NOTIFICAÇÕES ──
if ($uri === '/configuracoes') {
    requireLogin();
    renderView('configuracoes/index', 'Configurações do Sistema', 'configuracoes');
    exit;
}

if ($uri === '/configuracoes/salvar' && $method === 'POST') {
    requireLogin();
    if (!csrf_verify()) {
        flash('error', 'Token de segurança expirado. Tente novamente.');
        redirect('/configuracoes');
    }
    
    $enabled      = isset($_POST['notif_email_enabled']) ? '1' : '0';
    $destinatario = trim($_POST['notif_email_destinatario'] ?? '');
    $from         = trim($_POST['notif_smtp_from'] ?? '');
    $smtpHost     = trim($_POST['notif_smtp_host'] ?? '');
    $smtpPort     = trim($_POST['notif_smtp_port'] ?? '587');
    $smtpUser     = trim($_POST['notif_smtp_user'] ?? '');
    $smtpPass     = $_POST['notif_smtp_pass'] ?? '';
    $smtpSecure   = trim($_POST['notif_smtp_secure'] ?? 'tls');

    setSysConfig('notif_email_enabled', $enabled);
    setSysConfig('notif_email_destinatario', $destinatario);
    setSysConfig('notif_smtp_from', $from);
    setSysConfig('notif_smtp_host', $smtpHost);
    setSysConfig('notif_smtp_port', $smtpPort);
    setSysConfig('notif_smtp_user', $smtpUser);
    if (!empty($smtpPass)) {
        setSysConfig('notif_smtp_pass', $smtpPass);
    }
    setSysConfig('notif_smtp_secure', $smtpSecure);

    flash('success', 'Configurações de e-mail e SMTP salvas com sucesso!');
    redirect('/configuracoes');
    exit;
}

if ($uri === '/configuracoes/testar-email' && $method === 'POST') {
    requireLogin();
    if (!csrf_verify()) { flash('error', 'Token de segurança expirado.'); redirect('/configuracoes'); }
    $destinatario = getSysConfig('notif_email_destinatario', DEFAULT_ADMIN_EMAIL);
    
    $assunto = "[PecuáriaGest] Teste de Notificação do Sistema";
    $corpo = '
    <div style="font-family:Arial,sans-serif;padding:20px;background:#f4f6f4;color:#2c3e2d;">
      <div style="max-width:520px;margin:0 auto;background:#fff;padding:24px;border-radius:10px;border-top:5px solid #1a4d2e;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        <h3 style="color:#1a4d2e;margin-top:0;font-size:20px;">Teste de Notificação SMTP Bem-Sucedido!</h3>
        <p style="font-size:15px;line-height:1.5;">Este é um e-mail de teste disparado pelo sistema <strong>PecuáriaGest</strong> confirmando a comunicação ativa com o servidor.</p>
        <div style="background:#f8faf8;padding:12px 16px;border-radius:6px;border:1px solid #e8ede9;margin:16px 0;font-size:13px;">
          <strong>Destinatário:</strong> ' . htmlspecialchars($destinatario) . '<br>
          <strong>Horário do Disparo:</strong> ' . date('d/m/Y H:i:s') . '<br>
          <strong>Status:</strong> Conexão SMTP autenticada e entregue.
        </div>
        <p style="font-size:12px;color:#6b7280;margin-bottom:0;">PecuáriaGest — Gestão Agropecuária Integrada</p>
      </div>
    </div>';

    $errorMsg = null;
    $enviado = sendNotificationEmail($destinatario, $assunto, $corpo, $errorMsg);
    if ($enviado) {
        flash('success', "E-mail de teste enviado com sucesso para {$destinatario}!");
    } else {
        flash('error', "Falha no envio de e-mail: " . ($errorMsg ?: "Verifique as credenciais do servidor SMTP."));
    }
    redirect('/configuracoes');
    exit;
}

// ── GESTÃO DE USUÁRIOS & PERMISSÕES GRANULARES ──
if ($uri === '/usuarios') {
    requirePermission('gerenciar_usuarios');
    renderView('usuarios/index', 'Gestão de Usuários & Permissões', 'usuarios');
    exit;
}

if ($uri === '/usuarios/novo') {
    requirePermission('gerenciar_usuarios');
    renderView('usuarios/form', 'Novo Colaborador', 'usuarios', ['usuario' => []]);
    exit;
}

if ($uri === '/usuarios/criar' && $method === 'POST') {
    requirePermission('gerenciar_usuarios');
    if (!csrf_verify()) {
        flash('error', 'Token de segurança expirado.');
        redirect('/usuarios/novo');
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
        redirect('/usuarios/novo');
    }

    // Valida se e-mail já existe
    $exists = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $exists->execute([$email]);
    if ($exists->fetch()) {
        flash('error', 'Já existe um colaborador cadastrado com este e-mail.');
        redirect('/usuarios/novo');
    }

    $hash = password_hash($senha, PASSWORD_BCRYPT);
    $permsJson = json_encode($perms);

    $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha, tipo, cargo, permissoes, ativo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nome, $email, $hash, $tipo, $cargo, $permsJson, $ativo]);

    flash('success', "Colaborador {$nome} cadastrado com sucesso!");
    redirect('/usuarios');
    exit;
}

if (preg_match('#^/usuarios/(\d+)/editar$#', $uri, $m)) {
    requirePermission('gerenciar_usuarios');
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$m[1]]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        flash('error', 'Usuário não encontrado.');
        redirect('/usuarios');
    }
    renderView('usuarios/form', 'Editar Colaborador', 'usuarios', ['usuario' => $u]);
    exit;
}

if (preg_match('#^/usuarios/(\d+)/salvar$#', $uri, $m) && $method === 'POST') {
    requirePermission('gerenciar_usuarios');
    if (!csrf_verify()) {
        flash('error', 'Token de segurança expirado.');
        redirect("/usuarios/{$m[1]}/editar");
    }

    $id     = (int)$m[1];
    $nome   = trim($_POST['nome'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $senha  = $_POST['senha'] ?? '';
    $cargo  = trim($_POST['cargo'] ?? 'Colaborador');
    $tipo   = $_POST['tipo'] ?? 'usuario';
    $ativo  = isset($_POST['ativo']) ? 1 : 0;
    $perms  = $_POST['perm'] ?? [];

    if (empty($nome) || empty($email)) {
        flash('error', 'Nome e e-mail são obrigatórios.');
        redirect("/usuarios/{$id}/editar");
    }

    // Valida e-mail duplicado em outro ID
    $check = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ? LIMIT 1");
    $check->execute([$email, $id]);
    if ($check->fetch()) {
        flash('error', 'Este e-mail já está sendo utilizado por outro colaborador.');
        redirect("/usuarios/{$id}/editar");
    }

    $permsJson = json_encode($perms);

    if (!empty($senha)) {
        $hash = password_hash($senha, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE usuarios SET nome = ?, email = ?, senha = ?, tipo = ?, cargo = ?, permissoes = ?, ativo = ? WHERE id = ?");
        $stmt->execute([$nome, $email, $hash, $tipo, $cargo, $permsJson, $ativo, $id]);
    } else {
        $stmt = $db->prepare("UPDATE usuarios SET nome = ?, email = ?, tipo = ?, cargo = ?, permissoes = ?, ativo = ? WHERE id = ?");
        $stmt->execute([$nome, $email, $tipo, $cargo, $permsJson, $ativo, $id]);
    }

    // Se editou o próprio usuário logado, atualiza a sessão
    if ($_SESSION['user_id'] == $id) {
        $updatedUser = $db->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
        $updatedUser->execute([$id]);
        $_SESSION['user'] = $updatedUser->fetch(PDO::FETCH_ASSOC);
    }

    flash('success', "Dados de {$nome} atualizados com sucesso!");
    redirect('/usuarios');
    exit;
}

if (preg_match('#^/usuarios/(\d+)/toggle-status$#', $uri, $m) && $method === 'POST') {
    requirePermission('gerenciar_usuarios');
    if (!csrf_verify()) { flash('error', 'Token de segurança expirado.'); redirect('/usuarios'); }
    $id = (int)$m[1];
    if ($_SESSION['user_id'] == $id) {
        flash('error', 'Você não pode desativar seu próprio usuário.');
        redirect('/usuarios');
    }

    $db->prepare("UPDATE usuarios SET ativo = (1 - ativo) WHERE id = ?")->execute([$id]);
    flash('success', 'Status do usuário alterado com sucesso.');
    redirect('/usuarios');
    exit;
}

if (preg_match('#^/usuarios/(\d+)/excluir$#', $uri, $m) && $method === 'POST') {
    requirePermission('gerenciar_usuarios');
    if (!csrf_verify()) {
        flash('error', 'Token de segurança expirado.');
        redirect('/usuarios');
    }

    $id = (int)$m[1];
    if ($_SESSION['user_id'] == $id) {
        flash('error', 'Você não pode excluir seu próprio usuário.');
        redirect('/usuarios');
    }

    $db->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
    flash('success', 'Usuário removido da equipe com sucesso.');
    redirect('/usuarios');
    exit;
}

// 404
http_response_code(404);
echo '<div style="font-family:sans-serif;text-align:center;padding:4rem;color:#666">
  <h1 style="color:#1a4d2e;font-size:3rem">404</h1>
  <p>Página não encontrada.</p>
  <a href="/dashboard" style="color:#2d7a4e">← Voltar ao início</a>
</div>';
