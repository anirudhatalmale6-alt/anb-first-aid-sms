<div class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:linear-gradient(135deg,#2F1D3A,#4a2d5c);">
  <div class="card p-4" style="width:380px;">
    <div class="text-center mb-3">
      <img src="assets/logo-color.png" alt="A&amp;B First Aid Training" style="max-width:200px;width:80%;height:auto;">
      <div class="text-muted small mt-2">Student Management System</div>
    </div>
    <?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="?r=login">
      <label class="form-label small fw-semibold">Email</label>
      <input name="email" type="email" class="form-control mb-3" value="admin@anbfirstaidtraining.com.au" required>
      <label class="form-label small fw-semibold">Password</label>
      <input name="password" type="password" class="form-control mb-3" value="demo1234" required>
      <button class="btn btn-anb w-100">Sign in</button>
    </form>
    <div class="text-center text-muted small mt-3">RTO 46055</div>
    <hr class="my-3">
    <a href="?r=student_login" class="btn btn-outline-danger w-100"><i class="bi bi-mortarboard"></i> Student Portal login</a>
    <div class="text-center text-muted small mt-2">Students &amp; learners sign in here</div>
  </div>
</div>
