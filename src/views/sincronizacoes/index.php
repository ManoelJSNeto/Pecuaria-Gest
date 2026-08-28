<?php
$logs = $db->query("SELECT * FROM sincronizacoes ORDER BY created_at DESC LIMIT 20")->fetchAll();
$totalSincs = $db->query("SELECT COUNT(*) FROM sincronizacoes")->fetchColumn();
$totalDados = $db->query("SELECT SUM(dados_recebidos) FROM sincronizacoes")->fetchColumn();

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$apiUrl = "$proto://$host/api/sync";
?>
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card"><div class="stat-icon" style="background:#e3f2fd"><i class="bi bi-phone-fill text-primary fs-4"></i></div><div><div class="stat-value"><?= $totalSincs ?></div><div class="stat-label">Sincronizações</div></div></div>
  </div>
  <div class="col-md-4">
    <div class="stat-card"><div class="stat-icon" style="background:#e8f5ee"><i class="bi bi-box-seam-fill text-success fs-4"></i></div><div><div class="stat-value"><?= $totalDados ?? 0 ?></div><div class="stat-label">Registros Recebidos</div></div></div>
  </div>
  <div class="col-md-4">
    <div class="stat-card"><div class="stat-icon" style="background:#fff3cd"><i class="bi bi-clock-history text-warning fs-4"></i></div><div><div class="stat-value small fw-700"><?= $logs ? formatDateTime($logs[0]['created_at']) : '—' ?></div><div class="stat-label">Última Sinc.</div></div></div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header"><i class="bi bi-code-slash text-primary"></i><h6>API de Integração Mobile</h6></div>
  <div class="card-body">
    <p class="text-muted small mb-3">O app mobile pode enviar dados via <strong>POST</strong> para o endpoint abaixo. Inclua o header <code>X-API-Key: pecuaria-mobile-key</code>.</p>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label small fw-600">Endpoint</label>
        <div class="input-group input-group-sm">
          <input type="text" class="form-control font-monospace" value="<?= e($apiUrl) ?>" readonly id="apiEndpoint">
          <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('apiEndpoint').value);this.innerHTML='<i class=\'bi bi-check-lg\'></i>';"><i class="bi bi-clipboard"></i> Copiar</button>
        </div>
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-600">API Key</label>
        <div class="input-group input-group-sm">
          <input type="text" class="form-control font-monospace" value="pecuaria-mobile-key" readonly id="apiKey">
          <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('apiKey').value);this.innerHTML='<i class=\'bi bi-check-lg\'></i>';"><i class="bi bi-clipboard"></i> Copiar</button>
        </div>
      </div>
    </div>
    <div class="mt-3">
      <label class="form-label small fw-600">Payload de Exemplo (JSON)</label>
      <pre class="bg-light p-3 rounded small" style="font-size:.77rem">{
  "dispositivo": "App Mobile v1",
  "pesagens": [
    { "brinco": "BR0001", "peso": 345.5, "data": "<?= date('Y-m-d') ?>", "observacao": "Pesagem de campo" }
  ],
  "saude": [
    { "brinco": "BR0002", "tipo": "Vacinação", "descricao": "Aftosa", "data": "<?= date('Y-m-d') ?>", "medicamento": "Aftovac" }
  ],
  "animais_novos": []
}</pre>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-clock-history text-secondary"></i><h6>Histórico de Sincronizações</h6></div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>Data/Hora</th><th>Dispositivo</th><th>IP</th><th>Registros</th><th>Status</th><th>Detalhes</th></tr></thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma sincronização registrada ainda.</td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td class="small text-muted"><?= formatDateTime($log['created_at']) ?></td>
          <td class="small fw-600"><?= e($log['dispositivo'] ?? '—') ?></td>
          <td class="small text-muted"><?= e($log['ip'] ?? '—') ?></td>
          <td><span class="badge bg-primary"><?= $log['dados_recebidos'] ?></span></td>
          <td><span class="badge bg-<?= $log['status']==='ok'?'success':'danger' ?>"><?= e($log['status']) ?></span></td>
          <td class="small text-muted font-monospace"><?= e($log['detalhes'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
