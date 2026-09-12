<?php
// Summary stats for reports
$totalAnimais  = $db->query("SELECT COUNT(*) FROM animais")->fetchColumn();
$totalPesagens = $db->query("SELECT COUNT(*) FROM pesagens")->fetchColumn();
$totalSaude    = $db->query("SELECT COUNT(*) FROM saude")->fetchColumn();
$custoSaude    = $db->query("SELECT SUM(custo) FROM saude WHERE custo IS NOT NULL")->fetchColumn();

$pesoMedio = null;
$mensal = [];
try {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'pgsql') {
        $pesoMedio = $db->query("SELECT ROUND(AVG(p.peso)::numeric,1) FROM pesagens p INNER JOIN (SELECT animal_id,MAX(data) md FROM pesagens GROUP BY animal_id) lp ON p.animal_id=lp.animal_id AND p.data=lp.md")->fetchColumn();
        $mensal    = $db->query("SELECT TO_CHAR(data::date, 'YYYY-MM') as mes, COUNT(*) as total, ROUND(AVG(peso::numeric),1) as media FROM pesagens WHERE data::date >= (CURRENT_DATE - INTERVAL '12 months') GROUP BY mes ORDER BY mes")->fetchAll();
    } else {
        $pesoMedio = $db->query("SELECT ROUND(AVG(p.peso), 1) FROM pesagens p INNER JOIN (SELECT animal_id,MAX(data) md FROM pesagens GROUP BY animal_id) lp ON p.animal_id=lp.animal_id AND p.data=lp.md")->fetchColumn();
        $mensal    = $db->query("SELECT substr(data, 1, 7) as mes, COUNT(*) as total, ROUND(AVG(peso), 1) as media FROM pesagens GROUP BY mes ORDER BY mes")->fetchAll();
    }
} catch (Exception $e) {
    $mensal = [];
}

$statusReport  = $db->query("SELECT status, COUNT(*) as total FROM animais GROUP BY status ORDER BY total DESC")->fetchAll();
$racaReport    = $db->query("SELECT raca, COUNT(*) as total FROM animais GROUP BY raca ORDER BY total DESC")->fetchAll();

