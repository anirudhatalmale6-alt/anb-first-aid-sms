<?php
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
// work out what key details are still missing so the student can fix them
$missLabels = ['usi_number'=>'USI','date_of_birth'=>'Date of birth','gender'=>'Gender','mobile_phone'=>'Mobile',
  'street_name'=>'Street address','suburb'=>'Suburb','state'=>'State','postcode'=>'Postcode'];
$missing = [];
foreach ($missLabels as $k=>$lbl){ if (empty($me[$k])) $missing[] = $lbl; }
$certCount = count($mycerts); $courseCount = count($enrolments);
?>
<div style="background:#f4f5f7;min-height:100vh;">
  <!-- top bar -->
  <div style="background:linear-gradient(135deg,#2F1D3A,#4a2d5c);color:#fff;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 10px rgba(0,0,0,.12);flex-wrap:wrap;gap:8px;">
    <div style="display:flex;align-items:center;gap:12px;">
      <img src="assets/logo.png" alt="A&amp;B First Aid Training" style="height:40px;width:auto;">
      <div style="border-left:1px solid rgba(255,255,255,.25);padding-left:12px;font-size:.8rem;letter-spacing:.06em;opacity:.85;">STUDENT PORTAL</div>
    </div>
    <div style="font-size:.9rem;">
      <i class="bi bi-person-circle"></i> <?= e($me['first_name'].' '.$me['last_name']) ?>
      <a href="?r=student_logout" style="color:#ffb3b0;margin-left:14px;text-decoration:none;"><i class="bi bi-box-arrow-right"></i> Log out</a>
    </div>
  </div>

  <!-- welcome hero -->
  <div style="background:linear-gradient(135deg,#E53935,#8e24aa);color:#fff;padding:22px 20px;">
    <div style="max-width:960px;margin:0 auto;">
      <h4 class="fw-bold mb-1">Hi <?= e($me['first_name']) ?> 👋</h4>
      <div style="opacity:.92;font-size:.95rem;">Your courses, progress, certificates and details are all here.</div>
    </div>
  </div>

  <!-- tab bar -->
  <div style="background:#fff;border-bottom:1px solid #e5e2ea;box-shadow:0 2px 6px rgba(0,0,0,.04);">
    <div style="max-width:960px;margin:0 auto;display:flex;gap:4px;flex-wrap:wrap;padding:0 12px;">
      <?php foreach([['dashboard','Dashboard','bi-speedometer2'],['courses','My Courses','bi-mortarboard'],['certs','Certificates','bi-award'],['details','My Details','bi-person-vcard']] as $t): ?>
        <button class="antab" data-tab="<?= $t[0] ?>" style="background:none;border:0;border-bottom:3px solid transparent;padding:14px 16px;font-size:.92rem;font-weight:600;color:#6b6b6b;cursor:pointer;">
          <i class="bi <?= $t[2] ?>"></i> <?= $t[1] ?><?php if($t[0]==='details' && $missing): ?> <span class="badge text-bg-danger" style="font-size:.6rem;vertical-align:top;"><?= count($missing) ?></span><?php endif; ?>
        </button>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="max-width:960px;margin:0 auto;padding:22px 16px;">
    <?php if ($flash): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle-fill"></i> <?= e($flash) ?></div><?php endif; ?>

    <!-- ============ DASHBOARD ============ -->
    <div class="anpane" data-pane="dashboard">
      <div class="row g-3 mb-3">
        <div class="col-6 col-md-4"><div class="card stat-card p-3"><div class="num text-primary"><?= $courseCount ?></div><div class="text-muted small">My courses</div></div></div>
        <div class="col-6 col-md-4"><div class="card stat-card p-3"><div class="num text-success"><?= $certCount ?></div><div class="text-muted small">My certificates</div></div></div>
        <div class="col-12 col-md-4"><div class="card stat-card p-3"><div class="num" style="color:#8e24aa;"><?= $missing?count($missing):'0' ?></div><div class="text-muted small">Details to complete</div></div></div>
      </div>
      <?php if ($missing): ?>
      <div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle"></i> Please complete your <strong><?= e(implode(', ',$missing)) ?></strong>.
        <a href="#" class="antablink" data-tab="details">Update my details →</a></div>
      <?php endif; ?>
      <div class="card p-3">
        <h6 class="fw-bold mb-2" style="color:#2F1D3A;"><i class="bi bi-info-circle text-danger"></i> How your online learning works</h6>
        <ol class="small mb-0" style="padding-left:1.1rem;line-height:1.7;">
          <li>Open <strong>My Courses</strong>. Each course lists its modules in order.</li>
          <li>Click <strong>Start</strong>, read through, then <strong>Mark module complete &amp; continue</strong> — progress saves automatically.</li>
          <li>The <strong>Knowledge Quiz</strong> is split into short sections; you get <strong>3 attempts</strong>.</li>
          <li>Finish all modules before your face-to-face class. Your <strong>certificate</strong> appears under <strong>Certificates</strong> once your class is signed off.</li>
        </ol>
      </div>
    </div>

    <!-- ============ MY COURSES ============ -->
    <div class="anpane" data-pane="courses" style="display:none;">
      <?php foreach ($enrolments as $en):
        $total = (int)($en['modules_total'] ?? 0); $doneN = (int)($en['modules_done'] ?? 0);
        $pct = $total ? (int)round($doneN/$total*100) : ($en['online_complete'] ? 100 : ($en['status']==='enrolled' ? 40 : 100));
      ?>
        <div class="card p-3 mb-2">
          <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div>
              <div class="fw-semibold"><?= e($en['course_code']) ?> — <?= e($en['course_title']) ?></div>
              <div class="text-muted small"><?= e($en['plan_title']) ?></div>
              <?php if ($en['sched_date']): ?><div class="small mt-1"><i class="bi bi-calendar3"></i> <?= e($en['sched_date']) ?> <?= e(substr((string)$en['sched_time'],0,5)) ?> · <?= e($en['location']) ?></div><?php endif; ?>
            </div>
            <?= status_badge($en['status']) ?>
          </div>
          <div class="mt-2">
            <div class="d-flex justify-content-between small text-muted"><span>Online learning<?= $total?" ($doneN/$total modules)":'' ?></span><span><?= $pct ?>%</span></div>
            <div class="progress" style="height:8px;"><div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div></div>
          </div>
          <?php if (!empty($en['modules'])): $firstMod=$en['modules'][0]; $lnnDone=(($firstMod['progress']['status']??'')==='completed'); ?>
            <div class="mt-2">
            <?php foreach ($en['modules'] as $mi=>$m): $status=$m['progress']['status']??'not_started'; $done=$status==='completed'; $isFirst=($mi===0); $locked=(!$isFirst && !$lnnDone); ?>
              <div class="d-flex justify-content-between align-items-center py-1">
                <div class="small">
                  <i class="bi <?= $locked?'bi-lock':($m['type']==='quiz'?'bi-ui-checks-grid':($m['type']==='incident_report'?'bi-clipboard2-pulse':($m['type']==='practical'?'bi-clipboard2-check':'bi-play-btn'))) ?> text-muted"></i>
                  <?= e($m['title']) ?>
                  <?php if ($isFirst && !$done): ?><span class="badge text-bg-danger ms-1">Required first</span><?php endif; ?>
                  <?php if ($done): ?><span class="badge text-bg-success ms-1">Completed<?= $m['progress']['score']!==null?' · '.(int)$m['progress']['score'].'%':'' ?></span>
                  <?php elseif ($status==='in_progress'): ?><span class="badge text-bg-warning ms-1">In progress</span>
                  <?php elseif ($locked): ?><span class="badge text-bg-light border text-muted ms-1">Locked</span><?php endif; ?>
                </div>
                <?php if ($locked): ?>
                  <button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-lock"></i> Locked</button>
                <?php else: ?>
                  <a href="?r=learn&module_id=<?= (int)$m['id'] ?>" class="btn btn-sm <?= $done?'btn-outline-secondary':'btn-outline-danger' ?>"><i class="bi <?= $done?'bi-arrow-repeat':'bi-play-circle' ?>"></i> <?= $done?'Review':($status==='in_progress'?'Resume':'Start') ?></a>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            </div>
          <?php elseif (!$en['online_complete']): ?><div class="text-muted small mt-2">No online modules assigned to this course yet.</div><?php endif; ?>
        </div>
      <?php endforeach; if(!$enrolments): ?><div class="card p-3"><p class="text-muted small mb-0">You have no active courses.</p></div><?php endif; ?>
    </div>

    <!-- ============ CERTIFICATES ============ -->
    <div class="anpane" data-pane="certs" style="display:none;">
      <div class="card p-3">
        <h6 class="fw-bold mb-3"><i class="bi bi-award text-danger"></i> My Certificates</h6>
        <?php if ($mycerts): ?>
          <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Course</th><th>Certificate No</th><th>Issued</th><th>Expires</th><th></th></tr></thead><tbody>
            <?php foreach ($mycerts as $c): $d=days_until($c['expiry_date']); ?>
              <tr><td class="small fw-semibold"><?= e($c['course_title']) ?></td><td class="small"><?= e($c['certificate_number']) ?></td>
                <td class="small"><?= e($c['issue_date']) ?></td>
                <td class="small"><?= e($c['expiry_date']) ?><?php if ($d!==null && $d<0): ?> <span class="badge text-bg-danger">Expired</span><?php elseif ($d!==null && $d<=60): ?> <span class="badge text-bg-warning">Renew soon</span><?php endif; ?></td>
                <td class="text-end"><a href="?r=mycert&num=<?= urlencode($c['certificate_number']) ?>" class="btn btn-sm btn-anb"><i class="bi bi-download"></i> Download</a></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?><p class="text-muted small mb-0">No certificates yet — they'll appear here the moment your class is signed off.</p><?php endif; ?>
      </div>
    </div>

    <!-- ============ MY DETAILS ============ -->
    <div class="anpane" data-pane="details" style="display:none;">
      <?php if ($missing): ?><div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle"></i> These are still needed: <strong><?= e(implode(', ',$missing)) ?></strong>. Please add them below.</div><?php endif; ?>
      <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <h6 class="fw-bold mb-0"><i class="bi bi-person-vcard text-primary"></i> My Details</h6>
          <a href="?r=my_details" class="btn btn-anb btn-sm"><i class="bi bi-pencil-square"></i> Complete / update my details</a>
        </div>
        <table class="table table-sm mb-0"><tbody>
          <tr><th style="width:180px;">Name</th><td><?= e(trim($me['salutation'].' '.$me['first_name'].' '.$me['last_name'])) ?></td></tr>
          <tr><th>Email</th><td><?= e($me['email']) ?></td></tr>
          <tr><th>Mobile</th><td><?= e($me['mobile_phone']) ?: '<span class="text-danger">—</span>' ?></td></tr>
          <tr><th>Date of birth</th><td><?= e($me['date_of_birth']) ?: '<span class="text-danger">—</span>' ?></td></tr>
          <tr><th>USI</th><td><?= e($me['usi_number']) ?: '<span class="text-danger">Not provided</span>' ?></td></tr>
          <tr><th>Address</th><td><?= e(trim(($me['street_number']??'').' '.($me['street_name']??'').', '.($me['suburb']??'').' '.($me['state']??'').' '.($me['postcode']??''),' ,')) ?: '<span class="text-danger">—</span>' ?></td></tr>
        </tbody></table>
      </div>
    </div>

  </div>
</div>
<script>
(function(){
  function show(tab){
    document.querySelectorAll('.anpane').forEach(function(p){ p.style.display = (p.getAttribute('data-pane')===tab)?'':'none'; });
    document.querySelectorAll('.antab').forEach(function(b){ var on=b.getAttribute('data-tab')===tab; b.style.color=on?'#E53935':'#6b6b6b'; b.style.borderBottomColor=on?'#E53935':'transparent'; });
  }
  document.querySelectorAll('.antab').forEach(function(b){ b.addEventListener('click',function(){ show(b.getAttribute('data-tab')); }); });
  document.querySelectorAll('.antablink').forEach(function(a){ a.addEventListener('click',function(e){ e.preventDefault(); show(a.getAttribute('data-tab')); }); });
  show('dashboard');
})();
</script>
