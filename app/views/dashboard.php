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

<!-- Class Schedule (agenda list) -->
<?php
  $cvw = $cal['view']; $anc = $cal['anchor'];
  $clink = fn($v,$d) => '?r=dashboard&cv='.$v.'&d='.$d;
?>
<div class="card p-3 mb-4">
  <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <h6 class="fw-bold mb-0"><i class="bi bi-calendar3 text-primary"></i> Class Schedule</h6>
    <div class="btn-group btn-group-sm" role="group">
      <a href="<?= $clink('day',$anc) ?>"   class="btn <?= $cvw==='day'  ?'btn-anb':'btn-outline-secondary' ?>">Day</a>
      <a href="<?= $clink('week',$anc) ?>"  class="btn <?= $cvw==='week' ?'btn-anb':'btn-outline-secondary' ?>">Week</a>
      <a href="<?= $clink('month',$anc) ?>" class="btn <?= $cvw==='month'?'btn-anb':'btn-outline-secondary' ?>">Month</a>
    </div>
  </div>
  <div class="d-flex gap-3 small text-muted mb-2 flex-wrap">
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#1565c0;vertical-align:middle;"></span> Classes</span>
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#f9a825;vertical-align:middle;"></span> Employer classes</span>
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#c62828;vertical-align:middle;"></span> Holidays</span>
  </div>
  <div class="d-flex align-items-center gap-2 mb-2">
    <a href="<?= $clink($cvw,$cal['prev']) ?>"  class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
    <a href="<?= $clink($cvw,$cal['today']) ?>" class="btn btn-sm btn-outline-secondary">Today</a>
    <a href="<?= $clink($cvw,$cal['next']) ?>"  class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
    <span class="fw-semibold ms-1" style="color:#2F1D3A;"><?= $cal['label'] ?></span>
  </div>
  <div style="max-height:340px;overflow:auto;border-top:1px solid #eee;">
  <?php if (empty($cal['sessions'])): ?>
    <p class="text-muted small mb-0 pt-3">No classes scheduled in this period.</p>
  <?php else: foreach ($cal['sessions'] as $date=>$evs): $dts = strtotime($date); ?>
    <div class="fw-semibold small text-muted mt-2 mb-1" style="text-transform:uppercase;letter-spacing:.03em;"><?= date('l, j F Y', $dts) ?></div>
    <?php foreach ($evs as $ev):
      $st = !empty($ev['start_time']) ? date('g:iA', strtotime((string)$ev['start_time'])) : '';
      $et = !empty($ev['end_time'])   ? date('g:iA', strtotime((string)$ev['end_time']))   : '';
      $tr = $st.($et ? ' - '.$et : '');
      $places=(int)($ev['total_places']?:0); $bk=(int)$ev['booked']; $full=$places && $bk>=$places; ?>
      <div class="d-flex align-items-start py-2" style="border-bottom:1px solid #f1f0f3;">
        <div class="small text-muted" style="width:118px;flex:0 0 118px;white-space:nowrap;"><?= e($tr) ?></div>
        <div class="me-2" style="margin-top:4px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#1565c0;"></span></div>
        <div class="small" style="line-height:1.3;">
          <!-- The whole line is the link: this is the fastest way into a class
               on the day, and reading a class you cannot open is no use. -->
          <a href="?r=pipeline&schedule_id=<?= (int)$ev['id'] ?>" class="text-decoration-none"
             style="color:#1565c0;" title="Open this class and everyone in it">
            <span class="fw-semibold"><?= e($ev['course_code']) ?></span> <?= e($ev['course_title']) ?><?php
              if(!empty($ev['location'])) echo ' at '.e($ev['location']);
              if(!empty($ev['trainer_name'])) echo ' with '.e($ev['trainer_name']);
              if($st) echo ' at '.e($st);
            ?>
          </a>
          <span class="badge <?= $full?'text-bg-danger':'text-bg-light border' ?> ms-1"><?= $bk ?><?= $places?'/'.$places:'' ?> booked</span>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endforeach; endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card p-3 h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold mb-0"><i class="bi bi-bell text-danger"></i> Next to expire (30 days)</h6>
        <a href="?r=reminders" class="small">All renewals →</a>
      </div>
      <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Student</th><th>Course</th><th>Expires</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($expiring as $x): $d = days_until($x['expiry_date']); ?>
          <tr>
            <td class="fw-semibold">
              <a href="?r=student&id=<?= (int)$x['student_id'] ?>" class="text-decoration-none"><?= e($x['first_name'].' '.$x['last_name']) ?></a>
              <div class="text-muted small"><?= e($x['email']) ?></div>
            </td>
            <td class="small"><?= e($x['course_title']) ?></td>
            <td class="small"><?= e($x['expiry_date']) ?></td>
            <td><?php if ($d < 0): ?><span class="badge text-bg-danger">Expired <?= abs($d) ?>d ago</span>
                <?php elseif ($d === 0): ?><span class="badge text-bg-danger">today</span>
                <?php elseif ($d <= 7): ?><span class="badge text-bg-danger">in <?= $d ?>d</span>
                <?php else: ?><span class="badge text-bg-warning">in <?= $d ?>d</span><?php endif; ?></td>
          </tr>
        <?php endforeach; if (!$expiring): ?><tr><td colspan="4" class="text-muted small">
          Nothing expiring in the next 30 days. <a href="?r=reminders">Renewal Reminders</a> has the
          students whose certificates have already lapsed.
        </td></tr><?php endif; ?>
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
