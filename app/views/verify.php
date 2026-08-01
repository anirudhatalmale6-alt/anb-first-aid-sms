<?php
$valid = false; $expired = false; $d = null;
if ($cert) {
    $d = days_until($cert['expiry_date']);
    $expired = ($d !== null && $d < 0);
    $valid = !$expired;
}
?>
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:linear-gradient(135deg,#2F1D3A,#4a2d5c);padding:20px;">
  <div class="card p-4" style="width:480px;max-width:100%;">
    <div class="text-center mb-3">
      <span class="logo-badge" style="width:52px;height:52px;font-size:1.2rem;">A&amp;B</span>
      <h5 class="mt-3 mb-0" style="color:#2F1D3A;font-weight:700;">A&amp;B First Aid Training</h5>
      <div class="text-muted small">Certificate Verification &middot; RTO 46055</div>
    </div>

    <?php if (!$cert): ?>
      <div class="alert alert-danger text-center">
        <i class="bi bi-x-octagon-fill fs-3 d-block mb-2"></i>
        <strong>No certificate found</strong><br>
        <span class="small">We could not find a certificate matching <code><?= e($num ?: '—') ?></code>.</span>
      </div>
    <?php else: ?>
      <div class="alert <?= $valid?'alert-success':'alert-warning' ?> text-center">
        <?php if ($valid): ?>
          <i class="bi bi-patch-check-fill fs-3 d-block mb-2"></i><strong>Valid certificate</strong>
        <?php else: ?>
          <i class="bi bi-exclamation-triangle-fill fs-3 d-block mb-2"></i><strong>Certificate expired</strong>
          <div class="small">Expired on <?= e($cert['expiry_date']) ?> — renewal required.</div>
        <?php endif; ?>
      </div>
      <table class="table table-sm">
        <tr><td class="text-muted">Certificate No</td><td class="fw-semibold text-end"><?= e($cert['certificate_number']) ?></td></tr>
        <tr><td class="text-muted">Issued to</td><td class="fw-semibold text-end"><?= e($cert['first_name'].' '.$cert['last_name']) ?></td></tr>
        <tr><td class="text-muted">Unit</td><td class="fw-semibold text-end"><?= e($cert['course_code']) ?></td></tr>
        <tr><td class="text-muted">Course</td><td class="text-end small"><?= e($cert['course_title']) ?></td></tr>
        <tr><td class="text-muted">Issued</td><td class="text-end"><?= e($cert['issue_date']) ?></td></tr>
        <tr><td class="text-muted">Expires</td><td class="text-end"><?= e($cert['expiry_date']) ?></td></tr>
      </table>
      <div class="text-center text-muted small">This certificate was issued by A&amp;B First Aid Training Pty Ltd (RTO 46055).</div>
    <?php endif; ?>
  </div>
</div>
