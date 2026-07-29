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
    if ($apiKey !== 'pecuaria-mobile-key') {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    if ($uri === '/api/sync' && $method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $db = getDb();
        $processados = ['pesagens' => 0, 'saude' => 0, 'animais_novos' => 0];
        $erros = [];

        // Process new animals
        foreach ($body['animais_novos'] ?? [] as $an) {
            try {
                $stmt = $db->prepare("INSERT OR IGNORE INTO animais (brinco,sexo,raca,data_nascimento,nome,origem) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$an['brinco']??null,$an['sexo']??'M',$an['raca']??null,$an['data_nascimento']??null,$an['nome']??null,'mobile']);
                if ($db->lastInsertId()) $processados['animais_novos']++;
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
                }
            } catch (Exception $e) { $erros[] = 'saude:'.$e->getMessage(); }
        }

        // Log sync
        $total = array_sum($processados);
        $db->prepare("INSERT INTO sincronizacoes (dispositivo,ip,dados_recebidos,status,detalhes) VALUES (?,?,?,?,?)")
           ->execute([$body['dispositivo']??'desconhecido',$_SERVER['REMOTE_ADDR']??'',$total,'ok',json_encode($processados)]);

        echo json_encode(['status'=>'ok','processados'=>$processados,'erros'=>$erros]);
        exit;
    }

    if ($uri === '/api/animais' && $method === 'GET') {
        $db = getDb();
        $animais = $db->query("SELECT a.*, p.nome as pasto_nome, (SELECT peso FROM pesagens WHERE animal_id=a.id ORDER BY data DESC LIMIT 1) as peso_atual FROM animais a LEFT JOIN pastagens p ON a.pasto_id=p.id WHERE a.status NOT IN('vendido','morto') ORDER BY a.brinco")->fetchAll();
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
function renderView(string $view, string $title, string $page, ?string $scripts = null): void {
    global $db;
    $pageTitle   = $title;
    $currentPage = $page;
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
    $stmt = $db->prepare("INSERT INTO animais (brinco,nome,sexo,raca,data_nascimento,peso_inicial,status,pasto_id,origem,mae_id,pai_brinco,observacao) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
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
        ]);
        flash('success', 'Animal cadastrado com sucesso!');
        redirect('/animais/' . $db->lastInsertId());
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
        renderView('animais/show', 'Animal #'.$animal['brinco'], 'animais');
        exit;
    }
    if ($sub === '/editar') {
        renderView('animais/form', 'Editar Animal', 'animais');
        exit;
    }
    if ($sub === '/atualizar' && $method === 'POST') {
        if (!csrf_verify()) { flash('error','Token inválido.'); redirect("/animais/$id/editar"); }
        $db->prepare("UPDATE animais SET brinco=?,nome=?,sexo=?,raca=?,data_nascimento=?,peso_inicial=?,status=?,pasto_id=?,origem=?,mae_id=?,pai_brinco=?,observacao=?,updated_at=datetime('now') WHERE id=?")
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
if (preg_match('#^/pesagens/(\d+)/excluir$#', $uri, $m) && $method === 'POST') {
    if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/pesagens'); }
    $pStmt = $db->prepare("SELECT animal_id FROM pesagens WHERE id=?");
    $pStmt->execute([$m[1]]);
    $p = $pStmt->fetch();
    $db->prepare("DELETE FROM pesagens WHERE id=?")->execute([$m[1]]);
    flash('success','Pesagem excluída.');
    redirect($p ? "/animais/{$p['animal_id']}" : '/pesagens');
    exit;
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
    redirect('/saude');
    exit;
}
if (preg_match('#^/saude/(\d+)/excluir$#', $uri, $m) && $method === 'POST') {
    if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/saude'); }
    $db->prepare("DELETE FROM saude WHERE id=?")->execute([$m[1]]);
    flash('success','Registro excluído.');
    redirect('/saude');
    exit;
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
    if ($sub === '/editar') { renderView('pastagens/form', 'Editar Pastagem', 'pastagens'); exit; }
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
    redirect('/reproducao');
    exit;
}
if (preg_match('#^/reproducao/(\d+)/excluir$#', $uri, $m) && $method === 'POST') {
    if (!csrf_verify()) { flash('error','Token inválido.'); redirect('/reproducao'); }
    $db->prepare("DELETE FROM reproducao WHERE id=?")->execute([$m[1]]);
    flash('success','Registro excluído.');
    redirect('/reproducao');
    exit;
}

// ── ALERTAS ──
if ($uri === '/alertas') {
    renderView('alertas/index', 'Alertas', 'alertas');
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
        $rows = $db->query("SELECT a.brinco,s.tipo,s.descricao,s.data,s.medicamento,s.dose,s.veterinario FROM saude s JOIN animais a ON s.animal_id=a.id ORDER BY s.data DESC")->fetchAll();
        echo "Brinco,Tipo,Descrição,Data,Medicamento,Dose,Veterinário\n";
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
