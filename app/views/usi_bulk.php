<?php
/**
 * Bulk USI verification - work through the backlog of unverified USIs.
 *
 * @var array $cfg      from anb_usi_config()
 * @var array $progress from anb_usi_bulk_progress()
 * @var int   $waiting  students with a USI that is still unverified
 * @var array $problems the ones that did not verify
 * @var array $buckets  problem category => count
 */
$running = ($progress['state'] === 'running' && $progress['pending'] > 0);
$pct = $progress['total'] > 0 ? (int)round($progress['done'] / $progress['total'] * 100) : 0;
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0"><i class="bi bi-patch-check"></i> Bulk USI verification</h4>
  <a class="btn btn-sm btn-outline-secondary" href="?r=usi_registry">
    <i class="bi bi-gear"></i> USI Registry settings
  </a>
</div>

<p class="text-muted">
  Checks every student who has a USI on file but has not been verified yet, one at a time,
  against the Commonwealth registry. Each check takes about a second, so a large backlog takes
  a while - but you can close this page and come back, and it will carry on from where it stopped.
</p>

<?php if ($cfg['mode'] !== 'live'): ?>
  <div class="alert alert-warning">
    The USI Registry is set to <strong><?= e($cfg['mode']) ?></strong>. Switch it to
    <strong>Live</strong> on the <a href="?r=usi_registry">USI Registry</a> page before running this.
  </div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-7">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-bold mb-0">Progress</h6>
        <span class="badge bg-<?= $running ? 'primary' : ($progress['state']==='done' ? 'success' : 'secondary') ?>"
              id="runState"><?= e($progress['state']) ?></span>
      </div>

      <div class="progress mb-3" style="height:22px">
        <div class="progress-bar" id="bar" role="progressbar"
             style="width: <?= $pct ?>%"><?= $pct ?>%</div>
      </div>

      <div class="row text-center g-2 mb-3">
        <div class="col"><div class="fs-4 fw-bold" id="cDone"><?= (int)$progress['done'] ?></div><div class="small text-muted">checked</div></div>
        <div class="col"><div class="fs-4 fw-bold text-success" id="cVerified"><?= (int)$progress['verified'] ?></div><div class="small text-muted">verified</div></div>
        <div class="col"><div class="fs-4 fw-bold text-danger" id="cFailed"><?= (int)$progress['failed'] ?></div><div class="small text-muted">need attention</div></div>
        <div class="col"><div class="fs-4 fw-bold" id="cPending"><?= (int)$progress['pending'] ?></div><div class="small text-muted">left to do</div></div>
      </div>

      <div class="d-flex gap-2 align-items-center">
        <?php if ($running): ?>
          <button class="btn btn-anb btn-sm" id="btnGo"><i class="bi bi-play-fill"></i> Continue the run</button>
          <form method="post" action="?r=usi_bulk_stop" class="d-inline">
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-stop-fill"></i> Stop</button>
          </form>
        <?php else: ?>
          <form method="post" action="?r=usi_bulk_start" class="d-inline"
                onsubmit="return confirm('This will check <?= (int)$waiting ?> students against the USI Registry. It can be stopped at any time. Continue?')">
            <button class="btn btn-anb btn-sm" <?= $cfg['mode']==='live' ? '' : 'disabled' ?>>
              <i class="bi bi-play-fill"></i> Check <?= (int)$waiting ?> students
            </button>
          </form>
        <?php endif; ?>
        <span class="small text-muted" id="note"></span>
      </div>

      <?php if ($progress['started_at']): ?>
        <div class="small text-muted mt-2">
          Started <?= e($progress['started_at']) ?><?= $progress['finished_at'] ? ', finished '.e($progress['finished_at']) : '' ?>.
        </div>
      <?php endif; ?>
    </div>

    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-2">Live results</h6>
      <div class="table-responsive" style="max-height:320px;overflow:auto">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Student</th><th>USI</th><th>Result</th></tr></thead>
          <tbody id="liveRows">
            <tr id="liveEmpty"><td colspan="3" class="text-muted small">Nothing checked in this session yet.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-bold mb-0">Needs attention</h6>
        <?php if ($problems): ?>
          <a class="btn btn-sm btn-outline-danger" href="?r=usi_bulk_csv">
            <i class="bi bi-download"></i> Download CSV
          </a>
        <?php endif; ?>
      </div>

      <?php if (!$problems): ?>
        <div class="text-muted small">Nothing to fix yet.</div>
      <?php else: ?>
        <p class="small text-muted">
          These are the records that would fail an AVETMISS submission or an audit. Most are
          fixable by correcting the spelling on the student record and checking again.
        </p>
        <ul class="list-group list-group-flush mb-3">
          <?php foreach ($buckets as $label => $n): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <span class="small"><?= e($label) ?></span>
              <span class="badge bg-danger rounded-pill"><?= (int)$n ?></span>
            </li>
          <?php endforeach; ?>
        </ul>

      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($problems): ?>
  <!-- Full width: the problem text is a sentence, so it needs the room to read
       as one rather than one word per line down a narrow column. -->
  <div class="card p-3 mb-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h6 class="fw-bold mb-0">The <?= count($problems) ?> records to fix</h6>
      <a class="btn btn-sm btn-outline-danger" href="?r=usi_bulk_csv">
        <i class="bi bi-download"></i> Download CSV
      </a>
    </div>
    <div class="table-responsive" style="max-height:560px;overflow:auto">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light" style="position:sticky;top:0;z-index:1">
          <tr><th style="width:22%">Student</th><th style="width:12%">Date of birth</th>
              <th style="width:14%">USI</th><th>What the registry said</th></tr>
        </thead>
        <tbody>
        <?php foreach ($problems as $p): ?>
          <tr>
            <td class="small">
              <a href="?r=student&id=<?= (int)$p['student_id'] ?>">
                <?= e(trim(($p['first_name'] ?? '').' '.($p['last_name'] ?? ''))) ?: 'student #'.(int)$p['student_id'] ?>
              </a>
            </td>
            <td class="small text-muted"><?= e((string)$p['date_of_birth']) ?></td>
            <td><code class="small"><?= e((string)$p['usi_number']) ?></code></td>
            <td class="small text-muted"><?= e((string)$p['reason']) ?></td>
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
  var stop = false;

  function row(r) {
    var t = document.getElementById('liveRows');
    var empty = document.getElementById('liveEmpty');
    if (empty) empty.remove();
    var tr = document.createElement('tr');
    tr.innerHTML = '<td class="small"><a href="?r=student&id=' + r.id + '">' + esc(r.name) + '</a></td>' +
                   '<td><code class="small">' + esc(r.usi) + '</code></td>' +
                   '<td class="small">' + (r.verified
                      ? '<span class="badge bg-success">verified</span>'
                      : '<span class="text-danger">' + esc(r.reason) + '</span>') + '</td>';
    t.insertBefore(tr, t.firstChild);
    while (t.children.length > 60) t.removeChild(t.lastChild);
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
    });
  }
  function paint(p) {
    var pct = p.total > 0 ? Math.round(p.done / p.total * 100) : 0;
    var bar = document.getElementById('bar');
    bar.style.width = pct + '%'; bar.textContent = pct + '%';
    document.getElementById('cDone').textContent = p.done;
    document.getElementById('cVerified').textContent = p.verified;
    document.getElementById('cFailed').textContent = p.failed;
    document.getElementById('cPending').textContent = p.pending;
    document.getElementById('runState').textContent = p.state;
  }

  function step() {
    fetch('?r=usi_bulk_step&n=5', {credentials: 'same-origin'})
      .then(function (r) { return r.json(); })
      .then(function (p) {
        if (p.error) { document.getElementById('note').textContent = p.error; return; }
        paint(p);
        (p.rows || []).forEach(row);
        if (p.note) document.getElementById('note').textContent = p.note;
        if (stop) return;
        if (p.pending > 0 && p.state === 'running') {
          // Someone else (another tab, or the same run left open elsewhere) holds
          // the lock - back off rather than spinning against it.
          setTimeout(step, p.ran === 0 ? 2000 : 250);
        } else {
          document.getElementById('note').textContent = 'Finished. Reload the page for the full list.';
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Reload';
          btn.onclick = function () { location.reload(); };
        }
      })
      .catch(function (e) {
        // A dropped connection is not fatal - the queue is in the database, so
        // waiting a moment and asking again picks up exactly where it stopped.
        document.getElementById('note').textContent = 'Connection hiccup, retrying...';
        if (!stop) setTimeout(step, 3000);
      });
  }

  function begin() {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Checking...';
    document.getElementById('note').textContent = 'You can leave this page at any time - it will carry on from here next time.';
    step();
  }
  btn.onclick = begin;

  // A run that is already open picks itself up when the page loads, so coming
  // back to this page is all it takes to carry on.
  <?= $running ? 'begin();' : '' ?>
})();
</script>
