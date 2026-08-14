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
        $given = $_POST['password'] ?? '';
        // Personal password if the student has one set; otherwise the shared starter password
        // (so no existing student is ever locked out before they receive their own).
        $ok = false;
        if ($s) {
            if (!empty($s['password'])) $ok = password_verify($given, (string)$s['password']);
            else                        $ok = ($given === 'student123');
        }
        // fresh login = show the outstanding-details card again
        if ($ok) { $_SESSION['student_id'] = $s['id']; unset($_SESSION['todo_shown']); redirect('?r=my'); }
        render('student_login', ['error'=>'Invalid email or password.'], 'Student login'); exit;
    }
    render('student_login', [], 'Student login'); exit;
}
if ($r === 'student_logout') { unset($_SESSION['student_id'], $_SESSION['todo_shown']); redirect('?r=student_login'); }

if ($r === 'student_forgot') {
    require_once __DIR__ . '/../lib/student_portal.php'; sp_schema(db());
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $em = trim($_POST['email'] ?? '');
        $st = db()->prepare("SELECT * FROM students WHERE email=?"); $st->execute([$em]); $s = $st->fetch();
        if ($s && !empty($s['email'])) { sp_send_portal(db(), $s); }
        // Never reveal whether an email exists.
        render('student_login', ['error'=>'If that email is registered, we have emailed your login details. Please check your inbox.'], 'Student login'); exit;
    }
    render('student_login', ['forgot'=>true], 'Reset password'); exit;
}

if ($r === 'group_booking') {
    // Private corporate / onsite group-booking request page (not linked from the public website).
    require_once __DIR__ . '/../lib/group_booking.php';
    $pdo = db(); gb_schema($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $f = [
          'company'=>trim($_POST['company']??''), 'contact_name'=>trim($_POST['contact_name']??''),
          'email'=>trim($_POST['email']??''), 'phone'=>trim($_POST['phone']??''),
          'course_label'=>trim($_POST['course_label']??''), 'preferred_date'=>trim($_POST['preferred_date']??''),
          'location'=>trim($_POST['location']??''), 'participants'=>(int)($_POST['participants']??0),
          'attendees'=>trim($_POST['attendees']??''), 'notes'=>trim($_POST['notes']??''),
        ];
        if ($f['company']==='' || $f['contact_name']==='' || $f['email']==='') {
            render('group_booking', ['error'=>'Please provide at least company name, contact name and email.','f'=>$f,'courses'=>gb_courses($pdo)], 'Group Booking'); exit;
        }
        $cols=array_keys($f); $ph=implode(',',array_fill(0,count($cols),'?'));
        $pdo->prepare("INSERT INTO group_bookings (".implode(',',$cols).") VALUES ($ph)")->execute(array_values($f));
        gb_notify($pdo, $f); // email admin (best-effort)
        render('group_booking', ['done'=>true], 'Group Booking'); exit;
    }
    render('group_booking', ['courses'=>gb_courses($pdo)], 'Group / Corporate Booking'); exit;
}

