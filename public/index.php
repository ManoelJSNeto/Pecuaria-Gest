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

    // ── API REST & Sincronização Mobile (ApiController) ──
    if ($uri === '/api/sync' && $method === 'POST') {
        (new ApiController())->sync();
        exit;
    }
    if ($uri === '/api/animais' && $method === 'GET') {
        (new ApiController())->animais();
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
// Dashboard (DashboardController)
if ($uri === '/dashboard') {
    (new DashboardController())->index();
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
    exit;
}
if ($uri === '/compras/novo') {
    (new ComercialController())->comprasNovo();
    exit;
}
if ($uri === '/compras/salvar' && $method === 'POST') {
    (new ComercialController())->comprasSalvar();
    exit;
}
if (preg_match('#^/compras/(\d+)/excluir$#', $uri, $m) && $method === 'POST') {
    (new ComercialController())->comprasExcluir((int)$m[1]);
    exit;
}

if ($uri === '/vendas') {
    (new ComercialController())->vendasIndex();
    exit;
}
if ($uri === '/vendas/novo') {
    (new ComercialController())->vendasNovo();
    exit;
}
if ($uri === '/vendas/salvar' && $method === 'POST') {
    (new ComercialController())->vendasSalvar();
    exit;
}
if (preg_match('#^/vendas/(\d+)/excluir$#', $uri, $m) && $method === 'POST') {
    (new ComercialController())->vendasExcluir((int)$m[1]);
    exit;
}

// ── MÓDULO RELATÓRIOS & SINCRONIZAÇÕES (RelatoriosController) ──
if ($uri === '/relatorios') {
    (new RelatoriosController())->index();
    exit;
}
if ($uri === '/sincronizacoes') {
    (new RelatoriosController())->sincronizacoes();
    exit;
}

// ── MÓDULO CONFIGURAÇÕES & NOTIFICAÇÕES (ConfiguracoesController) ──
if ($uri === '/configuracoes') {
    (new ConfiguracoesController())->index();
    exit;
}
if ($uri === '/configuracoes/salvar' && $method === 'POST') {
    (new ConfiguracoesController())->salvar();
    exit;
}
if ($uri === '/configuracoes/testar-email' && $method === 'POST') {
    (new ConfiguracoesController())->testarEmail();
    exit;
}

// ── MÓDULO GESTÃO DE USUÁRIOS & RBAC (UsuariosController) ──
if ($uri === '/usuarios') {
    (new UsuariosController())->index();
    exit;
}
if ($uri === '/usuarios/novo') {
    (new UsuariosController())->novo();
    exit;
}
if ($uri === '/usuarios/criar' && $method === 'POST') {
    (new UsuariosController())->criar();
    exit;
}
if (preg_match('#^/usuarios/(\d+)/editar$#', $uri, $m)) {
    (new UsuariosController())->editar((int)$m[1]);
    exit;
}
if (preg_match('#^/usuarios/(\d+)/salvar$#', $uri, $m) && $method === 'POST') {
    (new UsuariosController())->salvar((int)$m[1]);
    exit;
}
if (preg_match('#^/usuarios/(\d+)/toggle-status$#', $uri, $m) && $method === 'POST') {
    (new UsuariosController())->toggleStatus((int)$m[1]);
    exit;
}
if (preg_match('#^/usuarios/(\d+)/excluir$#', $uri, $m) && $method === 'POST') {
    (new UsuariosController())->excluir((int)$m[1]);
    exit;
}

// 404
http_response_code(404);
echo '<div style="font-family:sans-serif;text-align:center;padding:4rem;color:#666">
  <h1 style="color:#1a4d2e;font-size:3rem">404</h1>
  <p>Página não encontrada.</p>
  <a href="/dashboard" style="color:#2d7a4e">← Voltar ao início</a>
</div>';
