<?php
/**
 * Student profile, laid out in the tabs the office already knows from the old
 * system: Overview, Edit, Profile (AVETMISS / USI / Notes), Documents,
 * Learning, Activity, Records, Training, Financial.
 *
 * "Staff" is deliberately absent - Gloria asked for it to go.
 *
 * Only the current tab's data is loaded, so this stays quick for a student
 * with years of history behind them.
 */
require_once __DIR__ . '/../lib/usi.php';
$usiCfg = anb_usi_config(db());

$tabUrl = fn(string $t): string => '?r=student&id='.(int)$s['id'].($t === 'overview' ? '' : '&tab='.$t);

/** The tabs, grouped the way the old system grouped them. */
$groups = [
  ['label' => 'Overview',  'tab' => 'overview'],
  ['label' => 'Edit',      'tab' => 'edit'],
  ['label' => 'Profile',   'children' => ['avetmiss' => 'AVETMISS', 'usi' => 'USI', 'notes' => 'Notes']],
  ['label' => 'Documents', 'tab' => 'documents'],
  ['label' => 'Learning',  'tab' => 'learning'],
  ['label' => 'Activity',  'tab' => 'activity'],
  ['label' => 'Records',   'tab' => 'records'],
  ['label' => 'Training',  'children' => ['upcoming' => 'Upcoming', 'bookings' => 'Bookings',
                                          'enrolments' => 'Enrolments', 'classes' => 'Classes',
                                          'pipelines' => 'Occurrence Pipelines']],
  ['label' => 'Financial', 'children' => ['invoices' => 'Invoices', 'payments' => 'Payments']],
];
$byRegistry = ($s['usi_verified_method'] ?? '') === 'registry';
?>
<div class="topbar">
  <div>
    <a href="?r=students" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Students</a>
    <h4 class="mb-0 fw-bold" style="color:#2F1D3A;"><?= e(trim($s['salutation'].' '.$s['first_name'].' '.$s['middle_name'].' '.$s['last_name'])) ?></h4>
  </div>
  <div class="text-end">
    <?php if ($s['usi_number']): ?><span class="badge text-bg-light border fs-6">USI <?= e($s['usi_number']) ?></span>
    <?php else: ?><span class="badge text-bg-warning fs-6">No USI on file</span><?php endif; ?>
    <?php if (!empty($s['email'])): ?>
      <a href="?r=student_send_access&id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-danger ms-2" onclick="return confirm('Email portal access (login + password) to <?= e($s['email']) ?>?')"><i class="bi bi-envelope"></i> Send portal access</a>
      <?php if (!empty($s['portal_emailed_at'])): ?><div class="small text-success mt-1">Portal access sent <?= e($s['portal_emailed_at']) ?></div><?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($_SESSION['flash'])):
        $isErr = !empty($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
  <div class="alert alert-<?= $isErr ? 'danger' : 'success' ?> py-2"><?= e($_SESSION['flash']) ?></div>
  <?php unset($_SESSION['flash']); endif; ?>

<ul class="nav nav-tabs mb-3">
  <?php foreach ($groups as $g): ?>
    <?php if (isset($g['tab'])): ?>
      <li class="nav-item">
        <a class="nav-link <?= $tab === $g['tab'] ? 'active fw-bold' : '' ?>" href="<?= e($tabUrl($g['tab'])) ?>"><?= e($g['label']) ?></a>
      </li>
    <?php else: ?>
      <?php $inGroup = array_key_exists($tab, $g['children']); ?>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle <?= $inGroup ? 'active fw-bold' : '' ?>" data-bs-toggle="dropdown" href="#" role="button">
          <?= e($g['label']) ?><?= $inGroup ? ' — '.e($g['children'][$tab]) : '' ?>
        </a>
        <ul class="dropdown-menu">
          <?php foreach ($g['children'] as $t => $lbl): ?>
            <li><a class="dropdown-item <?= $tab === $t ? 'active' : '' ?>" href="<?= e($tabUrl($t)) ?>"><?= e($lbl) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </li>
    <?php endif; ?>
  <?php endforeach; ?>
</ul>

<?php /* ------------------------------------------------------------ OVERVIEW */ ?>
<?php if ($tab === 'overview'): ?>
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card p-3 mb-3">
        <h6 class="fw-bold mb-3">Student Details</h6>
        <?php
          $rows = [
            'ID' => (string)$s['id'],
            'Legacy student #' => (string)($s['external_id'] ?: '—'),
            'RTO Data Cloud ID' => (string)($s['rto_person_id'] ?: '—'),
            'Name' => trim($s['first_name'].' '.$s['last_name']),
            'First / given name' => (string)($s['first_name'] ?: '—'),
            'Last name / surname' => (string)($s['last_name'] ?: '—'),
            'Date of birth' => (string)($s['date_of_birth'] ?: '—'),
            'Email' => (string)($s['email'] ?: '—'),
            'Mobile' => (string)($s['mobile_phone'] ?: '—'),
          ];
          foreach ($rows as $k => $v): ?>
          <div class="d-flex justify-content-between border-bottom py-2 small">
            <span class="text-muted"><?= e($k) ?></span><span class="fw-semibold text-end"><?= e($v) ?></span>
          </div>
        <?php endforeach; ?>
        <div class="d-flex justify-content-between border-bottom py-2 small">
          <span class="text-muted">
            <span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:<?= !empty($s['usi_verified']) ? ($byRegistry ? '#2e7d32' : '#f0ad4e') : '#E53935' ?>"></span>
            USI
          </span>
          <span class="fw-semibold text-end">
            <?= e($s['usi_number'] ?: '—') ?>
            <?php if (!empty($s['usi_verified'])): ?>
              <span class="text-muted fw-normal fst-italic">
                <?= $byRegistry ? 'verified' : 'ticked by hand' ?><?= $s['usi_verified_date'] ? ' on '.e(substr((string)$s['usi_verified_date'],0,10)) : '' ?>
              </span>
            <?php elseif ($s['usi_number']): ?>
              <a href="<?= e($tabUrl('usi')) ?>" class="fw-normal">not verified</a>
            <?php endif; ?>
          </span>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="fw-bold mb-0">Staff Notes</h6>
          <a class="btn btn-sm btn-outline-secondary" href="<?= e($tabUrl('notes')) ?>">Add / view all</a>
        </div>
        <?php if (!$notes): ?>
          <div class="text-muted small">Nothing recorded for this student.</div>
        <?php else: foreach (array_slice($notes, 0, 3) as $n): ?>
          <div class="border-bottom py-2 small">
            <?= nl2br(e((string)$n['note'])) ?>
            <div class="text-muted" style="font-size:.72rem;"><?= e((string)$n['created_by']) ?> · <?= e((string)$n['created_at']) ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <div class="card p-3">
        <h6 class="fw-bold mb-2">Completions</h6>
        <?php if (!$certs): ?>
          <div class="text-muted small">No recent training completions recorded.</div>
        <?php else: ?>
          <?php foreach (array_slice($certs, 0, 5) as $c): ?>
            <div class="d-flex justify-content-between border-bottom py-2 small">
              <span><?= e($c['course_title']) ?></span>
              <span class="text-muted"><?= e($c['issue_date']) ?></span>
            </div>
          <?php endforeach; ?>
          <a class="small mt-2" href="<?= e($tabUrl('records')) ?>">All records</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

<?php /* ---------------------------------------------------------------- EDIT */ ?>
<?php elseif ($tab === 'edit'): ?>
  <div class="card p-3" style="max-width:820px;">
    <h6 class="fw-bold mb-2">Edit student</h6>
    <p class="small text-muted">
      The name and date of birth must match the USI Registry exactly - middle names, hyphens and
      married names all count. A student with one legal name has it in the family name box with
      the first name left <strong>empty</strong>.
    </p>
    <form method="post" action="?r=student_save">
      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
      <div class="row g-2">
        <div class="col-3">
          <label class="form-label small fw-bold">Title</label>
          <input class="form-control form-control-sm" name="salutation" value="<?= e($s['salutation']) ?>">
        </div>
        <div class="col-9">
          <label class="form-label small fw-bold">First name</label>
          <input class="form-control form-control-sm" name="first_name" value="<?= e($s['first_name']) ?>">
        </div>
        <div class="col-6">
          <label class="form-label small fw-bold">Middle name</label>
          <input class="form-control form-control-sm" name="middle_name" value="<?= e($s['middle_name']) ?>">
        </div>
        <div class="col-6">
          <label class="form-label small fw-bold">Family name</label>
          <input class="form-control form-control-sm" name="last_name" value="<?= e($s['last_name']) ?>">
        </div>
        <div class="col-6">
          <label class="form-label small fw-bold">Date of birth</label>
          <input class="form-control form-control-sm" name="date_of_birth" value="<?= e($s['date_of_birth']) ?>" placeholder="yyyy-mm-dd">
        </div>
        <div class="col-6">
          <label class="form-label small fw-bold">USI</label>
          <input class="form-control form-control-sm text-uppercase" name="usi_number" value="<?= e($s['usi_number']) ?>" maxlength="10">
        </div>
        <div class="col-7">
          <label class="form-label small fw-bold">Email</label>
          <input class="form-control form-control-sm" name="email" value="<?= e($s['email']) ?>">
        </div>
        <div class="col-5">
          <label class="form-label small fw-bold">Mobile</label>
          <input class="form-control form-control-sm" name="mobile_phone" value="<?= e($s['mobile_phone']) ?>">
        </div>
      </div>
      <button class="btn btn-anb btn-sm mt-3"><i class="bi bi-save"></i> Save details</button>
      <span class="small text-muted ms-2">Changing a name or USI clears the verified tick.</span>
    </form>
  </div>

<?php /* ------------------------------------------------------------ AVETMISS */ ?>
<?php elseif ($tab === 'avetmiss'): ?>
  <div class="card p-3" style="max-width:720px;">
    <h6 class="fw-bold mb-3">AVETMISS details</h6>
    <?php
    $fields = [
      'Date of birth'=>$s['date_of_birth'], 'Gender'=>$s['gender'],
      'Email'=>$s['email'], 'Mobile'=>$s['mobile_phone'],
      'Address'=>trim($s['street_number'].' '.$s['street_name'].', '.$s['suburb'].' '.$s['state'].' '.$s['postcode']),
      'Town / city of birth'=>$s['town_of_birth'],
      'Country of birth'=>anb_demo_label('country',$s['country_of_birth']),
      'Language at home'=>anb_demo_label('lang',$s['main_language']),
      'Highest school level'=>anb_demo_label('school',$s['highest_school_level']),
      'Indigenous status'=>anb_demo_label('indig',$s['indigenous_status']),
      'Employment status'=>anb_demo_label('labour',$s['labour_force_status']),
      'Disability'=>anb_demo_label('disab',$s['disability_flag']),
    ];
    foreach ($fields as $k=>$v): ?>
      <div class="d-flex justify-content-between border-bottom py-2 small">
        <span class="text-muted"><?= e($k) ?></span>
        <span class="fw-semibold text-end <?= ($v === null || trim((string)$v) === '') ? 'text-danger' : '' ?>">
          <?= e($v ?: 'missing') ?>
        </span>
      </div>
    <?php endforeach; ?>
    <a href="<?= e($tabUrl('edit')) ?>" class="btn btn-sm btn-outline-secondary mt-3"><i class="bi bi-pencil"></i> Edit</a>
  </div>

<?php /* ----------------------------------------------------------------- USI */ ?>
<?php elseif ($tab === 'usi'): ?>
  <div class="card p-3" style="max-width:720px;border-left:4px solid <?= !empty($s['usi_verified'])?'#2e7d32':'#E53935' ?>;">
    <h6 class="fw-bold mb-2"><i class="bi bi-patch-check"></i> USI verification</h6>
    <?php if (!empty($s['usi_verified'])): ?>
      <div class="alert alert-<?= $byRegistry ? 'success' : 'warning' ?> py-2 small mb-2">
        <i class="bi bi-<?= $byRegistry ? 'check-circle-fill' : 'person-check' ?>"></i>
        Verified<?= !empty($s['usi_verified_method'])?' ('.e($s['usi_verified_method']).')':'' ?><?= !empty($s['usi_verified_date'])?' on '.e($s['usi_verified_date']):'' ?>.
        <?php if (!$byRegistry): ?>
          <div class="mt-1">
            This one was ticked by a person, not confirmed by the registry. Running the
            registry check gives you evidence that stands up on its own.
          </div>
        <?php endif; ?>
      </div>
      <?php if (!$byRegistry && $usiCfg['mode'] === 'live' && $usiCfg['configured'] && !empty($s['usi_number'])): ?>
        <form method="post" action="?r=usi_check" class="mb-2">
          <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
          <button class="btn btn-anb btn-sm"><i class="bi bi-shield-check"></i> Confirm with USI Registry</button>
        </form>
      <?php endif; ?>
      <form method="post" action="?r=usi_verify">
        <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
        <input type="hidden" name="unverify" value="1">
        <input type="hidden" name="reason" value="cleared from the student record">
        <button class="btn btn-sm btn-outline-secondary">Clear verification</button>
      </form>
    <?php else: ?>
      <div class="alert alert-danger py-2 small mb-2"><i class="bi bi-exclamation-triangle-fill"></i> Not verified - a certificate cannot be issued until the USI is verified.</div>

      <?php $lastRows = $usiLast ? anb_usi_check_rows($usiLast) : []; ?>
      <?php if ($usiLast): ?>
        <div class="border rounded p-2 mb-2" style="background:#fff8f8;">
          <div class="fw-bold small mb-1">What the registry said</div>
          <?php
            $status  = (string)($usiLast['status'] ?? '');
            $unknown = strcasecmp($status, 'Invalid') === 0;
          ?>
          <?php if (!empty($usiLast['error'])): ?>
            <div class="small text-danger mb-1"><?= e((string)$usiLast['error']) ?></div>
          <?php else: ?>
            <?php if ($status !== ''): ?>
              <div class="d-flex justify-content-between small border-bottom py-1">
                <span class="text-muted">USI itself</span>
                <span class="fw-semibold <?= strcasecmp($status,'Valid')===0 ? 'text-success' : 'text-danger' ?>">
                  <?= $unknown ? 'Not recognised' : e($status) ?>
                </span>
              </div>
            <?php endif; ?>
            <?php if ($unknown): ?>
              <div class="small text-muted mt-1">
                There is no such USI in the registry, so the name and date of birth were never
                compared. This is usually a wrong number rather than a wrong name.
              </div>
            <?php else: ?>
              <?php foreach ($lastRows as $row): ?>
                <div class="d-flex justify-content-between small border-bottom py-1">
                  <span class="text-muted"><?= e($row['label']) ?></span>
                  <span class="fw-semibold <?= $row['ok'] ? 'text-success' : 'text-danger' ?>">
                    <?= $row['ok'] ? 'Matches' : 'Does NOT match' ?>
                  </span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          <?php endif; ?>
          <a href="?r=usi_fix&id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-danger mt-2">
            <i class="bi bi-wrench-adjustable"></i> Fix this
          </a>
          <div class="text-muted mt-1" style="font-size:.7rem;">
            Checked <?= e((string)$usiLast['checked_at']) ?><?= $usiLast['checked_by'] ? ' by '.e((string)$usiLast['checked_by']) : '' ?>.
          </div>
        </div>
      <?php endif; ?>

      <?php if (empty($s['usi_number'])): ?>
        <div class="small text-muted">No USI on file yet - add the student's USI first, then verify.</div>
      <?php else: ?>
        <div class="small bg-light border rounded p-2 mb-2">
          <div><span class="text-muted">USI:</span> <strong><?= e($s['usi_number']) ?></strong></div>
          <div><span class="text-muted">Name:</span> <strong><?= e(trim($s['first_name'].' '.$s['last_name'])) ?></strong></div>
          <div><span class="text-muted">DOB:</span> <strong><?= e($s['date_of_birth'] ?: '—') ?></strong></div>
        </div>
        <?php $usiFormat = anb_usi_format_problem((string)$s['usi_number']); ?>
        <?php if ($usiFormat !== ''): ?>
          <div class="alert alert-warning py-2 small mb-2"><i class="bi bi-exclamation-triangle"></i> <?= e($usiFormat) ?></div>
        <?php endif; ?>
        <?php if ($usiCfg['mode'] === 'live' && $usiCfg['configured'] && $usiFormat === '' && !empty($s['date_of_birth'])): ?>
          <form method="post" action="?r=usi_check" class="mb-2">
            <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
            <button class="btn btn-anb btn-sm"><i class="bi bi-shield-check"></i> Verify with USI Registry</button>
          </form>
          <div class="small text-muted mb-2">Or check it by hand in the portal and tick below.</div>
        <?php else: ?>
          <div class="small mb-2">Check these details in your USI Organisation Portal (Verify USI), then tick below:</div>
        <?php endif; ?>
        <a href="https://www.usi.gov.au" target="_blank" rel="noopener" class="btn btn-sm btn-outline-danger mb-2"><i class="bi bi-box-arrow-up-right"></i> Open USI portal</a>
        <form method="post" action="?r=usi_verify">
          <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
          <input type="hidden" name="method" value="manual">
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="usiconfirm" required>
            <label class="form-check-label small" for="usiconfirm">I have verified this USI via the USI portal and it matches the student's details.</label>
          </div>
          <div class="mb-2">
            <label class="form-label small fw-bold">How was it checked?</label>
            <input class="form-control form-control-sm" name="reason" required
                   placeholder="e.g. checked in the USI Portal, 14 Aug, matched">
            <div class="form-text">
              Recorded against your name in the verification log. A tick with nothing behind it
              is what an auditor will ask about.
            </div>
          </div>
          <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-patch-check"></i> Mark verified by hand</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>

<?php /* --------------------------------------------------------------- NOTES */ ?>
<?php elseif ($tab === 'notes'): ?>
  <div class="card p-3" style="max-width:760px;">
    <h6 class="fw-bold mb-2">Staff notes</h6>
    <form method="post" action="?r=student_note_add" class="mb-3">
      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
      <textarea class="form-control mb-2" name="note" rows="3" required
                placeholder="Anything the next person needs to know about this student."></textarea>
      <button class="btn btn-anb btn-sm"><i class="bi bi-plus-lg"></i> Add note</button>
    </form>
    <?php if (!$notes): ?>
      <div class="text-muted small">No notes yet.</div>
    <?php else: foreach ($notes as $n): ?>
      <div class="border-bottom py-2">
        <div class="small"><?= nl2br(e((string)$n['note'])) ?></div>
        <div class="text-muted" style="font-size:.72rem;"><?= e((string)$n['created_by']) ?> · <?= e((string)$n['created_at']) ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>

<?php /* ----------------------------------------------------------- DOCUMENTS */ ?>
<?php elseif ($tab === 'documents'): ?>
  <div class="card p-3" style="max-width:720px;">
    <h6 class="fw-bold mb-2">Documents</h6>
    <p class="text-muted small mb-0">
      Nothing is stored against students yet. Certificates live under <a href="<?= e($tabUrl('records')) ?>">Records</a>,
      and the RTO's own compliance documents are under Compliance in the menu.
      If you want ID scans, workbooks or signed forms kept on the student record, say so and
      I will build it - it needs somewhere to store files and a rule about who can see them.
    </p>
  </div>

<?php /* ------------------------------------------------------------ LEARNING */ ?>
<?php elseif ($tab === 'learning'): ?>
  <?php if (!$learning): ?>
    <div class="card p-3"><div class="text-muted small">This student has no enrolments.</div></div>
  <?php endif; ?>
  <?php
    // Timestamps are written by SQLite datetime('now'), which is UTC.
    $when = fn(?string $t): string => $t ? date('D j M Y \a\t g:ia', strtotime($t.' UTC')) : '';
  ?>
  <?php foreach ($learning as $c): $en = $c['enrolment']; ?>
    <div class="card p-3 mb-3">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
          <h6 class="fw-bold mb-0"><?= e((string)$en['course_title']) ?></h6>
          <div class="small text-muted"><?= e((string)$en['course_code']) ?> · <?= e((string)$en['plan_title']) ?></div>
        </div>
        <span class="badge text-bg-<?= $c['complete'] ? 'success' : ($c['done'] ? 'warning' : 'secondary') ?>">
          <?= $c['complete'] ? 'Online learning complete' : ($c['done'] ? 'In progress' : 'Not started') ?>
        </span>
      </div>

      <div class="row small mb-2">
        <div class="col-md-6">
          <div><span class="text-muted">Enrolled:</span>
            <strong><?= e($when((string)$en['created_at']) ?: '—') ?></strong></div>
          <?php if (!empty($en['sched_date'])): ?>
            <div><span class="text-muted">Class:</span>
              <strong><?= e(date('D j M Y', strtotime((string)$en['sched_date']))) ?></strong></div>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <div><span class="text-muted">Online learning:</span>
            <strong>
              <?php if ($c['started_at'] === null): ?>
                not started
              <?php elseif ($c['complete']): ?>
                completed <?= e($when($c['last_at'])) ?>
              <?php else: ?>
                started <?= e($when($c['started_at'])) ?>
              <?php endif; ?>
            </strong>
          </div>
          <?php if ($c['last_at'] !== null): ?>
            <div><span class="text-muted">Last activity:</span>
              <strong><?= e($when($c['last_at'])) ?></strong></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="progress mb-2" style="height:20px">
        <div class="progress-bar bg-<?= $c['complete'] ? 'success' : 'warning' ?>"
             style="width: <?= (int)$c['pct'] ?>%"><?= (int)$c['pct'] ?>%</div>
      </div>
      <div class="small text-muted mb-2"><?= (int)$c['done'] ?> of <?= (int)$c['total'] ?> modules complete</div>

      <?php if (!$c['complete'] && $c['stopped']): ?>
        <div class="alert alert-warning py-2 small mb-2">
          <i class="bi bi-bookmark"></i> <strong>Stopped at:</strong>
          <?= e((string)$c['stopped']['module_title']) ?>
          <?php if (!empty($c['stopped']['updated_at'])): ?>
            — last touched <?= e($when((string)$c['stopped']['updated_at'])) ?>
          <?php else: ?>
            — never opened
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($c['modules']): ?>
        <a class="small" data-bs-toggle="collapse" href="#mods<?= (int)$en['id'] ?>">Show the <?= (int)$c['total'] ?> modules</a>
        <div class="collapse mt-2" id="mods<?= (int)$en['id'] ?>">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Module</th><th>Type</th><th>Status</th><th>Score</th><th>Attempts</th><th>Date &amp; time</th></tr></thead>
            <tbody>
            <?php foreach ($c['modules'] as $m):
              $st = (string)($m['status'] ?? '');
              $badge = $st === 'completed' ? 'success' : ($st !== '' ? 'warning' : 'secondary'); ?>
              <tr>
                <td class="small"><?= e((string)$m['module_title']) ?></td>
                <td class="small text-muted"><?= e((string)$m['module_type']) ?></td>
                <td><span class="badge text-bg-<?= $badge ?>"><?= e($st ?: 'not started') ?></span></td>
                <td class="small"><?= $m['score'] === null ? '—' : (int)$m['score'].'%' ?></td>
                <td class="small text-muted"><?= $m['attempts'] === null ? '—' : (int)$m['attempts'] ?></td>
                <td class="small text-muted"><?= e($when((string)($m['updated_at'] ?? '')) ?: '—') ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

<?php /* ------------------------------------------------------------ ACTIVITY */ ?>
<?php elseif ($tab === 'activity'): ?>
  <?php
    // LLN sits at the top because it is the gate: nothing else in the portal
    // opens until it is done, so it is the first thing to check when a
    // student says they cannot get in.
    $llnWhen = fn(?string $t): string => $t ? date('D j M Y \a\t g:ia', strtotime($t.' UTC')) : '';
  ?>
  <div class="card p-3 mb-3" style="max-width:820px;">
    <h6 class="fw-bold mb-2">LLN assessment</h6>
    <p class="small text-muted">
      Language, Literacy and Numeracy. It is the first thing every student has to complete, and
      the rest of their online learning stays locked until it is done.
    </p>
    <?php if (!$lln): ?>
      <div class="text-muted small">This student has no enrolments, so there is no LLN to record.</div>
    <?php else: ?>
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Course</th><th>Assessment</th><th>Result</th><th>Score</th><th>Attempts</th><th>Completed</th></tr></thead>
        <tbody>
        <?php foreach ($lln as $l):
          $done = $l['status'] === 'completed'; ?>
          <tr>
            <td class="small fw-semibold"><?= e($l['course_code']) ?></td>
            <td class="small text-muted"><?= e($l['module_title']) ?></td>
            <td>
              <span class="badge text-bg-<?= $done ? 'success' : ($l['status'] !== '' ? 'warning' : 'secondary') ?>">
                <?= $done ? 'Completed' : ($l['status'] !== '' ? e($l['status']) : 'Not started') ?>
              </span>
            </td>
            <td class="small">
              <?= $l['score'] === null ? '—' : (int)$l['score'].'%' ?>
              <?php if ($l['wrong_count'] > 0): ?>
                <div class="text-muted" style="font-size:.7rem;"><?= (int)$l['wrong_count'] ?> still wrong</div>
              <?php endif; ?>
            </td>
            <td class="small text-muted"><?= $l['attempts'] === null ? '—' : (int)$l['attempts'] ?></td>
            <td class="small text-muted"><?= e($llnWhen($l['done_at']) ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="small text-muted mt-2 mb-0">
        LLN is recorded as complete when it is submitted - it is there to identify who needs
        support, not to pass or fail anybody. The score and any wrong answers are kept so you can
        see who might struggle on the day.
      </p>
    <?php endif; ?>
  </div>

  <div class="card p-3" style="max-width:820px;">
    <h6 class="fw-bold mb-2">Activity</h6>
    <?php if (!$activity): ?>
      <div class="text-muted small">Nothing recorded for this student yet.</div>
    <?php else: foreach ($activity as $a): ?>
      <div class="d-flex gap-2 border-bottom py-2">
        <div class="text-muted"><i class="bi bi-<?= e($a['icon']) ?>"></i></div>
        <div class="flex-grow-1">
          <div class="small fw-semibold"><?= e($a['what']) ?></div>
          <?php if ($a['detail'] !== ''): ?><div class="small text-muted"><?= e($a['detail']) ?></div><?php endif; ?>
        </div>
        <div class="small text-muted text-nowrap"><?= e($a['when']) ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>

<?php /* ------------------------------------------------------------- RECORDS */ ?>
<?php elseif ($tab === 'records'): ?>
  <div class="card p-3 mb-3">
    <h6 class="fw-bold mb-2">Certificates</h6>
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Number</th><th>Course</th><th>Issued</th><th>Expires</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($certs as $c): $d = days_until($c['expiry_date']); ?>
        <tr>
          <td class="small fw-semibold"><?= e($c['certificate_number']) ?></td>
          <td class="small"><?= e($c['course_title']) ?></td>
          <td class="small"><?= e($c['issue_date']) ?></td>
          <td class="small"><?= e($c['expiry_date']) ?>
            <?php if ($d !== null && $d < 0): ?><span class="badge text-bg-danger">Expired</span>
            <?php elseif ($d !== null && $d <= 60): ?><span class="badge text-bg-warning">Soon</span><?php endif; ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-danger py-0" target="_blank" rel="noopener"
               href="?r=cert&num=<?= urlencode((string)$c['certificate_number']) ?>">
              <i class="bi bi-download"></i> Download
            </a>
            <?php if (!empty($s['email'])): ?>
              <a class="btn btn-sm btn-outline-secondary py-0"
                 href="?r=cert_email&id=<?= (int)$c['id'] ?>&student=<?= (int)$s['id'] ?>"
                 onclick="return confirm('Email this certificate to <?= e($s['email']) ?>?')">
                <i class="bi bi-envelope"></i> Email to student
              </a>
              <?php if (!empty($c['emailed_at'])): ?>
                <div class="text-success" style="font-size:.68rem;">sent <?= e((string)$c['emailed_at']) ?></div>
              <?php endif; ?>
            <?php else: ?>
              <div class="text-muted" style="font-size:.68rem;">no email on file</div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$certs): ?><tr><td colspan="5" class="text-muted small">No certificates issued yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card p-3">
    <h6 class="fw-bold mb-2">Unit outcomes</h6>
    <p class="small text-muted">These are what gets reported to the government in the AVETMISS submission.</p>
    <?php if (!$units): ?>
      <div class="text-muted small">No unit outcomes recorded.</div>
    <?php else: ?>
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Course</th><th>Unit</th><th>Outcome</th><th>Achieved</th></tr></thead>
        <tbody>
        <?php foreach ($units as $u): ?>
          <tr>
            <td class="small text-muted"><?= e((string)$u['course_code']) ?></td>
            <td class="small"><span class="fw-semibold"><?= e((string)$u['unit_code']) ?></span>
              <div class="text-muted"><?= e((string)$u['unit_title']) ?></div></td>
            <td class="small"><?= e(outcome_label((string)$u['outcome_national'])) ?></td>
            <td class="small text-muted"><?= e((string)($u['date_achieved'] ?: '—')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<?php /* ----------------------------------------------- TRAINING > ENROLMENTS */ ?>
<?php elseif ($tab === 'enrolments'): ?>
  <div class="card p-3">
    <h6 class="fw-bold mb-2">Enrolments</h6>
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Course / Plan</th><th>Schedule</th><th>Location</th><th>Status</th><th>Payment</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($enrolments as $en): ?>
        <tr>
          <td class="small fw-semibold"><?= e($en['course_code']) ?><div class="text-muted fw-normal"><?= e($en['course_title']) ?></div></td>
          <td class="small">
            <?php if (!empty($en['sched_id'])): ?>
              <a href="?r=pipeline&schedule_id=<?= (int)$en['sched_id'] ?>" title="Open this class and everyone in it">
                <?= e((string)$en['sched_date']) ?>
              </a>
              <div class="text-muted" style="font-size:.72rem;">
                <?= e(substr((string)$en['sched_time'],0,5)) ?><?= $en['sched_end'] ? '–'.e(substr((string)$en['sched_end'],0,5)) : '' ?>
              </div>
            <?php else: ?>
              <?= e((string)$en['start_date']) ?>
              <div class="text-muted" style="font-size:.72rem;">not in a class</div>
            <?php endif; ?>
          </td>
          <td class="small"><?= e((string)($en['sched_location'] ?: '—')) ?></td>
          <td><?= status_badge($en['status']) ?></td>
          <td><span class="badge text-bg-<?= $en['payment_status']==='paid'?'success':($en['payment_status']==='part'?'warning':'secondary') ?>"><?= ucfirst($en['payment_status']) ?></span></td>
          <td class="text-end">
            <?php if ($en['status'] !== 'issued'): ?>
              <a href="?r=enrol_move&id=<?= (int)$en['id'] ?>&from=<?= (int)$s['id'] ?>"
                 class="btn btn-sm btn-outline-secondary py-0" title="Move this student to a different class or date">
                <i class="bi bi-calendar-event"></i> Reschedule
              </a>
            <?php else: ?>
              <span class="text-muted" style="font-size:.72rem;">certificate issued</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$enrolments): ?><tr><td colspan="6" class="text-muted small">No enrolments.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

<?php /* ------------------------------------------------- TRAINING > UPCOMING */ ?>
<?php elseif ($tab === 'upcoming'): ?>
  <?php
    $today = date('Y-m-d');
    $soon  = array_values(array_filter($classes, fn($c) => (string)$c['start_date'] >= $today));
  ?>
  <div class="card p-3">
    <h6 class="fw-bold mb-2">Upcoming training</h6>
    <?php if (!$soon): ?>
      <div class="text-muted small">
        Nothing booked ahead. Everything this student has done is under
        <a href="<?= e($tabUrl('classes')) ?>">Classes</a>.
      </div>
    <?php else: ?>
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>When</th><th>Course</th><th>Location</th><th>Trainer</th><th>Ready?</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($soon as $c):
          $ready = $c['online_complete'] && $c['payment_status']==='paid' && $c['id_confirmed']
                && $c['attendance_marked'] && $c['tasks_satisfactory']; ?>
          <tr>
            <td class="small fw-semibold"><?= e(date('D j M Y', strtotime((string)$c['start_date']))) ?>
              <div class="text-muted fw-normal" style="font-size:.72rem;">
                <?= e(substr((string)$c['start_time'],0,5)) ?><?= $c['end_time'] ? '–'.e(substr((string)$c['end_time'],0,5)) : '' ?>
              </div></td>
            <td class="small"><?= e((string)$c['course_code']) ?></td>
            <td class="small text-muted"><?= e((string)($c['location'] ?: '—')) ?></td>
            <td class="small text-muted"><?= e((string)($c['trainer_name'] ?: 'unassigned')) ?></td>
            <td><span class="badge text-bg-<?= $ready ? 'success' : 'warning' ?>"><?= $ready ? 'Ready' : 'Pending' ?></span></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary py-0" href="?r=pipeline&schedule_id=<?= (int)$c['sched_id'] ?>">Open class</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<?php /* ------------------------------------------------- TRAINING > BOOKINGS */ ?>
<?php elseif ($tab === 'bookings'): ?>
  <div class="card p-3" style="max-width:820px;">
    <h6 class="fw-bold mb-2">Bookings</h6>
    <?php if (!$bookings): ?>
      <p class="text-muted small mb-0">
        No group booking against this student. Group bookings are where a company books a course
        for its staff - they are matched on the booking contact's email address, so a student
        booked under someone else's email will not show here. Their own enrolments are under
        <a href="<?= e($tabUrl('enrolments')) ?>">Enrolments</a>.
      </p>
    <?php else: ?>
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Booking</th><th>Contact</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td class="small fw-semibold">#<?= (int)$b['id'] ?>
              <div class="text-muted fw-normal"><?= e((string)($b['company_name'] ?? '')) ?></div></td>
            <td class="small text-muted"><?= e((string)($b['contact_email'] ?? '')) ?></td>
            <td class="small"><?= e((string)($b['status'] ?? '')) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary py-0" href="?r=group_booking_view&id=<?= (int)$b['id'] ?>">Open</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<?php /* -------------------------------------------------- TRAINING > CLASSES */ ?>
<?php elseif ($tab === 'classes'): ?>
  <div class="card p-3">
    <h6 class="fw-bold mb-2">Classes</h6>
    <?php if (!$classes): ?>
      <div class="text-muted small">
        This student is not attached to any class. Most migrated students are not - their training
        came across as history without a class record.
      </div>
    <?php else: ?>
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Date</th><th>Time</th><th>Course</th><th>Location</th><th>Trainer</th><th>Class size</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($classes as $c): ?>
          <tr>
            <td class="small fw-semibold"><?= e(date('D j M Y', strtotime((string)$c['start_date']))) ?></td>
            <td class="small text-muted"><?= e(substr((string)$c['start_time'],0,5)) ?><?= $c['end_time'] ? '–'.e(substr((string)$c['end_time'],0,5)) : '' ?></td>
            <td class="small"><?= e((string)$c['course_code']) ?>
              <div class="text-muted" style="font-size:.72rem;"><?= e((string)$c['plan_title']) ?></div></td>
            <td class="small text-muted"><?= e((string)($c['location'] ?: '—')) ?></td>
            <td class="small text-muted"><?= e((string)($c['trainer_name'] ?: 'unassigned')) ?></td>
            <td class="small text-muted"><?= (int)$c['booked'] ?><?= $c['total_places'] !== null ? ' / '.(int)$c['total_places'] : '' ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary py-0" href="?r=pipeline&schedule_id=<?= (int)$c['sched_id'] ?>">Open class</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<?php /* ---------------------------------------- TRAINING > OCCURRENCE PIPELINES */ ?>
<?php elseif ($tab === 'pipelines'): ?>
  <div class="card p-3">
    <h6 class="fw-bold mb-2">Occurrence pipelines</h6>
    <p class="small text-muted">
      Where this student sits in each of their classes - the same checks the class pipeline runs,
      but for them alone. Green means done, red means outstanding.
    </p>
    <?php if (!$classes): ?>
      <div class="text-muted small">This student is not attached to any class.</div>
    <?php else: ?>
      <?php
        $pdot = function (bool $ok, string $title): string {
            return '<span class="d-inline-block rounded-circle" title="'.htmlspecialchars($title, ENT_QUOTES).'"'
                 . ' style="width:14px;height:14px;background:'.($ok ? '#2e7d32' : '#E53935').'"></span>';
        };
      ?>
      <table class="table table-sm align-middle mb-0">
        <thead><tr>
          <th>Class</th><th class="text-center">Online</th><th class="text-center">USI</th>
          <th class="text-center">Paid</th><th class="text-center">ID</th>
          <th class="text-center">Attend.</th><th class="text-center">Tasks</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($classes as $c): ?>
          <tr>
            <td class="small fw-semibold"><?= e(date('D j M Y', strtotime((string)$c['start_date']))) ?>
              <div class="text-muted fw-normal" style="font-size:.72rem;"><?= e((string)$c['course_code']) ?><?= $c['location'] ? ' · '.e((string)$c['location']) : '' ?></div></td>
            <td class="text-center"><?= $pdot((bool)$c['online_complete'], 'Online modules') ?></td>
            <td class="text-center"><?= $pdot(!empty($s['usi_verified']), 'USI verified') ?></td>
            <td class="text-center"><?= $pdot($c['payment_status']==='paid', 'Paid') ?></td>
            <td class="text-center"><?= $pdot((bool)$c['id_confirmed'], 'ID sighted') ?></td>
            <td class="text-center">
              <?= $pdot((bool)$c['attendance_marked'], (string)($c['attendance_status'] ?: 'not marked')) ?>
              <?php if ((string)($c['attendance_status'] ?? '') === 'absent'): ?>
                <div class="text-danger" style="font-size:.62rem;">absent</div>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <?= $pdot((bool)$c['tasks_satisfactory'], (string)($c['tasks_status'] ?: 'not assessed')) ?>
              <?php if ((string)($c['tasks_status'] ?? '') === 'not_yet'): ?>
                <div class="text-danger" style="font-size:.62rem;">not yet</div>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary py-0" href="?r=pipeline&schedule_id=<?= (int)$c['sched_id'] ?>">Open pipeline</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<?php /* ------------------------------------------------ FINANCIAL > PAYMENTS */ ?>
<?php elseif ($tab === 'payments'): ?>
  <?php
    $paidRows = array_values(array_filter($financial, fn($f) => (float)$f['amount_paid'] > 0));
    $tot = 0.0; foreach ($paidRows as $f) $tot += (float)$f['amount_paid'];
  ?>
  <div class="card p-3" style="max-width:820px;">
    <h6 class="fw-bold mb-2">Payments received</h6>
    <?php if (!$paidRows): ?>
      <p class="text-muted small mb-0">
        Nothing recorded as received for this student.
      </p>
    <?php else: ?>
      <table class="table table-sm align-middle mb-2">
        <thead><tr><th>Course</th><th>Date</th><th>Received</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($paidRows as $f): ?>
          <tr>
            <td class="small fw-semibold"><?= e((string)$f['course_code']) ?>
              <div class="text-muted fw-normal"><?= e((string)$f['plan_title']) ?></div></td>
            <td class="small text-muted"><?= e((string)$f['start_date']) ?></td>
            <td class="small">$<?= number_format((float)$f['amount_paid'],2) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary py-0" target="_blank" rel="noopener"
                 href="?r=receipt&enrolment_id=<?= (int)$f['id'] ?>"><i class="bi bi-receipt"></i> Receipt link</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="fw-bold">Total received: $<?= number_format($tot,2) ?></div>
    <?php endif; ?>
    <p class="small text-muted mt-3 mb-0">
      This is the amount recorded against each enrolment, not a list of individual transactions.
      Card payments are taken by the website's checkout and the transaction detail stays with
      Stripe - the SMS only knows how much was paid, not when or by which card. Recording
      part-payments, refunds and payments taken over the phone is the invoicing job we discussed.
    </p>
  </div>

<?php /* ------------------------------------------------ FINANCIAL > INVOICES */ ?>
<?php elseif ($tab === 'invoices'): ?>
  <?php
    $due = 0.0; $paid = 0.0;
    foreach ($financial as $f) { $due += (float)$f['amount_due']; $paid += (float)$f['amount_paid']; }
  ?>
  <div class="card p-3" style="max-width:820px;">
    <h6 class="fw-bold mb-2">Financial</h6>
    <div class="row text-center g-2 mb-3">
      <div class="col"><div class="fs-5 fw-bold">$<?= number_format($due,2) ?></div><div class="small text-muted">charged</div></div>
      <div class="col"><div class="fs-5 fw-bold text-success">$<?= number_format($paid,2) ?></div><div class="small text-muted">paid</div></div>
      <div class="col"><div class="fs-5 fw-bold <?= ($due-$paid) > 0 ? 'text-danger' : '' ?>">$<?= number_format($due-$paid,2) ?></div><div class="small text-muted">outstanding</div></div>
    </div>
    <?php if (!$financial): ?>
      <div class="text-muted small">No enrolments to charge for.</div>
    <?php else: ?>
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Course</th><th>Date</th><th>Charged</th><th>Paid</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($financial as $f): ?>
          <tr>
            <td class="small fw-semibold"><?= e((string)$f['course_code']) ?>
              <div class="text-muted fw-normal"><?= e((string)$f['plan_title']) ?></div></td>
            <td class="small text-muted"><?= e((string)$f['start_date']) ?></td>
            <td class="small">$<?= number_format((float)$f['amount_due'],2) ?></td>
            <td class="small">$<?= number_format((float)$f['amount_paid'],2) ?></td>
            <td><span class="badge text-bg-<?= $f['payment_status']==='paid'?'success':($f['payment_status']==='part'?'warning':'secondary') ?>"><?= ucfirst((string)$f['payment_status']) ?></span></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary py-0" target="_blank" rel="noopener"
                 href="?r=receipt&enrolment_id=<?= (int)$f['id'] ?>"
                 title="<?= $f['payment_status']==='paid' ? 'Receipt' : 'Tax invoice - this one is not marked paid' ?>">
                <i class="bi bi-receipt"></i> Receipt link
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="small text-muted mt-2 mb-0">
        These figures come from what was charged at booking. There is no way to record a payment
        taken outside the website yet - that is the invoicing piece we talked about.
      </p>
    <?php endif; ?>
  </div>
<?php endif; ?>
