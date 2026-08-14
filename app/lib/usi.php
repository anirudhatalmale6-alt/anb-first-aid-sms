<?php
/**
 * A&B First Aid Training - real-time USI verification.
 *
 * Wraps UsiClient (the raw web-service plumbing) in the bits the SMS cares
 * about: settings, the students table, and an audit trail.
 *
 * Three modes (settings key `usi_mode`):
 *   off   - the registry is never called; staff tick the box by hand as before
 *   test  - only the government's 3PT sandbox is reachable, from the diagnostic
 *           page. Real students are NEVER sent to 3PT - that is a condition of
 *           using it, and 3PT does not hold real people anyway.
 *   live  - the "Verify with USI Registry" button on the student record works
 *
 * Why the split: 3PT is usable today with the credential the USI Office ships
 * in the developer kit, but production needs A&B's own machine credential
 * created in RAM against ABN 51 660 446 908. Test mode lets the whole thing be
 * proven before that credential exists.
 */
declare(strict_types=1);

require_once __DIR__ . '/usi_client.php';
require_once __DIR__ . '/mailer.php';        // settings table lives there

const ANB_USI_DIR            = __DIR__ . '/../data/usi';
const ANB_USI_TEST_KEYSTORE  = ANB_USI_DIR . '/keystore-test.xml';
const ANB_USI_LIVE_KEYSTORE  = ANB_USI_DIR . '/keystore-live.xml';
const ANB_USI_TOKEN_CACHE    = ANB_USI_DIR . '/token.json';

/* The credential + org code the USI Office ships for sandbox testing. */
const ANB_USI_TEST_CREDENTIAL = 'ABRD:27809366375_USIMachine';
const ANB_USI_TEST_PASSWORD   = 'Password1!';
const ANB_USI_TEST_ORGCODE    = 'VA1803';

/* ------------------------------------------------------------------ schema */

/** Add the USI audit table. Safe to call on every request. */
function anb_usi_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS usi_verify_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER,
        usi TEXT,
        env TEXT,
        org_code TEXT,
        status TEXT,
        first_name_result TEXT,
        family_name_result TEXT,
        dob_result TEXT,
        verified INTEGER DEFAULT 0,
        error TEXT,
        checked_by TEXT,
        checked_at TEXT DEFAULT (datetime('now'))
    )");

    // Added later: why somebody ticked a USI by hand. A tick with no reason
    // behind it is exactly the thing an auditor asks about.
    $cols = $pdo->query("PRAGMA table_info(usi_verify_log)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('note', $cols, true)) {
        $pdo->exec("ALTER TABLE usi_verify_log ADD COLUMN note TEXT");
    }
    // And what we actually sent. "Family name: NoMatch" is only half an answer -
    // the useful half is which spelling was rejected, and the record may well
    // have been edited since the check ran.
    foreach (['sent_first', 'sent_family', 'sent_dob'] as $c) {
        if (!in_array($c, $cols, true)) $pdo->exec("ALTER TABLE usi_verify_log ADD COLUMN $c TEXT");
    }
}

/**
 * How the ticks on file were actually earned.
 *
 * A tick that came across in the migration, or that somebody put there by
 * hand, is not evidence the registry ever agreed. Worth being able to see the
 * split at a glance rather than assuming "verified" means one thing.
 */
