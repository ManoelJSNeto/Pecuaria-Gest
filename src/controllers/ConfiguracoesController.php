<?php
/**
 * Controlador de Parâmetros Globais do Sistema e Notificações SMTP
 * PecuáriaGest - Arquitetura Limpa MVC
 */

class ConfiguracoesController extends BaseController {

    /**
     * Tela de configurações do sistema e SMTP
     */
    public function index(): void {
        $this->requireLogin();
        $this->render('configuracoes/index', 'Configurações do Sistema', 'configuracoes');
    }

    /**
     * Salva as configurações do sistema e e-mail SMTP (POST /configuracoes/salvar)
     */
    public function salvar(): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token de segurança expirado. Tente novamente.');
            $this->redirect('/configuracoes');
        }

        $enabled      = isset($_POST['notif_email_enabled']) ? '1' : '0';
        $destinatario = trim($_POST['notif_email_destinatario'] ?? '');
        $from         = trim($_POST['notif_smtp_from'] ?? '');
        $smtpHost     = trim($_POST['notif_smtp_host'] ?? '');
        $smtpPort     = trim($_POST['notif_smtp_port'] ?? '587');
        $smtpUser     = trim($_POST['notif_smtp_user'] ?? '');
        $smtpPass     = $_POST['notif_smtp_pass'] ?? '';
        $smtpSecure   = trim($_POST['notif_smtp_secure'] ?? 'tls');

        if (function_exists('setSysConfig')) {
            setSysConfig('notif_email_enabled', $enabled);
            setSysConfig('notif_email_destinatario', $destinatario);
            setSysConfig('notif_smtp_from', $from);
            setSysConfig('notif_smtp_host', $smtpHost);
            setSysConfig('notif_smtp_port', $smtpPort);
            setSysConfig('notif_smtp_user', $smtpUser);
            if (!empty($smtpPass)) {
                setSysConfig('notif_smtp_pass', $smtpPass);
            }
            setSysConfig('notif_smtp_secure', $smtpSecure);

            // Dados Institucionais da Propriedade
            if (isset($_POST['fazenda_nome'])) setSysConfig('fazenda_nome', trim($_POST['fazenda_nome']));
            if (isset($_POST['fazenda_proprietario'])) setSysConfig('fazenda_proprietario', trim($_POST['fazenda_proprietario']));
            if (isset($_POST['fazenda_municipio_uf'])) setSysConfig('fazenda_municipio_uf', trim($_POST['fazenda_municipio_uf']));
            if (isset($_POST['fazenda_nirf_car'])) setSysConfig('fazenda_nirf_car', trim($_POST['fazenda_nirf_car']));

            // Parâmetros Zootécnicos e Regras de Negócio
            if (isset($_POST['rendimento_carcaca_padrao'])) setSysConfig('rendimento_carcaca_padrao', trim($_POST['rendimento_carcaca_padrao']));
            if (isset($_POST['peso_alvo_abate'])) setSysConfig('peso_alvo_abate', trim($_POST['peso_alvo_abate']));
            if (isset($_POST['periodo_carencia_alerta_dias'])) setSysConfig('periodo_carencia_alerta_dias', trim($_POST['periodo_carencia_alerta_dias']));
        }

        flash('success', 'Configurações do sistema e notificações salvas com sucesso!');
        $this->redirect('/configuracoes');
    }

    /**
     * Dispara e-mail de teste para validação das credenciais SMTP (POST /configuracoes/testar-email)
     */
    public function testarEmail(): void {
        $this->requireLogin();
        if (!$this->validateCsrf()) {
            flash('error', 'Token de segurança expirado.');
            $this->redirect('/configuracoes');
        }

        $destinatario = function_exists('getSysConfig')
            ? getSysConfig('notif_email_destinatario', defined('DEFAULT_ADMIN_EMAIL') ? DEFAULT_ADMIN_EMAIL : 'admin@fazenda.com')
            : (defined('DEFAULT_ADMIN_EMAIL') ? DEFAULT_ADMIN_EMAIL : 'admin@fazenda.com');

        $assunto = "[PecuáriaGest] Teste de Notificação do Sistema";
        $corpo = '
        <div style="font-family:Arial,sans-serif;padding:20px;background:#f4f6f4;color:#2c3e2d;">
          <div style="max-width:520px;margin:0 auto;background:#fff;padding:24px;border-radius:10px;border-top:5px solid #1a4d2e;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
            <h3 style="color:#1a4d2e;margin-top:0;font-size:20px;">Teste de Notificação SMTP Bem-Sucedido!</h3>
            <p style="font-size:15px;line-height:1.5;">Este é um e-mail de teste disparado pelo sistema <strong>PecuáriaGest</strong> confirmando a comunicação ativa com o servidor.</p>
            <div style="background:#f8faf8;padding:12px 16px;border-radius:6px;border:1px solid #e8ede9;margin:16px 0;font-size:13px;">
              <strong>Destinatário:</strong> ' . htmlspecialchars($destinatario) . '<br>
              <strong>Horário do Disparo:</strong> ' . date('d/m/Y H:i:s') . '<br>
              <strong>Status:</strong> Conexão SMTP autenticada e entregue.
            </div>
            <p style="font-size:12px;color:#6b7280;margin-bottom:0;">PecuáriaGest — Gestão Agropecuária Integrada</p>
          </div>
        </div>';

        $errorMsg = null;
        $enviado = function_exists('sendNotificationEmail')
            ? sendNotificationEmail($destinatario, $assunto, $corpo, $errorMsg)
            : false;

        if ($enviado) {
            flash('success', "E-mail de teste enviado com sucesso para {$destinatario}!");
        } else {
            flash('error', "Falha no envio de e-mail: " . ($errorMsg ?: "Verifique as credenciais do servidor SMTP."));
        }

        $this->redirect('/configuracoes');
    }

    /**
     * Gera e realiza o download de backup manual das tabelas essenciais (GET /configuracoes/backup)
     */
    public function backup(): void {
        $this->requireLogin();

        $formato = strtolower($_GET['formato'] ?? 'sql');
        $dataHora = date('Y-m-d_H-i-s');

        // Tabelas vitais da propriedade
        $tabelas = [
            'configuracoes',
            'pastagens',
            'animais',
            'pesagens',
            'saude',
            'reproducao',
            'compras',
            'compras_animais',
            'vendas',
            'vendas_animais',
            'alertas',
            'usuarios'
        ];

        $user = currentUser();
        $nomeUser = $user['nome'] ?? 'Administrador';

        if ($formato === 'json') {
            $backupData = [
                'sistema' => 'PecuáriaGest',
                'versao' => '2.0-cloudscape',
                'gerado_em' => date('Y-m-d H:i:s'),
                'responsavel' => $nomeUser,
                'tabelas' => []
            ];

            foreach ($tabelas as $tab) {
                try {
                    $rows = $this->db->query("SELECT * FROM {$tab}")->fetchAll(PDO::FETCH_ASSOC);
                    if ($tab === 'usuarios') {
                        foreach ($rows as &$u) {
                            unset($u['senha']); // Sanitização de credenciais no JSON
                        }
                    }
                    $backupData['tabelas'][$tab] = [
                        'total_registros' => count($rows),
                        'dados' => $rows
                    ];
                } catch (Exception $e) {
                    // Ignora tabelas opcionais se não existirem
                }
            }

            header('Content-Type: application/json; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"backup_pecuariagest_{$dataHora}.json\"");
            echo json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            // Formato padrão SQL Dump
            $sqlDump = "-- ========================================================\n";
            $sqlDump .= "-- BACKUP MANUAL DE SEGURANÇA - PECUÁRIAGEST\n";
            $sqlDump .= "-- Data do Arquivo: " . date('d/m/Y H:i:s') . "\n";
            $sqlDump .= "-- Gerado por: " . $nomeUser . "\n";
            $sqlDump .= "-- ========================================================\n\n";

            foreach ($tabelas as $tab) {
                try {
                    $rows = $this->db->query("SELECT * FROM {$tab}")->fetchAll(PDO::FETCH_ASSOC);
                    $total = count($rows);
                    $sqlDump .= "-- --------------------------------------------------------\n";
                    $sqlDump .= "-- Registros da Tabela: {$tab} ({$total} linhas)\n";
                    $sqlDump .= "-- --------------------------------------------------------\n";

                    if ($total > 0) {
                        foreach ($rows as $row) {
                            $cols = array_keys($row);
                            $vals = array_map(function($v) {
                                if ($v === null) return 'NULL';
                                return $this->db->quote((string)$v);
                            }, array_values($row));

                            $sqlDump .= "INSERT INTO {$tab} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
                        }
                    }
                    $sqlDump .= "\n";
                } catch (Exception $e) {
                    // Ignora se tabela inexistente
                }
            }

            header('Content-Type: application/sql; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"backup_pecuariagest_{$dataHora}.sql\"");
            echo $sqlDump;
            exit;
        }
    }
}
