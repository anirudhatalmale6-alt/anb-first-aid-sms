<?php $states=['NSW','VIC','QLD','SA','WA','TAS','NT','ACT']; $f=$f??[]; ?>
<div style="min-height:100vh;background:linear-gradient(135deg,#E53935,#8e24aa);padding:30px 16px;">
  <div class="card p-4 mx-auto" style="max-width:760px;">
    <div class="text-center mb-3"><img src="assets/logo-color.png" alt="A&amp;B First Aid Training" style="max-width:200px;width:70%;height:auto;"></div>
    <?php if (!empty($invalid)): ?>
      <div class="text-center py-4"><div style="font-size:2.5rem;color:#c62828;"><i class="bi bi-exclamation-triangle"></i></div>
        <h5 class="fw-bold" style="color:#2F1D3A;">This enrolment link isn't valid</h5>
        <p class="text-muted small">Please check the link or contact us at admin@anbfirstaidtraining.com.au.</p></div>
    <?php elseif (!empty($done)): ?>
      <div class="text-center py-4"><div style="font-size:3rem;color:#2e7d32;"><i class="bi bi-check-circle-fill"></i></div>
        <h4 class="fw-bold" style="color:#2F1D3A;">You're enrolled!</h4>
        <p class="text-muted">You've been enrolled in <strong><?= e($sc['code'].' — '.$sc['title']) ?></strong><?= $sc['start_date']?(' on '.e(date('D j M Y',strtotime((string)$sc['start_date'])))):'' ?>.</p>
        <?php if (!empty($mailFailed)): ?>
          <div class="alert alert-warning small text-start">
            <strong>We could not email your login just now.</strong> Your enrolment is saved, so nothing is lost.
            Please email <a href="mailto:admin@anbfirstaidtraining.com.au">admin@anbfirstaidtraining.com.au</a>
            or call 0423 427 765 and we will send your Student Portal login straight away.
          </div>
        <?php else: ?>
          <p class="text-muted small">We've emailed you a login for the Student Portal, where you can complete your online learning and update any details. Please complete your online modules before your class.</p>
          <p class="text-muted small">If it hasn't arrived within a few minutes, please check your Junk or Spam folder — it comes from admin@anbfirstaidtraining.com.au.</p>
        <?php endif; ?>
        <p class="small text-muted mb-0">A&amp;B First Aid Training · admin@anbfirstaidtraining.com.au · 0423 427 765</p></div>
    <?php else: ?>
      <h4 class="fw-bold text-center mb-1" style="color:#2F1D3A;">Enrol in this course</h4>
      <div class="text-center mb-3">
        <span class="badge text-bg-light border"><?= e($sc['code'].' — '.$sc['title']) ?></span>
        <div class="small text-muted mt-1"><?= $sc['start_date']?e(date('D j M Y',strtotime((string)$sc['start_date']))):'' ?><?= $sc['start_time']?(' at '.e(date('g:iA',strtotime((string)$sc['start_time'])))):'' ?><?= $sc['loc']?(' · '.e($sc['loc'])):'' ?></div>
      </div>
      <?php if(!empty($error)): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="?r=selfenrol&c=<?= (int)$sc['id'] ?>">
        <input type="hidden" name="c" value="<?= (int)$sc['id'] ?>">
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">First name *</label><input name="first_name" class="form-control form-control-sm" value="<?= e($f['first_name']??'') ?>" required></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Last name *</label><input name="last_name" class="form-control form-control-sm" value="<?= e($f['last_name']??'') ?>" required></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Email *</label><input name="email" type="email" class="form-control form-control-sm" value="<?= e($f['email']??'') ?>" required></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Mobile</label><input name="mobile_phone" class="form-control form-control-sm" value="<?= e($f['mobile_phone']??'') ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Date of birth</label><input type="date" name="date_of_birth" class="form-control form-control-sm" value="<?= e($f['date_of_birth']??'') ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Gender</label><select name="gender" class="form-select form-select-sm"><option value="">—</option><option value="M">Male</option><option value="F">Female</option><option value="X">Other</option></select></div>
          <div class="col-md-9"><label class="form-label small fw-semibold mb-0">USI (Unique Student Identifier)</label><input name="usi_number" class="form-control form-control-sm" value="<?= e($f['usi_number']??'') ?>" placeholder="Required for your certificate — create one free at usi.gov.au"></div>
          <div class="col-md-2"><label class="form-label small fw-semibold mb-0">Street no.</label><input name="street_number" class="form-control form-control-sm"></div>
          <div class="col-md-5"><label class="form-label small fw-semibold mb-0">Street name</label><input name="street_name" class="form-control form-control-sm"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Suburb</label><input name="suburb" class="form-control form-control-sm"></div>
          <div class="col-md-1"><label class="form-label small fw-semibold mb-0">State</label><select name="state" class="form-select form-select-sm"><option value="">—</option><?php foreach($states as $s):?><option><?= $s ?></option><?php endforeach;?></select></div>
          <div class="col-md-1"><label class="form-label small fw-semibold mb-0">P/code</label><input name="postcode" class="form-control form-control-sm"></div>
        </div>
        <div class="mt-2"><div class="small fw-semibold" style="color:#2F1D3A;">A few background questions (for government reporting)</div></div>
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label small mb-0">Born in Australia?</label><select name="born_au" class="form-select form-select-sm"><option value="">—</option><option value="yes">Yes</option><option value="no">No / Other</option></select></div>
          <div class="col-md-6"><label class="form-label small mb-0">English main language at home?</label><select name="eng_main" class="form-select form-select-sm"><option value="">—</option><option value="yes">Yes</option><option value="no">No / Other</option></select></div>
          <div class="col-md-6"><label class="form-label small mb-0">Highest school level</label><select name="highest_school_level" class="form-select form-select-sm"><option value="">—</option><option value="12">Year 12</option><option value="11">Year 11</option><option value="10">Year 10</option><option value="09">Year 9</option><option value="08">Year 8 or below</option><option value="02">Did not attend</option></select></div>
          <div class="col-md-6"><label class="form-label small mb-0">Aboriginal or Torres Strait Islander?</label><select name="indigenous_status" class="form-select form-select-sm"><option value="">—</option><option value="4">No</option><option value="1">Aboriginal</option><option value="2">Torres Strait Islander</option><option value="3">Both</option><option value="9">Prefer not to say</option></select></div>
          <div class="col-md-6"><label class="form-label small mb-0">Employment status</label><select name="labour_force_status" class="form-select form-select-sm"><option value="">—</option><option value="01">Employed full-time</option><option value="02">Employed part-time</option><option value="05">Self-employed</option><option value="07">Unemployed — seeking work</option><option value="09">Not employed — not seeking</option></select></div>
          <div class="col-md-6"><label class="form-label small mb-0">Disability / long-term condition?</label><select name="disability_flag" class="form-select form-select-sm"><option value="">—</option><option value="N">No</option><option value="Y">Yes</option></select></div>
        </div>
        <button class="btn btn-anb w-100 mt-3">Enrol me</button>
        <p class="text-center text-muted small mt-2 mb-0">After enrolling you'll get a Student Portal login by email to complete your online learning and fix any details.</p>
      </form>
    <?php endif; ?>
  </div>
</div>
