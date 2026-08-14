<?php
/**
 * USI Registry - connection settings, sandbox test, and the audit trail.
 *
 * @var array $cfg      from anb_usi_config()
 * @var array $log      recent verification attempts
 * @var ?array $sandbox result of the last sandbox run, if one was just done
 * @var int   $pending  students with a USI on file that has not been verified
 * @var array $breakdown from anb_usi_verified_breakdown()
 */
$mode = $cfg['mode'];
$badge = ['off' => 'secondary', 'test' => 'warning', 'live' => 'success'][$mode] ?? 'secondary';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0"><i class="bi bi-patch-check"></i> USI Registry</h4>
  <span class="badge bg-<?= $badge ?> text-uppercase"><?= e($mode) ?></span>
</div>

<p class="text-muted">
  Checks a student's USI against the Commonwealth registry at
  <strong>usi.gov.au</strong> instead of taking a typed number on trust. A USI that does not match
  the registry is an AVETMISS reporting error and will not survive an audit.
</p>

<?php if ($mode === 'off'): ?>
  <div class="alert alert-secondary">
    Real-time verification is switched off. Staff verify by hand in the USI Organisation Portal and
    tick the box on the student record, exactly as before.
  </div>
<?php elseif ($mode === 'test'): ?>
  <div class="alert alert-warning">
    <strong>Test mode.</strong> Only the government's 3PT sandbox is reachable, using the credential
    the USI Office ships in the developer kit. Real students are never sent to 3PT - the
    <em>Verify with USI Registry</em> button stays hidden on student records until this is set to live.
  </div>
<?php else: ?>
  <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span>
      <strong>Live.</strong> Verification runs against the real registry using A&amp;B's own machine
      credential. <?= (int)$pending ?> student<?= $pending === 1 ? '' : 's' ?> currently
      <?= $pending === 1 ? 'has' : 'have' ?> a USI on file that has not been verified.
    </span>
    <?php if ($pending > 0): ?>
      <a class="btn btn-sm btn-anb text-nowrap" href="?r=usi_bulk">
        <i class="bi bi-lightning-charge"></i> Check them all
      </a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (!$cfg['configured']): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= e($cfg['problem']) ?></div>
<?php endif; ?>

<?php
  // "Verified" is not one thing. A tick that came across in the migration, or
  // that someone put there by hand, is somebody's word - only the registry
  // column is evidence that stands up without a person to vouch for it.
  $bd = $breakdown;
  $checkable = $bd['registry'] + $bd['by_hand'] + $bd['unverified'];
  $pct = $checkable > 0 ? round($bd['registry'] / $checkable * 100) : 0;
?>
<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <h6 class="fw-bold mb-0">How the USIs on file were checked</h6>
    <span class="small text-muted"><?= (int)$pct ?>% confirmed by the registry</span>
  </div>
  <div class="progress mb-3" style="height:20px">
    <?php if ($checkable > 0): ?>
      <div class="progress-bar bg-success" style="width: <?= $bd['registry']/$checkable*100 ?>%"
           title="Confirmed by the registry"></div>
      <div class="progress-bar bg-warning text-dark" style="width: <?= $bd['by_hand']/$checkable*100 ?>%"
           title="Ticked by a person"></div>
      <div class="progress-bar bg-danger" style="width: <?= $bd['unverified']/$checkable*100 ?>%"
           title="Not verified"></div>
    <?php endif; ?>
  </div>
  <div class="row text-center g-2">
    <div class="col">
      <div class="fs-4 fw-bold text-success"><?= (int)$bd['registry'] ?></div>
      <div class="small text-muted">confirmed by the registry</div>
    </div>
    <div class="col">
      <div class="fs-4 fw-bold text-warning"><?= (int)$bd['by_hand'] ?></div>
      <div class="small text-muted">ticked by a person</div>
    </div>
    <div class="col">
      <div class="fs-4 fw-bold text-danger"><?= (int)$bd['unverified'] ?></div>
      <div class="small text-muted">not verified</div>
    </div>
    <div class="col">
      <div class="fs-4 fw-bold text-muted"><?= (int)$bd['no_usi'] ?></div>
      <div class="small text-muted">no USI on file</div>
    </div>
  </div>
  <?php if ($bd['by_hand'] > 0): ?>
    <div class="alert alert-warning small mt-3 mb-0">
      <strong><?= (int)$bd['by_hand'] ?></strong> student<?= $bd['by_hand']===1?' is':'s are' ?>
      marked verified on somebody's word rather than the registry's - mostly ticks that came
      across in the migration. They are not wrong, but there is no evidence behind them.
      Running the bulk check over them turns each one into something an auditor can see for
      themselves.
    </div>
  <?php endif; ?>
</div>

