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
    $problem = anb_usi_format_problem($usi);
    if ($problem === '' && empty($s['date_of_birth'])) {
        $problem = 'The registry needs a date of birth to check against.';
    }
    if ($problem !== '') {
        anb_usi_log($pdo, $studentId, $usi, $cfg, null, $problem, $checkedBy);
        return anb_usi_result(false, false, '', $problem, []);
    }

    try {
        $client = anb_usi_client($pdo);
        $r = $client->verifyUsi($usi, (string)$s['first_name'], (string)$s['last_name'], (string)$s['date_of_birth']);
    } catch (Throwable $e) {
        // An expired or rejected token is worth one clean retry.
        try {
            $client = anb_usi_client($pdo);
            $client->forgetToken();
            $r = $client->verifyUsi($usi, (string)$s['first_name'], (string)$s['last_name'], (string)$s['date_of_birth']);
        } catch (Throwable $e2) {
            anb_usi_log($pdo, $studentId, $usi, $cfg, null, $e2->getMessage(), $checkedBy);
            return anb_usi_result(false, false, '', 'Could not reach the USI Registry: ' . $e2->getMessage(), []);
        }
    }

    anb_usi_log($pdo, $studentId, $usi, $cfg, $r, null, $checkedBy);

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

function anb_usi_log(PDO $pdo, ?int $studentId, string $usi, array $cfg, ?array $r, ?string $error, string $by): void {
    anb_usi_schema($pdo);
    $pdo->prepare("INSERT INTO usi_verify_log
        (student_id, usi, env, org_code, status, first_name_result, family_name_result, dob_result, verified, error, checked_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $studentId, $usi, $cfg['env'], $cfg['org_code'],
            $r['status'] ?? null,
            $r['firstName'] ?? ($r['singleName'] ?? null),
            $r['familyName'] ?? null,
            $r['dateOfBirth'] ?? null,
            !empty($r['verified']) ? 1 : 0,
            $error, $by,
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
