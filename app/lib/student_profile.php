<?php
/**
 * Data behind the tabbed student profile.
 *
 * Each tab is loaded on its own rather than building one enormous page, so
 * opening a student stays quick even for someone with years of history.
 */
declare(strict_types=1);

/** Staff notes against a student. */
function sp_notes_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        note TEXT NOT NULL,
        created_by TEXT,
        created_at TEXT
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_student_notes ON student_notes(student_id, id DESC)");
}

/** @return array<int,array<string,mixed>> */
function sp_notes(PDO $pdo, int $studentId): array {
    sp_notes_schema($pdo);
    $q = $pdo->prepare("SELECT * FROM student_notes WHERE student_id=? ORDER BY id DESC");
    $q->execute([$studentId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

function sp_note_add(PDO $pdo, int $studentId, string $note, string $by): void {
    sp_notes_schema($pdo);
    $note = trim($note);
    if ($note === '') return;
    $pdo->prepare("INSERT INTO student_notes (student_id, note, created_by, created_at) VALUES (?,?,?,?)")
        ->execute([$studentId, $note, $by, date('Y-m-d H:i:s')]);
}

/**
 * Online module progress, per enrolment.
 *
 * @return array<int,array<string,mixed>>
 */
function sp_learning(PDO $pdo, int $studentId): array {
    $q = $pdo->prepare("SELECT lp.*, cm.title module_title, cm.type module_type, cm.pass_mark,
               co.code course_code, e.id enrolment_id
        FROM enrolments e
        JOIN course_modules cm ON cm.course_id = e.course_id
        JOIN courses co ON co.id = e.course_id
        LEFT JOIN learner_progress lp ON lp.enrolment_id = e.id AND lp.module_id = cm.id
        WHERE e.student_id = ? AND COALESCE(cm.active,1) = 1
        ORDER BY e.id DESC, cm.position ASC");
    $q->execute([$studentId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

/** Unit outcomes across every enrolment - the AVETMISS side of the record. */
function sp_units(PDO $pdo, int $studentId): array {
    $q = $pdo->prepare("SELECT eu.*, u.code unit_code, u.title unit_title, co.code course_code,
               e.start_date, e.status enrol_status
        FROM enrolment_units eu
        JOIN enrolments e ON e.id = eu.enrolment_id
        JOIN units u ON u.id = eu.unit_id
        JOIN courses co ON co.id = e.course_id
        WHERE e.student_id = ?
        ORDER BY e.start_date DESC, u.code ASC");
    $q->execute([$studentId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Everything that has happened to this student, newest first.
 *
 * Pulled from the records that already exist rather than a new audit table -
 * portal emails, USI checks, survey responses and form submissions.
 */
function sp_activity(PDO $pdo, int $studentId, array $s): array {
    $out = [];

    if (!empty($s['portal_emailed_at'])) {
        $out[] = ['when' => (string)$s['portal_emailed_at'], 'what' => 'Portal login details emailed',
                  'detail' => (string)$s['email'], 'icon' => 'envelope-check'];
    }
    if (!empty($s['portal_error'])) {
        $out[] = ['when' => (string)($s['portal_attempted_at'] ?? ''), 'what' => 'Portal email FAILED',
                  'detail' => (string)$s['portal_error'], 'icon' => 'exclamation-triangle'];
    }

    $q = $pdo->prepare("SELECT * FROM usi_verify_log WHERE student_id=? ORDER BY id DESC LIMIT 30");
    $q->execute([$studentId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $what = ((int)$l['verified'] === 1) ? 'USI verified' : 'USI check failed';
        if ((string)($l['env'] ?? '') === 'by hand') $what = (string)$l['status'];
        $out[] = ['when' => (string)$l['checked_at'], 'what' => $what,
                  'detail' => trim((string)($l['error'] ?: ($l['note'] ?: (string)$l['usi']))),
                  'icon' => ((int)$l['verified'] === 1) ? 'patch-check' : 'patch-exclamation'];
    }

    try {
        $q = $pdo->prepare("SELECT type, sent_at, completed_at FROM surveys
                            WHERE student_id=? ORDER BY id DESC");
        $q->execute([$studentId]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $sv) {
            if (!empty($sv['completed_at'])) {
                $out[] = ['when' => (string)$sv['completed_at'], 'what' => 'Survey completed',
                          'detail' => (string)($sv['type'] ?? ''), 'icon' => 'clipboard-check'];
            } elseif (!empty($sv['sent_at'])) {
                $out[] = ['when' => (string)$sv['sent_at'], 'what' => 'Survey sent',
                          'detail' => (string)($sv['type'] ?? ''), 'icon' => 'clipboard'];
            }
        }
    } catch (Throwable $e) { /* surveys are optional */ }

    usort($out, fn($a, $b) => strcmp((string)$b['when'], (string)$a['when']));
    return $out;
}

/** Money, read off the enrolments. */
function sp_financial(PDO $pdo, int $studentId): array {
    $q = $pdo->prepare("SELECT e.id, e.amount_due, e.amount_paid, e.payment_status, e.start_date,
               co.code course_code, p.title plan_title
        FROM enrolments e JOIN courses co ON co.id=e.course_id JOIN plans p ON p.id=e.plan_id
        WHERE e.student_id=? ORDER BY e.start_date DESC");
    $q->execute([$studentId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