if (in_array($r, ['my','mycert'], true)) {
    if (empty($_SESSION['student_id'])) redirect('?r=student_login');
    $pdo = db(); $sid = (int)$_SESSION['student_id'];

    if ($r === 'mycert') {
        $num = trim($_GET['num'] ?? '');
        $c = $pdo->prepare("SELECT * FROM certificates WHERE certificate_number=? AND student_id=?");
        $c->execute([$num, $sid]); $cert = $c->fetch();
        if (!$cert) { http_response_code(404); echo 'Certificate not available'; exit; }
        // ---- survey gate: ask the learner to complete a quick survey before their first download ----
        $pdo->exec("CREATE TABLE IF NOT EXISTS portal_surveys (id INTEGER PRIMARY KEY AUTOINCREMENT, student_id INTEGER, cert_number TEXT, satisfied TEXT, quality INTEGER, sufficient_info TEXT, q_fair TEXT, q_skills TEXT, q_trainer TEXT, q_facilities TEXT, likely_again INTEGER, precourse TEXT, comment TEXT, contact_pref TEXT, created_at TEXT DEFAULT (datetime('now')))");
        $doneSurvey = (int)$pdo->query("SELECT COUNT(*) FROM portal_surveys WHERE student_id=".$sid)->fetchColumn();
        if (!$doneSurvey && empty($_GET['skip'])) {
            render('my_survey', ['num'=>$num, 'me'=>$pdo->query("SELECT * FROM students WHERE id=$sid")->fetch(), 'studentChrome'=>true], 'Student Survey'); exit;
        }
        // ---- ensure the PDF exists (re-render on demand in the current design) ----
        require_once __DIR__ . '/../lib/certificate.php';
        try { $cert = anb_ensure_cert_pdf($pdo, $cert); } catch (Throwable $ex) {}
        $file = __DIR__ . '/../data/' . ($cert['file_path'] ?? '');
        if (!empty($cert['file_path']) && is_file($file)) {
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
    render('portal', compact('me','enrolments','mycerts') + ['studentChrome'=>true], 'My Learning');
    exit;
}

if ($r === 'my_survey_save') {
    if (empty($_SESSION['student_id'])) redirect('?r=student_login');
    $pdo = db(); $sid = (int)$_SESSION['student_id'];
    $pdo->exec("CREATE TABLE IF NOT EXISTS portal_surveys (id INTEGER PRIMARY KEY AUTOINCREMENT, student_id INTEGER, cert_number TEXT, satisfied TEXT, quality INTEGER, sufficient_info TEXT, q_fair TEXT, q_skills TEXT, q_trainer TEXT, q_facilities TEXT, likely_again INTEGER, precourse TEXT, comment TEXT, contact_pref TEXT, created_at TEXT DEFAULT (datetime('now')))");
    $num = trim($_POST['num'] ?? '');
    $pdo->prepare("INSERT INTO portal_surveys (student_id,cert_number,satisfied,quality,sufficient_info,q_fair,q_skills,q_trainer,q_facilities,likely_again,precourse,comment,contact_pref)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
        $sid, $num, trim($_POST['satisfied']??''), (int)($_POST['quality']??0)?:null, trim($_POST['sufficient_info']??''),
        trim($_POST['q_fair']??''), trim($_POST['q_skills']??''), trim($_POST['q_trainer']??''), trim($_POST['q_facilities']??''),
        (int)($_POST['likely_again']??0)?:null, trim($_POST['precourse']??''), trim($_POST['comment']??''), trim($_POST['contact_pref']??'')]);
    // Peak-satisfaction moment: invite a Google review for the location they attended, then start the download.
    require_once __DIR__ . '/../lib/mailer.php';
    $loc = null;
    if ($num !== '') {
        $lq = $pdo->prepare("SELECT e.location_id FROM certificates c JOIN enrolments e ON e.id=c.enrolment_id WHERE c.certificate_number=? AND c.student_id=?");
        $lq->execute([$num, $sid]); $loc = $lq->fetchColumn();
    }
    $reviewUrl = anb_review_link($pdo, $loc ? (int)$loc : null);
    $satisfied = trim($_POST['satisfied'] ?? '');
    render('my_thankyou', ['num'=>$num, 'reviewUrl'=>$reviewUrl, 'satisfied'=>$satisfied, 'studentChrome'=>true], 'Thank You');
    exit;
}

if ($r === 'my_details' || $r === 'my_details_save') {
    if (empty($_SESSION['student_id'])) redirect('?r=student_login');
    $pdo = db(); $sid = (int)$_SESSION['student_id'];
    if ($r === 'my_details_save') {
        // plain personal/contact/address fields (free text)
        $plain = ['salutation','first_name','middle_name','last_name','date_of_birth','gender','mobile_phone',
                  'unit_flat','street_number','street_name','suburb','state','postcode','usi_number'];
        $sets = []; $vals = [];
        foreach ($plain as $f) { $sets[] = "$f=?"; $vals[] = trim($_POST[$f] ?? ''); }
        // AVETMISS coded fields — only store valid codes; leave unchanged otherwise
        $school = $_POST['highest_school_level'] ?? ''; if (in_array($school,['12','11','10','09','08','02'],true)) { $sets[]="highest_school_level=?"; $vals[]=$school; }
        $indig  = $_POST['indigenous_status'] ?? '';   if (in_array($indig,['1','2','3','4','9'],true))          { $sets[]="indigenous_status=?"; $vals[]=$indig; }
        $lab    = $_POST['labour_force_status'] ?? '';  if (in_array($lab,['01','02','05','07','09'],true))       { $sets[]="labour_force_status=?"; $vals[]=$lab; }
        $dis    = $_POST['disability_flag'] ?? '';      if (in_array($dis,['Y','N'],true))                        { $sets[]="disability_flag=?"; $vals[]=$dis; }
        // Country / language as real codes (the old yes/no pair discarded every "No").
        require_once __DIR__.'/../lib/avetmiss.php';
        $cob = $_POST['country_of_birth'] ?? ''; if (isset(avetmiss_country_options()[$cob]))  { $sets[]="country_of_birth=?"; $vals[]=$cob; }
        elseif (($_POST['born_au'] ?? '')==='yes')  { $sets[]="country_of_birth=?"; $vals[]='1101'; }
        $lng = $_POST['main_language'] ?? '';    if (isset(avetmiss_language_options()[$lng])) { $sets[]="main_language=?";   $vals[]=$lng; }
        elseif (($_POST['eng_main'] ?? '')==='yes') { $sets[]="main_language=?";   $vals[]='1201'; }
        // If the USI changed, it must be re-verified before a certificate can be issued.
        $curUsi = (string)($pdo->query("SELECT usi_number FROM students WHERE id=".$sid)->fetchColumn() ?: '');
        if (trim($_POST['usi_number'] ?? '') !== $curUsi) { $sets[]="usi_verified=0"; $sets[]="usi_verified_date=NULL"; $sets[]="usi_verified_method=NULL"; }
        $vals[] = $sid;
        $pdo->prepare("UPDATE students SET ".implode(',',$sets)." WHERE id=?")->execute($vals);
        $_SESSION['flash'] = 'Thank you — your details have been saved.';
        redirect('?r=my');
    }
    $me = $pdo->prepare("SELECT * FROM students WHERE id=?"); $me->execute([$sid]); $me = $me->fetch();
    render('my_details', ['me'=>$me, 'studentChrome'=>true], 'My Details');
    exit;
}

if ($r === 'selfenrol') {
    // Public per-class self-enrolment link (share with individuals or a group). Not on the public website.
    $pdo = db();
    $cid = (int)($_GET['c'] ?? $_POST['c'] ?? 0);
    $sc = $pdo->prepare("SELECT sc.*, p.course_id, co.code, co.title, l.name loc
        FROM schedules sc JOIN plans p ON p.id=sc.plan_id JOIN courses co ON co.id=p.course_id
        LEFT JOIN locations l ON l.id=sc.location_id WHERE sc.id=?");
    $sc->execute([$cid]); $sc = $sc->fetch(PDO::FETCH_ASSOC);
    if (!$sc) { render('selfenrol', ['invalid'=>true, 'studentChrome'=>true], 'Enrolment'); exit; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email=trim($_POST['email']??''); $fn=trim($_POST['first_name']??''); $ln=trim($_POST['last_name']??'');
        if ($fn===''||$ln===''||$email===''||strpos($email,'@')===false) {
            render('selfenrol', ['sc'=>$sc,'error'=>'Please enter your first name, last name and a valid email.','f'=>$_POST,'studentChrome'=>true], 'Enrolment'); exit;
        }
        $st=$pdo->prepare("SELECT * FROM students WHERE email=?"); $st->execute([$email]); $stu=$st->fetch(PDO::FETCH_ASSOC);
        if ($stu) { $sid=(int)$stu['id']; } else {
            $pdo->prepare("INSERT INTO students (first_name,last_name,email) VALUES (?,?,?)")->execute([$fn,$ln,$email]);
            $sid=(int)$pdo->lastInsertId();
        }
        // save their details (whitelist + valid AVETMISS codes)
        $plain=['first_name','last_name','date_of_birth','gender','mobile_phone','street_number','street_name','suburb','state','postcode','usi_number'];
        $sets=[]; $vals=[]; foreach($plain as $ff){ if(isset($_POST[$ff])){ $sets[]="$ff=?"; $vals[]=trim($_POST[$ff]); } }
        $school=$_POST['highest_school_level']??''; if(in_array($school,['12','11','10','09','08','02'],true)){$sets[]="highest_school_level=?";$vals[]=$school;}
        $indig=$_POST['indigenous_status']??''; if(in_array($indig,['1','2','3','4','9'],true)){$sets[]="indigenous_status=?";$vals[]=$indig;}
        $lab=$_POST['labour_force_status']??''; if(in_array($lab,['01','02','05','07','09'],true)){$sets[]="labour_force_status=?";$vals[]=$lab;}
        $dis=$_POST['disability_flag']??''; if(in_array($dis,['Y','N'],true)){$sets[]="disability_flag=?";$vals[]=$dis;}
        // Country / language now come through as real codes. The old yes/no pair
        // threw away every "No" answer, leaving overseas-born students unreportable.
        require_once __DIR__.'/../lib/avetmiss.php';
        $cob=$_POST['country_of_birth']??''; if(isset(avetmiss_country_options()[$cob])){$sets[]="country_of_birth=?";$vals[]=$cob;}
        elseif(($_POST['born_au']??'')==='yes'){$sets[]="country_of_birth=?";$vals[]='1101';}
        $lng=$_POST['main_language']??''; if(isset(avetmiss_language_options()[$lng])){$sets[]="main_language=?";$vals[]=$lng;}
        elseif(($_POST['eng_main']??'')==='yes'){$sets[]="main_language=?";$vals[]='1201';}
        if ($sets){ $vals[]=$sid; $pdo->prepare("UPDATE students SET ".implode(',',$sets)." WHERE id=?")->execute($vals); }
        // enrol into this class (guard duplicates)
        $dup=$pdo->prepare("SELECT COUNT(*) FROM enrolments WHERE student_id=? AND schedule_id=?"); $dup->execute([$sid,$cid]);
        if ((int)$dup->fetchColumn()===0) {
            $pdo->prepare("INSERT INTO enrolments (student_id,course_id,plan_id,schedule_id,location_id,start_date,end_date,status,payment_status)
                           VALUES (?,?,?,?,?,?,?,'enrolled','unpaid')")
                ->execute([$sid,(int)$sc['course_id'],(int)$sc['plan_id'],$cid,$sc['location_id']?:null,$sc['start_date'],$sc['end_date']]);
            // Mirror the enrolment into RTO Data Cloud so the USI can be verified there
            // (respects the off/dry/live switch on the RTO Sync screen; never blocks enrolment).
            require_once __DIR__.'/../lib/rtodata.php'; anb_rto_push_safe($pdo,(int)$pdo->lastInsertId());
        }
        // give them portal access to log in and complete/fix anything later (best-effort)
        require_once __DIR__.'/../lib/student_portal.php'; sp_schema($pdo);
        $stu2=$pdo->query("SELECT * FROM students WHERE id=$sid")->fetch(PDO::FETCH_ASSOC);
        $mailOk = true;
        try { [$mailOk,] = sp_send_portal($pdo,$stu2); } catch (Throwable $e) { $mailOk = false; }
        // Tell the student the truth on screen if the login email did not go out,
        // instead of promising an email that will never arrive.
        render('selfenrol', ['done'=>true,'sc'=>$sc,'mailFailed'=>!$mailOk,'studentChrome'=>true], 'Enrolment received'); exit;
    }
    render('selfenrol', ['sc'=>$sc,'studentChrome'=>true], 'Enrol — '.$sc['code']); exit;
}

/* ============ LMS player: online modules (SCORM + quiz) — learner or admin preview ============ */
if (in_array($r, ['learn','scorm_track','quiz_submit','form_submit','quiz_reset','module_complete'], true)) {
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

    // Gate: learners must complete the LLN assessment (the first module) before any other module.
    if ($isStudent && $enrolment && in_array($r, ['learn','quiz_submit','module_complete','form_submit'], true)) {
        $first = lms_first_module($pdo, (int)$module['course_id']);
        if ($first && (int)$first['id'] !== $moduleId
            && !lms_module_completed($pdo, (int)$enrolment['id'], (int)$first['id'])) {
            $_SESSION['flash'] = 'Please complete the LLN assessment first — it only takes a few minutes.';
            redirect('?r=learn&module_id='.(int)$first['id']);
        }
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

    define('ANB_QUIZ_MAX_ATTEMPTS', 3);

    // Native "mark this module complete" (for SCORM/content modules) - records
    // completion in OUR system, no external "Mark Activity Complete" needed.
    if ($r === 'module_complete') {
        if ($enrolment) lms_record_progress($pdo, (int)$enrolment['id'], $moduleId, 'completed', null);
        $_SESSION['flash'] = 'Module marked complete - your progress is saved.';
        redirect('?r=my');
    }

    // Reset a quiz's attempts after the learner has reviewed the modules again.
    if ($r === 'quiz_reset') {
        if ($enrolment) {
            $pdo->prepare("UPDATE learner_progress SET attempts=0, status='in_progress', updated_at=datetime('now')
                           WHERE enrolment_id=? AND module_id=? AND status<>'completed'")
                ->execute([(int)$enrolment['id'], $moduleId]);
        }
        redirect('?r=learn&module_id='.$moduleId);
    }

    if ($r === 'quiz_submit') {
        $allQuestions = lms_questions($pdo, $moduleId);
        $prog = $enrolment ? (lms_progress_for_enrolment($pdo,(int)$enrolment['id'])[$moduleId] ?? null) : null;
        $priorAttempts = (int)($prog['attempts'] ?? 0);
        $alreadyPassed = (($prog['status'] ?? '') === 'completed');
        // Locked out (3 attempts used, not passed) - must review modules first.
        if ($enrolment && !$alreadyPassed && $priorAttempts >= ANB_QUIZ_MAX_ATTEMPTS) {
            render('learn', ['module'=>$module,'enrolment'=>$enrolment,'questions'=>[],'studentChrome'=>$isStudent,
                             'lockout'=>true,'attempts'=>$priorAttempts,'attemptsMax'=>ANB_QUIZ_MAX_ATTEMPTS], $module['title']);
            exit;
        }
        // Re-attempt only the questions still wrong from last time (if any).
        $wrongIds = ($prog && isset($prog['wrong_qids']) && $prog['wrong_qids']!==null && $prog['wrong_qids']!=='')
                    ? (array)json_decode($prog['wrong_qids'], true) : null;
        $active = $allQuestions;
        if ($enrolment && is_array($wrongIds) && count($wrongIds) && !$alreadyPassed) {
            $wset = array_flip(array_map('intval',$wrongIds));
            $active = array_values(array_filter($allQuestions, fn($q)=>isset($wset[(int)$q['id']])));
        }
        [$pctA,$correctA,$totalA,$per] = lms_grade_quiz($active, $_POST['a'] ?? []);
        $newWrong = [];
        foreach ($active as $q) { if (empty($per[$q['id']])) $newWrong[] = (int)$q['id']; }
        $totalAll   = count($allQuestions);
        $displayPct = $totalAll ? (int)round(($totalAll - count($newWrong))/$totalAll*100) : 0;
        $passed     = ($displayPct >= (int)$module['pass_mark']);
        if ($enrolment) {
            lms_record_progress($pdo, (int)$enrolment['id'], $moduleId, $passed?'completed':'in_progress', (float)$displayPct);
            $pdo->prepare("UPDATE learner_progress SET wrong_qids=? WHERE enrolment_id=? AND module_id=?")
                ->execute([json_encode($newWrong), (int)$enrolment['id'], $moduleId]);
        }
        $attemptsNow = $priorAttempts + ($enrolment ? 1 : 0);
        $lockNow = ($enrolment && !$passed && $attemptsNow >= ANB_QUIZ_MAX_ATTEMPTS);
        render('learn', ['module'=>$module,'enrolment'=>$enrolment,'questions'=>$active,'studentChrome'=>$isStudent,
                         'quizResult'=>['pct'=>$displayPct,'correct'=>$totalAll-count($newWrong),'total'=>$totalAll,
                                        'per'=>$per,'passed'=>$passed,'remaining'=>count($newWrong)],
                         'attempts'=>$attemptsNow,'attemptsMax'=>ANB_QUIZ_MAX_ATTEMPTS,'lockNow'=>$lockNow], $module['title']);
        exit;
    }

    if ($r === 'form_submit') {
        $data = $_POST['f'] ?? [];
        if ($enrolment) {
            lms_save_form_submission($pdo, (int)$enrolment['id'], $moduleId, $data);
            lms_record_progress($pdo, (int)$enrolment['id'], $moduleId, 'completed', null);
        }
        $submission = $enrolment ? lms_form_submission($pdo, (int)$enrolment['id'], $moduleId) : ['fields'=>$data];
        render('learn', ['module'=>$module,'enrolment'=>$enrolment,'questions'=>[],'studentChrome'=>$isStudent,
                         'submission'=>$submission,'justSaved'=>true], $module['title']);
        exit;
    }

    // r === 'learn'
    $questions = $module['type']==='quiz' ? lms_questions($pdo, $moduleId) : [];
    $submission = ($module['type']==='incident_report' && $enrolment)
        ? lms_form_submission($pdo, (int)$enrolment['id'], $moduleId) : null;
    $extra = [];
    if ($module['type']==='quiz' && $enrolment) {
        $prog = lms_progress_for_enrolment($pdo,(int)$enrolment['id'])[$moduleId] ?? null;
        $extra['attempts']    = (int)($prog['attempts'] ?? 0);
        $extra['attemptsMax'] = ANB_QUIZ_MAX_ATTEMPTS;
        $extra['passed']      = (($prog['status'] ?? '') === 'completed');
        $extra['lockout']     = (!$extra['passed'] && $extra['attempts'] >= ANB_QUIZ_MAX_ATTEMPTS);
        // On a re-attempt, only re-test the questions they got wrong last time.
        $wrongIds = ($prog && isset($prog['wrong_qids']) && $prog['wrong_qids']!==null && $prog['wrong_qids']!=='')
                    ? (array)json_decode($prog['wrong_qids'], true) : null;
        if (is_array($wrongIds) && count($wrongIds) && !$extra['passed']) {
            $wset = array_flip(array_map('intval',$wrongIds));
            $questions = array_values(array_filter($questions, fn($q)=>isset($wset[(int)$q['id']])));
            $extra['retakeWrongOnly'] = count($questions);
        }
    }
    render('learn', array_merge(compact('module','enrolment','questions','submission'),$extra,['studentChrome'=>$isStudent]), $module['title']);
    exit;
}

require_login();
$pdo = db();

// Role-based access guard: admins see everything; other roles only their area (all can reach the dashboard).
if (!role_allowed($r)) { $_SESSION['flash'] = 'You do not have access to that area.'; redirect('?r=dashboard'); }

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
    require_once __DIR__ . '/../lib/lms.php';
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

/**
 * Correct the name, date of birth or USI on a student record.
 *
 * Until this existed the only way to change a name was the student's own
 * portal, so staff had no way to act on anything the registry rejected.
 */
case 'student_save':
    $id = (int)($_POST['id'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) redirect('?r=students');
    $cur = $pdo->prepare("SELECT usi_number, first_name, last_name FROM students WHERE id=?");
    $cur->execute([$id]); $cur = $cur->fetch(PDO::FETCH_ASSOC);
    if (!$cur) { http_response_code(404); echo 'Not found'; break; }

    $sets = []; $vals = [];
    foreach (['salutation','first_name','middle_name','last_name','date_of_birth',
              'email','mobile_phone','usi_number'] as $f) {
        if (!isset($_POST[$f])) continue;
        $v = trim((string)$_POST[$f]);
        if ($f === 'usi_number') $v = strtoupper($v);
        $sets[] = "$f=?"; $vals[] = $v;
    }
    // A changed USI has not been checked against anything, so the tick must go.
    // Changing the name does the same - the old tick was for the old spelling.
    $newUsi  = strtoupper(trim((string)($_POST['usi_number'] ?? $cur['usi_number'])));
    $renamed = trim((string)($_POST['first_name'] ?? '')) !== (string)$cur['first_name']
            || trim((string)($_POST['last_name']  ?? '')) !== (string)$cur['last_name'];
    if ($newUsi !== strtoupper((string)$cur['usi_number']) || $renamed) {
        $sets[] = "usi_verified=0"; $sets[] = "usi_verified_date=NULL"; $sets[] = "usi_verified_method=NULL";
    }
    if ($sets) {
        $vals[] = $id;
        $pdo->prepare("UPDATE students SET ".implode(',', $sets)." WHERE id=?")->execute($vals);
    }
    $_SESSION['flash'] = ($newUsi !== strtoupper((string)$cur['usi_number']) || $renamed)
        ? 'Saved. The USI needs verifying again now the details have changed.'
        : 'Saved.';
    redirect('?r=student&id='.$id);
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
    require_once __DIR__.'/../lib/student_portal.php'; sp_schema($pdo);
    $rows = $pdo->query("
        SELECT sc.*, p.title plan_title, co.code course_code, l.name location, u.name trainer_name,
          (SELECT COUNT(*) FROM enrolments e WHERE e.schedule_id=sc.id) AS enrolled,
          -- only meaningful for classes that haven't run yet; historical students never needed a portal login
          (CASE WHEN sc.start_date >= date('now') THEN
            (SELECT COUNT(DISTINCT s2.id) FROM enrolments e2 JOIN students s2 ON s2.id=e2.student_id
               WHERE e2.schedule_id=sc.id AND (s2.portal_emailed_at IS NULL OR s2.portal_emailed_at=''))
           ELSE 0 END) AS no_login
        FROM schedules sc JOIN plans p ON p.id=sc.plan_id JOIN courses co ON co.id=p.course_id
        LEFT JOIN locations l ON l.id=sc.location_id LEFT JOIN users u ON u.id=sc.trainer_id
        ORDER BY sc.start_date, l.name, sc.start_time")->fetchAll();
    $plans     = $pdo->query("SELECT p.id, p.title, co.code FROM plans p JOIN courses co ON co.id=p.course_id WHERE p.active=1 ORDER BY co.code, p.title")->fetchAll();
    $locations = $pdo->query("SELECT id, name FROM locations WHERE active=1 ORDER BY name")->fetchAll();
    $trainers  = $pdo->query("SELECT id, name FROM users WHERE active=1 AND (is_trainer=1 OR role IN ('trainer','admin')) ORDER BY name")->fetchAll();
    $editId = (int)($_GET['edit'] ?? 0);
    $edit = $editId ? $pdo->query("SELECT * FROM schedules WHERE id=".$editId)->fetch() : null;
    render('schedules', compact('rows','plans','locations','trainers','edit'), 'Schedules');
    break;

case 'schedule_save':
    $id    = (int)($_POST['id'] ?? 0);
    $plan  = (int)($_POST['plan_id'] ?? 0);
    $loc   = (int)($_POST['location_id'] ?? 0) ?: null;
    $trn   = (int)($_POST['trainer_id'] ?? 0) ?: null;
    $sdate = trim($_POST['start_date'] ?? '');
    $edate = trim($_POST['end_date'] ?? '') ?: $sdate;
    $stime = trim($_POST['start_time'] ?? '');
    $etime = trim($_POST['end_time'] ?? '');
    $places= (int)($_POST['total_places'] ?? 0) ?: 15;
    $name  = trim($_POST['name'] ?? '');
    if (!$plan || $sdate === '') {
        $_SESSION['flash'] = 'Please choose a course/plan and a date.';
        redirect('?r=schedules'.($id?'&edit='.$id:''));
        break;
    }
    if ($name === '') {
        // auto-build a friendly name from plan + location
        $pn = $pdo->prepare("SELECT co.code, l.name FROM plans p JOIN courses co ON co.id=p.course_id
                             LEFT JOIN locations l ON l.id=? WHERE p.id=?");
        $pn->execute([$loc, $plan]); $r = $pn->fetch();
        $name = trim(($r['code'] ?? 'Class').' - '.($r['name'] ?? $sdate));
    }
    if ($id) {
        $pdo->prepare("UPDATE schedules SET plan_id=?,location_id=?,trainer_id=?,name=?,start_date=?,end_date=?,start_time=?,end_time=?,total_places=? WHERE id=?")
            ->execute([$plan,$loc,$trn,$name,$sdate,$edate,$stime,$etime,$places,$id]);
        $_SESSION['flash'] = 'Schedule updated.';
    } else {
        // Repeat: create weekly occurrences (1 = just once)
        $repeat = max(1, min(52, (int)($_POST['repeat_weeks'] ?? 1)));
        $ins = $pdo->prepare("INSERT INTO schedules (plan_id,location_id,trainer_id,name,start_date,end_date,start_time,end_time,total_places) VALUES (?,?,?,?,?,?,?,?,?)");
        for ($i = 0; $i < $repeat; $i++) {
            $sd = date('Y-m-d', strtotime($sdate . " +".($i*7)." days"));
            $ed = date('Y-m-d', strtotime($edate . " +".($i*7)." days"));
            $ins->execute([$plan,$loc,$trn,$name,$sd,$ed,$stime,$etime,$places]);
        }
        $_SESSION['flash'] = $repeat > 1 ? "$repeat weekly classes created." : 'Schedule created.';
    }
    redirect('?r=schedules');
    break;

case 'schedule_duplicate':
    $sid = (int)($_GET['id'] ?? 0);
    $src = $pdo->query("SELECT * FROM schedules WHERE id=".$sid)->fetch();
    if ($src) {
        $sd = date('Y-m-d', strtotime(($src['start_date'] ?: date('Y-m-d')) . " +7 days"));
        $ed = date('Y-m-d', strtotime(($src['end_date'] ?: $src['start_date'] ?: date('Y-m-d')) . " +7 days"));
        $pdo->prepare("INSERT INTO schedules (plan_id,location_id,trainer_id,name,start_date,end_date,start_time,end_time,total_places) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$src['plan_id'],$src['location_id'],$src['trainer_id'],$src['name'],$sd,$ed,$src['start_time'],$src['end_time'],$src['total_places']]);
        $_SESSION['flash'] = 'Class duplicated to '.$sd.' - edit it if you need a different date.';
    }
    redirect('?r=schedules');
    break;

case 'schedule_delete':
    $sid = (int)($_GET['id'] ?? 0);
    $used = $pdo->prepare("SELECT COUNT(*) FROM enrolments WHERE schedule_id=?"); $used->execute([$sid]);
    if ($used->fetchColumn() > 0) {
        $_SESSION['flash'] = 'Cannot delete - students are booked into this class. Edit it instead.';
    } else {
        $pdo->prepare("DELETE FROM schedules WHERE id=?")->execute([$sid]);
        $_SESSION['flash'] = 'Schedule deleted.';
    }
    redirect('?r=schedules');
    break;

case 'generate':
    require __DIR__ . '/../lib/certificate.php';
    $eid = (int)($_GET['enrolment_id'] ?? 0);
    try { $cert = anb_generate_certificate($pdo, $eid); redirect('?r=cert&num='.urlencode($cert['certificate_number'])); }
    catch (Throwable $ex) {
        $_SESSION['flash'] = 'Could not issue certificate: '.$ex->getMessage();
        $back = $_SERVER['HTTP_REFERER'] ?? '?r=students';
        redirect($back);
    }
    break;

// Ticking by hand is a person's word, not the registry's. It stays available -
// staff do legitimately check in the USI Portal - but it now has to say who and
// why, and it lands in the same audit log as a real registry check.
case 'usi_verify':
    require_once __DIR__.'/../lib/usi.php';
    $sid = (int)($_POST['student_id'] ?? 0);
    $method = ($_POST['method'] ?? 'manual') === 'system' ? 'system' : 'manual';
    $reason = trim((string)($_POST['reason'] ?? ''));
    $u = current_user();
    $who = (string)($u['name'] ?? $u['email'] ?? '');
    $usi = (string)($pdo->query("SELECT usi_number FROM students WHERE id=".$sid)->fetchColumn() ?: '');

    if (!empty($_POST['unverify'])) {
        $pdo->prepare("UPDATE students SET usi_verified=0, usi_verified_date=NULL, usi_verified_method=NULL WHERE id=?")->execute([$sid]);
        anb_usi_log_manual($pdo, $sid, $usi, false, $reason, $who);
        $_SESSION['flash'] = 'USI verification cleared.';
    } elseif ($reason === '') {
        $_SESSION['flash'] = 'Please say how this USI was checked before marking it verified by hand.';
    } else {
        $pdo->prepare("UPDATE students SET usi_verified=1, usi_verified_date=?, usi_verified_method=? WHERE id=?")
            ->execute([date('Y-m-d H:i:s'), $method, $sid]);
        anb_usi_log_manual($pdo, $sid, $usi, true, $reason, $who);
        $_SESSION['flash'] = 'Marked verified by hand, and recorded in the verification log.';
    }
    redirect('?r=student&id='.$sid);
    break;

case 'signoff':
    require __DIR__ . '/../lib/certificate.php';
    require_once __DIR__ . '/../lib/avetmiss.php';
    $sid = (int)($_GET['schedule_id'] ?? 0);
    // find ready enrolments in this schedule not yet issued.
    // AVETMISS readiness is read off the student record, not the stored flag -
    // nothing ever set that flag, so this query used to match nobody, ever.
    $q = $pdo->prepare("
        SELECT e.id FROM enrolments e JOIN students s ON s.id=e.student_id
        WHERE e.schedule_id=? AND e.status!='issued'
          AND e.online_complete=1 AND ".avetmiss_sql_complete('s')." AND e.id_confirmed=1
          AND e.attendance_marked=1 AND e.tasks_satisfactory=1 AND e.payment_status='paid'
          AND s.usi_number IS NOT NULL AND s.usi_number<>'' AND s.usi_verified=1");
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
    if ($cert && (empty($cert['file_path']) || !is_file(__DIR__ . '/../data/' . $cert['file_path']))) {
        require_once __DIR__ . '/../lib/certificate.php';
        try { $cert = anb_ensure_cert_pdf($pdo, $cert); } catch (Throwable $ex) {}
    }
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
    require_once __DIR__.'/../lib/student_portal.php'; sp_schema($pdo);
    require_once __DIR__.'/../lib/avetmiss.php';
    $st = $pdo->prepare("
        SELECT e.*, s.first_name, s.last_name, s.usi_number, s.email,
               s.usi_verified, s.usi_verified_method,
               s.portal_emailed_at, s.portal_attempted_at, s.portal_error,
               ".avetmiss_select_columns('s')."
        FROM enrolments e JOIN students s ON s.id=e.student_id
        WHERE e.schedule_id=? ORDER BY s.last_name");
    $st->execute([$sid]); $rows = $st->fetchAll();
    // AVETMISS readiness is worked out from the record itself - see lib/avetmiss.php.
    $avetTotal = count(avetmiss_required_fields());
    foreach ($rows as $i => $rw) {
        $miss = avetmiss_missing($rw);
        $rows[$i]['avetmiss_missing']  = $miss;
        $rows[$i]['avetmiss_total']    = $avetTotal;   // lets the view tell "part done" from "nothing done"
        $rows[$i]['avetmiss_complete'] = $miss ? 0 : 1;
    }
    render('pipeline', compact('schedule','rows'), 'Class pipeline');
    break;

/**
 * Mark off what happens on the day: ID sighted, attendance, assessment, payment.
 *
 * These four gate the certificate, and until now nothing in the system could
 * set any of them - the pipeline's "All Sighted / All Here / All Satisfactory"
 * buttons were bare markup with no form behind them, so a class could never
 * reach a state where a certificate was allowed to issue.
 */
case 'pipe_mark':
    $schedId = (int)($_POST['schedule_id'] ?? 0);
    $field   = (string)($_POST['field'] ?? '');
    $allowed = ['id_confirmed', 'attendance_marked', 'tasks_satisfactory', 'payment_status'];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$schedId || !in_array($field, $allowed, true)) {
        redirect('?r=pipeline&schedule_id='.$schedId);
    }

    $on = !empty($_POST['on']);
    $val = $field === 'payment_status' ? ($on ? 'paid' : 'unpaid') : ($on ? 1 : 0);

    if (!empty($_POST['all'])) {
        // Whole class, but never touch anyone already certified - their record
        // is evidence now and the certificate was issued against it.
        $q = $pdo->prepare("UPDATE enrolments SET $field=? WHERE schedule_id=? AND status!='issued'");
        $q->execute([$val, $schedId]);
        $_SESSION['flash'] = 'Marked for the whole class.';
    } else {
        $eid = (int)($_POST['enrolment_id'] ?? 0);
        $q = $pdo->prepare("UPDATE enrolments SET $field=? WHERE id=? AND schedule_id=? AND status!='issued'");
        $q->execute([$val, $eid, $schedId]);
    }
    redirect('?r=pipeline&schedule_id='.$schedId);
    break;

case 'class_send_access':
    // Send Student Portal Access to everyone enrolled in ONE class (strictly scoped to this schedule).
    // Only emails students who don't already have access, so we never reset an existing password.
    $sid = (int)($_POST['schedule_id'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $sid <= 0) { redirect('?r=schedules'); }
    require_once __DIR__.'/../lib/student_portal.php'; sp_schema($pdo);
    // mode=all resends to EVERYONE on the class with a brand new password (use when
    // students say the first email never arrived); default only fills the gaps.
    $resendAll = (($_POST['mode'] ?? '') === 'all');
    $list = $resendAll ? sp_class_all($pdo, $sid) : sp_class_pending($pdo, $sid);
    $already = $pdo->prepare("SELECT COUNT(DISTINCT s.id) FROM enrolments e JOIN students s ON s.id=e.student_id
        WHERE e.schedule_id=? AND s.portal_emailed_at IS NOT NULL AND s.portal_emailed_at<>''");
    $already->execute([$sid]); $already = (int)$already->fetchColumn();
    $sent = 0; $failures = [];
    foreach ($list as $stu) {
        [$ok, $m] = sp_send_portal($pdo, $stu);
        if ($ok) $sent++;
        else $failures[] = trim($stu['first_name'].' '.$stu['last_name']).' ('.$m.')';
        usleep(300000);
    }
    if ($resendAll) {
        $flash = "Resent login details to {$sent} student".($sent===1?'':'s')." in this class. Each one now has a brand new password - the older email is no longer valid.";
    } else {
        $flash = "Sent login access to {$sent} student".($sent===1?'':'s').".";
        if ($already) $flash .= " {$already} already had access (not resent, so their existing password still works).";
    }
    if ($failures) {
        $flash .= ' COULD NOT SEND to '.count($failures).': '.implode('; ', array_slice($failures, 0, 5)).'.';
        $_SESSION['flash_error'] = 1;
    }
    $_SESSION['flash'] = $flash;
    redirect('?r=pipeline&schedule_id='.$sid);
    break;

/* ---- RTO Data Cloud sync (mirror SMS enrolments so the USI can be verified there) ---- */
case 'rto_sync':
    require_once __DIR__.'/../lib/rtodata.php'; anb_rto_schema($pdo);
    $mode = anb_rto_mode($pdo);
    $counts = [];
    foreach ($pdo->query("SELECT COALESCE(NULLIF(rto_sync_status,''),'not_queued') s, COUNT(*) c
                          FROM enrolments GROUP BY s") as $rr) $counts[$rr['s']] = (int)$rr['c'];
    // The queue = enrolments made in the SMS that aren't in RTO Data Cloud yet.
    $queue = $pdo->query("SELECT e.id, e.created_at, e.rto_sync_status, e.rto_error, e.rto_enrolment_id,
            s.first_name, s.last_name, s.email, s.usi_number, s.date_of_birth,
            co.code course_code, p.rto_plan_id, sc.rto_schedule_id, sc.start_date
        FROM enrolments e
        JOIN students s ON s.id=e.student_id
        JOIN courses co ON co.id=e.course_id
        JOIN plans p ON p.id=e.plan_id
        LEFT JOIN schedules sc ON sc.id=e.schedule_id
        WHERE (e.rto_enrolment_id IS NULL OR e.rto_enrolment_id='')
          AND COALESCE(e.rto_sync_status,'') NOT IN ('skipped_website','skipped_historical')
          AND e.id IN (SELECT enrolment_id FROM rto_sync_log)
        ORDER BY e.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    $log = $pdo->query("SELECT l.*, s.first_name, s.last_name FROM rto_sync_log l
        LEFT JOIN students s ON s.id=l.student_id ORDER BY l.id DESC LIMIT 40")->fetchAll(PDO::FETCH_ASSOC);
    $unmappedClasses = (int)$pdo->query("SELECT COUNT(*) FROM schedules sc JOIN plans p ON p.id=sc.plan_id
        WHERE (sc.rto_schedule_id IS NULL OR sc.rto_schedule_id='') AND p.rto_plan_id IS NOT NULL AND p.rto_plan_id<>''
          AND sc.start_date >= date('now','-1 day')")->fetchColumn();
    render('rto_sync', compact('mode','counts','queue','log','unmappedClasses'), 'RTO Data Cloud sync');
    break;

case 'rto_sync_mode':
    require_once __DIR__.'/../lib/rtodata.php';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        anb_rto_set_mode($pdo, (string)($_POST['mode'] ?? 'dry'));
        $_SESSION['flash'] = 'RTO Data Cloud sync is now set to: '.anb_rto_mode($pdo).'.';
    }
    redirect('?r=rto_sync');
    break;

case 'rto_sync_push':
    require_once __DIR__.'/../lib/rtodata.php';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $eid = (int)($_POST['enrolment_id'] ?? 0);
        $res = anb_rto_push($pdo, $eid, !empty($_POST['force']));
        $_SESSION['flash'] = ($res['ok'] ? 'RTO Data Cloud: ' : 'RTO Data Cloud problem: ').$res['message'];
    }
    redirect('?r=rto_sync');
    break;

case 'rto_sync_map':
    require_once __DIR__.'/../lib/rtodata.php';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $res = anb_rto_map_schedules($pdo);
        $_SESSION['flash'] = $res['error']
            ? 'Could not read the class list from RTO Data Cloud: '.$res['error']
            : 'Matched '.$res['matched'].' of '.$res['checked'].' classes to RTO Data Cloud.';
    }
    redirect('?r=rto_sync');
    break;

case 'usi_registry':
    require_once __DIR__.'/../lib/usi.php';
    $cfg = anb_usi_config($pdo);
    $log = anb_usi_recent_log($pdo, 25);
    $sandbox = $_SESSION['usi_sandbox'] ?? null;
    unset($_SESSION['usi_sandbox']);
    $pending = (int)$pdo->query("SELECT COUNT(*) FROM students
        WHERE usi_number IS NOT NULL AND usi_number<>'' AND COALESCE(usi_verified,0)=0")->fetchColumn();
    $breakdown = anb_usi_verified_breakdown($pdo);
    render('usi_registry', compact('cfg','log','sandbox','pending','breakdown'), 'USI Registry');
    break;

case 'usi_registry_save':
    require_once __DIR__.'/../lib/usi.php';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $mode = (string)($_POST['usi_mode'] ?? 'off');
        anb_setting_save($pdo, 'usi_mode', in_array($mode,['off','test','live'],true) ? $mode : 'off');
        // Blank org code / credential ID = leave the stored value alone. Same reasoning
        // as the password below: a blank box must never silently wipe a working setting.
        $postedOrg  = strtoupper(trim((string)($_POST['usi_org_code'] ?? '')));
        $postedCred = trim((string)($_POST['usi_credential_id'] ?? ''));
        if ($postedOrg  !== '') anb_setting_save($pdo, 'usi_org_code',      $postedOrg);
        if ($postedCred !== '') anb_setting_save($pdo, 'usi_credential_id', $postedCred);
        // Blank password = leave the stored one alone, so the form can be saved
        // without re-typing it.
        if (trim((string)($_POST['usi_keystore_password'] ?? '')) !== '') {
            anb_setting_save($pdo, 'usi_keystore_password', (string)$_POST['usi_keystore_password']);
        }
        $cfg = anb_usi_config($pdo);
        $_SESSION['flash'] = $cfg['configured']
            ? 'USI Registry settings saved. Mode: '.$cfg['mode'].'.'
            : 'Saved, but not usable yet: '.$cfg['problem'];
    }
    redirect('?r=usi_registry');
    break;

case 'usi_registry_test':
    require_once __DIR__.'/../lib/usi.php';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $u = current_user();
        $_SESSION['usi_sandbox'] = anb_usi_verify_sandbox($pdo, (string)($u['name'] ?? ''));
    }
    redirect('?r=usi_registry');
    break;

case 'usi_check':
    require_once __DIR__.'/../lib/usi.php';
    $sid = (int)($_POST['student_id'] ?? 0);
    $u = current_user();
    $res = anb_usi_verify_student($pdo, $sid, (string)($u['name'] ?? $u['email'] ?? ''));
    $_SESSION['flash'] = ($res['verified'] ? 'USI verified: ' : 'USI not verified. ').$res['message'];
    if (!$res['verified']) $_SESSION['flash_error'] = 1;
    // Checking from the class pipeline should land back on the class, not on
    // one student - the whole point there is working through a list.
    $back = (int)($_POST['schedule_id'] ?? 0);
    redirect($back ? '?r=pipeline&schedule_id='.$back : '?r=student&id='.$sid);
    break;

case 'usi_bulk':
    require_once __DIR__.'/../lib/usi.php';
    $cfg      = anb_usi_config($pdo);
    $progress = anb_usi_bulk_progress($pdo);
    $waiting  = count(anb_usi_bulk_candidates($pdo));
    $problems = anb_usi_bulk_problems($pdo);
    $buckets  = [];
    foreach ($problems as $p) {
        $b = anb_usi_bulk_reason_bucket((string)$p['reason']);
        $buckets[$b] = ($buckets[$b] ?? 0) + 1;
    }
    arsort($buckets);
    render('usi_bulk', compact('cfg','progress','waiting','problems','buckets'), 'Bulk USI verification');
    break;

case 'usi_bulk_start':
    require_once __DIR__.'/../lib/usi.php';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $cfg = anb_usi_config($pdo);
        if ($cfg['mode'] !== 'live') {
            $_SESSION['flash'] = 'Switch the USI Registry to Live before running a bulk check.';
        } else {
            $u = current_user();
            anb_usi_bulk_start($pdo, (string)($u['name'] ?? $u['email'] ?? ''));
        }
    }
    redirect('?r=usi_bulk');
    break;

case 'usi_bulk_stop':
    require_once __DIR__.'/../lib/usi.php';
    if ($_SERVER['REQUEST_METHOD']==='POST') anb_usi_bulk_stop($pdo);
    redirect('?r=usi_bulk');
    break;

// Called repeatedly by the page while a run is going. Returns JSON, never HTML,
// so a PHP notice would be visible rather than silently breaking the loop.
case 'usi_bulk_step':
    require_once __DIR__.'/../lib/usi.php';
    header('Content-Type: application/json');
    $u = current_user();
    try {
        $out = anb_usi_bulk_step($pdo, (string)($u['name'] ?? $u['email'] ?? ''), (int)($_GET['n'] ?? 5));
    } catch (Throwable $e) {
        http_response_code(500);
        $out = ['error' => $e->getMessage()];
    }
    echo json_encode($out);
    exit;

case 'usi_bulk_csv':
    require_once __DIR__.'/../lib/usi.php';
    $rows = anb_usi_bulk_problems($pdo);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="usi-problems-'.date('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID','First name','Family name','Date of birth','USI','Email','Registry status','Problem','Category','Checked at']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['student_id'], $r['first_name'], $r['last_name'], $r['date_of_birth'],
            $r['usi_number'], $r['email'], $r['status'], $r['reason'],
            anb_usi_bulk_reason_bucket((string)$r['reason']), $r['checked_at'],
        ]);
    }
    fclose($out);
    exit;

/* ---- name repair: the "(unknown)" import artefact ---- */

case 'usi_repair':
    require_once __DIR__.'/../lib/usi.php';
    $cfg      = anb_usi_config($pdo);
    $progress = anb_usi_repair_progress($pdo);
    $waiting  = count(anb_usi_repair_candidates($pdo));
    $rows     = anb_usi_repair_rows($pdo);
    render('usi_repair', compact('cfg','progress','waiting','rows'), 'Fix imported names');
    break;

case 'usi_repair_start':
    require_once __DIR__.'/../lib/usi.php';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $cfg = anb_usi_config($pdo);
        if ($cfg['mode'] !== 'live') {
            $_SESSION['flash'] = 'Switch the USI Registry to Live before scanning.';
        } else {
            anb_usi_repair_start($pdo);
        }
    }
    redirect('?r=usi_repair');
    break;

case 'usi_repair_stop':
    require_once __DIR__.'/../lib/usi.php';
    if ($_SERVER['REQUEST_METHOD']==='POST') anb_usi_repair_stop($pdo);
    redirect('?r=usi_repair');
    break;

case 'usi_repair_step':
    require_once __DIR__.'/../lib/usi.php';
    header('Content-Type: application/json');
    try {
        $out = anb_usi_repair_step($pdo, (int)($_GET['n'] ?? 3));
    } catch (Throwable $e) {
        http_response_code(500);
        $out = ['error' => $e->getMessage()];
    }
    echo json_encode($out);
    exit;

// Writing to student records is an administrator action, not something the
// office should be able to do to 164 records with one click.
case 'usi_repair_apply':
    require_once __DIR__.'/../lib/usi.php';
    $u = current_user();
    if (($u['role'] ?? 'admin') !== 'admin') {
        $_SESSION['flash'] = 'Only an administrator can save these changes.';
        redirect('?r=usi_repair');
    }
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $res = anb_usi_repair_apply($pdo, (string)($u['name'] ?? $u['email'] ?? ''), (int)($_POST['limit'] ?? 25));
        $_SESSION['flash'] = 'Saved ' . $res['saved'] . ' name' . ($res['saved']===1?'':'s')
            . ', ' . $res['verified'] . ' now verified against the registry'
            . ($res['failed'] ? ', ' . $res['failed'] . ' need another look' : '') . '.';
    }
    redirect('?r=usi_repair');
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
    $addr = trim($_POST['address'] ?? '');
    $ident= trim($_POST['identifier'] ?? '');
    $sub  = trim($_POST['suburb'] ?? '');
    $st8  = trim($_POST['state'] ?? '');
    $pc   = trim($_POST['postcode'] ?? '');
    $act  = isset($_POST['active']) ? 1 : 0;
    if ($name === '') {
        $_SESSION['flash'] = 'Please enter a location name.';
    } elseif ($id) {
        $pdo->prepare("UPDATE locations SET name=?,address=?,identifier=?,suburb=?,state=?,postcode=?,active=? WHERE id=?")
            ->execute([$name,$addr,$ident,$sub,$st8,$pc,$act,$id]);
        $_SESSION['flash'] = 'Location updated.';
    } else {
        $pdo->prepare("INSERT INTO locations (name,address,identifier,suburb,state,postcode,active) VALUES (?,?,?,?,?,?,1)")
            ->execute([$name,$addr,$ident,$sub,$st8,$pc]);
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

case 'obs_list':   // trainer: learners to observe for a practical module
    require __DIR__ . '/../lib/lms.php'; lms_ensure_schema($pdo);
    $module = lms_module($pdo, (int)($_GET['module_id'] ?? 0));
    if (!$module) redirect('?r=content');
    $learners = lms_course_learners($pdo, (int)$module['course_id']);
    foreach ($learners as &$L) {
        $ob = lms_observation($pdo, (int)$L['enrolment_id'], (int)$module['id']);
        $L['overall'] = $ob['overall'] ?? null;
        $ackq = $pdo->prepare("SELECT status FROM learner_progress WHERE enrolment_id=? AND module_id=?");
        $ackq->execute([(int)$L['enrolment_id'], (int)$module['id']]);
        $L['ack'] = ($ackq->fetchColumn() === 'completed');
    }
    unset($L);
    render('obs_list', compact('module','learners'), 'Observations');
    break;

case 'obs_mark':   // trainer: mark the observation checklist for one learner
    require __DIR__ . '/../lib/lms.php'; lms_ensure_schema($pdo);
    $module  = lms_module($pdo, (int)($_GET['module_id'] ?? 0));
    $enrolId = (int)($_GET['enrol'] ?? 0);
    if (!$module || !$enrolId) redirect('?r=content');
    $skills  = (array)json_decode($module['skills'] ?? '[]', true);
    $ob      = lms_observation($pdo, $enrolId, (int)$module['id']);
    $learner = $pdo->query("SELECT s.* FROM enrolments e JOIN students s ON s.id=e.student_id WHERE e.id=".$enrolId)->fetch() ?: null;
    render('obs_mark', compact('module','skills','ob','learner','enrolId'), 'Observation');
    break;

case 'obs_save':
    require __DIR__ . '/../lib/lms.php'; lms_ensure_schema($pdo);
    $moduleId = (int)($_POST['module_id'] ?? 0);
    $enrolId  = (int)($_POST['enrol'] ?? 0);
    $results  = $_POST['r'] ?? [];
    $overall  = ($_POST['overall'] ?? '') === 'satisfactory' ? 'satisfactory' : 'not_yet';
    $assessor = trim($_POST['assessor'] ?? '');
    $comments = trim($_POST['comments'] ?? '');
    if ($moduleId && $enrolId) {
        lms_save_observation($pdo, $enrolId, $moduleId, $results, $overall, $assessor, $comments);
        $_SESSION['flash'] = 'Observation saved.';
    }
    redirect('?r=obs_list&module_id='.$moduleId);
    break;

// ---------- Email Templates ----------
case 'emails':
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_templates (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, subject TEXT, body TEXT, updated_at TEXT)");
    if ((int)$pdo->query("SELECT COUNT(*) FROM email_templates")->fetchColumn() === 0) {
        $seed = [
          ["Certificate Issued","Your certificate from A&B First Aid Training","Hi {first_name},\n\nCongratulations on completing {course}. Your Statement of Attainment ({certificate_number}) is available to download here: {certificate_link}\n\nIssued: {issue_date}   Expiry: {expiry_date}\n\nKind regards,\nA&B First Aid Training"],
          ["Survey Request","We would love your feedback","Hi {first_name},\n\nThank you for training with us. Please take a moment to complete this short survey: {survey_link}\n\nKind regards,\nA&B First Aid Training"],
          ["Renewal Reminder","Your first aid certificate is due for renewal","Hi {first_name},\n\nOur records show your {course} certificate expires on {expiry_date}. Book your refresher here: {booking_link}\n\nKind regards,\nA&B First Aid Training"],
          ["Enrolment Confirmation","Your enrolment is confirmed","Hi {first_name},\n\nYour enrolment in {course} on {class_date} at {location} is confirmed.\n\nPlease complete your online learning before class: {portal_link}\n\nKind regards,\nA&B First Aid Training"],
        ];
        $ins = $pdo->prepare("INSERT INTO email_templates (name,subject,body,updated_at) VALUES (?,?,?,datetime('now'))");
        foreach ($seed as $s) $ins->execute($s);
    }
    $editId = (int)($_GET['edit'] ?? 0);
    $edit = $editId ? $pdo->query("SELECT * FROM email_templates WHERE id=".$editId)->fetch() : null;
    $rows = $pdo->query("SELECT * FROM email_templates ORDER BY name")->fetchAll();
    render('emails', compact('rows','edit'), 'Email Templates');
    break;

case 'email_save':
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_templates (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, subject TEXT, body TEXT, updated_at TEXT)");
    $id=(int)($_POST['id']??0); $name=trim($_POST['name']??''); $subject=trim($_POST['subject']??''); $body=trim($_POST['body']??'');
    if ($name!=='') {
        if ($id) $pdo->prepare("UPDATE email_templates SET name=?,subject=?,body=?,updated_at=datetime('now') WHERE id=?")->execute([$name,$subject,$body,$id]);
        else     $pdo->prepare("INSERT INTO email_templates (name,subject,body,updated_at) VALUES (?,?,?,datetime('now'))")->execute([$name,$subject,$body]);
        $_SESSION['flash']='Template saved.';
    } else $_SESSION['flash']='Please enter a template name.';
    redirect('?r=emails');
    break;

case 'email_delete':
    $pdo->prepare("DELETE FROM email_templates WHERE id=?")->execute([(int)($_GET['id']??0)]);
    $_SESSION['flash']='Template deleted.';
    redirect('?r=emails');
    break;

case 'review_links':
    require_once __DIR__ . '/../lib/mailer.php';
    $settings = anb_settings($pdo);
    $locs = $pdo->query("SELECT id,name,suburb FROM locations WHERE active=1 ORDER BY name")->fetchAll();
    render('review_links', compact('settings','locs'), 'Google Review Links');
    break;

case 'review_links_save':
    require_once __DIR__ . '/../lib/mailer.php';
    anb_setting_save($pdo, 'review_url_default', trim($_POST['review_url_default'] ?? ''));
    foreach (($_POST['review_url'] ?? []) as $lid=>$url)
        anb_setting_save($pdo, 'review_url_'.(int)$lid, trim((string)$url));
    $_SESSION['flash'] = 'Google review links saved.';
    redirect('?r=review_links');
    break;

case 'email_settings':
    require_once __DIR__ . '/../lib/mailer.php';
    $settings = anb_settings($pdo);
    render('email_settings', compact('settings'), 'Email Settings');
    break;

case 'email_settings_save':
    require_once __DIR__ . '/../lib/mailer.php';
    foreach (['smtp_host','smtp_port','smtp_security','smtp_user','mail_from','mail_from_name'] as $k)
        anb_setting_save($pdo, $k, trim($_POST[$k] ?? ''));
    // only overwrite the password if a new one was typed (blank = keep existing)
    if (($_POST['smtp_pass'] ?? '') !== '') anb_setting_save($pdo, 'smtp_pass', (string)$_POST['smtp_pass']);
    $_SESSION['flash'] = 'Email settings saved.';
    redirect('?r=email_settings');
    break;

case 'email_test':
    require_once __DIR__ . '/../lib/mailer.php';
    $to = trim($_POST['to'] ?? '');
    if ($to === '') { $_SESSION['flash'] = 'Enter a recipient address for the test.'; redirect('?r=email_settings'); }
    [$ok,$err] = anb_send_mail($pdo, $to,
        'Test email from A&B First Aid Training',
        '<p>This is a test email from your A&amp;B First Aid Training system.</p>'
        .'<p>If you can read this, SMTP sending is working correctly.</p>');
    $_SESSION['flash'] = $ok ? "Test email sent to $to." : "Test failed: $err";
    redirect('?r=email_settings');
    break;

case 'cert_email':
    require_once __DIR__ . '/../lib/mailer.php';
    require_once __DIR__ . '/../lib/certificate.php';
    $cid = (int)($_GET['id'] ?? 0);
    $c = $pdo->prepare("SELECT c.*, s.first_name, s.last_name, s.email, co.title course_title, co.code course_code
        FROM certificates c JOIN students s ON s.id=c.student_id
        JOIN enrolments e ON e.id=c.enrolment_id JOIN courses co ON co.id=e.course_id WHERE c.id=?");
    $c->execute([$cid]); $cert = $c->fetch();
    if (!$cert) { http_response_code(404); echo 'Certificate not found'; break; }
    if (empty($cert['email'])) { $_SESSION['flash'] = 'That student has no email address on file.'; redirect('?r=certificates'); }
    // ensure the PDF exists (lazy-render migrated certs)
    $cert = anb_ensure_cert_pdf($pdo, $cert);
    $pdfPath = __DIR__ . '/../data/' . $cert['file_path'];
    // build the email from the "Certificate Issued" template (fallback to a default)
    $tpl = $pdo->query("SELECT * FROM email_templates WHERE name='Certificate Issued' LIMIT 1")->fetch();
    $vars = [
        'first_name'=>$cert['first_name'], 'last_name'=>$cert['last_name'],
        'course'=>$cert['course_code'].' - '.$cert['course_title'],
        'certificate_number'=>$cert['certificate_number'],
        'certificate_link'=>ANB_VERIFY_BASE.'/?r=cert&num='.urlencode($cert['certificate_number']),
        'issue_date'=>date('d-m-Y', strtotime((string)$cert['issue_date'])),
        'expiry_date'=>$cert['expiry_date'] ? date('d-m-Y', strtotime((string)$cert['expiry_date'])) : '',
    ];
    $subject = anb_merge($tpl['subject'] ?? 'Your certificate from A&B First Aid Training', $vars);
    $bodyTxt = anb_merge($tpl['body'] ?? "Hi {first_name},\n\nYour certificate {certificate_number} is attached.\n\nA&B First Aid Training", $vars);
    $bodyHtml = nl2br(e($bodyTxt));
    [$ok,$err] = anb_send_mail($pdo, $cert['email'], $subject, $bodyHtml,
        [['path'=>$pdfPath, 'name'=>$cert['certificate_number'].'.pdf']]);
    if ($ok) {
        $pdo->prepare("UPDATE certificates SET emailed_at=datetime('now') WHERE id=?")->execute([$cid]);
        $_SESSION['flash'] = 'Certificate emailed to '.$cert['email'].'.';
    } else $_SESSION['flash'] = 'Could not send: '.$err;
    redirect('?r=certificates');
    break;

// ---------- Organisation Management (files) ----------
case 'management':
    $pdo->exec("CREATE TABLE IF NOT EXISTS org_files (id INTEGER PRIMARY KEY AUTOINCREMENT, category TEXT, title TEXT, notes TEXT, file_path TEXT, original_name TEXT, uploaded_at TEXT)");
    $cats = ['Meetings','Quality Improvement','Complaints & Appeals','Events','Document Management','Compliance Management'];
    $cat = $_GET['cat'] ?? '';
    if ($cat!=='' && in_array($cat,$cats,true)) { $st=$pdo->prepare("SELECT * FROM org_files WHERE category=? ORDER BY uploaded_at DESC"); $st->execute([$cat]); $rows=$st->fetchAll(); }
    else { $rows=$pdo->query("SELECT * FROM org_files ORDER BY uploaded_at DESC")->fetchAll(); }
    $counts=[]; foreach ($pdo->query("SELECT category,COUNT(*) c FROM org_files GROUP BY category") as $rr) $counts[$rr['category']]=$rr['c'];
    render('management', compact('cats','cat','rows','counts'), 'Management');
    break;

case 'management_upload':
    $pdo->exec("CREATE TABLE IF NOT EXISTS org_files (id INTEGER PRIMARY KEY AUTOINCREMENT, category TEXT, title TEXT, notes TEXT, file_path TEXT, original_name TEXT, uploaded_at TEXT)");
    $cat=trim($_POST['category']??''); $title=trim($_POST['title']??''); $notes=trim($_POST['notes']??'');
    try {
        if ($title===''||$cat==='') throw new RuntimeException('Please choose a category and enter a title.');
        if (empty($_FILES['file']['tmp_name'])||($_FILES['file']['error']??1)!==UPLOAD_ERR_OK) throw new RuntimeException('Please choose a file to upload.');
        $dir=__DIR__.'/../data/org_files'; if(!is_dir($dir)) mkdir($dir,0775,true);
        $orig=$_FILES['file']['name']; $safe=preg_replace('/[^A-Za-z0-9._-]+/','_',$orig);
        $fname=date('Ymd_His').'_'.substr($safe,0,80);
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir.'/'.$fname)) throw new RuntimeException('Could not save the file.');
        $pdo->prepare("INSERT INTO org_files (category,title,notes,file_path,original_name,uploaded_at) VALUES (?,?,?,?,?,datetime('now'))")
            ->execute([$cat,$title,$notes,'org_files/'.$fname,$orig]);
        $_SESSION['flash']='File uploaded.';
    } catch (Throwable $e) { $_SESSION['flash']='Upload failed: '.$e->getMessage(); }
    redirect('?r=management&cat='.urlencode($cat));
    break;

case 'management_download':
    $f=$pdo->query("SELECT * FROM org_files WHERE id=".(int)($_GET['id']??0))->fetch();
    if ($f) { $path=__DIR__.'/../data/'.$f['file_path'];
        if (is_file($path)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($f['original_name']).'"');
            header('Content-Length: '.filesize($path));
            readfile($path); exit;
        } }
    http_response_code(404); echo 'File not found'; exit;

case 'management_delete':
    $f=$pdo->query("SELECT * FROM org_files WHERE id=".(int)($_GET['id']??0))->fetch();
    if ($f) { @unlink(__DIR__.'/../data/'.$f['file_path']); $pdo->prepare("DELETE FROM org_files WHERE id=?")->execute([(int)$f['id']]); $_SESSION['flash']='File deleted.'; }
    redirect('?r=management');
    break;

case 'certificates':
    require_once __DIR__ . '/../lib/certificate.php'; anb_ensure_reference_data($pdo);
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
    $type     = in_array($_POST['type'] ?? 'quiz', ['quiz','incident_report','practical'], true) ? $_POST['type'] : 'quiz';
    $title    = trim($_POST['title'] ?? '') ?: (['incident_report'=>'Incident Report','practical'=>'Practical Assessment'][$type] ?? 'Knowledge Check');
    $pass     = (int)($_POST['pass_mark'] ?? 80);
    $body     = trim($_POST['body'] ?? '');
    if ($courseId) {
        $pos = (int)$pdo->query("SELECT COALESCE(MAX(position),0)+1 FROM course_modules")->fetchColumn();
        if ($type === 'practical') {
            $skills = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $_POST['skills'] ?? ''))));
            $ack    = trim($_POST['ack_text'] ?? '');
            $pdo->prepare("INSERT INTO course_modules (course_id,title,type,body,skills,ack_text,position) VALUES (?,?,'practical',?,?,?,?)")
                ->execute([$courseId,$title,$body,json_encode($skills),$ack,$pos]);
            $_SESSION['flash'] = 'Practical assessment activity created.';
            redirect('?r=content');
        }
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

case 'compliance':
    require __DIR__ . '/../lib/compliance.php';
    comp_schema($pdo); comp_migrate($pdo); comp_seed_ci($pdo); comp_seed_stage2($pdo);
    $tab = $_GET['tab'] ?? 'dashboard';
    $equip = $pdo->query("SELECT * FROM equipment ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);
    $trainers = $pdo->query("SELECT * FROM trainer_profiles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $tquals = [];
    foreach ($pdo->query("SELECT * FROM trainer_quals ORDER BY trainer_id, qual_type") as $tqr) $tquals[$tqr['trainer_id']][] = $tqr;
    $sysUsers = $pdo->query("SELECT id,name,email,role,is_trainer,active FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $editEquip = null; if(isset($_GET['equip_edit'])){ $x=$pdo->prepare("SELECT * FROM equipment WHERE id=?"); $x->execute([(int)$_GET['equip_edit']]); $editEquip=$x->fetch(PDO::FETCH_ASSOC)?:null; }
    $editTrainer=null; if(isset($_GET['trainer_edit'])){ $x=$pdo->prepare("SELECT * FROM trainer_profiles WHERE id=?"); $x->execute([(int)$_GET['trainer_edit']]); $editTrainer=$x->fetch(PDO::FETCH_ASSOC)?:null; }
    $tax = comp_tax(); $units = comp_units();
    $canEdit = comp_can_edit();
    // filters
    $fSection = $_GET['section'] ?? ''; $fUnit = $_GET['unit'] ?? ''; $fStatus = $_GET['status'] ?? '';
    $fQ = trim($_GET['q'] ?? '');
    $where = []; $args = [];
    if ($fSection!=='') { $where[]='section=?'; $args[]=$fSection; }
    if ($fUnit!=='')    { $where[]='unit_code=?'; $args[]=$fUnit; }
    if ($fStatus!=='')  { $where[]='status=?'; $args[]=$fStatus; }
    if ($fQ!=='')       { $where[]='(doc_name LIKE ? OR subcategory LIKE ? OR unit_code LIKE ?)'; array_push($args,"%$fQ%","%$fQ%","%$fQ%"); }
    $sql = "SELECT * FROM compliance_docs".($where?(' WHERE '.implode(' AND ',$where)):'')." ORDER BY section, unit_code, doc_name";
    $stq = $pdo->prepare($sql); $stq->execute($args); $docs = $stq->fetchAll(PDO::FETCH_ASSOC);
    $secCounts = []; foreach ($pdo->query("SELECT section, COUNT(*) c FROM compliance_docs GROUP BY section") as $rr) $secCounts[$rr['section']]=$rr['c'];
    $dash = comp_dashboard($pdo);
    $ci = $pdo->query("SELECT * FROM ci_register ORDER BY (status='Completed'), due_date")->fetchAll(PDO::FETCH_ASSOC);
    $editDoc = null;
    if (isset($_GET['edit'])) { $e=$pdo->prepare("SELECT * FROM compliance_docs WHERE id=?"); $e->execute([(int)$_GET['edit']]); $editDoc=$e->fetch(PDO::FETCH_ASSOC) ?: null; }
    $auditRows = []; $verRows = [];
    if (isset($_GET['view'])) {
        $vid=(int)$_GET['view'];
        $v=$pdo->prepare("SELECT * FROM compliance_docs WHERE id=?"); $v->execute([$vid]); $editDoc=$v->fetch(PDO::FETCH_ASSOC) ?: null;
        $auditRows=$pdo->query("SELECT * FROM compliance_audit WHERE doc_id=$vid ORDER BY at DESC")->fetchAll(PDO::FETCH_ASSOC);
        $verRows=$pdo->query("SELECT * FROM compliance_versions WHERE doc_id=$vid ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }
    render('compliance', compact('tab','tax','units','canEdit','docs','secCounts','dash','ci','editDoc','auditRows','verRows','fSection','fUnit','fStatus','fQ','equip','trainers','tquals','sysUsers','editEquip','editTrainer'), 'Compliance Management');
    break;

case 'comp_equip_save':
    require __DIR__ . '/../lib/compliance.php'; comp_schema($pdo);
    if (!comp_can_edit()) { $_SESSION['flash']='Permission denied.'; redirect('?r=compliance&tab=equipment'); }
    $id=(int)($_POST['id']??0);
    $f=['name'=>trim($_POST['name']??''),'category'=>trim($_POST['category']??''),'asset_id'=>trim($_POST['asset_id']??'') ?: null,
        'location'=>trim($_POST['location']??'') ?: null,'purchase_date'=>trim($_POST['purchase_date']??'') ?: null,
        'last_service_date'=>trim($_POST['last_service_date']??'') ?: null,'next_service_date'=>trim($_POST['next_service_date']??'') ?: null,
        'replacement_date'=>trim($_POST['replacement_date']??'') ?: null,'status'=>trim($_POST['status']??'In Service'),'notes'=>trim($_POST['notes']??'') ?: null];
    if ($f['name']===''){ $_SESSION['flash']='Equipment name required.'; redirect('?r=compliance&tab=equipment'); }
    if ($id>0){ $sets=[];$a=[]; foreach($f as $k=>$v){$sets[]="$k=?";$a[]=$v;} $sets[]="updated_at=datetime('now')"; $a[]=$id;
        $pdo->prepare("UPDATE equipment SET ".implode(',',$sets)." WHERE id=?")->execute($a); $_SESSION['flash']='Equipment updated.'; }
    else { $cols=array_keys($f);$ph=implode(',',array_fill(0,count($cols),'?'));
        $pdo->prepare("INSERT INTO equipment (".implode(',',$cols).") VALUES ($ph)")->execute(array_values($f)); $_SESSION['flash']='Equipment added.'; }
    redirect('?r=compliance&tab=equipment');
    break;

case 'trainer_save':
    require __DIR__ . '/../lib/compliance.php'; comp_schema($pdo);
    if (!comp_can_edit()) { $_SESSION['flash']='Permission denied.'; redirect('?r=compliance&tab=trainers'); }
    $id=(int)($_POST['id']??0);
    $f=['name'=>trim($_POST['name']??''),'email'=>trim($_POST['email']??'') ?: null,'phone'=>trim($_POST['phone']??'') ?: null,
        'position'=>trim($_POST['position']??'') ?: null,'notes'=>trim($_POST['notes']??'') ?: null,'active'=>isset($_POST['active'])?1:1];
    if ($f['name']===''){ $_SESSION['flash']='Trainer name required.'; redirect('?r=compliance&tab=trainers'); }
    if ($id>0){ $pdo->prepare("UPDATE trainer_profiles SET name=?,email=?,phone=?,position=?,notes=? WHERE id=?")
        ->execute([$f['name'],$f['email'],$f['phone'],$f['position'],$f['notes'],$id]); $_SESSION['flash']='Trainer updated.'; }
    else { $pdo->prepare("INSERT INTO trainer_profiles (name,email,phone,position,notes) VALUES (?,?,?,?,?)")
        ->execute([$f['name'],$f['email'],$f['phone'],$f['position'],$f['notes']]); $_SESSION['flash']='Trainer added.'; }
    redirect('?r=compliance&tab=trainers');
    break;

case 'trainer_qual_save':
    require __DIR__ . '/../lib/compliance.php'; comp_schema($pdo);
    if (!comp_can_edit()) { $_SESSION['flash']='Permission denied.'; redirect('?r=compliance&tab=trainers'); }
    $tid=(int)($_POST['trainer_id']??0); $qid=(int)($_POST['qid']??0);
    $filePath=null;$orig=null;
    if (!empty($_FILES['file']['tmp_name']) && ($_FILES['file']['error']??1)===UPLOAD_ERR_OK) {
        $dir=__DIR__.'/../data/org_files'; if(!is_dir($dir)) mkdir($dir,0775,true);
        $orig=$_FILES['file']['name']; $safe=preg_replace('/[^A-Za-z0-9._-]+/','_',$orig);
        $fn='cert_'.date('Ymd_His').'_'.substr($safe,0,80);
        if (move_uploaded_file($_FILES['file']['tmp_name'],$dir.'/'.$fn)) $filePath='org_files/'.$fn;
    }
    $f=['trainer_id'=>$tid,'qual_type'=>trim($_POST['qual_type']??'Qualification'),'title'=>trim($_POST['title']??''),
        'code'=>trim($_POST['code']??'') ?: null,'issued_date'=>trim($_POST['issued_date']??'') ?: null,
        'expiry_date'=>trim($_POST['expiry_date']??'') ?: null,'notes'=>trim($_POST['notes']??'') ?: null];
    if ($qid>0){ $sets=[];$a=[]; foreach($f as $k=>$v){ if($k==='trainer_id') continue; $sets[]="$k=?";$a[]=$v; }
        if($filePath){$sets[]="file_path=?";$a[]=$filePath;$sets[]="original_name=?";$a[]=$orig;} $a[]=$qid;
        $pdo->prepare("UPDATE trainer_quals SET ".implode(',',$sets)." WHERE id=?")->execute($a); $_SESSION['flash']='Qualification updated.'; }
    else { $cols=array_keys($f);$vals=array_values($f); if($filePath){$cols[]='file_path';$vals[]=$filePath;$cols[]='original_name';$vals[]=$orig;}
        $ph=implode(',',array_fill(0,count($cols),'?'));
        $pdo->prepare("INSERT INTO trainer_quals (".implode(',',$cols).") VALUES ($ph)")->execute($vals); $_SESSION['flash']='Qualification added.'; }
    redirect('?r=compliance&tab=trainers&trainer_edit='.$tid);
    break;

case 'trainer_ins_download':
    require __DIR__ . '/../lib/compliance.php'; comp_schema($pdo);
    $tp=$pdo->prepare("SELECT * FROM trainer_profiles WHERE id=?"); $tp->execute([(int)($_GET['id']??0)]); $tp=$tp->fetch(PDO::FETCH_ASSOC);
    // a trainer may only fetch their own insurance file; admins/compliance can fetch any
    $cu=current_user();
    if ($tp && !empty($tp['insurance_file']) && (($cu['role']??'')!=='trainer' || strcasecmp($cu['email']??'',$tp['email']??'')===0)) {
        $p=__DIR__.'/../data/'.$tp['insurance_file'];
        if (is_file($p)) { header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="'.basename($tp['insurance_original_name']?:'insurance').'"'); header('Content-Length: '.filesize($p)); readfile($p); exit; }
    }
    http_response_code(404); echo 'File not found'; exit;

case 'trainer_insurance_save':
    require __DIR__ . '/../lib/compliance.php'; comp_schema($pdo);
    if (!comp_can_edit()) { $_SESSION['flash']='Permission denied.'; redirect('?r=compliance&tab=trainers'); }
    $tid=(int)($_POST['trainer_id']??0);
    $types=$_POST['insurance_type']??[]; $typeStr=is_array($types)?implode('; ',array_map('trim',$types)):trim((string)$types);
    $sets=['insurance_type=?','insurance_provider=?','insurance_policy_no=?','insurance_expiry=?'];
    $vals=[$typeStr, trim($_POST['insurance_provider']??'')?:null, trim($_POST['insurance_policy_no']??'')?:null, trim($_POST['insurance_expiry']??'')?:null];
    if (!empty($_FILES['file']['tmp_name']) && ($_FILES['file']['error']??1)===UPLOAD_ERR_OK) {
        $dir=__DIR__.'/../data/org_files'; if(!is_dir($dir)) mkdir($dir,0775,true);
        $orig=$_FILES['file']['name']; $safe=preg_replace('/[^A-Za-z0-9._-]+/','_',$orig); $fn='ins_'.date('Ymd_His').'_'.substr($safe,0,80);
        if (move_uploaded_file($_FILES['file']['tmp_name'],$dir.'/'.$fn)) { $sets[]='insurance_file=?';$vals[]='org_files/'.$fn; $sets[]='insurance_original_name=?';$vals[]=$orig; }
    }
    $vals[]=$tid; $pdo->prepare("UPDATE trainer_profiles SET ".implode(',',$sets)." WHERE id=?")->execute($vals);
    $_SESSION['flash']='Insurance details saved.'; redirect('?r=compliance&tab=trainers&trainer_edit='.$tid);
    break;

case 'trainer_cert_download':
    require __DIR__ . '/../lib/compliance.php'; comp_schema($pdo);
    $q=$pdo->prepare("SELECT * FROM trainer_quals WHERE id=?"); $q->execute([(int)($_GET['id']??0)]); $q=$q->fetch(PDO::FETCH_ASSOC);
    if ($q && $q['file_path']) { $p=__DIR__.'/../data/'.$q['file_path'];
        if (is_file($p)) { header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="'.basename($q['original_name']?:'certificate').'"'); header('Content-Length: '.filesize($p)); readfile($p); exit; } }
    http_response_code(404); echo 'File not found'; exit;

case 'user_save':
    require __DIR__ . '/../lib/compliance.php';
    // creating / editing staff logins with roles is an administrator function
    if (!comp_role_admin_or_cm()) { $_SESSION['flash']='Permission denied.'; redirect('?r=compliance&tab=users'); }
    $id=(int)($_POST['id']??0);
    $name=trim($_POST['name']??''); $email=trim($_POST['email']??''); $role=trim($_POST['role']??'office');
    $role=in_array($role,['admin','compliance_manager','trainer','office','auditor'],true)?$role:'office';
    $isTrainer=in_array($role,['trainer'],true)?1:0;
    $pw=$_POST['password']??'';
    try {
        if ($name===''||$email==='') throw new RuntimeException('Name and email are required.');
        if ($id>0){
            if ($pw!=='') $pdo->prepare("UPDATE users SET name=?,email=?,role=?,is_trainer=?,password=? WHERE id=?")->execute([$name,$email,$role,$isTrainer,password_hash($pw,PASSWORD_DEFAULT),$id]);
            else $pdo->prepare("UPDATE users SET name=?,email=?,role=?,is_trainer=? WHERE id=?")->execute([$name,$email,$role,$isTrainer,$id]);
            $_SESSION['flash']='User updated.';
        } else {
            if ($pw==='') throw new RuntimeException('Password is required for a new user.');
            $pdo->prepare("INSERT INTO users (name,email,password,role,is_trainer,active) VALUES (?,?,?,?,?,1)")->execute([$name,$email,password_hash($pw,PASSWORD_DEFAULT),$role,$isTrainer]);
            $_SESSION['flash']='User created.';
        }
    } catch (Throwable $e){ $_SESSION['flash']='Could not save user: '.$e->getMessage(); }
    redirect('?r=compliance&tab=users');
    break;

case 'comp_reminders':
    // token-guarded digest emailer - schedule via cron/cPanel to hit this URL daily/weekly.
    require __DIR__ . '/../lib/compliance.php'; comp_schema($pdo);
    if (($_GET['token'] ?? '') !== 'anb-comp-reminders') { http_response_code(403); echo 'forbidden'; exit; }
    $dash = comp_dashboard($pdo);
    $lines = [];
    if ($dash['overdue']) { $lines[]='REVIEWS OVERDUE:'; foreach($dash['overdue'] as $o) $lines[]=" - {$o['doc_name']} (due {$o['review_date']})"; }
    if ($dash['due_soon']) { $lines[]='REVIEWS DUE SOON:'; foreach($dash['due_soon'] as $o) $lines[]=" - {$o['doc_name']} (due {$o['review_date']})"; }
    if ($dash['equip_due']) { $lines[]='EQUIPMENT MAINTENANCE DUE:'; foreach($dash['equip_due'] as $o) $lines[]=" - {$o['name']} (service due {$o['next_service_date']})"; }
    if ($dash['qual_exp']) { $lines[]='TRAINER QUALIFICATIONS EXPIRING:'; foreach($dash['qual_exp'] as $o) $lines[]=" - {$o['trainer_name']}: {$o['title']} (expires {$o['expiry_date']})"; }
    if (!empty($dash['ins_exp'])) { $lines[]='TRAINER INSURANCE EXPIRING:'; foreach($dash['ins_exp'] as $o) $lines[]=" - {$o['name']}: ".($o['insurance_provider']?:'insurance')." (expires {$o['insurance_expiry']})"; }
    if ($dash['ci_open']) { $lines[]='OUTSTANDING IMPROVEMENT ACTIONS:'; foreach($dash['ci_open'] as $o) $lines[]=" - {$o['ref']}: {$o['description']} (due {$o['due_date']})"; }
    $sent=false; $err='';
    if ($lines) {
        require_once __DIR__ . '/../lib/mailer.php';
        $html='<h3>Compliance reminders — A&amp;B First Aid Training (RTO 46055)</h3><pre style="font-family:inherit;font-size:14px;">'
              .htmlspecialchars(implode("\n",$lines)).'</pre><p>Open the Compliance Module for details.</p>';
        [$ok,$msg]=anb_send_mail($pdo,'admin@anbfirstaidtraining.com.au','Compliance reminders — A&B First Aid Training',$html);
        $sent=$ok; $err=$msg;
    }
    header('Content-Type: application/json');
    echo json_encode(['items'=>count($lines),'emailed'=>$sent,'error'=>$err,'preview'=>$lines]);
    exit;

case 'comp_save':
    require __DIR__ . '/../lib/compliance.php'; comp_schema($pdo);
    if (!comp_can_edit()) { $_SESSION['flash']='You do not have permission to edit compliance documents.'; redirect('?r=compliance'); }
    $id=(int)($_POST['id']??0);
    $fields=['section'=>trim($_POST['section']??''),'subcategory'=>trim($_POST['subcategory']??''),
     'unit_code'=>trim($_POST['unit_code']??'') ?: null,'doc_name'=>trim($_POST['doc_name']??''),
     'version'=>trim($_POST['version']??'1.0') ?: '1.0','status'=>trim($_POST['status']??'Draft'),
     'approval_date'=>trim($_POST['approval_date']??'') ?: null,'review_date'=>trim($_POST['review_date']??'') ?: null,
     'approved_by'=>trim($_POST['approved_by']??'') ?: null,'owner'=>trim($_POST['owner']??'') ?: null,
     'notes'=>trim($_POST['notes']??'') ?: null];
    // optional file
    $filePath=null; $orig=null;
    if (!empty($_FILES['file']['tmp_name']) && ($_FILES['file']['error']??1)===UPLOAD_ERR_OK) {
        $dir=__DIR__.'/../data/org_files'; if(!is_dir($dir)) mkdir($dir,0775,true);
        $orig=$_FILES['file']['name']; $safe=preg_replace('/[^A-Za-z0-9._-]+/','_',$orig);
        $fn='comp_'.date('Ymd_His').'_'.substr($safe,0,80);
        if (move_uploaded_file($_FILES['file']['tmp_name'],$dir.'/'.$fn)) { $filePath='org_files/'.$fn; }
    }
    if ($fields['doc_name']==='' || $fields['section']==='') { $_SESSION['flash']='Document name and section are required.'; redirect('?r=compliance&tab=register'); }
    if ($id>0) {
        $cur=$pdo->prepare("SELECT * FROM compliance_docs WHERE id=?"); $cur->execute([$id]); $cur=$cur->fetch(PDO::FETCH_ASSOC);
        $sets=[]; $a=[];
        foreach ($fields as $k=>$v){ $sets[]="$k=?"; $a[]=$v; }
        $sets[]="updated_at=datetime('now')";
        if ($filePath){ $sets[]="file_path=?"; $a[]=$filePath; $sets[]="original_name=?"; $a[]=$orig; }
        $a[]=$id;
        $pdo->prepare("UPDATE compliance_docs SET ".implode(',',$sets)." WHERE id=?")->execute($a);
        if ($filePath){
            $pdo->prepare("INSERT INTO compliance_versions (doc_id,version,file_path,original_name,note,changed_by) VALUES (?,?,?,?,?,?)")
                ->execute([$id,$fields['version'],$filePath,$orig,'New file uploaded',(current_user()['name']??'')]);
            comp_audit($pdo,$id,'version',"Uploaded new file (v{$fields['version']})");
        }
        if ($cur && $cur['status']!==$fields['status']) comp_audit($pdo,$id,'status',"Status: {$cur['status']} -> {$fields['status']}");
        comp_audit($pdo,$id,'updated','Metadata updated');
        $_SESSION['flash']='Document updated.';
    } else {
        $cols=array_keys($fields); $ph=implode(',',array_fill(0,count($cols),'?'));
        $vals=array_values($fields);
        if ($filePath){ $cols[]='file_path'; $vals[]=$filePath; $cols[]='original_name'; $vals[]=$orig; }
        $pdo->prepare("INSERT INTO compliance_docs (".implode(',',$cols).") VALUES ($ph".($filePath?",?,?":"").")")->execute($vals);
        $nid=(int)$pdo->lastInsertId();
        if ($filePath) $pdo->prepare("INSERT INTO compliance_versions (doc_id,version,file_path,original_name,note,changed_by) VALUES (?,?,?,?,?,?)")
            ->execute([$nid,$fields['version'],$filePath,$orig,'Initial version',(current_user()['name']??'')]);
        comp_audit($pdo,$nid,'created','Document created');
        $_SESSION['flash']='Document added.';
    }
    redirect('?r=compliance&tab=register'.($fields['section']?'&section='.urlencode($fields['section']):''));
    break;

case 'comp_download':
    require __DIR__ . '/../lib/compliance.php'; comp_schema($pdo);
    $f=$pdo->prepare("SELECT * FROM compliance_docs WHERE id=?"); $f->execute([(int)($_GET['id']??0)]); $f=$f->fetch(PDO::FETCH_ASSOC);
    if ($f && $f['file_path']) { $path=__DIR__.'/../data/'.$f['file_path'];
        if (is_file($path)) { comp_audit($pdo,(int)$f['id'],'download',$f['original_name'] ?? '');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($f['original_name'] ?: 'document').'"');
            header('Content-Length: '.filesize($path)); readfile($path); exit; } }
    http_response_code(404); echo 'File not found'; exit;

case 'comp_archive':
    require __DIR__ . '/../lib/compliance.php'; comp_schema($pdo);
    if (!comp_can_edit()) { $_SESSION['flash']='Permission denied.'; redirect('?r=compliance'); }
    $id=(int)($_GET['id']??0); $to=($_GET['to']??'Archived');
    $to=in_array($to,['Archived','Active','Draft'],true)?$to:'Archived';
    $pdo->prepare("UPDATE compliance_docs SET status=?, updated_at=datetime('now') WHERE id=?")->execute([$to,$id]);
    comp_audit($pdo,$id,'status',"Status set to $to");
    $_SESSION['flash']="Document set to $to.";
    redirect('?r=compliance&tab=register');
    break;

case 'ci_save':
    require __DIR__ . '/../lib/compliance.php'; comp_schema($pdo);
    if (!comp_can_edit()) { $_SESSION['flash']='Permission denied.'; redirect('?r=compliance&tab=ci'); }
    $id=(int)($_POST['id']??0);
    $f=['ref'=>trim($_POST['ref']??''),'date_raised'=>trim($_POST['date_raised']??'') ?: null,'source'=>trim($_POST['source']??''),
        'description'=>trim($_POST['description']??''),'action_required'=>trim($_POST['action_required']??''),
        'responsible'=>trim($_POST['responsible']??''),'due_date'=>trim($_POST['due_date']??'') ?: null,
        'status'=>trim($_POST['status']??'Open'),'completed_date'=>trim($_POST['completed_date']??'') ?: null,
        'linked_type'=>trim($_POST['linked_type']??'') ?: null,'linked_ref'=>trim($_POST['linked_ref']??'') ?: null];
    if ($id>0){ $sets=[];$a=[]; foreach($f as $k=>$v){$sets[]="$k=?";$a[]=$v;} $a[]=$id;
        $pdo->prepare("UPDATE ci_register SET ".implode(',',$sets)." WHERE id=?")->execute($a); $_SESSION['flash']='Improvement item updated.'; }
    else { $cols=array_keys($f);$ph=implode(',',array_fill(0,count($cols),'?'));
        $pdo->prepare("INSERT INTO ci_register (".implode(',',$cols).") VALUES ($ph)")->execute(array_values($f)); $_SESSION['flash']='Improvement item added.'; }
    redirect('?r=compliance&tab=ci');
    break;

case 'student_send_access':
    require_once __DIR__ . '/../lib/student_portal.php'; sp_schema($pdo);
    $sid=(int)($_GET['id']??0);
    $s=$pdo->prepare("SELECT * FROM students WHERE id=?"); $s->execute([$sid]); $s=$s->fetch(PDO::FETCH_ASSOC);
    if ($s) { [$ok,$msg]=sp_send_portal($pdo,$s); $_SESSION['flash']=$ok?('Portal access emailed to '.$s['email']):('Could not send: '.$msg); if(!$ok) $_SESSION['flash_error']=1; }
    // Come back to the class screen when the button was pressed from there.
    $back=(int)($_GET['schedule_id']??0);
    redirect($back>0 ? ('?r=pipeline&schedule_id='.$back) : ('?r=student&id='.$sid));
    break;

case 'student_portal':
    require_once __DIR__ . '/../lib/student_portal.php'; sp_schema($pdo);
    $pending = sp_pending_count($pdo);
    $sentTotal = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE portal_emailed_at IS NOT NULL")->fetchColumn();
    $withEmail = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE email LIKE '%@%'")->fetchColumn();
    render('student_portal', compact('pending','sentTotal','withEmail'), 'Student Portal Access');
    break;

case 'student_portal_batch':
    require_once __DIR__ . '/../lib/student_portal.php'; sp_schema($pdo);
    $isCron = (($_GET['token'] ?? '') === 'anb-portal-batch');
    $isStaff = !empty($_SESSION['uid']);
    if (!$isCron && !$isStaff) { http_response_code(403); echo 'forbidden'; exit; }
    $limit = (int)($_GET['limit'] ?? $_POST['limit'] ?? 60);
    if ($limit < 1) $limit = 60; if ($limit > 200) $limit = 200;
    $res = sp_send_batch($pdo, $limit);
    if ($isCron && !$isStaff) { header('Content-Type: application/json'); echo json_encode($res); exit; }
    $_SESSION['flash'] = "Batch sent: {$res['sent']} emailed, {$res['failed']} failed, {$res['remaining']} remaining.";
    redirect('?r=student_portal');
    break;

case 'my_trainer':
case 'my_trainer_save':
case 'my_trainer_qual':
case 'my_trainer_declare':
case 'my_trainer_insurance':
    require_once __DIR__ . '/../lib/compliance.php'; comp_schema($pdo);
    $cu = current_user(); $em = $cu['email'];
    $tp = $pdo->prepare("SELECT * FROM trainer_profiles WHERE email=?"); $tp->execute([$em]); $prof = $tp->fetch(PDO::FETCH_ASSOC);
    if (!$prof) { $pdo->prepare("INSERT INTO trainer_profiles (name,email,position) VALUES (?,?,?)")->execute([$cu['name'],$em,'Trainer / Assessor']); $tp->execute([$em]); $prof = $tp->fetch(PDO::FETCH_ASSOC); }
    $pid = (int)$prof['id'];
    if ($r==='my_trainer_save') {
        $pdo->prepare("UPDATE trainer_profiles SET name=?,phone=?,position=?,notes=? WHERE id=?")
            ->execute([trim($_POST['name']??$prof['name']) ?: $prof['name'], trim($_POST['phone']??'') ?: null, trim($_POST['position']??'') ?: null, trim($_POST['notes']??'') ?: null, $pid]);
        $_SESSION['flash']='Your profile has been saved.'; redirect('?r=my_trainer');
    }
    if ($r==='my_trainer_qual') {
        $filePath=null;$orig=null;
        if (!empty($_FILES['file']['tmp_name']) && ($_FILES['file']['error']??1)===UPLOAD_ERR_OK) {
            $dir=__DIR__.'/../data/org_files'; if(!is_dir($dir)) mkdir($dir,0775,true);
            $orig=$_FILES['file']['name']; $safe=preg_replace('/[^A-Za-z0-9._-]+/','_',$orig);
            $fn='trncert_'.date('Ymd_His').'_'.substr($safe,0,80);
            if (move_uploaded_file($_FILES['file']['tmp_name'],$dir.'/'.$fn)) $filePath='org_files/'.$fn;
        }
        if (trim($_POST['title']??'')!=='') {
            $cols=['trainer_id','qual_type','title','code','issued_date','expiry_date','notes'];
            $vals=[$pid,trim($_POST['qual_type']??'Qualification'),trim($_POST['title']),trim($_POST['code']??'')?:null,trim($_POST['issued_date']??'')?:null,trim($_POST['expiry_date']??'')?:null,trim($_POST['notes']??'')?:null];
            if ($filePath){$cols[]='file_path';$vals[]=$filePath;$cols[]='original_name';$vals[]=$orig;}
            $ph=implode(',',array_fill(0,count($cols),'?'));
            $pdo->prepare("INSERT INTO trainer_quals (".implode(',',$cols).") VALUES ($ph)")->execute($vals);
            $_SESSION['flash']='Document added.';
        } else { $_SESSION['flash']='Please enter a title for the document.'; }
        redirect('?r=my_trainer');
    }
    if ($r==='my_trainer_declare') {
        $nm=trim($_POST['declaration_name']??'');
        if ($nm!=='') { $pdo->prepare("UPDATE trainer_profiles SET declaration_name=?, declaration_date=datetime('now') WHERE id=?")->execute([$nm,$pid]); $_SESSION['flash']='Declaration signed - thank you.'; }
        redirect('?r=my_trainer');
    }
    if ($r==='my_trainer_insurance') {
        $types=$_POST['insurance_type']??[]; $typeStr=is_array($types)?implode('; ',array_map('trim',$types)):trim((string)$types);
        $ins=['insurance_type'=>$typeStr,'insurance_provider'=>trim($_POST['insurance_provider']??'')?:null,
              'insurance_policy_no'=>trim($_POST['insurance_policy_no']??'')?:null,'insurance_expiry'=>trim($_POST['insurance_expiry']??'')?:null];
        $sets=[]; $vals=[]; foreach($ins as $k=>$v){$sets[]="$k=?";$vals[]=$v;}
        if (!empty($_FILES['file']['tmp_name']) && ($_FILES['file']['error']??1)===UPLOAD_ERR_OK) {
            $dir=__DIR__.'/../data/org_files'; if(!is_dir($dir)) mkdir($dir,0775,true);
            $orig=$_FILES['file']['name']; $safe=preg_replace('/[^A-Za-z0-9._-]+/','_',$orig);
            $fn='ins_'.date('Ymd_His').'_'.substr($safe,0,80);
            if (move_uploaded_file($_FILES['file']['tmp_name'],$dir.'/'.$fn)) { $sets[]="insurance_file=?";$vals[]='org_files/'.$fn; $sets[]="insurance_original_name=?";$vals[]=$orig; }
        }
        $vals[]=$pid; $pdo->prepare("UPDATE trainer_profiles SET ".implode(',',$sets)." WHERE id=?")->execute($vals);
        $_SESSION['flash']='Insurance details saved.'; redirect('?r=my_trainer');
    }
    $quals = $pdo->query("SELECT * FROM trainer_quals WHERE trainer_id=$pid ORDER BY qual_type, id")->fetchAll(PDO::FETCH_ASSOC);
    render('my_trainer', compact('prof','quals'), 'My Trainer Profile');
    break;

case 'enrol_new':
    $schedules = $pdo->query("SELECT sc.id, sc.start_date, sc.start_time, sc.plan_id, p.course_id, co.code, co.title,
        sc.location_id, l.name loc FROM schedules sc JOIN plans p ON p.id=sc.plan_id JOIN courses co ON co.id=p.course_id
        LEFT JOIN locations l ON l.id=sc.location_id ORDER BY sc.start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
    render('enrol_new', compact('schedules'), 'New enrolment');
    break;

case 'enrol_create':
    $email=trim($_POST['email']??''); $fn=trim($_POST['first_name']??''); $ln=trim($_POST['last_name']??'');
    $schedId=(int)($_POST['schedule_id']??0); $send=!empty($_POST['send_access']);
    try {
        if ($schedId<=0) throw new RuntimeException('Please choose a class.');
        if ($email==='' || strpos($email,'@')===false) throw new RuntimeException('Please enter a valid email.');
        $sc=$pdo->prepare("SELECT sc.*, p.course_id FROM schedules sc JOIN plans p ON p.id=sc.plan_id WHERE sc.id=?");
        $sc->execute([$schedId]); $sc=$sc->fetch(PDO::FETCH_ASSOC);
        if (!$sc) throw new RuntimeException('Class not found.');
        // find or create student
        $st=$pdo->prepare("SELECT * FROM students WHERE email=?"); $st->execute([$email]); $stu=$st->fetch(PDO::FETCH_ASSOC);
        if (!$stu) {
            if ($fn===''||$ln==='') throw new RuntimeException('New student needs a first and last name.');
            $pdo->prepare("INSERT INTO students (first_name,last_name,email) VALUES (?,?,?)")->execute([$fn,$ln,$email]);
            $sid=(int)$pdo->lastInsertId();
        } else { $sid=(int)$stu['id']; }
        // prevent duplicate enrolment in same class
        $dup=$pdo->prepare("SELECT COUNT(*) FROM enrolments WHERE student_id=? AND schedule_id=?"); $dup->execute([$sid,$schedId]);
        if ((int)$dup->fetchColumn()>0) throw new RuntimeException('That student is already enrolled in this class.');
        $pdo->prepare("INSERT INTO enrolments (student_id,course_id,plan_id,schedule_id,location_id,start_date,end_date,status,payment_status)
                       VALUES (?,?,?,?,?,?,?,'enrolled','unpaid')")
            ->execute([$sid,(int)$sc['course_id'],(int)$sc['plan_id'],$schedId,$sc['location_id']?:null,$sc['start_date'],$sc['end_date']]);
        // Mirror into RTO Data Cloud (off/dry/live switch on the RTO Sync screen).
        require_once __DIR__.'/../lib/rtodata.php'; anb_rto_push_safe($pdo,(int)$pdo->lastInsertId());
        if ($send) { require_once __DIR__.'/../lib/student_portal.php'; sp_schema($pdo);
            $stu2=$pdo->query("SELECT * FROM students WHERE id=$sid")->fetch(PDO::FETCH_ASSOC); sp_send_portal($pdo,$stu2); }
        $_SESSION['flash']='Student enrolled'.($send?' and sent portal access to complete their details.':'.');
        redirect('?r=student&id='.$sid);
    } catch (Throwable $e) { $_SESSION['flash']='Could not enrol: '.$e->getMessage(); redirect('?r=enrol_new'); }
    break;

case 'enrol_move':
    $eid=(int)($_GET['id']??0);
    $en=$pdo->prepare("SELECT e.*, s.first_name, s.last_name, co.code course_code, co.title course_title
        FROM enrolments e JOIN students s ON s.id=e.student_id JOIN courses co ON co.id=e.course_id WHERE e.id=?");
    $en->execute([$eid]); $en=$en->fetch(PDO::FETCH_ASSOC);
    if (!$en) { http_response_code(404); echo 'Not found'; break; }
    $schedules = $pdo->query("SELECT sc.id, sc.start_date, sc.start_time, sc.plan_id, p.course_id, co.code, co.title,
        sc.location_id, l.name loc FROM schedules sc JOIN plans p ON p.id=sc.plan_id JOIN courses co ON co.id=p.course_id
        LEFT JOIN locations l ON l.id=sc.location_id ORDER BY sc.start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
    render('enrol_move', compact('en','schedules'), 'Move / transfer enrolment');
    break;

case 'enrol_move_save':
    $eid=(int)($_POST['id']??0); $schedId=(int)($_POST['schedule_id']??0);
    try {
        $sc=$pdo->prepare("SELECT sc.*, p.course_id FROM schedules sc JOIN plans p ON p.id=sc.plan_id WHERE sc.id=?");
        $sc->execute([$schedId]); $sc=$sc->fetch(PDO::FETCH_ASSOC);
        if (!$sc) throw new RuntimeException('Please choose a class to move to.');
        $pdo->prepare("UPDATE enrolments SET course_id=?, plan_id=?, schedule_id=?, location_id=?, start_date=?, end_date=? WHERE id=?")
            ->execute([(int)$sc['course_id'],(int)$sc['plan_id'],$schedId,$sc['location_id']?:null,$sc['start_date'],$sc['end_date'],$eid]);
        $_SESSION['flash']='Enrolment moved to the selected class/course.';
    } catch (Throwable $e) { $_SESSION['flash']='Could not move: '.$e->getMessage(); }
    redirect('?r=enrol_move&id='.$eid);
    break;

case 'group_bookings':
    require_once __DIR__ . '/../lib/group_booking.php'; gb_schema($pdo);
    $status = $_GET['status'] ?? '';
    if ($status!=='') { $q=$pdo->prepare("SELECT * FROM group_bookings WHERE status=? ORDER BY created_at DESC"); $q->execute([$status]); $rows=$q->fetchAll(PDO::FETCH_ASSOC); }
    else { $rows=$pdo->query("SELECT * FROM group_bookings ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC); }
    $counts=[]; foreach ($pdo->query("SELECT status,COUNT(*) c FROM group_bookings GROUP BY status") as $rr) $counts[$rr['status']]=$rr['c'];
    render('group_bookings', compact('rows','counts','status'), 'Group Bookings');
    break;

case 'group_booking_view':
    require_once __DIR__ . '/../lib/group_booking.php'; gb_schema($pdo);
    $id=(int)($_GET['id']??0);
    $b=$pdo->prepare("SELECT * FROM group_bookings WHERE id=?"); $b->execute([$id]); $b=$b->fetch(PDO::FETCH_ASSOC);
    if (!$b) { http_response_code(404); echo 'Not found'; break; }
    render('group_booking_view', compact('b'), 'Group Booking');
    break;

case 'group_booking_save':
    require_once __DIR__ . '/../lib/group_booking.php'; gb_schema($pdo);
    $id=(int)($_POST['id']??0);
    $st=trim($_POST['status']??'New'); $st=in_array($st,['New','Quoted','Confirmed','Completed','Cancelled'],true)?$st:'New';
    $pdo->prepare("UPDATE group_bookings SET status=?, staff_notes=? WHERE id=?")->execute([$st, trim($_POST['staff_notes']??''), $id]);
    $_SESSION['flash']='Group booking updated.';
    redirect('?r=group_booking_view&id='.$id);
    break;

default:
    http_response_code(404); echo 'Page not found';
}
