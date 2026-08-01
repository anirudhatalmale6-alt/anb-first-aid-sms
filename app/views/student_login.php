<div class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:linear-gradient(135deg,#E53935,#8e24aa);padding:20px;">
  <div class="card p-4" style="width:400px;max-width:100%;">
    <div class="text-center mb-3">
      <span class="logo-badge" style="width:52px;height:52px;font-size:1.2rem;">A&amp;B</span>
      <h5 class="mt-3 mb-0" style="color:#2F1D3A;font-weight:700;">Student Login</h5>
      <div class="text-muted small">A&amp;B First Aid Training</div>
    </div>
    <?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="?r=student_login">
      <label class="form-label small fw-semibold">Email</label>
      <input name="email" type="email" class="form-control mb-3" value="amna.a@example.com" required>
      <label class="form-label small fw-semibold">Password</label>
      <input name="password" type="password" class="form-control mb-3" value="student123" required>
      <button class="btn btn-anb w-100">Log in</button>
    </form>
    <div class="text-center text-muted small mt-3">Demo login pre-filled. Access your courses &amp; certificates.</div>
  </div>
</div>