function anb_usi_verified_breakdown(PDO $pdo): array {
    $r = $pdo->query("SELECT
            COUNT(*) total,
            SUM(CASE WHEN TRIM(COALESCE(usi_number,''))='' THEN 1 ELSE 0 END) no_usi,
            SUM(CASE WHEN TRIM(COALESCE(usi_number,''))<>'' AND COALESCE(usi_verified,0)=0 THEN 1 ELSE 0 END) unverified,
            SUM(CASE WHEN COALESCE(usi_verified,0)=1 AND usi_verified_method='registry' THEN 1 ELSE 0 END) registry,
            SUM(CASE WHEN COALESCE(usi_verified,0)=1 AND COALESCE(usi_verified_method,'')<>'registry' THEN 1 ELSE 0 END) by_hand
        FROM students")->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach (['total','no_usi','unverified','registry','by_hand'] as $k) $r[$k] = (int)($r[$k] ?? 0);
    return $r;
}

/** Record a tick (or an untick) that a person made themselves, not the registry. */
function anb_usi_log_manual(PDO $pdo, int $studentId, string $usi, bool $verified, string $reason, string $by): void {
    anb_usi_schema($pdo);
    $pdo->prepare("INSERT INTO usi_verify_log
        (student_id, usi, env, org_code, status, verified, checked_by, checked_at, note)
        VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([
            $studentId, $usi, 'by hand', '',
            $verified ? 'Marked verified by hand' : 'Verification cleared by hand',
            $verified ? 1 : 0, $by, date('Y-m-d H:i:s'), $reason,
        ]);
}

/* ---------------------------------------------------------------- settings */

/**
 * @return array{mode:string,env:string,org_code:string,credential_id:string,
 *               password:string,keystore:string,configured:bool,problem:string}
 */
function anb_usi_config(PDO $pdo): array {
    $s = anb_settings($pdo);
    $mode = $s['usi_mode'] ?? 'off';
    if (!in_array($mode, ['off', 'test', 'live'], true)) $mode = 'off';

    $live = ($mode === 'live');
    $cfg = [
        'mode'          => $mode,
        'env'           => $live ? UsiClient::ENV_LIVE : UsiClient::ENV_TEST,
        'org_code'      => $live ? trim((string)($s['usi_org_code'] ?? ''))      : ANB_USI_TEST_ORGCODE,
        'credential_id' => $live ? trim((string)($s['usi_credential_id'] ?? '')) : ANB_USI_TEST_CREDENTIAL,
        'password'      => $live ? (string)($s['usi_keystore_password'] ?? '')   : ANB_USI_TEST_PASSWORD,
        'keystore'      => $live ? ANB_USI_LIVE_KEYSTORE : ANB_USI_TEST_KEYSTORE,
    ];

    $problem = '';
    if (!is_readable($cfg['keystore'])) {
        $problem = $live
            ? 'The live machine credential (keystore-live.xml) has not been uploaded yet.'
            : 'The test credential store is missing from app/data/usi.';
    } elseif ($cfg['org_code'] === '') {
        $problem = 'No organisation code set.';
    } elseif ($cfg['credential_id'] === '') {
        $problem = 'No machine credential ID set.';
    }
    $cfg['configured'] = ($problem === '');
    $cfg['problem']    = $problem;

    return $cfg;
}

/** Build a client for the configured environment. Throws if it is not usable. */
function anb_usi_client(PDO $pdo): UsiClient {
    $cfg = anb_usi_config($pdo);
    if (!$cfg['configured']) {
        throw new UsiClientException($cfg['problem']);
    }
    return new UsiClient(
        $cfg['keystore'],
        $cfg['credential_id'],
        $cfg['password'],
        $cfg['org_code'],
        $cfg['env'],
        ANB_USI_TOKEN_CACHE
    );
}

/* ------------------------------------------------------------- validation */

/**
 * A USI is ten characters from a restricted alphabet, and the last character
 * is a check character. Catching a typo here saves a round trip and tells the
 * student something useful instead of a flat "not found".
 *
 * Returns '' when the format is fine, otherwise the reason.
 */
function anb_usi_format_problem(string $usi): string {
    $usi = strtoupper(trim($usi));
    if ($usi === '')            return 'No USI entered.';
    if (strlen($usi) !== 10)    return 'A USI is exactly 10 characters - this one has ' . strlen($usi) . '.';
    if (preg_match('/[^A-HJ-NP-Z2-9]/', $usi)) {
        return 'A USI never contains the letters I or O, or the digits 0 or 1.';
    }
    return '';
}

/**
 * The registry's schema only accepts yyyy-mm-dd, and it rejects the whole
 * request - not just the date - when it gets anything else. Student records
 * hold a mix of ISO and D/M/Y depending on how the row was created, same as
 * the RTO Data Cloud feed has to cope with.
 *
 * Returns '' when the date cannot be read, so the caller can say so plainly
 * instead of sending rubbish to the registry.
 */
function anb_usi_dob(?string $date): string {
    $date = trim((string)$date);
    if ($date === '') return '';
    if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})#', $date, $m)) {
        $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
    } elseif (preg_match('#^(\d{1,2})[/\-](\d{1,2})[/\-](\d{4})$#', $date, $m)) {
        $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
    } else {
        return '';
    }
    if (!checkdate($mo, $d, $y)) return '';
    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

/* --------------------------------------------------------------- verifying */

/**
 * Verify one student's USI against the registry and record the result.
 *
 * Only ever called in live mode - anb_usi_verify_sandbox() is the test-mode
 * path, so a real student can never be sent to 3PT by accident.
 *
 * @return array{ok:bool,verified:bool,status:string,message:string,detail:array}
 */
function anb_usi_verify_student(PDO $pdo, int $studentId, string $checkedBy = '', bool $allowSandbox = false): array {
    anb_usi_schema($pdo);

    $cfg = anb_usi_config($pdo);
    // $allowSandbox exists so the tooling in app/tools can exercise this exact
    // code path against 3PT. Nothing in the web app ever passes it.
    if ($cfg['mode'] !== 'live' && !($allowSandbox && $cfg['mode'] === 'test')) {
        return anb_usi_result(false, false, '', 'Real-time verification is not switched on yet.', []);
    }

    $st = $pdo->prepare("SELECT id, first_name, last_name, date_of_birth, usi_number FROM students WHERE id=?");
    $st->execute([$studentId]);
    $s = $st->fetch(PDO::FETCH_ASSOC);
    if (!$s) {
        return anb_usi_result(false, false, '', 'Student not found.', []);
    }

    $usi = strtoupper(trim((string)$s['usi_number']));
    $dob = anb_usi_dob((string)$s['date_of_birth']);
    $problem = anb_usi_format_problem($usi);
    if ($problem === '' && empty($s['date_of_birth'])) {
        $problem = 'The registry needs a date of birth to check against.';
    }
    if ($problem === '' && $dob === '') {
        $problem = 'The date of birth on this record (' . (string)$s['date_of_birth']
                 . ') is not a date the registry can read. Fix it on the student record first.';
    }
    if ($problem !== '') {
        anb_usi_log($pdo, $studentId, $usi, $cfg, null, $problem, $checkedBy);
        return anb_usi_result(false, false, '', $problem, []);
    }

    // A person with one legal name is a real thing and the registry has its own
    // field for it - sending their only name as a family name with an empty
    // first name would never match. No first name on file means single name.
    $single = anb_usi_is_placeholder_name((string)$s['first_name'])
            ? trim((string)$s['last_name'])
            : null;
    if ($single === '') $single = null;

    try {
        $client = anb_usi_client($pdo);
        $r = $client->verifyUsi($usi, (string)$s['first_name'], (string)$s['last_name'], $dob, $single);
    } catch (Throwable $e) {
        // An expired or rejected token is worth one clean retry.
        try {
            $client = anb_usi_client($pdo);
            $client->forgetToken();
            $r = $client->verifyUsi($usi, (string)$s['first_name'], (string)$s['last_name'], $dob, $single);
        } catch (Throwable $e2) {
            anb_usi_log($pdo, $studentId, $usi, $cfg, null, $e2->getMessage(), $checkedBy);
            return anb_usi_result(false, false, '', 'Could not reach the USI Registry: ' . $e2->getMessage(), []);
        }
    }

    anb_usi_log($pdo, $studentId, $usi, $cfg, $r, null, $checkedBy, [
        'first'  => $single !== null ? '' : (string)$s['first_name'],
        'family' => $single !== null ? $single : (string)$s['last_name'],
        'dob'    => $dob,
    ]);

    if ($r['verified']) {
        $pdo->prepare("UPDATE students SET usi_verified=1, usi_verified_date=?, usi_verified_method='registry' WHERE id=?")
            ->execute([date('Y-m-d H:i:s'), $studentId]);
        return anb_usi_result(true, true, $r['status'], 'Verified against the USI Registry.', $r);
    }

    // Never leave a stale tick behind a failed check.
    $pdo->prepare("UPDATE students SET usi_verified=0, usi_verified_date=NULL, usi_verified_method=NULL WHERE id=?")
        ->execute([$studentId]);

    return anb_usi_result(true, false, $r['status'], anb_usi_explain($r), $r);
}

