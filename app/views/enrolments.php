<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Enrolments</h4>
    <div class="text-muted small"><?= count($rows) ?> total</div></div>
</div>
<div class="card p-3">
  <div class="table-responsive">
  <table class="table align-middle">
    <thead><tr><th>Student</th><th>Course / Plan</th><th>Schedule</th><th>Location</th><th>Status</th><th>Payment</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $e): ?>
      <tr>
        <td class="fw-semibold"><a href="?r=student&id=<?= (int)$e['student_id'] ?>" class="text-decoration-none"><?= e($e['first_name'].' '.$e['last_name']) ?></a></td>
        <td class="small"><span class="fw-semibold"><?= e($e['course_code']) ?></span><div class="text-muted"><?= e($e['plan_title']) ?></div></td>
        <td class="small"><?= e($e['sched_date'] ?: '—') ?></td>
        <td class="small"><?= e($e['location'] ?: '—') ?></td>
        <td><?= status_badge($e['status']) ?></td>
        <td><span class="badge text-bg-<?= $e['payment_status']==='paid'?'success':($e['payment_status']==='part'?'warning':'secondary') ?>"><?= ucfirst($e['payment_status']) ?></span>
            <div class="text-muted small">$<?= number_format((float)$e['amount_paid'],2) ?> / $<?= number_format((float)$e['amount_due'],2) ?></div></td>
      </tr>
    <?php endforeach; if(!$rows): ?><tr><td colspan="6" class="text-muted">No enrolments.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
