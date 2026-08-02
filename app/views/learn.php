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

  <?php if ($module['type'] === 'scorm'):
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
