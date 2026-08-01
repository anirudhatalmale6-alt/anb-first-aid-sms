<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Renewal Reminders</h4>
    <div class="text-muted small">Automatic email reminders before a student's certificate expires</div></div>
  <span class="badge text-bg-success"><i class="bi bi-robot"></i> Automation ON</span>
</div>

<div class="alert alert-light border small">
  <i class="bi bi-info-circle text-primary"></i>
  The system checks every day and automatically emails students a friendly reminder to re-book
  <strong>6 weeks</strong> and again <strong>2 weeks</strong> before their certificate expires (CPR yearly, First Aid every 3 years),
  with a direct link to your booking calendar. No manual chasing.
</div>

<div class="card p-3">
  <div class="table-responsive">
  <table class="table align-middle">
    <thead><tr><th>Student</th><th>Course</th><th>Expires</th><th>Countdown</th><th>6-week email</th><th>2-week email</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c): $d = days_until($c['expiry_date']); ?>
      <tr>
        <td class="fw-semibold small"><?= e($c['first_name'].' '.$c['last_name']) ?><div class="text-muted fw-normal"><?= e($c['email']) ?></div></td>
        <td class="small"><?= e($c['course_title']) ?><div class="text-muted"><?= (int)$c['validity_months'] ?>mo validity</div></td>
        <td class="small"><?= e($c['expiry_date']) ?></td>
        <td>
          <?php if ($d < 0): ?><span class="badge text-bg-danger">Expired <?= abs($d) ?>d ago</span>
          <?php elseif ($d <= 14): ?><span class="badge text-bg-danger">in <?= $d ?>d</span>
          <?php elseif ($d <= 42): ?><span class="badge text-bg-warning">in <?= $d ?>d</span>
          <?php else: ?><span class="badge text-bg-light border">in <?= $d ?>d</span><?php endif; ?>
        </td>
        <td class="small"><?php if ($d <= 42): ?><i class="bi bi-envelope-check-fill text-success"></i> queued<?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
        <td class="small"><?php if ($d <= 14): ?><i class="bi bi-envelope-check-fill text-success"></i> queued<?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
        <td class="text-end"><a href="#" class="btn btn-sm btn-outline-secondary"><i class="bi bi-send"></i> Send now</a></td>
      </tr>
    <?php endforeach; if(!$rows): ?><tr><td colspan="7" class="text-muted">No certificates to track yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