/**
 * Turn a registry response into something a staff member can act on.
 *
 * Note the registry reports a suspended USI as Valid - suspension is not
 * exposed by this service - so the only statuses we ever see are Valid,
 * Invalid and Deactivated.
 */
function anb_usi_explain(array $r): string {
    $status = strtolower((string)$r['status']);

    if ($status === 'invalid') {
        return 'The USI Registry does not recognise this USI. Check it has been typed correctly, '
             . 'or ask the student to look it up at usi.gov.au.';
    }
    if ($status === 'deactivated') {
        return 'This USI has been deactivated. The student needs to contact the USI Office before a '
             . 'certificate can be issued against it.';
    }

    $wrong = [];
    if (($r['firstName']  ?? null) !== null && strcasecmp((string)$r['firstName'],  'Match') !== 0) $wrong[] = 'first name';
    if (($r['familyName'] ?? null) !== null && strcasecmp((string)$r['familyName'], 'Match') !== 0) $wrong[] = 'family name';
    if (($r['singleName'] ?? null) !== null && strcasecmp((string)$r['singleName'], 'Match') !== 0) $wrong[] = 'name';
    if (($r['dateOfBirth']?? null) !== null && strcasecmp((string)$r['dateOfBirth'],'Match') !== 0) $wrong[] = 'date of birth';

    if ($wrong) {
        return 'The USI exists, but the ' . anb_usi_join($wrong) . ' on file '
             . (count($wrong) === 1 ? 'does' : 'do') . ' not match the registry. '
             . 'The record must match exactly - middle names, hyphens and married names all count.';
    }
    return 'The registry did not confirm this USI (status: ' . $r['status'] . ').';
}

function anb_usi_join(array $bits): string {
    if (count($bits) === 1) return $bits[0];
    $last = array_pop($bits);
    return implode(', ', $bits) . ' and ' . $last;
}

/**
 * Run the developer-kit test records through the sandbox. This is what proves
 * the connection works without touching a real student.
 *
 * @return array<int,array{usi:string,name:string,expected:string,status:string,verified:bool,error:string}>
 */
function anb_usi_verify_sandbox(PDO $pdo, string $by = ''): array {
    anb_usi_schema($pdo);
    $cfg = anb_usi_config($pdo);
    $cases = [
        ['BNGH7C75FN', 'Maryam', 'Fredrick',  '1966-05-25', null, 'Active record, all details correct'],
        ['BP6LKB3C7X', 'Csenge', 'Gumarsson', '1988-12-26', null, 'Active record, all details correct'],
        ['DG6K5YHPP3', 'Asfaha', 'Loflin',    '1982-12-23', null, 'Deactivated USI'],
        ['QFJEGFSBC4', null, null, '1985-01-01', 'Testsinglename', 'Student with a single legal name'],
        ['R5HQLSWS9Y', null, null, '1963-12-10', 'Testdeactivated', 'Deactivated, single name'],
        ['BNGH7C75FN', 'Maryam', 'Frederick', '1966-05-25', null, 'Family name spelt wrong - must fail'],
        ['BNGH7C75FN', 'Maryam', 'Fredrick',  '1966-05-26', null, 'Date of birth wrong - must fail'],
        ['ZZZZ9999ZZ', 'Maryam', 'Fredrick',  '1966-05-25', null, 'USI that does not exist - must fail'],
    ];

    $out = [];
    try {
        $client = anb_usi_client($pdo);
    } catch (Throwable $e) {
        foreach ($cases as [$usi, $f, $l, $dob, $single, $expected]) {
            $out[] = ['usi' => $usi, 'name' => $single ?? trim("$f $l"), 'expected' => $expected,
                      'status' => '', 'verified' => false, 'error' => $e->getMessage()];
        }
        return $out;
    }

    foreach ($cases as [$usi, $f, $l, $dob, $single, $expected]) {
        $row = ['usi' => $usi, 'name' => $single ?? trim("$f $l"), 'expected' => $expected,
                'status' => '', 'verified' => false, 'error' => ''];
        try {
            $r = $client->verifyUsi($usi, $f, $l, $dob, $single);
            $row['status']   = $r['status'];
            $row['verified'] = $r['verified'];
            $row['detail']   = $r;
            anb_usi_log($pdo, null, $usi, $cfg, $r, null, $by);
        } catch (Throwable $e) {
            $row['error'] = $e->getMessage();
            anb_usi_log($pdo, null, $usi, $cfg, null, $e->getMessage(), $by);
        }
        $out[] = $row;
    }
    return $out;
}

