<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — PecuáriaGest</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-brand">🐄</div>
    <h4 class="text-center mb-1" style="color:#1a4d2e;font-weight:800">PecuáriaGest</h4>
    <p class="text-center text-muted small mb-4">Sistema de Gestão Pecuária Individual</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/login">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">E-mail</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" class="form-control" placeholder="seu@email.com" required
                 value="<?= e($_POST['email'] ?? '') ?>">
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label">Senha</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="senha" class="form-control" placeholder="••••••••" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-100 py-2 fw-600">
        <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
      </button>
    </form>

    <hr class="my-3">
    <p class="text-center text-muted small mb-0">
      <i class="bi bi-info-circle me-1"></i>
      Padrão: <code>admin@fazenda.com</code> / <code>admin123</code>
    </p>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
