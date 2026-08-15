<?php
/**
 * Class-day marking: attendance and classroom tasks.
 *
 * Both started life as a yes/no tick, which cannot say the two things that
 * actually happen in a room: a student who did not turn up, and a student who
 * was assessed and is not yet satisfactory. A blank tick read the same as
 * "absent", so nobody could tell "not marked yet" from "did not attend".
 *
 * The old boolean columns are kept in step with these, because the certificate
 * gate and the AVETMISS export are built on them - attendance_marked is 1 only
 * for present, tasks_satisfactory is 1 only for satisfactory.
 */
declare(strict_types=1);

const PIPE_ATTENDANCE = ['' => 'Please select', 'present' => 'Present', 'absent' => 'Absent'];
const PIPE_TASKS      = ['' => 'Not Assessed', 'satisfactory' => 'Satisfactory', 'not_yet' => 'Not Yet Satisfactory'];

/** Add the two status columns. Safe to call on every request. */
function pipe_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $cols = $pdo->query("PRAGMA table_info(enrolments)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('online_marked_by', $cols, true)) {
        // Who ticked the online modules off by hand, and when. Its presence is
        // also the override flag - see lms_recompute_online_complete().
        $pdo->exec("ALTER TABLE enrolments ADD COLUMN online_marked_by TEXT");
        $pdo->exec("ALTER TABLE enrolments ADD COLUMN online_marked_at TEXT");
    }
    if (!in_array('attendance_status', $cols, true)) {
        $pdo->exec("ALTER TABLE enrolments ADD COLUMN attendance_status TEXT DEFAULT ''");
        // Anyone already ticked present keeps that meaning rather than reverting
        // to "not marked" the moment this ships.
        $pdo->exec("UPDATE enrolments SET attendance_status='present' WHERE attendance_marked=1");
    }
    if (!in_array('tasks_status', $cols, true)) {
        $pdo->exec("ALTER TABLE enrolments ADD COLUMN tasks_status TEXT DEFAULT ''");
        $pdo->exec("UPDATE enrolments SET tasks_status='satisfactory' WHERE tasks_satisfactory=1");
    }
}

/**
 * Save attendance for one enrolment, keeping the boolean the certificate gate
 * reads in step with it.
 */
function pipe_set_attendance(PDO $pdo, int $enrolmentId, string $status): void {
    if (!array_key_exists($status, PIPE_ATTENDANCE)) return;
    $pdo->prepare("UPDATE enrolments SET attendance_status=?, attendance_marked=? WHERE id=? AND status!='issued'")
        ->execute([$status, $status === 'present' ? 1 : 0, $enrolmentId]);
}

/** Same for the classroom tasks. Only 'satisfactory' opens the certificate gate. */
function pipe_set_tasks(PDO $pdo, int $enrolmentId, string $status): void {
    if (!array_key_exists($status, PIPE_TASKS)) return;
    $pdo->prepare("UPDATE enrolments SET tasks_status=?, tasks_satisfactory=? WHERE id=? AND status!='issued'")
        ->execute([$status, $status === 'satisfactory' ? 1 : 0, $enrolmentId]);
}

