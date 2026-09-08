<?php
/**
 * PecuáriaGest — BaseController
 *
 * Classe base abstrata para todos os controladores da aplicação.
 * Fornece métodos utilitários padronizados para renderização, respostas JSON,
 * redirecionamentos e verificações de segurança/permissões.
 */
abstract class BaseController {
    protected PDO $db;

    public function __construct() {
        $this->db = getDb();
    }

    /**
     * Renderiza uma view injetando os dados no layout mestre (SSR).
     */
    protected function render(string $view, string $title, string $page, array $data = [], ?string $scripts = null): void {
        renderView($view, $title, $page, $data, $scripts);
    }

    /**
     * Retorna uma resposta padronizada em JSON (para APIs ou chamadas AJAX).
     */
    protected function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    /**
     * Redireciona o navegador para uma rota e encerra o fluxo.
     */
    protected function redirect(string $url): void {
        redirect($url);
        exit;
    }

    /**
     * Valida o token CSRF em formulários POST.
     */
    protected function validateCsrf(): bool {
        return csrf_verify();
    }

    /**
     * Exige que o usuário esteja autenticado.
     */
    protected function requireLogin(): void {
        requireLogin();
    }

    /**
     * Exige uma permissão granular específica do sistema RBAC.
     */
    protected function requirePermission(string $permission): void {
        requirePermission($permission);
    }
}
