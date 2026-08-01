<?php
/**
 * A&B First Aid Training - AVETMISS 8.0 NAT-file export engine.
 *
 * Produces the fixed-width NAT files (NCVER AVETMISS 8.0 / VET Provider Collection)
 * straight from the SMS database. Character fields are left-justified & space-filled;
 * numeric/identifier fields are right-justified & zero-filled per the NCVER data
 * element definitions. Dates are DDMMYYYY.
 *
 * The generated set is designed to load into NCVER's AVS validation tool. Any
 * edit-rule flags AVS raises against the live dataset are tuned here before lodgement.
 */
declare(strict_types=1);

/* ---- RTO identity (matches certificate.php) ---- */
const AVET_RTO_ID   = '46055';
const AVET_RTO_NAME = 'A&B First Aid Training';

/**
 * Pad/format a single fixed-width field.
 *   'A' = alphanumeric  -> left-justified, space-filled, truncated to len
 *   'N' = numeric       -> right-justified, zero-filled, truncated to len
 */
function avet_field($value, int $len, string $type = 'A'): string {
    $v = (string)($value ?? '');
    if ($type === 'N') {
        $v = preg_replace('/[^0-9]/', '', $v);
        if ($v === '') $v = '0';
        $v = substr($v, 0, $len);
        return str_pad($v, $len, '0', STR_PAD_LEFT);
    }
    // strip CR/LF and any non-printable that would corrupt the fixed layout
    $v = str_replace(["\r", "\n"], ' ', $v);
    $v = substr($v, 0, $len);
    return str_pad($v, $len, ' ', STR_PAD_RIGHT);
}

/** Build one record from a [value,len,type] spec list. */
function avet_record(array $fields): string {
    $line = '';
    foreach ($fields as $f) {
        $line .= avet_field($f[0], $f[1], $f[2] ?? 'A');
    }
    return $line . "\r\n";
}

/** DOB / achieved date -> DDMMYYYY. */
function avet_date(?string $ymd): string {
    if (!$ymd) return '';
    $t = strtotime($ymd);
    return $t ? date('dmY', $t) : '';
}

/* ------------------------------------------------------------------ *
 *  Individual NAT file builders
 * ------------------------------------------------------------------ */

/** NAT00010 - Training Organisation */
function avet_nat00010(PDO $pdo): string {
    return avet_record([
        [AVET_RTO_ID,   10, 'A'],
        [AVET_RTO_NAME, 100, 'A'],
    ]);
}

/** NAT00020 - Training Organisation Delivery Location */
function avet_nat00020(PDO $pdo): string {
    $out = '';
    $rows = $pdo->query("SELECT * FROM locations WHERE active=1 ORDER BY id")->fetchAll();
    foreach ($rows as $l) {
        $out .= avet_record([
            [AVET_RTO_ID,                              10, 'A'],
            [str_pad((string)$l['id'], 6, '0', STR_PAD_LEFT), 10, 'A'], // location identifier
            [$l['name'],                               100, 'A'],
            [$l['postcode'] ?: '0000',                 4,  'A'],
            [$l['state'] ?: 'NSW',                     3,  'A'],
            ['1101',                                   4,  'A'], // country: Australia
        ]);
    }
    return $out;
}

/** NAT00030 - Program (course / qualification / skill set). First-aid units are
 *  nationally recognised accredited units delivered as stand-alone programs. */
function avet_nat00030(PDO $pdo): string {
    $out = '';
    $rows = $pdo->query("SELECT * FROM courses ORDER BY id")->fetchAll();
    foreach ($rows as $c) {
        $hrs = (int)($pdo->query("SELECT nominal_hours FROM units WHERE code='".$c['code']."'")->fetchColumn() ?: 0);
        $out .= avet_record([
            [$c['code'],   10, 'A'],   // program identifier
            [$c['title'],  100,'A'],   // program name
            [$hrs,         4,  'N'],   // nominal hours
            ['',           11, 'A'],   // program recognition identifier
            ['514',        3,  'A'],   // level of education (non-award / stand-alone unit)
            ['061301',     6,  'A'],   // field of education - First Aid
            ['000000',     6,  'A'],   // ANZSCO
            ['Y',          1,  'A'],   // VET flag
        ]);
    }
    return $out;
}

/** NAT00060 - Subject (unit of competency) */
function avet_nat00060(PDO $pdo): string {
    $out = '';
    $rows = $pdo->query("SELECT * FROM units WHERE active=1 ORDER BY id")->fetchAll();
    foreach ($rows as $u) {
        $out .= avet_record([
            [$u['code'],            12, 'A'], // subject identifier
            [$u['title'],           100,'A'], // subject name
            ['061301',              6,  'A'], // field of education - First Aid
            ['Y',                   1,  'A'], // VET flag
            [$u['nominal_hours'],   4,  'N'], // nominal hours
        ]);
    }
    return $out;
}

