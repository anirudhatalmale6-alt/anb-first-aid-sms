<?php
declare(strict_types=1);

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    static $u = null;
    if ($u === null) {
        $st = db()->prepare("SELECT * FROM users WHERE id=?");
        $st->execute([$_SESSION['uid']]);
        $u = $st->fetch() ?: null;
    }
    return $u;
}

function require_login(): void {
    if (!current_user()) { header('Location: ?r=login'); exit; }
}

function redirect(string $to): void { header("Location: $to"); exit; }

/** Role-based access. Admin sees everything; other roles are limited to their area. */
function role_routes(string $role): array {
    $office = ['dashboard','students','student','student_save','enrolments','enrol_new','enrol_create','enrol_move','enrol_move_save',
      'schedules','schedule_save','schedule_duplicate','schedule_delete','pipeline','pipe_mark','class_send_access','generate','signoff','cert','usi_verify',
      'group_bookings','group_booking_view','group_booking_save','locations','location_save','location_delete',
      'courses','certificates','reminders','emails','email_save','email_delete','email_settings','email_settings_save',
      'email_test','cert_email','surveys','survey_view','avetmiss','avetmiss_export','avetmiss_preview',
      'student_send_access','student_portal','student_portal_batch','review_links','review_links_save',
      // RTO Data Cloud: office can view/retry a push, but only admin can change the off/dry/live mode
      'rto_sync','rto_sync_push','rto_sync_map',
      // USI Registry: office can run a check and see the log, but not change the credential
      // and office can work through the backlog, but not start/stop a run
      // name repair: office can look at what the registry found, but saving a
      // change to 164 student records is an administrator action
      'usi_registry','usi_registry_test','usi_check','usi_bulk','usi_bulk_step','usi_bulk_csv',
      'usi_repair','usi_repair_step','usi_fix','usi_fix_save'];
    $trainer = ['dashboard','trainer','students','student','schedules','pipeline','pipe_mark','class_send_access','obs_list','obs_mark','obs_save',
      'form_subs','form_view','content','quiz_edit','signoff','generate','cert','usi_verify','usi_check','usi_fix','usi_fix_save',
      'my_trainer','my_trainer_save','my_trainer_qual','my_trainer_declare','my_trainer_insurance',
      'trainer_cert_download','trainer_ins_download'];
    $compliance = ['dashboard','compliance','comp_save','comp_download','comp_archive','ci_save','comp_equip_save',
      'trainer_save','trainer_qual_save','trainer_cert_download','user_save','management','management_upload',
      'management_download','management_delete'];
    $auditor = ['dashboard','compliance','comp_download','trainer_cert_download','management_download'];
    switch ($role) {
        case 'office':              return $office;
        case 'trainer':             return $trainer;
        case 'compliance_manager':  return $compliance;
        case 'auditor':             return $auditor;
        default:                    return ['*']; // admin / unknown -> full
    }
}
function role_allowed(string $route): bool {
    $u = current_user(); if (!$u) return false;
    $allowed = role_routes($u['role'] ?? 'admin');
    return in_array('*', $allowed, true) || in_array($route, $allowed, true);
}

/** AVETMISS national outcome code -> label */
function outcome_label(string $code): string {
    return [
        '20' => 'Competency achieved',
        '30' => 'Competency not achieved',
        '40' => 'Withdrawn',
        '51' => 'RPL granted',
        '60' => 'Credit transfer',
        '70' => 'Continuing enrolment',
        '85' => 'Not started',
    ][$code] ?? $code;
}

function status_badge(string $s): string {
    $map = ['enrolled'=>'secondary','complete'=>'info','issued'=>'success','incomplete'=>'warning','withdrawn'=>'dark'];
    $c = $map[$s] ?? 'secondary';
    return '<span class="badge text-bg-'.$c.'">'.ucfirst($s).'</span>';
}

/** days until date (negative if past) */
function days_until(?string $date): ?int {
    if (!$date) return null;
    $d = new DateTime($date); $now = new DateTime('2026-08-01');
    return (int)$now->diff($d)->format('%r%a');
}

function render(string $view, array $data = [], string $title = ''): void {
    extract($data);
    ob_start();
    require __DIR__ . '/../views/' . $view . '.php';
    $content = ob_get_clean();
    require __DIR__ . '/../views/layout.php';
}
