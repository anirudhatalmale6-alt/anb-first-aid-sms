<?php
/**
 * Send one student an email from a saved template.
 *
 * The office needs this for the things that do not fit a workflow - a class
 * cancelled, a change of venue, chasing paperwork. Rather than another
 * hard-coded message, it takes whatever templates exist on the Email
 * Templates page, fills in that student's details, and lets the sender read
 * and edit it before anything goes out.
 */
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

function se_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_emails (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        template   TEXT,
        subject    TEXT,
        body       TEXT,
        to_email   TEXT,
        ok         INTEGER DEFAULT 0,
        error      TEXT,
        sent_by    TEXT,
        sent_at    TEXT
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_student_emails ON student_emails(student_id, id DESC)");
}

/**
 * Templates offered in the picker.
 *
 * Portal Access is deliberately excluded: its body carries {password}, and a
 * password only exists at the moment one is generated. Offering it here would
 * send the student an email with an empty password field. The "Send portal
 * access" button does that job properly.
 */
function se_templates(PDO $pdo): array {
    $rows = $pdo->query("SELECT id, name, subject, body FROM email_templates ORDER BY name")
                ->fetchAll(PDO::FETCH_ASSOC);
    return array_values(array_filter($rows, static function (array $t): bool {
        return stripos((string)$t['body'], '{password}') === false;
    }));
}

/**
 * The merge values for this student.
 *
 * Class details come from their next class if they have one, otherwise their
 * most recent - which is what a cancellation or venue-change email needs.
 */
function se_vars(PDO $pdo, array $s): array {
    // locations has no single address column - it is name/suburb/state/postcode.
    $q = $pdo->prepare("SELECT sc.start_date, sc.start_time, co.code, co.title,
                               l.name loc, l.suburb loc_suburb, l.state loc_state, l.postcode loc_pc
        FROM enrolments e
        JOIN courses co ON co.id = e.course_id
        LEFT JOIN schedules sc ON sc.id = e.schedule_id
        LEFT JOIN locations l  ON l.id  = sc.location_id
        WHERE e.student_id = ?
        ORDER BY CASE WHEN date(COALESCE(sc.start_date, e.start_date)) >= date('now') THEN 0 ELSE 1 END,
                 ABS(julianday(COALESCE(sc.start_date, e.start_date)) - julianday(date('now')))
        LIMIT 1");
    $q->execute([(int)$s['id']]);
    $c = $q->fetch(PDO::FETCH_ASSOC) ?: [];

    require_once __DIR__ . '/reminders.php';
    require_once __DIR__ . '/certificate.php';
    $date = (string)($c['start_date'] ?? '');

    // Their latest certificate, so a renewal or re-issue email fills in without
    // the office having to look the number up.
    $cq = $pdo->prepare("SELECT certificate_number, issue_date, expiry_date
                         FROM certificates WHERE student_id=?
                         ORDER BY date(expiry_date) DESC, id DESC LIMIT 1");
    $cq->execute([(int)$s['id']]);
    $cert = $cq->fetch(PDO::FETCH_ASSOC) ?: [];
    $num  = (string)($cert['certificate_number'] ?? '');

    return [
        'first_name'       => (string)$s['first_name'],
        'last_name'        => (string)$s['last_name'],
        'email'            => (string)$s['email'],
        'course'           => trim((string)($c['code'] ?? '') . ' - ' . (string)($c['title'] ?? '')) === '-'
                                ? '' : trim((string)($c['code'] ?? '') . ' - ' . (string)($c['title'] ?? '')),
        'class_date'       => $date !== '' ? date('D j M Y', strtotime($date)) : '',
        'start_date'       => $date !== '' ? date('D j M Y', strtotime($date)) : '',
        'start_time'       => !empty($c['start_time']) ? date('g:ia', strtotime((string)$c['start_time'])) : '',
        'location'         => (string)($c['loc'] ?? ''),
        'location_address' => trim(implode(', ', array_filter([
                                  (string)($c['loc'] ?? ''),
                                  trim(implode(' ', array_filter([
                                      (string)($c['loc_suburb'] ?? ''),
                                      (string)($c['loc_state'] ?? ''),
                                      (string)($c['loc_pc'] ?? ''),
                                  ]))),
                              ]))),
        'certificate_number' => $num,
        'certificate_link' => $num !== '' ? ANB_VERIFY_BASE . '/?r=cert&num=' . urlencode($num) : '',
        'issue_date'       => !empty($cert['issue_date'])
                                ? date('d-m-Y', strtotime((string)$cert['issue_date'])) : '',
        'expiry_date'      => !empty($cert['expiry_date'])
                                ? date('d-m-Y', strtotime((string)$cert['expiry_date'])) : '',
        'booking_link'     => rem_config($pdo)['booking_url'],
        'booking_url'      => rem_config($pdo)['booking_url'],
        'portal_link'      => 'https://sms.anbfirstaidtraining.com.au/?r=student_login',
        'login_url'        => 'https://sms.anbfirstaidtraining.com.au/?r=student_login',
    ];
}

/**
 * Any {tokens} the merge could not fill.
 *
 * Sending "your class on  is cancelled" is worse than not sending, so the
 * screen shows these before the button is pressed.
 *
 * @return array<int,string>
 */
function se_unfilled(string $text): array {
    preg_match_all('/\{[a-z_]+\}/i', $text, $m);
    return array_values(array_unique($m[0]));
}

/** @return array{0:bool,1:string} */
function se_send(PDO $pdo, array $s, string $template, string $subject, string $bodyTxt, string $by): array {
    se_schema($pdo);
    $to = trim((string)$s['email']);
    if ($to === '' || strpos($to, '@') === false) return [false, 'That student has no email address on file.'];
    if (trim($subject) === '' || trim($bodyTxt) === '') return [false, 'The subject and message cannot be empty.'];

    [$ok, $err] = anb_send_mail($pdo, $to, $subject, anb_body_html($bodyTxt));

    $pdo->prepare("INSERT INTO student_emails
        (student_id, template, subject, body, to_email, ok, error, sent_by, sent_at)
        VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([(int)$s['id'], $template, $subject, $bodyTxt, $to,
                   $ok ? 1 : 0, $ok ? null : $err, $by, date('Y-m-d H:i:s')]);

    return $ok ? [true, 'Email sent to ' . $to . '.'] : [false, 'Could not send: ' . $err];
}

/** @return array<int,array<string,mixed>> */
function se_history(PDO $pdo, int $studentId): array {
    se_schema($pdo);
    $q = $pdo->prepare("SELECT * FROM student_emails WHERE student_id=? ORDER BY id DESC LIMIT 50");
    $q->execute([$studentId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
