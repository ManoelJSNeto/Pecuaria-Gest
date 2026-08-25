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
        if ($diff->y >= 1) return $diff->y . ' ano' . ($diff->y > 1 ? 's' : '') . ($diff->m > 0 ? ' e ' . $diff->m . ' m' : '');
        if ($diff->m >= 1) return $diff->m . ' mês' . ($diff->m > 1 ? 'es' : '');
        return $diff->d . ' dia' . ($diff->d > 1 ? 's' : '');
    } catch (Exception $e) {
        return '-';
    }
}

function calcIdadeMeses(?string $dataNasc): int {
    if (!$dataNasc) return 999;
    try {
        $nasc = new DateTime($dataNasc);
        $hoje = new DateTime();
        $diff = $hoje->diff($nasc);
        return ($diff->y * 12) + $diff->m;
    } catch (Exception $e) {
        return 999;
    }
}

function isFilhote(?string $dataNasc): bool {
    return calcIdadeMeses($dataNasc) <= 12;
}

function uploadFoto(array $file, string $subfolder = 'fotos'): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowed, true)) {
        return null;
    }
    
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];
    $ext = $extMap[$mime] ?? 'jpg';
    $filename = uniqid('foto_', true) . '.' . $ext;
    
    $targetDir = UPLOADS_PATH . '/' . trim($subfolder, '/');
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0775, true);
    }
    
    $targetFile = $targetDir . '/' . $filename;
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return '/uploads/' . trim($subfolder, '/') . '/' . $filename;
    }
    
    return null;
}

function salvarBase64Foto(string $base64Data, string $subfolder = 'fotos'): ?string {
    if (empty($base64Data)) return null;
    
    // Support data:image/jpeg;base64,....
    $ext = 'jpg';
    if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $m)) {
        $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
    }
    
    $decoded = base64_decode($base64Data);
    if ($decoded === false) return null;
    
    $filename = uniqid('pwa_', true) . '.' . $ext;
    $targetDir = UPLOADS_PATH . '/' . trim($subfolder, '/');
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0775, true);
    }
    
    $targetFile = $targetDir . '/' . $filename;
    if (file_put_contents($targetFile, $decoded) !== false) {
        return '/uploads/' . trim($subfolder, '/') . '/' . $filename;
    }
    
    return null;
}
