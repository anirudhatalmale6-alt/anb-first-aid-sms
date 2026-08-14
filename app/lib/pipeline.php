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
