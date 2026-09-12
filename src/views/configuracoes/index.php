<?php
// Parâmetros Institucionais da Propriedade
$fazendaNome         = getSysConfig('fazenda_nome', 'fazenda pecuGest');
$fazendaProprietario = getSysConfig('fazenda_proprietario', 'Alunos Etec');
$fazendaMunicipioUf  = getSysConfig('fazenda_municipio_uf', 'Presidente Prudente - SP');
$fazendaNirfCar      = getSysConfig('fazenda_nirf_car', '');

// Parâmetros Zootécnicos e Regras de Negócio
$rendimentoPadrao    = getSysConfig('rendimento_carcaca_padrao', '50.0');
$pesoAlvoAbate       = getSysConfig('peso_alvo_abate', '540.0');
$periodoCarenciaDias = getSysConfig('periodo_carencia_alerta_dias', '7');

// Notificações por E-mail & SMTP
$emailEnabled        = getSysConfig('notif_email_enabled', '1');
$emailDestinatario   = getSysConfig('notif_email_destinatario', DEFAULT_ADMIN_EMAIL);
$emailFrom           = getSysConfig('notif_smtp_from', 'sistema@pecuariagest.com.br');
$smtpHost            = getSysConfig('notif_smtp_host', '');
$smtpPort            = getSysConfig('notif_smtp_port', '587');
$smtpUser            = getSysConfig('notif_smtp_user', '');
$smtpPassSaved       = !empty(getSysConfig('notif_smtp_pass', ''));
$smtpSecure          = getSysConfig('notif_smtp_secure', 'tls');

// Estatísticas do Sistema
$totalAnimais        = $db->query("SELECT COUNT(*) FROM animais WHERE status != 'morto' AND status != 'vendido'")->fetchColumn();
$totalPesagens       = $db->query("SELECT COUNT(*) FROM pesagens")->fetchColumn();
$totalPastagens      = $db->query("SELECT COUNT(*) FROM pastagens WHERE status = 'ativa'")->fetchColumn();
$totalSincs          = $db->query("SELECT COUNT(*) FROM sincronizacoes")->fetchColumn();
$dbDriver            = 'PostgreSQL 16 (Dedicado / Docker)';
?>

<!-- Barra Superior com Breadcrumbs e Ajuda -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold">Configurações Gerais do Sistema</h5>
    <small class="text-muted">Parâmetros zootécnicos, identificação institucional da fazenda, backups e servidor de e-mails</small>
  </div>

  <div class="d-flex align-items-center gap-2">
    <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" onclick="abrirAjudaConfiguracoes()">
      <i class="bi bi-info-circle"></i> <span>Instruções deste Painel</span>
    </button>
  </div>
</div>

