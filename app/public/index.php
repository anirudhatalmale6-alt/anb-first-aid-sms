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
    // attach online modules (SCORM/quiz) + this learner's progress to each enrolment
    require_once __DIR__ . '/../lib/lms.php';
    lms_ensure_schema($pdo); lms_seed_demo($pdo);
    foreach ($enrolments as &$en) {
        $mods = lms_course_modules($pdo, (int)$en['course_id']);
        $prog = lms_progress_for_enrolment($pdo, (int)$en['id']);
        $done = 0;
        foreach ($mods as &$m) {
            $m['progress'] = $prog[(int)$m['id']] ?? null;
            if (($m['progress']['status'] ?? '') === 'completed') $done++;
        }
        unset($m);
        $en['modules'] = $mods;
        $en['modules_done'] = $done;
        $en['modules_total'] = count($mods);
    }
    unset($en);
    $ct = $pdo->prepare("SELECT c.*, co.title course_title FROM certificates c JOIN enrolments e ON e.id=c.enrolment_id JOIN courses co ON co.id=e.course_id WHERE c.student_id=? ORDER BY c.issue_date DESC");
    $ct->execute([$sid]); $mycerts = $ct->fetchAll();
    render('portal', compact('me','enrolments','mycerts'), 'My Learning');
    exit;
}

