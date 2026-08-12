<?php $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); ?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Email Templates</h4>
    <div class="text-muted small">Reusable emails for certificates, surveys, reminders and enrolments</div></div>
  <a href="?r=email_settings" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear"></i> Email settings</a>
</div>
<?php if ($flash): ?><div class="alert alert-info py-2"><?= e($flash) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card p-3">
      <h6 class="fw-bold mb-2"><i class="bi bi-collection"></i> Templates</h6>
      <div class="list-group list-group-flush">
      <?php foreach ($rows as $t): ?>
        <a href="?r=emails&edit=<?= (int)$t['id'] ?>" class="list-group-item list-group-item-action px-2 <?= (!empty($edit) && $edit['id']==$t['id'])?'active':'' ?>">
          <div class="fw-semibold small"><?= e($t['name']) ?></div>
          <div class="small <?= (!empty($edit) && $edit['id']==$t['id'])?'text-white-50':'text-muted' ?>"><?= e($t['subject']) ?></div>
        </a>
      <?php endforeach; if(!$rows): ?><div class="text-muted small">No templates yet.</div><?php endif; ?>
      </div>
      <a href="?r=emails&edit=0" class="btn btn-outline-danger btn-sm mt-3"><i class="bi bi-plus-circle"></i> New template</a>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card p-3">
      <h6 class="fw-bold mb-3"><?= !empty($edit) ? 'Edit template' : 'New template' ?></h6>
      <form method="post" action="?r=email_save">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <label class="form-label small fw-semibold">Template name</label>
        <input name="name" class="form-control mb-2" value="<?= e($edit['name'] ?? '') ?>" placeholder="e.g. Certificate Issued" required>
        <label class="form-label small fw-semibold">Subject</label>
        <input name="subject" class="form-control mb-2" value="<?= e($edit['subject'] ?? '') ?>">
        <label class="form-label small fw-semibold">Body</label>
        <textarea name="body" class="form-control mb-2" rows="12" style="font-family:inherit;"><?= e($edit['body'] ?? '') ?></textarea>
        <div class="small text-muted mb-3">
          Merge fields you can use: <code>{first_name}</code> <code>{last_name}</code> <code>{course}</code>
          <code>{certificate_number}</code> <code>{certificate_link}</code> <code>{issue_date}</code> <code>{expiry_date}</code>
          <code>{class_date}</code> <code>{start_date}</code> <code>{start_time}</code> <code>{location}</code> <code>{location_address}</code>
          <code>{email}</code> <code>{password}</code> <code>{login_url}</code>
          <code>{survey_link}</code> <code>{booking_link}</code> <code>{portal_link}</code>
        </div>
        <button class="btn btn-anb"><i class="bi bi-save"></i> Save template</button>
        <?php if (!empty($edit)): ?>
          <a href="?r=email_delete&id=<?= (int)$edit['id'] ?>" class="btn btn-outline-danger ms-2" onclick="return confirm('Delete this template?')"><i class="bi bi-trash"></i> Delete</a>
          <a href="?r=emails" class="btn btn-outline-secondary ms-2">Cancel</a>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>
