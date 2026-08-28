<?php
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect('/login');
    }
}

function requireAuth(): void {
    requireLogin();
}

function attemptLogin(string $email, string $senha): bool {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($senha, $user['senha'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = $user;
        $db->prepare("UPDATE usuarios SET ultimo_acesso = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user['id']]);
        return true;
    }
    return false;
}

function logout(): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

// ── Motor de Permissões Granulares (RBAC / ABAC) ──

function getUserPermissions(?array $user = null): array {
    $u = $user ?? currentUser();
    if (!$u) return [];
    if (($u['tipo'] ?? '') === 'admin') {
        return ['*' => true]; // Acesso irrestrito a todos os recursos
    }
    $raw = $u['permissoes'] ?? '{}';
    if (is_array($raw)) return $raw;
    return json_decode($raw, true) ?: [];
}

function can(string $permission, ?array $user = null): bool {
    $u = $user ?? currentUser();
    if (!$u) return false;
    
    // Administrador mestre tem acesso irrestrito
    if (($u['tipo'] ?? '') === 'admin') {
        return true;
    }

    $perms = getUserPermissions($u);
    if (!empty($perms['*'])) return true;

    return !empty($perms[$permission]);
}

function requirePermission(string $permission): void {
    requireLogin();
    if (!can($permission)) {
        flash('error', 'Acesso restrito: você não possui permissão para acessar esta área ou executar esta ação.');
        redirect('/dashboard');
    }
}
