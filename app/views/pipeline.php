<?php
/** Class readiness pipeline - one screen, whole class, traffic-light per requirement. */
function dot($ok, $warn=false) {
    if ($ok)   return '<span class="pdot bg-success" title="Done"></span>';
    if ($warn) return '<span class="pdot bg-warning" title="Pending"></span>';
    return '<span class="pdot bg-danger" title="Not done"></span>';
}

/**
 * A dot you can click to change. Each one posts a single field for a single
 * enrolment, so there is nothing to save and nothing to lose if the page is
 * closed halfway through a class.
 */
function markdot($schedId, $enrolId, $field, $isOn, $labelOn, $labelOff, $warn=false) {
    $cls = $isOn ? 'bg-success' : ($warn ? 'bg-warning' : 'bg-danger');
    $t   = $isOn ? $labelOn : $labelOff;
    return '<form method="post" action="?r=pipe_mark" class="m-0">'
         . '<input type="hidden" name="schedule_id" value="'.(int)$schedId.'">'
         . '<input type="hidden" name="enrolment_id" value="'.(int)$enrolId.'">'
         . '<input type="hidden" name="field" value="'.htmlspecialchars($field, ENT_QUOTES).'">'
         . ($isOn ? '' : '<input type="hidden" name="on" value="1">')
         . '<button type="submit" class="btn p-0 border-0 bg-transparent" title="'
         . htmlspecialchars($t, ENT_QUOTES).' - click to change">'
         . '<span class="pdot '.$cls.'"></span></button></form>';
}

/**
 * A three-state picker that saves the moment it changes - the same
 * Absent/Present and Not Assessed/Satisfactory/Not Yet Satisfactory wording
 * the trainers already know from the old system.
 */
function markselect($schedId, $enrolId, $field, $options, $current, $colours) {
    $h  = '<form method="post" action="?r=pipe_mark" class="m-0">'
        . '<input type="hidden" name="schedule_id" value="'.(int)$schedId.'">'
        . '<input type="hidden" name="enrolment_id" value="'.(int)$enrolId.'">'
        . '<input type="hidden" name="field" value="'.htmlspecialchars($field, ENT_QUOTES).'">'
        . '<select name="status" onchange="this.form.submit()" '
        . 'class="form-select form-select-sm border-0 '.($colours[$current] ?? '').'" '
        . 'style="font-size:.72rem;padding:2px 18px 2px 6px;background-position:right .2rem center;">';
    foreach ($options as $v => $label) {
        $h .= '<option value="'.htmlspecialchars((string)$v, ENT_QUOTES).'"'
            . ($current === $v ? ' selected' : '').'>'.htmlspecialchars($label).'</option>';
    }
    return $h . '</select></form>';
}

