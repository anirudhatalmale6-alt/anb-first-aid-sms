<div style="background:#f4f5f7;min-height:100vh;">
  <!-- top bar -->
  <div style="background:#2F1D3A;color:#fff;padding:14px 28px;display:flex;justify-content:space-between;align-items:center;">
    <div style="display:flex;align-items:center;gap:10px;">
      <span class="logo-badge">A&amp;B</span>
      <div><div style="font-weight:700;">A&amp;B First Aid Training</div><div style="font-size:.8rem;opacity:.8;">Student Portal</div></div>
    </div>
    <div style="text-align:right;font-size:.9rem;">
      <?= e($me['first_name'].' '.$me['last_name']) ?>
      <a href="?r=student_logout" style="color:#ffb3b0;margin-left:14px;text-decoration:none;"><i class="bi bi-box-arrow-right"></i> Log out</a>
    </div>
  </div>

  <div style="max-width:900px;margin:0 auto;padding:28px 20px;">
    <h4 class="fw-bold mb-1" style="color:#2F1D3A;">Hi <?= e($me['first_name']) ?> 👋</h4>
    <p class="text-muted">Your courses, learning progress and certificates.</p>

    <!-- My certificates -->
    <div class="card p-3 mb-4">
      <h6 class="fw-bold mb-3"><i class="bi bi-award text-danger"></i> My Certificates</h6>
      <?php if ($mycerts): ?>
        <table class="table align-middle mb-0">
          <thead><tr><th>Course</th><th>Certificate No</th><th>Issued</th><th>Expires</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($mycerts as $c): $d = days_until($c['expiry_date']); ?>
            <tr>
              <td class="small fw-semibold"><?= e($c['course_title']) ?></td>
              <td class="small"><?= e($c['certificate_number']) ?></td>
              <td class="small"><?= e($c['issue_date']) ?></td>
              <td class="small"><?= e($c['expiry_date']) ?>
                <?php if ($d!==null && $d<0): ?><span class="badge text-bg-danger">Expired</span>
                <?php elseif ($d!==null && $d<=60): ?><span class="badge text-bg-warning">Renew soon</span><?php endif; ?></td>
              <td class="text-end">
                <?php if (!empty($c['file_path'])): ?>
                  <a href="?r=mycert&num=<?= urlencode($c['certificate_number']) ?>" class="btn btn-sm btn-anb"><i class="bi bi-download"></i> Download</a>
                <?php else: ?><span class="text-muted small">Processing…</span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?><p class="text-muted small mb-0">No certificates yet — they'll appear here the moment your class is signed off.</p><?php endif; ?>
    </div>

    <!-- My learning -->
    <div class="card p-3">
      <h6 class="fw-bold mb-3"><i class="bi bi-mortarboard text-primary"></i> My Courses &amp; Online Learning</h6>
      <?php foreach ($enrolments as $en):
        $pct = $en['online_complete'] ? 100 : ($en['status']==='enrolled' ? 40 : 100);
      ?>
        <div class="border rounded p-3 mb-2">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-semibold"><?= e($en['course_code']) ?> — <?= e($en['course_title']) ?></div>
              <div class="text-muted small"><?= e($en['plan_title']) ?></div>
              <?php if ($en['sched_date']): ?><div class="small mt-1"><i class="bi bi-calendar3"></i> <?= e($en['sched_date']) ?> <?= e(substr((string)$en['sched_time'],0,5)) ?> · <?= e($en['location']) ?></div><?php endif; ?>
            </div>
            <?= status_badge($en['status']) ?>
          </div>
          <div class="mt-2">
            <div class="d-flex justify-content-between small text-muted"><span>Online learning</span><span><?= $pct ?>%</span></div>
            <div class="progress" style="height:8px;"><div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div></div>
          </div>
          <?php if (!$en['online_complete']): ?>
            <a href="#" class="btn btn-sm btn-outline-danger mt-2"><i class="bi bi-play-circle"></i> Continue online learning</a>
          <?php endif; ?>
        </div>
      <?php endforeach; if(!$enrolments): ?><p class="text-muted small mb-0">You have no active courses.</p><?php endif; ?>
    </div>
  </div>
</div>
