<?php
/**
 * Booking intake endpoint.
 * Receives a completed website booking (server-to-server from the WordPress
 * enrolment plugin) and creates/updates the student + creates the enrolment
 * in the SMS. Token-secured and idempotent (safe to call twice).
 *
 * Runs alongside RTO Data Cloud during the parallel-run phase.
 */
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';

const ANB_INTAKE_SECRET = 'anb_intake_9Xk27Qm4Zp8Rw3Tn6Ls';

header('Content-Type: application/json');
function out(array $a, int $code = 200): void { http_response_code($code); echo json_encode($a); exit; }

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) out(['ok'=>false,'error'=>'bad_json'], 400);
if (!hash_equals(ANB_INTAKE_SECRET, (string)($data['secret'] ?? ''))) out(['ok'=>false,'error'=>'unauthorized'], 403);

$email = strtolower(trim((string)($data['email'] ?? '')));
$first = trim((string)($data['first_name'] ?? ''));
$last  = trim((string)($data['last_name'] ?? ''));
if ($email === '' || $first === '' || $last === '') out(['ok'=>false,'error'=>'missing_required'], 400);

$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS booking_intake_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_id TEXT UNIQUE, student_id INTEGER,
    enrolment_id INTEGER, payload TEXT, created_at TEXT DEFAULT (datetime('now')))");

$txn = trim((string)($data['transaction_id'] ?? ''));

// ---- Idempotency: never create the same booking twice ----
if ($txn !== '') {
    $ex = $pdo->prepare("SELECT student_id, enrolment_id FROM booking_intake_log WHERE transaction_id=?");
    $ex->execute([$txn]);
    if ($row = $ex->fetch(PDO::FETCH_ASSOC)) {
        out(['ok'=>true,'duplicate'=>true,'student_id'=>(int)$row['student_id'],'enrolment_id'=>(int)$row['enrolment_id']]);
    }
}

$val = fn($k) => trim((string)($data[$k] ?? '')) ?: null;

// ---- Course (match by code, then title) ----
$courseCode = (string)($data['course_code'] ?? '');
$courseName = (string)($data['course_name'] ?? '');
$course = null;
if ($courseCode !== '') { $q=$pdo->prepare("SELECT * FROM courses WHERE code=? LIMIT 1"); $q->execute([$courseCode]); $course=$q->fetch(PDO::FETCH_ASSOC) ?: null; }
if (!$course && $courseName !== '') { $q=$pdo->prepare("SELECT * FROM courses WHERE title LIKE ? LIMIT 1"); $q->execute(['%'.$courseName.'%']); $course=$q->fetch(PDO::FETCH_ASSOC) ?: null; }
if (!$course) out(['ok'=>false,'error'=>'course_not_matched','course_code'=>$courseCode,'course_name'=>$courseName], 422);
$courseId = (int)$course['id'];

$price = (float)($data['amount_paid'] ?? $data['price'] ?? 0);

// The RTO Data Cloud columns are added by the connector's own migration; a
// database that has never run it must still take a booking.
$schedCols = $pdo->query("PRAGMA table_info(schedules)")->fetchAll(PDO::FETCH_COLUMN, 1);
$hasRtoCols = in_array('rto_schedule_id', $schedCols, true);
if (!$hasRtoCols) {
    $pdo->exec("ALTER TABLE schedules ADD COLUMN rto_schedule_id TEXT");
    $hasRtoCols = true;
}

// ---- Plan (the one they actually bought, else first active, else create) ----
$rtoPlan = trim((string)($data['rto_plan_id'] ?? ''));
$planId  = 0;
if ($rtoPlan !== '' && in_array('rto_plan_id', $pdo->query("PRAGMA table_info(plans)")->fetchAll(PDO::FETCH_COLUMN, 1), true)) {
    $q=$pdo->prepare("SELECT id FROM plans WHERE course_id=? AND rto_plan_id=? LIMIT 1");
    $q->execute([$courseId,$rtoPlan]);
    $planId = (int)($q->fetchColumn() ?: 0);
}
if (!$planId) { $q=$pdo->prepare("SELECT id FROM plans WHERE course_id=? AND active=1 ORDER BY id LIMIT 1"); $q->execute([$courseId]);
$planId = (int)($q->fetchColumn() ?: 0); }
if (!$planId) {
    $pdo->prepare("INSERT INTO plans (course_id,title,delivery_mode,price,active) VALUES (?,?,?,?,1)")
        ->execute([$courseId,'Website Booking','In class',$price]);
    $planId = (int)$pdo->lastInsertId();
}

