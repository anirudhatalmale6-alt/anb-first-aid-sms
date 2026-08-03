<?php $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); ?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Email Settings</h4>
    <div class="text-muted small">SMTP mail server used to send certificates, reminders and confirmations</div></div>
  <a href="?r=emails" class="btn btn-outline-secondary btn-sm"><i class="bi bi-envelope-paper"></i> Templates</a>
</div>
<?php if ($flash): ?><div class="alert alert-info py-2"><?= e($flash) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card p-3">
      <h6 class="fw-bold mb-3"><i class="bi bi-hdd-network"></i> SMTP server</h6>
      <form method="post" action="?r=email_settings_save">
        <div class="row g-2">
          <div class="col-8">
            <label class="form-label small fw-semibold">Host</label>
            <input name="smtp_host" class="form-control" value="<?= e($settings['smtp_host'] ?? '') ?>" placeholder="smtp.office365.com">
          </div>
          <div class="col-4">
            <label class="form-label small fw-semibold">Port</label>
            <input name="smtp_port" class="form-control" value="<?= e($settings['smtp_port'] ?? '587') ?>" placeholder="587">
          </div>
        </div>
        <label class="form-label small fw-semibold mt-2">Security</label>
        <select name="smtp_security" class="form-select">
          <?php foreach (['tls'=>'STARTTLS (port 587)','ssl'=>'SSL/TLS (port 465)','none'=>'None'] as $k=>$lbl): ?>
            <option value="<?= $k ?>" <?= ($settings['smtp_security'] ?? 'tls')===$k?'selected':'' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
        <label class="form-label small fw-semibold mt-2">Username</label>
        <input name="smtp_user" class="form-control" value="<?= e($settings['smtp_user'] ?? '') ?>" placeholder="admin@anbfirstaidtraining.com.au">
        <label class="form-label small fw-semibold mt-2">Password / app password</label>
        <input name="smtp_pass" type="password" class="form-control" value="" placeholder="<?= !empty($settings['smtp_pass']) ? '•••••••• (leave blank to keep)' : 'enter the mailbox app password' ?>">
        <div class="small text-muted mt-1">Office 365 with MFA needs an app password. Leave blank to keep the saved one.</div>
        <hr>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label small fw-semibold">From address</label>
            <input name="mail_from" class="form-control" value="<?= e($settings['mail_from'] ?? '') ?>">
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">From name</label>
            <input name="mail_from_name" class="form-control" value="<?= e($settings['mail_from_name'] ?? '') ?>">
          </div>
        </div>
        <button class="btn btn-anb mt-3"><i class="bi bi-save"></i> Save settings</button>
      </form>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card p-3">
      <h6 class="fw-bold mb-2"><i class="bi bi-send-check"></i> Send a test</h6>
      <p class="small text-muted">Save your settings first, then send a test email to confirm everything works.</p>
      <form method="post" action="?r=email_test">
        <label class="form-label small fw-semibold">Send test to</label>
        <input name="to" type="email" class="form-control mb-2" placeholder="you@example.com" required>
        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-envelope-arrow-up"></i> Send test email</button>
      </form>
      <div class="alert alert-light border mt-3 small mb-0">
        <strong>Status:</strong>
        <?php if (!empty($settings['smtp_pass'])): ?>
          <span class="text-success">Password saved — ready to send.</span>
        <?php else: ?>
          <span class="text-warning">No password saved yet — sending is disabled until you add one.</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
