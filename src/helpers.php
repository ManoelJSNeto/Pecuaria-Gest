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
        'ativo'     => 'bg-success text-white',
        'doente'    => 'bg-danger text-white',
        'vendido'   => 'bg-secondary text-white',
        'morto'     => 'bg-dark text-white border border-light border-opacity-50',
        'prenha'    => 'bg-info text-dark',
        'desmamado' => 'bg-warning text-dark',
    ];
    $classes = $map[$status] ?? 'bg-primary text-white';
    return '<span class="badge ' . $classes . '">' . ucfirst(e($status)) . '</span>';
}

function sexoLabel(string $sexo): string {
    return $sexo === 'M' ? 'Macho' : 'Fêmea';
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

// ── Configurações do Sistema e Notificações ──

function getSysConfig(string $chave, ?string $default = null): ?string {
    global $db;
    try {
        $stmt = $db->prepare("SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1");
        $stmt->execute([$chave]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setSysConfig(string $chave, ?string $valor): void {
    global $db;
    try {
        $db->prepare("DELETE FROM configuracoes WHERE chave = ?")->execute([$chave]);
        $db->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?)")->execute([$chave, $valor ?? '']);
    } catch (Exception $e) {}
}

require_once __DIR__ . '/mailer.php';

function sendNotificationEmail(string $destinatario, string $assunto, string $htmlCorpo, ?string &$errorMsg = null): bool {
    $fromEmail = getSysConfig('notif_smtp_from', 'sistema@pecuariagest.com.br');
    $fromName  = 'PecuáriaGest';

    $host       = getSysConfig('notif_smtp_host', '');
    $port       = (int)(getSysConfig('notif_smtp_port', '587') ?: 587);
    $user       = getSysConfig('notif_smtp_user', '');
    $pass       = getSysConfig('notif_smtp_pass', '');
    $encryption = getSysConfig('notif_smtp_secure', 'tls');

    $mailer = new PGLiteMailer($host, $port, $user, $pass, $encryption);
    $success = $mailer->send($fromEmail, $fromName, $destinatario, $assunto, $htmlCorpo);

    if (!$success) {
        $errorMsg = $mailer->getLastError();
    }

    return $success;
}

function notifyOwnerOnSyncEmail(array $processados, string $dispositivo): void {
    $enabled = getSysConfig('notif_email_enabled', '1');
    if ($enabled !== '1') return;

    $destinatario = getSysConfig('notif_email_destinatario', DEFAULT_ADMIN_EMAIL);
    if (empty($destinatario)) return;

    $total = array_sum($processados);
    if ($total === 0) return;

    $dataHora = date('d/m/Y \à\s H:i');
    $assunto = "[PecuáriaGest] Nova Coleta de Campo Sincronizada ({$total} registros)";

    $corpo = '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="font-family: Arial, sans-serif; background-color: #f4f6f4; margin: 0; padding: 20px; color: #2c3e2d;">
      <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <div style="background: linear-gradient(135deg, #1a4d2e 0%, #2d7a4e 100%); color: #ffffff; padding: 20px; text-align: center;">
          <h2 style="margin: 0; font-size: 22px; letter-spacing: -0.5px;">PecuáriaGest</h2>
          <p style="margin: 5px 0 0 0; opacity: 0.85; font-size: 14px;">Relatório Automático de Sincronização</p>
        </div>
        <div style="padding: 24px;">
          <p style="font-size: 16px; margin-top: 0;">Olá, <strong>Administrador</strong>!</p>
          <p style="font-size: 14px; color: #4b5563;">
            Uma nova coleta de dados foi descarregada no servidor central da fazenda:
          </p>
          
          <table style="width: 100%; border-collapse: collapse; margin: 20px 0; background: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb;">
            <tr style="border-bottom: 1px solid #e5e7eb;">
              <td style="padding: 10px 14px; font-weight: bold; width: 40%;">Origem:</td>
              <td style="padding: 10px 14px;">' . htmlspecialchars($dispositivo) . '</td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb;">
              <td style="padding: 10px 14px; font-weight: bold;">Horário:</td>
              <td style="padding: 10px 14px;">' . $dataHora . '</td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb;">
              <td style="padding: 10px 14px; font-weight: bold;">Pesagens:</td>
              <td style="padding: 10px 14px;"><strong style="color: #d97706;">' . ($processados['pesagens'] ?? 0) . '</strong> registros</td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb;">
              <td style="padding: 10px 14px; font-weight: bold;">Novos Bezerros:</td>
              <td style="padding: 10px 14px;"><strong style="color: #16a34a;">' . ($processados['animais_novos'] ?? 0) . '</strong> cadastrados</td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb;">
              <td style="padding: 10px 14px; font-weight: bold;">Manejos Sanitários:</td>
              <td style="padding: 10px 14px;"><strong style="color: #dc2626;">' . ($processados['saude'] ?? 0) . '</strong> lançamentos</td>
            </tr>
            <tr>
              <td style="padding: 10px 14px; font-weight: bold;">Fotos Vinculadas:</td>
              <td style="padding: 10px 14px;"><strong>' . ($processados['fotos'] ?? 0) . '</strong> fotos salvas</td>
            </tr>
          </table>

          <div style="text-align: center; margin-top: 25px;">
            <a href="http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080') . '/sincronizacoes" style="background: #1a4d2e; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; display: inline-block;">
              Acessar Painel de Controle
            </a>
          </div>
        </div>
        <div style="background: #f3f4f6; padding: 12px; text-align: center; font-size: 12px; color: #6b7280;">
          PecuáriaGest — Sistema de Gestão Agropecuária Integrada
        </div>
      </div>
    </body>
    </html>';

    sendNotificationEmail($destinatario, $assunto, $corpo);
}