/**
 * ---- Location ----
 *
 * A straight LIKE is not enough: RTO Data Cloud sends the suburb as
 * "ST MARYS" while the location here is named "St. Marys", so the full stop
 * alone made every St Marys booking land with no location. Compare on letters
 * and digits only, in both directions, so punctuation and case cannot matter.
 */
$locName = $val('location_name');
$locId   = null;
if ($locName) {
    $norm = static fn(string $s): string => strtolower(preg_replace('/[^a-z0-9]+/i', '', $s) ?? '');
    $want = $norm($locName);
    if ($want !== '') {
        foreach ($pdo->query("SELECT id, name, suburb FROM locations WHERE active=1 ORDER BY id") as $l) {
            foreach ([$l['name'], $l['suburb']] as $cand) {
                $c = $norm((string)$cand);
                if ($c === '') continue;
                if ($c === $want || strpos($c, $want) !== false || strpos($want, $c) !== false) {
                    $locId = (int)$l['id'];
                    break 2;
                }
            }
        }
    }
}

/**
 * The website sends dates as DD-MM-YYYY (that is RTO Data Cloud's format);
 * we store YYYY-MM-DD. Accept either so the caller cannot get it wrong.
 */
$toIso = static function (?string $d): ?string {
    $d = trim((string)$d);
    if ($d === '') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;
    if (preg_match('#^(\d{2})[-/](\d{2})[-/](\d{4})$#', $d, $m)) return $m[3].'-'.$m[2].'-'.$m[1];
    $ts = strtotime($d);
    return $ts ? date('Y-m-d', $ts) : null;
};

$classDate = $toIso($val('class_date'));
$rtoSched  = $val('rto_schedule_id');
$startTime = $val('class_start_time');
$endTime   = $val('class_end_time');

/**
 * Which class did they book?
 *
 * Every booking used to arrive with no class at all, because the website sent
 * the course and the payment but never the date - 414 paid students ended up
 * unreachable from the class screen and could not be signed off. Now that the
 * date does come through, match it to a class that already exists before
 * inventing a new one, or the register fills up with duplicate classes on the
 * same day.
 */
$schedId = null;

// 1. Their own schedule id is the strongest link - no date or name to get wrong.
if ($rtoSched !== null) {
    $q = $pdo->prepare("SELECT id FROM schedules WHERE rto_schedule_id=? LIMIT 1");
    $q->execute([$rtoSched]);
    $schedId = (int)($q->fetchColumn() ?: 0) ?: null;
}

// 2. Otherwise a class for the same COURSE on that date - any plan of that
//    course, because Express and Regular are separate plans of one course and
//    the website's plan is not always the one the class was created under.
if (!$schedId && $classDate) {
    $sql = "SELECT sc.id FROM schedules sc JOIN plans p ON p.id=sc.plan_id
            WHERE p.course_id=? AND sc.start_date=?"
         . ($locId ? " AND sc.location_id=?" : "")
         . " ORDER BY (sc.plan_id=?) DESC, sc.id LIMIT 1";
    $args = $locId ? [$courseId,$classDate,$locId,$planId] : [$courseId,$classDate,$planId];
    $q = $pdo->prepare($sql); $q->execute($args);
    $schedId = (int)($q->fetchColumn() ?: 0) ?: null;
}

