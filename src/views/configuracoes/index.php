<?php
$emailEnabled      = getSysConfig('notif_email_enabled', '1');
$emailDestinatario = getSysConfig('notif_email_destinatario', DEFAULT_ADMIN_EMAIL);
$emailFrom         = getSysConfig('notif_smtp_from', 'sistema@pecuariagest.com.br');
$smtpHost          = getSysConfig('notif_smtp_host', '');
$smtpPort          = getSysConfig('notif_smtp_port', '587');
$smtpUser          = getSysConfig('notif_smtp_user', '');
$smtpPassSaved     = !empty(getSysConfig('notif_smtp_pass', ''));
$smtpSecure        = getSysConfig('notif_smtp_secure', 'tls');

$totalAnimais      = $db->query("SELECT COUNT(*) FROM animais WHERE status != 'morto' AND status != 'vendido'")->fetchColumn();
$totalPesagens     = $db->query("SELECT COUNT(*) FROM pesagens")->fetchColumn();
$totalSincs        = $db->query("SELECT COUNT(*) FROM sincronizacoes")->fetchColumn();
$dbDriver          = 'PostgreSQL 16 (Dedicado / Docker)';
?>

<div class="row g-4">
  <!-- Coluna da Esquerda: Configurações de Notificação & SMTP -->
  <div class="col-lg-7">
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between border-bottom">
        <div class="d-flex align-items-center gap-2">
          <div class="rounded p-2 bg-primary bg-opacity-10 text-primary">
            <i class="bi bi-envelope-at-fill fs-5"></i>
          </div>
          <div>
            <h6 class="mb-0 fw-bold">Notificações por E-mail & Servidor SMTP</h6>
            <small class="text-muted">Configure o servidor de envio para relatórios de sincronização</small>
          </div>
        </div>
        <span class="badge bg-<?= $emailEnabled === '1' ? 'success' : 'secondary' ?>">
          <?= $emailEnabled === '1' ? 'Ativo' : 'Desativado' ?>
        </span>
      </div>

      <div class="card-body p-4">
        <form method="POST" action="/configuracoes/salvar">
          <?= csrf_field() ?>

          <div class="form-check form-switch mb-4 p-3 bg-light rounded-3 border">
            <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" id="notif_email_enabled" name="notif_email_enabled" value="1" <?= $emailEnabled === '1' ? 'checked' : '' ?> style="width: 2.2em; height: 1.2em;">
            <label class="form-check-label fw-bold" for="notif_email_enabled">
              Receber resumo por e-mail a cada sincronização de campo
            </label>
            <div class="text-muted small ps-5 mt-1">
              Dispara automaticamente um relatório formatado com as pesagens, novos bezerros e manejos recebidos.
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold small">E-mail do Proprietário / Gerente (Destinatário) *</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
              <input type="email" name="notif_email_destinatario" class="form-control" value="<?= e($emailDestinatario) ?>" required placeholder="proprietario@fazenda.com.br">
            </div>
            <small class="text-muted">Endereço que receberá os alertas imediatos da fazenda.</small>
          </div>

          <div class="mb-4">
            <label class="form-label fw-bold small">E-mail Remetente do Sistema (From)</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-send-fill"></i></span>
              <input type="email" name="notif_smtp_from" id="smtp_from" class="form-control" value="<?= e($emailFrom) ?>" placeholder="sistema@pecuariagest.com.br">
            </div>
            <small class="text-muted">E-mail de envio cadastrado no provedor SMTP.</small>
          </div>

          <!-- Seção de Servidor SMTP Autenticado -->
          <div class="border rounded-3 p-3 bg-light bg-opacity-50 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
              <h6 class="fw-bold mb-0 text-success d-flex align-items-center gap-1" style="font-size:0.9rem;">
                <i class="bi bi-server"></i> Servidor de Envio SMTP (Gmail, SES, Outlook, Mailtrap)
              </h6>
              <!-- Presets Rápidos -->
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary py-0" onclick="applySmtpPreset('gmail')">Gmail</button>
                <button type="button" class="btn btn-outline-secondary py-0" onclick="applySmtpPreset('outlook')">Outlook</button>
                <button type="button" class="btn btn-outline-secondary py-0" onclick="applySmtpPreset('mailtrap')">Mailtrap</button>
                <button type="button" class="btn btn-outline-secondary py-0" onclick="applySmtpPreset('aws')">AWS SES</button>
              </div>
            </div>
            <p class="text-muted small mb-3">
              Permite o disparo real de e-mails em ambiente local (XAMPP) e na nuvem AWS sem depender do sendmail do Windows.
            </p>

            <div class="row g-2 mb-2">
              <div class="col-md-8">
                <label class="form-label small fw-bold">Servidor SMTP (Host)</label>
                <input type="text" name="notif_smtp_host" id="smtp_host" class="form-control form-control-sm" value="<?= e($smtpHost) ?>" placeholder="Ex: smtp.gmail.com">
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-bold">Porta</label>
                <input type="number" name="notif_smtp_port" id="smtp_port" class="form-control form-control-sm" value="<?= e($smtpPort) ?>" placeholder="587">
              </div>
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-6">
                <label class="form-label small fw-bold">Usuário / E-mail de Login</label>
                <input type="text" name="notif_smtp_user" id="smtp_user" class="form-control form-control-sm" value="<?= e($smtpUser) ?>" placeholder="seu-email@gmail.com">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold">
                  Senha SMTP <?= $smtpPassSaved ? '<span class="badge bg-success bg-opacity-10 text-success fw-normal">Salva</span>' : '' ?>
                </label>
                <input type="password" name="notif_smtp_pass" class="form-control form-control-sm" placeholder="<?= $smtpPassSaved ? 'Deixe em branco para manter' : 'Senha ou Senha de App' ?>" autocomplete="new-password">
              </div>
            </div>

            <div class="row g-2">
              <div class="col-md-12">
                <label class="form-label small fw-bold">Criptografia</label>
                <select name="notif_smtp_secure" id="smtp_secure" class="form-select form-select-sm">
                  <option value="tls" <?= $smtpSecure === 'tls' ? 'selected' : '' ?>>TLS / STARTTLS (Porta 587 - Recomendado)</option>
                  <option value="ssl" <?= $smtpSecure === 'ssl' ? 'selected' : '' ?>>SSL (Porta 465)</option>
                  <option value="none" <?= $smtpSecure === 'none' ? 'selected' : '' ?>>Nenhuma (Servidores locais / Mailtrap porta 2525)</option>
                </select>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
            <button type="submit" class="btn btn-primary fw-bold px-4">
              <i class="bi bi-check-lg me-1"></i> Salvar Configurações
            </button>
            <button type="button" class="btn btn-outline-success btn-sm fw-bold"
              onclick="document.getElementById('formTestarEmail').submit()">
              <i class="bi bi-envelope-paper-fill me-1"></i> Enviar E-mail de Teste
            </button>
          </div>
        </form>
        <form id="formTestarEmail" method="POST" action="/configuracoes/testar-email" class="d-none">
          <?= csrf_field() ?>
        </form>
      </div>
    </div>
  </div>

  <script>
  function applySmtpPreset(type) {
    const host = document.getElementById('smtp_host');
    const port = document.getElementById('smtp_port');
    const secure = document.getElementById('smtp_secure');
    
    if (type === 'gmail') {
      host.value = 'smtp.gmail.com';
      port.value = '587';
      secure.value = 'tls';
      alert("Configuração do Gmail selecionada!\n\nNota: No Gmail, utilize seu endereço de e-mail e gere uma 'Senha de Aplicativo' (App Password) nas configurações de Segurança da sua conta Google.");
    } else if (type === 'outlook') {
      host.value = 'smtp.office365.com';
      port.value = '587';
      secure.value = 'tls';
    } else if (type === 'mailtrap') {
      host.value = 'sandbox.smtp.mailtrap.io';
      port.value = '2525';
      secure.value = 'none';
      alert("Configuração do Mailtrap selecionada! Insira o Usuário e Senha da sua Inbox do Mailtrap.");
    } else if (type === 'aws') {
      host.value = 'email-smtp.us-east-1.amazonaws.com';
      port.value = '587';
      secure.value = 'tls';
      alert("Configuração do Amazon SES selecionada! Utilize as credenciais SMTP geradas no console da AWS.");
    }
  }
  </script>

  <!-- Coluna da Direita: Informações do Sistema & TCC -->
  <div class="col-lg-5">
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-header bg-white py-3 d-flex align-items-center gap-2 border-bottom">
        <div class="rounded p-2 bg-success bg-opacity-10 text-success">
          <i class="bi bi-hdd-stack-fill fs-5"></i>
        </div>
        <div>
          <h6 class="mb-0 fw-bold">Arquitetura do Sistema</h6>
          <small class="text-muted">Ambiente e infraestrutura de dados</small>
        </div>
      </div>

      <div class="card-body p-3">
        <ul class="list-group list-group-flush small">
          <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-0">
            <span class="text-muted">Banco de Dados Ativo:</span>
            <strong class="text-success"><?= e($dbDriver) ?></strong>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-0">
            <span class="text-muted">Versão PHP:</span>
            <strong>PHP <?= phpversion() ?></strong>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-0">
            <span class="text-muted">Animais Ativos:</span>
            <span class="badge bg-primary rounded-pill"><?= $totalAnimais ?></span>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-0">
            <span class="text-muted">Total de Pesagens:</span>
            <span class="badge bg-warning text-dark rounded-pill"><?= $totalPesagens ?></span>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-0">
            <span class="text-muted">Sincronizações Realizadas:</span>
            <span class="badge bg-success rounded-pill"><?= $totalSincs ?></span>
          </li>
        </ul>

        <div class="alert alert-success bg-opacity-10 border-success border-opacity-25 mt-3 mb-0 p-3 small">
          <h6 class="fw-bold text-success mb-1" style="font-size:0.85rem;"><i class="bi bi-shield-check me-1"></i> Módulo Mobile Híbrido Ativo</h6>
          Os dados coletados em campo pelo aplicativo nativo são integrados automaticamente à base de dados central com suporte a transações atômicas e fotos comprimidas.
        </div>
      </div>
    </div>
  </div>
</div>
