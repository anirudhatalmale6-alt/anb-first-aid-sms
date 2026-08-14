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
      <?php if ($lapsed > 0): ?>
        <div class="alert alert-light border small mb-0">
          <strong><?= number_format($lapsed) ?></strong> students have a certificate that has
          <em>already</em> lapsed. They are deliberately left out of this - re-engaging them is a
          different message and your decision, not something the system should do on its own.
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
