<?php
/**
 * One screen to see why a USI failed and put it right.
 *
 * The point is that the three things you need are together: what we sent, what
 * the registry said about each field, and the boxes to change it. Before this
 * you had to read the reason on one screen, edit on another, and go back to the
 * class to check it worked.
 *
 * @var array  $s        the student
 * @var ?array $last     latest usi_verify_log row, or null
 * @var array  $rows     from anb_usi_check_rows()
 * @var int    $schedule schedule to return to, 0 for none
 * @var array  $cfg      from anb_usi_config()
 */
$status  = (string)($last['status'] ?? '');
$unknown = strcasecmp($status, 'Invalid') === 0;
$backUrl = $schedule ? '?r=pipeline&schedule_id='.(int)$schedule : '?r=student&id='.(int)$s['id'];

/** What we sent last time, falling back to what is on the record now. */
$sentFirst  = $last['sent_first']  ?? null;
$sentFamily = $last['sent_family'] ?? null;
$sentDob    = $last['sent_dob']    ?? null;
$haveSent   = ($sentFirst !== null || $sentFamily !== null || $sentDob !== null);
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <a href="<?= e($backUrl) ?>" class="text-muted small text-decoration-none">
      <i class="bi bi-arrow-left"></i> <?= $schedule ? 'Back to the class' : 'Back to the student' ?>
    </a>
    <h4 class="mb-0 fw-bold" style="color:#2F1D3A;">
      Fix USI — <?= e(trim($s['first_name'].' '.$s['last_name'])) ?>
    </h4>
  </div>
  <?php if (!empty($s['usi_verified'])): ?>
    <span class="badge bg-success fs-6">Verified</span>
  <?php else: ?>
    <span class="badge bg-danger fs-6">Not verified</span>
  <?php endif; ?>
</div>

<?php if (!empty($_SESSION['flash'])):
        $isErr = !empty($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
  <div class="alert alert-<?= $isErr ? 'danger' : 'success' ?>"><?= e($_SESSION['flash']) ?></div>
  <?php unset($_SESSION['flash']); endif; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-2">What happened last time</h6>

      <?php if (!$last): ?>
        <p class="text-muted small mb-0">
          This student has never been checked against the registry. Fill in the details on the
          right and press Save &amp; check.
        </p>
      <?php elseif (!empty($last['error'])): ?>
        <div class="alert alert-danger py-2 small mb-0"><?= e((string)$last['error']) ?></div>
      <?php else: ?>
        <table class="table table-sm align-middle mb-2">
          <thead>
            <tr class="small text-muted">
              <th></th><th>We sent</th><th class="text-end">Registry said</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="small text-muted">USI</td>
              <td><code class="small"><?= e((string)$last['usi']) ?></code></td>
              <td class="text-end small fw-semibold <?= strcasecmp($status,'Valid')===0 ? 'text-success' : 'text-danger' ?>">
                <?= $unknown ? 'No such USI' : e($status) ?>
              </td>
            </tr>
            <?php if (!$unknown): ?>
              <?php
                $sentMap = ['First name' => $sentFirst, 'Family name' => $sentFamily, 'Date of birth' => $sentDob];
              ?>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td class="small text-muted"><?= e($row['label']) ?></td>
                  <td class="small">
                    <?php $v = $sentMap[$row['label']] ?? null; ?>
                    <?php if ($v === null): ?>
                      <span class="text-muted">—</span>
                    <?php elseif ($v === ''): ?>
                      <span class="text-muted fst-italic">(blank)</span>
                    <?php else: ?>
                      <?= e((string)$v) ?>
                    <?php endif; ?>
                  </td>
                  <td class="text-end small fw-semibold <?= $row['ok'] ? 'text-success' : 'text-danger' ?>">
                    <?= $row['ok'] ? 'Matches' : 'Does NOT match' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <?php if ($unknown): ?>
          <div class="alert alert-warning py-2 small mb-0">
            There is no such USI in the registry, so the name and date of birth were never
            compared. It is the number that is wrong, not the name. Check it against the
            student's paperwork, or ask them to look it up at
            <a href="https://www.usi.gov.au" target="_blank" rel="noopener">usi.gov.au</a>.
          </div>
        <?php elseif (strcasecmp($status,'Deactivated') === 0): ?>
          <div class="alert alert-warning py-2 small mb-0">
            This USI has been deactivated. Nothing here will fix that — the student has to
            contact the USI Office themselves.
          </div>
        <?php elseif (!$haveSent): ?>
          <div class="small text-muted">
            This check ran before the system started recording what it sent, so the "we sent"
            column is blank. Press Save &amp; check and it will be filled in.
          </div>
        <?php endif; ?>

        <div class="text-muted mt-2" style="font-size:.72rem;">
          Checked <?= e((string)$last['checked_at']) ?><?= $last['checked_by'] ? ' by '.e((string)$last['checked_by']) : '' ?>.
        </div>
      <?php endif; ?>
    </div>

    <div class="card p-3">
      <h6 class="fw-bold mb-2">If it still will not match</h6>
      <ul class="small text-muted mb-0" style="padding-left:18px;line-height:1.8;">
        <li>The registry holds the name on the student's ID, not what they go by.</li>
        <li>Middle names, hyphens and married names all count.</li>
        <li>Some students are recorded with <strong>one name only</strong> — put it in the
            family name box and leave the first name empty.</li>
        <li>If nothing works, ask the student to check their own details at usi.gov.au.</li>
      </ul>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card p-3">
      <h6 class="fw-bold mb-2">Correct it and check again</h6>
      <p class="small text-muted">
        These go straight to the registry when you press the button. Nothing else on the
        student record is touched.
      </p>
      <form method="post" action="?r=usi_fix_save">
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <input type="hidden" name="schedule_id" value="<?= (int)$schedule ?>">
        <div class="mb-2">
          <label class="form-label small fw-bold">First name</label>
          <input class="form-control" name="first_name" value="<?= e($s['first_name']) ?>">
          <div class="form-text">Leave empty if the student has only one legal name.</div>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold">Family name</label>
          <input class="form-control" name="last_name" value="<?= e($s['last_name']) ?>">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold">Date of birth</label>
          <input class="form-control" name="date_of_birth" value="<?= e($s['date_of_birth']) ?>"
                 placeholder="yyyy-mm-dd">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">USI</label>
          <input class="form-control text-uppercase" name="usi_number"
                 value="<?= e($s['usi_number']) ?>" maxlength="10">
        </div>

        <?php if ($cfg['mode'] === 'live' && $cfg['configured']): ?>
          <button class="btn btn-anb"><i class="bi bi-shield-check"></i> Save &amp; check with the registry</button>
        <?php else: ?>
          <button class="btn btn-anb" disabled>Save &amp; check with the registry</button>
          <div class="form-text text-danger">The USI Registry is not switched on.</div>
        <?php endif; ?>
        <a href="<?= e($backUrl) ?>" class="btn btn-link">Cancel</a>
      </form>
    </div>
  </div>
</div>
