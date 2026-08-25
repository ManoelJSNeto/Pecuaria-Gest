<?php
$isEdit    = isset($animal);
$a         = $animal ?? [];
$currPasto = (int)($a['pasto_id'] ?? 0);
$pastagens = $db->query("SELECT id, nome, status FROM pastagens WHERE status='ativa' OR id = $currPasto ORDER BY nome")->fetchAll();
$animaisF  = $db->query("SELECT id, brinco, nome FROM animais ORDER BY brinco")->fetchAll();
?>
<div class="mb-3">
  <a href="<?= $isEdit ? '/animais/'.$a['id'] : '/animais' ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar
  </a>
</div>

<form method="POST" action="<?= $isEdit ? '/animais/'.$a['id'].'/atualizar' : '/animais/salvar' ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="form-section">
        <div class="form-section-title"><i class="bi bi-tag-fill text-primary"></i> Identificação</div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Brinco / Código *</label>
            <input type="text" name="brinco" class="form-control" required value="<?= e($a['brinco'] ?? '') ?>" placeholder="Ex: BR0001">
          </div>
          <div class="col-md-4">
            <label class="form-label">Nome</label>
            <input type="text" name="nome" class="form-control" value="<?= e($a['nome'] ?? '') ?>" placeholder="Opcional">
          </div>
          <div class="col-md-4">
            <label class="form-label">Sexo *</label>
            <select name="sexo" class="form-select" required>
              <option value="M" <?= ($a['sexo']??'M')==='M'?'selected':'' ?>>♂ Macho</option>
              <option value="F" <?= ($a['sexo']??'')==='F'?'selected':'' ?>>♀ Fêmea</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Raça</label>
            <input type="text" name="raca" class="form-control" list="racasList" value="<?= e($a['raca'] ?? '') ?>" placeholder="Ex: Nelore">
            <datalist id="racasList">
              <?php foreach (['Nelore','Angus','Brahman','Girolando','Gir','Senepol','Tabapuã','Hereford','Simental','Limousin'] as $r): ?>
                <option value="<?= $r ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="col-md-4">
            <label class="form-label">Data de Nascimento</label>
            <input type="date" name="data_nascimento" class="form-control" value="<?= e($a['data_nascimento'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Peso Inicial (kg)</label>
            <input type="number" step="0.01" name="peso_inicial" class="form-control" value="<?= e($a['peso_inicial'] ?? '') ?>" placeholder="0.00">
          </div>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-title"><i class="bi bi-geo-alt-fill text-success"></i> Manejo e Localização</div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Pastagem Atual</label>
            <select name="pasto_id" class="form-select">
              <option value="">— Sem pastagem —</option>
              <?php foreach ($pastagens as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ($a['pasto_id']??'')==$p['id']?'selected':'' ?>>
                  <?= e($p['nome']) ?><?= $p['status'] !== 'ativa' ? ' (' . ucfirst($p['status']) . ')' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php foreach (['ativo','doente','prenha','desmamado','vendido','morto'] as $s): ?>
                <option value="<?= $s ?>" <?= ($a['status']??'ativo')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Origem</label>
            <input type="text" name="origem" class="form-control" list="origemList" value="<?= e($a['origem'] ?? '') ?>" placeholder="Próprio, Comprado...">
            <datalist id="origemList">
              <option value="Próprio"><option value="Comprado"><option value="Nascido na fazenda">
            </datalist>
          </div>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-title"><i class="bi bi-diagram-3 text-info"></i> Genealogia</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Mãe (brinco)</label>
            <select name="mae_id" class="form-select">
              <option value="">— Desconhecida —</option>
              <?php foreach ($animaisF as $af): ?>
                <?php if (($af['id'] ?? 0) !== ($a['id'] ?? null)): ?>
                <option value="<?= $af['id'] ?>" <?= ($a['mae_id']??'')==$af['id']?'selected':'' ?>>
                  <?= e($af['brinco']) ?><?= $af['nome'] ? ' — '.e($af['nome']) : '' ?>
                </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Pai / Touro (brinco)</label>
            <input type="text" name="pai_brinco" class="form-control" value="<?= e($a['pai_brinco'] ?? '') ?>" placeholder="Ex: TO0042">
          </div>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-title"><i class="bi bi-journal-text text-secondary"></i> Observações</div>
        <textarea name="observacao" class="form-control" rows="3" placeholder="Informações adicionais..."><?= e($a['observacao'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-camera-fill text-primary"></i><h6>Foto do Animal</h6></div>
        <div class="card-body">
          <?php if (!empty($a['foto_url'])): ?>
            <div class="text-center mb-3">
              <img src="<?= e($a['foto_url']) ?>" alt="Foto do animal" class="img-fluid rounded border shadow-sm" style="max-height: 180px; object-fit: cover;">
            </div>
          <?php endif; ?>
          <label class="form-label small fw-bold">Enviar <?= !empty($a['foto_url']) ? 'Nova ' : '' ?>Foto</label>
          <input type="file" name="foto" class="form-control form-control-sm" accept="image/*">
          <small class="text-muted d-block mt-2">
            💡 Se for bezerro/filhote, a foto é gravada com destaque permanente como <strong>Memória de Nascimento</strong>.
          </small>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h6>Ações</h6></div>
        <div class="card-body">
          <?php if ($isEdit): ?>
            <div class="mb-3">
              <div class="info-label">Cadastrado em</div>
              <div class="info-value"><?= formatDate($a['created_at']) ?></div>
            </div>
            <div class="mb-3">
              <div class="info-label">Última atualização</div>
              <div class="info-value"><?= formatDate($a['updated_at']) ?></div>
            </div>
          <?php endif; ?>
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check-lg me-1"></i>
              <?= $isEdit ? 'Salvar Alterações' : 'Cadastrar Animal' ?>
            </button>
            <a href="<?= $isEdit ? '/animais/'.$a['id'] : '/animais' ?>" class="btn btn-outline-secondary">Cancelar</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
