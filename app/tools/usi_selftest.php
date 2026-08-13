<?php
/**
 * End-to-end self-test for real-time USI verification.
 *
 *   php tools/usi_selftest.php
 *
 * Runs the government's 3PT test records through the SAME code path a real
 * student takes - temporary student rows are created, verified against the
 * sandbox, then deleted again. Nothing real is touched and nothing real is
 * sent to 3PT.
 *
 * Use this after moving the SMS to a new server, or after the machine
 * credential is replaced, to prove the connection still works.
 */
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/usi.php';

$pdo = db();
anb_usi_schema($pdo);

$previousMode = anb_settings($pdo)['usi_mode'] ?? 'off';
anb_setting_save($pdo, 'usi_mode', 'test');

$cfg = anb_usi_config($pdo);
if (!$cfg['configured']) {
    fwrite(STDERR, "Not usable: {$cfg['problem']}\n");
    exit(1);
}
echo "Environment : {$cfg['env']}\n";
echo "Org code    : {$cfg['org_code']}\n";
echo "Credential  : {$cfg['credential_id']}\n\n";

/* first, family, dob, usi, what should happen */
$cases = [
    ['Maryam', 'Fredrick',  '1966-05-25', 'BNGH7C75FN', true,  'active record, everything matches'],
    ['Csenge', 'Gumarsson', '1988-12-26', 'BP6LKB3C7X', true,  'active record, everything matches'],
    ['Asfaha', 'Loflin',    '1982-12-23', 'DG6K5YHPP3', false, 'deactivated USI'],
    ['Maryam', 'Frederick', '1966-05-25', 'BNGH7C75FN', false, 'family name spelt wrong'],
    ['Maryam', 'Fredrick',  '1966-05-26', 'BNGH7C75FN', false, 'date of birth wrong'],
    ['Maryam', 'Fredrick',  '1966-05-25', 'ZZZZ9999ZZ', false, 'USI does not exist'],
    ['Maryam', 'Fredrick',  '1966-05-25', 'BNGH7C75F',  false, 'too short - caught before the call'],
    ['Maryam', 'Fredrick',  '1966-05-25', 'BNGH7C75FO', false, 'contains an O - caught before the call'],
];

$passed = 0;
$created = [];
foreach ($cases as $i => [$first, $last, $dob, $usi, $shouldVerify, $note]) {
    $pdo->prepare("INSERT INTO students (first_name,last_name,date_of_birth,usi_number,email)
                   VALUES (?,?,?,?,?)")
        ->execute([$first, $last, $dob, $usi, 'usi-selftest-' . $i . '@example.invalid']);
    $sid = (int)$pdo->lastInsertId();
    $created[] = $sid;

    $r = anb_usi_verify_student($pdo, $sid, 'self-test', true);

    $stored = (int)$pdo->query("SELECT COALESCE(usi_verified,0) FROM students WHERE id={$sid}")->fetchColumn();
    $ok = ($r['verified'] === $shouldVerify) && ($stored === ($shouldVerify ? 1 : 0));
    if ($ok) $passed++;

    printf("%-4s %-11s %-20s %-9s  %s\n",
        $ok ? 'PASS' : 'FAIL',
        $usi,
        trim("$first $last"),
        $r['verified'] ? 'verified' : 'rejected',
        $note);
    if (!$ok || !$r['verified']) {
        echo "       -> {$r['message']}\n";
    }
}

/* clean up - these students never existed */
foreach ($created as $sid) {
    $pdo->prepare("DELETE FROM usi_verify_log WHERE student_id=?")->execute([$sid]);
    $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$sid]);
}
anb_setting_save($pdo, 'usi_mode', $previousMode);

printf("\n%d/%d passed. Mode restored to '%s'.\n", $passed, count($cases), $previousMode);
exit($passed === count($cases) ? 0 : 1);
