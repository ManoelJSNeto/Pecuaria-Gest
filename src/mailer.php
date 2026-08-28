<?php
/**
 * PecuáriaGest — Cliente SMTP Nativo Autônomo (Zero Dependências)
 * Suporta autenticação SMTP com TLS/STARTTLS, SSL e portas padrão (587, 465, 25).
 */

class PGLiteMailer {
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private string $encryption;
    private int $timeout;
    private string $lastError = '';

    public function __construct(
        string $host,
        int $port = 587,
        string $user = '',
        string $pass = '',
        string $encryption = 'tls',
        int $timeout = 10
    ) {
        $this->host = trim($host);
        $this->port = $port > 0 ? $port : 587;
        $this->user = trim($user);
        $this->pass = $pass;
        $this->encryption = strtolower(trim($encryption));
        $this->timeout = $timeout;
    }

    public function getLastError(): string {
        return $this->lastError;
    }

    public function send(string $fromEmail, string $fromName, string $toEmail, string $subject, string $htmlBody): bool {
        if (empty($this->host)) {
            // Fallback para mail() nativo do PHP se SMTP não estiver configurado
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
            $headers .= "Reply-To: {$fromEmail}\r\n";
            $headers .= "X-Mailer: PecuariaGest-Native\r\n";

            $sent = @mail($toEmail, $subject, $htmlBody, $headers);
            if (!$sent) {
                $this->lastError = "Host SMTP não configurado e a função nativa mail() do PHP falhou (comum no Windows sem servidor local na porta 25). Configure o servidor SMTP em Configurações.";
            }
            return $sent;
        }

        $connectHost = $this->host;
        if ($this->encryption === 'ssl' || $this->port === 465) {
            $connectHost = 'ssl://' . $this->host;
        }

        $socket = @fsockopen($connectHost, $this->port, $errno, $errstr, $this->timeout);
        if (!$socket) {
            $this->lastError = "Falha ao conectar no servidor SMTP {$this->host}:{$this->port} (Código {$errno}: {$errstr}). Verifique o host, porta e conexão de internet.";
            return false;
        }

        stream_set_timeout($socket, $this->timeout);

        $res = $this->readResponse($socket);
        if (substr($res, 0, 3) !== '220') {
            $this->lastError = "Servidor SMTP não respondeu com código 220 inicial: {$res}";
            fclose($socket);
            return false;
        }

        // 1. EHLO
        $this->sendCommand($socket, 'EHLO ' . (gethostname() ?: 'localhost'));
        $res = $this->readResponse($socket);

        // 2. STARTTLS se porta 587 ou modo TLS
        if ($this->encryption === 'tls' || ($this->port === 587 && $this->encryption !== 'ssl')) {
            $this->sendCommand($socket, 'STARTTLS');
            $res = $this->readResponse($socket);
            if (substr($res, 0, 3) !== '220') {
                $this->lastError = "Servidor recusou STARTTLS: {$res}";
                fclose($socket);
                return false;
            }

            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            if (!@stream_socket_enable_crypto($socket, true, $crypto)) {
                $this->lastError = "Falha na negociação criptografada TLS com {$this->host}.";
                fclose($socket);
                return false;
            }

            $this->sendCommand($socket, 'EHLO ' . (gethostname() ?: 'localhost'));
            $this->readResponse($socket);
        }

        // 3. Autenticação (se usuário informado)
        if (!empty($this->user)) {
            $this->sendCommand($socket, 'AUTH LOGIN');
            $res = $this->readResponse($socket);
            if (substr($res, 0, 3) !== '334') {
                $this->lastError = "Servidor recusou autenticação AUTH LOGIN: {$res}";
                fclose($socket);
                return false;
            }

            $this->sendCommand($socket, base64_encode($this->user));
            $res = $this->readResponse($socket);
            if (substr($res, 0, 3) !== '334') {
                $this->lastError = "Usuário SMTP rejeitado pelo servidor: {$res}";
                fclose($socket);
                return false;
            }

            $this->sendCommand($socket, base64_encode($this->pass));
            $res = $this->readResponse($socket);
            if (substr($res, 0, 3) !== '235') {
                $this->lastError = "Senha SMTP rejeitada ou autenticação falhou (Código {$res}). Em contas Gmail/Outlook utilize uma Senha de Aplicativo.";
                fclose($socket);
                return false;
            }
        }

        // 4. MAIL FROM
        $this->sendCommand($socket, "MAIL FROM:<{$fromEmail}>");
        $res = $this->readResponse($socket);
        if (substr($res, 0, 3) !== '250') {
            $this->lastError = "Remetente MAIL FROM rejeitado ({$fromEmail}): {$res}";
            fclose($socket);
            return false;
        }

        // 5. RCPT TO
        $this->sendCommand($socket, "RCPT TO:<{$toEmail}>");
        $res = $this->readResponse($socket);
        if (substr($res, 0, 3) !== '250' && substr($res, 0, 3) !== '251') {
            $this->lastError = "Destinatário RCPT TO rejeitado ({$toEmail}): {$res}";
            fclose($socket);
            return false;
        }

        // 6. DATA
        $this->sendCommand($socket, 'DATA');
        $res = $this->readResponse($socket);
        if (substr($res, 0, 3) !== '354') {
            $this->lastError = "Comando DATA rejeitado: {$res}";
            fclose($socket);
            return false;
        }

        // Cabeçalhos MIME
        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>",
            "To: <{$toEmail}>",
            "Reply-To: <{$fromEmail}>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "Date: " . date('r'),
            "X-Mailer: PecuariaGest-Mailer"
        ];

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.\r\n";
        fwrite($socket, $payload);

        $res = $this->readResponse($socket);
        $this->sendCommand($socket, 'QUIT');
        fclose($socket);

        if (substr($res, 0, 3) !== '250') {
            $this->lastError = "Falha ao finalizar envio de e-mail: {$res}";
            return false;
        }

        return true;
    }

    private function sendCommand($socket, string $cmd): void {
        fwrite($socket, $cmd . "\r\n");
    }

    private function readResponse($socket): string {
        $res = '';
        while ($line = fgets($socket, 515)) {
            $res .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return trim($res);
    }
}
