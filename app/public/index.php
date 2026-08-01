<?php
/**
 * A&B First Aid Training - SMS core (demo). Front controller.
 * Run: php -S 0.0.0.0:8080 -t app/public
 */
declare(strict_types=1);
session_start();
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/helpers.php';

$r = $_GET['r'] ?? 'dashboard';

/* ---- auth ---- */
if ($r === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $st = db()->prepare("SELECT * FROM users WHERE email=? AND active=1");
        $st->execute([trim($_POST['email'] ?? '')]);
        $u = $st->fetch();
        if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
            $_SESSION['uid'] = $u['id']; redirect('?r=dashboard');
        }
        render('login', ['error' => 'Invalid email or password.'], 'Sign in');
        exit;
    }
    render('login', [], 'Sign in'); exit;
}
if ($r === 'logout') { session_destroy(); redirect('?r=login'); }

/* ---- public certificate verification (QR target, no login) ---- */
if ($r === 'verify') {
    $num = trim($_GET['cert'] ?? '');
    $st = db()->prepare("
        SELECT c.*, s.first_name, s.last_name, co.code course_code, co.title course_title
        FROM certificates c JOIN students s ON s.id=c.student_id
        JOIN enrolments e ON e.id=c.enrolment_id JOIN courses co ON co.id=e.course_id
        WHERE c.certificate_number=?");
    $st->execute([$num]);
    $cert = $st->fetch() ?: null;
    render('verify', compact('cert','num'), 'Verify certificate');
    exit;
}

/* ---- public Quality Indicator survey (tokenised link, no login) ---- */
if ($r === 'survey') {
    require __DIR__ . '/../lib/survey.php';
    $pdo = db();
    $token = trim($_GET['t'] ?? '');
    $st = $pdo->prepare("SELECT sv.*, s.first_name, s.last_name, co.code course_code, co.title course_title
        FROM surveys sv
        LEFT JOIN students s ON s.id=sv.student_id
        LEFT JOIN enrolments e ON e.id=sv.enrolment_id
        LEFT JOIN courses co ON co.id=e.course_id
        WHERE sv.token=?");
    $st->execute([$token]);
    $survey = $st->fetch() ?: null;

    if ($survey && $_SERVER['REQUEST_METHOD'] === 'POST' && !$survey['completed_at']) {
        $questions = survey_questions($survey['type']);
        $answers = [];
        foreach ($questions as $code=>$q) {
            $v = (int)($_POST['q'][$code] ?? 0);
            if ($v>=1 && $v<=4) $answers[$code] = $v;
        }
        $upd = $pdo->prepare("UPDATE surveys SET completed_at=datetime('now'), answers=?, comment_best=?, comment_improve=?, respondent_name=COALESCE(NULLIF(?,''),respondent_name), company_name=COALESCE(NULLIF(?,''),company_name) WHERE id=?");
        $upd->execute([
            json_encode($answers), trim($_POST['best'] ?? ''), trim($_POST['improve'] ?? ''),
            trim($_POST['respondent_name'] ?? ''), trim($_POST['company_name'] ?? ''), $survey['id']
        ]);
        $survey = null; // fall through to thank-you
        render('survey', ['survey'=>null,'done'=>true,'token'=>$token], 'Survey submitted');
        exit;
    }
    render('survey', ['survey'=>$survey,'done'=>false,'token'=>$token], 'Quality Indicator Survey');
    exit;
}

/* ================= STUDENT PORTAL (learner-facing, separate login) ================= */
if ($r === 'student_login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $em = trim($_POST['email'] ?? '');
        $st = db()->prepare("SELECT * FROM students WHERE email=?"); $st->execute([$em]); $s = $st->fetch();
        // demo password for the learner portal
        if ($s && ($_POST['password'] ?? '') === 'student123') { $_SESSION['student_id'] = $s['id']; redirect('?r=my'); }
        render('student_login', ['error'=>'Invalid email or password.'], 'Student login'); exit;
    }
    render('student_login', [], 'Student login'); exit;
}
if ($r === 'student_logout') { unset($_SESSION['student_id']); redirect('?r=student_login'); }

if (in_array($r, ['my','mycert'], true)) {
    if (empty($_SESSION['student_id'])) redirect('?r=student_login');
    $pdo = db(); $sid = (int)$_SESSION['student_id'];

    if ($r === 'mycert') {
        $num = trim($_GET['num'] ?? '');
        $c = $pdo->prepare("SELECT * FROM certificates WHERE certificate_number=? AND student_id=?");
        $c->execute([$num, $sid]); $cert = $c->fetch();
        $file = $cert ? __DIR__ . '/../data/' . $cert['file_path'] : '';
        if ($cert && $cert['file_path'] && is_file($file)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="'.$num.'.pdf"');
            readfile($file); exit;
        }
        http_response_code(404); echo 'Certificate not available'; exit;
    }

    // my learning dashboard
    $me = $pdo->prepare("SELECT * FROM students WHERE id=?"); $me->execute([$sid]); $me = $me->fetch();
    $enr = $pdo->prepare("
        SELECT e.*, co.code course_code, co.title course_title, p.title plan_title,
               sc.start_date sched_date, sc.start_time sched_time, l.name location
        FROM enrolments e JOIN courses co ON co.id=e.course_id JOIN plans p ON p.id=e.plan_id
        LEFT JOIN schedules sc ON sc.id=e.schedule_id LEFT JOIN locations l ON l.id=e.location_id
        WHERE e.student_id=? ORDER BY e.start_date DESC");
    $enr->execute([$sid]); $enrolments = $enr->fetchAll();
    $ct = $pdo->prepare("SELECT c.*, co.title course_title FROM certificates c JOIN enrolments e ON e.id=c.enrolment_id JOIN courses co ON co.id=e.course_id WHERE c.student_id=? ORDER BY c.issue_date DESC");
    $ct->execute([$sid]); $mycerts = $ct->fetchAll();
    render('portal', compact('me','enrolments','mycerts'), 'My Learning');
    exit;
}

require_login();
$pdo = db();

switch ($r) {

case 'dashboard':
    $stats = [
        'students'    => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'enrolments'  => $pdo->query("SELECT COUNT(*) FROM enrolments")->fetchColumn(),
        'issued'      => $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn(),
        'upcoming'    => $pdo->query("SELECT COUNT(*) FROM schedules WHERE start_date>='2026-08-01'")->fetchColumn(),
    ];
    // certs expiring within 60 days or already expired (renewal opportunities)
    $expiring = $pdo->query("
        SELECT c.*, s.first_name, s.last_name, s.email, co.title AS course_title
        FROM certificates c
        JOIN students s ON s.id=c.student_id
        JOIN enrolments e ON e.id=c.enrolment_id
        JOIN courses co ON co.id=e.course_id
        WHERE c.expiry_date IS NOT NULL AND date(c.expiry_date) <= date('2026-08-01','+60 day')
        ORDER BY c.expiry_date ASC")->fetchAll();
    $pending = $pdo->query("
        SELECT e.*, s.first_name, s.last_name, co.title AS course_title
        FROM enrolments e JOIN students s ON s.id=e.student_id JOIN courses co ON co.id=e.course_id
        WHERE e.status='complete' ORDER BY e.end_date DESC")->fetchAll();
    render('dashboard', compact('stats','expiring','pending'), 'Dashboard');
    break;

case 'students':
    $q = trim($_GET['q'] ?? '');
    if ($q !== '') {
        $st = $pdo->prepare("SELECT * FROM students WHERE first_name LIKE ? OR last_name LIKE ? OR usi_number LIKE ? OR email LIKE ? ORDER BY last_name");
        $like = "%$q%"; $st->execute([$like,$like,$like,$like]); $rows = $st->fetchAll();
    } else {
        $rows = $pdo->query("SELECT * FROM students ORDER BY last_name, first_name")->fetchAll();
    }
    render('students', compact('rows','q'), 'Students');
    break;

case 'student':
    $id = (int)($_GET['id'] ?? 0);
    $st = $pdo->prepare("SELECT * FROM students WHERE id=?"); $st->execute([$id]); $s = $st->fetch();
    if (!$s) { http_response_code(404); echo 'Not found'; break; }
    $enr = $pdo->prepare("SELECT e.*, co.title course_title, co.code course_code, p.title plan_title
        FROM enrolments e JOIN courses co ON co.id=e.course_id JOIN plans p ON p.id=e.plan_id
        WHERE e.student_id=? ORDER BY e.start_date DESC");
    $enr->execute([$id]); $enrolments = $enr->fetchAll();
    $cert = $pdo->prepare("SELECT c.*, co.title course_title FROM certificates c JOIN enrolments e ON e.id=c.enrolment_id JOIN courses co ON co.id=e.course_id WHERE c.student_id=? ORDER BY c.issue_date DESC");
    $cert->execute([$id]); $certs = $cert->fetchAll();
    render('student', compact('s','enrolments','certs'), $s['first_name'].' '.$s['last_name']);
    break;

case 'enrolments':
    $rows = $pdo->query("
        SELECT e.*, s.first_name, s.last_name, co.code course_code, co.title course_title,
               p.title plan_title, sc.start_date sched_date, l.name location
        FROM enrolments e
        JOIN students s ON s.id=e.student_id
        JOIN courses co ON co.id=e.course_id
        JOIN plans p ON p.id=e.plan_id
        LEFT JOIN schedules sc ON sc.id=e.schedule_id
        LEFT JOIN locations l ON l.id=e.location_id
        ORDER BY e.created_at DESC")->fetchAll();
    render('enrolments', compact('rows'), 'Enrolments');
    break;

case 'schedules':
    $rows = $pdo->query("
        SELECT sc.*, p.title plan_title, co.code course_code, l.name location,
          (SELECT COUNT(*) FROM enrolments e WHERE e.schedule_id=sc.id) AS enrolled
        FROM schedules sc JOIN plans p ON p.id=sc.plan_id JOIN courses co ON co.id=p.course_id
        LEFT JOIN locations l ON l.id=sc.location_id ORDER BY sc.start_date")->fetchAll();
    render('schedules', compact('rows'), 'Schedules');
    break;

case 'generate':
    require __DIR__ . '/../lib/certificate.php';
    $eid = (int)($_GET['enrolment_id'] ?? 0);
    try { $cert = anb_generate_certificate($pdo, $eid); redirect('?r=cert&num='.urlencode($cert['certificate_number'])); }
    catch (Throwable $ex) { echo 'Could not generate: '.e($ex->getMessage()); }
    break;

case 'signoff':
    require __DIR__ . '/../lib/certificate.php';
    $sid = (int)($_GET['schedule_id'] ?? 0);
    // find ready enrolments in this schedule not yet issued
    $q = $pdo->prepare("
        SELECT e.id FROM enrolments e JOIN students s ON s.id=e.student_id
        WHERE e.schedule_id=? AND e.status!='issued'
          AND e.online_complete=1 AND e.avetmiss_complete=1 AND e.id_confirmed=1
          AND e.attendance_marked=1 AND e.tasks_satisfactory=1 AND e.payment_status='paid'
          AND s.usi_number IS NOT NULL AND s.usi_number<>''");
    $q->execute([$sid]);
    $n = 0;
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $eid) { try { anb_generate_certificate($pdo, (int)$eid); $n++; } catch (Throwable $ex) {} }
    $_SESSION['flash'] = "$n certificate(s) generated and issued.";
    redirect('?r=pipeline&schedule_id='.$sid);
    break;

case 'cert':
    $num = trim($_GET['num'] ?? '');
    $c = $pdo->prepare("SELECT * FROM certificates WHERE certificate_number=?");
    $c->execute([$num]); $cert = $c->fetch();
    $file = $cert ? __DIR__ . '/../data/' . $cert['file_path'] : '';
    if ($cert && is_file($file)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="'.$num.'.pdf"');
        readfile($file); exit;
    }
    http_response_code(404); echo 'Certificate not found';
    break;

case 'pipeline':
    $sid = (int)($_GET['schedule_id'] ?? 0);
    $sc = $pdo->prepare("SELECT sc.*, p.title plan_title, co.code course_code, co.title course_title, l.name location
        FROM schedules sc JOIN plans p ON p.id=sc.plan_id JOIN courses co ON co.id=p.course_id
        LEFT JOIN locations l ON l.id=sc.location_id WHERE sc.id=?");
    $sc->execute([$sid]); $schedule = $sc->fetch();
    if (!$schedule) { http_response_code(404); echo 'Schedule not found'; break; }
    $st = $pdo->prepare("
        SELECT e.*, s.first_name, s.last_name, s.usi_number, s.email
        FROM enrolments e JOIN students s ON s.id=e.student_id
        WHERE e.schedule_id=? ORDER BY s.last_name");
    $st->execute([$sid]); $rows = $st->fetchAll();
    render('pipeline', compact('schedule','rows'), 'Class pipeline');
    break;

case 'courses':
    $rows = $pdo->query("
        SELECT co.*, (SELECT COUNT(*) FROM plans p WHERE p.course_id=co.id) plans,
               (SELECT COUNT(*) FROM enrolments e WHERE e.course_id=co.id) enrolments
        FROM courses co ORDER BY co.code")->fetchAll();
    render('courses', compact('rows'), 'Courses');
    break;

case 'certificates':
    $rows = $pdo->query("
        SELECT c.*, s.first_name, s.last_name, co.title course_title, co.code course_code
        FROM certificates c JOIN students s ON s.id=c.student_id
        JOIN enrolments e ON e.id=c.enrolment_id JOIN courses co ON co.id=e.course_id
        ORDER BY c.issue_date DESC")->fetchAll();
    render('certificates', compact('rows'), 'Certificates');
    break;

case 'reminders':
    // renewal reminder engine preview: certs expiring soon / expired, grouped
    $rows = $pdo->query("
        SELECT c.*, s.first_name, s.last_name, s.email, co.title course_title, co.validity_months
        FROM certificates c JOIN students s ON s.id=c.student_id
        JOIN enrolments e ON e.id=c.enrolment_id JOIN courses co ON co.id=e.course_id
        WHERE c.expiry_date IS NOT NULL ORDER BY c.expiry_date ASC")->fetchAll();
    render('reminders', compact('rows'), 'Renewal Reminders');
    break;

case 'trainer':
    // Trainer dashboard: classes assigned to this trainer + readiness
    $uid = (int)$_SESSION['uid'];
    $isTrainer = (current_user()['role'] ?? '') === 'trainer';
    // admins see all classes; trainers see their own
    $sqlWhere = $isTrainer ? "WHERE sc.trainer_id=?" : "";
    $sql = "
        SELECT sc.*, p.title plan_title, co.code course_code, co.title course_title, l.name location,
          (SELECT COUNT(*) FROM enrolments e WHERE e.schedule_id=sc.id) enrolled,
          (SELECT COUNT(*) FROM enrolments e WHERE e.schedule_id=sc.id AND e.attendance_marked=1) present,
          (SELECT COUNT(*) FROM enrolments e WHERE e.schedule_id=sc.id AND e.tasks_satisfactory=1) assessed,
          (SELECT COUNT(*) FROM enrolments e WHERE e.schedule_id=sc.id AND e.status='issued') issued
        FROM schedules sc JOIN plans p ON p.id=sc.plan_id JOIN courses co ON co.id=p.course_id
        LEFT JOIN locations l ON l.id=sc.location_id $sqlWhere ORDER BY sc.start_date";
    $stt = $pdo->prepare($sql);
    $stt->execute($isTrainer ? [$uid] : []);
    $classes = $stt->fetchAll();
    $me = current_user();
    render('trainer', compact('classes','me','isTrainer'), 'Trainer Dashboard');
    break;

case 'surveys':
    require __DIR__ . '/../lib/survey.php';
    survey_backfill($pdo);
    $stats = survey_stats($pdo);
    $rows = $pdo->query("
        SELECT sv.*, s.first_name, s.last_name, co.code course_code
        FROM surveys sv LEFT JOIN students s ON s.id=sv.student_id
        LEFT JOIN enrolments e ON e.id=sv.enrolment_id LEFT JOIN courses co ON co.id=e.course_id
        ORDER BY sv.completed_at IS NULL, sv.completed_at DESC, sv.sent_at DESC")->fetchAll();
    render('surveys', compact('stats','rows'), 'Survey Reporting');
    break;

case 'survey_view':
    require __DIR__ . '/../lib/survey.php';
    $id = (int)($_GET['id'] ?? 0);
    $sv = $pdo->prepare("SELECT sv.*, s.first_name, s.last_name, co.title course_title
        FROM surveys sv LEFT JOIN students s ON s.id=sv.student_id
        LEFT JOIN enrolments e ON e.id=sv.enrolment_id LEFT JOIN courses co ON co.id=e.course_id
        WHERE sv.id=?");
    $sv->execute([$id]); $survey = $sv->fetch();
    if (!$survey) { http_response_code(404); echo 'Not found'; break; }
    render('survey_view', compact('survey'), 'Survey response');
    break;

case 'avetmiss':
    require __DIR__ . '/../lib/avetmiss.php';
    // period selection: default current calendar year
    $year = (int)($_GET['year'] ?? 2026);
    $q    = $_GET['q'] ?? 'full';
    $ranges = [
        'full' => ["$year-01-01", "$year-12-31", "$year — Full year"],
        'q1'   => ["$year-01-01", "$year-03-31", "$year — Q1 (Jan–Mar)"],
        'q2'   => ["$year-04-01", "$year-06-30", "$year — Q2 (Apr–Jun)"],
        'q3'   => ["$year-07-01", "$year-09-30", "$year — Q3 (Jul–Sep)"],
        'q4'   => ["$year-10-01", "$year-12-31", "$year — Q4 (Oct–Dec)"],
    ];
    [$from,$to,$label] = $ranges[$q] ?? $ranges['full'];
    $files   = avet_build_files($pdo, $from, $to);
    $summary = avet_summary($files);
    render('avetmiss', compact('summary','year','q','label','from','to'), 'AVETMISS Reporting');
    break;

case 'avetmiss_export':
    require __DIR__ . '/../lib/avetmiss.php';
    $year = (int)($_GET['year'] ?? 2026);
    $q    = $_GET['q'] ?? 'full';
    $ranges = [
        'full' => ["$year-01-01", "$year-12-31"], 'q1' => ["$year-01-01", "$year-03-31"],
        'q2'   => ["$year-04-01", "$year-06-30"], 'q3' => ["$year-07-01", "$year-09-30"],
        'q4'   => ["$year-10-01", "$year-12-31"],
    ];
    [$from,$to] = $ranges[$q] ?? $ranges['full'];
    $path = avet_build_zip($pdo, $from, $to);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="'.basename($path).'"');
    header('Content-Length: '.filesize($path));
    readfile($path); exit;

case 'avetmiss_preview':
    require __DIR__ . '/../lib/avetmiss.php';
    $year = (int)($_GET['year'] ?? 2026);
    $q    = $_GET['q'] ?? 'full';
    $file = preg_replace('/[^A-Z0-9.]/','',$_GET['file'] ?? 'NAT00120.txt');
    $ranges = [
        'full' => ["$year-01-01", "$year-12-31"], 'q1' => ["$year-01-01", "$year-03-31"],
        'q2'   => ["$year-04-01", "$year-06-30"], 'q3' => ["$year-07-01", "$year-09-30"],
        'q4'   => ["$year-10-01", "$year-12-31"],
    ];
    [$from,$to] = $ranges[$q] ?? $ranges['full'];
    $files = avet_build_files($pdo, $from, $to);
    header('Content-Type: text/plain; charset=utf-8');
    echo $files[$file] ?? '';
    exit;

default:
    http_response_code(404); echo 'Page not found';
}
