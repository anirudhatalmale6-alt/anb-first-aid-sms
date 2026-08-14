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

/** Google review URL for a location, falling back to the default link. Empty string if none set. */
function anb_review_link(PDO $pdo, ?int $locationId): string {
    $s = anb_settings($pdo);
    if ($locationId && !empty($s['review_url_'.$locationId])) return $s['review_url_'.$locationId];
    return $s['review_url_default'] ?? '';
}

/** Ensure the students.review_emailed_at column exists (once-per-student guard). */
function anb_review_request_schema(PDO $pdo): void {
    $cols = $pdo->query("PRAGMA table_info(students)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('review_emailed_at', $cols, true)) {
        $pdo->exec("ALTER TABLE students ADD COLUMN review_emailed_at TEXT");
    }
}

/**
 * Email a student a Google review request (sent once, right after their certificate
 * is issued). Best-effort: returns [bool ok, string msg]; never throws to the caller.
 * Only sends if: valid email, not already sent, and a review link exists for the location.
 */
function anb_send_review_request(PDO $pdo, int $studentId, ?string $email, ?string $firstName, ?int $locationId): array {
    anb_review_request_schema($pdo);
    if (!$email || strpos($email, '@') === false) return [false, 'no valid email'];
    $chk = $pdo->prepare("SELECT review_emailed_at FROM students WHERE id=?");
    $chk->execute([$studentId]);
    if (!empty($chk->fetchColumn())) return [false, 'already sent'];
    $url = anb_review_link($pdo, $locationId);
    if ($url === '') return [false, 'no review link configured'];
    $s = anb_settings($pdo);
    $company = $s['mail_from_name'] ?: 'A&B First Aid Training';
    $fn = $firstName !== null && $firstName !== '' ? $firstName : 'there';
    $E = function ($t) { return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8'); };
    $subject = 'How did we go? A quick favour, ' . $fn;
    $body = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#16232d;line-height:1.6;max-width:560px">'
        . '<p>Hi ' . $E($fn) . ',</p>'
        . '<p>Congratulations on completing your course with ' . $E($company) . ' and receiving your certificate!</p>'
        . '<p>We\'re a small local team and a quick Google review makes a huge difference. It only takes 30 seconds - would you mind sharing how we went?</p>'
        . '<p style="text-align:center;margin:26px 0"><a href="' . $E($url) . '" style="background:#f4b400;color:#16232d;font-weight:bold;text-decoration:none;padding:13px 26px;border-radius:8px;display:inline-block">Leave us a Google review</a></p>'
        . '<p>Thank you so much - it really helps other people find us.</p>'
        . '<p>Kind regards,<br>' . $E($company) . '</p></div>';
    [$ok, $msg] = anb_send_mail($pdo, $email, $subject, $body);
    if ($ok) $pdo->prepare("UPDATE students SET review_emailed_at=datetime('now') WHERE id=?")->execute([$studentId]);
    return [$ok, $msg];
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

/**
 * What to call the student in a greeting.
 *
 * 158 records have an empty first_name with the whole name sitting in
 * last_name - "Parwinder Kaur", "Harvinder Singh" - an import artefact. The
 * renewal reminder merged that to "Hi ," which went out to a real student
 * before this existed. Falling back to the first word of the surname gives
 * "Hi Parwinder," which is what the record actually means.
 *
 * Placeholders from the same import are treated as empty, or we would greet
 * somebody as "(unknown)".
 */
function anb_greeting_name(?string $first, ?string $last = null): string {
    $junk  = ['(unknown)', 'unknown', 'n/a', 'na', '-', '.', '?'];
    $first = trim((string)$first);
    if ($first !== '' && !in_array(strtolower($first), $junk, true)) return $first;

    $last = trim((string)$last);
    if ($last === '' || in_array(strtolower($last), $junk, true)) return '';
    $parts = preg_split('/\s+/', $last) ?: [];
    return (string)($parts[0] ?? '');
}

/**
 * Domains that are somebody's typing rather than a real mail host.
 *
 * Not a spam list and not exhaustive - just the handful that turn up in this
 * data and bounce every single time. A bounce costs sender reputation, so the
 * address is worth refusing until it is corrected.
 */
const ANB_TYPO_DOMAINS = [
    'gamil.com','gmial.com','gmai.com','gmail.co','gmail.con','gmil.com','gmaill.com','gnail.com',
    'hotmial.com','hotmai.com','hotmail.co','hotmail.con','hotnail.com','hotmail.cm',
    'yaho.com','yahoo.con','yahho.com','yhaoo.com','yahoo.co',
    'outlok.com','outlook.co','outloo.com','oulook.com',
];

/** The obvious correction for a typo domain, or '' if we do not know one. */
function anb_email_suggestion(string $email): string {
    $at = strrpos($email, '@');
    if ($at === false) return '';
    $dom = strtolower(substr($email, $at + 1));
    $map = [
        'gamil.com'=>'gmail.com','gmial.com'=>'gmail.com','gmai.com'=>'gmail.com','gmail.co'=>'gmail.com',
        'gmail.con'=>'gmail.com','gmil.com'=>'gmail.com','gmaill.com'=>'gmail.com','gnail.com'=>'gmail.com',
        'hotmial.com'=>'hotmail.com','hotmai.com'=>'hotmail.com','hotmail.co'=>'hotmail.com',
        'hotmail.con'=>'hotmail.com','hotnail.com'=>'hotmail.com','hotmail.cm'=>'hotmail.com',
        'yaho.com'=>'yahoo.com','yahoo.con'=>'yahoo.com','yahho.com'=>'yahoo.com','yhaoo.com'=>'yahoo.com',
        'yahoo.co'=>'yahoo.com',
        'outlok.com'=>'outlook.com','outlook.co'=>'outlook.com','outloo.com'=>'outlook.com',
        'oulook.com'=>'outlook.com',
    ];
    return isset($map[$dom]) ? substr($email, 0, $at + 1) . $map[$dom] : '';
}

/**
 * Is this address worth sending to?
 *
 * @return array{0:bool,1:string} [usable, why not]
 */
function anb_email_usable(?string $email): array {
    $email = trim((string)$email);
    if ($email === '')                                    return [false, 'no email address'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))       return [false, 'not a valid address'];
    $dom = strtolower(substr($email, strrpos($email, '@') + 1));
    if (in_array($dom, ANB_TYPO_DOMAINS, true))           return [false, 'misspelt domain "' . $dom . '"'];
    return [true, ''];
}

/**
 * Turn a plain-text email body into the HTML we actually send.
 *
 * Every sender was doing nl2br(htmlspecialchars($body)) which is safe but
 * leaves a web address as dead text - the reader sees the link and cannot
 * click it. That affected the renewal reminder, the certificate email and,
 * worst of all, the portal access email, where the whole point is that the
 * student clicks through and logs in.
 *
 * Escape first so nothing in the body can inject markup, then linkify, then
 * break the lines - in that order, or the anchors we add get escaped too.
 */
function anb_body_html(string $text): string {
    $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // One pass, not three. Anything matched here is replaced with an anchor, and
    // a second pass over the result would happily match the address again inside
    // the href it just wrote - which is how "www.anbfirstaidtraining.com.au"
    // inside an https:// link would end up nested in a second broken anchor.
    // The alternatives are ordered: full address, then email (so the domain half
    // of admin@... is never taken for a host), then a bare www. host.
    $safe = preg_replace_callback(
        '#(?P<url>\bhttps?://[^\s<>"]+)'
        . '|(?P<mail>\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b)'
        . '|(?P<www>\bwww\.[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+(?:/[^\s<>"]*)?)#i',
        static function (array $m): string {
            if (!empty($m['mail'])) {
                return '<a href="mailto:' . $m['mail'] . '">' . $m['mail'] . '</a>';
            }
            // Trailing punctuation is part of the sentence, not the address.
            $shown = $m[0];
            $tail  = '';
            while ($shown !== '' && strpos('.,;:!?)', substr($shown, -1)) !== false) {
                $tail  = substr($shown, -1) . $tail;
                $shown = substr($shown, 0, -1);
            }
            if ($shown === '') return $tail;
            // She writes the address the way it is printed on her paperwork -
            // "www.anbfirstaidtraining.com.au", no scheme. A browser needs one
            // or it treats the whole thing as a relative path and 404s.
            $href = empty($m['url']) ? 'https://' . $shown : $shown;
            return '<a href="' . $href . '" target="_blank" rel="noopener">' . $shown . '</a>' . $tail;
        },
        $safe
    ) ?? $safe;

    return nl2br($safe);
}
