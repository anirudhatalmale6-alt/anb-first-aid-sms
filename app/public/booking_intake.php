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

// ---- Plan (first active for this course, else create a Website Booking plan) ----
$q=$pdo->prepare("SELECT id FROM plans WHERE course_id=? AND active=1 ORDER BY id LIMIT 1"); $q->execute([$courseId]);
$planId = (int)($q->fetchColumn() ?: 0);
if (!$planId) {
    $pdo->prepare("INSERT INTO plans (course_id,title,delivery_mode,price,active) VALUES (?,?,?,?,1)")
        ->execute([$courseId,'Website Booking','In class',$price]);
    $planId = (int)$pdo->lastInsertId();
}

// ---- Location (match by name/suburb) ----
$locName = $val('location_name');
$locId = null;
if ($locName) { $q=$pdo->prepare("SELECT id FROM locations WHERE active=1 AND (name LIKE ? OR suburb LIKE ?) ORDER BY id LIMIT 1"); $q->execute(['%'.$locName.'%','%'.$locName.'%']); $locId=(int)($q->fetchColumn() ?: 0) ?: null; }

// ---- Schedule (match by plan + date [+ location], else create) ----
$classDate = $val('class_date');
$schedId = null;
if ($classDate) {
    $sql = "SELECT id FROM schedules WHERE plan_id=? AND start_date=?" . ($locId ? " AND location_id=?" : "") . " LIMIT 1";
    $args = $locId ? [$planId,$classDate,$locId] : [$planId,$classDate];
    $q=$pdo->prepare($sql); $q->execute($args); $schedId=(int)($q->fetchColumn() ?: 0) ?: null;
    if (!$schedId) {
        $pdo->prepare("INSERT INTO schedules (plan_id,location_id,name,start_date,end_date,total_places) VALUES (?,?,?,?,?,15)")
            ->execute([$planId,$locId,trim(($courseName?:$course['title']).' '.$classDate),$classDate,$classDate]);
        $schedId = (int)$pdo->lastInsertId();
    }
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

out(['ok'=>true,'student_id'=>$studentId,'enrolment_id'=>$enrolId,'course_id'=>$courseId,'schedule_id'=>$schedId,'location_id'=>$locId]);
