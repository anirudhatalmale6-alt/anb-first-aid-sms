<?php
/**
 * Compose an email to one student from a saved template.
 *
 * @var array  $s         the student
 * @var array  $templates from se_templates()
 * @var array  $vars      merge values for this student
 * @var array  $history   from se_history()
 * @var string $pick      the template chosen, if any
 * @var array  $draft     merged subject/body ready to edit
 */
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$isErr = !empty($_SESSION['flash_error']); unset($_SESSION['flash_error']);
$who   = trim($s['first_name'].' '.$s['last_name']);
$unfilled = se_unfilled($draft['subject'].' '.$draft['body']);
?>
<div class="topbar">
  <div>
    <a href="?r=student&id=<?= (int)$s['id'] ?>" class="text-muted small text-decoration-none">
      <i class="bi bi-arrow-left"></i> Back to <?= e($who) ?>
    </a>
    <h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Email <?= e($who) ?></h4>
  </div>
  <a href="?r=emails" class="btn btn-sm btn-outline-secondary">Manage templates</a>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= $isErr ? 'danger' : 'success' ?>"><?= e($flash) ?></div>
<?php endif; ?>

<?php if (empty($s['email'])): ?>
  <div class="alert alert-danger">
    <?= e($who) ?> has no email address on file, so nothing can be sent.
    <a href="?r=student&id=<?= (int)$s['id'] ?>&tab=edit">Add one on their record</a>.
  </div>
<?php else: ?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-2">1. Pick a template</h6>
      <?php if (!$templates): ?>
        <div class="text-muted small">
          No templates saved yet. Create one under <a href="?r=emails">Email Templates</a> -
          a "Group Cancellation" for instance - and it will appear here.
        </div>
      <?php else: ?>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($templates as $t): ?>
            <a class="btn btn-sm <?= $pick === $t['name'] ? 'btn-anb' : 'btn-outline-secondary' ?>"
               href="?r=student_email&id=<?= (int)$s['id'] ?>&tpl=<?= urlencode((string)$t['name']) ?>">
              <?= e((string)$t['name']) ?>
            </a>
          <?php endforeach; ?>
        </div>
        <div class="form-text mt-2">
          Choosing one fills the message below with this student's details. You can edit it
          before sending - what you send is exactly what is in the boxes.
        </div>
      <?php endif; ?>
    </div>

    <div class="card p-3">
      <h6 class="fw-bold mb-2">2. Check it, then send</h6>

      <?php if ($unfilled): ?>
        <div class="alert alert-warning py-2 small">
          <strong>These did not fill in:</strong> <?= e(implode(' ', $unfilled)) ?><br>
          That usually means this student has no class or certificate for that detail. Type over
          them or delete them - do not send the email with <?= e($unfilled[0]) ?> still in it.
        </div>
      <?php endif; ?>

      <form method="post" action="?r=student_email_send"
            onsubmit="return confirm('Send this email to <?= e($s['email']) ?>?')">
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <input type="hidden" name="template" value="<?= e($pick) ?>">

        <div class="mb-2">
          <label class="form-label small fw-bold mb-1">To</label>
          <input class="form-control form-control-sm" value="<?= e($s['email']) ?>" disabled>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold mb-1">Subject</label>
          <input class="form-control" name="subject" required value="<?= e($draft['subject']) ?>">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold mb-1">Message</label>
          <textarea class="form-control" name="body" rows="12" required><?= e($draft['body']) ?></textarea>
          <div class="form-text">
            Plain text. Any web address you type becomes a clickable link in the email.
          </div>
        </div>
        <button class="btn btn-anb"><i class="bi bi-send"></i> Send to <?= e($who) ?></button>
        <a href="?r=student&id=<?= (int)$s['id'] ?>" class="btn btn-link">Cancel</a>
      </form>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-2">What will fill in</h6>
      <p class="small text-muted">
        Type any of these into a template and it is replaced with this student's details.
      </p>
      <div style="max-height:260px;overflow:auto;">
        <table class="table table-sm mb-0">
          <tbody>
          <?php foreach ($vars as $k => $v): ?>
            <tr>
              <td class="small text-muted" style="white-space:nowrap;">{<?= e($k) ?>}</td>
              <td class="small <?= trim((string)$v) === '' ? 'text-danger' : '' ?>">
                <?= trim((string)$v) === '' ? 'nothing on file' : e((string)$v) ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card p-3">
      <h6 class="fw-bold mb-2">Already sent</h6>
      <?php if (!$history): ?>
        <div class="text-muted small">No emails sent to this student from here yet.</div>
      <?php else: foreach ($history as $h): ?>
        <div class="border-bottom py-2">
          <div class="small fw-semibold">
            <?= e((string)$h['subject']) ?>
            <?php if (!(int)$h['ok']): ?><span class="badge text-bg-danger">failed</span><?php endif; ?>
          </div>
          <div class="text-muted" style="font-size:.72rem;">
            <?= e((string)$h['sent_at']) ?> · <?= e((string)$h['sent_by']) ?>
            <?= $h['template'] ? ' · '.e((string)$h['template']) : '' ?>
          </div>
          <?php if (!(int)$h['ok'] && $h['error']): ?>
            <div class="small text-danger"><?= e((string)$h['error']) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<?php endif; ?>
