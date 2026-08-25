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

// ── Public routes ──────────────────────────────
if ($uri === '/' || $uri === '/login') {
    if (isLoggedIn()) { redirect('/dashboard'); }
    $error = '';
    if ($method === 'POST') {
        if (!csrf_verify()) { $error = 'Token inválido. Recarregue a página.'; }
        else {
            $email = trim($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';
            if (attemptLogin($email, $senha)) { redirect('/dashboard'); }
            else { $error = 'E-mail ou senha incorretos.'; }
        }
    }
    require __DIR__ . '/../src/views/login.php';
    exit;
}

if ($uri === '/logout') {
    logout();
    redirect('/login');
}

// ── API Endpoint (for mobile app) ──────────────
if (str_starts_with($uri, '/api/')) {
    header('Content-Type: application/json');
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($apiKey !== API_KEY) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    if ($uri === '/api/sync' && $method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $db = getDb();
        $processados = ['pesagens' => 0, 'saude' => 0, 'animais_novos' => 0, 'fotos' => 0];
        $erros = [];

        // Process new animals
        foreach ($body['animais_novos'] ?? [] as $an) {
            try {
                $stmt = $db->prepare("INSERT INTO animais (brinco,sexo,raca,data_nascimento,nome,origem,status) VALUES (?,?,?,?,?,?,'ativo')");
                $stmt->execute([$an['brinco']??null,$an['sexo']??'M',$an['raca']??null,$an['data_nascimento']??null,$an['nome']??null,'mobile']);
                $aid = $db->lastInsertId();
                if ($aid) {
                    $processados['animais_novos']++;
                    if (!empty($an['foto_base64'])) {
                        $fUrl = salvarBase64Foto($an['foto_base64']);
                        if ($fUrl) {
                            $db->prepare("UPDATE animais SET foto_url=? WHERE id=?")->execute([$fUrl, $aid]);
                            $db->prepare("INSERT INTO fotos_animais (animal_id,foto_url,tipo_evento,fase,data,observacao) VALUES (?,?,'nascimento','filhote',?,?)")
                               ->execute([$aid, $fUrl, $an['data_nascimento'] ?? date('Y-m-d'), 'Foto de nascimento (Mobile/PWA)']);
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
                               ->execute([$aid, $fUrl, $p['data'] ?? date('Y-m-d'), 'Pesagem ' . ($p['peso']??'') . 'kg (PWA)']);
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

                    $isObito = strtolower($s['tipo'] ?? '') === 'óbito' || strtolower($s['tipo'] ?? '') === 'obito';
                    if ($isObito) {
                        $db->prepare("UPDATE animais SET status='morto' WHERE id=?")->execute([$aid]);
                    }

                    if (!empty($s['foto_base64'])) {
                        $fUrl = salvarBase64Foto($s['foto_base64']);
                        if ($fUrl) {
                            $isSensivel = ($isObito || !empty($s['is_sensivel'])) ? 1 : 0;
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

        echo json_encode(['status'=>$status,'processados'=>$processados,'erros'=>$erros]);
        exit;
    }

    if ($uri === '/api/animais' && $method === 'GET') {
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

// ── Protected routes ───────────────────────────
requireLogin();
$db = getDb();

// Helper to render a view with layout
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

// ── Routing ────────────────────────────────────
// Dashboard
if ($uri === '/dashboard') {
    renderView('dashboard', 'Dashboard', 'dashboard');
    exit;
}

// ── PWA / MODO CAMPO ──
if ($uri === '/campo' || $uri === '/mobile') {
    renderView('campo/index', 'Modo Campo (PWA)', 'campo');
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
    $f = $db->query("SELECT animal_id, foto_url FROM fotos_animais WHERE id=$fId")->fetch();
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
    $db->prepare("INSERT INTO saude (animal_id,tipo,descricao,data,proxima_data,custo,medicamento,dose,veterinario,observacao,origem) VALUES (?,?,?,?,?,?,?,?,?,?,'web')")
       ->execute([
           $_POST['animal_id'],
           $_POST['tipo'],
           $_POST['descricao'],
           $_POST['data'],
           $_POST['proxima_data'] ?: null,
           $_POST['custo'] ?: null,
           $_POST['medicamento'] ?: null,
           $_POST['dose'] ?: null,
           $_POST['veterinario'] ?: null,
           trim($_POST['observacao'] ?? '') ?: null,
       ]);
    flash('success','Evento de saúde registrado!');
    $back = !empty($_POST['animal_id']) ? "/animais/{$_POST['animal_id']}" : '/saude';
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
        $db->prepare("UPDATE saude SET animal_id=?,tipo=?,descricao=?,data=?,proxima_data=?,custo=?,medicamento=?,dose=?,veterinario=?,observacao=? WHERE id=?")
           ->execute([
               $_POST['animal_id'],
               $_POST['tipo'],
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
        flash('success','Evento de saúde atualizado!');
        $back = !empty($_POST['animal_id']) ? "/animais/{$_POST['animal_id']}" : '/saude';
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
    $db->prepare("INSERT INTO reproducao (animal_id,tipo,data,resultado,touro_brinco,observacao) VALUES (?,?,?,?,?,?)")
       ->execute([$_POST['animal_id'],$_POST['tipo'],$_POST['data'],$_POST['resultado']?:null,$_POST['touro_brinco']?:null,trim($_POST['observacao']??'')?:null]);
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
        $db->prepare("UPDATE reproducao SET animal_id=?,tipo=?,data=?,resultado=?,touro_brinco=?,observacao=? WHERE id=?")
           ->execute([$_POST['animal_id'],$_POST['tipo'],$_POST['data'],$_POST['resultado']?:null,$_POST['touro_brinco']?:null,trim($_POST['observacao']??'')?:null,$id]);
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
if ($uri === '/alertas/ler-todos') {
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
if (preg_match('#^/alertas/(\d+)/ler$#', $uri, $m)) {
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

// ── RELATÓRIOS ──
if ($uri === '/relatorios') {
    $export = $_GET['export'] ?? '';
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

// 404
http_response_code(404);
echo '<div style="font-family:sans-serif;text-align:center;padding:4rem;color:#666">
  <h1 style="color:#1a4d2e;font-size:3rem">404</h1>
  <p>Página não encontrada.</p>
  <a href="/dashboard" style="color:#2d7a4e">← Voltar ao início</a>
</div>';
