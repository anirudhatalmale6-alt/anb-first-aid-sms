<?php
/** LMS player: SCORM iframe (with a JS SCORM API adapter) or a quiz. */
$backUrl = !empty($_SESSION['student_id']) ? '?r=my' : '?r=content';
$isPreview = empty($enrolment);
?>
<div style="background:#f4f5f7;min-height:100vh;">
  <div style="background:#2F1D3A;color:#fff;padding:12px 24px;display:flex;justify-content:space-between;align-items:center;">
    <div style="display:flex;align-items:center;gap:10px;">
      <span class="logo-badge">A&amp;B</span>
      <div><div style="font-weight:700;"><?= e($module['title']) ?></div>
        <div style="font-size:.8rem;opacity:.8;"><?= e($module['course_code']) ?> — <?= e($module['course_title']) ?></div></div>
    </div>
    <a href="<?= e($backUrl) ?>" style="color:#ffb3b0;text-decoration:none;"><i class="bi bi-arrow-left"></i> Back</a>
  </div>

  <div style="max-width:900px;margin:0 auto;padding:22px 16px;">
  <?php if ($isPreview): ?>
    <div class="alert alert-info py-2 small"><i class="bi bi-eye"></i> Preview mode (staff) — progress is not recorded.</div>
  <?php endif; ?>

  <?php if ($module['type'] === 'incident_report'):
      $fv = $submission['fields'] ?? [];
      $ro = !empty($readonly); $dis = $ro ? 'disabled' : '';
      $natureOpts = ['Abrasion, scrapes','Amputation','Broken bone','Bruise','Burn (heat)','Burn (chemical)',
        'Concussion (to the head)','Crushing Injury','Cut, laceration, puncture','Hernia','Illness',
        'Sprain, strain','Damage to a body system','Shock'];
      $natureSel = (array)($fv['nature'] ?? []);
      $V = static function($k) use ($fv){ return e($fv[$k] ?? ''); };
  ?>
    <?php if (!empty($justSaved)): ?>
      <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Your incident report has been submitted<?= $isPreview?' (preview - not saved)':' and saved to your assessment' ?>. You can update and resubmit any time before your class.</div>
    <?php endif; ?>
    <?php if ($ro && !empty($viewStudent)): ?>
      <div class="alert alert-secondary py-2"><i class="bi bi-person"></i> Submission by <strong><?= e($viewStudent['first_name'].' '.$viewStudent['last_name']) ?></strong><?= !empty($submission['updated_at'])?' &middot; '.e($submission['updated_at']):'' ?></div>
    <?php endif; ?>

    <?php if (!empty($module['body'])): ?>
      <div class="card p-3 mb-3" style="border-left:4px solid #E53935;">
        <h6 class="fw-bold mb-2" style="color:#2F1D3A;"><i class="bi bi-clipboard2-pulse text-danger"></i> First Aid Practical Scenario</h6>
        <div class="small" style="white-space:pre-line;"><?= e($module['body']) ?></div>
      </div>
    <?php endif; ?>

    <form method="post" action="?r=form_submit">
      <input type="hidden" name="module_id" value="<?= (int)$module['id'] ?>">
      <div class="card p-3 mb-3">
        <h6 class="fw-bold mb-3" style="color:#2F1D3A;">Incident Report</h6>
        <div class="row g-2">
          <div class="col-md-4"><label class="form-label small fw-semibold">Date</label><input <?=$dis?>name="f[date]" class="form-control form-control-sm" value="<?= $V('date') ?>"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Time</label><input <?=$dis?>name="f[time]" class="form-control form-control-sm" value="<?= $V('time') ?>"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Location of incident</label><input <?=$dis?>name="f[location]" class="form-control form-control-sm" value="<?= $V('location') ?>"></div>
        </div>
        <hr class="my-3">
        <div class="row g-2">
          <div class="col-md-4"><label class="form-label small fw-semibold">Surname</label><input <?=$dis?>name="f[surname]" class="form-control form-control-sm" value="<?= $V('surname') ?>"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Given name</label><input <?=$dis?>name="f[given_name]" class="form-control form-control-sm" value="<?= $V('given_name') ?>"></div>
          <div class="col-md-2"><label class="form-label small fw-semibold">Sex</label>
            <select <?=$dis?>name="f[sex]" class="form-select form-select-sm">
              <?php foreach (['','Female','Male'] as $sx): ?><option <?= (($fv['sex']??'')===$sx)?'selected':'' ?>><?= $sx ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-md-2"><label class="form-label small fw-semibold">DOB</label><input <?=$dis?>name="f[dob]" class="form-control form-control-sm" value="<?= $V('dob') ?>"></div>
        </div>
        <div class="row g-2 mt-1">
          <div class="col-md-8"><label class="form-label small fw-semibold">Address</label><input <?=$dis?>name="f[address]" class="form-control form-control-sm" value="<?= $V('address') ?>"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Postcode</label><input <?=$dis?>name="f[postcode]" class="form-control form-control-sm" value="<?= $V('postcode') ?>"></div>
        </div>
        <div class="row g-2 mt-1">
          <div class="col-md-6"><label class="form-label small fw-semibold">Allergies</label><input <?=$dis?>name="f[allergies]" class="form-control form-control-sm" value="<?= $V('allergies') ?>"></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Medications</label><input <?=$dis?>name="f[medications]" class="form-control form-control-sm" value="<?= $V('medications') ?>"></div>
        </div>
        <div class="mt-2"><label class="form-label small fw-semibold">Incident / Injury</label><input <?=$dis?>name="f[incident_injury]" class="form-control form-control-sm" value="<?= $V('incident_injury') ?>"></div>
        <div class="mt-2"><label class="form-label small fw-semibold">Part of the body affected</label><input <?=$dis?>name="f[body_part]" class="form-control form-control-sm" value="<?= $V('body_part') ?>"></div>

        <label class="form-label small fw-semibold mt-3">Nature of injury (most serious) — tick all that apply</label>
        <div class="row g-1">
          <?php foreach ($natureOpts as $no): ?>
            <div class="col-md-4 col-sm-6">
              <label class="d-block small"><input <?=$dis?>class="form-check-input me-1" type="checkbox" name="f[nature][]" value="<?= e($no) ?>" <?= in_array($no,$natureSel,true)?'checked':'' ?>> <?= e($no) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <label class="form-label small fw-semibold">Assessment — identify the injury type, recognise the condition developing, and describe the correct first aid management (minor wound + shock)</label>
        <textarea <?=$dis?>name="f[assessment_answer]" class="form-control" rows="5"><?= $V('assessment_answer') ?></textarea>
        <label class="form-label small fw-semibold mt-3">Treatment given</label>
        <textarea <?=$dis?>name="f[treatment]" class="form-control" rows="3"><?= $V('treatment') ?></textarea>
        <label class="form-label small fw-semibold mt-3">Incident outcome</label>
        <textarea <?=$dis?>name="f[outcome]" class="form-control" rows="2"><?= $V('outcome') ?></textarea>
        <div class="row g-2 mt-1">
          <div class="col-md-4"><label class="form-label small fw-semibold">Incident reported to</label><input <?=$dis?>name="f[reported_to]" class="form-control form-control-sm" value="<?= $V('reported_to') ?>"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Name of first aider</label><input <?=$dis?>name="f[first_aider]" class="form-control form-control-sm" value="<?= $V('first_aider') ?>"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Signature (type your name)</label><input <?=$dis?>name="f[signature]" class="form-control form-control-sm" value="<?= $V('signature') ?>"></div>
        </div>
      </div>

      <?php if (!$ro): ?>
        <button class="btn btn-anb w-100 mb-4"><i class="bi bi-send"></i> <?= $submission ? 'Update &amp; resubmit report' : 'Submit incident report' ?></button>
      <?php else: ?>
        <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary mb-4"><i class="bi bi-arrow-left"></i> Back</a>
      <?php endif; ?>
    </form>

  <?php elseif ($module['type'] === 'scorm'):
      $src = '/'.lms_scorm_url_prefix().'/'.trim($module['scorm_dir'],'/').'/'.ltrim($module['launch_url'],'/');
  ?>
    <div class="card p-2 mb-2">
      <iframe id="scoframe" src="<?= e($src) ?>" style="width:100%;height:520px;border:0;border-radius:10px;"></iframe>
    </div>
    <div id="scoStatus" class="small text-muted"><i class="bi bi-hourglass-split"></i> Module in progress…</div>

    <script>
    // ---- SCORM run-time API adapter (this window is the LMS to the content iframe) ----
    var CMI = {"cmi.core.lesson_status":"not attempted","cmi.core.score.raw":"",
               "cmi.core.student_name":"Learner","cmi.core.credit":"credit","cmi.core.entry":"ab-initio",
               "cmi.completion_status":"unknown","cmi.success_status":"unknown","cmi.score.raw":""};
    var reported = false;
    function normStatus(){
      var s = (CMI["cmi.core.lesson_status"]||"").toLowerCase();
      var c = (CMI["cmi.completion_status"]||"").toLowerCase();
      if (s==="completed"||s==="passed"||c==="completed"||c==="passed") return "completed";
      return "in_progress";
    }
    function report(){
      var st = normStatus();
      var score = CMI["cmi.core.score.raw"]||CMI["cmi.score.raw"]||"";
      fetch('?r=scorm_track',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'module_id=<?= (int)$module['id'] ?>&status='+encodeURIComponent(st)+'&score='+encodeURIComponent(score)})
        .then(function(r){return r.json();}).then(function(){
          if (st==="completed" && !reported){
            reported = true;
            document.getElementById('scoStatus').innerHTML =
              '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill"></i> Completion recorded'
              + <?= $isPreview ? "' (preview - not saved)'" : "''" ?> + '.</span> '
              + '<a href="<?= e($backUrl) ?>" class="ms-2">Back to my learning</a>';
          }
        });
    }
    // SCORM 1.2
    window.API = {
      LMSInitialize:function(){return "true";},
      LMSFinish:function(){report();return "true";},
      LMSGetValue:function(k){return CMI[k]!==undefined?CMI[k]:"";},
      LMSSetValue:function(k,v){CMI[k]=v; if(k.indexOf("status")>-1||k.indexOf("score")>-1) report(); return "true";},
      LMSCommit:function(){report();return "true";},
      LMSGetLastError:function(){return "0";},
      LMSGetErrorString:function(){return "No error";},
      LMSGetDiagnostic:function(){return "";}
    };
    // SCORM 2004
    window.API_1484_11 = {
      Initialize:function(){return "true";},
      Terminate:function(){report();return "true";},
      GetValue:function(k){return CMI[k]!==undefined?CMI[k]:"";},
      SetValue:function(k,v){CMI[k]=v; if(k.indexOf("status")>-1||k.indexOf("score")>-1) report(); return "true";},
      Commit:function(){report();return "true";},
      GetLastError:function(){return "0";},
      GetErrorString:function(){return "No error";},
      GetDiagnostic:function(){return "";}
    };
    </script>

  <?php elseif (isset($quizResult)):
      $qr = $quizResult; ?>
    <div class="card p-4 mb-3 text-center">
      <div style="font-size:2.6rem;font-weight:800;color:<?= $qr['passed']?'#2e7d32':'#c62828' ?>;"><?= $qr['pct'] ?>%</div>
      <div class="mb-2"><?= $qr['correct'] ?> of <?= $qr['total'] ?> correct · pass mark <?= (int)$module['pass_mark'] ?>%</div>
      <?php if ($qr['passed']): ?>
        <span class="badge text-bg-success fs-6">Passed<?= $isPreview?'' : ' — completion recorded' ?></span>
      <?php else: ?>
        <span class="badge text-bg-danger fs-6">Not passed — please review and try again</span>
      <?php endif; ?>
    </div>
    <div class="card p-3 mb-3">
      <?php foreach ($questions as $i=>$q): $ok = $qr['per'][$q['id']] ?? false; ?>
        <div class="d-flex gap-2 py-2 border-bottom">
          <i class="bi <?= $ok?'bi-check-circle-fill text-success':'bi-x-circle-fill text-danger' ?>"></i>
          <div class="small"><?= ($i+1).'. '.e($q['question']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center">
      <a href="?r=learn&module_id=<?= (int)$module['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat"></i> Retake</a>
      <a href="<?= e($backUrl) ?>" class="btn btn-anb"><i class="bi bi-arrow-left"></i> Back to my learning</a>
    </div>

  <?php else: // quiz form ?>
    <div class="card p-4">
      <h5 class="fw-bold mb-1" style="color:#2F1D3A;"><?= e($module['title']) ?></h5>
      <p class="text-muted small">Answer all questions. You need <?= (int)$module['pass_mark'] ?>% to pass.</p>
      <?php if (!$questions): ?>
        <p class="text-muted">No questions have been added to this quiz yet.</p>
      <?php else: ?>
      <form method="post" action="?r=quiz_submit">
        <input type="hidden" name="module_id" value="<?= (int)$module['id'] ?>">
        <?php foreach ($questions as $i=>$q):
          $opts = (array)json_decode($q['options'] ?? '[]', true);
          $multi = $q['qtype']==='multi'; ?>
          <div class="mb-4">
            <div class="fw-semibold mb-2"><?= ($i+1).'. '.e($q['question']) ?>
              <?php if ($multi): ?><span class="badge text-bg-light text-muted">select all that apply</span><?php endif; ?></div>
            <?php foreach ($opts as $oi=>$ot): ?>
              <label class="d-block border rounded px-3 py-2 mb-2" style="cursor:pointer;">
                <input class="form-check-input me-2" type="<?= $multi?'checkbox':'radio' ?>"
                       name="a[<?= (int)$q['id'] ?>]<?= $multi?'[]':'' ?>" value="<?= $oi ?>" <?= $multi?'':'required' ?>>
                <?= e($ot) ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <button class="btn btn-anb w-100"><i class="bi bi-send"></i> Submit answers</button>
      </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  </div>
</div>
