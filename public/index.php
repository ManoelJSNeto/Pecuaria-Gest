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

// ── ANIMAIS ──
if ($uri === '/animais') {
    renderView('animais/index', 'Animais', 'animais');
    exit;
}
if ($uri === '/animais/novo') {
    renderView('animais/form', 'Cadastrar Animal', 'animais');
    exit;
}
if ($uri === '/animais/salvar' && $method === 'POST') {
    if (!csrf_verify()) { flash('error', 'Token inválido.'); redirect('/animais/novo'); }
    
    $brinco = trim($_POST['brinco'] ?? '');
    $maeId = !empty($_POST['mae_id']) ? (int)$_POST['mae_id'] : null;
    $paiBrinco = trim($_POST['pai_brinco'] ?? '') ?: null;

    // Validação estrita da Mãe: deve ser fêmea e não pode ser ela mesma
    if ($maeId) {
        $chkMae = $db->prepare("SELECT id, brinco, sexo FROM animais WHERE id = ?");
        $chkMae->execute([$maeId]);
        $mae = $chkMae->fetch();
        if (!$mae) {
            flash('error', 'A mãe biológica informada não foi encontrada no rebanho.');
            redirect('/animais/novo');
        }
        if ($mae['sexo'] !== 'F') {
            flash('error', 'Inconsistência zootécnica: A mãe biológica deve ser obrigatoriamente uma FÊMEA.');
            redirect('/animais/novo');
        }
        if (strtoupper($mae['brinco']) === strtoupper($brinco)) {
            flash('error', 'Inconsistência genealógica: O animal não pode ser a mãe de si mesmo.');
            redirect('/animais/novo');
        }
    }

    // Validação estrita do Pai: não pode ser o próprio animal nem uma fêmea
    if ($paiBrinco) {
        if (strtoupper($paiBrinco) === strtoupper($brinco)) {
            flash('error', 'Inconsistência genealógica: O animal não pode ser o pai de si mesmo.');
            redirect('/animais/novo');
        }
        if ($maeId && isset($mae) && strtoupper($paiBrinco) === strtoupper($mae['brinco'])) {
            flash('error', 'Inconsistência zootécnica: O pai e a mãe biológicos não podem ser o mesmo animal.');
            redirect('/animais/novo');
        }
        $chkPai = $db->prepare("SELECT id, sexo FROM animais WHERE UPPER(brinco) = UPPER(?)");
        $chkPai->execute([$paiBrinco]);
        $pai = $chkPai->fetch();
        if ($pai && $pai['sexo'] === 'F') {
            flash('error', 'Inconsistência zootécnica: O animal com brinco "'.$paiBrinco.'" é uma FÊMEA e não pode ser informado como pai/touro reprodutor.');
            redirect('/animais/novo');
        }
    }

    $fotoUrl = !empty($_FILES['foto']) ? uploadFoto($_FILES['foto']) : null;
    $stmt = $db->prepare("INSERT INTO animais (brinco,nome,sexo,raca,data_nascimento,peso_inicial,status,pasto_id,origem,mae_id,pai_brinco,observacao,foto_url) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    try {
        $stmt->execute([
            trim($_POST['brinco']),
            trim($_POST['nome'] ?? '') ?: null,
            $_POST['sexo'] ?? 'M',
            trim($_POST['raca'] ?? '') ?: null,
            $_POST['data_nascimento'] ?: null,
            $_POST['peso_inicial'] ?: null,
            $_POST['status'] ?? 'ativo',
            $_POST['pasto_id'] ?: null,
            trim($_POST['origem'] ?? '') ?: null,
            $_POST['mae_id'] ?: null,
            trim($_POST['pai_brinco'] ?? '') ?: null,
            trim($_POST['observacao'] ?? '') ?: null,
            $fotoUrl
        ]);
        $newId = $db->lastInsertId();
        if ($fotoUrl && $newId) {
            $isPuppy = isFilhote($_POST['data_nascimento'] ?: null);
            $db->prepare("INSERT INTO fotos_animais (animal_id,foto_url,tipo_evento,fase,data,observacao) VALUES (?,?,?,?,?,?)")
               ->execute([$newId, $fotoUrl, 'nascimento', $isPuppy ? 'filhote' : 'adulto', $_POST['data_nascimento'] ?: date('Y-m-d'), 'Foto de cadastro inicial']);
        }
        flash('success', 'Animal cadastrado com sucesso!');
        redirect('/animais/' . $newId);
    } catch (Exception $e) {
        flash('error', 'Erro: brinco já existe ou dados inválidos.');
        redirect('/animais/novo');
    }
    exit;
}

