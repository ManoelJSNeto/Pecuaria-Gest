<?php
function redirect(string $path): void {
    header("Location: $path");
    exit;
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function e(mixed $str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool {
    return isset($_POST['_csrf']) && hash_equals(csrf_token(), $_POST['_csrf']);
}

function formatDate(?string $date): string {
    if (!$date) return '-';
    try {
        $d = new DateTime($date);
        return $d->format('d/m/Y');
    } catch (Exception $e) {
        return '-';
    }
}

function formatDateTime(?string $date): string {
    if (!$date) return '-';
    try {
        $d = new DateTime($date);
        return $d->format('d/m/Y H:i');
    } catch (Exception $e) {
        return '-';
    }
}

function statusBadge(string $status): string {
    $map = [
        'ativo'     => 'success',
        'doente'    => 'danger',
        'vendido'   => 'secondary',
        'morto'     => 'dark',
        'prenha'    => 'info',
        'desmamado' => 'warning',
    ];
    $color = $map[$status] ?? 'primary';
    return '<span class="badge bg-' . $color . '">' . ucfirst(e($status)) . '</span>';
}

function sexoLabel(string $sexo): string {
    return $sexo === 'M' ? '♂ Macho' : '♀ Fêmea';
}

function calcIdade(?string $dataNasc): string {
    if (!$dataNasc) return '-';
    try {
        $nasc = new DateTime($dataNasc);
        $hoje = new DateTime();
        $diff = $hoje->diff($nasc);
        if ($diff->y >= 1) return $diff->y . ' ano' . ($diff->y > 1 ? 's' : '');
        if ($diff->m >= 1) return $diff->m . ' mês' . ($diff->m > 1 ? 'es' : '');
        return $diff->d . ' dia' . ($diff->d > 1 ? 's' : '');
    } catch (Exception $e) {
        return '-';
    }
}
