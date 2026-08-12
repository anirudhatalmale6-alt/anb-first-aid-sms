<?php
/**
 * Offline dry-run harness for the RTO Data Cloud connector.
 *
 *   php tools/rto_dryrun.php                 -> map classes from a saved list + dry-run the newest SMS enrolment
 *   php tools/rto_dryrun.php <enrolment_id>  -> dry-run one specific enrolment
 *
 * NEVER sends anything: it forces test mode for the duration of the run.
 */
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/rtodata.php';

$pdo = db();
anb_rto_schema($pdo);

$prevMode = anb_rto_mode($pdo);
anb_rto_set_mode($pdo, 'dry');           // belt and braces - nothing can go out

echo "Mode forced to: dry (was: $prevMode)\n\n";

// --- mapping report ---------------------------------------------------------
echo "COURSE / PLAN MAPPING (ours -> RTO Data Cloud)\n";
foreach ($pdo->query("SELECT c.code, c.rto_course_id, p.id, p.title, p.rto_plan_id
                      FROM plans p JOIN courses c ON c.id=p.course_id ORDER BY c.code, p.id") as $r) {
    printf("  %-10s course=%-3s plan#%-3d %-62s -> plan=%s\n",
        $r['code'], $r['rto_course_id'] ?: '-', (int)$r['id'], substr($r['title'], 0, 62), $r['rto_plan_id'] ?: '(none - never sent)');
}

// --- class matching against a saved copy of their list ----------------------
$listFile = __DIR__ . '/../../../rto_list.json';
if (is_file($listFile)) {
    $list = json_decode((string)file_get_contents($listFile), true);
    if (is_array($list)) {
        $res = anb_rto_map_schedules($pdo, $list);
        echo "\nCLASS MATCHING (from saved list): matched {$res['matched']} of {$res['checked']} unmatched classes\n";
    }
} else {
    echo "\nCLASS MATCHING: no saved list at $listFile - run the 'Re-check class matching' button on the server instead.\n";
}

// --- dry-run one enrolment --------------------------------------------------
$eid = (int)($argv[1] ?? 0);
if ($eid <= 0) {
    $eid = (int)($pdo->query("SELECT e.id FROM enrolments e JOIN plans p ON p.id=e.plan_id
        WHERE p.rto_plan_id IS NOT NULL AND p.rto_plan_id<>'' ORDER BY e.id DESC LIMIT 1")->fetchColumn() ?: 0);
}
if ($eid <= 0) { echo "\nNo suitable enrolment found to dry-run.\n"; anb_rto_set_mode($pdo, $prevMode); exit(0); }

echo "\nDRY-RUN enrolment #$eid\n";
$built = anb_rto_build_payload($pdo, $eid);
foreach ($built['warnings'] as $w) echo "  ! $w\n";
echo "  Fields that will be sent (blank ones omitted here for readability):\n";
foreach ($built['values'] as $k => $v) if ($v !== '') printf("    %-20s %s\n", $k, $v);

$res = anb_rto_push($pdo, $eid, true);
echo "\n  push() -> " . json_encode(['ok' => $res['ok'], 'result' => $res['result'], 'message' => $res['message']]) . "\n";

// --- idempotency check ------------------------------------------------------
$pdo->prepare("UPDATE enrolments SET rto_enrolment_id='TEST123' WHERE id=?")->execute([$eid]);
$again = anb_rto_push($pdo, $eid);
echo "  re-push with an existing RTO id -> {$again['result']}: {$again['message']}\n";
$pdo->prepare("UPDATE enrolments SET rto_enrolment_id=NULL WHERE id=?")->execute([$eid]);

anb_rto_set_mode($pdo, $prevMode);
echo "\nMode restored to: " . anb_rto_mode($pdo) . "\nNothing was sent to RTO Data Cloud.\n";
