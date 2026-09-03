<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — PecuáriaGest</title>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">

<!-- Tipografia Técnica -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
<link href="/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="text-center mb-3">
      <img src="/favicon.svg" alt="PecuáriaGest Logo" class="brand-logo mb-2">
      <h4 class="mb-0 fw-800" style="color:var(--earth-green-950); letter-spacing:-0.03em;">PecuáriaGest</h4>
      <p class="text-secondary small mb-3">Gestão Integrada de Precisão</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger py-2 px-3 small mb-3" role="alert" style="border-radius: var(--radius-sm);">
        <i class="bi bi-exclamation-circle-fill me-1"></i> <?= e($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="/login">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">E-mail de Acesso</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" class="form-control" placeholder="colaborador@fazenda.com" required
                 value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email">
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label">Senha</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="senha" class="form-control" placeholder="••••••••" required autocomplete="current-password">
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
        <i class="bi bi-box-arrow-in-right me-2"></i>Entrar no Painel
      </button>
    </form>

    <hr class="my-3" style="border-color: var(--border-subtle);">
    <div class="text-center text-muted small">
      <i class="bi bi-info-circle me-1"></i>
      Padrão: <code>admin@fazenda.com</code> / <code>admin123</code>
    </div>
  </div>
</div>
</body>
</html>
