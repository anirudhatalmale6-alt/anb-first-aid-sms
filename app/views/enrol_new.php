<?php $flash=$_SESSION['flash']??null; unset($_SESSION['flash']); ?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">New Enrolment</h4>
    <div class="text-muted small">Enrol a student into a class from the back end</div></div>
  <a href="?r=enrolments" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Enrolments</a>
</div>
<?php if($flash): ?><div class="alert alert-info py-2"><i class="bi bi-info-circle"></i> <?= e($flash) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8"><div class="card p-3">
    <form method="post" action="?r=enrol_create">
      <h6 class="fw-bold mb-2">1. Choose the class</h6>
      <select name="schedule_id" class="form-select form-select-sm mb-3" required>
        <option value="">— select a class —</option>
        <?php foreach($schedules as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= e($s['code']) ?> — <?= e($s['title']) ?> · <?= e(date('D j M Y', strtotime((string)$s['start_date']))) ?><?= $s['start_time']?(' '.e(date('g:iA',strtotime((string)$s['start_time'])))):'' ?><?= $s['loc']?(' · '.e($s['loc'])):'' ?></option>
        <?php endforeach; ?>
      </select>
      <h6 class="fw-bold mb-2">2. Student</h6>
      <p class="small text-muted mb-2">Enter the student's email. If they already exist we'll use their record; if not, we'll create it from the name below.</p>
      <div class="row g-2">
        <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Email</label><input name="email" type="email" class="form-control form-control-sm" required></div>
        <div class="col-md-3"><label class="form-label small fw-semibold mb-0">First name</label><input name="first_name" class="form-control form-control-sm"></div>
        <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Last name</label><input name="last_name" class="form-control form-control-sm"></div>
      </div>
      <div class="form-check mt-3">
        <input class="form-check-input" type="checkbox" name="send_access" value="1" id="sa" checked>
        <label class="form-check-label small" for="sa">Email the student their portal login so they can complete their own details</label>
      </div>
      <button class="btn btn-anb btn-sm mt-3"><i class="bi bi-journal-plus"></i> Enrol student</button>
    </form>
  </div></div>
  <div class="col-lg-4"><div class="card p-3">
    <h6 class="fw-bold mb-2">How this works</h6>
    <ul class="small mb-0" style="line-height:1.7;">
      <li>Pick the class (these come from your Schedules).</li>
      <li>Enter the student's email + name. Existing students are matched by email.</li>
      <li>Leave "email the student their portal login" ticked and they'll receive their login to complete their own personal details and USI in the student portal.</li>
      <li>Need a class first? Create one under <a href="?r=schedules">Schedules</a>.</li>
    </ul>
  </div></div>
</div>
