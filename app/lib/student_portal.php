<?php
// Per-student passwords + portal access emails.
declare(strict_types=1);
require_once __DIR__ . '/mailer.php';

function sp_schema(PDO $pdo): void {
    $cols = $pdo->query("PRAGMA table_info(students)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('password', $cols, true))          $pdo->exec("ALTER TABLE students ADD COLUMN password TEXT");
    if (!in_array('portal_emailed_at', $cols, true)) $pdo->exec("ALTER TABLE students ADD COLUMN portal_emailed_at TEXT");
    // When a send fails we must never fail silently again - record the attempt and the reason.
    if (!in_array('portal_attempted_at', $cols, true)) $pdo->exec("ALTER TABLE students ADD COLUMN portal_attempted_at TEXT");
    if (!in_array('portal_error', $cols, true))        $pdo->exec("ALTER TABLE students ADD COLUMN portal_error TEXT");
    // The office asks "have they even logged in yet?" constantly - it is the
    // difference between chasing a student and chasing an email problem.
    if (!in_array('last_login_at', $cols, true))       $pdo->exec("ALTER TABLE students ADD COLUMN last_login_at TEXT");
}

/**
 * A password a person can read down the phone without spelling every letter.
 * Word + number beats nine random characters when it has to be dictated.
 */
function sp_friendly_pw(): string {
    $words = ['jellyfish','kangaroo','bandage','compass','harbour','lantern','pelican',
              'quokka','rosella','sandpit','tugboat','wattle','anchor','biscuit'];
    return $words[random_int(0, count($words) - 1)] . random_int(100, 999);
}

function sp_genpw(): string {
    $c = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $p = '';
    for ($i = 0; $i < 9; $i++) $p .= $c[random_int(0, strlen($c) - 1)];
    return $p;
}

function sp_set_password(PDO $pdo, int $id, ?string $plain = null): string {
    if ($plain === null || $plain === '') $plain = sp_genpw();
    $pdo->prepare("UPDATE students SET password=? WHERE id=?")->execute([password_hash($plain, PASSWORD_DEFAULT), $id]);
    return $plain;
}

function sp_login_url(): string { return 'https://sms.anbfirstaidtraining.com.au/?r=student_login'; }

// Send the Student Portal Access email to a student. Generates a fresh password.
// Returns [bool ok, string message].
/**
 * @param ?string $plain Use this password instead of generating one. Lets the
 *   office set a password AND email the same one, rather than the email
 *   quietly replacing what they just read out to the student.
 */
function sp_send_portal(PDO $pdo, array $student, ?string $plain = null): array {
    sp_schema($pdo);
    $sid = (int)$student['id'];
    if (empty($student['email']) || strpos($student['email'], '@') === false) {
        sp_record_attempt($pdo, $sid, false, 'No valid email on file');
        return [false, 'No valid email on file'];
    }
    $plain = sp_set_password($pdo, $sid, $plain);
    $vars = [
        'first_name' => $student['first_name'] ?? '',
        'last_name'  => $student['last_name'] ?? '',
        'course'     => $student['course'] ?? '',
        'email'      => $student['email'],
        'password'   => $plain,
        'login_url'  => sp_login_url(),
        'portal_link'=> sp_login_url(),
    ];
    // Prefer the client's saved template; fall back to a built-in.
    $t = $pdo->prepare("SELECT subject, body FROM email_templates WHERE name='Student Portal Access' LIMIT 1");
    $t->execute(); $tpl = $t->fetch(PDO::FETCH_ASSOC);
    if ($tpl && trim((string)$tpl['body']) !== '') {
        $subject = anb_merge($tpl['subject'] ?: 'Your A&B First Aid Training Student Portal Access', $vars);
        $body    = anb_merge($tpl['body'], $vars);
    } else {
        $subject = 'Your A&B First Aid Training Student Portal Access';
        $body    = "Hi {first_name},\n\nHere are your login details for the A&B First Aid Training Student Portal:\n\n"
                 . "Login page: {login_url}\nEmail: {email}\nPassword: {password}\n\n"
                 . "Please log in and complete your online learning before your course. You can change your password after logging in.\n\n"
                 . "Kind regards,\nA&B First Aid Training";
        $subject = anb_merge($subject, $vars); $body = anb_merge($body, $vars);
    }
    $html = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
    [$ok, $msg] = anb_send_mail($pdo, $student['email'], $subject, $html);
    sp_record_attempt($pdo, $sid, $ok, $msg);
    return [$ok, $msg];
}

// Record every send attempt so a failure is always visible on screen, never silent.
function sp_record_attempt(PDO $pdo, int $studentId, bool $ok, string $msg): void {
    try {
        if ($ok) {
            $pdo->prepare("UPDATE students SET portal_emailed_at=datetime('now'), portal_attempted_at=datetime('now'), portal_error=NULL WHERE id=?")
                ->execute([$studentId]);
        } else {
            $pdo->prepare("UPDATE students SET portal_attempted_at=datetime('now'), portal_error=? WHERE id=?")
                ->execute([mb_substr($msg, 0, 300), $studentId]);
        }
    } catch (Throwable $e) { /* recording must never break a send */ }
}

// Students on one class who have NOT successfully received their login email.
function sp_class_pending(PDO $pdo, int $scheduleId): array {
    sp_schema($pdo);
    $q = $pdo->prepare("SELECT s.* FROM enrolments e JOIN students s ON s.id=e.student_id
        WHERE e.schedule_id=? AND (s.portal_emailed_at IS NULL OR s.portal_emailed_at='') AND s.email LIKE '%@%'
        GROUP BY s.id");
    $q->execute([$scheduleId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

// Everyone on one class with a usable email (used by "resend to everyone").
function sp_class_all(PDO $pdo, int $scheduleId): array {
    sp_schema($pdo);
    $q = $pdo->prepare("SELECT s.* FROM enrolments e JOIN students s ON s.id=e.student_id
        WHERE e.schedule_id=? AND s.email LIKE '%@%' GROUP BY s.id");
    $q->execute([$scheduleId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

// How many students still need their portal access email.
function sp_pending_count(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM students WHERE portal_emailed_at IS NULL AND email LIKE '%@%'")->fetchColumn();
}

// Send the next batch (rate-limited). Returns ['sent'=>n,'failed'=>n,'remaining'=>n,'errors'=>[]].
function sp_send_batch(PDO $pdo, int $limit = 60): array {
    sp_schema($pdo);
    $rows = $pdo->query("SELECT * FROM students WHERE portal_emailed_at IS NULL AND email LIKE '%@%' ORDER BY id LIMIT " . (int)$limit)->fetchAll(PDO::FETCH_ASSOC);
    $sent = 0; $failed = 0; $errors = [];
    foreach ($rows as $s) {
        [$ok, $msg] = sp_send_portal($pdo, $s);
        if ($ok) $sent++;
        else { $failed++; if (count($errors) < 5) $errors[] = $msg; }
        usleep(400000); // ~0.4s between sends to respect provider rate limits
    }
    return ['sent' => $sent, 'failed' => $failed, 'remaining' => sp_pending_count($pdo), 'errors' => $errors];
}
