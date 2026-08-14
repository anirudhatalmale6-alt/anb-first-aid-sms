<?php
/**
 * Repair the names the migration left as "(unknown)".
 *
 * Nothing on this page changes a student record until the Save button is
 * pressed, and the only rows it saves are the ones the registry confirmed.
 *
 * @var array $cfg      from anb_usi_config()
 * @var array $progress from anb_usi_repair_progress()
 * @var int   $waiting  candidates a fresh scan would look at
 * @var array $rows     from anb_usi_repair_rows()
 */
$running = ($progress['state'] === 'running' && $progress['pending'] > 0);
$pct = $progress['total'] > 0 ? (int)round($progress['done'] / $progress['total'] * 100) : 0;

$matched = array_values(array_filter($rows, fn($r) => $r['state'] === 'matched' && !$r['applied_at']));
$applied = array_values(array_filter($rows, fn($r) => (bool)$r['applied_at']));
$stuck   = array_values(array_filter($rows, fn($r) => in_array($r['state'], ['nomatch','error'], true)));

/** How the name will read once saved. */
$reads = function (array $r): string {
    return (string)$r['new_single'] !== ''
        ? (string)$r['new_single']
        : trim((string)$r['new_first'] . ' ' . (string)$r['new_last']);
};
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0"><i class="bi bi-person-vcard"></i> Fix imported names</h4>
  <a class="btn btn-sm btn-outline-secondary" href="?r=usi_bulk">
    <i class="bi bi-patch-check"></i> Bulk USI verification
  </a>
</div>

<p class="text-muted">
  The move from the old system left a batch of students with the words
  <code>(unknown)</code> where their first name should be, and their whole name sitting in the
  surname box. Those can never be verified, and they would fail an AVETMISS submission too.
  This page works out the right split by <strong>asking the registry</strong> - it tries a
  version, and only keeps it if the registry confirms it. Anything the registry does not confirm
  is left exactly as it is.
</p>

<?php if ($cfg['mode'] !== 'live'): ?>
  <div class="alert alert-warning">
    The USI Registry is set to <strong><?= e($cfg['mode']) ?></strong>. Switch it to
    <strong>Live</strong> on the <a href="?r=usi_registry">USI Registry</a> page first.
  </div>
<?php endif; ?>

<div class="card p-3 mb-3">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <h6 class="fw-bold mb-0">Checking with the registry</h6>
    <span class="badge bg-<?= $running ? 'primary' : ($progress['state']==='done' ? 'success' : 'secondary') ?>"
          id="runState"><?= e($progress['state'] ?: 'not started') ?></span>
  </div>

  <div class="progress mb-3" style="height:22px">
    <div class="progress-bar" id="bar" style="width: <?= $pct ?>%"><?= $pct ?>%</div>
  </div>

  <div class="row text-center g-2 mb-3">
    <div class="col"><div class="fs-4 fw-bold" id="cDone"><?= (int)$progress['done'] ?></div><div class="small text-muted">checked</div></div>
    <div class="col"><div class="fs-4 fw-bold text-success" id="cMatched"><?= (int)$progress['matched'] ?></div><div class="small text-muted">registry confirmed a fix</div></div>
    <div class="col"><div class="fs-4 fw-bold text-secondary" id="cNo"><?= (int)$progress['nomatch'] ?></div><div class="small text-muted">no version matched</div></div>
    <div class="col"><div class="fs-4 fw-bold" id="cPending"><?= (int)$progress['pending'] ?></div><div class="small text-muted">left to check</div></div>
  </div>

  <div class="d-flex gap-2 align-items-center flex-wrap">
    <?php if ($running): ?>
      <button class="btn btn-anb btn-sm" id="btnGo"><i class="bi bi-play-fill"></i> Carry on</button>
      <form method="post" action="?r=usi_repair_stop" class="d-inline">
        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-stop-fill"></i> Stop</button>
      </form>
    <?php else: ?>
      <form method="post" action="?r=usi_repair_start" class="d-inline"
            onsubmit="return confirm('This only asks the registry questions - no student record is changed. Continue?')">
        <button class="btn btn-anb btn-sm" <?= $cfg['mode']==='live' ? '' : 'disabled' ?>>
          <i class="bi bi-search"></i> Check <?= (int)$waiting ?> records
        </button>
      </form>
    <?php endif; ?>
    <span class="small text-muted" id="note"></span>
  </div>

  <?php if ($progress['started_at']): ?>
    <div class="small text-muted mt-2">
      Started <?= e($progress['started_at']) ?><?= $progress['finished_at'] ? ', finished '.e($progress['finished_at']) : '' ?>.
      Nothing has been saved to a student record from this page except where it says "saved" below.
    </div>
  <?php endif; ?>
</div>