$pastos = $pastos ?? $db->query("SELECT id, nome FROM pastagens ORDER BY nome ASC")->fetchAll();
$racas = $racas ?? $db->query("SELECT DISTINCT raca FROM animais WHERE raca IS NOT NULL AND raca != '' ORDER BY raca ASC")->fetchAll(PDO::FETCH_COLUMN);
$tiposSaude = $tiposSaude ?? $db->query("SELECT DISTINCT tipo FROM saude WHERE tipo IS NOT NULL AND tipo != '' ORDER BY tipo ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold">Relatórios Gerenciais & Exportação</h5>
    <small class="text-muted">Consolidação estatística, emissão de documentos técnicos A4 e extração CSV</small>
  </div>
  <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" onclick="abrirAjudaRelatorios()">
    <i class="bi bi-info-circle"></i> <span>Instruções dos Relatórios</span>
  </button>
</div>

<!-- Cockpit de Indicadores Gerenciais -->
<div class="metric-cockpit">
  <div class="metric-cell">
    <span class="metric-label">Base Cadastrada</span>
    <div class="metric-value tabular-nums"><?= (int)$totalAnimais ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">animais</span></div>
    <span class="metric-sub">Total histórico registrado</span>
  </div>
  <div class="metric-cell">
    <span class="metric-label">Aferições Biométricas</span>
    <div class="metric-value tabular-nums"><?= (int)$totalPesagens ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">pesagens</span></div>
    <span class="metric-sub">Peso médio: <?= $pesoMedio ? $pesoMedio . ' kg' : '—' ?></span>
  </div>
  <div class="metric-cell">
    <span class="metric-label">Ocorrências Clínicas</span>
    <div class="metric-value tabular-nums"><?= (int)$totalSaude ?> <span style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">eventos</span></div>
    <span class="metric-sub">Vacinas e tratamentos</span>
  </div>
  <div class="metric-cell">
    <span class="metric-label">Investimento Sanitário</span>
    <div class="metric-value tabular-nums" style="color:var(--earth-green-800);">
      R$ <?= $custoSaude ? number_format($custoSaude, 2, ',', '.') : '0,00' ?>
    </div>
    <span class="metric-sub">Custo acumulado em insumos</span>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-pie-chart text-secondary me-1"></i>Rebanho por Status</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="border:none; border-radius:0;">
          <table class="table table-sm">
            <tbody>
              <?php foreach ($statusReport as $s): ?>
              <tr>
                <td><?= statusBadge($s['status']) ?></td>
                <td class="fw-bold text-end tabular-nums"><?= (int)$s['total'] ?></td>
                <td class="text-end text-muted small tabular-nums"><?= $totalAnimais ? round($s['total']/$totalAnimais*100) : 0 ?>%</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-tag text-secondary me-1"></i>Animais por Raça</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="border:none; border-radius:0;">
          <table class="table table-sm">
            <tbody>
              <?php foreach ($racaReport as $r): ?>
              <tr>
                <td class="small fw-600"><?= e($r['raca'] ?? 'Sem raça') ?></td>
                <td class="fw-bold text-end tabular-nums"><?= (int)$r['total'] ?></td>
                <td class="text-end text-muted small tabular-nums"><?= $totalAnimais ? round($r['total']/$totalAnimais*100) : 0 ?>%</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-calendar-check text-secondary me-1"></i>Pesagens por Mês</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="border:none; border-radius:0;">
          <table class="table table-sm">
            <tbody>
              <?php foreach (array_slice(array_reverse($mensal), 0, 8) as $m): ?>
              <tr>
                <td class="small fw-600 tabular-nums"><?= $m['mes'] ?></td>
                <td class="text-end">
                  <span class="badge bg-light text-dark border tabular-nums"><?= (int)$m['total'] ?> pesagens</span>
                </td>
                <td class="text-end text-secondary small tabular-nums"><?= $m['media'] ?> kg</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Emissão de Relatórios Oficiais em PDF (A4) -->
<div class="card mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #f0f7f2 0%, #ffffff 100%); border: 1px solid var(--border) !important;">
  <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
    <div>
      <h6 class="mb-0 fw-bold" style="color: var(--primary);">
        <i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>Relatórios Oficiais em PDF (A4 para Impressão)
      </h6>
      <small class="text-muted">Documentos técnicos formatados com cabeçalho institucional, indicadores zootécnicos e assinatura</small>
    </div>
    <span class="badge bg-success" style="font-size: 0.72rem;">Pronto para Impressão / PDF</span>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <!-- Card Inventário Geral do Rebanho -->
      <div class="col-md-6">
        <div class="p-3 bg-white border rounded h-100 d-flex flex-column justify-content-between shadow-xs">
          <div>
            <div class="d-flex align-items-center gap-2 mb-2">
              <div class="p-2 rounded" style="background: #eef7f0; color: var(--primary);">
                <i class="bi bi-clipboard2-data-fill fs-5"></i>
              </div>
              <div>
                <strong class="d-block" style="color: var(--earth-green-950);">Inventário Geral do Rebanho & Lotação</strong>
                <span class="badge-status ativo" style="font-size: 0.68rem;">Zootécnico & Pastoreio</span>
              </div>
            </div>
            <p class="small text-muted mb-3">
              Consolidação de animais ativos, balanço de ocupação por pastagem/piquete, densidade (cab/ha), categorias zootécnicas e médias de peso em kg e arrobas (@).
            </p>
          </div>
          <div class="d-flex gap-2">
            <a href="/relatorios/pdf?tipo=rebanho" target="_blank" class="btn btn-outline-success btn-sm flex-grow-1 fw-600">
              <i class="bi bi-printer me-1"></i> PDF Completo
            </a>
            <button type="button" class="btn btn-success btn-sm fw-600" data-bs-toggle="modal" data-bs-target="#modalFiltroRebanho" title="Filtrar por pasto, sexo, raça ou categoria">
              <i class="bi bi-sliders me-1"></i> Personalizar...
            </button>
          </div>
        </div>
      </div>

      <!-- Card Laudo Sanitário -->
      <div class="col-md-6">
        <div class="p-3 bg-white border rounded h-100 d-flex flex-column justify-content-between shadow-xs">
          <div>
            <div class="d-flex align-items-center gap-2 mb-2">
              <div class="p-2 rounded" style="background: #fdf2e9; color: #d35400;">
                <i class="bi bi-heart-pulse-fill fs-5"></i>
              </div>
              <div>
                <strong class="d-block" style="color: var(--earth-green-950);">Laudo Sanitário & Manejo Clínico</strong>
                <span class="badge-status ativo" style="font-size: 0.68rem; background: #fdf2e9; color: #d35400; border-color: #f5cba7;">Sanitário & Fármacos</span>
              </div>
            </div>
            <p class="small text-muted mb-3">
              Prontuário sanitário consolidado com histórico cronológico de aplicações, custos com vacinas e medicamentos, distribuição por tipo e controle por veterinário.
            </p>
          </div>
          <div class="d-flex gap-2">
            <a href="/relatorios/pdf?tipo=saude" target="_blank" class="btn btn-outline-success btn-sm flex-grow-1 fw-600">
              <i class="bi bi-printer me-1"></i> PDF Completo
            </a>
            <button type="button" class="btn btn-success btn-sm fw-600" data-bs-toggle="modal" data-bs-target="#modalFiltroSaude" title="Filtrar por tipo de manejo, período ou veterinário">
              <i class="bi bi-sliders me-1"></i> Personalizar...
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Exportação de Dados em CSV -->
<div class="card">
  <div class="card-header">
    <h6 class="mb-0"><i class="bi bi-download text-secondary me-1"></i>Exportar Bases de Dados (Formato CSV)</h6>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-lg-4 col-md-6">
        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between" style="background:var(--bg-subtle);">
          <div>
            <div class="fs-4 text-primary mb-1"><i class="bi bi-tag"></i></div>
            <strong class="d-block mb-1">Animais</strong>
            <small class="text-muted d-block mb-3">Rebanho completo com dados cadastrais e genealogia.</small>
          </div>
          <a href="/relatorios?export=animais" class="btn btn-secondary btn-sm w-100">
            <i class="bi bi-download me-1"></i> Baixar CSV
          </a>
        </div>
      </div>

      <div class="col-lg-4 col-md-6">
        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between" style="background:var(--bg-subtle);">
          <div>
            <div class="fs-4 text-primary mb-1"><i class="bi bi-rulers"></i></div>
            <strong class="d-block mb-1">Pesagens</strong>
            <small class="text-muted d-block mb-3">Histórico completo de pesagens e evolução de peso.</small>
          </div>
          <a href="/relatorios?export=pesagens" class="btn btn-secondary btn-sm w-100">
            <i class="bi bi-download me-1"></i> Baixar CSV
          </a>
        </div>
      </div>

      <div class="col-lg-4 col-md-6">
        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between" style="background:var(--bg-subtle);">
          <div>
            <div class="fs-4 text-primary mb-1"><i class="bi bi-heart-pulse"></i></div>
            <strong class="d-block mb-1">Saúde & Vacinas</strong>
            <small class="text-muted d-block mb-3">Ocorrências sanitárias, medicamentos e custos.</small>
          </div>
          <a href="/relatorios?export=saude" class="btn btn-secondary btn-sm w-100">
            <i class="bi bi-download me-1"></i> Baixar CSV
          </a>
        </div>
      </div>

      <div class="col-lg-4 col-md-6">
        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between" style="background:var(--bg-subtle);">
          <div>
            <div class="fs-4 text-primary mb-1"><i class="bi bi-tree"></i></div>
            <strong class="d-block mb-1">Pastagens</strong>
            <small class="text-muted d-block mb-3">Capacidade, área e taxa de ocupação dos piquetes.</small>
          </div>
          <a href="/relatorios?export=pastagens" class="btn btn-secondary btn-sm w-100">
            <i class="bi bi-download me-1"></i> Baixar CSV
          </a>
        </div>
      </div>

      <div class="col-lg-4 col-md-6">
        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between" style="background:var(--bg-subtle);">
          <div>
            <div class="fs-4 text-primary mb-1"><i class="bi bi-truck"></i></div>
            <strong class="d-block mb-1">Compras & Entradas</strong>
            <small class="text-muted d-block mb-3">Lotes adquiridos com GTA, NF-e, custos e médias.</small>
          </div>
          <a href="/relatorios?export=compras" class="btn btn-secondary btn-sm w-100">
            <i class="bi bi-download me-1"></i> Baixar CSV
          </a>
        </div>
      </div>

      <div class="col-lg-4 col-md-6">
        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between" style="background:var(--bg-subtle);">
          <div>
            <div class="fs-4 text-success mb-1"><i class="bi bi-cash-coin"></i></div>
            <strong class="d-block mb-1">Vendas & Saídas</strong>
            <small class="text-muted d-block mb-3">Abates e comercialização com GTA, NF-e e apuração.</small>
          </div>
          <a href="/relatorios?export=vendas" class="btn btn-secondary btn-sm w-100">
            <i class="bi bi-download me-1"></i> Baixar CSV
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal de Personalização do Inventário do Rebanho (PDF) -->
<div class="modal fade" id="modalFiltroRebanho" tabindex="-1" aria-labelledby="modalFiltroRebanhoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="modalFiltroRebanhoLabel">
          <i class="bi bi-funnel-fill text-success me-2"></i>Personalizar Inventário do Rebanho (PDF)
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <form action="/relatorios/pdf" method="GET" target="_blank">
        <input type="hidden" name="tipo" value="rebanho">
        <div class="modal-body">
          <p class="small text-muted mb-3">Escolha os critérios para filtrar os animais e os blocos incluídos no relatório oficial:</p>
          
          <!-- Filtro por Prefixo / Início de Brinco ou Nome -->
          <div class="mb-3 p-2 rounded border bg-light">
            <label class="form-label small fw-bold mb-1">
              <i class="bi bi-tag-fill text-success me-1"></i>Filtrar por Prefixo / Início do Brinco ou Nome
            </label>
            <input type="text" name="prefixo" class="form-control form-control-sm" placeholder="Ex: T001, T002, T00, PG_ (ou separe por vírgula: T001, T002)" autocomplete="off">
            <div class="form-text" style="font-size: 0.72rem;">
              Filtra animais cujo brinco ou nome comece com o termo digitado (ex: <code>T001</code>, <code>T002</code>, <code>T00</code>).
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Pastagem / Piquete</label>
              <select name="pasto_id" class="form-select form-select-sm">
                <option value="">Todas as pastagens</option>
                <?php foreach ($pastos as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= e($p['nome']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Status dos Animais</label>
              <select name="status" class="form-select form-select-sm">
                <option value="ativo">Apenas Ativos (Padrão)</option>
                <option value="vendido">Apenas Vendidos</option>
                <option value="morto">Apenas Óbitos</option>
                <option value="todos">Todos (Ativos e Baixados)</option>
              </select>
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <label class="form-label small fw-bold">Sexo Biológico</label>
              <select name="sexo" class="form-select form-select-sm">
                <option value="">Todos (Machos e Fêmeas)</option>
                <option value="M">Apenas Machos ♂</option>
                <option value="F">Apenas Fêmeas ♀</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold">Categoria de Idade</label>
              <select name="categoria" class="form-select form-select-sm">
                <option value="">Todas as categorias</option>
                <option value="bezerro">Bezerros / Filhotes (≤ 12 meses)</option>
                <option value="adulto">Adultos (> 12 meses)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold">Raça Predominante</label>
              <select name="raca" class="form-select form-select-sm">
                <option value="">Todas as raças</option>
                <?php foreach ($racas as $r): ?>
                  <option value="<?= e($r) ?>"><?= e($r) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Sub-aba Retrátil: Seleção Vaca por Vaca (Específica) -->
          <div class="card border rounded mb-3" style="background: #fafbfa;">
            <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#subAbaVacas" role="button" aria-expanded="false" style="cursor: pointer;">
              <span class="small fw-bold text-dark">
                <i class="bi bi-check2-square text-success me-1"></i> Seleção Específica de Animais (Vaca por Vaca)
              </span>
              <span class="badge bg-secondary" id="badgeVacasCount" style="font-size: 0.65rem;">Opcional</span>
            </div>
            <div class="collapse" id="subAbaVacas">
              <div class="card-body p-2 bg-white">
                <p class="text-muted mb-2" style="font-size:0.75rem;">
                  Selecione exatamente quais vacas/animais devem constar no relatório. Se nenhuma for marcada, todos os animais do rebanho serão considerados conforme os filtros gerais.
                </p>
                <div class="d-flex gap-2 mb-2">
                  <input type="text" class="form-control form-control-sm" id="filtroRapidoVacas" placeholder="Buscar por brinco, nome, raça..." oninput="filtrarListaVacasModal(this.value)">
                  <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" onclick="toggleTodosAnimaisModal(true)">Marcar Visíveis</button>
                  <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" onclick="toggleTodosAnimaisModal(false)">Limpar</button>
                </div>
                <div id="listaVacasScroll" style="max-height: 180px; overflow-y: auto; border: 1px solid var(--border); border-radius: 4px; padding: 4px; background: #fff;">
                  <?php if (empty($todosAnimais)): ?>
                    <div class="text-muted small p-2 text-center">Nenhum animal ativo cadastrado.</div>
                  <?php else: ?>
                    <?php foreach ($todosAnimais as $an): ?>
                      <div class="form-check py-1 px-4 border-bottom item-vaca-check" data-busca="<?= strtolower(e($an['brinco'] . ' ' . ($an['nome'] ?? '') . ' ' . ($an['raca'] ?? '') . ' ' . ($an['pasto_nome'] ?? ''))) ?>">
                        <input class="form-check-input check-animal-id" type="checkbox" name="animais_ids[]" value="<?= $an['id'] ?>" id="chk_vaca_<?= $an['id'] ?>" onchange="atualizarContadorVacas()">
                        <label class="form-check-label small d-flex justify-content-between cursor-pointer w-100" for="chk_vaca_<?= $an['id'] ?>">
                          <span>
                            <strong><?= e($an['brinco']) ?></strong>
                            <?= $an['nome'] ? ' — ' . e($an['nome']) : '' ?>
                            <span class="text-muted">(<?= e($an['raca'] ?? 'Nelore') ?>)</span>
                          </span>
                          <span class="text-secondary" style="font-size:0.72rem;"><?= e($an['pasto_nome'] ?? 'Sem pasto') ?></span>
                        </label>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <div class="form-check p-2 rounded bg-light border">
            <input class="form-check-input ms-1" type="checkbox" name="sem_animais" value="1" id="checkSemAnimais">
            <label class="form-check-label small fw-600 ms-2" for="checkSemAnimais">
              Ocultar romaneio individual de animais
              <span class="d-block text-muted" style="font-size: 0.72rem; font-weight: normal;">Gera apenas o resumo executivo, indicadores e o balanço de pastagens (ideal para impressões sucintas de 1 página).</span>
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-success fw-600">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Gerar PDF Personalizado
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal de Personalização do Laudo Sanitário (PDF) -->
<div class="modal fade" id="modalFiltroSaude" tabindex="-1" aria-labelledby="modalFiltroSaudeLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="modalFiltroSaudeLabel">
          <i class="bi bi-funnel-fill text-warning me-2"></i>Personalizar Laudo Sanitário (PDF)
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <form action="/relatorios/pdf" method="GET" target="_blank">
        <input type="hidden" name="tipo" value="saude">
        <div class="modal-body">
          <p class="small text-muted mb-3">Defina os filtros clínicos e o período para emissão do laudo veterinário oficial:</p>
          
          <div class="mb-3">
            <label class="form-label small fw-bold">Classificação do Manejo Sanitário</label>
            <select name="tipo_manejo" class="form-select form-select-sm">
              <option value="">Todos os manejos e procedimentos</option>
              <?php foreach ($tiposSaude as $ts): ?>
                <option value="<?= e($ts) ?>"><?= e($ts) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Data Inicial</label>
              <input type="date" name="data_inicio" class="form-control form-control-sm">
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Data Final</label>
              <input type="date" name="data_fim" class="form-control form-control-sm">
            </div>
          </div>

          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small fw-bold">Veterinário Responsável</label>
              <input type="text" name="veterinario" class="form-control form-control-sm" placeholder="Ex: Dr. Silva">
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Brinco / Prefixo</label>
              <input type="text" name="brinco" class="form-control form-control-sm" placeholder="Ex: T001, BR001, PG_">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-success fw-600">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Gerar Laudo Filtrado
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function filtrarListaVacasModal(termo) {
  const query = termo.toLowerCase().trim();
  const itens = document.querySelectorAll('#listaVacasScroll .item-vaca-check');
  itens.forEach(it => {
    const texto = it.getAttribute('data-busca') || '';
    if (!query || texto.includes(query)) {
      it.style.display = 'block';
    } else {
      it.style.display = 'none';
    }
  });
}

function toggleTodosAnimaisModal(marcar) {
  const visiveis = document.querySelectorAll('#listaVacasScroll .item-vaca-check');
  visiveis.forEach(it => {
    if (it.style.display !== 'none') {
      const chk = it.querySelector('.check-animal-id');
      if (chk) chk.checked = marcar;
    }
  });
  atualizarContadorVacas();
}

function atualizarContadorVacas() {
  const selecionados = document.querySelectorAll('.check-animal-id:checked').length;
  const badge = document.getElementById('badgeVacasCount');
  if (badge) {
    if (selecionados > 0) {
      badge.className = 'badge bg-success';
      badge.textContent = selecionados + ' selecionada(s)';
    } else {
      badge.className = 'badge bg-secondary';
      badge.textContent = 'Opcional';
    }
  }
}

// ── Guia e Ajuda Lateral AWS para Relatórios ──
window.abrirAjudaRelatorios = function() {
  const title = 'Guia: Relatórios Zootécnicos e Emissões Oficiais';
  const html = `
    <div class="aws-help-section">
      <h7><i class="bi bi-file-earmark-pdf-fill text-danger"></i> Relatórios Oficiais em PDF (A4)</h7>
      <p>Gerados com cabeçalho institucional da fazenda, data de emissão e espaço para assinatura do responsável técnico:</p>
      <ul>
        <li><strong>Inventário Geral do Rebanho:</strong> Balanço do plantel, categorias (bezerros, novilhas, matrizes, touros), lotação dos pastos (cab/ha) e peso médio em kg e arrobas (@). Ideal para bancos (financiamentos/custeio) e declaração anual de rebanho.</li>
        <li><strong>Laudo Sanitário:</strong> Histórico de vacinas obrigatórias (aftosa, brucelose) e medicações com controle de período de carência pré-abate.</li>
      </ul>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-sliders"></i> Personalização e Filtros Granulares</h7>
      <p>Ao clicar em <strong>"Personalizar..."</strong>, você pode emitir relatórios específicos:</p>
      <ul>
        <li>Filtrar apenas fêmeas ou machos;</li>
        <li>Filtrar por lote de prefixo (ex: lote <code>T001</code>);</li>
        <li>Marcar manualmente vaca por vaca pelo brinco para montar um lote de venda ou transferência.</li>
      </ul>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-filetype-csv text-success"></i> Exportações em Planilha (CSV)</h7>
      <div class="aws-help-tip-box">
        Para auditorias contábeis ou análises personalizadas no Microsoft Excel, utilize a seção <strong>"Exportar Bases de Dados"</strong> para baixar os registros completos em formato CSV tabular.
      </div>
    </div>
  `;
  openAwsHelpDrawer(title, html);
};

document.addEventListener('DOMContentLoaded', () => {
  const btnTop = document.getElementById('btnTopHelp');
  if (btnTop) btnTop.onclick = abrirAjudaRelatorios;
});
</script>
