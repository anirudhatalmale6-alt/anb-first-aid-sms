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

case 'avetmiss':
    render('avetmiss', [], 'AVETMISS Reporting');
    break;

default:
    http_response_code(404); echo 'Page not found';
}
