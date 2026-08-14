<?php
/**
 * Renewal reminders.
 *
 * @var array $cfg     from rem_config()
 * @var array $due     from rem_due()
 * @var array $rows    everything with an expiry, for the full picture
 * @var int   $lapsed  certificates already past their date
 * @var ?array $preview result of a dry run, when one was just asked for
 */
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$isErr = !empty($_SESSION['flash_error']); unset($_SESSION['flash_error']);
?>
<div class="topbar">
  <div>
    <h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Renewal Reminders</h4>
    <div class="text-muted small">Email students before their certificate expires</div>
  </div>
  <span class="badge text-bg-<?= $cfg['on'] ? 'success' : 'secondary' ?> fs-6">
    <i class="bi bi-<?= $cfg['on'] ? 'robot' : 'pause-circle' ?>"></i>
    <?= $cfg['on'] ? 'Automation ON' : 'Automation OFF' ?>
  </span>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= $isErr ? 'danger' : 'success' ?>"><?= e($flash) ?></div>
<?php endif; ?>

<?php if (!$cfg['on']): ?>
  <div class="alert alert-warning">
    <strong>Nothing is being sent.</strong>
    Renewal reminders are switched off. Nothing has ever been emailed from this page - the earlier
    version of it displayed "Automation ON" and marked people as "queued", but there was no
    engine behind it and no reminder was ever sent. That is now real, and it is off until you
    turn it on.
  </div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card p-3 h-100">
      <h6 class="fw-bold mb-2">How it works</h6>
      <ul class="small text-muted mb-2" style="padding-left:18px;line-height:1.9;">
        <li>A student is emailed <strong>6 weeks</strong> before their certificate expires, and
            again at <strong>2 weeks</strong>, with a link to your booking page.</li>
        <li>Each of those goes <strong>once</strong>. The date it was sent is recorded against the
            certificate, so nobody is chased twice.</li>
        <li>Only certificates that <strong>have not yet expired</strong> are chased. Someone whose
            certificate lapsed a year ago never gets a "your certificate expires soon" email.</li>
        <li>At most <strong><?= (int)$cfg['cap'] ?> emails per run</strong>, so a mistake costs a
            handful of emails rather than thousands.</li>
      </ul>
      <div class="border rounded p-2 mb-2" style="background:#fbfbfc;">
        <form method="post" action="?r=reminders_booking">
          <label class="form-label small fw-bold mb-1">"Re-book here" link in the email</label>
          <div class="input-group input-group-sm">
            <input class="form-control" name="booking_url" value="<?= e($cfg['booking_url']) ?>">
            <button class="btn btn-outline-secondary">Save</button>
          </div>
          <div class="form-text">
            This is the address students land on when they click the link in the reminder.
            <a href="<?= e($cfg['booking_url']) ?>" target="_blank" rel="noopener">Open it and check</a>
            it goes where you expect.
          </div>
        </form>
      </div>

      <?php if ($lapsed > 0): ?>
        <div class="alert alert-light border small mb-0">
          <strong><?= number_format($lapsed) ?></strong> certificates have <em>already</em> lapsed.
          They are deliberately left out of the automatic run - "your certificate expires soon" is
          not true once it has gone. See <a href="#lapsed">Already expired</a> below.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card p-3 h-100">
      <h6 class="fw-bold mb-2">Due right now</h6>
      <div class="fs-1 fw-bold <?= $due ? 'text-danger' : 'text-muted' ?>"><?= count($due) ?></div>
      <div class="small text-muted mb-3">
        student<?= count($due) === 1 ? '' : 's' ?> would be emailed on the next run
      </div>

      <form method="post" action="?r=reminders_preview" class="d-inline">
        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-eye"></i> Preview - send nothing</button>
      </form>

      <hr>
      <form method="post" action="?r=reminders_toggle"
            onsubmit="return confirm('<?= $cfg['on']
              ? 'Switch renewal reminders OFF? Nothing further will be emailed.'
              : 'Switch renewal reminders ON? Real emails will start going to students on the next run.' ?>')">
        <input type="hidden" name="on" value="<?= $cfg['on'] ? '0' : '1' ?>">
        <button class="btn btn-<?= $cfg['on'] ? 'outline-danger' : 'anb' ?> btn-sm">
          <i class="bi bi-power"></i> Turn automation <?= $cfg['on'] ? 'OFF' : 'ON' ?>
        </button>
      </form>
      <?php if ($cfg['last_run']): ?>
        <div class="small text-muted mt-2">
          Last run <?= e($cfg['last_run']) ?> - <?= (int)$cfg['last_count'] ?> sent.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($preview !== null): ?>
  <div class="card p-3 mb-3">
    <h6 class="fw-bold mb-2">Preview - <?= (int)$preview['considered'] ?> would be emailed</h6>
    <p class="small text-muted">Nothing was sent. This is exactly what a real run would do.</p>
    <?php if (!$preview['lines']): ?>
      <div class="text-muted small">Nobody is due.</div>
    <?php else: ?>
      <div style="max-height:320px;overflow:auto;">
        <ul class="small mb-0" style="padding-left:18px;line-height:1.8;">
          <?php foreach ($preview['lines'] as $l): ?><li><?= e($l) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card p-3">
  <h6 class="fw-bold mb-2">Coming up</h6>
  <p class="small text-muted">
    Certificates expiring in the next 6 weeks. "Sent" means that reminder has gone out and will
    not go again.
  </p>
  <div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead><tr><th>Student</th><th>Course</th><th>Expires</th><th>Countdown</th>
               <th>6-week email</th><th>2-week email</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c): $d = days_until($c['expiry_date']); if ($d === null || $d < 0 || $d > 42) continue; ?>
      <tr>
        <td class="fw-semibold small">
          <a href="?r=student&id=<?= (int)$c['student_id'] ?>" class="text-decoration-none"><?= e($c['first_name'].' '.$c['last_name']) ?></a>
          <div class="text-muted fw-normal"><?= e($c['email'] ?: 'no email on file') ?></div>
        </td>
        <td class="small"><?= e($c['course_title']) ?><div class="text-muted"><?= (int)$c['validity_months'] ?>mo validity</div></td>
        <td class="small"><?= e($c['expiry_date']) ?></td>
        <td>
          <?php if ($d <= 14): ?><span class="badge text-bg-danger">in <?= $d ?>d</span>
          <?php else: ?><span class="badge text-bg-warning">in <?= $d ?>d</span><?php endif; ?>
        </td>
        <td class="small">
          <?php if (!empty($c['reminder_6wk_sent'])): ?>
            <span class="text-success">sent <?= e(substr((string)$c['reminder_6wk_sent'],0,10)) ?></span>
          <?php elseif (empty($c['email'])): ?><span class="text-muted">no email</span>
          <?php else: ?><span class="text-muted">due</span><?php endif; ?>
        </td>
        <td class="small">
          <?php if (!empty($c['reminder_2wk_sent'])): ?>
            <span class="text-success">sent <?= e(substr((string)$c['reminder_2wk_sent'],0,10)) ?></span>
          <?php elseif ($d > 14): ?><span class="text-muted">—</span>
          <?php elseif (empty($c['email'])): ?><span class="text-muted">no email</span>
          <?php else: ?><span class="text-muted">due</span><?php endif; ?>
        </td>
        <td class="text-end">
          <?php if (!empty($c['email'])): ?>
            <form method="post" action="?r=reminders_send_one" class="m-0"
                  onsubmit="return confirm('Email this reminder to <?= e($c['email']) ?> now?')">
              <input type="hidden" name="cert_id" value="<?= (int)$c['id'] ?>">
              <button class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-send"></i> Send now</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<?php
