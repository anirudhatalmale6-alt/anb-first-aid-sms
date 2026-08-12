<div style="min-height:100vh;background:linear-gradient(135deg,#E53935,#8e24aa);padding:30px 16px;">
  <div class="card p-4 mx-auto" style="max-width:720px;">
    <div class="text-center mb-3">
      <img src="assets/logo-color.png" alt="A&amp;B First Aid Training" style="max-width:200px;width:70%;height:auto;">
    </div>
    <?php if (!empty($done)): ?>
      <div class="text-center py-4">
        <div style="font-size:3rem;color:#2e7d32;"><i class="bi bi-check-circle-fill"></i></div>
        <h4 class="fw-bold" style="color:#2F1D3A;">Thank you — request received</h4>
        <p class="text-muted">We've received your group booking request and our team will be in touch shortly to confirm the details and provide a quote.</p>
        <p class="small text-muted mb-0">A&amp;B First Aid Training · admin@anbfirstaidtraining.com.au · 0423 427 765</p>
      </div>
    <?php else: ?>
      <h4 class="fw-bold text-center mb-1" style="color:#2F1D3A;">Group / Corporate Booking</h4>
      <p class="text-center text-muted small mb-3">Booking first aid training for your team or an onsite session? Complete the form and we'll get back to you with availability and a quote.</p>
      <?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="?r=group_booking">
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Company / organisation *</label><input name="company" class="form-control form-control-sm" value="<?= e($f['company']??'') ?>" required></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Contact name *</label><input name="contact_name" class="form-control form-control-sm" value="<?= e($f['contact_name']??'') ?>" required></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Email *</label><input name="email" type="email" class="form-control form-control-sm" value="<?= e($f['email']??'') ?>" required></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Phone</label><input name="phone" class="form-control form-control-sm" value="<?= e($f['phone']??'') ?>"></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Course</label>
            <select name="course_label" class="form-select form-select-sm">
              <option value="">— select a course —</option>
              <?php foreach(($courses??[]) as $c): $lbl=$c['code'].' — '.$c['title']; ?><option <?= (($f['course_label']??'')===$lbl)?'selected':'' ?>><?= e($lbl) ?></option><?php endforeach; ?>
              <option>Not sure / need advice</option>
            </select></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Number of participants</label><input name="participants" type="number" min="1" class="form-control form-control-sm" value="<?= e($f['participants']??'') ?>"></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Preferred date(s)</label><input name="preferred_date" class="form-control form-control-sm" placeholder="e.g. week of 15 Sept, or a few options" value="<?= e($f['preferred_date']??'') ?>"></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Onsite location / address</label><input name="location" class="form-control form-control-sm" placeholder="Where should we deliver the training?" value="<?= e($f['location']??'') ?>"></div>
          <div class="col-12"><label class="form-label small fw-semibold mb-0">Attendees (optional)</label><textarea name="attendees" class="form-control form-control-sm" rows="4" placeholder="You can list names and emails now, or send them later. One per line."><?= e($f['attendees']??'') ?></textarea></div>
          <div class="col-12"><label class="form-label small fw-semibold mb-0">Anything else?</label><textarea name="notes" class="form-control form-control-sm" rows="2"><?= e($f['notes']??'') ?></textarea></div>
        </div>
        <button class="btn btn-anb w-100 mt-3">Submit booking request</button>
        <p class="text-center text-muted small mt-2 mb-0">We'll respond by email with availability and a quote.</p>
      </form>
    <?php endif; ?>
  </div>
</div>