<?php if ($matched): ?>
  <div class="card p-3 mb-3">
    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
      <h6 class="fw-bold mb-0 text-success">
        <i class="bi bi-check2-circle"></i> <?= count($matched) ?> the registry confirmed
      </h6>
      <form method="post" action="?r=usi_repair_apply"
            onsubmit="return confirm('Save these <?= count($matched) ?> names to the student records? Only these ones - nothing else is touched.')">
        <button class="btn btn-anb btn-sm"><i class="bi bi-save"></i> Save these <?= count($matched) ?> changes</button>
      </form>
    </div>
    <p class="small text-muted">
      For each of these the registry was asked and said the details now match. Saving writes the
      name to the student record and ticks them as verified, with an entry in the verification log.
    </p>
    <div class="table-responsive" style="max-height:520px;overflow:auto">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light" style="position:sticky;top:0;z-index:1">
          <tr><th>On file now</th><th>Will become</th><th>Date of birth</th><th>USI</th><th>Tried</th></tr>
        </thead>
        <tbody>
        <?php foreach ($matched as $r): ?>
          <tr>
            <td class="small text-muted">
              <a href="?r=student&id=<?= (int)$r['student_id'] ?>">
                <?= e(trim((string)$r['old_first'].' '.(string)$r['old_last'])) ?>
              </a>
            </td>
            <td class="small fw-bold"><?= e($reads($r)) ?>
              <?php if ((string)$r['new_single'] !== ''): ?>
                <span class="badge bg-info text-dark">single name</span>
              <?php endif; ?>
            </td>
            <td class="small text-muted"><?= e((string)$r['date_of_birth']) ?></td>
            <td><code class="small"><?= e((string)$r['usi_number']) ?></code></td>
            <td class="small text-muted"><?= (int)$r['tried'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if ($applied): ?>
  <div class="card p-3 mb-3">
    <h6 class="fw-bold mb-2"><i class="bi bi-check2-all"></i> <?= count($applied) ?> already saved</h6>
    <div class="table-responsive" style="max-height:300px;overflow:auto">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>Was</th><th>Now</th><th>Saved</th></tr></thead>
        <tbody>
        <?php foreach ($applied as $r): ?>
          <tr>
            <td class="small text-muted"><?= e(trim((string)$r['old_first'].' '.(string)$r['old_last'])) ?></td>
            <td class="small">
              <a href="?r=student&id=<?= (int)$r['student_id'] ?>"><?= e($reads($r)) ?></a>
            </td>
            <td class="small text-muted"><?= e((string)$r['applied_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if ($stuck): ?>
  <div class="card p-3 mb-3">
    <h6 class="fw-bold mb-2"><?= count($stuck) ?> the registry could not confirm</h6>
    <p class="small text-muted">
      These are left exactly as they were. Either the name on the registry is different from
      anything on file - a married name, a middle name, a different spelling - or the USI itself
      is wrong. They need a person to look at them.
    </p>
    <div class="table-responsive" style="max-height:420px;overflow:auto">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light" style="position:sticky;top:0;z-index:1">
          <tr><th>On file</th><th>Date of birth</th><th>USI</th><th>What happened</th></tr>
        </thead>
        <tbody>
        <?php foreach ($stuck as $r): ?>
          <tr>
            <td class="small">
              <a href="?r=student&id=<?= (int)$r['student_id'] ?>">
                <?= e(trim((string)$r['old_first'].' '.(string)$r['old_last'])) ?>
              </a>
            </td>
            <td class="small text-muted"><?= e((string)$r['date_of_birth']) ?></td>
            <td><code class="small"><?= e((string)$r['usi_number']) ?></code></td>
            <td class="small text-muted"><?= e((string)$r['note']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<script>
(function () {
  var btn = document.getElementById('btnGo');
  if (!btn) return;

  function paint(p) {
    var pct = p.total > 0 ? Math.round(p.done / p.total * 100) : 0;
    var bar = document.getElementById('bar');
    bar.style.width = pct + '%'; bar.textContent = pct + '%';
    document.getElementById('cDone').textContent = p.done;
    document.getElementById('cMatched').textContent = p.matched;
    document.getElementById('cNo').textContent = p.nomatch;
    document.getElementById('cPending').textContent = p.pending;
    document.getElementById('runState').textContent = p.state;
  }

  function step() {
    // Three at a time, and each one can be up to four registry questions, so
    // this is deliberately slower than the bulk check.
    fetch('?r=usi_repair_step&n=3', {credentials: 'same-origin'})
      .then(function (r) { return r.json(); })
      .then(function (p) {
        if (p.error) { document.getElementById('note').textContent = p.error; return; }
        paint(p);
        if (p.note) document.getElementById('note').textContent = p.note;
        if (p.pending > 0 && p.state === 'running') {
          setTimeout(step, p.ran === 0 ? 2000 : 300);
        } else {
          document.getElementById('note').textContent = 'Finished. Reload to see what it found.';
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Reload';
          btn.onclick = function () { location.reload(); };
        }
      })
      .catch(function () {
        document.getElementById('note').textContent = 'Connection hiccup, retrying...';
        setTimeout(step, 3000);
      });
  }

  function begin() {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Asking the registry...';
    document.getElementById('note').textContent = 'You can leave this page - it carries on from here next time.';
    step();
  }
  btn.onclick = begin;
  <?= $running ? 'begin();' : '' ?>
})();
</script>