$lapTotal  = array_sum($lapBands);
$bandLabel = REM_LAPSED_BANDS[$lapBand]['label'] ?? '';
?>
<div class="card p-3 mt-3" id="lapsed">
  <h6 class="fw-bold mb-2">Already expired - winning them back</h6>
  <p class="small text-muted mb-3">
    <?= number_format($lapsed) ?> certificates have expired, but that is not the number of people to
    contact - most of those students came back and re-certified since. Counting one row per student,
    and only where their <em>most recent</em> certificate has expired, leaves
    <strong><?= number_format($lapTotal) ?></strong> who are genuinely no longer certified.
    This never runs on its own: one press sends one batch, up to the cap.
  </p>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach (REM_LAPSED_BANDS as $k => $b): ?>
      <a href="?r=reminders&band=<?= e($k) ?>#lapsed"
         class="btn btn-sm <?= $lapBand === $k ? 'btn-anb' : 'btn-outline-secondary' ?>">
        <?= e($b['label']) ?>
        <span class="badge text-bg-light text-dark ms-1"><?= number_format($lapBands[$k]) ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (!$lapTpl): ?>
    <div class="alert alert-warning small">
      <strong>The wording does not exist yet.</strong>
      A lapsed student needs a different email from the renewal reminder - that one says the
      certificate "expires on", which reads badly to somebody whose certificate went a year ago.
      <form method="post" action="?r=reminders_lapsed_template" class="mt-2">
        <button class="btn btn-sm btn-anb">
          <i class="bi bi-file-earmark-plus"></i> Create a "<?= e(REM_LAPSED_TEMPLATE) ?>" template
        </button>
        <span class="text-muted ms-2">Then edit it on the Email Templates page to suit.</span>
      </form>
    </div>
  <?php else: ?>
    <div class="row g-3 mb-3">
      <div class="col-lg-7">
        <div class="border rounded p-2 small bg-light" style="max-height:190px;overflow:auto;">
          <div class="fw-semibold"><?= e((string)$lapTpl['subject']) ?></div>
          <div class="text-muted" style="white-space:pre-wrap;"><?= e((string)$lapTpl['body']) ?></div>
        </div>
        <div class="form-text">
          This is the wording that will go out.
          <a href="?r=emails&edit=<?= (int)$lapTpl['id'] ?>">Edit it</a> before you send anything.
        </div>
      </div>
      <div class="col-lg-5">
        <form method="post" action="?r=reminders_lapsed_preview" class="mb-2">
          <input type="hidden" name="band" value="<?= e($lapBand) ?>">
          <label class="form-label small fw-bold mb-1">How many in this batch</label>
          <div class="input-group input-group-sm mb-2">
            <input class="form-control" type="number" name="cap" value="25" min="1" max="200">
            <button class="btn btn-outline-secondary"><i class="bi bi-eye"></i> Preview - send nothing</button>
          </div>
        </form>
        <form method="post" action="?r=reminders_lapsed_send"
              onsubmit="return confirm('Really email this batch? These are real students and it cannot be undone.')">
          <input type="hidden" name="band" value="<?= e($lapBand) ?>">
          <div class="input-group input-group-sm">
            <input class="form-control" type="number" name="cap" value="25" min="1" max="200">
            <button class="btn btn-anb"><i class="bi bi-send"></i> Send this batch</button>
          </div>
          <div class="form-text">
            Everyone emailed is stamped, so the next batch carries on where this one stopped and
            nobody is contacted twice. Keep it to a hundred or so a day - a few hundred at once
            looks like bulk mail to the spam filters.
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($lapPreview !== null): ?>
    <div class="alert alert-<?= !empty($lapPreview['was_real']) ? 'success' : 'secondary' ?> small">
      <strong>
        <?= !empty($lapPreview['was_real'])
              ? (int)$lapPreview['sent'].' sent'.((int)$lapPreview['failed'] ? ', '.(int)$lapPreview['failed'].' failed' : '')
              : (int)$lapPreview['considered'].' would be emailed - nothing was sent' ?>
      </strong>
      <div style="max-height:220px;overflow:auto;">
        <ul class="mb-0" style="padding-left:18px;line-height:1.7;">
          <?php foreach ($lapPreview['lines'] as $l): ?><li><?= e($l) ?></li><?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php endif; ?>

  <div class="small text-muted mb-2">
    <?= e($bandLabel) ?> - <?= number_format($lapBands[$lapBand]) ?> not contacted yet<?php
      if ($lapBands[$lapBand] > count($lapRows)) echo ', showing the first '.count($lapRows); ?>
  </div>
  <div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead><tr><th>Student</th><th>Last certificate</th><th>Expired</th><th>Lapsed</th></tr></thead>
    <tbody>
    <?php if (!$lapRows): ?>
      <tr><td colspan="4" class="text-muted small">Nobody left in this group.</td></tr>
    <?php else: foreach ($lapRows as $r): ?>
      <tr>
        <td class="fw-semibold small">
          <a href="?r=student&id=<?= (int)$r['student_id'] ?>" class="text-decoration-none">
            <?= e(trim($r['first_name'].' '.$r['last_name'])) ?></a>
          <div class="text-muted fw-normal"><?= e((string)$r['email']) ?></div>
        </td>
        <td class="small"><?= e(trim(trim($r['course_code'].' - '.$r['course_title'], ' -'))) ?></td>
        <td class="small"><?= e(substr((string)$r['expiry_date'],0,10)) ?></td>
        <td class="small text-muted"><?php $dd = (int)$r['days_ago'];
          echo $dd < 45 ? $dd.' day'.($dd === 1 ? '' : 's').' ago'
                        : round($dd/30).' months ago'; ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>