<form method="POST" action="/configuracoes/salvar" id="formConfiguracoes">
  <?= csrf_field() ?>

  <div class="row g-4">
    <!-- Coluna Principal (Configurações) -->
    <div class="col-lg-8">

      <!-- SEÇÃO 1: IDENTIFICAÇÃO DA PROPRIEDADE -->
      <div class="aws-container mb-4">
        <div class="aws-container-header d-flex justify-content-between align-items-center">
          <h6><i class="bi bi-geo-alt-fill text-success"></i> 1. Identificação da Propriedade (Cabeçalho de Laudos e PDFs)</h6>
          <span class="badge bg-light text-secondary border">Documentos Oficiais</span>
        </div>

        <div class="aws-container-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">Nome da Fazenda / Propriedade *</label>
              <input type="text" name="fazenda_nome" class="form-control" required
                     value="<?= e($fazendaNome) ?>" placeholder="Ex: fazenda pecuGest">
              <div class="aws-form-hint">
                <strong>O que é:</strong> O nome fantasia ou oficial da fazenda.<br>
                <strong>Como afeta o sistema:</strong> Impresso em destaque no cabeçalho de todos os laudos sanitários, fichas de inventário para bancos e recibos em PDF.
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bold">Produtor Rural / Titular Legal *</label>
              <input type="text" name="fazenda_proprietario" class="form-control" required
                     value="<?= e($fazendaProprietario) ?>" placeholder="Ex: Alunos Etec">
              <div class="aws-form-hint">
                <strong>O que é:</strong> Nome do titular ou razão social da exploração pecuária.<br>
                <strong>Como afeta o sistema:</strong> Identifica o proprietário nos documentos emitidos para contabilidade, GTA e cartórios.
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bold">Município e UF da Fazenda</label>
              <input type="text" name="fazenda_municipio_uf" class="form-control"
                     value="<?= e($fazendaMunicipioUf) ?>" placeholder="Ex: Presidente Prudente - SP">
              <div class="aws-form-hint">
                <strong>O que é:</strong> Cidade e Estado de localização dos pastos.<br>
                <strong>Como afeta o sistema:</strong> Utilizado na fiscalização sanitária (declaração anual de rebanho em órgãos como IDAF, IMA e Agrodefesa).
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bold">Inscrição Estadual ou CAR / NIRF</label>
              <input type="text" name="fazenda_nirf_car" class="form-control"
                     value="<?= e($fazendaNirfCar) ?>" placeholder="Ex: GO-5213806-CAR / NIRF">
              <div class="aws-form-hint">
                <strong>O que é:</strong> Registro ambiental ou cadastral do imóvel rural.<br>
                <strong>Como afeta o sistema:</strong> Permite comprovar conformidade socioambiental em relatórios bancários de crédito e financiamento.
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SEÇÃO 2: PARÂMETROS ZOOTÉCNICOS & BALANÇA -->
      <div class="aws-container mb-4">
        <div class="aws-container-header d-flex justify-content-between align-items-center">
          <h6><i class="bi bi-rulers text-primary"></i> 2. Parâmetros Zootécnicos & Regras da Balança</h6>
          <span class="badge bg-light text-secondary border">Métricas de Rebanho</span>
        </div>

        <div class="aws-container-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-bold">Rendimento de Carcaça Padrão (%) *</label>
              <div class="input-group">
                <input type="number" step="0.1" min="40" max="65" name="rendimento_carcaca_padrao" class="form-control" required
                       value="<?= e($rendimentoPadrao) ?>">
                <span class="input-group-text">%</span>
              </div>
              <div class="aws-form-hint">
                <strong>O que é:</strong> A proporção de carne limpa em relação ao peso vivo total do boi.<br>
                <strong>Como afeta o sistema:</strong> Altera o cálculo automático de <strong>Arrobas (@)</strong> nas telas de Pesagem, Compra e Venda. <em>(Nelore a pasto gira em torno de 50%, enquanto confinamento atinge 54% a 56%)</em>.
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-bold">Meta de Peso para Abate (kg) *</label>
              <div class="input-group">
                <input type="number" step="1" min="300" max="900" name="peso_alvo_abate" class="form-control" required
                       value="<?= e($pesoAlvoAbate) ?>">
                <span class="input-group-text">kg</span>
              </div>
              <div class="aws-form-hint">
                <strong>O que é:</strong> Peso final estipulado para venda aos frigoríficos.<br>
                <strong>Como afeta o sistema:</strong> Define a linha de chegada no gráfico de evolução de peso e aciona alertas de <em>"Lote Pronto para Abate"</em> no Dashboard.
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-bold">Aviso Prévio de Carência (Dias) *</label>
              <div class="input-group">
                <input type="number" step="1" min="1" max="60" name="periodo_carencia_alerta_dias" class="form-control" required
                       value="<?= e($periodoCarenciaDias) ?>">
                <span class="input-group-text">dias</span>
              </div>
              <div class="aws-form-hint">
                <strong>O que é:</strong> Antecedência para alertar o fim do período de resíduo medicamentoso.<br>
                <strong>Como afeta o sistema:</strong> Avisa na Central de Alertas que um lote vacinado/tratado está prestes a ser liberado para abate legal.
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SEÇÃO 3: NOTIFICAÇÕES POR E-MAIL & SMTP -->
      <div class="aws-container mb-4">
        <div class="aws-container-header d-flex justify-content-between align-items-center">
          <h6><i class="bi bi-envelope-at text-warning"></i> 3. Notificações por E-mail & Servidor de Envio (SMTP)</h6>
          <span class="badge bg-<?= $emailEnabled === '1' ? 'success' : 'secondary' ?>">
            <?= $emailEnabled === '1' ? 'Serviço Ativo' : 'Desativado' ?>
          </span>
        </div>

        <div class="aws-container-body">
          <div class="form-check form-switch mb-3 p-3 bg-light rounded border">
            <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" id="notif_email_enabled" name="notif_email_enabled" value="1" <?= $emailEnabled === '1' ? 'checked' : '' ?> style="width: 2.2em; height: 1.2em;">
            <label class="form-check-label fw-bold" for="notif_email_enabled">
              Disparar resumo por e-mail a cada sincronização realizada no curral
            </label>
            <div class="aws-form-hint ps-5">
              <strong>Como afeta o sistema:</strong> Se ligado, sempre que o capataz/peão sincronizar o aplicativo móvel com novas pesagens ou bezerros, o proprietário recebe um resumo consolidado em sua caixa de entrada.
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">E-mail do Proprietário / Gerente (Destinatário) *</label>
              <input type="email" name="notif_email_destinatario" class="form-control" required
                     value="<?= e($emailDestinatario) ?>" placeholder="proprietario@fazenda.com.br">
              <div class="aws-form-hint">
                <strong>O que é:</strong> Endereço de e-mail que receberá os alertas sanitários e resumos de manejo.
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bold">E-mail Remetente do Sistema (From)</label>
              <input type="email" name="notif_smtp_from" id="smtp_from" class="form-control"
                     value="<?= e($emailFrom) ?>" placeholder="sistema@pecuariagest.com.br">
              <div class="aws-form-hint">
                <strong>O que é:</strong> E-mail de remetente cadastrado no provedor (Gmail, Outlook ou AWS SES).
              </div>
            </div>
          </div>

          <!-- Bloco do Servidor SMTP -->
          <div class="p-3 bg-light rounded border mt-3">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
              <strong class="text-dark small"><i class="bi bi-server me-1"></i> Servidor de Envio SMTP Autenticado</strong>
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary py-0" onclick="applySmtpPreset('gmail')">Gmail</button>
                <button type="button" class="btn btn-outline-secondary py-0" onclick="applySmtpPreset('outlook')">Outlook</button>
                <button type="button" class="btn btn-outline-secondary py-0" onclick="applySmtpPreset('mailtrap')">Mailtrap</button>
                <button type="button" class="btn btn-outline-secondary py-0" onclick="applySmtpPreset('aws')">AWS SES</button>
              </div>
            </div>

            <div class="row g-2">
              <div class="col-md-8">
                <label class="form-label small fw-bold">Servidor Host</label>
                <input type="text" name="notif_smtp_host" id="smtp_host" class="form-control form-control-sm" value="<?= e($smtpHost) ?>" placeholder="smtp.gmail.com">
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-bold">Porta</label>
                <input type="number" name="notif_smtp_port" id="smtp_port" class="form-control form-control-sm" value="<?= e($smtpPort) ?>" placeholder="587">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold">Usuário de Autenticação</label>
                <input type="text" name="notif_smtp_user" id="smtp_user" class="form-control form-control-sm" value="<?= e($smtpUser) ?>" placeholder="usuario@provedor.com">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold">
                  Senha SMTP <?= $smtpPassSaved ? '<span class="badge bg-success bg-opacity-10 text-success fw-normal">Salva</span>' : '' ?>
                </label>
                <input type="password" name="notif_smtp_pass" class="form-control form-control-sm" placeholder="<?= $smtpPassSaved ? 'Deixe em branco para manter' : '••••••••' ?>" autocomplete="new-password">
              </div>
              <div class="col-12">
                <label class="form-label small fw-bold">Criptografia de Segurança</label>
                <select name="notif_smtp_secure" id="smtp_secure" class="form-select form-select-sm">
                  <option value="tls" <?= $smtpSecure === 'tls' ? 'selected' : '' ?>>TLS / STARTTLS (Porta 587 - Recomendado)</option>
                  <option value="ssl" <?= $smtpSecure === 'ssl' ? 'selected' : '' ?>>SSL (Porta 465)</option>
                  <option value="none" <?= $smtpSecure === 'none' ? 'selected' : '' ?>>Nenhuma (Servidores locais de teste)</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Barra de Ação para Salvar -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <button type="submit" class="aws-btn-primary py-2 px-4 shadow-sm">
          <i class="bi bi-check-lg"></i> Salvar Todas as Configurações
        </button>
        <button type="button" class="btn btn-outline-success btn-sm fw-bold" onclick="document.getElementById('formTestarEmail').submit()">
          <i class="bi bi-envelope-paper-fill me-1"></i> Enviar E-mail de Teste
        </button>
      </div>
    </div>

    <!-- Coluna Lateral: Módulo de Backup & Infraestrutura -->
    <div class="col-lg-4">

      <!-- CARD DE BACKUP MANUAL DO BANCO -->
      <div class="aws-container mb-4">
        <div class="aws-container-header">
          <h6><i class="bi bi-database-down text-success"></i> Backup Manual de Segurança</h6>
          <span class="badge bg-success-subtle text-success border border-success">Sob Demanda</span>
        </div>

        <div class="aws-container-body">
          <p class="small text-muted mb-3">
            Exporte uma cópia completa e atualizada dos dados vitais da sua fazenda. É altamente recomendável salvar uma cópia em pendrive antes de fechamentos mensais ou inventários.
          </p>

          <div class="d-grid gap-2 mb-3">
            <a href="/configuracoes/backup?formato=sql" class="btn btn-success btn-sm fw-bold py-2 text-start d-flex align-items-center justify-content-between shadow-xs">
              <span><i class="bi bi-filetype-sql me-2"></i> Baixar Backup Completo (.SQL)</span>
              <i class="bi bi-download"></i>
            </a>
            <small class="text-muted" style="font-size:0.75rem;">
              <strong>Formato SQL:</strong> Ideal para restauração direta no PostgreSQL ou MySQL caso precise reinstalar o sistema.
            </small>

            <a href="/configuracoes/backup?formato=json" class="btn btn-outline-secondary btn-sm fw-bold py-2 text-start d-flex align-items-center justify-content-between mt-2">
              <span><i class="bi bi-filetype-json me-2"></i> Baixar Dados Estruturados (.JSON)</span>
              <i class="bi bi-download"></i>
            </a>
            <small class="text-muted" style="font-size:0.75rem;">
              <strong>Formato JSON:</strong> Arquivo legível e organizado, ideal para inspeções e conversões em planilhas.
            </small>
          </div>

          <div class="p-2 rounded bg-light border small text-muted">
            <i class="bi bi-shield-lock-fill text-success me-1"></i>
            <strong>Tabelas incluídas:</strong> Animais, Pesagens, Manejos Sanitários, Reprodução, Pastagens, Compras, Vendas, Alertas, Usuários e Parâmetros.
          </div>
        </div>
      </div>

      <!-- CARD DE ARQUITETURA & ESTATÍSTICAS -->
      <div class="aws-container">
        <div class="aws-container-header">
          <h6><i class="bi bi-hdd-stack-fill text-primary"></i> Diagnóstico da Infraestrutura</h6>
        </div>

        <div class="aws-container-body">
          <table class="aws-review-table">
            <tr>
              <td class="label-cell">Banco de Dados:</td>
              <td class="value-cell text-success"><?= e($dbDriver) ?></td>
            </tr>
            <tr>
              <td class="label-cell">Ambiente PHP:</td>
              <td class="value-cell">PHP <?= phpversion() ?></td>
            </tr>
            <tr>
              <td class="label-cell">Animais Ativos:</td>
              <td class="value-cell fw-bold text-dark"><?= $totalAnimais ?> cabeças</td>
            </tr>
            <tr>
              <td class="label-cell">Pesagens Salvas:</td>
              <td class="value-cell fw-bold text-dark"><?= $totalPesagens ?> aferições</td>
            </tr>
            <tr>
              <td class="label-cell">Pastos Ativos:</td>
              <td class="value-cell"><?= $totalPastagens ?> piquetes</td>
            </tr>
            <tr>
              <td class="label-cell">Sincronizações:</td>
              <td class="value-cell"><?= $totalSincs ?> envios de campo</td>
            </tr>
          </table>

          <div class="mt-3 p-2 bg-success bg-opacity-10 border border-success border-opacity-25 rounded small text-success">
            <i class="bi bi-check-circle-fill me-1"></i> Servidor operacional e integrado com o curral.
          </div>
        </div>
      </div>

    </div>
  </div>