/* ============ LMS player: online modules (SCORM + quiz) — learner or admin preview ============ */
if (in_array($r, ['learn','scorm_track','quiz_submit','form_submit'], true)) {
    require __DIR__ . '/../lib/lms.php';
    $pdo = db();
    lms_ensure_schema($pdo); lms_seed_demo($pdo);

    $isStudent = !empty($_SESSION['student_id']);
    $isStaff   = !empty($_SESSION['uid']);
    if (!$isStudent && !$isStaff) redirect('?r=student_login');

    $moduleId = (int)($_GET['module_id'] ?? $_POST['module_id'] ?? 0);
    $module = lms_module($pdo, $moduleId);
    if (!$module) { http_response_code(404); echo 'Module not found'; exit; }

    // learner's enrolment for this module's course (null when staff previews)
    $enrolment = null;
    if ($isStudent) {
        $eq = $pdo->prepare("SELECT * FROM enrolments WHERE student_id=? AND course_id=? ORDER BY id DESC LIMIT 1");
        $eq->execute([(int)$_SESSION['student_id'], (int)$module['course_id']]);
        $enrolment = $eq->fetch() ?: null;
    }

    if ($r === 'scorm_track') {
        header('Content-Type: application/json');
        if ($enrolment) {
            $status = ($_POST['status'] ?? '') === 'completed' ? 'completed' : 'in_progress';
            $score  = (isset($_POST['score']) && $_POST['score'] !== '') ? (float)$_POST['score'] : null;
            lms_record_progress($pdo, (int)$enrolment['id'], $moduleId, $status, $score);
            echo json_encode(['ok'=>true,'status'=>$status]);
        } else { echo json_encode(['ok'=>true,'preview'=>true]); }
        exit;
    }

    if ($r === 'quiz_submit') {
        $questions = lms_questions($pdo, $moduleId);
        [$pct,$correct,$total,$per] = lms_grade_quiz($questions, $_POST['a'] ?? []);
        $passed = $pct >= (int)$module['pass_mark'];
        if ($enrolment) lms_record_progress($pdo, (int)$enrolment['id'], $moduleId, $passed?'completed':'in_progress', (float)$pct);
        render('learn', ['module'=>$module,'enrolment'=>$enrolment,'questions'=>$questions,
                         'quizResult'=>compact('pct','correct','total','per','passed')], $module['title']);
        exit;
    }

    if ($r === 'form_submit') {
        $data = $_POST['f'] ?? [];
        if ($enrolment) {
            lms_save_form_submission($pdo, (int)$enrolment['id'], $moduleId, $data);
            lms_record_progress($pdo, (int)$enrolment['id'], $moduleId, 'completed', null);
        }
        $submission = $enrolment ? lms_form_submission($pdo, (int)$enrolment['id'], $moduleId) : ['fields'=>$data];
        render('learn', ['module'=>$module,'enrolment'=>$enrolment,'questions'=>[],
                         'submission'=>$submission,'justSaved'=>true], $module['title']);
        exit;
    }

    // r === 'learn'
    $questions = $module['type']==='quiz' ? lms_questions($pdo, $moduleId) : [];
    $submission = ($module['type']==='incident_report' && $enrolment)
        ? lms_form_submission($pdo, (int)$enrolment['id'], $moduleId) : null;
    render('learn', compact('module','enrolment','questions','submission'), $module['title']);
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
    // Next training dates (upcoming sessions with how many booked)
    $next_sessions = $pdo->query("
        SELECT sc.*, co.title AS course_title, co.code AS course_code, l.name AS location,
               u.name AS trainer_name,
               (SELECT COUNT(*) FROM enrolments e WHERE e.schedule_id=sc.id) AS booked
        FROM schedules sc
        JOIN plans p ON p.id=sc.plan_id
        JOIN courses co ON co.id=p.course_id
        LEFT JOIN locations l ON l.id=sc.location_id
        LEFT JOIN users u ON u.id=sc.trainer_id
        WHERE date(sc.start_date) >= date('now')
        ORDER BY date(sc.start_date) ASC, sc.start_time ASC
        LIMIT 8")->fetchAll();
    // How many students in each course
    $course_counts = $pdo->query("
        SELECT co.code, co.title, COUNT(e.id) AS cnt
        FROM courses co LEFT JOIN enrolments e ON e.course_id=co.id
        GROUP BY co.id ORDER BY cnt DESC, co.title ASC")->fetchAll();
    // Class Schedule (agenda list) — day / week / month scope, ?cv=view&d=YYYY-MM-DD
    $cv = $_GET['cv'] ?? 'month';
    if (!in_array($cv, ['day','week','month'], true)) $cv = 'month';
    $anchor = $_GET['d'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$anchor)) $anchor = date('Y-m-d');
    $ats = strtotime($anchor);
    if ($cv === 'day') {
        $cStart = $cEnd = date('Y-m-d', $ats);
        $cLabel = date('l, j F Y', $ats);
        $cPrev  = date('Y-m-d', strtotime($anchor.' -1 day'));
        $cNext  = date('Y-m-d', strtotime($anchor.' +1 day'));
    } elseif ($cv === 'week') {
        $dow    = (int)date('N', $ats);
        $cStart = date('Y-m-d', strtotime($anchor.' -'.($dow-1).' day'));
        $cEnd   = date('Y-m-d', strtotime($cStart.' +6 day'));
        $cLabel = date('j M', strtotime($cStart)).' &ndash; '.date('j M Y', strtotime($cEnd));
        $cPrev  = date('Y-m-d', strtotime($cStart.' -7 day'));
        $cNext  = date('Y-m-d', strtotime($cStart.' +7 day'));
    } else { // month
        $cStart = date('Y-m-01', $ats);
        $cEnd   = date('Y-m-t', $ats);
        $cLabel = date('F Y', $ats);
        $cPrev  = date('Y-m-d', strtotime($cStart.' -1 month'));
        $cNext  = date('Y-m-d', strtotime($cStart.' +1 month'));
    }
    $sstmt = $pdo->prepare("
        SELECT sc.start_date, sc.start_time, sc.end_time, sc.total_places,
               co.title AS course_title, co.code AS course_code, l.name AS location, u.name AS trainer_name,
               (SELECT COUNT(*) FROM enrolments e WHERE e.schedule_id=sc.id) AS booked
        FROM schedules sc
        JOIN plans p ON p.id=sc.plan_id
        JOIN courses co ON co.id=p.course_id
        LEFT JOIN locations l ON l.id=sc.location_id
        LEFT JOIN users u ON u.id=sc.trainer_id
        WHERE date(sc.start_date) BETWEEN ? AND ?
        ORDER BY sc.start_date, sc.start_time");
    $sstmt->execute([$cStart, $cEnd]);
    $cal_sessions = [];
    foreach ($sstmt->fetchAll() as $ev) { $cal_sessions[substr((string)$ev['start_date'],0,10)][] = $ev; }
    $cal = ['view'=>$cv,'anchor'=>$anchor,'label'=>$cLabel,'prev'=>$cPrev,'next'=>$cNext,
            'today'=>date('Y-m-d'),'sessions'=>$cal_sessions];
    render('dashboard', compact('stats','expiring','pending','next_sessions','course_counts','cal'), 'Dashboard');
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

case 'locations':
    $editId = (int)($_GET['edit'] ?? 0);
    $edit = $editId ? $pdo->query("SELECT * FROM locations WHERE id=".$editId)->fetch() : null;
    $rows = $pdo->query("
        SELECT l.*, (SELECT COUNT(*) FROM schedules s WHERE s.location_id=l.id) uses
        FROM locations l ORDER BY l.active DESC, l.name")->fetchAll();
    render('locations', compact('rows','edit'), 'Locations');
    break;

case 'location_save':
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $ident= trim($_POST['identifier'] ?? '');
    $sub  = trim($_POST['suburb'] ?? '');
    $st8  = trim($_POST['state'] ?? '');
    $pc   = trim($_POST['postcode'] ?? '');
    $act  = isset($_POST['active']) ? 1 : 0;
    if ($name === '') {
        $_SESSION['flash'] = 'Please enter a location name.';
    } elseif ($id) {
        $pdo->prepare("UPDATE locations SET name=?,identifier=?,suburb=?,state=?,postcode=?,active=? WHERE id=?")
            ->execute([$name,$ident,$sub,$st8,$pc,$act,$id]);
        $_SESSION['flash'] = 'Location updated.';
    } else {
        $pdo->prepare("INSERT INTO locations (name,identifier,suburb,state,postcode,active) VALUES (?,?,?,?,?,1)")
            ->execute([$name,$ident,$sub,$st8,$pc]);
        $_SESSION['flash'] = 'Location added.';
    }
    redirect('?r=locations');
    break;

case 'location_delete':
    $pdo->prepare("UPDATE locations SET active=0 WHERE id=?")->execute([(int)($_GET['id'] ?? 0)]);
    $_SESSION['flash'] = 'Location deactivated.';
    redirect('?r=locations');
    break;

case 'form_subs':   // staff: list submissions for an incident-report module
    require __DIR__ . '/../lib/lms.php'; lms_ensure_schema($pdo);
    $module = lms_module($pdo, (int)($_GET['module_id'] ?? 0));
    if (!$module) redirect('?r=content');
    $subs = lms_module_submissions($pdo, (int)$module['id']);
    render('form_subs', compact('module','subs'), 'Submissions');
    break;

case 'form_view':   // staff: view one student's submission (read-only)
    require __DIR__ . '/../lib/lms.php'; lms_ensure_schema($pdo);
    $sub = $pdo->query("SELECT * FROM form_submissions WHERE id=".(int)($_GET['sub'] ?? 0))->fetch();
    if (!$sub) redirect('?r=content');
    $sub['fields'] = (array)json_decode($sub['data'] ?? '{}', true);
    $module = lms_module($pdo, (int)$sub['module_id']);
    $viewStudent = $pdo->query("SELECT s.* FROM enrolments e JOIN students s ON s.id=e.student_id WHERE e.id=".(int)$sub['enrolment_id'])->fetch() ?: null;
    render('learn', ['module'=>$module,'enrolment'=>null,'questions'=>[],
                     'submission'=>$sub,'readonly'=>true,'viewStudent'=>$viewStudent], $module['title']);
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

case 'content':
    require __DIR__ . '/../lib/lms.php';
    lms_ensure_schema($pdo); lms_seed_demo($pdo);
    $modules = lms_all_modules($pdo);
    $courses = $pdo->query("SELECT id,code,title FROM courses ORDER BY code")->fetchAll();
    render('content', compact('modules','courses'), 'Course Content (LMS)');
    break;

case 'content_upload':
    require __DIR__ . '/../lib/lms.php';
    lms_ensure_schema($pdo);
    $courseId = (int)($_POST['course_id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    try {
        if (!$courseId || $title === '') throw new RuntimeException('Please choose a course and give the module a title.');
        if (empty($_FILES['scorm']['tmp_name']) || ($_FILES['scorm']['error'] ?? 1) !== UPLOAD_ERR_OK)
            throw new RuntimeException('Please choose a SCORM .zip file to upload.');
        $slug = 'mod-'.trim(preg_replace('/[^a-z0-9]+/','-', strtolower($title)),'-');
        [$dir,$launch] = lms_import_scorm_zip($_FILES['scorm']['tmp_name'], $slug ?: 'mod');
        $pos = (int)$pdo->query("SELECT COALESCE(MAX(position),0)+1 FROM course_modules")->fetchColumn();
        $pdo->prepare("INSERT INTO course_modules (course_id,title,type,scorm_dir,launch_url,position) VALUES (?,?,'scorm',?,?,?)")
            ->execute([$courseId,$title,$dir,$launch,$pos]);
        $_SESSION['flash'] = 'SCORM package uploaded and ready to launch.';
    } catch (Throwable $ex) { $_SESSION['flash'] = 'Upload failed: '.$ex->getMessage(); }
    redirect('?r=content');
    break;

case 'module_new':   // create a quiz (then open builder) or an incident-report form module
    require __DIR__ . '/../lib/lms.php';
    lms_ensure_schema($pdo);
    $courseId = (int)($_POST['course_id'] ?? 0);
    $type     = ($_POST['type'] ?? 'quiz') === 'incident_report' ? 'incident_report' : 'quiz';
    $title    = trim($_POST['title'] ?? '') ?: ($type==='incident_report' ? 'Incident Report' : 'Knowledge Check');
    $pass     = (int)($_POST['pass_mark'] ?? 80);
    $body     = trim($_POST['body'] ?? '');
    if ($courseId) {
        $pos = (int)$pdo->query("SELECT COALESCE(MAX(position),0)+1 FROM course_modules")->fetchColumn();
        if ($type === 'incident_report') {
            $pdo->prepare("INSERT INTO course_modules (course_id,title,type,body,position) VALUES (?,?,'incident_report',?,?)")
                ->execute([$courseId,$title,$body,$pos]);
            $_SESSION['flash'] = 'Incident report assessment created.';
            redirect('?r=content');
        }
        $pdo->prepare("INSERT INTO course_modules (course_id,title,type,pass_mark,position) VALUES (?,?,'quiz',?,?)")
            ->execute([$courseId,$title,$pass,$pos]);
        redirect('?r=quiz_edit&id='.$pdo->lastInsertId());
    }
    redirect('?r=content');
    break;

case 'quiz_edit':
    require __DIR__ . '/../lib/lms.php';
    lms_ensure_schema($pdo);
    $id = (int)($_GET['id'] ?? 0); $module = lms_module($pdo, $id);
    if (!$module || $module['type'] !== 'quiz') { http_response_code(404); echo 'Quiz not found'; break; }
    $questions = lms_questions($pdo, $id);
    render('quiz_edit', compact('module','questions'), 'Edit quiz');
    break;

case 'quiz_save':    // replace-all save from the builder
    require __DIR__ . '/../lib/lms.php';
    lms_ensure_schema($pdo);
    $id = (int)($_POST['module_id'] ?? 0); $module = lms_module($pdo, $id);
    if ($module) {
        $pdo->prepare("UPDATE course_modules SET title=?, pass_mark=? WHERE id=?")
            ->execute([trim($_POST['title'] ?? $module['title']) ?: $module['title'], (int)($_POST['pass_mark'] ?? 80), $id]);
        $pdo->prepare("DELETE FROM quiz_questions WHERE module_id=?")->execute([$id]);
        $pos = 0;
        foreach (($_POST['q'] ?? []) as $qd) {
            $qtext = trim($qd['question'] ?? ''); if ($qtext === '') continue;
            $qtype = in_array($qd['qtype'] ?? 'single', ['single','multi','truefalse'], true) ? $qd['qtype'] : 'single';
            $opts = []; $correct = [];
            if ($qtype === 'truefalse') {
                $opts = ['True','False'];
                $correct = (($qd['correct_single'] ?? '1') === '0') ? [0] : [1];
            } else {
                foreach (($qd['opt'] ?? []) as $oi => $otext) {
                    $otext = trim($otext); if ($otext === '') continue;
                    $idx = count($opts); $opts[] = $otext;
                    if ($qtype === 'single') {
                        if ((string)($qd['correct_single'] ?? '') === (string)$oi) $correct[] = $idx;
                    } else { // multi
                        if (isset($qd['correct'][$oi])) $correct[] = $idx;
                    }
                }
            }
            if (!$opts) continue;
            $pdo->prepare("INSERT INTO quiz_questions (module_id,question,qtype,options,correct,position) VALUES (?,?,?,?,?,?)")
                ->execute([$id,$qtext,$qtype,json_encode($opts),json_encode($correct),++$pos]);
        }
        $_SESSION['flash'] = 'Quiz saved.';
    }
    redirect('?r=quiz_edit&id='.$id);
    break;

case 'module_delete':
    require __DIR__ . '/../lib/lms.php';
    lms_ensure_schema($pdo);
    $pdo->prepare("UPDATE course_modules SET active=0 WHERE id=?")->execute([(int)($_GET['id'] ?? 0)]);
    $_SESSION['flash'] = 'Module removed.';
    redirect('?r=content');
    break;

default:
    http_response_code(404); echo 'Page not found';
}