// Animal detail/edit — match /animais/{id}[/...]
if (preg_match('#^/animais/(\d+)(/.*)?$#', $uri, $m)) {
    $id  = (int)$m[1];
    $sub = $m[2] ?? '';

    $animalStmt = $db->prepare("SELECT * FROM animais WHERE id=?");
    $animalStmt->execute([$id]);
    $animal = $animalStmt->fetch() ?: null;

    if (!$animal) { flash('error','Animal não encontrado.'); redirect('/animais'); }

    if ($sub === '' || $sub === '/') {
        renderView('animais/show', 'Animal #'.$animal['brinco'], 'animais', ['animal' => $animal]);
        exit;
    }
    if ($sub === '/editar') {
        renderView('animais/form', 'Editar Animal', 'animais', ['animal' => $animal]);
        exit;
    }
    if ($sub === '/foto' && $method === 'POST') {
        if (!csrf_verify()) { flash('error','Token inválido.'); redirect("/animais/$id"); }
        if (!empty($_FILES['foto']['tmp_name'])) {
            $fUrl = uploadFoto($_FILES['foto']);
            if ($fUrl) {
                $tipo = $_POST['tipo_evento'] ?? 'geral';
                $isSensivel = !empty($_POST['is_sensivel']) || $tipo === 'obito' || $animal['status'] === 'morto' ? 1 : 0;
                $fase = $tipo === 'obito' ? 'obito' : (isFilhote($animal['data_nascimento']) ? 'filhote' : 'adulto');
                $obs = trim($_POST['observacao'] ?? '') ?: null;
                $dataFoto = $_POST['data'] ?: date('Y-m-d');
                
                $db->prepare("INSERT INTO fotos_animais (animal_id,foto_url,tipo_evento,fase,is_sensivel,data,observacao) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$id, $fUrl, $tipo, $fase, $isSensivel, $dataFoto, $obs]);
                
                if (empty($animal['foto_url']) || !empty($_POST['definir_principal'])) {
                    $db->prepare("UPDATE animais SET foto_url=? WHERE id=?")->execute([$fUrl, $id]);
                }
                flash('success','Foto adicionada com sucesso à galeria do animal!');
            } else {
                flash('error','Formato de imagem inválido (use JPG, PNG ou WEBP).');
            }
        }
        redirect("/animais/$id");
        exit;
    }
    if ($sub === '/atualizar' && $method === 'POST') {
        if (!csrf_verify()) { flash('error','Token inválido.'); redirect("/animais/$id/editar"); }
        
        $brinco = trim($_POST['brinco'] ?? '');
        $maeId = !empty($_POST['mae_id']) ? (int)$_POST['mae_id'] : null;
        $paiBrinco = trim($_POST['pai_brinco'] ?? '') ?: null;

        // Validação estrita da Mãe: não pode ser ela mesma e deve ser fêmea
        if ($maeId) {
            if ($maeId === $id) {
                flash('error', 'Inconsistência genealógica: O animal não pode ser a mãe de si mesmo.');
                redirect("/animais/$id/editar");
            }
            $chkMae = $db->prepare("SELECT id, brinco, sexo FROM animais WHERE id = ?");
            $chkMae->execute([$maeId]);
            $mae = $chkMae->fetch();
            if (!$mae) {
                flash('error', 'A mãe selecionada não foi encontrada no rebanho.');
                redirect("/animais/$id/editar");
            }
            if ($mae['sexo'] !== 'F') {
                flash('error', 'Inconsistência zootécnica: A mãe biológica deve ser obrigatoriamente uma FÊMEA.');
                redirect("/animais/$id/editar");
            }
            if (strtoupper($mae['brinco']) === strtoupper($brinco)) {
                flash('error', 'Inconsistência genealógica: O animal não pode ser a mãe de si mesmo.');
                redirect("/animais/$id/editar");
            }
        }

        // Validação estrita do Pai: não pode ser ele mesmo nem fêmea
        if ($paiBrinco) {
            if (strtoupper($paiBrinco) === strtoupper($brinco)) {
                flash('error', 'Inconsistência genealógica: O animal não pode ser o pai de si mesmo.');
                redirect("/animais/$id/editar");
            }
            if ($maeId && isset($mae) && strtoupper($paiBrinco) === strtoupper($mae['brinco'])) {
                flash('error', 'Inconsistência zootécnica: O pai e a mãe biológicos não podem ser o mesmo animal.');
                redirect("/animais/$id/editar");
            }
            $chkPai = $db->prepare("SELECT id, sexo FROM animais WHERE UPPER(brinco) = UPPER(?)");
            $chkPai->execute([$paiBrinco]);
            $pai = $chkPai->fetch();
            if ($pai) {
                if ($pai['id'] === $id) {
                    flash('error', 'Inconsistência genealógica: O animal não pode ser o pai de si mesmo.');
                    redirect("/animais/$id/editar");
                }
                if ($pai['sexo'] === 'F') {
                    flash('error', 'Inconsistência zootécnica: O animal com brinco "'.$paiBrinco.'" é uma FÊMEA e não pode ser informado como pai/touro reprodutor.');
                    redirect("/animais/$id/editar");
                }
            }
        }

        $fotoUrl = $animal['foto_url'];
        if (!empty($_FILES['foto']['tmp_name'])) {
            $newPhoto = uploadFoto($_FILES['foto']);
            if ($newPhoto) {
                $fotoUrl = $newPhoto;
                $db->prepare("INSERT INTO fotos_animais (animal_id,foto_url,tipo_evento,fase,data,observacao) VALUES (?,?,'perfil',?,?,?)")
                   ->execute([$id, $newPhoto, isFilhote($_POST['data_nascimento'] ?: null) ? 'filhote' : 'adulto', date('Y-m-d'), 'Atualização de foto de perfil']);
            }
        }
        $db->prepare("UPDATE animais SET brinco=?,nome=?,sexo=?,raca=?,data_nascimento=?,peso_inicial=?,status=?,pasto_id=?,origem=?,mae_id=?,pai_brinco=?,observacao=?,foto_url=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
           ->execute([
               trim($_POST['brinco']),
               trim($_POST['nome'] ?? '') ?: null,
               $_POST['sexo'] ?? 'M',
               trim($_POST['raca'] ?? '') ?: null,
               $_POST['data_nascimento'] ?: null,
               $_POST['peso_inicial'] ?: null,
               $_POST['status'] ?? 'ativo',
               $_POST['pasto_id'] ?: null,
               trim($_POST['origem'] ?? '') ?: null,
               $_POST['mae_id'] ?: null,
               trim($_POST['pai_brinco'] ?? '') ?: null,
               trim($_POST['observacao'] ?? '') ?: null,
               $fotoUrl,
               $id,
           ]);
        flash('success','Animal atualizado!');
        redirect("/animais/$id");
        exit;
    }
    if ($sub === '/excluir' && $method === 'POST') {
        if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/animais'); }
        $db->prepare("DELETE FROM animais WHERE id=?")->execute([$id]);
        flash('success','Animal removido.');
        redirect('/animais');
        exit;
    }
}

// ── FOTOS EXCLUIR ──
if (preg_match('#^/fotos/(\d+)/excluir$#', $uri, $m) && $method === 'POST') {
    if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/animais'); }
    $fId = (int)$m[1];
    $fStmt = $db->prepare("SELECT animal_id, foto_url FROM fotos_animais WHERE id=?");
    $fStmt->execute([$fId]);
    $f = $fStmt->fetch();
    if ($f) {
        $db->prepare("DELETE FROM fotos_animais WHERE id=?")->execute([$fId]);
        flash('success','Foto removida do histórico.');
        redirect('/animais/' . $f['animal_id']);
    } else {
        redirect('/animais');
    }
    exit;
}

// ── PESAGENS ──
if ($uri === '/pesagens') {
    renderView('pesagens/index', 'Pesagens', 'pesagens');
    exit;
}
if ($uri === '/pesagens/novo') {
    renderView('pesagens/form', 'Registrar Pesagem', 'pesagens');
    exit;
}
if ($uri === '/pesagens/salvar' && $method === 'POST') {
    if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/pesagens/novo'); }
    $db->prepare("INSERT INTO pesagens (animal_id,peso,data,observacao,origem) VALUES (?,?,?,?,'web')")
       ->execute([$_POST['animal_id'],$_POST['peso'],$_POST['data'],trim($_POST['observacao'] ?? '') ?: null]);
    flash('success','Pesagem registrada!');
    $back = !empty($_POST['animal_id']) ? "/animais/{$_POST['animal_id']}" : '/pesagens';
    redirect($back);
    exit;
}
if (preg_match('#^/pesagens/(\d+)(/.*)?$#', $uri, $m)) {
    $id  = (int)$m[1];
    $sub = $m[2] ?? '';
    $pStmt = $db->prepare("SELECT * FROM pesagens WHERE id=?");
    $pStmt->execute([$id]);
    $pesagem = $pStmt->fetch() ?: null;
    if (!$pesagem) { flash('error','Pesagem não encontrada.'); redirect('/pesagens'); }
    if ($sub === '/editar') {
        renderView('pesagens/form', 'Editar Pesagem', 'pesagens', ['pesagem' => $pesagem]);
        exit;
    }
    if ($sub === '/atualizar' && $method === 'POST') {
        if (!csrf_verify()) { flash('error','Token inválido.'); redirect("/pesagens/$id/editar"); }
        $db->prepare("UPDATE pesagens SET animal_id=?,peso=?,data=?,observacao=? WHERE id=?")
           ->execute([$_POST['animal_id'],$_POST['peso'],$_POST['data'],trim($_POST['observacao'] ?? '') ?: null, $id]);
        flash('success','Pesagem atualizada com sucesso!');
        $back = !empty($_POST['animal_id']) ? "/animais/{$_POST['animal_id']}" : '/pesagens';
        redirect($back);
        exit;
    }
    if ($sub === '/excluir' && $method === 'POST') {
        if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/pesagens'); }
        $db->prepare("DELETE FROM pesagens WHERE id=?")->execute([$id]);
        flash('success','Pesagem excluída.');
        redirect($pesagem['animal_id'] ? "/animais/{$pesagem['animal_id']}" : '/pesagens');
        exit;
    }
}

// ── SAÚDE ──
if ($uri === '/saude') {
    renderView('saude/index', 'Saúde', 'saude');
    exit;
}
if ($uri === '/saude/novo') {
    renderView('saude/form', 'Registrar Evento de Saúde', 'saude');
    exit;
}
if ($uri === '/saude/salvar' && $method === 'POST') {
    if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/saude/novo'); }
    $aid = (int)($_POST['animal_id'] ?? 0);
    $tipo = (string)($_POST['tipo'] ?? '');
    $db->prepare("INSERT INTO saude (animal_id,tipo,descricao,data,proxima_data,custo,medicamento,dose,veterinario,observacao,origem) VALUES (?,?,?,?,?,?,?,?,?,?,'web')")
       ->execute([
           $aid,
           $tipo,
           $_POST['descricao'],
           $_POST['data'],
           $_POST['proxima_data'] ?: null,
           $_POST['custo'] ?: null,
           $_POST['medicamento'] ?: null,
           $_POST['dose'] ?: null,
           $_POST['veterinario'] ?: null,
           trim($_POST['observacao'] ?? '') ?: null,
       ]);

    // Atualização automática de status (óbito -> morto, tratamento -> doente, alta -> ativo, parto -> ativo)
    atualizarStatusAnimalPorSaude($db, $aid, $tipo);

    flash('success','Evento de saúde registrado!');
    $back = !empty($aid) ? "/animais/{$aid}" : '/saude';
    redirect($back);
    exit;
}
if (preg_match('#^/saude/(\d+)(/.*)?$#', $uri, $m)) {
    $id  = (int)$m[1];
    $sub = $m[2] ?? '';
    $sStmt = $db->prepare("SELECT * FROM saude WHERE id=?");
    $sStmt->execute([$id]);
    $saude = $sStmt->fetch() ?: null;
    if (!$saude) { flash('error','Registro de saúde não encontrado.'); redirect('/saude'); }
    if ($sub === '/editar') {
        renderView('saude/form', 'Editar Evento de Saúde', 'saude', ['saude' => $saude]);
        exit;
    }
    if ($sub === '/atualizar' && $method === 'POST') {
        if (!csrf_verify()) { flash('error','Token inválido.'); redirect("/saude/$id/editar"); }
        $aid = (int)($_POST['animal_id'] ?? 0);
        $tipo = (string)($_POST['tipo'] ?? '');
        $db->prepare("UPDATE saude SET animal_id=?,tipo=?,descricao=?,data=?,proxima_data=?,custo=?,medicamento=?,dose=?,veterinario=?,observacao=? WHERE id=?")
           ->execute([
               $aid,
               $tipo,
               $_POST['descricao'],
               $_POST['data'],
               $_POST['proxima_data'] ?: null,
               $_POST['custo'] ?: null,
               $_POST['medicamento'] ?: null,
               $_POST['dose'] ?: null,
               $_POST['veterinario'] ?: null,
               trim($_POST['observacao'] ?? '') ?: null,
               $id,
           ]);

        // Atualização automática de status
        atualizarStatusAnimalPorSaude($db, $aid, $tipo);

        flash('success','Evento de saúde atualizado!');
        $back = !empty($aid) ? "/animais/{$aid}" : '/saude';
        redirect($back);
        exit;
    }
    if ($sub === '/excluir' && $method === 'POST') {
        if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/saude'); }
        $db->prepare("DELETE FROM saude WHERE id=?")->execute([$id]);
        flash('success','Registro excluído.');
        redirect($saude['animal_id'] ? "/animais/{$saude['animal_id']}" : '/saude');
        exit;
    }
}

// ── REPRODUÇÃO ──
if ($uri === '/reproducao') {
    requirePermission('ver_reproducao');
    renderView('reproducao/index', 'Manejo Reprodutivo', 'reproducao');
    exit;
}
if ($uri === '/reproducao/novo') {
    requirePermission('ver_reproducao');
    renderView('reproducao/form', 'Registrar Evento Reprodutivo', 'reproducao');
    exit;
}
if ($uri === '/reproducao/salvar' && $method === 'POST') {
    requirePermission('ver_reproducao');
    if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/reproducao/novo'); }
    $aid = (int)($_POST['animal_id'] ?? 0);
    $tipo = trim($_POST['tipo'] ?? '');
    $data = $_POST['data'] ?? date('Y-m-d');
    $touro = trim($_POST['touro_brinco'] ?? '') ?: null;
    $resultado = trim($_POST['resultado'] ?? '') ?: null;
    $obs = trim($_POST['observacao'] ?? '') ?: null;

    $db->prepare("INSERT INTO reproducao (animal_id,tipo,data,touro_brinco,resultado,observacao) VALUES (?,?,?,?,?,?)")
       ->execute([$aid, $tipo, $data, $touro, $resultado, $obs]);

    atualizarStatusAnimalPorReproducao($db, $aid, $tipo, $resultado);

    flash('success','Evento reprodutivo registrado com sucesso!');
    $back = !empty($aid) ? "/animais/{$aid}" : '/reproducao';
    redirect($back);
    exit;
}
if (preg_match('#^/reproducao/(\d+)(/.*)?$#', $uri, $m)) {
    requirePermission('ver_reproducao');
    $id  = (int)$m[1];
    $sub = $m[2] ?? '';
    $rStmt = $db->prepare("SELECT * FROM reproducao WHERE id=?");
    $rStmt->execute([$id]);
    $reproducao = $rStmt->fetch() ?: null;
    if (!$reproducao) { flash('error','Registro reprodutivo não encontrado.'); redirect('/reproducao'); }

    if ($sub === '/editar') {
        renderView('reproducao/form', 'Editar Evento Reprodutivo', 'reproducao', ['reproducao' => $reproducao]);
        exit;
    }
    if ($sub === '/atualizar' && $method === 'POST') {
        if (!csrf_verify()) { flash('error','Token inválido.'); redirect("/reproducao/$id/editar"); }
        $aid = (int)($_POST['animal_id'] ?? 0);
        $tipo = trim($_POST['tipo'] ?? '');
        $data = $_POST['data'] ?? date('Y-m-d');
        $touro = trim($_POST['touro_brinco'] ?? '') ?: null;
        $resultado = trim($_POST['resultado'] ?? '') ?: null;
        $obs = trim($_POST['observacao'] ?? '') ?: null;

        $db->prepare("UPDATE reproducao SET animal_id=?,tipo=?,data=?,touro_brinco=?,resultado=?,observacao=? WHERE id=?")
           ->execute([$aid, $tipo, $data, $touro, $resultado, $obs, $id]);

        atualizarStatusAnimalPorReproducao($db, $aid, $tipo, $resultado);

        flash('success','Evento reprodutivo atualizado com sucesso!');
        $back = !empty($aid) ? "/animais/{$aid}" : '/reproducao';
        redirect($back);
        exit;
    }
    if ($sub === '/excluir' && $method === 'POST') {
        if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/reproducao'); }
        $db->prepare("DELETE FROM reproducao WHERE id=?")->execute([$id]);
        flash('success','Evento reprodutivo excluído.');
        redirect($reproducao['animal_id'] ? "/animais/{$reproducao['animal_id']}" : '/reproducao');
        exit;
    }
}

// ── PASTAGENS ──
if ($uri === '/pastagens') {
    renderView('pastagens/index', 'Pastagens', 'pastagens');
    exit;
}
if ($uri === '/pastagens/novo') {
    renderView('pastagens/form', 'Cadastrar Pastagem', 'pastagens');
    exit;
}
if ($uri === '/pastagens/salvar' && $method === 'POST') {
    if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/pastagens/novo'); }
    $db->prepare("INSERT INTO pastagens (nome,area_ha,capacidade,status,observacao) VALUES (?,?,?,?,?)")
       ->execute([trim($_POST['nome']),$_POST['area_ha']?:null,$_POST['capacidade']?:null,$_POST['status']??'ativa',trim($_POST['observacao']??'')?:null]);
    flash('success','Pastagem cadastrada!');
    redirect('/pastagens');
    exit;
}
if (preg_match('#^/pastagens/(\d+)(/.*)?$#', $uri, $m)) {
    $id  = (int)$m[1];
    $sub = $m[2] ?? '';
    $pStmt = $db->prepare("SELECT * FROM pastagens WHERE id=?");
    $pStmt->execute([$id]);
    $pastagem = $pStmt->fetch() ?: null;
    if (!$pastagem) { flash('error','Pastagem não encontrada.'); redirect('/pastagens'); }
    if ($sub === '' || $sub === '/') {
        redirect('/animais?pasto_id=' . $id);
        exit;
    }
    if ($sub === '/editar') { renderView('pastagens/form', 'Editar Pastagem', 'pastagens', ['pastagem' => $pastagem]); exit; }
    if ($sub === '/atualizar' && $method === 'POST') {
        if (!csrf_verify()) { flash('error','Token inválido.'); redirect("/pastagens/$id/editar"); }
        $db->prepare("UPDATE pastagens SET nome=?,area_ha=?,capacidade=?,status=?,observacao=? WHERE id=?")
           ->execute([trim($_POST['nome']),$_POST['area_ha']?:null,$_POST['capacidade']?:null,$_POST['status']??'ativa',trim($_POST['observacao']??'')?:null,$id]);
        flash('success','Pastagem atualizada!');
        redirect('/pastagens');
        exit;
    }
    if ($sub === '/excluir' && $method === 'POST') {
        if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/pastagens'); }
        $db->prepare("DELETE FROM pastagens WHERE id=?")->execute([$id]);
        flash('success','Pastagem removida.');
        redirect('/pastagens');
        exit;
    }
}

// ── REPRODUÇÃO ──
if ($uri === '/reproducao') {
    renderView('reproducao/index', 'Reprodução', 'reproducao');
    exit;
}
if ($uri === '/reproducao/novo') {
    renderView('reproducao/form', 'Registrar Evento Reprodutivo', 'reproducao');
    exit;
}
if ($uri === '/reproducao/salvar' && $method === 'POST') {
    if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/reproducao/novo'); }
    
    $animalId = (int)($_POST['animal_id'] ?? 0);
    $touroBrinco = trim($_POST['touro_brinco'] ?? '') ?: null;

    $matrizStmt = $db->prepare("SELECT id, brinco, sexo FROM animais WHERE id = ?");
    $matrizStmt->execute([$animalId]);
    $matriz = $matrizStmt->fetch();
    if (!$matriz) {
        flash('error', 'Animal não encontrado para o manejo reprodutivo.');
        redirect('/reproducao/novo');
    }
    if ($matriz['sexo'] !== 'F') {
        flash('error', 'Inconsistência zootécnica: A matriz reprodutiva selecionada deve ser obrigatoriamente uma FÊMEA.');
        redirect('/reproducao/novo');
    }

    if ($touroBrinco) {
        if (strtoupper($matriz['brinco']) === strtoupper($touroBrinco)) {
            flash('error', 'Inconsistência: A matriz reprodutiva não pode ser o próprio touro da cobertura.');
            redirect('/reproducao/novo');
        }
        $tStmt = $db->prepare("SELECT id, sexo FROM animais WHERE UPPER(brinco) = UPPER(?)");
        $tStmt->execute([$touroBrinco]);
        $touro = $tStmt->fetch();
        if ($touro && $touro['sexo'] === 'F') {
            flash('error', 'Inconsistência zootécnica: O animal com brinco "'.$touroBrinco.'" é uma FÊMEA e não pode ser informado como touro reprodutor.');
            redirect('/reproducao/novo');
        }
    }

    $db->prepare("INSERT INTO reproducao (animal_id,tipo,data,resultado,touro_brinco,observacao) VALUES (?,?,?,?,?,?)")
       ->execute([$animalId,$_POST['tipo'],$_POST['data'],$_POST['resultado']?:null,$touroBrinco,trim($_POST['observacao']??'')?:null]);
    
    // Atualização automática de status da fêmea (prenha / parto / aborto / desmame)
    atualizarStatusAnimalPorReproducao($db, $animalId, $_POST['tipo'] ?? '', $_POST['resultado'] ?? null);

    flash('success','Evento registrado!');
    $back = !empty($_POST['animal_id']) ? "/animais/{$_POST['animal_id']}" : '/reproducao';
    redirect($back);
    exit;
}
if (preg_match('#^/reproducao/(\d+)(/.*)?$#', $uri, $m)) {
    $id  = (int)$m[1];
    $sub = $m[2] ?? '';
    $rStmt = $db->prepare("SELECT * FROM reproducao WHERE id=?");
    $rStmt->execute([$id]);
    $reproducao = $rStmt->fetch() ?: null;
    if (!$reproducao) { flash('error','Registro reprodutivo não encontrado.'); redirect('/reproducao'); }
    if ($sub === '/editar') {
        renderView('reproducao/form', 'Editar Evento Reprodutivo', 'reproducao', ['reproducao' => $reproducao]);
        exit;
    }
    if ($sub === '/atualizar' && $method === 'POST') {
        if (!csrf_verify()) { flash('error','Token inválido.'); redirect("/reproducao/$id/editar"); }
        
        $animalId = (int)($_POST['animal_id'] ?? 0);
        $touroBrinco = trim($_POST['touro_brinco'] ?? '') ?: null;

        $matrizStmt = $db->prepare("SELECT id, brinco, sexo FROM animais WHERE id = ?");
        $matrizStmt->execute([$animalId]);
        $matriz = $matrizStmt->fetch();
        if (!$matriz) {
            flash('error', 'Animal não encontrado para o manejo reprodutivo.');
            redirect("/reproducao/$id/editar");
        }
        if ($matriz['sexo'] !== 'F') {
            flash('error', 'Inconsistência zootécnica: A matriz reprodutiva selecionada deve ser obrigatoriamente uma FÊMEA.');
            redirect("/reproducao/$id/editar");
        }

        if ($touroBrinco) {
            if (strtoupper($matriz['brinco']) === strtoupper($touroBrinco)) {
                flash('error', 'Inconsistência: A matriz reprodutiva não pode ser o próprio touro da cobertura.');
                redirect("/reproducao/$id/editar");
            }
            $tStmt = $db->prepare("SELECT id, sexo FROM animais WHERE UPPER(brinco) = UPPER(?)");
            $tStmt->execute([$touroBrinco]);
            $touro = $tStmt->fetch();
            if ($touro && $touro['sexo'] === 'F') {
                flash('error', 'Inconsistência zootécnica: O animal com brinco "'.$touroBrinco.'" é uma FÊMEA e não pode ser informado como touro reprodutor.');
                redirect("/reproducao/$id/editar");
            }
        }

        $db->prepare("UPDATE reproducao SET animal_id=?,tipo=?,data=?,resultado=?,touro_brinco=?,observacao=? WHERE id=?")
           ->execute([$animalId,$_POST['tipo'],$_POST['data'],$_POST['resultado']?:null,$touroBrinco,trim($_POST['observacao']??'')?:null,$id]);

        // Atualização automática de status da fêmea
        atualizarStatusAnimalPorReproducao($db, $animalId, $_POST['tipo'] ?? '', $_POST['resultado'] ?? null);

        flash('success','Registro reprodutivo atualizado!');
        $back = !empty($_POST['animal_id']) ? "/animais/{$_POST['animal_id']}" : '/reproducao';
        redirect($back);
        exit;
    }
    if ($sub === '/excluir' && $method === 'POST') {
        if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/reproducao'); }
        $db->prepare("DELETE FROM reproducao WHERE id=?")->execute([$id]);
        flash('success','Registro excluído.');
        redirect($reproducao['animal_id'] ? "/animais/{$reproducao['animal_id']}" : '/reproducao');
        exit;
    }
}

// ── ALERTAS ──
if ($uri === '/alertas') {
    renderView('alertas/index', 'Alertas', 'alertas');
    exit;
}
if ($uri === '/alertas/ler-todos' && $method === 'POST') {
    if (!csrf_verify()) { flash('error', 'Token inválido.'); redirect('/alertas'); }
    $db->exec("UPDATE alertas SET lido=1 WHERE lido=0");
    flash('success','Todos os alertas foram marcados como lidos.');
    redirect('/alertas');
    exit;
}
if ($uri === '/alertas/novo') {
    renderView('alertas/form', 'Criar Alerta', 'alertas');
    exit;
}
if ($uri === '/alertas/salvar' && $method === 'POST') {
    if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/alertas/novo'); }
    $db->prepare("INSERT INTO alertas (animal_id,tipo,mensagem) VALUES (?,?,?)")
       ->execute([$_POST['animal_id']?:null,$_POST['tipo'],$_POST['mensagem']]);
    flash('success','Alerta criado!');
    redirect('/alertas');
    exit;
}
if (preg_match('#^/alertas/(\d+)/ler$#', $uri, $m) && $method === 'POST') {
    if (!csrf_verify()) { flash('error', 'Token inválido.'); redirect('/alertas'); }
    $db->prepare("UPDATE alertas SET lido=1 WHERE id=?")->execute([$m[1]]);
    flash('success','Alerta marcado como lido.');
    redirect('/alertas');
    exit;
}
if (preg_match('#^/alertas/(\d+)/excluir$#', $uri, $m) && $method === 'POST') {
    if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/alertas'); }
    $db->prepare("DELETE FROM alertas WHERE id=?")->execute([$m[1]]);
    flash('success','Alerta excluído.');
    redirect('/alertas');
    exit;
}

// ── COMPRAS DE GADO ──
if ($uri === '/compras') {
    renderView('compras/index', 'Compras de Gado', 'compras');
    exit;
}
if ($uri === '/compras/novo') {
    renderView('compras/form', 'Registrar Compra', 'compras');
    exit;
}
if ($uri === '/compras/salvar' && $method === 'POST') {
    if (!csrf_verify()) { flash('error', 'Token inválido. Tente novamente.'); redirect('/compras/novo'); }
    
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
        redirect('/compras/novo');
    }
    if ($valorTotal <= 0) {
        flash('error', 'O valor total da compra deve ser informado.');
        redirect('/compras/novo');
    }

    $arquivoXml = salvarUploadDocumento($_FILES['arquivo_xml'] ?? null, 'documentos');

    $stmt = $db->prepare("
        INSERT INTO compras (numero_gta, chave_nfe, arquivo_xml, fornecedor_origem, data_compra, quantidade_cabecas, peso_total_kg, valor_total, descricao, pasto_destino_id, criado_em)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$numeroGta, $chaveNfe ?: null, $arquivoXml, $fornecedor, $dataCompra, $qtdCabecas, $pesoTotal, $valorTotal, $descricao, $pastoDestinoId]);
    $compraId = (int)$db->lastInsertId();

    // Cadastro automático de animais do lote se habilitado
    if (!empty($_POST['cadastrar_animais'])) {
        $prefixo = trim($_POST['prefixo_brinco'] ?? 'C-') ?: 'C-';
        $raca = trim($_POST['raca_animais'] ?? 'Nelore') ?: 'Nelore';
        $sexo = in_array($_POST['sexo_animais'] ?? '', ['M', 'F']) ? $_POST['sexo_animais'] : 'M';

        $pesoIndiv = ($pesoTotal && $qtdCabecas > 0) ? round($pesoTotal / $qtdCabecas, 2) : null;
        $valorIndiv = $qtdCabecas > 0 ? round($valorTotal / $qtdCabecas, 2) : null;

        $stmtAnimal = $db->prepare("
            INSERT INTO animais (brinco, sexo, raca, status, pasto_id, peso_inicial, data_entrada, compra_id, valor_compra_individual, observacoes, criado_em)
            VALUES (?, ?, ?, 'ativo', ?, ?, ?, ?, ?, ?, NOW())
        ");

        $brincosInseridos = 0;
        $seq = 1;
        while ($brincosInseridos < $qtdCabecas && $seq <= ($qtdCabecas + 5000)) {
            $brincoGerado = $prefixo . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
            $chk = $db->prepare("SELECT id FROM animais WHERE brinco = ? LIMIT 1");
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
    redirect('/compras');
    exit;
}
if (preg_match('#^/compras/(\d+)/excluir$#', $uri, $m) && $method === 'POST') {
    if (!csrf_verify()) { flash('error', 'Token inválido.'); redirect('/compras'); }
    $id = (int)$m[1];
    $db->prepare("UPDATE animais SET compra_id = NULL, valor_compra_individual = NULL WHERE compra_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM compras WHERE id = ?")->execute([$id]);
    flash('success', 'Registro de compra excluído com sucesso.');
    redirect('/compras');
    exit;
}

// ── VENDAS DE GADO ──
if ($uri === '/vendas') {
    renderView('vendas/index', 'Vendas de Gado', 'vendas');
    exit;
}
if ($uri === '/vendas/novo') {
    renderView('vendas/form', 'Registrar Venda', 'vendas');
    exit;
}
if ($uri === '/vendas/salvar' && $method === 'POST') {
    if (!csrf_verify()) { flash('error', 'Token inválido. Tente novamente.'); redirect('/vendas/novo'); }

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
        redirect('/vendas/novo');
    }
    if (empty($comprador)) {
        flash('error', 'O comprador ou frigorífico de destino é obrigatório.');
        redirect('/vendas/novo');
    }
    if ($valorTotal <= 0) {
        flash('error', 'O valor total da venda deve ser informado.');
        redirect('/vendas/novo');
    }

    $arquivoXml = salvarUploadDocumento($_FILES['arquivo_xml'] ?? null, 'documentos');
    $qtdCabecas = (!empty($animaisIds) && is_array($animaisIds)) ? count($animaisIds) : max(1, (int)($_POST['quantidade_cabecas'] ?? 1));

    $stmt = $db->prepare("
        INSERT INTO vendas (numero_gta, chave_nfe, arquivo_xml, comprador_destino, data_venda, quantidade_cabecas, peso_total_kg, valor_total, preco_unitario, tipo_precificacao, descricao, criado_em)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$numeroGta, $chaveNfe ?: null, $arquivoXml, $comprador, $dataVenda, $qtdCabecas, $pesoTotal, $valorTotal, $precoUnitario, $tipoPrecificacao, $descricao]);
    $vendaId = (int)$db->lastInsertId();

    // Baixa comercial dos animais selecionados
    if (!empty($animaisIds) && is_array($animaisIds)) {
        $valorIndiv = $qtdCabecas > 0 ? round($valorTotal / $qtdCabecas, 2) : 0;
        $pesoIndiv = ($pesoTotal && $qtdCabecas > 0) ? round($pesoTotal / $qtdCabecas, 2) : null;

        $updAnimal = $db->prepare("
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
    redirect('/vendas');
    exit;
}
if (preg_match('#^/vendas/(\d+)/excluir$#', $uri, $m) && $method === 'POST') {
    if (!csrf_verify()) { flash('error', 'Token inválido.'); redirect('/vendas'); }
    $id = (int)$m[1];
    // Restaura animais vinculados para ativo
    $db->prepare("
        UPDATE animais 
        SET status = 'ativo',
            venda_id = NULL,
            valor_venda_individual = NULL,
            peso_venda = NULL,
            data_venda = NULL
        WHERE venda_id = ?
    ")->execute([$id]);

    $db->prepare("DELETE FROM vendas WHERE id = ?")->execute([$id]);
    flash('success', 'Venda estornada com sucesso! Os animais retornaram ao rebanho ativo.');
    redirect('/vendas');
    exit;
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
