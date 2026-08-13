<div class="topbar">
  <div>
    <a href="?r=students" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Students</a>
    <h4 class="mb-0 fw-bold" style="color:#2F1D3A;"><?= e(trim($s['salutation'].' '.$s['first_name'].' '.$s['middle_name'].' '.$s['last_name'])) ?></h4>
  </div>
  <div class="text-end">
    <?php if ($s['usi_number']): ?><span class="badge text-bg-light border fs-6">USI <?= e($s['usi_number']) ?></span>
    <?php else: ?><span class="badge text-bg-warning fs-6">No USI on file</span><?php endif; ?>
    <?php if (!empty($s['email'])): ?>
      <a href="?r=student_send_access&id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-danger ms-2" onclick="return confirm('Email portal access (login + password) to <?= e($s['email']) ?>?')"><i class="bi bi-envelope"></i> Send portal access</a>
      <?php if (!empty($s['portal_emailed_at'])): ?><div class="small text-success mt-1">Portal access sent <?= e($s['portal_emailed_at']) ?></div><?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-3">Personal &amp; AVETMISS details</h6>
      <?php
      $fields = [
        'Date of birth'=>$s['date_of_birth'], 'Gender'=>$s['gender'],
        'Email'=>$s['email'], 'Mobile'=>$s['mobile_phone'],
        'Address'=>trim($s['street_number'].' '.$s['street_name'].', '.$s['suburb'].' '.$s['state'].' '.$s['postcode']),
        'Country of birth'=>anb_demo_label('country',$s['country_of_birth']),
        'Language at home'=>anb_demo_label('lang',$s['main_language']),
        'Highest school level'=>anb_demo_label('school',$s['highest_school_level']),
        'Indigenous status'=>anb_demo_label('indig',$s['indigenous_status']),
        'Employment status'=>anb_demo_label('labour',$s['labour_force_status']),
        'Disability'=>anb_demo_label('disab',$s['disability_flag']),
      ];
      foreach ($fields as $k=>$v): ?>
        <div class="d-flex justify-content-between border-bottom py-2 small">
          <span class="text-muted"><?= e($k) ?></span><span class="fw-semibold text-end"><?= e($v ?: '—') ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card p-3 mb-3" style="border-left:4px solid <?= !empty($s['usi_verified'])?'#2e7d32':'#E53935' ?>;">
      <h6 class="fw-bold mb-2"><i class="bi bi-patch-check"></i> USI verification</h6>
      <?php if (!empty($s['usi_verified'])): ?>
        <div class="alert alert-success py-2 small mb-2"><i class="bi bi-check-circle-fill"></i> Verified<?= !empty($s['usi_verified_method'])?' ('.e($s['usi_verified_method']).')':'' ?><?= !empty($s['usi_verified_date'])?' on '.e($s['usi_verified_date']):'' ?>.</div>
        <form method="post" action="?r=usi_verify">
          <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
          <input type="hidden" name="unverify" value="1">
          <button class="btn btn-sm btn-outline-secondary">Clear verification</button>
        </form>
      <?php else: ?>
        <div class="alert alert-danger py-2 small mb-2"><i class="bi bi-exclamation-triangle-fill"></i> Not verified - a certificate cannot be issued until the USI is verified.</div>
        <?php if (empty($s['usi_number'])): ?>
          <div class="small text-muted">No USI on file yet - add the student's USI first, then verify.</div>
        <?php else: ?>
          <div class="small bg-light border rounded p-2 mb-2">
            <div><span class="text-muted">USI:</span> <strong><?= e($s['usi_number']) ?></strong></div>
            <div><span class="text-muted">Name:</span> <strong><?= e(trim($s['first_name'].' '.$s['last_name'])) ?></strong></div>
            <div><span class="text-muted">DOB:</span> <strong><?= e($s['date_of_birth'] ?: '—') ?></strong></div>
          </div>
          <?php
            // The one-click check only appears when the registry connection is
            // live - in test mode the only students that exist are the
            // government's fake ones.
            require_once __DIR__ . '/../lib/usi.php';
            $usiCfg = anb_usi_config(db());
            $usiFormat = anb_usi_format_problem((string)$s['usi_number']);
          ?>
          <?php if ($usiFormat !== ''): ?>
            <div class="alert alert-warning py-2 small mb-2"><i class="bi bi-exclamation-triangle"></i> <?= e($usiFormat) ?></div>
          <?php endif; ?>
          <?php if ($usiCfg['mode'] === 'live' && $usiCfg['configured'] && $usiFormat === '' && !empty($s['date_of_birth'])): ?>
            <form method="post" action="?r=usi_check" class="mb-2">
              <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
              <button class="btn btn-anb btn-sm"><i class="bi bi-shield-check"></i> Verify with USI Registry</button>
            </form>
            <div class="small text-muted mb-2">Or check it by hand in the portal and tick below.</div>
          <?php else: ?>
            <div class="small mb-2">Check these details in your USI Organisation Portal (Verify USI), then tick below:</div>
          <?php endif; ?>
          <a href="https://www.usi.gov.au" target="_blank" rel="noopener" class="btn btn-sm btn-outline-danger mb-2"><i class="bi bi-box-arrow-up-right"></i> Open USI portal</a>
          <form method="post" action="?r=usi_verify">
            <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
            <input type="hidden" name="method" value="manual">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="usiconfirm" required>
              <label class="form-check-label small" for="usiconfirm">I have verified this USI via the USI portal and it matches the student's details.</label>
            </div>
            <button class="btn btn-anb btn-sm"><i class="bi bi-patch-check"></i> Mark USI verified</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-2">Enrolments</h6>
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Course</th><th>Date</th><th>Status</th><th>Payment</th></tr></thead>
        <tbody>
        <?php foreach ($enrolments as $en): ?>
          <tr>
            <td class="small fw-semibold"><?= e($en['course_code']) ?><div class="text-muted fw-normal"><?= e($en['course_title']) ?></div></td>
            <td class="small"><?= e($en['start_date']) ?></td>
            <td><?= status_badge($en['status']) ?></td>
            <td><span class="badge text-bg-<?= $en['payment_status']==='paid'?'success':($en['payment_status']==='part'?'warning':'secondary') ?>"><?= ucfirst($en['payment_status']) ?></span></td>
          </tr>
        <?php endforeach; if (!$enrolments): ?><tr><td colspan="4" class="text-muted small">No enrolments.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card p-3">
      <h6 class="fw-bold mb-2">Certificates</h6>
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Number</th><th>Course</th><th>Issued</th><th>Expires</th></tr></thead>
        <tbody>
        <?php foreach ($certs as $c): $d = days_until($c['expiry_date']); ?>
          <tr>
            <td class="small fw-semibold"><?= e($c['certificate_number']) ?></td>
            <td class="small"><?= e($c['course_title']) ?></td>
            <td class="small"><?= e($c['issue_date']) ?></td>
            <td class="small"><?= e($c['expiry_date']) ?>
              <?php if ($d !== null && $d < 0): ?><span class="badge text-bg-danger">Expired</span>
              <?php elseif ($d !== null && $d <= 60): ?><span class="badge text-bg-warning">Soon</span><?php endif; ?></td>
          </tr>
        <?php endforeach; if (!$certs): ?><tr><td colspan="4" class="text-muted small">No certificates issued yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
