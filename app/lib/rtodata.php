<?php
/**
 * A&B First Aid Training - RTO Data Cloud connector.
 *
 * Pushes enrolments created INSIDE the SMS (staff "New enrolment", class
 * self-enrolment links, group classes) up to RTO Data Cloud, so the USI can be
 * verified there without anyone re-typing the student by hand.
 *
 * Website bookings are NOT pushed from here - the WordPress booking plugin
 * already sends those straight to RTO Data Cloud, and pushing again would
 * create a duplicate. Those enrolments are marked 'skipped_website'.
 *
 * Three modes (settings key `rto_mode`):
 *   off   - do nothing at all
 *   dry   - build + log the exact payload, but never call RTO Data Cloud  (default)
 *   live  - actually create the enrolment in RTO Data Cloud
 *
 * Every push is idempotent: an enrolment that already carries an
 * rto_enrolment_id (or was marked skipped) is never sent again.
 */
declare(strict_types=1);

const ANB_RTO_SUBDOMAIN  = 'abfirstaid';
const ANB_RTO_PUBLIC_KEY = 'eXRsjMhrN5pnx1PrVv5CIWKcLMA925qbY6V0usFx0x23bWHULqfnfYp8cRk89LyQmSsj5u5JCmaHrn6eKZmYHA';
const ANB_RTO_BASE       = 'https://' . ANB_RTO_SUBDOMAIN . '.rtodata.com.au/api/v2/';
const ANB_RTO_ENROL_URL  = ANB_RTO_BASE . 'enrol/';
const ANB_RTO_UPDATE_URL = ANB_RTO_BASE . 'update/';
const ANB_RTO_LIST_URL   = ANB_RTO_BASE . 'list/';
const ANB_RTO_ORIGIN     = 'https://www.anbfirstaidtraining.com.au/';

/* ------------------------------------------------------------------ schema */

/** Add the RTO Data Cloud columns/tables. Safe to call on every request. */
function anb_rto_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $addCol = function (string $table, string $col, string $decl) use ($pdo): void {
        $cols = [];
        foreach ($pdo->query("PRAGMA table_info($table)") as $c) $cols[] = $c['name'];
        if (!in_array($col, $cols, true)) $pdo->exec("ALTER TABLE $table ADD COLUMN $col $decl");
    };

    // Our id  ->  their id
    $addCol('courses',    'rto_course_id',   'TEXT');
    $addCol('plans',      'rto_plan_id',     'TEXT');
    $addCol('schedules',  'rto_schedule_id', 'TEXT');
    $addCol('students',   'rto_person_id',   'TEXT');
    // Per-enrolment sync state
    $addCol('enrolments', 'rto_enrolment_id', 'TEXT');
    $addCol('enrolments', 'rto_sync_status',  "TEXT");   // pending|synced|failed|skipped_website|skipped_historical
    $addCol('enrolments', 'rto_synced_at',    'TEXT');
    $addCol('enrolments', 'rto_error',        'TEXT');

    $pdo->exec("CREATE TABLE IF NOT EXISTS rto_sync_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        enrolment_id INTEGER, student_id INTEGER,
        mode TEXT, result TEXT,            -- dry|sent|error|skipped
        message TEXT, payload TEXT, response TEXT,
        created_at TEXT DEFAULT (datetime('now')))");

    anb_rto_seed_map($pdo);
}

/**
 * Seed the course/plan id mapping (ours -> RTO Data Cloud's).
 * Only fills blanks, so anything set by hand in the UI is never overwritten.
 */
