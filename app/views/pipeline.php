<?php
/** Class readiness pipeline - one screen, whole class, traffic-light per requirement. */
function dot($ok, $warn=false) {
    if ($ok)   return '<span class="pdot bg-success" title="Done"></span>';
    if ($warn) return '<span class="pdot bg-warning" title="Pending"></span>';
    return '<span class="pdot bg-danger" title="Not done"></span>';
}
$cols = ['Online','AVETMISS','USI','Paid','ID','Attend.','Tasks'];
// count fully-ready students
$ready = 0;
foreach ($rows as $r) {
    $ok = $r['online_complete'] && $r['avetmiss_complete'] && $r['usi_number'] && $r['payment_status']==='paid'
        && $r['id_confirmed'] && $r['attendance_marked'] && $r['tasks_satisfactory'];
    if ($ok) $ready++;
}
// how many students still have no login email (only chased up for classes still to run)
$classUpcoming = !empty($schedule['start_date']) && $schedule['start_date'] >= date('Y-m-d');
$noLogin = 0; $loginFailed = 0;
foreach ($rows as $r) {
    if (empty($r['portal_emailed_at'])) { $noLogin++; if (!empty($r['portal_error'])) $loginFailed++; }
}
if (!$classUpcoming) $noLogin = 0;
// Timestamps are stored by SQLite datetime('now'), which is UTC - show them in local time.
$stamp = function ($t) { return $t ? date('j M, g:ia', strtotime($t . ' UTC')) : ''; };
?>
<style>
  .pdot{ display:inline-block;width:15px;height:15px;border-radius:50%; }
  .pipe-th{ font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;color:#8a8a8a;text-align:center; }
  .pipe-td{ text-align:center; }
</style>

<div class="topbar">
  <div>
    <a href="?r=schedules" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Schedules</a>
    <h4 class="mb-0 fw-bold" style="color:#2F1D3A;"><?= e($schedule['course_code']) ?> — Class Pipeline</h4>
    <div class="text-muted small"><?= e($schedule['plan_title']) ?><br>
      <i class="bi bi-calendar3"></i> <?= e($schedule['start_date']) ?>
      <?= e(substr((string)$schedule['start_time'],0,5)) ?>–<?= e(substr((string)$schedule['end_time'],0,5)) ?>
      &middot; <i class="bi bi-geo-alt"></i> <?= e($schedule['location']) ?></div>
  </div>
  <div class="text-end">
    <span class="badge text-bg-<?= $ready===count($rows)&&$rows?'success':'secondary' ?> fs-6"><?= $ready ?>/<?= count($rows) ?> ready to certify</span>
  </div>
</div>

<?php if (!empty($_SESSION['flash'])):
        $isErr = !empty($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
  <div class="alert alert-<?= $isErr ? 'danger' : 'success' ?>">
    <i class="bi bi-<?= $isErr ? 'exclamation-triangle-fill' : 'check-circle-fill' ?>"></i> <?= e($_SESSION['flash']) ?></div>
  <?php unset($_SESSION['flash']); endif; ?>

<?php if ($noLogin > 0): ?>
  <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <i class="bi bi-envelope-exclamation-fill"></i>
      <strong><?= (int)$noLogin ?> student<?= $noLogin===1?' has':'s have' ?> not received login details</strong>
      for the online modules<?php if ($loginFailed): ?> — <?= (int)$loginFailed ?> of them because the email failed to send<?php endif; ?>.
      They cannot start their pre-course learning until this is sent.
    </div>
    <form method="post" action="?r=class_send_access" class="m-0"
          onsubmit="return confirm('Send login details to the <?= (int)$noLogin ?> student(s) who have not received them?');">
      <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
      <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-send"></i> Send now</button>
    </form>
  </div>
<?php endif; ?>

<div class="alert alert-light border small">
  <i class="bi bi-list-check text-danger"></i>
  Every student in this class at a glance. Green = done, amber = pending, red = outstanding.
  Use the bulk buttons, then <strong>Sign Off</strong> the whole class in one go — no more checking students one by one.
</div>

<div class="card p-0">
  <div class="table-responsive">
  <table class="table align-middle mb-0">
    <thead>
      <tr>
        <th style="padding-left:18px;">Student</th>
        <th class="pipe-th">Status</th>
        <th class="pipe-th">Login sent</th>
        <?php foreach ($cols as $c): ?><th class="pipe-th"><?= e($c) ?></th><?php endforeach; ?>
        <th class="pipe-th">Certificate</th>
      </tr>
      <tr class="table-light">
        <td colspan="3"></td>
        <td class="pipe-td" colspan="4"></td>
        <td class="pipe-td"><button class="btn btn-sm btn-outline-secondary py-0">All Sighted</button></td>
        <td class="pipe-td"><button class="btn btn-sm btn-outline-secondary py-0">All Here</button></td>
        <td class="pipe-td"><button class="btn btn-sm btn-outline-secondary py-0">All Satisfactory</button></td>
        <td></td>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
      $paid = $r['payment_status']==='paid';
      $allGreen = $r['online_complete'] && $r['avetmiss_complete'] && $r['usi_number'] && $paid
                  && $r['id_confirmed'] && $r['attendance_marked'] && $r['tasks_satisfactory'];
    ?>
      <tr>
        <td style="padding-left:18px;">
          <a href="?r=student&id=<?= (int)$r['student_id'] ?>" class="fw-semibold text-decoration-none" style="color:#2F1D3A;" title="Open student details"><?= e($r['first_name'].' '.$r['last_name']) ?></a>
          <div class="text-muted small"><?= e($r['email']) ?></div>
        </td>
        <td class="pipe-td">
          <?php if ($allGreen): ?><span class="badge text-bg-success">Ready</span>
          <?php else: ?><span class="badge text-bg-warning">Pending</span><?php endif; ?>
        </td>
        <td class="pipe-td">
          <?php if (!empty($r['portal_emailed_at'])): ?>
            <span class="pdot bg-success" title="Login details emailed <?= e($stamp($r['portal_emailed_at'])) ?>"></span>
            <div class="text-muted" style="font-size:.68rem;"><?= e($stamp($r['portal_emailed_at'])) ?></div>
          <?php else: ?>
            <span class="pdot bg-danger" title="<?= e($r['portal_error'] ? 'Last attempt failed: '.$r['portal_error'] : 'No login details sent yet') ?>"></span>
            <div style="font-size:.68rem;">
              <a href="?r=student_send_access&id=<?= (int)$r['student_id'] ?>&schedule_id=<?= (int)$schedule['id'] ?>"
                 class="text-danger fw-semibold text-decoration-none">Send</a>
            </div>
            <?php if (!empty($r['portal_error'])): ?>
              <div class="text-danger" style="font-size:.62rem;">failed</div>
            <?php endif; ?>
          <?php endif; ?>
        </td>
        <td class="pipe-td"><?= dot($r['online_complete']) ?></td>
        <td class="pipe-td">
          <?php
            $am    = $r['avetmiss_missing'] ?? [];
            $amTot = $r['avetmiss_total'] ?? count($am);
            // Amber when they have made a start and something is outstanding;
            // red is reserved for a record with nothing on it at all.
            $amPart = $am && count($am) < $amTot;
          ?>
          <?php if (!$am): ?>
            <span class="pdot bg-success" title="All government reporting details are on file"></span>
          <?php else: ?>
            <a href="?r=student&id=<?= (int)$r['student_id'] ?>" class="text-decoration-none"
               title="Still needed: <?= e(implode(', ', $am)) ?>">
              <span class="pdot <?= $amPart ? 'bg-warning' : 'bg-danger' ?>"></span>
              <div class="<?= $amPart ? 'text-warning-emphasis' : 'text-danger' ?>" style="font-size:.62rem;"><?= count($am) ?> missing</div>
            </a>
          <?php endif; ?>
        </td>
        <td class="pipe-td"><?= dot((bool)$r['usi_number']) ?></td>
        <td class="pipe-td"><?= dot($paid, $r['payment_status']==='part') ?></td>
        <td class="pipe-td"><?= dot($r['id_confirmed']) ?></td>
        <td class="pipe-td"><?= dot($r['attendance_marked']) ?></td>
        <td class="pipe-td"><?= dot($r['tasks_satisfactory'], !$r['tasks_satisfactory'] && $r['attendance_marked']) ?></td>
        <td class="pipe-td">
          <?php if ($r['status']==='issued'): ?>
            <span class="badge text-bg-success">Issued</span>
          <?php elseif ($allGreen): ?>
            <a href="?r=generate&enrolment_id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-anb py-0"><i class="bi bi-award"></i> Generate</a>
          <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; if(!$rows): ?><tr><td colspan="11" class="text-muted p-3">No students enrolled in this class.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  <div class="d-flex justify-content-between align-items-center p-3 border-top">
    <div class="small text-muted">Only students with every box green can be certified.</div>
    <div>
      <button class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat"></i> Refresh</button>
      <form method="post" action="?r=class_send_access" class="d-inline" onsubmit="return confirm('Send portal login access to all students in this class who have not received it yet?');">
        <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-envelope-check"></i> Send access to class</button>
      </form>
      <form method="post" action="?r=class_send_access" class="d-inline"
            onsubmit="return confirm('Resend login details to EVERY student in this class? Each one gets a brand new password and any earlier email stops working. Use this when students say the first email never arrived.');">
        <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
        <input type="hidden" name="mode" value="all">
        <button type="submit" class="btn btn-outline-secondary" title="New password for everyone in this class"><i class="bi bi-arrow-repeat"></i> Resend to everyone</button>
      </form>
      <a href="?r=signoff&schedule_id=<?= (int)$schedule['id'] ?>" class="btn btn-success"><i class="bi bi-check2-all"></i> Sign Off &amp; Generate Certificates (<?= $ready ?>)</a>
    </div>
  </div>
</div>
