<?php
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$isAdmin = (($u = current_user()) && ($u['role'] ?? '') === 'admin');
$modeLabel = ['off'=>'Off','dry'=>'Test mode (nothing is sent)','live'=>'Live - enrolments go to RTO Data Cloud'];
$badge = ['off'=>'secondary','dry'=>'warning','live'=>'success'];
?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">RTO Data Cloud sync</h4>
    <div class="text-muted small">Enrolments made in this system are mirrored into RTO Data Cloud, so you can verify the USI there without typing anyone in by hand.</div></div>
  <div>
    <span class="badge text-bg-<?= $badge[$mode] ?? 'secondary' ?>"><?= e($modeLabel[$mode] ?? $mode) ?></span>
  </div>
</div>
<?php if($flash): ?><div class="alert alert-info py-2"><i class="bi bi-info-circle"></i> <?= e($flash) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">

    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-2">Waiting to go across (<?= count($queue) ?>)</h6>
      <p class="small text-muted mb-2">These were enrolled in this system and are not in RTO Data Cloud yet. In test mode nothing is actually sent - the exact information that <em>would</em> be sent is recorded below so you can check it first.</p>
      <?php if(!$queue): ?>
        <div class="text-muted small">Nothing waiting. Every enrolment made here is either already in RTO Data Cloud or came from the website (the website already sends those itself).</div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Student</th><th>Class</th><th>Status</th><th class="text-end">Action</th></tr></thead>
          <tbody>
          <?php foreach($queue as $q): ?>
            <tr>
              <td>
                <span class="fw-semibold"><?= e($q['first_name'].' '.$q['last_name']) ?></span><br>
                <span class="small text-muted"><?= e($q['email']) ?></span><br>
                <span class="small">USI: <?= $q['usi_number'] ? e($q['usi_number']) : '<span class="text-muted">not given yet</span>' ?></span>
              </td>
              <td class="small">
                <span class="fw-semibold"><?= e($q['course_code']) ?></span><br>
                <?= e($q['start_date'] ?: 'no class date') ?>
                <?php if(!$q['rto_schedule_id']): ?><br><span class="badge text-bg-light text-warning-emphasis">no matching class</span><?php endif; ?>
              </td>
              <td class="small">
                <?php $s=$q['rto_sync_status'] ?: 'not queued'; ?>
                <span class="badge text-bg-<?= $s==='failed'?'danger':'secondary' ?>"><?= e($s) ?></span>
                <?php if($q['rto_error']): $msg=(string)$q['rto_error']; ?>
                  <div class="text-muted" style="font-size:.75rem;" title="<?= e($msg) ?>"><?= e(mb_strimwidth($msg,0,90,'...')) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <form method="post" action="?r=rto_sync_push" class="d-inline">
                  <input type="hidden" name="enrolment_id" value="<?= (int)$q['id'] ?>">
                  <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-up-right-circle"></i> Send</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <div class="card p-3">
      <h6 class="fw-bold mb-2">Recent activity</h6>
      <?php if(!$log): ?>
        <div class="text-muted small">Nothing yet. As soon as someone enrols through this system, it will show here.</div>
      <?php else: ?>
      <div class="table-responsive" style="max-height:460px;overflow:auto;">
        <table class="table table-sm mb-0">
          <thead><tr><th>When</th><th>Student</th><th>Result</th><th>Detail</th></tr></thead>
          <tbody>
          <?php foreach($log as $l):
            $col = ['sent'=>'success','dry'=>'warning','error'=>'danger','skipped'=>'secondary'][$l['result']] ?? 'secondary'; ?>
            <tr>
              <td class="small text-muted" style="white-space:nowrap;"><?= e($l['created_at']) ?></td>
              <td class="small"><?= e(trim(($l['first_name'] ?? '').' '.($l['last_name'] ?? ''))) ?: '<span class="text-muted">-</span>' ?></td>
              <td><span class="badge text-bg-<?= $col ?>"><?= e($l['result']) ?></span></td>
              <td class="small"><?= e($l['message']) ?></td>
            </tr>
            <?php if($l['payload']): ?>
            <tr><td colspan="4" class="pt-0">
              <details><summary class="small text-muted" style="cursor:pointer;">Show exactly what was (or would be) sent</summary>
              <pre class="small bg-light p-2 mt-1 mb-0" style="max-height:220px;overflow:auto;"><?= e($l['payload']) ?></pre></details>
            </td></tr>
            <?php endif; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>

  <div class="col-lg-4">

    <?php if($isAdmin): ?>
    <div class="card p-3 mb-3" style="border-left:4px solid #E53935;">
      <h6 class="fw-bold mb-2"><i class="bi bi-toggles"></i> Sync setting</h6>
      <form method="post" action="?r=rto_sync_mode">
        <div class="form-check mb-2">
          <input class="form-check-input" type="radio" name="mode" id="m_off" value="off" <?= $mode==='off'?'checked':'' ?>>
          <label class="form-check-label small" for="m_off"><span class="fw-semibold">Off</span> - don't touch RTO Data Cloud at all.</label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="radio" name="mode" id="m_dry" value="dry" <?= $mode==='dry'?'checked':'' ?>>
          <label class="form-check-label small" for="m_dry"><span class="fw-semibold">Test mode</span> - record exactly what would be sent, but send nothing. Safe: no records are created in RTO Data Cloud.</label>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="radio" name="mode" id="m_live" value="live" <?= $mode==='live'?'checked':'' ?>>
          <label class="form-check-label small" for="m_live"><span class="fw-semibold">Live</span> - every new enrolment made here is created in RTO Data Cloud automatically.</label>
        </div>
        <button class="btn btn-anb btn-sm"><i class="bi bi-save"></i> Save setting</button>
      </form>
    </div>
    <?php endif; ?>

    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-2"><i class="bi bi-calendar-check"></i> Class matching</h6>
      <p class="small text-muted mb-2">So a student lands on the right class in RTO Data Cloud, each class here is matched to the same class over there by course and date.
      <?php if($unmappedClasses): ?><br><span class="text-danger-emphasis"><?= (int)$unmappedClasses ?> upcoming class<?= $unmappedClasses==1?'':'es' ?> not matched yet.</span><?php else: ?><br>All upcoming classes are matched.<?php endif; ?></p>
      <form method="post" action="?r=rto_sync_map">
        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat"></i> Re-check class matching</button>
      </form>
    </div>

    <div class="card p-3">
      <h6 class="fw-bold mb-2"><i class="bi bi-info-circle"></i> What gets sent, and what doesn't</h6>
      <ul class="small mb-2" style="padding-left:1.1rem;">
        <li class="mb-1"><span class="fw-semibold">Sent:</span> enrolments created in this system - staff "New enrolment", class self-enrolment links, and group classes.</li>
        <li class="mb-1"><span class="fw-semibold">Not sent:</span> website bookings. The website already puts those into RTO Data Cloud, so sending again would create a duplicate.</li>
        <li class="mb-1"><span class="fw-semibold">Not sent:</span> the old historical records that were imported into this system.</li>
        <li class="mb-1">Nothing is ever sent twice - once a student is over there, this system remembers their RTO Data Cloud id and leaves them alone.</li>
      </ul>
      <div class="small text-muted">Totals by status:
        <?php foreach($counts as $k=>$v): ?><span class="badge text-bg-light text-dark me-1"><?= e($k) ?>: <?= (int)$v ?></span><?php endforeach; ?>
      </div>
    </div>

  </div>
</div>