// 3. Only now create one, and keep their id on it so the next booking matches.
if (!$schedId && $classDate) {
    $pdo->prepare("INSERT INTO schedules (plan_id,location_id,name,start_date,end_date,start_time,end_time,total_places,rto_schedule_id)
                   VALUES (?,?,?,?,?,?,?,15,?)")
        ->execute([$planId,$locId,trim(($courseName?:$course['title']).' '.$classDate),
                   $classDate,$classDate,$startTime,$endTime,$rtoSched]);
    $schedId = (int)$pdo->lastInsertId();
}

// Backfill their id onto a class we matched by date, so it is exact next time.
if ($schedId && $rtoSched !== null) {
    $pdo->prepare("UPDATE schedules SET rto_schedule_id=? WHERE id=? AND (rto_schedule_id IS NULL OR rto_schedule_id='')")
        ->execute([$rtoSched, $schedId]);
}

// ---- Student (match by email, else create) ----
$stFields = [
    'salutation'=>$val('title'),'first_name'=>$first,'middle_name'=>$val('middle_name'),'last_name'=>$last,
    'date_of_birth'=>$val('dob'),'gender'=>$val('gender'),'usi_number'=>$val('usi_number'),
    'email'=>$email,'mobile_phone'=>$val('mobile_phone'),
    'unit_flat'=>$val('unit_number'),'street_number'=>$val('street_number'),'street_name'=>$val('street_name'),
    'suburb'=>$val('suburb'),'state'=>$val('state'),'postcode'=>$val('postcode'),
    'highest_school_level'=>$val('school_level'),'indigenous_status'=>$val('atsi_status'),
    'labour_force_status'=>$val('employment_status'),'main_language'=>$val('main_language'),
    'disability_flag'=>$val('disability'),
];
$q=$pdo->prepare("SELECT id FROM students WHERE LOWER(email)=? LIMIT 1"); $q->execute([$email]);
$studentId = (int)($q->fetchColumn() ?: 0);
if ($studentId) {
    // update only fields we were given (don't wipe existing data with blanks)
    $set=[]; $args=[];
    foreach ($stFields as $k=>$v) { if ($v !== null && $v !== '') { $set[]="$k=?"; $args[]=$v; } }
    if ($set) { $args[]=$studentId; $pdo->prepare("UPDATE students SET ".implode(',',$set)." WHERE id=?")->execute($args); }
} else {
    $cols=array_keys($stFields); $ph=implode(',',array_fill(0,count($cols),'?'));
    $pdo->prepare("INSERT INTO students (".implode(',',$cols).") VALUES ($ph)")->execute(array_values($stFields));
    $studentId = (int)$pdo->lastInsertId();
}

// ---- Enrolment ----
$payStatus = $price > 0 ? 'paid' : 'unpaid';
$pdo->prepare("INSERT INTO enrolments (student_id,course_id,plan_id,schedule_id,location_id,start_date,end_date,status,amount_due,amount_paid,payment_status)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)")
    ->execute([$studentId,$courseId,$planId,$schedId,$locId,$classDate,$classDate,'enrolled',$price,$price,$payStatus]);
$enrolId = (int)$pdo->lastInsertId();

// ---- Log for idempotency + audit ----
$pdo->prepare("INSERT OR IGNORE INTO booking_intake_log (transaction_id,student_id,enrolment_id,payload) VALUES (?,?,?,?)")
    ->execute([$txn !== '' ? $txn : ('noref_'.$enrolId), $studentId, $enrolId, $raw]);

/**
 * Send the student their login for the online modules.
 *
 * This is what makes the booking self-service: they pay, and the course
 * arrives without anybody in the office pressing anything. It happens AFTER
 * the response has gone back to the website, because the booking must never
 * wait on - or fail because of - a mail server.
 *
 * Guarded by the `intake_send_portal` setting so it can be turned off without
 * a deploy, and skipped for anyone who already has a login, so re-booking a
 * second course never resets a password the student is already using.
 */
$response = ['ok'=>true,'student_id'=>$studentId,'enrolment_id'=>$enrolId,
             'course_id'=>$courseId,'schedule_id'=>$schedId,'location_id'=>$locId];

// NOT `?: '1'` - the stored value for "off" is the string "0", which is falsy
// in PHP, so a default-if-empty would have turned the off switch back on.
$rawSetting = $pdo->query("SELECT v FROM settings WHERE k='intake_send_portal'")->fetchColumn();
$sendPortal = ($rawSetting === false || trim((string)$rawSetting) === '') ? true : (trim((string)$rawSetting) === '1');
$already = $pdo->prepare("SELECT portal_emailed_at FROM students WHERE id=?");
$already->execute([$studentId]);
$sendPortal = $sendPortal && trim((string)$already->fetchColumn()) === '';
$response['portal_email'] = $sendPortal ? 'queued' : 'skipped';

echo json_encode($response);
if (!$sendPortal) exit;

// Detach: the website has its answer, the rest is ours to finish.
if (function_exists('litespeed_finish_request'))      { litespeed_finish_request(); }
elseif (function_exists('fastcgi_finish_request'))    { fastcgi_finish_request(); }
else                                                  { exit; }
ignore_user_abort(true);
set_time_limit(60);

try {
    require_once __DIR__ . '/../lib/mailer.php';
    require_once __DIR__ . '/../lib/student_portal.php';
    sp_schema($pdo);
    $st = $pdo->prepare("SELECT s.*, co.code || ' - ' || co.title AS course
                         FROM students s LEFT JOIN courses co ON co.id=? WHERE s.id=?");
    $st->execute([$courseId, $studentId]);
    if ($stu = $st->fetch(PDO::FETCH_ASSOC)) sp_send_portal($pdo, $stu);
} catch (Throwable $e) {
    // sp_send_portal already records a failure against the student; anything
    // thrown beyond that must not be able to affect a completed booking.
    error_log('booking_intake portal send failed: ' . $e->getMessage());
}
exit;
