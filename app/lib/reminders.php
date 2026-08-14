<?php
/**
 * Certificate renewal reminders - the real engine.
 *
 * The old page claimed "Automation ON" and showed rows as "queued", but there
 * was no automation and nothing ever wrote reminder_6wk_sent or
 * reminder_2wk_sent. Nothing was ever emailed. This is the actual thing.
 *
 * Deliberately cautious, because the failure mode here is emailing thousands
 * of people by accident:
 *   - OFF until switched on, and the switch is the client's to press
 *   - only chases certificates that are genuinely coming up, never one that
 *     lapsed years ago - "your certificate expires soon" is untrue and
 *     embarrassing when it went two years back
 *   - each reminder is stamped, so nobody is chased twice
 *   - a daily cap, so a mistake costs a handful of emails and not 8,000
 *   - dry-run first: the preview is the same code path as the send
 */
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

const REM_LEAD_6WK   = 42;   // days before expiry for the first nudge
const REM_LEAD_2WK   = 14;   // days before expiry for the second
const REM_CAP_DEFAULT = 50;  // most emails one run may send

function rem_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $cols = $pdo->query("PRAGMA table_info(certificates)")->fetchAll(PDO::FETCH_COLUMN, 1);
    foreach (['reminder_6wk_sent', 'reminder_2wk_sent'] as $c) {
        if (!in_array($c, $cols, true)) $pdo->exec("ALTER TABLE certificates ADD COLUMN $c TEXT");
    }
    anb_settings_init($pdo);
}

/** Where "re-book here" points. A setting, not a constant, because only the
 *  client knows which page they want students landing on - and their own site
 *  menu has two different Book Now links, one of which 404s. */
const REM_BOOKING_URL_DEFAULT = 'https://www.anbfirstaidtraining.com.au/book-first-aid-course/';

/** @return array{on:bool,cap:int,booking_url:string,last_run:string,last_count:int} */
function rem_config(PDO $pdo): array {
    rem_schema($pdo);
    $s = anb_settings($pdo);
    $url = trim((string)($s['reminders_booking_url'] ?? ''));
    return [
        'on'          => ($s['reminders_on'] ?? '0') === '1',
        'cap'         => max(1, (int)($s['reminders_cap'] ?? REM_CAP_DEFAULT)),
        'booking_url' => $url !== '' ? $url : REM_BOOKING_URL_DEFAULT,
        'last_run'    => (string)($s['reminders_last_run'] ?? ''),
        'last_count'  => (int)($s['reminders_last_count'] ?? 0),
    ];
}

/**
 * Who is due a reminder right now.
 *
 * A certificate qualifies when it expires within the lead time AND has not
 * already expired AND that particular reminder has not been sent. The
 * "not already expired" is the important half - the old page treated an
 * expiry 698 days ago as being "within 6 weeks".
 *
 * @return array<int,array<string,mixed>>
 */
function rem_due(PDO $pdo, int $limit = 0): array {
    rem_schema($pdo);
    $sql = "
        SELECT c.id, c.certificate_number, c.expiry_date, c.reminder_6wk_sent, c.reminder_2wk_sent,
               s.id student_id, s.first_name, s.last_name, s.email,
               co.title course_title, co.code course_code,
               CAST(julianday(date(c.expiry_date)) - julianday(date('now')) AS INTEGER) days_left
        FROM certificates c
        JOIN students s   ON s.id = c.student_id
        JOIN enrolments e ON e.id = c.enrolment_id
        JOIN courses co   ON co.id = e.course_id
        WHERE c.expiry_date IS NOT NULL
          AND TRIM(COALESCE(s.email,'')) <> ''
          AND date(c.expiry_date) >= date('now')
          AND date(c.expiry_date) <= date('now', '+" . REM_LEAD_6WK . " day')
          AND (
                (c.reminder_6wk_sent IS NULL)
             OR (c.reminder_2wk_sent IS NULL
                 AND date(c.expiry_date) <= date('now', '+" . REM_LEAD_2WK . " day'))
          )
        ORDER BY date(c.expiry_date) ASC";
    if ($limit > 0) $sql .= " LIMIT " . $limit;

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $i => $r) {
        // Which of the two this send would be.
        $rows[$i]['which'] = ((int)$r['days_left'] <= REM_LEAD_2WK && empty($r['reminder_2wk_sent']))
            ? '2wk' : '6wk';
    }
    return $rows;
}

