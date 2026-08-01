<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Dashboard</h4>
    <div class="text-muted small">Welcome back, <?= e(current_user()['name']) ?> &middot; A&amp;B First Aid Training (RTO 46055)</div></div>
  <a href="?r=students" class="btn btn-anb"><i class="bi bi-plus-lg"></i> New enrolment</a>
</div>

<div class="row g-3 mb-4">
  <?php
  $cards = [
    ['Students', $stats['students'], 'bi-people', '#8e24aa'],
    ['Enrolments', $stats['enrolments'], 'bi-journal-check', '#E53935'],
    ['Certificates issued', $stats['issued'], 'bi-award', '#2e7d32'],
    ['Upcoming classes', $stats['upcoming'], 'bi-calendar3', '#1565c0'],
  ];
  foreach ($cards as $c): ?>
  <div class="col-6 col-lg-3">
    <div class="card stat-card p-3">
      <div class="d-flex justify-content-between align-items-start">
        <div><div class="text-muted small"><?= e($c[0]) ?></div><div class="num"><?= (int)$c[1] ?></div></div>
        <span class="ico" style="color:<?= $c[3] ?>"><i class="bi <?= $c[2] ?>"></i></span>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card p-3 h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold mb-0"><i class="bi bi-bell text-danger"></i> Renewals due (next 60 days)</h6>
        <a href="?r=reminders" class="small">Manage reminders →</a>
      </div>
      <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Student</th><th>Course</th><th>Expires</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($expiring as $x): $d = days_until($x['expiry_date']); ?>
          <tr>
            <td class="fw-semibold"><?= e($x['first_name'].' '.$x['last_name']) ?><div class="text-muted small"><?= e($x['email']) ?></div></td>
            <td class="small"><?= e($x['course_title']) ?></td>
            <td class="small"><?= e($x['expiry_date']) ?></td>
            <td><?php if ($d < 0): ?><span class="badge text-bg-danger">Expired <?= abs($d) ?>d ago</span>
                <?php else: ?><span class="badge text-bg-warning">in <?= $d ?>d</span><?php endif; ?></td>
          </tr>
        <?php endforeach; if (!$expiring): ?><tr><td colspan="4" class="text-muted small">Nothing due.</td></tr><?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card p-3 h-100">
      <h6 class="fw-bold mb-2"><i class="bi bi-hourglass-split text-info"></i> Completed &mdash; ready to certify</h6>
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Student</th><th>Course</th></tr></thead>
        <tbody>
        <?php foreach ($pending as $p): ?>
          <tr><td class="fw-semibold small"><?= e($p['first_name'].' '.$p['last_name']) ?></td><td class="small"><?= e($p['course_title']) ?></td></tr>
        <?php endforeach; if (!$pending): ?><tr><td colspan="2" class="text-muted small">None waiting.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
