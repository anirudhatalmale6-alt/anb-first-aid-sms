<?php require_once __DIR__.'/../lib/avetmiss.php';
$states=['NSW','VIC','QLD','SA','WA','TAS','NT','ACT'];
$sel=function($a,$b){ return $a===$b?'selected':''; };
// Without this a student changes their password and the page just reloads
// looking identical - no way to tell whether it worked.
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$flashErr = !empty($_SESSION['flash_error']); unset($_SESSION['flash_error']);
?>
<div style="background:#f4f5f7;min-height:100vh;">
  <div style="background:linear-gradient(135deg,#2F1D3A,#4a2d5c);color:#fff;padding:14px 28px;display:flex;justify-content:space-between;align-items:center;">
    <div style="display:flex;align-items:center;gap:12px;">
      <img src="assets/logo.png" alt="A&amp;B First Aid Training" style="height:40px;width:auto;">
      <div style="border-left:1px solid rgba(255,255,255,.25);padding-left:12px;font-size:.82rem;letter-spacing:.06em;opacity:.85;">MY DETAILS</div>
    </div>
    <a href="?r=my" style="color:#ffb3b0;text-decoration:none;"><i class="bi bi-arrow-left"></i> Back to my learning</a>
  </div>
  <?php if ($flash): ?>
    <div style="max-width:960px;margin:14px auto 0;padding:0 16px;">
      <div class="alert alert-<?= $flashErr ? 'danger' : 'success' ?> py-2 mb-0"><?= e($flash) ?></div>
    </div>
  <?php endif; ?>

  <div style="max-width:820px;margin:0 auto;padding:24px 20px;">
    <div class="card p-3 mb-3" style="border-left:4px solid #E53935;">
      <h5 class="fw-bold mb-1" style="color:#2F1D3A;">My Details</h5>
      <div class="text-muted small">Please complete and keep your details up to date. This information is used for your enrolment and certificate, so please make sure it is correct.</div>
    </div>

    <form method="post" action="?r=my_details_save">
      <div class="card p-3 mb-3">
        <h6 class="fw-bold mb-3">Personal</h6>
        <div class="row g-2">
          <div class="col-md-2"><label class="form-label small fw-semibold mb-0">Title</label><input name="salutation" class="form-control form-control-sm" value="<?= e($me['salutation']) ?>"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold mb-0">First name</label><input name="first_name" class="form-control form-control-sm" value="<?= e($me['first_name']) ?>" required></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Middle name</label><input name="middle_name" class="form-control form-control-sm" value="<?= e($me['middle_name']) ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Last name</label><input name="last_name" class="form-control form-control-sm" value="<?= e($me['last_name']) ?>" required></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Date of birth</label><input type="date" name="date_of_birth" class="form-control form-control-sm" value="<?= e($me['date_of_birth']) ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Gender</label><select name="gender" class="form-select form-select-sm"><option value="">—</option><option value="M" <?= $sel($me['gender'],'M') ?>>Male</option><option value="F" <?= $sel($me['gender'],'F') ?>>Female</option><option value="X" <?= $sel($me['gender'],'X') ?>>Other</option></select></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Mobile</label><input name="mobile_phone" class="form-control form-control-sm" value="<?= e($me['mobile_phone']) ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Email (login)</label><input class="form-control form-control-sm" value="<?= e($me['email']) ?>" disabled><div class="small text-muted">Contact us to change your email.</div></div>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <h6 class="fw-bold mb-3">Address</h6>
        <div class="row g-2">
          <div class="col-md-2"><label class="form-label small fw-semibold mb-0">Unit/Flat</label><input name="unit_flat" class="form-control form-control-sm" value="<?= e($me['unit_flat']) ?>"></div>
          <div class="col-md-2"><label class="form-label small fw-semibold mb-0">Street no.</label><input name="street_number" class="form-control form-control-sm" value="<?= e($me['street_number']) ?>"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Street name</label><input name="street_name" class="form-control form-control-sm" value="<?= e($me['street_name']) ?>"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Suburb</label><input name="suburb" class="form-control form-control-sm" value="<?= e($me['suburb']) ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">State</label><select name="state" class="form-select form-select-sm"><option value="">—</option><?php foreach($states as $st): ?><option <?= $sel($me['state'],$st) ?>><?= $st ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Postcode</label><input name="postcode" class="form-control form-control-sm" value="<?= e($me['postcode']) ?>"></div>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <h6 class="fw-bold mb-2">Unique Student Identifier (USI)</h6>
        <div class="row g-2 align-items-end">
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">USI</label><input name="usi_number" class="form-control form-control-sm" value="<?= e($me['usi_number']) ?>"></div>
          <div class="col-md-6"><div class="small text-muted">We can't issue your certificate without a valid USI. Don't have one? Create it free at usi.gov.au</div></div>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <h6 class="fw-bold mb-3">Background (for government reporting)</h6>
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Country of birth</label>
            <select name="country_of_birth" class="form-select form-select-sm"><option value="">—</option>
              <?php foreach (avetmiss_country_options() as $cCode=>$cName): ?>
                <option value="<?= e($cCode) ?>" <?= $sel($me['country_of_birth'],$cCode) ?>><?= e($cName) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Main language spoken at home</label>
            <select name="main_language" class="form-select form-select-sm"><option value="">—</option>
              <?php foreach (avetmiss_language_options() as $lCode=>$lName): ?>
                <option value="<?= e($lCode) ?>" <?= $sel($me['main_language'],$lCode) ?>><?= e($lName) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Highest school level completed</label>
            <select name="highest_school_level" class="form-select form-select-sm"><option value="">—</option>
              <option value="12" <?= $sel($me['highest_school_level'],'12') ?>>Year 12</option>
              <option value="11" <?= $sel($me['highest_school_level'],'11') ?>>Year 11</option>
              <option value="10" <?= $sel($me['highest_school_level'],'10') ?>>Year 10</option>
              <option value="09" <?= $sel($me['highest_school_level'],'09') ?>>Year 9</option>
              <option value="08" <?= $sel($me['highest_school_level'],'08') ?>>Year 8 or below</option>
              <option value="02" <?= $sel($me['highest_school_level'],'02') ?>>Did not attend school</option>
            </select></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Are you of Aboriginal or Torres Strait Islander origin?</label>
            <select name="indigenous_status" class="form-select form-select-sm"><option value="">—</option>
              <option value="4" <?= $sel($me['indigenous_status'],'4') ?>>No</option>
              <option value="1" <?= $sel($me['indigenous_status'],'1') ?>>Aboriginal</option>
              <option value="2" <?= $sel($me['indigenous_status'],'2') ?>>Torres Strait Islander</option>
              <option value="3" <?= $sel($me['indigenous_status'],'3') ?>>Both</option>
              <option value="9" <?= $sel($me['indigenous_status'],'9') ?>>Prefer not to say</option>
            </select></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Employment status</label>
            <select name="labour_force_status" class="form-select form-select-sm"><option value="">—</option>
              <option value="01" <?= $sel($me['labour_force_status'],'01') ?>>Employed full-time</option>
              <option value="02" <?= $sel($me['labour_force_status'],'02') ?>>Employed part-time</option>
              <option value="05" <?= $sel($me['labour_force_status'],'05') ?>>Self-employed</option>
              <option value="07" <?= $sel($me['labour_force_status'],'07') ?>>Unemployed — seeking work</option>
              <option value="09" <?= $sel($me['labour_force_status'],'09') ?>>Not employed — not seeking</option>
            </select></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Do you have a disability, impairment or long-term condition?</label>
            <select name="disability_flag" class="form-select form-select-sm"><option value="">—</option><option value="N" <?= $sel($me['disability_flag'],'N') ?>>No</option><option value="Y" <?= $sel($me['disability_flag'],'Y') ?>>Yes</option></select></div>
        </div>
        <div class="small text-muted mt-2">If you were not born in Australia or English isn't your main language at home, just let us know and we'll help complete that with you.</div>
      </div>

      <button class="btn btn-anb w-100"><i class="bi bi-save"></i> Save my details</button>
    </form>

    <div class="card p-3 mt-4">
      <h6 class="fw-bold mb-2"><i class="bi bi-key"></i> Change my password</h6>
      <p class="small text-muted">
        If you were emailed a password when you enrolled, you can change it to something you will
        remember. If you have forgotten it, log out and use "Forgotten your password?" on the
        login page instead.
      </p>
      <form method="post" action="?r=my_password">
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label small fw-semibold mb-0">Current password</label>
            <input type="password" class="form-control form-control-sm" name="current" required
                   autocomplete="current-password">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold mb-0">New password</label>
            <input type="password" class="form-control form-control-sm" name="new" required minlength="6"
                   placeholder="at least 6 characters" autocomplete="new-password">
          </div>
        </div>
        <button class="btn btn-outline-secondary btn-sm mt-3">Change my password</button>
      </form>
    </div>
    <div class="py-4"></div>
  </div>
</div>
