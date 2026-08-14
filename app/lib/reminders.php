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
                -- The 6-week nudge belongs to the 6-week window only. Without
                -- the second half of this line, a certificate inside the last
                -- fortnight still matched \"6wk not sent\", so the day after
                -- somebody got their 2-week email they qualified again and got
                -- the 6-week one four days before expiry. 63 people were in
                -- that window when this was found.
                (c.reminder_6wk_sent IS NULL
                 AND date(c.expiry_date) > date('now', '+" . REM_LEAD_2WK . " day'))
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

/* ------------------------------------------------------------------ *
 * Certificates that have already lapsed.
 *
 * A separate engine on purpose. The counting is the part that matters:
 * there are ~2,772 lapsed certificates but only ~972 students who have
 * nothing current, because most of the rest came back and re-certified.
 * Emailing all 2,772 would tell 1,800 currently-certified students that
 * they are out of date.
 *
 * So: one row per student, their most recent certificate, and only where
 * that most recent one has expired.
 * ------------------------------------------------------------------ */

const REM_LAPSED_TEMPLATE = 'Certificate Expired';

/** How long ago their last certificate lapsed. Colder bands are worth
 *  treating differently - a two-year-old lapse is a different letter. */
const REM_LAPSED_BANDS = [
    'm6'  => ['label' => 'Lapsed within 6 months',  'min' => 0,   'max' => 180],
    'm12' => ['label' => 'Lapsed 6 to 12 months ago','min' => 181, 'max' => 365],
    'y2'  => ['label' => 'Lapsed 1 to 2 years ago', 'min' => 366, 'max' => 730],
    'old' => ['label' => 'Lapsed over 2 years ago', 'min' => 731, 'max' => 100000],
];

function rem_lapsed_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    rem_schema($pdo);
    $cols = $pdo->query("PRAGMA table_info(certificates)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('lapsed_sent', $cols, true)) {
        $pdo->exec("ALTER TABLE certificates ADD COLUMN lapsed_sent TEXT");
    }
    // "their newest certificate" is a correlated lookup over ~11,000 rows.
    // Without this the page is a full scan per student.
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cert_student_expiry
                ON certificates(student_id, expiry_date)");
}

/**
 * The shared body of the lapsed query: one row per student, being the
 * newest certificate they hold, where that newest one is in the past.
 */
function rem_lapsed_sql(): string {
    return "
        FROM certificates c
        JOIN students s      ON s.id = c.student_id
        LEFT JOIN enrolments e ON e.id = c.enrolment_id
        LEFT JOIN courses co ON co.id = e.course_id
        WHERE c.expiry_date IS NOT NULL
          AND TRIM(COALESCE(s.email,'')) <> ''
          AND s.email LIKE '%@%'
          AND date(c.expiry_date) < date('now')
          AND c.id = (SELECT c2.id FROM certificates c2
                      WHERE c2.student_id = c.student_id AND c2.expiry_date IS NOT NULL
                      ORDER BY date(c2.expiry_date) DESC, c2.id DESC LIMIT 1)";
}

/** @return array<string,int> band key => number of students */
function rem_lapsed_bands(PDO $pdo): array {
    rem_lapsed_schema($pdo);
    // One pass, bucketed in SQL - running the whole correlated query once per
    // band made the page take four times as long for the same answer.
    $out = array_fill_keys(array_keys(REM_LAPSED_BANDS), 0);
    $rows = $pdo->query("
        SELECT CASE WHEN d <= 180 THEN 'm6'
                    WHEN d <= 365 THEN 'm12'
                    WHEN d <= 730 THEN 'y2'
                    ELSE 'old' END band, COUNT(*) n
        FROM (
            SELECT CAST(julianday(date('now')) - julianday(date(c.expiry_date)) AS INTEGER) d
            " . rem_lapsed_sql() . " AND c.lapsed_sent IS NULL
        )
        GROUP BY band")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        if (isset($out[$r['band']])) $out[$r['band']] = (int)$r['n'];
    }
    return $out;
}

