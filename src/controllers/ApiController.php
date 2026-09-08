<?php
/**
 * Controlador de APIs REST e Sincronização Mobile Offline-First
 * PecuáriaGest - Arquitetura Limpa MVC
 */

class ApiController extends BaseController {

    /**
     * Valida autenticação por Sessão, Chave de API ou Credenciais de Usuário
     */
    private function checkApiAuth(array $body = []): bool {
        if (isLoggedIn()) {
            return true;
        }

        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($body['api_key'] ?? ($_GET['api_key'] ?? ''));
        if (!empty($apiKey) && defined('API_KEY') && hash_equals(API_KEY, (string)$apiKey)) {
            return true;
        }

        if (!empty($body['auth_email']) && !empty($body['auth_senha'])) {
            $email = trim($body['auth_email']);
            $senha = (string)$body['auth_senha'];

            $uStmt = $this->db->prepare("SELECT id, senha FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
            $uStmt->execute([$email]);
            $userObj = $uStmt->fetch();
            if ($userObj && password_verify($senha, $userObj['senha'])) {
                return true;
            } elseif (defined('APP_ENV') && APP_ENV === 'development' && defined('DEFAULT_ADMIN_EMAIL') && defined('DEFAULT_ADMIN_PASS') && $email === DEFAULT_ADMIN_EMAIL && $senha === DEFAULT_ADMIN_PASS) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sincronização bidirecional offline de coletas mobile (POST /api/sync)
     */
    public function sync(): void {
        header('Content-Type: application/json');

        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: [];

        if (!$this->checkApiAuth($body)) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autorizado. Informe uma API Key válida ou credenciais de um usuário ativo.']);
            exit;
        }

        $processados = ['pesagens' => 0, 'saude' => 0, 'animais_novos' => 0, 'fotos' => 0];
        $erros       = [];

        // 1. Processa novos animais (suporta 'animais_novos' e 'animais')
        $novosAnimais = $body['animais_novos'] ?? ($body['animais'] ?? []);
        foreach ($novosAnimais as $an) {
            try {
                $brinco = trim($an['brinco'] ?? '');
                if (!$brinco) continue;

                $chk = $this->db->prepare("SELECT id FROM animais WHERE brinco = ?");
                $chk->execute([$brinco]);
                $existing = $chk->fetch();

                if (!$existing) {
                    $stmt = $this->db->prepare("INSERT INTO animais (brinco,sexo,raca,data_nascimento,nome,origem,status) VALUES (?,?,?,?,?,?,'ativo')");
                    $stmt->execute([
                        $an['brinco'] ?? null,
                        $an['sexo'] ?? 'M',
                        $an['raca'] ?? null,
                        $an['data_nascimento'] ?? null,
                        $an['nome'] ?? null,
                        'mobile'
                    ]);

                    $aid = null;
                    try {
                        $aid = (int)$this->db->lastInsertId();
                    } catch (Exception $ex) {}
                    if (!$aid) {
                        $aidStmt = $this->db->prepare("SELECT id FROM animais WHERE brinco = ? LIMIT 1");
                        $aidStmt->execute([$brinco]);
                        $aid = (int)$aidStmt->fetchColumn();
                    }

                    if ($aid) {
                        $processados['animais_novos']++;
                        if (!empty($an['foto_base64']) && function_exists('salvarBase64Foto')) {
                            $fUrl = salvarBase64Foto($an['foto_base64']);
                            if ($fUrl) {
                                $this->db->prepare("UPDATE animais SET foto_url = ? WHERE id = ?")->execute([$fUrl, $aid]);
                                $this->db->prepare("INSERT INTO fotos_animais (animal_id,foto_url,tipo_evento,fase,data,observacao) VALUES (?,?,'nascimento','bezerro',?,?)")
                                         ->execute([$aid, $fUrl, $an['data_nascimento'] ?? date('Y-m-d'), 'Foto de nascimento (PecuGest-Campo)']);
                                $processados['fotos']++;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $erros[] = 'animal:' . $e->getMessage();
            }
        }

        // 2. Processa pesagens
        foreach ($body['pesagens'] ?? [] as $p) {
            try {
                $aidStmt = $this->db->prepare("SELECT id FROM animais WHERE brinco = ?");
                $aidStmt->execute([$p['brinco'] ?? '']);
                $aid = $aidStmt->fetchColumn() ?: null;
                if ($aid) {
                    $stmt = $this->db->prepare("INSERT INTO pesagens (animal_id,peso,data,observacao,origem) VALUES (?,?,?,?,'mobile')");
                    $stmt->execute([$aid, $p['peso'] ?? 0, $p['data'] ?? date('Y-m-d'), $p['observacao'] ?? null]);
                    $processados['pesagens']++;

                    if (!empty($p['foto_base64']) && function_exists('salvarBase64Foto')) {
                        $fUrl = salvarBase64Foto($p['foto_base64']);
                        if ($fUrl) {
                            $this->db->prepare("INSERT INTO fotos_animais (animal_id,foto_url,tipo_evento,fase,data,observacao) VALUES (?,?,'pesagem','adulto',?,?)")
                                     ->execute([$aid, $fUrl, $p['data'] ?? date('Y-m-d'), 'Pesagem ' . ($p['peso'] ?? '') . 'kg (PecuGest-Campo)']);
                            $this->db->prepare("UPDATE animais SET foto_url = COALESCE(foto_url, ?) WHERE id = ?")->execute([$fUrl, $aid]);
                            $processados['fotos']++;
                        }
                    }
                }
            } catch (Exception $e) {
                $erros[] = 'pesagem:' . $e->getMessage();
            }
        }

        // 3. Processa eventos de saúde
        foreach ($body['saude'] ?? [] as $s) {
            try {
                $aidStmt = $this->db->prepare("SELECT id FROM animais WHERE brinco = ?");
                $aidStmt->execute([$s['brinco'] ?? '']);
                $aid = $aidStmt->fetchColumn() ?: null;
                if ($aid) {
                    $stmt = $this->db->prepare("INSERT INTO saude (animal_id,tipo,descricao,data,medicamento,dose,observacao,origem) VALUES (?,?,?,?,?,?,?,'mobile')");
                    $stmt->execute([$aid, $s['tipo'] ?? 'Outro', $s['descricao'] ?? '', $s['data'] ?? date('Y-m-d'), $s['medicamento'] ?? null, $s['dose'] ?? null, $s['observacao'] ?? null]);
                    $processados['saude']++;

                    if (function_exists('atualizarStatusAnimalPorSaude')) {
                        atualizarStatusAnimalPorSaude($this->db, (int)$aid, $s['tipo'] ?? '');
                    }

                    $tipoNorm = function_exists('normalizarTexto') ? normalizarTexto($s['tipo'] ?? '') : strtolower(trim($s['tipo'] ?? ''));
                    $isObito = in_array($tipoNorm, ['obito', 'morte', 'morreu']);

                    if (!empty($s['foto_base64']) && function_exists('salvarBase64Foto')) {
                        $fUrl = salvarBase64Foto($s['foto_base64']);
                        if ($fUrl) {
                            $isSensivel = ($isObito || in_array($tipoNorm, ['curativo', 'ferimento', 'cirurgia', 'obito', 'tratamento']) || !empty($s['is_sensivel'])) ? 1 : 0;
                            $fase = $isObito ? 'obito' : 'adulto';
                            $this->db->prepare("INSERT INTO fotos_animais (animal_id,foto_url,tipo_evento,fase,is_sensivel,data,observacao) VALUES (?,?,?,?,?,?,?)")
                                     ->execute([$aid, $fUrl, $isObito ? 'obito' : 'saude', $fase, $isSensivel, $s['data'] ?? date('Y-m-d'), $s['descricao'] ?? 'Registro de saúde']);
                            $processados['fotos']++;
                        }
                    }
                }
            } catch (Exception $e) {
                $erros[] = 'saude:' . $e->getMessage();
            }
        }

        // Log sync
        $total = array_sum($processados);
        $status = empty($erros) ? 'ok' : ($total > 0 ? 'parcial' : 'erro');
        $this->db->prepare("INSERT INTO sincronizacoes (dispositivo,ip,dados_recebidos,status,detalhes) VALUES (?,?,?,?,?)")
                 ->execute([$body['dispositivo'] ?? 'desconhecido', $_SERVER['REMOTE_ADDR'] ?? '', $total, $status, json_encode(['processados' => $processados, 'erros' => $erros])]);

        // Alerta no Painel Web
        if ($total > 0) {
            $msgPartes = [];
            if ($processados['pesagens'] > 0)     $msgPartes[] = "{$processados['pesagens']} pesagens";
            if ($processados['animais_novos'] > 0) $msgPartes[] = "{$processados['animais_novos']} novos bezerros";
            if ($processados['saude'] > 0)         $msgPartes[] = "{$processados['saude']} manejos sanitários";
            if ($processados['fotos'] > 0)         $msgPartes[] = "{$processados['fotos']} fotos";

            $resumoMsg = implode(', ', $msgPartes);
            $dispositivoNome = htmlspecialchars($body['dispositivo'] ?? 'App Campo');

            try {
                $this->db->prepare("INSERT INTO alertas (animal_id, tipo, mensagem, lido) VALUES (NULL, 'sincronizacao', ?, 0)")
                         ->execute(["Coleta sincronizada via {$dispositivoNome}: {$resumoMsg}."]);
            } catch (Exception $e) {}

            if (function_exists('notifyOwnerOnSyncEmail')) {
                try {
                    notifyOwnerOnSyncEmail($processados, $body['dispositivo'] ?? 'App Campo');
                } catch (Exception $e) {}
            }
        }

        echo json_encode(['status' => $status, 'processados' => $processados, 'erros' => $erros]);
        exit;
    }

    /**
     * Retorna a lista de animais ativos com peso atual para carga inicial no app (GET /api/animais)
     */
    public function animais(): void {
        header('Content-Type: application/json');

        if (!$this->checkApiAuth()) {
            http_response_code(401);
            echo json_encode(['error' => 'Acesso não autorizado. Chave de API ou sessão de usuário obrigatória.']);
            exit;
        }

        $animais = $this->db->query("
            SELECT a.*, p.nome as pasto_nome,
              COALESCE(
                (SELECT peso FROM pesagens WHERE animal_id=a.id ORDER BY data DESC, id DESC LIMIT 1),
                a.peso_inicial
              ) as peso_atual
            FROM animais a
            LEFT JOIN pastagens p ON a.pasto_id=p.id
            WHERE a.status NOT IN('vendido','morto')
            ORDER BY a.brinco
        ")->fetchAll();

        echo json_encode(['animais' => $animais]);
        exit;
    }
}
