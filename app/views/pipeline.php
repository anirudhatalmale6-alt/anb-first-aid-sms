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
        <?php foreach ($cols as $c): ?><th class="pipe-th"><?= e($c) ?></th><?php endforeach; ?>
        <th class="pipe-th">Certificate</th>
      </tr>
      <tr class="table-light">
        <td colspan="2"></td>
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
          <div class="fw-semibold"><?= e($r['first_name'].' '.$r['last_name']) ?></div>
          <div class="text-muted small"><?= e($r['email']) ?></div>
        </td>
        <td class="pipe-td">
          <?php if ($allGreen): ?><span class="badge text-bg-success">Ready</span>
          <?php else: ?><span class="badge text-bg-warning">Pending</span><?php endif; ?>
        </td>
        <td class="pipe-td"><?= dot($r['online_complete']) ?></td>
        <td class="pipe-td"><?= dot($r['avetmiss_complete']) ?></td>
        <td class="pipe-td"><?= dot((bool)$r['usi_number']) ?></td>
        <td class="pipe-td"><?= dot($paid, $r['payment_status']==='part') ?></td>
        <td class="pipe-td"><?= dot($r['id_confirmed']) ?></td>
        <td class="pipe-td"><?= dot($r['attendance_marked']) ?></td>
        <td class="pipe-td"><?= dot($r['tasks_satisfactory'], !$r['tasks_satisfactory'] && $r['attendance_marked']) ?></td>
        <td class="pipe-td">
          <?php if ($allGreen): ?>
            <button class="btn btn-sm btn-anb py-0"><i class="bi bi-award"></i> Generate</button>
          <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; if(!$rows): ?><tr><td colspan="10" class="text-muted p-3">No students enrolled in this class.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  <div class="d-flex justify-content-between align-items-center p-3 border-top">
    <div class="small text-muted">Only students with every box green can be certified.</div>
    <div>
      <button class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat"></i> Refresh</button>
      <button class="btn btn-success"><i class="bi bi-check2-all"></i> Sign Off &amp; Generate Certificates (<?= $ready ?>)</button>
    </div>
  </div>
</div>