<div class="row">
  <div class="col-lg-5">
    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-3">Connection</h6>
      <form method="post" action="?r=usi_registry_save">
        <div class="mb-3">
          <label class="form-label small fw-bold">Mode</label>
          <?php foreach ([
            'off'  => ['Off', 'Manual verification only.'],
            'test' => ['Test (3PT sandbox)', 'Prove the connection with the government\'s fake students.'],
            'live' => ['Live', 'Verify real students against the real registry.'],
          ] as $value => [$label, $help]): ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="usi_mode" id="mode_<?= $value ?>"
                     value="<?= $value ?>" <?= $mode === $value ? 'checked' : '' ?>>
              <label class="form-check-label" for="mode_<?= $value ?>">
                <?= e($label) ?><span class="d-block text-muted small"><?= e($help) ?></span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>

        <hr>
        <p class="small text-muted mb-3">
          These three are only used in live mode. They come from the machine credential created in
          Relationship Authorisation Manager against A&amp;B's ABN, and the credential file itself is
          uploaded to <code>app/data/usi/keystore-live.xml</code> outside the web root.
        </p>

        <?php
          // Show what is actually stored, whatever the current mode. anb_usi_config()
          // substitutes the sandbox credential when mode is off/test, so reading the
          // settings directly is the only way to see the real saved values - and if
          // these boxes render empty, switching to Live and pressing Save wipes them.
          $stored = anb_settings(db());
          $storedOrg  = trim((string)($stored['usi_org_code'] ?? ''));
          $storedCred = trim((string)($stored['usi_credential_id'] ?? ''));
          $storedPw   = (string)($stored['usi_keystore_password'] ?? '');
        ?>
        <div class="mb-2">
          <label class="form-label small fw-bold">Organisation code</label>
          <input class="form-control form-control-sm" name="usi_org_code"
                 value="<?= e($storedOrg) ?>" placeholder="e.g. 46055">
          <div class="form-text">The code the USI Registry knows A&amp;B by - not the ABN.</div>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold">Machine credential ID</label>
          <input class="form-control form-control-sm" name="usi_credential_id"
                 value="<?= e($storedCred) ?>"
                 placeholder="ABRD:51660446908_...">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Credential password</label>
          <input class="form-control form-control-sm" type="password" name="usi_keystore_password"
                 placeholder="<?= $storedPw !== '' ? 'unchanged' : '' ?>"
                 autocomplete="new-password">
          <div class="form-text">Leave blank to keep the stored one.</div>
        </div>

        <button class="btn btn-anb btn-sm"><i class="bi bi-save"></i> Save</button>
      </form>
    </div>

    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-2">Test the connection</h6>
      <p class="small text-muted mb-2">
        Runs the USI Office's own test records through the sandbox - two that should verify, two
        deactivated, one single-name student, and three that must fail. This is the evidence the
        integration works, and it touches nobody real.
      </p>
      <form method="post" action="?r=usi_registry_test">
        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-play-circle"></i> Run sandbox test</button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <?php if ($sandbox !== null): ?>
      <?php
        $passed = 0;
        foreach ($sandbox as $row) {
            $shouldVerify = stripos($row['expected'], 'must fail') === false
                         && stripos($row['expected'], 'Deactivated') === false;
            if ($row['error'] === '' && $row['verified'] === $shouldVerify) $passed++;
        }
      ?>
      <div class="card p-3 mb-3">
        <h6 class="fw-bold mb-2">
          Sandbox result
          <span class="badge bg-<?= $passed === count($sandbox) ? 'success' : 'danger' ?>">
            <?= $passed ?>/<?= count($sandbox) ?> as expected
          </span>
        </h6>
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>USI</th><th>Name</th><th>Registry says</th><th>Result</th><th>Expected</th></tr></thead>
          <tbody>
          <?php foreach ($sandbox as $row): ?>
            <tr>
              <td><code class="small"><?= e($row['usi']) ?></code></td>
              <td class="small"><?= e($row['name']) ?></td>
              <td class="small">
                <?php if ($row['error'] !== ''): ?>
                  <span class="text-danger"><?= e($row['error']) ?></span>
                <?php else: ?>
                  <?= e($row['status']) ?>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($row['error'] !== ''): ?>
                  <span class="badge bg-danger">error</span>
                <?php elseif ($row['verified']): ?>
                  <span class="badge bg-success">verified</span>
                <?php else: ?>
                  <span class="badge bg-secondary">rejected</span>
                <?php endif; ?>
              </td>
              <td class="small text-muted"><?= e($row['expected']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-2">Verification log</h6>
      <?php if (!$log): ?>
        <div class="text-muted small">Nothing checked yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>When</th><th>Student</th><th>USI</th><th>Env</th><th>Result</th><th>By</th></tr></thead>
            <tbody>
            <?php foreach ($log as $l): ?>
              <tr>
                <td class="small text-muted"><?= e((string)$l['checked_at']) ?></td>
                <td class="small">
                  <?php if ($l['student_id']): ?>
                    <a href="?r=student&id=<?= (int)$l['student_id'] ?>">
                      <?= e(trim(($l['first_name'] ?? '').' '.($l['last_name'] ?? ''))) ?: 'student #'.(int)$l['student_id'] ?>
                    </a>
                  <?php else: ?>
                    <span class="text-muted">sandbox</span>
                  <?php endif; ?>
                </td>
                <td><code class="small"><?= e((string)$l['usi']) ?></code></td>
                <td class="small text-muted"><?= e((string)$l['env']) ?></td>
                <td class="small">
                  <?php if (!empty($l['error'])): ?>
                    <span class="text-danger"><?= e((string)$l['error']) ?></span>
                  <?php elseif ((string)($l['env'] ?? '') === 'by hand'): ?>
                    <span class="badge bg-warning text-dark"><?= e((string)$l['status']) ?></span>
                  <?php elseif ((int)$l['verified'] === 1): ?>
                    <span class="badge bg-success">verified</span>
                  <?php else: ?>
                    <span class="badge bg-secondary"><?= e((string)$l['status'] ?: 'not verified') ?></span>
                  <?php endif; ?>
                  <?php if (!empty($l['note'])): ?>
                    <div class="text-muted"><?= e((string)$l['note']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="small text-muted"><?= e((string)$l['checked_by']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
