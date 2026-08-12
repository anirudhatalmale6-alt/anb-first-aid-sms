<div class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:linear-gradient(135deg,#E53935,#8e24aa);padding:20px;">
  <div class="card p-4" style="width:400px;max-width:100%;">
    <div class="text-center mb-3">
      <img src="assets/logo-color.png" alt="A&amp;B First Aid Training" style="max-width:190px;width:75%;height:auto;">
      <h5 class="mt-3 mb-0" style="color:#2F1D3A;font-weight:700;"><?= !empty($forgot)?'Reset your password':'Student Login' ?></h5>
    </div>
    <?php if (!empty($error)): ?><div class="alert <?= (strpos((string)$error,'emailed')!==false)?'alert-info':'alert-danger' ?> py-2 small"><?= e($error) ?></div><?php endif; ?>
    <?php if (!empty($forgot)): ?>
      <form method="post" action="?r=student_forgot">
        <label class="form-label small fw-semibold">Email</label>
        <input name="email" type="email" class="form-control mb-3" placeholder="Your email address" required>
        <button class="btn btn-anb w-100">Email me my login details</button>
      </form>
      <div class="text-center mt-3"><a href="?r=student_login" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left"></i> Back to login</a></div>
    <?php else: ?>
    <form method="post" action="?r=student_login">
      <label class="form-label small fw-semibold">Email</label>
      <input name="email" type="email" class="form-control mb-3" placeholder="Your email address" required>
      <label class="form-label small fw-semibold">Password</label>
      <input name="password" type="password" class="form-control mb-2" placeholder="Password" required>
      <div class="text-end mb-3"><a href="?r=student_forgot" class="small text-decoration-none" style="color:#8e24aa;">Forgot password?</a></div>
      <button class="btn btn-anb w-100">Log in</button>
    </form>
    <div class="text-center text-muted small mt-3">Access your courses &amp; certificates.</div>
    <?php endif; ?>
    <hr class="my-3">
    <div class="text-center"><a href="?r=login" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left"></i> Staff / Trainer login</a></div>
  </div>
</div>
