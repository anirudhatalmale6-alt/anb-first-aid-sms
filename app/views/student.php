<div class="topbar">
  <div>
    <a href="?r=students" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Students</a>
    <h4 class="mb-0 fw-bold" style="color:#2F1D3A;"><?= e(trim($s['salutation'].' '.$s['first_name'].' '.$s['middle_name'].' '.$s['last_name'])) ?></h4>
  </div>
  <div>
    <?php if ($s['usi_number']): ?><span class="badge text-bg-light border fs-6">USI <?= e($s['usi_number']) ?></span>
    <?php else: ?><span class="badge text-bg-warning fs-6">No USI on file</span><?php endif; ?>
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
        'Country of birth'=>$s['country_of_birth'], 'Highest school level'=>$s['highest_school_level'],
      ];
      foreach ($fields as $k=>$v): ?>
        <div class="d-flex justify-content-between border-bottom py-2 small">
          <span class="text-muted"><?= e($k) ?></span><span class="fw-semibold text-end"><?= e($v ?: '—') ?></span>
        </div>
      <?php endforeach; ?>
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
