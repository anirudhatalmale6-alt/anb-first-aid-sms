<?php
/**
 * Self-contained SMTP mailer (no external dependencies).
 * Supports STARTTLS + AUTH LOGIN (works with Office 365 / smtp.office365.com:587),
 * HTML body and file attachments. Settings live in a key/value `settings` table.
 */
declare(strict_types=1);

/** Ensure the settings table exists and is seeded with SMTP defaults. */
function anb_settings_init(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT)");
    $defaults = [
        'smtp_host'     => 'smtp.office365.com',
        'smtp_port'     => '587',
        'smtp_security' => 'tls',           // tls (STARTTLS) | ssl | none
        'smtp_user'     => 'admin@anbfirstaidtraining.com.au',
        'smtp_pass'     => '',
        'mail_from'     => 'admin@anbfirstaidtraining.com.au',
        'mail_from_name'=> 'A&B First Aid Training',
    ];
    $ins = $pdo->prepare("INSERT OR IGNORE INTO settings (k,v) VALUES (?,?)");
    foreach ($defaults as $k=>$v) $ins->execute([$k,$v]);
}

/** Return all settings as an assoc array. */
function anb_settings(PDO $pdo): array {
    anb_settings_init($pdo);
    $out = [];
    foreach ($pdo->query("SELECT k,v FROM settings") as $r) $out[$r['k']] = $r['v'];
    return $out;
}

function anb_setting_save(PDO $pdo, string $k, string $v): void {
    anb_settings_init($pdo);
    $pdo->prepare("INSERT INTO settings (k,v) VALUES (?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v")
        ->execute([$k,$v]);
}

/** Replace {merge_field} tokens in a string. Unknown tokens left as-is. */
function anb_merge(string $text, array $vars): string {
    foreach ($vars as $k=>$v) $text = str_replace('{'.$k.'}', (string)$v, $text);
    return $text;
}

/**
 * Send an email via SMTP. $attachments = [ ['path'=>..,'name'=>..], ... ].
 * Returns [true,''] on success or [false,'error message'] on failure.
 */
function anb_send_mail(PDO $pdo, string $to, string $subject, string $htmlBody, array $attachments = []): array {
    $s = anb_settings($pdo);
    $host = $s['smtp_host'] ?? ''; $port = (int)($s['smtp_port'] ?? 587);
    $user = $s['smtp_user'] ?? ''; $pass = $s['smtp_pass'] ?? '';
    $sec  = $s['smtp_security'] ?? 'tls';
    $fromEmail = $s['mail_from'] ?: $user;
    $fromName  = $s['mail_from_name'] ?: 'A&B First Aid Training';
    if ($host === '' || $user === '' || $pass === '')
        return [false, 'SMTP is not configured yet (host, username and password are required).'];

    $eol = "\r\n";
    $transport = ($sec === 'ssl') ? "ssl://$host" : $host;
    $ctx = stream_context_create(['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]]);
    $errno=0; $errstr='';
    $fp = @stream_socket_client("$transport:$port", $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return [false, "Connection failed: $errstr ($errno)"];
    stream_set_timeout($fp, 20);

    $read = function() use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break; // last line of reply
        }
        return $data;
    };
    $cmd = function(string $c, ?string $expect = null) use ($fp, $read) {
        fwrite($fp, $c . "\r\n");
        $resp = $read();
        if ($expect !== null && strpos($resp, $expect) !== 0)
            throw new RuntimeException("SMTP error after '".preg_replace('/AUTH LOGIN.*/','AUTH LOGIN',$c)."': ".trim($resp));
        return $resp;
    };

    try {
        $read(); // server greeting
        $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
        $cmd($ehlo, '250');
        if ($sec === 'tls') {
            $cmd('STARTTLS', '220');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT))
                throw new RuntimeException('Failed to start TLS');
            $cmd($ehlo, '250');
        }
        $cmd('AUTH LOGIN', '334');
        $cmd(base64_encode($user), '334');
        $cmd(base64_encode($pass), '235'); // 235 = auth successful
        $cmd('MAIL FROM:<' . $fromEmail . '>', '250');
        $cmd('RCPT TO:<' . $to . '>', '250');
        $cmd('DATA', '354');

        // ---- build MIME message ----
        $boundary = 'anb_' . bin2hex(random_bytes(8));
        $headers  = 'From: ' . anb_mime_name($fromName) . ' <' . $fromEmail . '>' . $eol;
        $headers .= 'To: <' . $to . '>' . $eol;
        $headers .= 'Subject: ' . anb_mime_header($subject) . $eol;
        $headers .= 'MIME-Version: 1.0' . $eol;
        $headers .= 'Date: ' . date('r') . $eol;

        $body = '';
        if ($attachments) {
            $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . $eol;
            $body .= '--' . $boundary . $eol;
            $body .= 'Content-Type: text/html; charset=UTF-8' . $eol;
            $body .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
            $body .= chunk_split(base64_encode($htmlBody)) . $eol;
            foreach ($attachments as $att) {
                if (empty($att['path']) || !is_file($att['path'])) continue;
                $name = $att['name'] ?? basename($att['path']);
                $body .= '--' . $boundary . $eol;
                $body .= 'Content-Type: application/octet-stream; name="' . $name . '"' . $eol;
                $body .= 'Content-Transfer-Encoding: base64' . $eol;
                $body .= 'Content-Disposition: attachment; filename="' . $name . '"' . $eol . $eol;
                $body .= chunk_split(base64_encode((string)file_get_contents($att['path']))) . $eol;
            }
            $body .= '--' . $boundary . '--' . $eol;
        } else {
            $headers .= 'Content-Type: text/html; charset=UTF-8' . $eol;
            $headers .= 'Content-Transfer-Encoding: base64' . $eol;
            $body = chunk_split(base64_encode($htmlBody));
        }

        // dot-stuffing for lines beginning with '.'
        $message = preg_replace('/^\./m', '..', $headers . $eol . $body);
        fwrite($fp, $message . $eol . '.' . $eol);
        $resp = $read();
        if (strpos($resp, '250') !== 0) throw new RuntimeException('Message not accepted: ' . trim($resp));
        $cmd('QUIT');
        fclose($fp);
        return [true, ''];
    } catch (Throwable $ex) {
        @fclose($fp);
        return [false, $ex->getMessage()];
    }
}

function anb_mime_header(string $s): string {
    return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}
function anb_mime_name(string $s): string {
    return preg_match('/[^\x20-\x7e]/', $s) ? anb_mime_header($s) : '"' . str_replace('"', '', $s) . '"';
}
