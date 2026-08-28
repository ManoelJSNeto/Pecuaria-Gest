<?php
$emailEnabled     = getSysConfig('notif_email_enabled', '1');
$emailDestinatario = getSysConfig('notif_email_destinatario', DEFAULT_ADMIN_EMAIL);
$emailFrom        = getSysConfig('notif_smtp_from', 'sistema@pecuariagest.com.br');

$totalAnimais     = $db->query("SELECT COUNT(*) FROM animais WHERE status != 'morto' AND status != 'vendido'")->fetchColumn();
$totalPesagens    = $db->query("SELECT COUNT(*) FROM pesagens")->fetchColumn();
$totalSincs       = $db->query("SELECT COUNT(*) FROM sincronizacoes")->fetchColumn();
$dbDriver         = DB_DRIVER === 'pgsql' ? 'PostgreSQL 16 (Amazon RDS / Nuvem)' : 'SQLite 3 (Armazenamento Local)';
?>

<div class="row g-4">
  <!-- Coluna da Esquerda: Configurações de Notificação -->
  <div class="col-lg-7">
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between border-bottom">
        <div class="d-flex align-items-center gap-2">
          <div class="rounded p-2 bg-primary bg-opacity-10 text-primary">
            <i class="bi bi-envelope-at-fill fs-5"></i>
          </div>
          <div>
            <h6 class="mb-0 fw-bold">Notificações por E-mail do Proprietário</h6>
            <small class="text-muted">Configurações de relatórios e alertas automáticos de campo</small>
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
            <label class="form-label fw-bold small">E-mail Remetente do Sistema</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-send-fill"></i></span>
              <input type="email" name="notif_smtp_from" class="form-control" value="<?= e($emailFrom) ?>" placeholder="sistema@pecuariagest.com.br">
            </div>
            <small class="text-muted">Identificação do remetente configurada no servidor (SMTP/Amazon SES).</small>
          </div>

          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
            <button type="submit" class="btn btn-primary fw-bold px-4">
              <i class="bi bi-check-lg me-1"></i> Salvar Configurações
            </button>
            <a href="/configuracoes/testar-email" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-envelope-paper-fill me-1"></i> Enviar E-mail de Teste
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>

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