/* -------------------------------------------------------------------- log */

function anb_usi_log(PDO $pdo, ?int $studentId, string $usi, array $cfg, ?array $r, ?string $error, string $by, array $sent = []): void {
    anb_usi_schema($pdo);
    // checked_at is written explicitly rather than left to the column default,
    // because datetime('now') is UTC and the tick on the student record is
    // written in server local time - side by side they looked ten hours apart.
    $pdo->prepare("INSERT INTO usi_verify_log
        (student_id, usi, env, org_code, status, first_name_result, family_name_result, dob_result, verified, error, checked_by, checked_at, sent_first, sent_family, sent_dob)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $studentId, $usi, $cfg['env'], $cfg['org_code'],
            $r['status'] ?? null,
            $r['firstName'] ?? ($r['singleName'] ?? null),
            $r['familyName'] ?? null,
            $r['dateOfBirth'] ?? null,
            !empty($r['verified']) ? 1 : 0,
            $error, $by, date('Y-m-d H:i:s'),
            $sent['first'] ?? null, $sent['family'] ?? null, $sent['dob'] ?? null,
        ]);
}

/** @return array<int,array<string,mixed>> */
function anb_usi_recent_log(PDO $pdo, int $limit = 25): array {
    anb_usi_schema($pdo);
    $q = $pdo->prepare("SELECT l.*, s.first_name, s.last_name
                        FROM usi_verify_log l LEFT JOIN students s ON s.id=l.student_id
                        ORDER BY l.id DESC LIMIT ?");
    $q->execute([$limit]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

function anb_usi_result(bool $ok, bool $verified, string $status, string $message, array $detail): array {
    return ['ok' => $ok, 'verified' => $verified, 'status' => $status, 'message' => $message, 'detail' => $detail];
}

/* ------------------------------------------------------------ bulk verify */

/**
 * Verifying eight thousand students one button at a time is not a job anyone
 * is going to do, so the backlog is worked through in small batches.
 *
 * The queue lives in the database rather than in the browser, which means the
 * run survives a closed laptop, a dropped connection or a PHP timeout - it
 * picks up exactly where it stopped. Each student is only ever taken once.
 */
function anb_usi_bulk_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS usi_bulk_queue (
        student_id INTEGER PRIMARY KEY,
        state      TEXT NOT NULL DEFAULT 'pending',
        verified   INTEGER,
        status     TEXT,
        reason     TEXT,
        checked_at TEXT
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_usi_bulk_state ON usi_bulk_queue(state)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS usi_bulk_run (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        started_at TEXT,
        finished_at TEXT,
        started_by TEXT,
        total      INTEGER DEFAULT 0,
        state      TEXT DEFAULT 'running'
    )");
}

/** The students a bulk run would look at: a USI on file, not yet verified. */
function anb_usi_bulk_candidates(PDO $pdo): array {
    return $pdo->query("SELECT id FROM students
        WHERE TRIM(COALESCE(usi_number,'')) <> '' AND COALESCE(usi_verified,0)=0
        ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
}

/** Queue every candidate and open a run. Any previous queue is cleared. */
function anb_usi_bulk_start(PDO $pdo, string $by): array {
    anb_usi_bulk_schema($pdo);
    $ids = anb_usi_bulk_candidates($pdo);

    $pdo->beginTransaction();
    try {
        $pdo->exec("DELETE FROM usi_bulk_queue");
        $pdo->exec("UPDATE usi_bulk_run SET state='stopped', finished_at='" . date('Y-m-d H:i:s') . "' WHERE state='running'");
        $ins = $pdo->prepare("INSERT INTO usi_bulk_queue (student_id) VALUES (?)");
        foreach ($ids as $id) $ins->execute([(int)$id]);
        $pdo->prepare("INSERT INTO usi_bulk_run (started_at, started_by, total, state) VALUES (?,?,?,'running')")
            ->execute([date('Y-m-d H:i:s'), $by, count($ids)]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return anb_usi_bulk_progress($pdo);
}

function anb_usi_bulk_stop(PDO $pdo): void {
    anb_usi_bulk_schema($pdo);
    $pdo->prepare("UPDATE usi_bulk_run SET state='stopped', finished_at=? WHERE state='running'")
        ->execute([date('Y-m-d H:i:s')]);
}

/**
 * Process the next few students. Kept small on purpose: each one is a round
 * trip to Canberra, and a batch has to finish inside PHP's time limit.
 *
 * The lock is not paranoia - two open browser tabs, or a double-clicked
 * button, would otherwise take the same rows twice and send the registry
 * duplicate traffic under A&B's credential.
 */
function anb_usi_bulk_step(PDO $pdo, string $by, int $batch = 5): array {
    anb_usi_bulk_schema($pdo);

    $run = $pdo->query("SELECT * FROM usi_bulk_run ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$run || $run['state'] !== 'running') {
        return anb_usi_bulk_progress($pdo) + ['ran' => 0, 'note' => 'No run in progress.'];
    }

    $lock = ANB_USI_DIR . '/bulk.lock';
    $fh = @fopen($lock, 'x');       // x = fail if it already exists. Not file_exists() -
    if ($fh === false) {            // two requests can both pass that and both proceed.
        if (is_file($lock) && (time() - (int)filemtime($lock)) > 300) {
            @unlink($lock);          // a batch that died mid-flight must not wedge the run
            $fh = @fopen($lock, 'x');
        }
        if ($fh === false) {
            return anb_usi_bulk_progress($pdo) + ['ran' => 0, 'note' => 'Another batch is running.'];
        }
    }
    fwrite($fh, (string)getmypid());
    fclose($fh);

    $ran = [];
    try {
        $rows = $pdo->query("SELECT q.student_id, s.first_name, s.last_name, s.usi_number
            FROM usi_bulk_queue q JOIN students s ON s.id=q.student_id
            WHERE q.state='pending' ORDER BY q.student_id LIMIT " . max(1, min(25, $batch)))
            ->fetchAll(PDO::FETCH_ASSOC);

        $mark = $pdo->prepare("UPDATE usi_bulk_queue
            SET state='done', verified=?, status=?, reason=?, checked_at=? WHERE student_id=?");

        foreach ($rows as $row) {
            $sid = (int)$row['student_id'];
            try {
                $res = anb_usi_verify_student($pdo, $sid, $by);
                $mark->execute([
                    $res['verified'] ? 1 : 0,
                    (string)$res['status'],
                    $res['verified'] ? '' : (string)$res['message'],
                    date('Y-m-d H:i:s'),
                    $sid,
                ]);
                $ran[] = [
                    'id'       => $sid,
                    'name'     => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                    'usi'      => (string)$row['usi_number'],
                    'verified' => (bool)$res['verified'],
                    'reason'   => $res['verified'] ? '' : (string)$res['message'],
                ];
            } catch (Throwable $e) {
                // One bad record must never stop the run.
                $mark->execute([0, '', 'Error: ' . $e->getMessage(), date('Y-m-d H:i:s'), $sid]);
                $ran[] = [
                    'id'       => $sid,
                    'name'     => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                    'usi'      => (string)$row['usi_number'],
                    'verified' => false,
                    'reason'   => 'Error: ' . $e->getMessage(),
                ];
            }
        }
    } finally {
        @unlink($lock);
    }

    $p = anb_usi_bulk_progress($pdo);
    if ($p['pending'] === 0 && $p['total'] > 0) {
        $pdo->prepare("UPDATE usi_bulk_run SET state='done', finished_at=? WHERE state='running'")
            ->execute([date('Y-m-d H:i:s')]);
        $p = anb_usi_bulk_progress($pdo);
    }
    return $p + ['ran' => count($ran), 'rows' => $ran];
}

/** @return array{total:int,done:int,pending:int,verified:int,failed:int,state:string,started_at:string,finished_at:string} */
function anb_usi_bulk_progress(PDO $pdo): array {
    anb_usi_bulk_schema($pdo);
    $run = $pdo->query("SELECT * FROM usi_bulk_run ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    // verified/failed are read from the student's CURRENT tick, not the verdict
    // the run recorded. Someone who was fixed after the run should show as
    // verified here, otherwise the page keeps reporting yesterday's problem.
    $c = $pdo->query("SELECT
            COUNT(*) total,
            SUM(CASE WHEN q.state='done' THEN 1 ELSE 0 END) done,
            SUM(CASE WHEN q.state='pending' THEN 1 ELSE 0 END) pending,
            SUM(CASE WHEN q.state='done' AND COALESCE(s.usi_verified,0)=1 THEN 1 ELSE 0 END) verified,
            SUM(CASE WHEN q.state='done' AND COALESCE(s.usi_verified,0)=0 THEN 1 ELSE 0 END) failed
        FROM usi_bulk_queue q JOIN students s ON s.id=q.student_id")->fetch(PDO::FETCH_ASSOC);
    return [
        'total'       => (int)($c['total'] ?? 0),
        'done'        => (int)($c['done'] ?? 0),
        'pending'     => (int)($c['pending'] ?? 0),
        'verified'    => (int)($c['verified'] ?? 0),
        'failed'      => (int)($c['failed'] ?? 0),
        'state'       => (string)($run['state'] ?? 'none'),
        'started_at'  => (string)($run['started_at'] ?? ''),
        'finished_at' => (string)($run['finished_at'] ?? ''),
    ];
}

/**
 * The records that did not verify, newest first. This is the whole point of
 * the exercise - these are the rows that would fail an AVETMISS submission.
 *
 * The queue row is a record of what happened during the run, so it keeps
 * saying "failed" forever. The list has to be what is wrong *now*, or it goes
 * on showing a student the day after somebody fixed them - hence the check
 * against the student's current tick rather than the run's verdict alone.
 *
 * @return array<int,array<string,mixed>>
 */
function anb_usi_bulk_problems(PDO $pdo, int $limit = 0): array {
    anb_usi_bulk_schema($pdo);
    $sql = "SELECT q.student_id, q.status, q.reason, q.checked_at,
                   s.first_name, s.last_name, s.date_of_birth, s.usi_number, s.email
            FROM usi_bulk_queue q JOIN students s ON s.id=q.student_id
            WHERE q.state='done' AND q.verified=0 AND COALESCE(s.usi_verified,0)=0
            ORDER BY s.last_name, s.first_name";
    if ($limit > 0) $sql .= ' LIMIT ' . $limit;
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/** Group the failures by what actually went wrong, so the list is actionable. */
function anb_usi_bulk_reason_bucket(string $reason): string {
    $r = strtolower($reason);
    if ($r === '')                                   return 'Other';
    if (str_contains($r, 'does not recognise'))      return 'USI not recognised by the registry';
    if (str_contains($r, 'deactivated'))             return 'USI deactivated';
    if (str_contains($r, 'do not match') || str_contains($r, 'does not match')) return 'Details do not match the registry';
    if (str_contains($r, 'exactly 10 characters') || str_contains($r, 'never contains')) return 'USI is not a valid format';
    if (str_contains($r, 'date of birth'))           return 'Date of birth problem';
    if (str_contains($r, 'could not reach') || str_contains($r, 'error:')) return 'Could not reach the registry';
    return 'Other';
}

/* ================= name repair =================
 *
 * The migration from RTO Data Cloud left a batch of records with the literal
 * text "(unknown)" in the first name and the student's whole name sitting in
 * the family name - "(unknown)" / "Amandeep Kaur". Those can never verify, and
 * they would fail an AVETMISS submission too.
 *
 * The fix is not to guess. We try a rearrangement, ask the registry, and keep
 * it only if the registry says it now matches. Anything the registry does not
 * confirm is left exactly as it was, so nothing invented is ever saved.
 *
 * Trial lookups deliberately do NOT write to usi_verify_log. A student with
 * three candidate spellings would otherwise put three failures in the audit
 * trail for what is really one question. The log entry is written when the
 * change is applied, through the normal verify path.
 */

/** Values that mean "we never got a first name", not an actual name. */
function anb_usi_is_placeholder_name(?string $v): bool {
    $v = strtolower(trim((string)$v));
    return in_array($v, ['', '(unknown)', 'unknown', 'n/a', 'na', 'nil', 'none', '-', '.', '?'], true);
}

function anb_usi_repair_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS usi_name_repair (
        student_id INTEGER PRIMARY KEY,
        state      TEXT DEFAULT 'pending',   -- pending | matched | nomatch | error
        old_first  TEXT,
        old_last   TEXT,
        new_first  TEXT,
        new_last   TEXT,
        new_single TEXT,
        tried      INTEGER DEFAULT 0,
        note       TEXT,
        checked_at TEXT,
        applied_at TEXT
    )");
}

/**
 * Students this can help: a USI on file, not verified, and a first name that is
 * a placeholder rather than a name. Anything else is a genuine spelling
 * difference for a human to look at, not something to rearrange.
 */
function anb_usi_repair_candidates(PDO $pdo): array {
    $rows = $pdo->query("SELECT id, first_name, last_name FROM students
        WHERE TRIM(COALESCE(usi_number,'')) <> '' AND COALESCE(usi_verified,0)=0
        ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $firstBlank = anb_usi_is_placeholder_name($r['first_name']);
        $lastBlank  = anb_usi_is_placeholder_name($r['last_name']);
        // Exactly one side must be a placeholder - if both are, there is no
        // name to work with; if neither is, there is nothing to rearrange.
        if ($firstBlank !== $lastBlank) $out[] = (int)$r['id'];
    }
    return $out;
}

/**
 * The spellings worth asking the registry about, best guess first.
 *
 * Each option is [firstName, familyName, singleName]. A single-name person is
 * a real thing in the registry - it has its own field - so a one-word name is
 * asked as a single name rather than shoved into one half of a pair.
 *
 * Capped at four options so a run stays bounded: 164 students should cost a
 * few hundred lookups, not a few thousand.
 */
function anb_usi_repair_options(string $first, string $last): array {
    $firstBlank = anb_usi_is_placeholder_name($first);
    $lastBlank  = anb_usi_is_placeholder_name($last);
    // Both sides filled means there is a real first and family name already -
    // that is a spelling difference for a person to look at, not something to
    // rearrange. Both blank means there is no name here at all.
    if ($firstBlank === $lastBlank) return [];

    $name = $firstBlank ? $last : $first;
    $w = preg_split('/\s+/', trim(preg_replace('/\s+/', ' ', $name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $n = count($w);

    if ($n === 0) return [];
    if ($n === 1) return [['', '', $w[0]]];
    if ($n === 2) {
        return [
            [$w[0], $w[1], ''],            // Amandeep | Kaur - by far the common case
            [$w[1], $w[0], ''],            // the record was entered the other way round
            ['', '', $w[0] . ' ' . $w[1]], // one legal name that happens to contain a space
        ];
    }
    // Three or more: the split could fall either side of the middle word, and
    // some registries hold the middle name as part of the first name.
    $firstWord = $w[0];
    $lastWord  = $w[$n - 1];
    return [
        [$firstWord, implode(' ', array_slice($w, 1)), ''],
        [implode(' ', array_slice($w, 0, $n - 1)), $lastWord, ''],
        [$firstWord, $lastWord, ''],
        ['', '', implode(' ', $w)],
    ];
}

/** Queue every candidate and open a scan. Any previous scan is cleared. */
function anb_usi_repair_start(PDO $pdo): array {
    anb_usi_repair_schema($pdo);
    $ids = anb_usi_repair_candidates($pdo);

    $pdo->beginTransaction();
    try {
        $pdo->exec("DELETE FROM usi_name_repair");
        $ins = $pdo->prepare("INSERT INTO usi_name_repair (student_id, old_first, old_last)
            SELECT id, first_name, last_name FROM students WHERE id=?");
        foreach ($ids as $id) $ins->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    anb_setting_save($pdo, 'usi_repair_state', 'running');
    anb_setting_save($pdo, 'usi_repair_started', date('Y-m-d H:i:s'));
    anb_setting_save($pdo, 'usi_repair_finished', '');
    return anb_usi_repair_progress($pdo);
}

function anb_usi_repair_stop(PDO $pdo): void {
    anb_setting_save($pdo, 'usi_repair_state', 'stopped');
}

/**
 * Work through a few students: try each candidate spelling against the registry
 * and stop at the first one it confirms. Writes nothing to the student record.
 */
function anb_usi_repair_step(PDO $pdo, int $batch = 3): array {
    anb_usi_repair_schema($pdo);

    $s = anb_settings($pdo);
    if (($s['usi_repair_state'] ?? '') !== 'running') {
        return anb_usi_repair_progress($pdo) + ['ran' => 0, 'note' => 'No scan in progress.'];
    }
    $cfg = anb_usi_config($pdo);
    if ($cfg['mode'] !== 'live') {
        return anb_usi_repair_progress($pdo) + ['ran' => 0, 'note' => 'The USI Registry is not in live mode.'];
    }

    $lock = ANB_USI_DIR . '/repair.lock';
    $fh = @fopen($lock, 'x');
    if ($fh === false) {
        if (is_file($lock) && (time() - (int)filemtime($lock)) > 300) {
            @unlink($lock);
            $fh = @fopen($lock, 'x');
        }
        if ($fh === false) {
            return anb_usi_repair_progress($pdo) + ['ran' => 0, 'note' => 'Another batch is running.'];
        }
    }
    fwrite($fh, (string)getmypid());
    fclose($fh);

    $ran = [];
    try {
        $rows = $pdo->query("SELECT r.student_id, r.old_first, r.old_last,
                   s.usi_number, s.date_of_birth
            FROM usi_name_repair r JOIN students s ON s.id=r.student_id
            WHERE r.state='pending' ORDER BY r.student_id LIMIT " . max(1, min(10, $batch)))
            ->fetchAll(PDO::FETCH_ASSOC);

        $mark = $pdo->prepare("UPDATE usi_name_repair
            SET state=?, new_first=?, new_last=?, new_single=?, tried=?, note=?, checked_at=?
            WHERE student_id=?");

        foreach ($rows as $row) {
            $sid = (int)$row['student_id'];
            $usi = strtoupper(trim((string)$row['usi_number']));
            $dob = anb_usi_dob((string)$row['date_of_birth']);
            $now = date('Y-m-d H:i:s');

            $problem = anb_usi_format_problem($usi);
            if ($problem === '' && $dob === '') {
                $problem = 'The date of birth on this record cannot be read, so the registry cannot check it.';
            }
            if ($problem !== '') {
                $mark->execute(['nomatch', '', '', '', 0, $problem, $now, $sid]);
                continue;
            }

            $options = anb_usi_repair_options((string)$row['old_first'], (string)$row['old_last']);
            $tried = 0; $hit = null; $err = '';
            try {
                $client = anb_usi_client($pdo);
                foreach ($options as [$f, $l, $single]) {
                    $tried++;
                    $r = $client->verifyUsi($usi, $f, $l, $dob, $single !== '' ? $single : null);
                    if ($r['verified']) { $hit = [$f, $l, $single]; break; }
                }
            } catch (Throwable $e) {
                $err = $e->getMessage();
            }

            if ($hit !== null) {
                $mark->execute(['matched', $hit[0], $hit[1], $hit[2], $tried, '', $now, $sid]);
                $ran[] = ['id' => $sid, 'usi' => $usi, 'matched' => true,
                          'was'  => trim((string)$row['old_first'] . ' ' . (string)$row['old_last']),
                          'now'  => $hit[2] !== '' ? $hit[2] : trim($hit[0] . ' ' . $hit[1]),
                          'single' => $hit[2] !== ''];
            } elseif ($err !== '') {
                $mark->execute(['error', '', '', '', $tried, 'Could not reach the registry: ' . $err, $now, $sid]);
                $ran[] = ['id' => $sid, 'usi' => $usi, 'matched' => false, 'note' => 'registry error'];
            } else {
                $mark->execute(['nomatch', '', '', '', $tried,
                    'Tried ' . $tried . ' spelling' . ($tried === 1 ? '' : 's') . ', the registry confirmed none of them.',
                    $now, $sid]);
                $ran[] = ['id' => $sid, 'usi' => $usi, 'matched' => false,
                          'was' => trim((string)$row['old_first'] . ' ' . (string)$row['old_last'])];
            }
        }
    } finally {
        @unlink($lock);
    }

    $p = anb_usi_repair_progress($pdo);
    if ($p['pending'] === 0 && $p['total'] > 0) {
        anb_setting_save($pdo, 'usi_repair_state', 'done');
        anb_setting_save($pdo, 'usi_repair_finished', date('Y-m-d H:i:s'));
        $p = anb_usi_repair_progress($pdo);
    }
    return $p + ['ran' => count($ran), 'rows' => $ran];
}

/** @return array{total:int,done:int,pending:int,matched:int,nomatch:int,applied:int,state:string,started_at:string,finished_at:string} */
function anb_usi_repair_progress(PDO $pdo): array {
    anb_usi_repair_schema($pdo);
    $c = $pdo->query("SELECT
            COUNT(*) total,
            SUM(CASE WHEN state<>'pending' THEN 1 ELSE 0 END) done,
            SUM(CASE WHEN state='pending'  THEN 1 ELSE 0 END) pending,
            SUM(CASE WHEN state='matched'  THEN 1 ELSE 0 END) matched,
            SUM(CASE WHEN state='nomatch' OR state='error' THEN 1 ELSE 0 END) nomatch,
            SUM(CASE WHEN applied_at IS NOT NULL THEN 1 ELSE 0 END) applied
        FROM usi_name_repair")->fetch(PDO::FETCH_ASSOC) ?: [];
    $s = anb_settings($pdo);
    return [
        'total'       => (int)($c['total'] ?? 0),
        'done'        => (int)($c['done'] ?? 0),
        'pending'     => (int)($c['pending'] ?? 0),
        'matched'     => (int)($c['matched'] ?? 0),
        'nomatch'     => (int)($c['nomatch'] ?? 0),
        'applied'     => (int)($c['applied'] ?? 0),
        'state'       => (string)($s['usi_repair_state'] ?? ''),
        'started_at'  => (string)($s['usi_repair_started'] ?? ''),
        'finished_at' => (string)($s['usi_repair_finished'] ?? ''),
    ];
}

/** @return array<int,array<string,mixed>> */
function anb_usi_repair_rows(PDO $pdo, string $state = ''): array {
    anb_usi_repair_schema($pdo);
    $sql = "SELECT r.*, s.usi_number, s.date_of_birth, s.email
            FROM usi_name_repair r JOIN students s ON s.id=r.student_id";
    if ($state !== '') $sql .= " WHERE r.state=" . $pdo->quote($state);
    $sql .= " ORDER BY r.state, s.last_name, s.first_name";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Save the confirmed changes. Only rows the registry matched, and only those
 * not already applied. Each one goes through the normal verify path afterwards
 * so the tick and the audit-log entry are written the same way a staff member
 * pressing the button would write them.
 */
function anb_usi_repair_apply(PDO $pdo, string $by, int $limit = 0): array {
    anb_usi_repair_schema($pdo);
    // Saving re-verifies each student, which is a second or so of network each.
    // A limit keeps one request comfortably inside the host's execution cap;
    // the caller just calls again, because 'applied_at IS NULL' is the resume
    // point and re-applying a row is harmless anyway.
    $sql = "SELECT * FROM usi_name_repair WHERE state='matched' AND applied_at IS NULL ORDER BY student_id";
    if ($limit > 0) $sql .= " LIMIT " . $limit;
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $upd  = $pdo->prepare("UPDATE students SET first_name=?, last_name=? WHERE id=?");
    $done = $pdo->prepare("UPDATE usi_name_repair SET applied_at=? WHERE student_id=?");

    $saved = 0; $verified = 0; $failed = [];
    foreach ($rows as $r) {
        $sid = (int)$r['student_id'];
        // A single-name student keeps the name in the family-name field and
        // carries no first name - that is how the registry itself holds them.
        $first = (string)$r['new_single'] !== '' ? '' : (string)$r['new_first'];
        $last  = (string)$r['new_single'] !== '' ? (string)$r['new_single'] : (string)$r['new_last'];
        try {
            $upd->execute([$first, $last, $sid]);
            $res = anb_usi_verify_student($pdo, $sid, $by);
            // Marked applied only once the verify has actually come back, so a
            // request that dies mid-run leaves the record retryable rather than
            // renamed-but-unverified with nothing left to pick it up.
            $done->execute([date('Y-m-d H:i:s'), $sid]);
            $saved++;
            if ($res['verified']) $verified++;
            else $failed[] = $sid;
        } catch (Throwable $e) {
            $failed[] = $sid;
        }
    }
    return ['saved' => $saved, 'verified' => $verified, 'failed' => count($failed)];
}

/**
 * The last thing the registry said about one student.
 *
 * The detail was always captured - it just lived in the flash message that
 * appeared for one page load after pressing the button. Come back to the
 * record an hour later and all it said was "not verified", which is the least
 * useful half of the answer. This puts the per-field result back on the record.
 */
function anb_usi_last_check(PDO $pdo, int $studentId): ?array {
    anb_usi_schema($pdo);
    $q = $pdo->prepare("SELECT * FROM usi_verify_log WHERE student_id=? ORDER BY id DESC LIMIT 1");
    $q->execute([$studentId]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Turn one logged check into rows for the screen: what was asked, what came back.
 *
 * A null field means the registry was not asked about it - a single-name
 * student has no first/family pair - so it is left out rather than shown as a
 * failure.
 *
 * @return array<int,array{label:string,value:string,ok:bool}>
 */
function anb_usi_check_rows(array $log): array {
    $rows = [];
    $map = [
        'first_name_result'  => 'First name',
        'family_name_result' => 'Family name',
        'dob_result'         => 'Date of birth',
    ];
    foreach ($map as $col => $label) {
        $v = $log[$col] ?? null;
        if ($v === null || $v === '') continue;
        $rows[] = ['label' => $label, 'value' => (string)$v,
                   'ok' => strcasecmp((string)$v, 'Match') === 0];
    }
    return $rows;
}
