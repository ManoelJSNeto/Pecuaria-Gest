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

function attemptLogin(string $email, string $senha): bool {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($senha, $user['senha'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = $user;
        $db->prepare("UPDATE usuarios SET ultimo_acesso = datetime('now') WHERE id = ?")->execute([$user['id']]);
        return true;
    }
    return false;
}

function logout(): void {
    session_destroy();
}