</form>

<!-- Formulário Oculto para Disparo de E-mail de Teste -->
<form id="formTestarEmail" method="POST" action="/configuracoes/testar-email" class="d-none">
  <?= csrf_field() ?>
</form>

<script>
function applySmtpPreset(type) {
  const host = document.getElementById('smtp_host');
  const port = document.getElementById('smtp_port');
  const secure = document.getElementById('smtp_secure');
  
  if (type === 'gmail') {
    host.value = 'smtp.gmail.com';
    port.value = '587';
    secure.value = 'tls';
    alert("Configuração do Gmail preenchida!\n\nUtilize seu e-mail completo e gere uma 'Senha de Aplicativo' (App Password) na sua Conta Google.");
  } else if (type === 'outlook') {
    host.value = 'smtp.office365.com';
    port.value = '587';
    secure.value = 'tls';
  } else if (type === 'mailtrap') {
    host.value = 'sandbox.smtp.mailtrap.io';
    port.value = '2525';
    secure.value = 'none';
  } else if (type === 'aws') {
    host.value = 'email-smtp.us-east-1.amazonaws.com';
    port.value = '587';
    secure.value = 'tls';
  }
}

// Configuração da Ajuda Lateral AWS para Configurações
window.abrirAjudaConfiguracoes = function() {
  const title = 'Guia: Configurações Gerais e Backup';
  const html = `
    <div class="aws-help-section">
      <h7><i class="bi bi-geo-alt-fill text-success"></i> Identificação da Fazenda</h7>
      <p>Os dados inseridos na Seção 1 saem automaticamente impressos no cabeçalho institucional dos laudos sanitários, inventários para bancos e comprovantes de compra e venda.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-rulers text-primary"></i> Impacto dos Parâmetros Zootécnicos</h7>
      <p>O <strong>Rendimento de Carcaça (%)</strong> afeta a conversão de kg vivo em arrobas (@) em todo o sistema. Se você comercializa gado terminado em confinamento, aumente para 54% a 56% para que o valor em arrobas reflita com precisão o ganho real do lote.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-database-down text-success"></i> Como e Quando Fazer Backup?</h7>
      <div class="aws-help-tip-box">
        Recomenda-se realizar o <strong>Download do Backup (.SQL)</strong> periodicamente e guardá-lo em um pendrive ou na nuvem. Em caso de pane no computador da fazenda, esse arquivo restaura 100% dos animais, pesagens e histórico em segundos.
      </div>
    </div>
  `;
  openAwsHelpDrawer(title, html);
};

document.addEventListener('DOMContentLoaded', () => {
  const btnTop = document.getElementById('btnTopHelp');
  if (btnTop) btnTop.onclick = abrirAjudaConfiguracoes;
});
</script>