/** Whole class at once - the common case at the end of a session. */
function pipe_set_all(PDO $pdo, int $scheduleId, string $field, string $status): int {
    if ($field === 'attendance') {
        if (!array_key_exists($status, PIPE_ATTENDANCE)) return 0;
        $q = $pdo->prepare("UPDATE enrolments SET attendance_status=?, attendance_marked=?
                            WHERE schedule_id=? AND status!='issued'");
        $q->execute([$status, $status === 'present' ? 1 : 0, $scheduleId]);
        return $q->rowCount();
    }
    if ($field === 'tasks') {
        if (!array_key_exists($status, PIPE_TASKS)) return 0;
        $q = $pdo->prepare("UPDATE enrolments SET tasks_status=?, tasks_satisfactory=?
                            WHERE schedule_id=? AND status!='issued'");
        $q->execute([$status, $status === 'satisfactory' ? 1 : 0, $scheduleId]);
        return $q->rowCount();
    }
    return 0;
}

/**
 * Mark the online modules complete (or not) by hand.
 *
 * The flag is normally worked out by the LMS from module progress, and there
 * was no way to set it in the office at all - so a student who did the theory
 * in the room, or whose progress never saved, could never be certified no
 * matter what else was ticked. The name and time are stored so it is a record
 * of a decision somebody made, not an anonymous flag; that record is also what
 * stops the LMS quietly recomputing it back to 0 on the student's next login.
 */
function pipe_set_online(PDO $pdo, int $enrolmentId, bool $on, string $who): void {
    pipe_schema($pdo);
    $pdo->prepare("UPDATE enrolments SET online_complete=?, online_marked_by=?, online_marked_at=?
                   WHERE id=? AND status!='issued'")
        ->execute([$on ? 1 : 0, $on ? $who : null, $on ? date('Y-m-d H:i:s') : null, $enrolmentId]);
}

/** The same for a whole class. Returns how many rows changed. */
function pipe_set_online_all(PDO $pdo, int $scheduleId, bool $on, string $who): int {
    pipe_schema($pdo);
    $q = $pdo->prepare("UPDATE enrolments SET online_complete=?, online_marked_by=?, online_marked_at=?
                        WHERE schedule_id=? AND status!='issued'");
    $q->execute([$on ? 1 : 0, $on ? $who : null, $on ? date('Y-m-d H:i:s') : null, $scheduleId]);
    return $q->rowCount();
}

/**
 * Bookings that are not attached to any class yet, for the same course as this
 * one - the people you would be looking for when a class has just finished.
 *
 * Nearly every booking arrives this way: the website sends the course and the
 * payment but no class, so 400-odd paid students sit in a list nobody can sign
 * off. This is what the "who was in the room" picker is built on.
 *
 * @return array<int,array<string,mixed>>
 */
function pipe_unattached(PDO $pdo, int $scheduleId, string $search = '', int $limit = 300): array {
    $sc = $pdo->prepare("SELECT p.course_id FROM schedules sc JOIN plans p ON p.id=sc.plan_id WHERE sc.id=?");
    $sc->execute([$scheduleId]);
    $courseId = (int)$sc->fetchColumn();
    if (!$courseId) return [];

    $sql = "SELECT e.id, e.created_at, e.payment_status, e.online_complete,
                   s.id student_id, s.first_name, s.last_name, s.email, s.mobile_phone,
                   s.usi_number, s.usi_verified
            FROM enrolments e JOIN students s ON s.id=e.student_id
            WHERE (e.schedule_id IS NULL OR e.schedule_id='')
              AND e.course_id=? AND e.status<>'issued'";
    $args = [$courseId];
    $search = trim($search);
    if ($search !== '') {
        $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ?
                       OR (s.first_name || ' ' || s.last_name) LIKE ?)";
        $like = '%' . $search . '%';
        array_push($args, $like, $like, $like, $like);
    }
    // Newest booking first - after a class it is nearly always someone who
    // booked in the last week or two.
    $sql .= " ORDER BY e.id DESC LIMIT " . (int)$limit;
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

/**
 * Attach bookings to a class, copying the class's date and location onto them.
 *
 * Only enrolments for the same course are moved - picking the wrong list on a
 * busy day should not be able to put a CPR student into a Child Care class.
 *
 * @param array<int,int|string> $enrolmentIds
 * @return array{0:int,1:int} [moved, refused because the course did not match]
 */
function pipe_add_to_class(PDO $pdo, int $scheduleId, array $enrolmentIds): array {
    $sc = $pdo->prepare("SELECT sc.id, sc.location_id, sc.start_date, sc.end_date, p.course_id, p.id plan_id
                         FROM schedules sc JOIN plans p ON p.id=sc.plan_id WHERE sc.id=?");
    $sc->execute([$scheduleId]);
    $class = $sc->fetch();
    if (!$class) return [0, 0];

    $up = $pdo->prepare("UPDATE enrolments
                         SET schedule_id=?, plan_id=?, location_id=?, start_date=?, end_date=?
                         WHERE id=? AND course_id=? AND status<>'issued'");
    $moved = 0; $refused = 0;
    foreach ($enrolmentIds as $eid) {
        $eid = (int)$eid;
        if ($eid <= 0) continue;
        $up->execute([$scheduleId, $class['plan_id'], $class['location_id'],
                      $class['start_date'], $class['end_date'] ?: $class['start_date'],
                      $eid, $class['course_id']]);
        if ($up->rowCount() > 0) $moved++; else $refused++;
    }
    return [$moved, $refused];
}

/**
 * Why each student in a class cannot be certified yet, in the office's words.
 *
 * Sign-off used to swallow every failure and report a bare count, so "0
 * certificate(s) generated" was the only thing you ever saw and there was
 * nothing to act on.
 *
 * @return array<int,array{name:string,enrolment_id:int,student_id:int,reasons:array<int,string>}>
 */
function pipe_blockers(PDO $pdo, int $scheduleId): array {
    require_once __DIR__ . '/avetmiss.php';
    $st = $pdo->prepare("SELECT e.id, e.student_id, e.status, e.online_complete, e.id_confirmed,
            e.attendance_marked, e.tasks_satisfactory, e.attendance_status, e.tasks_status,
            e.payment_status, s.first_name, s.last_name, s.usi_number, s.usi_verified,
            " . avetmiss_select_columns('s') . "
        FROM enrolments e JOIN students s ON s.id=e.student_id
        WHERE e.schedule_id=? ORDER BY s.last_name, s.first_name");
    $st->execute([$scheduleId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        if ($r['status'] === 'issued') continue;
        $why = [];
        if (($r['attendance_status'] ?? '') === 'absent')  $why[] = 'marked absent';
        elseif (!$r['attendance_marked'])                  $why[] = 'attendance not marked';
        if (($r['tasks_status'] ?? '') === 'not_yet')      $why[] = 'not yet satisfactory';
        elseif (!$r['tasks_satisfactory'])                 $why[] = 'tasks not assessed';
        if (!$r['online_complete'])                        $why[] = 'online modules not complete';
        if (!$r['id_confirmed'])                           $why[] = 'ID not sighted';
        if ($r['payment_status'] !== 'paid')               $why[] = 'not marked paid';
        if (trim((string)$r['usi_number']) === '')         $why[] = 'no USI';
        elseif (empty($r['usi_verified']))                 $why[] = 'USI not verified';
        $miss = avetmiss_missing($r);
        if ($miss) $why[] = 'missing ' . implode(', ', array_map('strtolower', $miss));
        if (!$why) continue;
        $out[] = [
            'name'         => trim($r['first_name'] . ' ' . $r['last_name']),
            'enrolment_id' => (int)$r['id'],
            'student_id'   => (int)$r['student_id'],
            'reasons'      => $why,
        ];
    }
    return $out;
}
