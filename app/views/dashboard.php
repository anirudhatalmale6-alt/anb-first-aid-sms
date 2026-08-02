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

<!-- Next training dates + Students per course -->
<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card p-3 h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold mb-0"><i class="bi bi-calendar-event text-primary"></i> Next training dates</h6>
        <a href="?r=schedules" class="small">All schedules →</a>
      </div>
      <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Date</th><th>Course</th><th>Location</th><th class="text-center">Booked</th></tr></thead>
        <tbody>
        <?php foreach (($next_sessions ?? []) as $s):
          $ts = strtotime((string)$s['start_date']);
          $places = (int)($s['total_places'] ?: 0); $booked = (int)$s['booked'];
          $full = $places && $booked >= $places; ?>
          <tr>
            <td class="small fw-semibold" style="white-space:nowrap;">
              <?= $ts ? date('D j M Y', $ts) : e($s['start_date']) ?>
              <?php if (!empty($s['start_time'])): ?><div class="text-muted"><?= e(substr((string)$s['start_time'],0,5)) ?></div><?php endif; ?>
            </td>
            <td class="small"><span class="badge text-bg-light border me-1"><?= e($s['course_code']) ?></span><?= e($s['course_title']) ?>
              <?php if (!empty($s['trainer_name'])): ?><div class="text-muted">Trainer: <?= e($s['trainer_name']) ?></div><?php endif; ?></td>
            <td class="small"><?= e($s['location'] ?: '—') ?></td>
            <td class="text-center">
              <span class="badge <?= $full ? 'text-bg-danger' : 'text-bg-success' ?>"><?= $booked ?><?= $places ? '/'.$places : '' ?></span>
            </td>
          </tr>
        <?php endforeach; if (empty($next_sessions)): ?>
          <tr><td colspan="4" class="text-muted small">No upcoming sessions scheduled.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card p-3 h-100">
      <h6 class="fw-bold mb-2"><i class="bi bi-mortarboard text-danger"></i> Students in each course</h6>
      <?php
      $maxc = 0; foreach (($course_counts ?? []) as $cc) { $maxc = max($maxc, (int)$cc['cnt']); }
      foreach (($course_counts ?? []) as $cc): $cnt = (int)$cc['cnt']; $w = $maxc ? round($cnt/$maxc*100) : 0; ?>
        <div class="mb-2">
          <div class="d-flex justify-content-between small">
            <span><span class="badge text-bg-light border me-1"><?= e($cc['code']) ?></span><?= e($cc['title']) ?></span>
            <span class="fw-semibold"><?= $cnt ?></span>
          </div>
          <div class="progress mt-1" style="height:6px;"><div class="progress-bar" style="width:<?= $w ?>%;background:#8e24aa;"></div></div>
        </div>
      <?php endforeach; if (empty($course_counts)): ?><p class="text-muted small mb-0">No courses yet.</p><?php endif; ?>
    </div>
  </div>
</div>

<!-- Training calendar -->
<?php
  $first    = ($ym ?? date('Y-m')).'-01';
  $firstTs  = strtotime($first);
  $daysIn   = (int)date('t', $firstTs);
  $startDow = (int)date('N', $firstTs);          // 1=Mon .. 7=Sun
  $monthLbl = date('F Y', $firstTs);
  $prevYm   = date('Y-m', strtotime($first.' -1 month'));
  $nextYm   = date('Y-m', strtotime($first.' +1 month'));
  $todayStr = date('Y-m-d');
  $cal      = $cal_events ?? [];
?>
<div class="card p-3 mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0"><i class="bi bi-calendar3 text-primary"></i> Training calendar</h6>
    <div class="d-flex align-items-center gap-2">
      <a href="?r=dashboard&ym=<?= $prevYm ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
      <span class="fw-semibold" style="min-width:130px;text-align:center;"><?= e($monthLbl) ?></span>
      <a href="?r=dashboard&ym=<?= $nextYm ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px;">
    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dn): ?>
      <div class="text-muted small fw-semibold text-center" style="text-transform:uppercase;letter-spacing:.03em;"><?= $dn ?></div>
    <?php endforeach; ?>
    <?php for ($i=1; $i<$startDow; $i++): ?><div></div><?php endfor; ?>
    <?php for ($d=1; $d<=$daysIn; $d++):
      $dateStr = sprintf('%s-%02d', $ym, $d);
      $evs = $cal[$dateStr] ?? [];
      $isToday = ($dateStr === $todayStr); ?>
      <div style="border:1px solid <?= $isToday ? '#E53935' : '#eceaf0' ?>;border-radius:8px;min-height:88px;padding:5px 6px;<?= $isToday ? 'box-shadow:0 0 0 1px #E53935 inset;' : '' ?>">
        <div class="small <?= $isToday ? 'fw-bold' : 'text-muted' ?>" style="<?= $isToday ? 'color:#E53935;' : '' ?>"><?= $d ?></div>
        <?php foreach ($evs as $ev):
          $places=(int)($ev['total_places']?:0); $bk=(int)$ev['booked']; $full=$places && $bk>=$places; ?>
          <div class="mt-1" title="<?= e($ev['course_title'].' · '.($ev['location']?:'')) ?>"
               style="background:<?= $full ? '#fdecea' : '#efeaf5' ?>;border-left:3px solid <?= $full ? '#E53935' : '#8e24aa' ?>;border-radius:4px;padding:2px 4px;font-size:.68rem;line-height:1.15;overflow:hidden;">
            <span class="fw-semibold"><?= e(substr((string)$ev['start_time'],0,5)) ?></span> <?= e($ev['course_code']) ?>
            <div style="color:#555;"><?= $bk ?><?= $places?'/'.$places:'' ?> booked</div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endfor; ?>
  </div>
  <div class="d-flex gap-3 mt-2 small text-muted">
    <span><span style="display:inline-block;width:10px;height:10px;background:#8e24aa;border-radius:2px;vertical-align:middle;"></span> Session (places available)</span>
    <span><span style="display:inline-block;width:10px;height:10px;background:#E53935;border-radius:2px;vertical-align:middle;"></span> Full</span>
  </div>
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
