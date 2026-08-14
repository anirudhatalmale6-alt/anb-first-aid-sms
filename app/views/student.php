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

      <button class="btn btn-sm btn-outline-secondary mt-3" type="button"
              data-bs-toggle="collapse" data-bs-target="#editDetails">
        <i class="bi bi-pencil"></i> Correct these details
      </button>

      <div class="collapse mt-3" id="editDetails">
        <form method="post" action="?r=student_save">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <p class="small text-muted">
            The name and date of birth must match the USI Registry exactly - middle names,
            hyphens and married names all count. A student with one legal name has it in the
            family name box with the first name left <strong>empty</strong>.
          </p>
          <div class="row g-2">
            <div class="col-3">
              <label class="form-label small fw-bold">Title</label>
              <input class="form-control form-control-sm" name="salutation" value="<?= e($s['salutation']) ?>">
            </div>
            <div class="col-9">
              <label class="form-label small fw-bold">First name</label>
              <input class="form-control form-control-sm" name="first_name" value="<?= e($s['first_name']) ?>">
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Middle name</label>
              <input class="form-control form-control-sm" name="middle_name" value="<?= e($s['middle_name']) ?>">
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Family name</label>
              <input class="form-control form-control-sm" name="last_name" value="<?= e($s['last_name']) ?>">
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Date of birth</label>
              <input class="form-control form-control-sm" name="date_of_birth"
                     value="<?= e($s['date_of_birth']) ?>" placeholder="yyyy-mm-dd">
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">USI</label>
              <input class="form-control form-control-sm text-uppercase" name="usi_number"
                     value="<?= e($s['usi_number']) ?>" maxlength="10">
            </div>
            <div class="col-7">
              <label class="form-label small fw-bold">Email</label>
              <input class="form-control form-control-sm" name="email" value="<?= e($s['email']) ?>">
            </div>
            <div class="col-5">
              <label class="form-label small fw-bold">Mobile</label>
              <input class="form-control form-control-sm" name="mobile_phone" value="<?= e($s['mobile_phone']) ?>">
            </div>
          </div>
          <button class="btn btn-anb btn-sm mt-3"><i class="bi bi-save"></i> Save details</button>
          <span class="small text-muted ms-2">Changing a name or USI clears the verified tick.</span>
        </form>
      </div>
    </div>

    <div class="card p-3 mb-3" style="border-left:4px solid <?= !empty($s['usi_verified'])?'#2e7d32':'#E53935' ?>;">
      <h6 class="fw-bold mb-2"><i class="bi bi-patch-check"></i> USI verification</h6>
      <?php if (!empty($s['usi_verified'])): ?>
        <?php $byRegistry = ($s['usi_verified_method'] ?? '') === 'registry'; ?>
        <div class="alert alert-<?= $byRegistry ? 'success' : 'warning' ?> py-2 small mb-2">
          <i class="bi bi-<?= $byRegistry ? 'check-circle-fill' : 'person-check' ?>"></i>
          Verified<?= !empty($s['usi_verified_method'])?' ('.e($s['usi_verified_method']).')':'' ?><?= !empty($s['usi_verified_date'])?' on '.e($s['usi_verified_date']):'' ?>.
          <?php if (!$byRegistry): ?>
            <div class="mt-1">
              This one was ticked by a person, not confirmed by the registry. Running the
              registry check gives you evidence that stands up on its own.
            </div>
          <?php endif; ?>
        </div>
        <?php
          require_once __DIR__ . '/../lib/usi.php';
          $usiCfg = anb_usi_config(db());
        ?>
        <?php if (!$byRegistry && $usiCfg['mode'] === 'live' && $usiCfg['configured'] && !empty($s['usi_number'])): ?>
          <form method="post" action="?r=usi_check" class="mb-2">
            <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
            <button class="btn btn-anb btn-sm"><i class="bi bi-shield-check"></i> Confirm with USI Registry</button>
          </form>
        <?php endif; ?>
        <form method="post" action="?r=usi_verify">
          <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
          <input type="hidden" name="unverify" value="1">
          <input type="hidden" name="reason" value="cleared from the student record">
          <button class="btn btn-sm btn-outline-secondary">Clear verification</button>
        </form>
      <?php else: ?>
        <div class="alert alert-danger py-2 small mb-2"><i class="bi bi-exclamation-triangle-fill"></i> Not verified - a certificate cannot be issued until the USI is verified.</div>

        <?php
          // What the registry actually said last time. Without this the record
          // only says "not verified", which is the least useful half of it -
          // the staff member still has to guess which field is wrong.
          require_once __DIR__ . '/../lib/usi.php';
          $lastRows = $usiLast ? anb_usi_check_rows($usiLast) : [];
        ?>
        <?php if ($usiLast): ?>
          <div class="border rounded p-2 mb-2" style="background:#fff8f8;">
            <div class="fw-bold small mb-1">What the registry said</div>

            <?php
              $status  = (string)($usiLast['status'] ?? '');
              $unknown = strcasecmp($status, 'Invalid') === 0;
            ?>
            <?php if (!empty($usiLast['error'])): ?>
              <div class="small text-danger mb-1"><?= e((string)$usiLast['error']) ?></div>
            <?php else: ?>
              <?php if ($status !== ''): ?>
                <div class="d-flex justify-content-between small border-bottom py-1">
                  <span class="text-muted">USI itself</span>
                  <span class="fw-semibold <?= strcasecmp($status,'Valid')===0 ? 'text-success' : 'text-danger' ?>">
                    <?= $unknown ? 'Not recognised' : e($status) ?>
                  </span>
                </div>
              <?php endif; ?>

              <?php if ($unknown): ?>
                <?php
                  // When the USI does not exist the registry has nothing to compare
                  // against and returns "no match" for every field. Listing those
                  // would send someone off correcting a name that is perfectly fine.
                ?>
                <div class="small text-muted mt-1">
                  There is no such USI in the registry, so the name and date of birth were
                  never compared. Check the USI itself has been typed correctly, or ask the
                  student to look it up at usi.gov.au - this is usually a wrong number rather
                  than a wrong name.
                </div>
              <?php else: ?>
                <?php foreach ($lastRows as $row): ?>
                  <div class="d-flex justify-content-between small border-bottom py-1">
                    <span class="text-muted"><?= e($row['label']) ?></span>
                    <span class="fw-semibold <?= $row['ok'] ? 'text-success' : 'text-danger' ?>">
                      <?= $row['ok'] ? 'Matches' : 'Does NOT match' ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php endif; ?>

            <div class="text-muted mt-1" style="font-size:.7rem;">
              Checked <?= e((string)$usiLast['checked_at']) ?><?= $usiLast['checked_by'] ? ' by '.e((string)$usiLast['checked_by']) : '' ?>.
              <?php if (!$unknown): ?>
                Anything marked "does not match" is different from what the registry holds -
                correct it above and check again.
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
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
            <div class="mb-2">
              <label class="form-label small fw-bold">How was it checked?</label>
              <input class="form-control form-control-sm" name="reason" required
                     placeholder="e.g. checked in the USI Portal, 14 Aug, matched">
              <div class="form-text">
                Recorded against your name in the verification log. A tick with nothing
                behind it is what an auditor will ask about.
              </div>
            </div>
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-patch-check"></i> Mark verified by hand</button>
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
