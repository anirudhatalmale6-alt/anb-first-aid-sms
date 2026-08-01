<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Trainer Dashboard</h4>
    <div class="text-muted small">
      <?php if ($isTrainer): ?>Welcome <?= e($me['name']) ?> — your upcoming &amp; recent classes
      <?php else: ?>All classes (admin view) — trainers see only their own<?php endif; ?>
    </div></div>
</div>

<?php
$total = count($classes);
$upcoming = array_filter($classes, fn($c)=>$c['start_date']>='2026-08-01');
$learners = array_sum(array_map(fn($c)=>(int)$c['enrolled'], $classes));
?>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card stat-card p-3"><div class="d-flex justify-content-between">
    <div><div class="text-muted small text-uppercase fw-semibold">My classes</div><div class="num"><?= $total ?></div></div>
    <i class="bi bi-calendar3 ico text-danger"></i></div></div></div>
  <div class="col-md-4"><div class="card stat-card p-3"><div class="d-flex justify-content-between">
    <div><div class="text-muted small text-uppercase fw-semibold">Upcoming</div><div class="num"><?= count($upcoming) ?></div></div>
    <i class="bi bi-clock-history ico text-primary"></i></div></div></div>
  <div class="col-md-4"><div class="card stat-card p-3"><div class="d-flex justify-content-between">
    <div><div class="text-muted small text-uppercase fw-semibold">Learners enrolled</div><div class="num"><?= $learners ?></div></div>
    <i class="bi bi-people ico text-success"></i></div></div></div>
</div>

<div class="card p-3">
  <h6 class="fw-bold mb-3">Class list</h6>
  <table class="table align-middle mb-0">
    <thead><tr><th>Date</th><th>Class</th><th>Location</th><th class="text-center">Enrolled</th>
      <th class="text-center">Attendance</th><th class="text-center">Assessed</th><th class="text-center">Issued</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($classes as $c):
      $enr=(int)$c['enrolled']; $pres=(int)$c['present']; $ass=(int)$c['assessed']; $iss=(int)$c['issued'];
      $upc = $c['start_date']>='2026-08-01';
    ?>
      <tr>
        <td class="small"><?= e($c['start_date']) ?><br><span class="text-muted"><?= e(substr((string)$c['start_time'],0,5)) ?></span></td>
        <td class="small fw-semibold"><?= e($c['course_code']) ?> — <?= e($c['plan_title']) ?>
          <?php if ($upc): ?><span class="badge text-bg-info ms-1">Upcoming</span><?php endif; ?></td>
        <td class="small"><?= e($c['location']) ?></td>
        <td class="text-center"><?= $enr ?></td>
        <td class="text-center"><?php if($enr): ?><span class="badge text-bg-<?= $pres>=$enr&&$enr?'success':'light text-muted' ?>"><?= $pres ?>/<?= $enr ?></span><?php else: ?>—<?php endif; ?></td>
        <td class="text-center"><?php if($enr): ?><span class="badge text-bg-<?= $ass>=$enr&&$enr?'success':'light text-muted' ?>"><?= $ass ?>/<?= $enr ?></span><?php else: ?>—<?php endif; ?></td>
        <td class="text-center"><?php if($iss): ?><span class="badge text-bg-success"><?= $iss ?></span><?php else: ?>—<?php endif; ?></td>
        <td class="text-end"><a class="btn btn-sm btn-anb" href="?r=pipeline&schedule_id=<?= $c['id'] ?>"><i class="bi bi-clipboard2-check"></i> Mark &amp; sign off</a></td>
      </tr>
    <?php endforeach; if(!$classes): ?>
      <tr><td colspan="8" class="text-muted small text-center py-3">No classes assigned yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  <div class="text-muted small mt-3"><i class="bi bi-info-circle"></i> Open a class to mark attendance, record assessment outcomes and sign off — that's what triggers certificate generation.</div>
</div>
