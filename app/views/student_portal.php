<?php $flash=$_SESSION['flash']??null; unset($_SESSION['flash']); ?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Student Portal Access</h4>
    <div class="text-muted small">Send students their login details for the online portal</div></div>
  <a href="?r=students" class="btn btn-outline-secondary btn-sm"><i class="bi bi-people"></i> Students</a>
</div>
<?php if($flash): ?><div class="alert alert-info py-2"><i class="bi bi-info-circle"></i> <?= e($flash) ?></div><?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col"><div class="card stat-card p-3"><div class="num"><?= number_format($withEmail) ?></div><div class="text-muted small">Students with an email</div></div></div>
  <div class="col"><div class="card stat-card p-3"><div class="num text-success"><?= number_format($sentTotal) ?></div><div class="text-muted small">Access already emailed</div></div></div>
  <div class="col"><div class="card stat-card p-3"><div class="num" style="color:#b8860b;"><?= number_format($pending) ?></div><div class="text-muted small">Still to send</div></div></div>
</div>

<div class="card p-3 mb-3">
  <h6 class="fw-bold mb-2">Send portal access (batched)</h6>
  <p class="small mb-2">Each student is given their own password and emailed their login details. To protect your email
  reputation and stay within your provider's sending limits, this sends in batches. Any student who hasn't been sent
  yet (existing or newly enrolled) is picked up automatically, so you can run it repeatedly until "Still to send" reaches zero.</p>
  <?php if($pending>0): ?>
    <form method="post" action="?r=student_portal_batch" onsubmit="return confirm('Send the next batch of up to 60 portal-access emails now?');">
      <input type="hidden" name="limit" value="60">
      <button class="btn btn-anb btn-sm"><i class="bi bi-send"></i> Send next batch (up to 60)</button>
    </form>
    <div class="small text-muted mt-2">Tip: for the full list, this can also be scheduled to run automatically in batches via a cron job — ask your developer to set the schedule.</div>
  <?php else: ?>
    <div class="alert alert-success py-2 mb-0"><i class="bi bi-check-circle"></i> All students with an email address have been sent their portal access.</div>
  <?php endif; ?>
</div>

<div class="card p-3">
  <h6 class="fw-bold mb-1">Notes</h6>
  <ul class="small mb-0" style="line-height:1.7;">
    <li>You can also send access to one student at a time from their profile (Students &rarr; open a student &rarr; "Send portal access").</li>
    <li>Students who forget their password can reset it themselves from the login page ("Forgot password").</li>
    <li>Emails send from your own mail server, so recipients see A&amp;B First Aid Training as the sender.</li>
  </ul>
</div>