function anb_rto_seed_map(PDO $pdo): void {
    // Courses, matched on the national code.
    $courses = ['HLTAID009' => '1', 'HLTAID010' => '2', 'HLTAID011' => '3', 'HLTAID012' => '4'];
    $cu = $pdo->prepare("UPDATE courses SET rto_course_id=? WHERE code=? AND (rto_course_id IS NULL OR rto_course_id='')");
    foreach ($courses as $code => $rid) $cu->execute([$rid, $code]);

    // Plans, matched on course code + Express/Regular in our plan title.
    //   HLTAID009 Express=1  Regular=8 | HLTAID011 Express=11 Regular=12
    //   HLTAID012 Express=6  Regular=10 | HLTAID010 (single plan)=2
    $plans = [
        ['HLTAID009', '%Express%', '1'],
        ['HLTAID009', '%Regular%', '8'],
        ['HLTAID011', '%Express%', '11'],
        ['HLTAID011', '%Regular%', '12'],
        ['HLTAID012', '%Express%', '6'],
        ['HLTAID012', '%Regular%', '10'],
    ];
    $pu = $pdo->prepare("UPDATE plans SET rto_plan_id=?
        WHERE (rto_plan_id IS NULL OR rto_plan_id='')
          AND title LIKE ? AND title NOT LIKE '%Migrated%'
          AND course_id IN (SELECT id FROM courses WHERE code=?)");
    foreach ($plans as [$code, $like, $rid]) $pu->execute([$rid, $like, $code]);

    $pdo->prepare("UPDATE plans SET rto_plan_id='2'
        WHERE (rto_plan_id IS NULL OR rto_plan_id='') AND title NOT LIKE '%Migrated%'
          AND course_id IN (SELECT id FROM courses WHERE code='HLTAID010')")->execute();
}

/* ------------------------------------------------------------------- config */

/** Current mode: off | dry | live. Defaults to dry (build the payload, send nothing). */
function anb_rto_mode(PDO $pdo): string {
    require_once __DIR__ . '/mailer.php';       // settings table lives there
    anb_settings_init($pdo);
    $q = $pdo->prepare("SELECT v FROM settings WHERE k='rto_mode'");
    $q->execute();
    $m = (string)($q->fetchColumn() ?: '');
    return in_array($m, ['off', 'dry', 'live'], true) ? $m : 'dry';
}

function anb_rto_set_mode(PDO $pdo, string $mode): void {
    require_once __DIR__ . '/mailer.php';
    anb_settings_init($pdo);
    if (!in_array($mode, ['off', 'dry', 'live'], true)) return;
    $pdo->prepare("INSERT INTO settings (k,v) VALUES ('rto_mode',?) ON CONFLICT(k) DO UPDATE SET v=excluded.v")
        ->execute([$mode]);
}

/* ------------------------------------------------------------------ helpers */

/** RTO Data Cloud wants the DOB as DD-MM-YYYY. Accepts ISO or D/M/Y in. */
function anb_rto_dob(?string $date): string {
    $date = trim((string)$date);
    if ($date === '') return '';
    if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})#', $date, $m)) {
        return sprintf('%02d-%02d-%04d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }
    if (preg_match('#^(\d{1,2})[/\-](\d{1,2})[/\-](\d{4})$#', $date, $m)) {
        return sprintf('%02d-%02d-%04d', (int)$m[1], (int)$m[2], (int)$m[3]);
    }
    return $date;
}

/** Was this enrolment created by a website booking? (already in RTO Data Cloud) */
function anb_rto_is_website_booking(PDO $pdo, int $enrolId): bool {
    try {
        $q = $pdo->prepare("SELECT COUNT(*) FROM booking_intake_log WHERE enrolment_id=?");
        $q->execute([$enrolId]);
        return (int)$q->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
}

/* ------------------------------------------------------------------ payload */

/**
 * Build the exact `values` RTO Data Cloud's enrol endpoint expects for one
 * enrolment, plus any warnings (missing mapping / missing student details).
 *
 * @return array{values:array,warnings:string[],row:array}
 */
function anb_rto_build_payload(PDO $pdo, int $enrolId): array {
    anb_rto_schema($pdo);

    $q = $pdo->prepare("SELECT e.*, s.salutation, s.first_name, s.middle_name, s.last_name,
            s.date_of_birth, s.gender, s.usi_number, s.email, s.mobile_phone,
            s.unit_flat, s.street_number, s.street_name, s.suburb, s.state, s.postcode,
            c.code course_code, c.rto_course_id, p.title plan_title, p.rto_plan_id,
            sc.rto_schedule_id, sc.start_date sched_start
        FROM enrolments e
        JOIN students s ON s.id = e.student_id
        JOIN courses  c ON c.id = e.course_id
        JOIN plans    p ON p.id = e.plan_id
        LEFT JOIN schedules sc ON sc.id = e.schedule_id
        WHERE e.id=?");
    $q->execute([$enrolId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Enrolment not found: ' . $enrolId);

    $warn = [];
    if (!$row['rto_course_id']) $warn[] = 'No RTO Data Cloud course id mapped for ' . $row['course_code'] . '.';
    if (!$row['rto_plan_id'])   $warn[] = 'No RTO Data Cloud plan id mapped for "' . $row['plan_title'] . '".';
    if (!$row['rto_schedule_id']) $warn[] = 'No RTO Data Cloud class (schedule) matched - the student will land in RTO Data Cloud without a class date attached.';
    if (trim((string)$row['date_of_birth']) === '') $warn[] = 'Student has no date of birth yet.';
    if (trim((string)$row['usi_number']) === '')    $warn[] = 'Student has not given their USI yet.';

    // Their enrol endpoint needs EVERY field present (blank is fine); missing
    // keys make their server error out with "RTOData Offline".
    $defaults = [
        'course_id'=>'', 'date_of_birth'=>'', 'last_name'=>'', 'plan_id'=>'', 'allergies'=>'',
        'at_school_flag'=>'', 'building_property'=>'', 'client_id'=>'', 'contact'=>'', 'contact_email'=>'',
        'contact_home_phone'=>'', 'contact_mobile_phone'=>'', 'contact_work_phone'=>'', 'country'=>'',
        'country_of_birth'=>'', 'current_school_level'=>'', 'custom'=>'', 'disability_flag'=>'',
        'disability_type'=>'', 'doctor_name'=>'', 'doctor_number'=>'', 'email'=>'', 'end_date'=>'',
        'first_name'=>'', 'gender'=>'', 'highest_school_level'=>'', 'home_phone'=>'', 'import_id'=>'',
        'indigenous_status'=>'', 'individual_needs'=>'', 'insurer'=>'', 'insurer_number'=>'', 'labour_force'=>'',
        'main_language'=>'', 'medicare'=>'', 'medications'=>'', 'middle_name'=>'', 'mobile_phone'=>'',
        'other_email'=>'', 'postal_box'=>'', 'postal_building_property'=>'', 'postal_flag'=>'', 'postal_postcode'=>'',
        'postal_state'=>'', 'postal_street_name'=>'', 'postal_street_number'=>'', 'postal_suburb'=>'',
        'postal_unit_flat'=>'', 'postcode'=>'', 'preferred_name'=>'', 'prior_achievement'=>'',
        'prior_achievement_flag'=>'', 'proficiency_in_english'=>'', 'relationship'=>'', 'salutation'=>'',
        'schedule_id'=>'', 'start_date'=>'', 'state'=>'', 'street_name'=>'', 'street_number'=>'',
        'study_reason'=>'', 'town_of_birth'=>'', 'unit_flat'=>'', 'usi_exemption'=>'', 'usi_number'=>'',
        'website_fields'=>'', 'work_phone'=>'', 'year_completed'=>'', 'suburb'=>'',
    ];

    // Only the fields whose format RTO Data Cloud is confirmed to accept. The
    // coded AVETMISS dropdowns need their internal option codes, so they stay
    // blank here and are filled from our own AVETMISS export instead.
    // NOTE: sending state AND country together makes their enrol error out, so
    // we send state only (every student is Australian).
    $mapped = [
        'course_id'         => (string)$row['rto_course_id'],
        'plan_id'           => (string)$row['rto_plan_id'],
        'schedule_id'       => (string)($row['rto_schedule_id'] ?? ''),
        'date_of_birth'     => anb_rto_dob($row['date_of_birth']),
        'first_name'        => (string)$row['first_name'],
        'middle_name'       => (string)$row['middle_name'],
        'last_name'         => (string)$row['last_name'],
        'salutation'        => (string)$row['salutation'],
        'gender'            => (string)$row['gender'],
        'usi_number'        => (string)$row['usi_number'],
        'unit_flat'         => (string)$row['unit_flat'],
        'street_number'     => (string)$row['street_number'],
        'street_name'       => (string)$row['street_name'],
        'suburb'            => (string)$row['suburb'],
        'state'             => (string)$row['state'],
        'postcode'          => (string)$row['postcode'],
        'email'             => (string)$row['email'],
        'mobile_phone'      => (string)$row['mobile_phone'],
    ];
    foreach ($mapped as $k => $v) { if ($v !== null && $v !== '') $defaults[$k] = $v; }

    return ['values' => $defaults, 'warnings' => $warn, 'row' => $row];
}

/* --------------------------------------------------------------------- push */

/**
 * Push one enrolment to RTO Data Cloud.
 *
 * @param bool $force  re-send even if it already has an RTO id (manual retry)
 * @return array{ok:bool,result:string,message:string,payload?:array,response?:mixed}
 */
function anb_rto_push(PDO $pdo, int $enrolId, bool $force = false): array {
    anb_rto_schema($pdo);
    $mode = anb_rto_mode($pdo);

    $log = function (string $result, string $msg, $payload = null, $resp = null) use ($pdo, $enrolId, $mode): void {
        try {
            $sid = (int)($pdo->query("SELECT student_id FROM enrolments WHERE id=" . $enrolId)->fetchColumn() ?: 0);
            $pdo->prepare("INSERT INTO rto_sync_log (enrolment_id,student_id,mode,result,message,payload,response)
                           VALUES (?,?,?,?,?,?,?)")
                ->execute([$enrolId, $sid, $mode, $result, $msg,
                    $payload === null ? null : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                    $resp === null ? null : (is_string($resp) ? $resp : json_encode($resp, JSON_UNESCAPED_SLASHES))]);
        } catch (Throwable $e) { /* logging must never break an enrolment */ }
    };

    if ($mode === 'off') return ['ok' => true, 'result' => 'skipped', 'message' => 'RTO Data Cloud sync is switched off.'];

    // ---- idempotency guards -------------------------------------------------
    $cur = $pdo->prepare("SELECT rto_enrolment_id, rto_sync_status FROM enrolments WHERE id=?");
    $cur->execute([$enrolId]);
    $cur = $cur->fetch(PDO::FETCH_ASSOC);
    if (!$cur) return ['ok' => false, 'result' => 'error', 'message' => 'Enrolment not found.'];

    if (!$force && (string)$cur['rto_enrolment_id'] !== '' && $cur['rto_enrolment_id'] !== null) {
        return ['ok' => true, 'result' => 'skipped', 'message' => 'Already in RTO Data Cloud (id ' . $cur['rto_enrolment_id'] . ').'];
    }
    if (!$force && in_array((string)$cur['rto_sync_status'], ['skipped_website', 'skipped_historical'], true)) {
        return ['ok' => true, 'result' => 'skipped', 'message' => 'Deliberately skipped (' . $cur['rto_sync_status'] . ').'];
    }
    if (!$force && anb_rto_is_website_booking($pdo, $enrolId)) {
        $pdo->prepare("UPDATE enrolments SET rto_sync_status='skipped_website' WHERE id=?")->execute([$enrolId]);
        $log('skipped', 'Website booking - the WordPress plugin already sent this to RTO Data Cloud.');
        return ['ok' => true, 'result' => 'skipped', 'message' => 'Website booking - already sent by the website plugin.'];
    }

    // ---- build --------------------------------------------------------------
    try {
        $built = anb_rto_build_payload($pdo, $enrolId);
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE enrolments SET rto_sync_status='failed', rto_error=? WHERE id=?")
            ->execute([$e->getMessage(), $enrolId]);
        $log('error', $e->getMessage());
        return ['ok' => false, 'result' => 'error', 'message' => $e->getMessage()];
    }

    // A historical "Migrated" enrolment has no plan mapping - never send those.
    if ($built['values']['course_id'] === '' || $built['values']['plan_id'] === '') {
        $pdo->prepare("UPDATE enrolments SET rto_sync_status='skipped_historical', rto_error=? WHERE id=?")
            ->execute([implode(' ', $built['warnings']), $enrolId]);
        $log('skipped', 'No course/plan mapping - not sent. ' . implode(' ', $built['warnings']));
        return ['ok' => true, 'result' => 'skipped', 'message' => 'No RTO course/plan mapping - not sent.'];
    }

    $payload = [
        'public_key' => ANB_RTO_PUBLIC_KEY,
        'subdomain'  => ANB_RTO_SUBDOMAIN,
        'type'       => 'enrol',
        // their endpoint runs parse_str() on `values`, so it must be a query STRING
        'values'     => http_build_query($built['values']),
    ];

    // ---- dry run ------------------------------------------------------------
    if ($mode === 'dry') {
        $pdo->prepare("UPDATE enrolments SET rto_sync_status='pending', rto_error=? WHERE id=?")
            ->execute([$built['warnings'] ? implode(' ', $built['warnings']) : null, $enrolId]);
        $log('dry', 'DRY RUN - nothing sent. ' . implode(' ', $built['warnings']),
            ['url' => ANB_RTO_ENROL_URL, 'values' => $built['values']]);
        return ['ok' => true, 'result' => 'dry', 'message' => 'Dry run - payload built and logged, nothing sent.',
                'payload' => $built['values'], 'warnings' => $built['warnings']];
    }

    // ---- live ---------------------------------------------------------------
    $resp = anb_rto_request(ANB_RTO_ENROL_URL, $payload);
    if (!$resp['ok']) {
        $pdo->prepare("UPDATE enrolments SET rto_sync_status='failed', rto_error=? WHERE id=?")
            ->execute([$resp['message'], $enrolId]);
        $log('error', $resp['message'], ['url' => ANB_RTO_ENROL_URL, 'values' => $built['values']], $resp['raw'] ?? null);
        return ['ok' => false, 'result' => 'error', 'message' => $resp['message']];
    }

    $data      = $resp['data'];
    $rtoEnrol  = anb_rto_dig($data, ['enrolment_id', 'enrolmentId', 'id']);
    $rtoPerson = anb_rto_dig($data, ['person_id', 'personId', 'client_id', 'student_id']);

    $pdo->prepare("UPDATE enrolments SET rto_enrolment_id=?, rto_sync_status='synced',
                   rto_synced_at=datetime('now'), rto_error=? WHERE id=?")
        ->execute([$rtoEnrol !== null ? (string)$rtoEnrol : '', $built['warnings'] ? implode(' ', $built['warnings']) : null, $enrolId]);
    if ($rtoPerson !== null) {
        $pdo->prepare("UPDATE students SET rto_person_id=? WHERE id=? AND (rto_person_id IS NULL OR rto_person_id='')")
            ->execute([(string)$rtoPerson, (int)$built['row']['student_id']]);
    }
    $log('sent', 'Created in RTO Data Cloud.' . ($rtoEnrol !== null ? ' Enrolment id ' . $rtoEnrol . '.' : ''),
        ['url' => ANB_RTO_ENROL_URL, 'values' => $built['values']], $resp['raw']);

    return ['ok' => true, 'result' => 'sent', 'message' => 'Sent to RTO Data Cloud.',
            'rto_enrolment_id' => $rtoEnrol, 'response' => $data];
}

/** Find the first of $keys anywhere in a (possibly nested) response array. */
function anb_rto_dig($data, array $keys) {
    if (!is_array($data)) return null;
    foreach ($keys as $k) if (isset($data[$k]) && $data[$k] !== '' && !is_array($data[$k])) return $data[$k];
    foreach ($data as $v) { if (is_array($v)) { $hit = anb_rto_dig($v, $keys); if ($hit !== null) return $hit; } }
    return null;
}

/** POST JSON to RTO Data Cloud (plain cURL - this app is not inside WordPress). */
function anb_rto_request(string $url, array $payload, int $timeout = 30): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json', 'Origin: ' . ANB_RTO_ORIGIN],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
    ]);
    $body   = curl_exec($ch);
    $errNo  = curl_errno($ch);
    $errStr = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errNo) return ['ok' => false, 'message' => 'Could not reach RTO Data Cloud: ' . $errStr, 'raw' => null];

    $decoded = json_decode((string)$body, true);
    if ($decoded === null) {
        return ['ok' => false, 'raw' => (string)$body,
                'message' => 'RTO Data Cloud returned a non-JSON response (HTTP ' . $status . ').'];
    }
    $ok = isset($decoded['success']) ? (bool)$decoded['success'] : ($status >= 200 && $status < 300);
    if (!$ok) {
        $msg = (string)($decoded['message'] ?? $decoded['error'] ?? 'RTO Data Cloud rejected the enrolment.');
        return ['ok' => false, 'message' => $msg, 'raw' => (string)$body, 'data' => $decoded];
    }
    return ['ok' => true, 'data' => $decoded, 'raw' => (string)$body, 'message' => 'OK'];
}

/* --------------------------------------------------- class (schedule) mapping */

/**
 * Fetch RTO Data Cloud's live course/plan/class list and fill in
 * schedules.rto_schedule_id for any of our classes that match on
 * plan + start date. Returns [matched, checked, error|null].
 *
 * $listJson lets a test run feed in a saved copy of their list.
 */
function anb_rto_map_schedules(PDO $pdo, ?array $listJson = null): array {
    anb_rto_schema($pdo);

    if ($listJson === null) {
        $resp = anb_rto_request(ANB_RTO_LIST_URL, [
            'public_key' => ANB_RTO_PUBLIC_KEY,
            'subdomain'  => ANB_RTO_SUBDOMAIN,
            'type'       => 'courses-list',
        ], 25);
        if (!$resp['ok']) return ['matched' => 0, 'checked' => 0, 'error' => $resp['message']];
        $listJson = $resp['data']['data'] ?? null;
        if (!is_array($listJson)) return ['matched' => 0, 'checked' => 0, 'error' => 'Unexpected list response.'];
    }

    // their plan id -> [ 'DD-MM-YYYY' => schedule id ]
    $byPlan = [];
    foreach ($listJson as $course) {
        foreach (($course['plans'] ?? []) as $plan) {
            $pid = (string)($plan['id'] ?? '');
            foreach (($plan['schedules'] ?? []) as $s) {
                $d = (string)($s['start_date'] ?? '');
                if ($pid !== '' && $d !== '') $byPlan[$pid][$d] = (string)$s['id'];
            }
        }
    }

    $rows = $pdo->query("SELECT sc.id, sc.start_date, p.rto_plan_id
        FROM schedules sc JOIN plans p ON p.id = sc.plan_id
        WHERE (sc.rto_schedule_id IS NULL OR sc.rto_schedule_id='')
          AND p.rto_plan_id IS NOT NULL AND p.rto_plan_id<>''")->fetchAll(PDO::FETCH_ASSOC);

    $upd = $pdo->prepare("UPDATE schedules SET rto_schedule_id=? WHERE id=?");
    $matched = 0;
    foreach ($rows as $r) {
        $key = anb_rto_dob($r['start_date']);              // same DD-MM-YYYY formatting
        $hit = $byPlan[(string)$r['rto_plan_id']][$key] ?? null;
        if ($hit !== null) { $upd->execute([$hit, (int)$r['id']]); $matched++; }
    }
    return ['matched' => $matched, 'checked' => count($rows), 'error' => null];
}

/**
 * Best-effort hook used by the enrolment routes. Never throws, never blocks an
 * enrolment - if RTO Data Cloud is down the student is still enrolled with us
 * and the push shows up in the RTO Sync screen to retry.
 */
function anb_rto_push_safe(PDO $pdo, int $enrolId): void {
    try { anb_rto_push($pdo, $enrolId); } catch (Throwable $e) { /* never break enrolment */ }
}
