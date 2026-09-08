<?php
require_once __DIR__ . '/BaseController.php';

/**
 * PecuáriaGest — AuthController
 *
 * Controlador responsável pelo ciclo de vida da autenticação:
 * exibição da tela de login, validação de credenciais, proteção contra
 * força bruta (Rate Limiting Lockout) e encerramento de sessão.
 */
class AuthController extends BaseController {

    /**
     * Exibe a tela de login (GET /login ou GET /)
     */
    public function showLogin(): void {
        if (isLoggedIn()) {
            $this->redirect('/dashboard');
        }

        $error = '';
        $lockoutUntil = $_SESSION['login_lockout_until'] ?? 0;
        if ($lockoutUntil > time()) {
            $tempoRestante = ceil(($lockoutUntil - time()) / 60);
            $error = "Muitas tentativas incorretas consecutivas. Por segurança, aguarde {$tempoRestante} minuto(s) antes de tentar novamente.";
        }

        require __DIR__ . '/../views/login.php';
        exit;
    }

    /**
     * Processa a tentativa de autenticação (POST /login)
     */
    public function login(): void {
        if (isLoggedIn()) {
            $this->redirect('/dashboard');
        }

        $error = '';
        $lockoutUntil = $_SESSION['login_lockout_until'] ?? 0;

        // 1. Verificação de Bloqueio por Força Bruta
        if ($lockoutUntil > time()) {
            $tempoRestante = ceil(($lockoutUntil - time()) / 60);
            $error = "Muitas tentativas incorretas consecutivas. Por segurança, aguarde {$tempoRestante} minuto(s) antes de tentar novamente.";
            require __DIR__ . '/../views/login.php';
            exit;
        }

        // 2. Validação do Token Anti-CSRF
        if (!$this->validateCsrf()) {
            $error = 'Token de segurança expirado ou inválido. Recarregue a página.';
            require __DIR__ . '/../views/login.php';
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $senha = (string)($_POST['senha'] ?? '');

        // 3. Validação de preenchimento
        if (empty($email) || empty($senha)) {
            $error = 'Por favor, preencha o e-mail e a senha.';
            require __DIR__ . '/../views/login.php';
            exit;
        }

        // 4. Tentativa de Login no banco de dados com Bcrypt
        if (attemptLogin($email, $senha)) {
            // Zera o contador de tentativas após sucesso
            unset($_SESSION['login_attempts'], $_SESSION['login_lockout_until']);
            $this->redirect('/dashboard');
        }

        // 5. Tratamento de Falha e Incremento de Tentativas
        $attempts = ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['login_attempts'] = $attempts;

        if ($attempts >= 5) {
            $_SESSION['login_lockout_until'] = time() + 180; // Bloqueio temporário de 3 minutos
            $error = 'Limite de tentativas incorretas atingido. Acesso bloqueado temporariamente por 3 minutos.';
        } else {
            $restantes = 5 - $attempts;
            $error = "E-mail ou senha incorretos. ({$restantes} tentativa(s) restante(s))";
        }

        require __DIR__ . '/../views/login.php';
        exit;
    }

    /**
     * Encerra a sessão do usuário (GET /logout)
     */
    public function logout(): void {
        logout();
        $this->redirect('/login');
    }
}