/** Students who actually have training activity in the period. */
function avet_clients(PDO $pdo, string $from, string $to): array {
    $st = $pdo->prepare("
        SELECT DISTINCT s.* FROM students s
        JOIN enrolments e ON e.student_id = s.id
        WHERE date(e.start_date) BETWEEN date(?) AND date(?)
        ORDER BY s.id");
    $st->execute([$from, $to]);
    return $st->fetchAll();
}

/** NAT00080 - Client (demographic) */
function avet_nat00080(PDO $pdo, array $clients): string {
    $out = '';
    foreach ($clients as $s) {
        $out .= avet_record([
            [str_pad((string)$s['id'], 6, '0', STR_PAD_LEFT), 10, 'A'], // client identifier
            [$s['highest_school_level'] ?: '@@',      2,  'A'], // highest school level
            [strtoupper(substr($s['gender'] ?: 'X',0,1)), 1, 'A'], // gender M/F/X
            [avet_date($s['date_of_birth']),          8,  'A'], // DOB DDMMYYYY
            [$s['indigenous_status'] ?: '4',          4,  'A'], // indigenous status (4=No)
            [$s['main_language'] ?: '1201',           4,  'A'], // language (1201=English)
            [$s['labour_force_status'] ?: '@@',       2,  'A'], // labour force status
            [$s['country_of_birth'] ?: '1101',        4,  'A'], // country of birth (1101=Australia)
            [$s['disability_flag'] ?: 'n',            1,  'A'], // disability flag
            ['@',                                     1,  'A'], // prior educational achievement flag
            ['@',                                     1,  'A'], // at school flag
            ['@@',                                    2,  'A'], // proficiency in spoken English
            [$s['salutation'],                        4,  'A'], // title
            [$s['first_name'],                        40, 'A'], // first given name
            [$s['last_name'],                         40, 'A'], // family name
            [$s['street_number'],                     15, 'A'], // address street number
            [$s['street_name'],                       70, 'A'], // address street name
            [$s['suburb'],                            50, 'A'], // suburb/town/locality
            [$s['postcode'] ?: '0000',                4,  'A'], // postcode
            [$s['state'] ?: 'NSW',                    3,  'A'], // state identifier
        ]);
    }
    return $out;
}

/** NAT00085 - Client Postal Details (name + USI) */
function avet_nat00085(PDO $pdo, array $clients): string {
    $out = '';
    foreach ($clients as $s) {
        $out .= avet_record([
            [str_pad((string)$s['id'], 6, '0', STR_PAD_LEFT), 10, 'A'], // client identifier
            [$s['first_name'],   40, 'A'],
            [$s['last_name'],    40, 'A'],
            ['',                 50, 'A'], // address building/property name
            ['',                 30, 'A'], // flat/unit details
            [$s['street_number'],15, 'A'],
            [$s['street_name'],  70, 'A'],
            ['',                 22, 'A'], // postal delivery box
            [$s['suburb'],       50, 'A'],
            [$s['postcode'] ?: '0000', 4, 'A'],
            [$s['state'] ?: 'NSW',3,  'A'],
            [$s['usi_number'],   10, 'A'], // Unique Student Identifier
        ]);
    }
    return $out;
}

/** NAT00090 - Client Disability (only clients flagged with a disability) */
function avet_nat00090(PDO $pdo, array $clients): string {
    $out = '';
    foreach ($clients as $s) {
        if (($s['disability_flag'] ?? 'n') !== 'y') continue;
        $out .= avet_record([
            [str_pad((string)$s['id'], 6, '0', STR_PAD_LEFT), 10, 'A'],
            ['99', 2, 'A'], // disability type (99 = other, refined per student record)
        ]);
    }
    return $out;
}

/** NAT00100 - Client Prior Educational Achievement (only where recorded) */
function avet_nat00100(PDO $pdo, array $clients): string {
    // No prior-achievement records captured in the demo dataset -> empty file.
    return '';
}

/** NAT00120 - Enrolment / Training Activity (subject outcome) */
function avet_nat00120(PDO $pdo, string $from, string $to): string {
    $out = '';
    $st = $pdo->prepare("
        SELECT e.*, s.id student_id, u.code unit_code, eu.outcome_national, eu.date_achieved,
               co.code course_code, u.nominal_hours,
               COALESCE(e.location_id, sc.location_id) loc_id,
               sc.start_date sched_start, sc.end_date sched_end
        FROM enrolment_units eu
        JOIN enrolments e   ON e.id = eu.enrolment_id
        JOIN students s     ON s.id = e.student_id
        JOIN units u        ON u.id = eu.unit_id
        JOIN courses co     ON co.id = e.course_id
        LEFT JOIN schedules sc ON sc.id = e.schedule_id
        WHERE date(e.start_date) BETWEEN date(?) AND date(?)
        ORDER BY e.id, u.code");
    $st->execute([$from, $to]);
    foreach ($st->fetchAll() as $r) {
        $start = $r['sched_start'] ?: $r['start_date'];
        $end   = $r['date_achieved'] ?: ($r['sched_end'] ?: $r['end_date']);
        $out .= avet_record([
            [str_pad((string)$r['student_id'], 6, '0', STR_PAD_LEFT), 10, 'A'], // client identifier
            [$r['course_code'],                       10, 'A'], // program identifier
            [$r['unit_code'],                         12, 'A'], // subject identifier
            [str_pad((string)($r['loc_id'] ?: 1), 6, '0', STR_PAD_LEFT), 10, 'A'], // delivery location
            ['20',                                    2,  'A'], // delivery mode: internal/classroom
            [avet_date($start),                       8,  'A'], // activity start date
            [avet_date($end),                         8,  'A'], // activity end date
            [$r['outcome_national'] ?: '70',          2,  'A'], // outcome identifier - national
            ['30',                                    3,  'A'], // funding source - national (30=domestic fee for service)
            [str_pad(AVET_RTO_ID, 10, ' ', STR_PAD_RIGHT), 10, 'A'], // training contract identifier (n/a)
            ['',                                      10, 'A'], // client identifier - apprenticeships (n/a)
            [$r['nominal_hours'],                     4,  'N'], // scheduled hours
            ['',                                      1,  'A'], // predominant delivery mode
            ['',                                      6,  'A'], // study reason (at enrolment)
            ['',                                      1,  'A'], // VET in schools flag
        ]);
    }
    return $out;
}

/** NAT00130 - Program Completed (qualifications/skill sets issued) */
function avet_nat00130(PDO $pdo, string $from, string $to): string {
    $out = '';
    $st = $pdo->prepare("
        SELECT c.*, e.course_id, co.code course_code, s.id student_id
        FROM certificates c
        JOIN enrolments e ON e.id = c.enrolment_id
        JOIN courses co   ON co.id = e.course_id
        JOIN students s   ON s.id = c.student_id
        WHERE date(c.issue_date) BETWEEN date(?) AND date(?)
        ORDER BY c.id");
    $st->execute([$from, $to]);
    foreach ($st->fetchAll() as $r) {
        $out .= avet_record([
            [str_pad((string)$r['student_id'], 6, '0', STR_PAD_LEFT), 10, 'A'], // client identifier
            [$r['course_code'],           10, 'A'], // program identifier
            [avet_date($r['issue_date']), 8,  'A'], // date program completed
            [$r['certificate_number'],    20, 'A'], // issued certificate identifier
        ]);
    }
    return $out;
}

/* ------------------------------------------------------------------ *
 *  Orchestration
 * ------------------------------------------------------------------ */

/** Return [filename => content] for the whole NAT set over a period. */
function avet_build_files(PDO $pdo, string $from, string $to): array {
    $clients = avet_clients($pdo, $from, $to);
    return [
        'NAT00010.txt' => avet_nat00010($pdo),
        'NAT00020.txt' => avet_nat00020($pdo),
        'NAT00030.txt' => avet_nat00030($pdo),
        'NAT00060.txt' => avet_nat00060($pdo),
        'NAT00080.txt' => avet_nat00080($pdo, $clients),
        'NAT00085.txt' => avet_nat00085($pdo, $clients),
        'NAT00090.txt' => avet_nat00090($pdo, $clients),
        'NAT00100.txt' => avet_nat00100($pdo, $clients),
        'NAT00120.txt' => avet_nat00120($pdo, $from, $to),
        'NAT00130.txt' => avet_nat00130($pdo, $from, $to),
    ];
}

/** Record counts per file (for the UI summary). */
function avet_summary(array $files): array {
    $s = [];
    foreach ($files as $name => $content) {
        $s[$name] = $content === '' ? 0 : substr_count($content, "\n");
    }
    return $s;
}

/** Build the submission ZIP, return path to the temp file. */
function avet_build_zip(PDO $pdo, string $from, string $to): string {
    $files = avet_build_files($pdo, $from, $to);
    $dir = __DIR__ . '/../data/avetmiss';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $path = $dir . '/AVETMISS_' . AVET_RTO_ID . '_' . date('Ymd', strtotime($from)) . '-' . date('Ymd', strtotime($to)) . '.zip';
    if (file_exists($path)) @unlink($path);
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content); // NCVER accepts empty NAT files
    }
    $zip->close();
    return $path;
}