/** Certificates that lapsed - a different conversation, never auto-emailed. */
function rem_lapsed_count(PDO $pdo): int {
    rem_schema($pdo);
    return (int)$pdo->query("SELECT COUNT(*) FROM certificates c JOIN students s ON s.id=c.student_id
        WHERE c.expiry_date IS NOT NULL AND date(c.expiry_date) < date('now')
          AND TRIM(COALESCE(s.email,'')) <> ''")->fetchColumn();
}

/**
 * Send one reminder. Returns [ok, message].
 *
 * The stamp is written only after the send reports success, so a failure
 * leaves it due rather than silently marking it done.
 */
function rem_send_one(PDO $pdo, array $row, bool $dryRun = true): array {
    rem_schema($pdo);
    $which = $row['which'] === '2wk' ? '2wk' : '6wk';
    $col   = $which === '2wk' ? 'reminder_2wk_sent' : 'reminder_6wk_sent';

    $name = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
    $vars = [
        'first_name'  => (string)$row['first_name'],
        'last_name'   => (string)$row['last_name'],
        'course'      => (string)$row['course_code'] . ' - ' . (string)$row['course_title'],
        'expiry_date' => date('d-m-Y', strtotime((string)$row['expiry_date'])),
        'days_left'   => (string)(int)$row['days_left'],
        'booking_url' => rem_config($pdo)['booking_url'],
    ];

    $tpl = $pdo->query("SELECT subject, body FROM email_templates WHERE name='Renewal Reminder' LIMIT 1")
               ->fetch(PDO::FETCH_ASSOC);
    $subject = anb_merge($tpl['subject'] ?? 'Your {course} certificate expires on {expiry_date}', $vars);
    $bodyTxt = anb_merge($tpl['body'] ?? (
        "Hi {first_name},\n\n" .
        "Your {course} certificate expires on {expiry_date}.\n\n" .
        "You can re-book here: {booking_url}\n\n" .
        "A&B First Aid Training\nRTO 46055"
    ), $vars);

    if ($dryRun) {
        return [true, 'Would email ' . $name . ' <' . $row['email'] . '> - ' . $subject];
    }

    [$ok, $err] = anb_send_mail($pdo, (string)$row['email'], $subject, nl2br(e($bodyTxt)));
    if ($ok) {
        $pdo->prepare("UPDATE certificates SET $col=? WHERE id=?")
            ->execute([date('Y-m-d H:i:s'), (int)$row['id']]);
        return [true, 'Emailed ' . $name];
    }
    return [false, 'Failed for ' . $name . ': ' . $err];
}

/**
 * One pass of the engine.
 *
 * @return array{sent:int,failed:int,considered:int,lines:array<int,string>,ran:bool,why:string}
 */
function rem_run(PDO $pdo, bool $dryRun = true, ?int $cap = null): array {
    rem_schema($pdo);
    $cfg = rem_config($pdo);
    $cap = $cap ?? $cfg['cap'];

    if (!$dryRun && !$cfg['on']) {
        return ['sent'=>0,'failed'=>0,'considered'=>0,'lines'=>[],'ran'=>false,
                'why'=>'Renewal reminders are switched off.'];
    }

    $due = rem_due($pdo, $cap);
    $sent = 0; $failed = 0; $lines = [];
    foreach ($due as $row) {
        [$ok, $msg] = rem_send_one($pdo, $row, $dryRun);
        $lines[] = ($ok ? '' : 'FAILED: ') . $msg;
        if ($ok) $sent++; else $failed++;
    }

    if (!$dryRun) {
        anb_setting_save($pdo, 'reminders_last_run', date('Y-m-d H:i:s'));
        anb_setting_save($pdo, 'reminders_last_count', (string)$sent);
    }
    return ['sent'=>$sent,'failed'=>$failed,'considered'=>count($due),
            'lines'=>$lines,'ran'=>true,'why'=>''];
}

/**
 * Build a reminder row for one certificate, whether or not it is "due".
 *
 * The scheduled run only picks up certificates inside the 6-week window and
 * not already sent, which is right for automation. But the office needs to be
 * able to send one deliberately - to test it on themselves, or because a
 * student rang - and that should not be blocked by the same rules.
 *
 * @return ?array the row shape rem_send_one() expects, or null
 */
function rem_row_for_cert(PDO $pdo, int $certId): ?array {
    rem_schema($pdo);
    $q = $pdo->prepare("
        SELECT c.id, c.certificate_number, c.expiry_date, c.reminder_6wk_sent, c.reminder_2wk_sent,
               s.id student_id, s.first_name, s.last_name, s.email,
               co.title course_title, co.code course_code,
               CAST(julianday(date(c.expiry_date)) - julianday(date('now')) AS INTEGER) days_left
        FROM certificates c
        JOIN students s   ON s.id = c.student_id
        JOIN enrolments e ON e.id = c.enrolment_id
        JOIN courses co   ON co.id = e.course_id
        WHERE c.id = ?");
    $q->execute([$certId]);
    $r = $q->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;
    $r['which'] = ((int)$r['days_left'] <= REM_LEAD_2WK) ? '2wk' : '6wk';
    return $r;
}