/** The same thing for a whole class. */
function markall($schedId, $field, $label) {
    return '<form method="post" action="?r=pipe_mark" class="m-0 d-inline">'
         . '<input type="hidden" name="schedule_id" value="'.(int)$schedId.'">'
         . '<input type="hidden" name="field" value="'.htmlspecialchars($field, ENT_QUOTES).'">'
         . '<input type="hidden" name="all" value="1"><input type="hidden" name="on" value="1">'
         . '<button class="btn btn-sm btn-outline-secondary py-0">'.htmlspecialchars($label).'</button></form>';
}
$cols = ['Online','AVETMISS','USI','Paid','ID','Attend.','Tasks'];
// count fully-ready students
$ready = 0; $issuedN = 0;
foreach ($rows as $r) {
    // Somebody who already has their certificate is not "ready to certify" -
    // counting them kept the button offering work that was already done.
    if (($r['status'] ?? '') === 'issued') { $issuedN++; continue; }
    // usi_verified, not usi_number - anb_generate_certificate() refuses an
    // unverified USI, so counting one as ready would offer a button that fails.
    $ok = $r['online_complete'] && $r['avetmiss_complete'] && !empty($r['usi_verified']) && $r['payment_status']==='paid'
        && $r['id_confirmed'] && $r['attendance_marked'] && $r['tasks_satisfactory'];
    if ($ok) $ready++;
}
// how many students still have no login email (only chased up for classes still to run)
$classUpcoming = !empty($schedule['start_date']) && $schedule['start_date'] >= date('Y-m-d');
$noLogin = 0; $loginFailed = 0;
foreach ($rows as $r) {
    if (empty($r['portal_emailed_at'])) { $noLogin++; if (!empty($r['portal_error'])) $loginFailed++; }
}
if (!$classUpcoming) $noLogin = 0;
// Timestamps are stored by SQLite datetime('now'), which is UTC - show them in local time.
$stamp = function ($t) { return $t ? date('j M, g:ia', strtotime($t . ' UTC')) : ''; };
?>
<style>
  .pdot{ display:inline-block;width:15px;height:15px;border-radius:50%; }
  .pipe-th{ font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;color:#8a8a8a;text-align:center; }
  .pipe-td{ text-align:center; }
</style>

<div class="topbar">
  <div>
    <a href="?r=schedules" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Schedules</a>
    <h4 class="mb-0 fw-bold" style="color:#2F1D3A;"><?= e($schedule['course_code']) ?> — Class Pipeline</h4>
    <div class="text-muted small"><?= e($schedule['plan_title']) ?><br>
      <i class="bi bi-calendar3"></i> <?= e($schedule['start_date']) ?>
      <?= e(substr((string)$schedule['start_time'],0,5)) ?>–<?= e(substr((string)$schedule['end_time'],0,5)) ?>
      &middot; <i class="bi bi-geo-alt"></i> <?= e($schedule['location']) ?></div>
  </div>
  <div class="text-end">
    <span class="badge text-bg-<?= $ready===count($rows)&&$rows?'success':'secondary' ?> fs-6"><?= $ready ?>/<?= count($rows) ?> ready to certify</span>
    <?php if ($issuedN): ?>
      <div class="small text-success mt-1"><i class="bi bi-award"></i> <?= (int)$issuedN ?> certificate<?= $issuedN===1?'':'s' ?> issued</div>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($_SESSION['flash'])):
        $isErr = !empty($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
  <div class="alert alert-<?= $isErr ? 'danger' : 'success' ?>">
    <i class="bi bi-<?= $isErr ? 'exclamation-triangle-fill' : 'check-circle-fill' ?>"></i> <?= e($_SESSION['flash']) ?></div>
  <?php unset($_SESSION['flash']); endif; ?>

<?php if ($noLogin > 0): ?>
  <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <i class="bi bi-envelope-exclamation-fill"></i>
      <strong><?= (int)$noLogin ?> student<?= $noLogin===1?' has':'s have' ?> not received login details</strong>
      for the online modules<?php if ($loginFailed): ?> — <?= (int)$loginFailed ?> of them because the email failed to send<?php endif; ?>.
      They cannot start their pre-course learning until this is sent.
    </div>
    <form method="post" action="?r=class_send_access" class="m-0"
          onsubmit="return confirm('Send login details to the <?= (int)$noLogin ?> student(s) who have not received them?');">
      <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
      <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-send"></i> Send now</button>
    </form>
  </div>
<?php endif; ?>

<div class="alert alert-light border small">
  <i class="bi bi-list-check text-danger"></i>
  Every student in this class at a glance. Green = done, amber = pending, red = outstanding.
  <strong>Click the Online, Paid or ID dots to change them, and pick from the Attend. and Tasks
  drop-downs</strong> — each one saves as you go. Ticking Online by hand records your name against
  it, for the students who did the theory in the room rather than online. The buttons at the top of those columns do the
  whole class at once. Then <strong>Sign Off</strong> to issue every certificate in one go.
  A student marked <strong>Absent</strong> or <strong>Not Yet Satisfactory</strong> is left out of
  the sign-off, which is the point — they should not receive a certificate.
</div>

<?php
/**
 * Website bookings arrive with a course and a payment but no class against
 * them, so they never appear here and can never be signed off. This is the
 * step between "they booked" and "issue their certificate".
 */
?>
<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h6 class="fw-bold mb-0"><i class="bi bi-person-plus text-danger"></i> Who was in the room?</h6>
      <div class="text-muted small">
        Add students who attended but are not on this list. Most website bookings come through
        with the course only, so they have to be put into a class before they can be certified.
      </div>
    </div>
    <?php if (!$addOpen): ?>
      <a href="?r=pipeline&schedule_id=<?= (int)$schedule['id'] ?>&add=1" class="btn btn-outline-danger btn-sm">
        <i class="bi bi-search"></i> Find students to add</a>
    <?php else: ?>
      <a href="?r=pipeline&schedule_id=<?= (int)$schedule['id'] ?>" class="btn btn-outline-secondary btn-sm">Close</a>
    <?php endif; ?>
  </div>

  <?php if ($addOpen): ?>
    <hr class="my-2">
    <form method="get" class="row g-2 align-items-center mb-2">
      <input type="hidden" name="r" value="pipeline">
      <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
      <input type="hidden" name="add" value="1">
      <div class="col-sm-6 col-md-4">
        <input type="text" name="q" value="<?= e($addSearch) ?>" class="form-control form-control-sm"
               placeholder="Search by name or email">
      </div>
      <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Search</button></div>
      <?php if ($addSearch !== ''): ?>
        <div class="col-auto"><a href="?r=pipeline&schedule_id=<?= (int)$schedule['id'] ?>&add=1"
           class="btn btn-sm btn-link text-decoration-none">Clear</a></div>
      <?php endif; ?>
    </form>

    <?php if (!$addRows): ?>
      <div class="text-muted small">
        <?= $addSearch !== '' ? 'Nobody matching that is waiting to be put into a class.'
            : 'Every booking for this course is already in a class.' ?>
      </div>
    <?php else: ?>
      <form method="post" action="?r=pipe_add"
            onsubmit="return confirm('Add the ticked students to this class?')">
        <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
        <div class="table-responsive" style="max-height:320px;overflow-y:auto;">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light" style="position:sticky;top:0;">
              <tr>
                <th style="width:32px;"><input type="checkbox" class="form-check-input"
                    onclick="this.closest('form').querySelectorAll('.pick').forEach(c=>c.checked=this.checked)"></th>
                <th class="small">Student</th><th class="small">Booked</th>
                <th class="small">Paid</th><th class="small">USI</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($addRows as $a): ?>
              <tr>
                <td><input type="checkbox" class="form-check-input pick" name="enrolment_id[]" value="<?= (int)$a['id'] ?>"></td>
                <td class="small">
                  <a href="?r=student&id=<?= (int)$a['student_id'] ?>" class="text-decoration-none fw-semibold"
                     style="color:#2F1D3A;" target="_blank"><?= e(trim($a['first_name'].' '.$a['last_name'])) ?></a>
                  <div class="text-muted" style="font-size:.7rem;"><?= e((string)$a['email']) ?></div>
                </td>
                <td class="small text-muted"><?= $a['created_at'] ? e(date('j M Y', strtotime((string)$a['created_at']))) : '' ?></td>
                <td><?= $a['payment_status']==='paid'
                      ? '<span class="badge text-bg-success">Paid</span>'
                      : '<span class="badge text-bg-secondary">'.e((string)$a['payment_status']).'</span>' ?></td>
                <td><?php if (!empty($a['usi_verified'])): ?><span class="badge text-bg-success">Verified</span>
                    <?php elseif (trim((string)$a['usi_number']) !== ''): ?><span class="badge text-bg-warning">Not verified</span>
                    <?php else: ?><span class="badge text-bg-danger">None</span><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-2">
          <div class="text-muted small">
            <?= count($addRows) ?> booking<?= count($addRows)===1?'':'s' ?> for
            <?= e($schedule['course_code']) ?> with no class yet<?= count($addRows) >= 300 ? ' (showing the 300 most recent — search to narrow it down)' : '' ?>.
            Adding them puts this class's date and location on their record.
          </div>
          <button class="btn btn-anb btn-sm"><i class="bi bi-person-check"></i> Add to this class</button>
        </div>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card p-0">
  <div class="table-responsive">
  <table class="table align-middle mb-0">
    <thead>
      <tr>
        <th style="padding-left:18px;">Student</th>
        <th class="pipe-th">Status</th>
        <th class="pipe-th">Login sent</th>
        <?php foreach ($cols as $c): ?><th class="pipe-th"><?= e($c) ?></th><?php endforeach; ?>
        <th class="pipe-th">Certificate</th>
      </tr>
      <tr class="table-light">
        <td colspan="3"></td>
        <td class="pipe-td"><?= markall($schedule['id'],'online_complete','All Done') ?></td>
        <td class="pipe-td" colspan="3"></td>
        <td class="pipe-td"><?= markall($schedule['id'],'id_confirmed','All Sighted') ?></td>
        <td class="pipe-td">
          <form method="post" action="?r=pipe_mark" class="m-0 d-inline">
            <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
            <input type="hidden" name="field" value="attendance">
            <input type="hidden" name="status" value="present">
            <input type="hidden" name="all" value="1">
            <button class="btn btn-sm btn-outline-secondary py-0">All Present</button>
          </form>
        </td>
        <td class="pipe-td">
          <form method="post" action="?r=pipe_mark" class="m-0 d-inline">
            <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
            <input type="hidden" name="field" value="tasks">
            <input type="hidden" name="status" value="satisfactory">
            <input type="hidden" name="all" value="1">
            <button class="btn btn-sm btn-outline-secondary py-0">All Satisfactory</button>
          </form>
        </td>
        <td></td>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
      $paid = $r['payment_status']==='paid';
      $allGreen = $r['online_complete'] && $r['avetmiss_complete'] && !empty($r['usi_verified']) && $paid
                  && $r['id_confirmed'] && $r['attendance_marked'] && $r['tasks_satisfactory'];
    ?>
      <tr>
        <td style="padding-left:18px;">
          <a href="?r=student&id=<?= (int)$r['student_id'] ?>" class="fw-semibold text-decoration-none" style="color:#2F1D3A;" title="Open student details"><?= e($r['first_name'].' '.$r['last_name']) ?></a>
          <div class="text-muted small"><?= e($r['email']) ?></div>
        </td>
        <td class="pipe-td">
          <?php
            // Absent / not yet satisfactory are decisions, not things still to
            // do - saying "Pending" would read as though someone forgot.
            $att  = (string)($r['attendance_status'] ?? '');
            $task = (string)($r['tasks_status'] ?? '');
          ?>
          <?php if ($allGreen): ?><span class="badge text-bg-success">Ready</span>
          <?php elseif ($att === 'absent'): ?><span class="badge text-bg-dark">Absent</span>
          <?php elseif ($task === 'not_yet'): ?><span class="badge text-bg-danger">Not yet satisfactory</span>
          <?php else: ?><span class="badge text-bg-warning">Pending</span><?php endif; ?>
        </td>
        <td class="pipe-td">
          <?php if (!empty($r['portal_emailed_at'])): ?>
            <span class="pdot bg-success" title="Login details emailed <?= e($stamp($r['portal_emailed_at'])) ?>"></span>
            <div class="text-muted" style="font-size:.68rem;"><?= e($stamp($r['portal_emailed_at'])) ?></div>
          <?php else: ?>
            <span class="pdot bg-danger" title="<?= e($r['portal_error'] ? 'Last attempt failed: '.$r['portal_error'] : 'No login details sent yet') ?>"></span>
            <div style="font-size:.68rem;">
              <a href="?r=student_send_access&id=<?= (int)$r['student_id'] ?>&schedule_id=<?= (int)$schedule['id'] ?>"
                 class="text-danger fw-semibold text-decoration-none">Send</a>
            </div>
            <?php if (!empty($r['portal_error'])): ?>
              <div class="text-danger" style="font-size:.62rem;">failed</div>
            <?php endif; ?>
          <?php endif; ?>
        </td>
        <td class="pipe-td">
          <?= markdot($schedule['id'],$r['id'],'online_complete',(bool)$r['online_complete'],
                'Online modules complete','Online modules not finished - click if they did the theory in the room') ?>
          <?php if (!empty($r['online_marked_by'])): ?>
            <div class="text-warning-emphasis" style="font-size:.62rem;"
                 title="Ticked by <?= e((string)$r['online_marked_by']) ?> on <?= e((string)$r['online_marked_at']) ?>">by hand</div>
          <?php endif; ?>
        </td>
        <td class="pipe-td">
          <?php
            $am    = $r['avetmiss_missing'] ?? [];
            $amTot = $r['avetmiss_total'] ?? count($am);
            // Amber when they have made a start and something is outstanding;
            // red is reserved for a record with nothing on it at all.
            $amPart = $am && count($am) < $amTot;
          ?>
          <?php if (!$am): ?>
            <span class="pdot bg-success" title="All government reporting details are on file"></span>
          <?php else: ?>
            <a href="?r=student&id=<?= (int)$r['student_id'] ?>" class="text-decoration-none"
               title="Still needed: <?= e(implode(', ', $am)) ?>">
              <span class="pdot <?= $amPart ? 'bg-warning' : 'bg-danger' ?>"></span>
              <div class="<?= $amPart ? 'text-warning-emphasis' : 'text-danger' ?>" style="font-size:.62rem;"><?= count($am) ?> missing</div>
            </a>
          <?php endif; ?>
        </td>
        <td class="pipe-td">
          <?php
            // A USI sitting in the box proves nothing - the certificate is
            // refused unless the registry has confirmed it, so this column has
            // to show that same rule or the screen promises what it can't do.
            $hasUsi   = trim((string)$r['usi_number']) !== '';
            $usiOk    = !empty($r['usi_verified']);
            $byHand   = $usiOk && ($r['usi_verified_method'] ?? '') !== 'registry';
          ?>
          <?php if (!$hasUsi): ?>
            <span class="pdot bg-danger" title="No USI on file"></span>
            <div class="text-danger" style="font-size:.62rem;">none</div>
          <?php elseif ($usiOk): ?>
            <span class="pdot bg-<?= $byHand ? 'warning' : 'success' ?>"
                  title="<?= $byHand ? 'Ticked by a person, not confirmed by the registry' : 'Confirmed by the USI Registry' ?>"></span>
            <?php if ($byHand): ?><div class="text-warning-emphasis" style="font-size:.62rem;">by hand</div><?php endif; ?>
          <?php else: ?>
            <span class="pdot bg-warning" title="USI on file but not verified - <?= e((string)$r['usi_number']) ?>"></span>
            <form method="post" action="?r=usi_check" class="m-0">
              <input type="hidden" name="student_id" value="<?= (int)$r['student_id'] ?>">
              <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
              <button class="btn btn-link p-0 text-decoration-none fw-semibold"
                      style="font-size:.62rem;" title="Check <?= e((string)$r['usi_number']) ?> against the registry now">
                verify
              </button>
            </form>
            <a href="?r=usi_fix&id=<?= (int)$r['student_id'] ?>&schedule_id=<?= (int)$schedule['id'] ?>"
               class="text-decoration-none" style="font-size:.62rem;"
               title="See what the registry rejected and correct it">fix</a>
          <?php endif; ?>
        </td>
        <td class="pipe-td"><?= markdot($schedule['id'],$r['id'],'payment_status',$paid,'Paid','Not paid - click if they have paid or been invoiced', $r['payment_status']==='part') ?></td>
        <td class="pipe-td"><?= markdot($schedule['id'],$r['id'],'id_confirmed',(bool)$r['id_confirmed'],'ID sighted','ID not sighted yet') ?></td>
        <td class="pipe-td" style="min-width:122px;">
          <?= markselect($schedule['id'],$r['id'],'attendance',PIPE_ATTENDANCE,(string)($r['attendance_status'] ?? ''),
                ['' => 'text-muted', 'present' => 'text-success fw-bold', 'absent' => 'text-danger fw-bold']) ?>
        </td>
        <td class="pipe-td" style="min-width:150px;">
          <?= markselect($schedule['id'],$r['id'],'tasks',PIPE_TASKS,(string)($r['tasks_status'] ?? ''),
                ['' => 'text-muted', 'satisfactory' => 'text-success fw-bold', 'not_yet' => 'text-danger fw-bold']) ?>
        </td>
        <td class="pipe-td">
          <?php if ($r['status']==='issued'): ?>
            <span class="badge text-bg-success">Issued</span>
          <?php elseif ($allGreen): ?>
            <a href="?r=generate&enrolment_id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-anb py-0"><i class="bi bi-award"></i> Generate</a>
          <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; if(!$rows): ?><tr><td colspan="11" class="text-muted p-3">No students enrolled in this class.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  <div class="d-flex justify-content-between align-items-center p-3 border-top">
    <div class="small text-muted">Only students with every box green can be certified.</div>
    <div>
      <?php if ($unsentCerts > 0): ?>
        <form method="post" action="?r=class_cert_email" class="d-inline"
              onsubmit="return confirm('Email the certificate to the <?= (int)$unsentCerts ?> student(s) in this class who have not been sent one yet?')">
          <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
          <button class="btn btn-anb"><i class="bi bi-envelope-arrow-up"></i>
            Email certificates (<?= (int)$unsentCerts ?>)</button>
        </form>
      <?php endif; ?>
      <button class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat"></i> Refresh</button>
      <form method="post" action="?r=class_send_access" class="d-inline" onsubmit="return confirm('Send portal login access to all students in this class who have not received it yet?');">
        <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-envelope-check"></i> Send access to class</button>
      </form>
      <form method="post" action="?r=class_send_access" class="d-inline"
            onsubmit="return confirm('Resend login details to EVERY student in this class? Each one gets a brand new password and any earlier email stops working. Use this when students say the first email never arrived.');">
        <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
        <input type="hidden" name="mode" value="all">
        <button type="submit" class="btn btn-outline-secondary" title="New password for everyone in this class"><i class="bi bi-arrow-repeat"></i> Resend to everyone</button>
      </form>
      <a href="?r=signoff&schedule_id=<?= (int)$schedule['id'] ?>" class="btn btn-success"><i class="bi bi-check2-all"></i> Sign Off &amp; Generate Certificates (<?= $ready ?>)</a>
    </div>
  </div>
</div>

<?php
/**
 * What is standing between each student and their certificate, in words.
 * The dots say which box is red; this says what to do about it, and it is the
 * same list the sign-off uses to decide who to leave out.
 */
if ($blockers): ?>
<div class="card p-3 mt-3">
  <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle text-warning"></i>
    <?= count($blockers) ?> student<?= count($blockers)===1?'':'s' ?> cannot be certified yet</h6>
  <div class="text-muted small mb-2">Sign Off skips these. Clear what is listed and they are included next time.</div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <tbody>
      <?php foreach ($blockers as $b): ?>
        <tr>
          <td class="small fw-semibold" style="width:220px;">
            <a href="?r=student&id=<?= (int)$b['student_id'] ?>" class="text-decoration-none"
               style="color:#2F1D3A;"><?= e($b['name']) ?></a>
          </td>
          <td class="small text-muted"><?= e(implode(' · ', $b['reasons'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