/** @return array<int,array<string,mixed>> */
function rem_lapsed_rows(PDO $pdo, string $band, int $limit = 0): array {
    rem_lapsed_schema($pdo);
    $b = REM_LAPSED_BANDS[$band] ?? null;
    if (!$b) return [];
    $sql = "SELECT c.id, c.certificate_number, c.expiry_date, c.lapsed_sent,
                   s.id student_id, s.first_name, s.last_name, s.email,
                   COALESCE(co.title,'') course_title, COALESCE(co.code, c.type, '') course_code,
                   CAST(julianday(date('now')) - julianday(date(c.expiry_date)) AS INTEGER) days_ago
            " . rem_lapsed_sql() . "
              AND CAST(julianday(date('now')) - julianday(date(c.expiry_date)) AS INTEGER)
                  BETWEEN {$b['min']} AND {$b['max']}
              AND c.lapsed_sent IS NULL
            ORDER BY date(c.expiry_date) DESC";
    if ($limit > 0) $sql .= " LIMIT " . (int)$limit;
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/** The wording offered if the template does not exist yet. Hers to edit. */
function rem_lapsed_default_template(): array {
    return [
        'subject' => 'Your first aid certificate has expired',
        'body'    => "Hi {first_name},\n\n"
            . "Our records show your {course} certificate expired on {expiry_date}, "
            . "so you are no longer currently certified.\n\n"
            . "Getting back up to date is straightforward - the refresher is a short course "
            . "and you can book online here: {booking_link}\n\n"
            . "If you have already renewed elsewhere, please ignore this email.\n\n"
            . "Kind regards,\n\n"
            . "A&B First Aid Training Pty Ltd\nRTO 46055\nM: 0423 427 765\n"
            . "www.anbfirstaidtraining.com.au",
    ];
}

function rem_lapsed_template(PDO $pdo): ?array {
    $q = $pdo->prepare("SELECT * FROM email_templates WHERE name=? LIMIT 1");
    $q->execute([REM_LAPSED_TEMPLATE]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** @return array{0:bool,1:string} */
function rem_lapsed_send_one(PDO $pdo, array $row, bool $dryRun = true): array {
    rem_lapsed_schema($pdo);
    $tpl = rem_lapsed_template($pdo);
    if (!$tpl) return [false, 'The "' . REM_LAPSED_TEMPLATE . '" template does not exist yet.'];

    $name = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
    $vars = [
        'first_name'  => (string)$row['first_name'],
        'last_name'   => (string)$row['last_name'],
        'course'      => trim(trim((string)$row['course_code'] . ' - ' . (string)$row['course_title']), ' -'),
        'expiry_date' => date('d-m-Y', strtotime((string)$row['expiry_date'])),
        'booking_url'  => rem_config($pdo)['booking_url'],
        'booking_link' => rem_config($pdo)['booking_url'],
    ];
    $subject = anb_merge((string)$tpl['subject'], $vars);
    $bodyTxt = anb_merge((string)$tpl['body'], $vars);

    if ($dryRun) return [true, 'Would email ' . $name . ' <' . $row['email'] . '> - ' . $subject];

    [$ok, $err] = anb_send_mail($pdo, (string)$row['email'], $subject, anb_body_html($bodyTxt));
    if ($ok) {
        $pdo->prepare("UPDATE certificates SET lapsed_sent=? WHERE id=?")
            ->execute([date('Y-m-d H:i:s'), (int)$row['id']]);
        return [true, 'Emailed ' . $name];
    }
    return [false, 'Failed for ' . $name . ': ' . $err];
}

/**
 * One batch. Never automatic - there is no schedule behind this, it only
 * runs when somebody presses the button, and only as far as the cap.
 *
 * @return array{sent:int,failed:int,considered:int,lines:array<int,string>,why:string}
 */
function rem_lapsed_run(PDO $pdo, string $band, int $cap, bool $dryRun = true): array {
    rem_lapsed_schema($pdo);
    $cap = max(1, min(200, $cap));
    if (!rem_lapsed_template($pdo)) {
        return ['sent'=>0,'failed'=>0,'considered'=>0,'lines'=>[],
                'why'=>'Create the "' . REM_LAPSED_TEMPLATE . '" template first.'];
    }
    $rows = rem_lapsed_rows($pdo, $band, $cap);
    $sent = 0; $failed = 0; $lines = [];
    foreach ($rows as $r) {
        [$ok, $msg] = rem_lapsed_send_one($pdo, $r, $dryRun);
        $lines[] = ($ok ? '' : 'FAILED: ') . $msg;
        if ($ok) $sent++; else $failed++;
    }
    return ['sent'=>$sent,'failed'=>$failed,'considered'=>count($rows),'lines'=>$lines,'why'=>''];
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
        // Both spellings on purpose. The Email Templates page offers
        // {booking_link} and the saved template uses it, but this code was
        // written with {booking_url} - so the student's email contained the
        // literal text "{booking_link}" and no link at all.
        'booking_url'  => rem_config($pdo)['booking_url'],
        'booking_link' => rem_config($pdo)['booking_url'],
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

    [$ok, $err] = anb_send_mail($pdo, (string)$row['email'], $subject, anb_body_html($bodyTxt));
    if ($ok) {
        $pdo->prepare("UPDATE certificates SET $col=? WHERE id=?")
            ->execute([date('Y-m-d H:i:s'), (int)$row['id']]);
        return [true, 'Emailed ' . $name];
    }
    return [false, 'Failed for ' . $name . ': ' . $err];
}

/**
 * Claim today's run, atomically.
 *
 * Two things can start a run - cPanel cron and the first person to open the
 * dashboard - and cron itself can fire twice if a run is slow. Checking a
 * "last run" setting and then writing it is not enough: two requests can both
 * read the old value before either writes. So the claim IS the write, and the
 * primary key does the arbitration - whoever loses gets a constraint violation
 * and goes home.
 *
 * @return bool true if this request owns today's run
 */
function rem_claim_day(PDO $pdo, string $by): bool {
    rem_schema($pdo);
    $pdo->exec("CREATE TABLE IF NOT EXISTS reminder_runs (
        run_date   TEXT PRIMARY KEY,
        started_at TEXT,
        started_by TEXT,
        sent       INTEGER,
        failed     INTEGER,
        finished_at TEXT
    )");
    // A claim that was never closed means the run died part-way - the dashboard
    // trigger detaches from the request, and a detached process can be killed.
    // Without this, that day would stay claimed with nothing sent and the page
    // would read "going out now" forever. Half an hour is far longer than a
    // capped run takes (50 emails ran in 28 seconds).
    // Compared in Sydney time on purpose: started_at is written with date(),
    // which is local, while SQLite's datetime('now') is UTC.
    $pdo->prepare("DELETE FROM reminder_runs
                   WHERE run_date=? AND finished_at IS NULL AND started_at < ?")
        ->execute([date('Y-m-d'), date('Y-m-d H:i:s', time() - 1800)]);

    try {
        $pdo->prepare("INSERT INTO reminder_runs (run_date, started_at, started_by) VALUES (?,?,?)")
            ->execute([date('Y-m-d'), date('Y-m-d H:i:s'), $by]);
        return true;
    } catch (PDOException $e) {
        return false;   // somebody already has today
    }
}

function rem_close_day(PDO $pdo, int $sent, int $failed): void {
    $pdo->prepare("UPDATE reminder_runs SET sent=?, failed=?, finished_at=? WHERE run_date=?")
        ->execute([$sent, $failed, date('Y-m-d H:i:s'), date('Y-m-d')]);
}

/** Has today's run already happened? For display only - never for gating. */
function rem_today_run(PDO $pdo): ?array {
    rem_schema($pdo);
    $pdo->exec("CREATE TABLE IF NOT EXISTS reminder_runs (
        run_date TEXT PRIMARY KEY, started_at TEXT, started_by TEXT,
        sent INTEGER, failed INTEGER, finished_at TEXT)");
    $q = $pdo->prepare("SELECT * FROM reminder_runs WHERE run_date=?");
    $q->execute([date('Y-m-d')]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * The daily run, wherever it is triggered from.
 *
 * Safe to call on every page load: it does nothing unless the switch is on and
 * unless this request wins the claim for today.
 *
 * @return array{ran:bool,sent:int,failed:int,why:string}
 */
function rem_run_daily(PDO $pdo, string $by): array {
    $cfg = rem_config($pdo);
    if (!$cfg['on'])                     return ['ran'=>false,'sent'=>0,'failed'=>0,'why'=>'switched off'];
    if (!rem_claim_day($pdo, $by))       return ['ran'=>false,'sent'=>0,'failed'=>0,'why'=>'already run today'];

    $res = rem_run($pdo, false);
    rem_close_day($pdo, (int)$res['sent'], (int)$res['failed']);
    return ['ran'=>true,'sent'=>(int)$res['sent'],'failed'=>(int)$res['failed'],'why'=>''];
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
