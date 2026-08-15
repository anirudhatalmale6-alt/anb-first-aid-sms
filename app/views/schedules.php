<?php $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); ?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Schedules</h4>
    <div class="text-muted small">Upcoming &amp; recent classes</div></div>
</div>

<?php if ($flash): ?><div class="alert alert-info py-2"><?= e($flash) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card p-3">
      <h6 class="fw-bold mb-3"><i class="bi <?= $edit?'bi-pencil-square':'bi-calendar-plus' ?> text-danger"></i> <?= $edit ? 'Edit schedule' : 'New schedule' ?></h6>
      <form method="post" action="?r=schedule_save">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

        <label class="form-label small fw-semibold">Course / plan *</label>
        <select name="plan_id" class="form-select mb-2" required>
          <option value="">Choose a course…</option>
          <?php foreach ($plans as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= (isset($edit['plan_id']) && $edit['plan_id']==$p['id'])?'selected':'' ?>>
              <?= e($p['code']) ?> — <?= e($p['title']) ?></option>
          <?php endforeach; ?>
        </select>

        <label class="form-label small fw-semibold">Location</label>
        <select name="location_id" class="form-select mb-2">
          <option value="">— No location —</option>
          <?php foreach ($locations as $l): ?>
            <option value="<?= (int)$l['id'] ?>" <?= (isset($edit['location_id']) && $edit['location_id']==$l['id'])?'selected':'' ?>><?= e($l['name']) ?></option>
          <?php endforeach; ?>
        </select>

        <div class="row g-2">
          <div class="col-6"><label class="form-label small fw-semibold">Date *</label>
            <input type="date" name="start_date" class="form-control mb-2" value="<?= e($edit['start_date'] ?? '') ?>" required></div>
          <div class="col-6"><label class="form-label small fw-semibold">End date</label>
            <input type="date" name="end_date" class="form-control mb-2" value="<?= e($edit['end_date'] ?? '') ?>" placeholder="same day"></div>
        </div>

        <div class="row g-2">
          <div class="col-6"><label class="form-label small fw-semibold">Start time</label>
            <input type="time" name="start_time" class="form-control mb-2" value="<?= e($edit['start_time'] ?? '09:00') ?>"></div>
          <div class="col-6"><label class="form-label small fw-semibold">End time</label>
            <input type="time" name="end_time" class="form-control mb-2" value="<?= e($edit['end_time'] ?? '') ?>"></div>
        </div>

        <label class="form-label small fw-semibold">Trainer</label>
        <select name="trainer_id" class="form-select mb-2">
          <option value="">— Unassigned —</option>
          <?php foreach ($trainers as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= (isset($edit['trainer_id']) && $edit['trainer_id']==$t['id'])?'selected':'' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>

        <div class="row g-2 align-items-end">
          <div class="col-6"><label class="form-label small fw-semibold">Places</label>
            <input type="number" name="total_places" min="1" class="form-control mb-2" value="<?= (int)($edit['total_places'] ?? 15) ?>"></div>
        </div>

        <label class="form-label small fw-semibold">Class name <span class="text-muted fw-normal">(optional)</span></label>
        <input name="name" class="form-control mb-2" value="<?= e($edit['name'] ?? '') ?>" placeholder="Auto-filled from course + location">

        <?php if (!$edit): ?>
        <label class="form-label small fw-semibold"><i class="bi bi-arrow-repeat"></i> Repeat</label>
        <select name="repeat_weeks" class="form-select mb-3">
          <option value="1">Just this once</option>
          <option value="2">Weekly — for 2 weeks</option>
          <option value="3">Weekly — for 3 weeks</option>
          <option value="4">Weekly — for 4 weeks</option>
          <option value="6">Weekly — for 6 weeks</option>
          <option value="8">Weekly — for 8 weeks</option>
          <option value="12">Weekly — for 12 weeks</option>
        </select>
        <?php endif; ?>

        <button class="btn btn-anb w-100"><i class="bi bi-save"></i> <?= $edit ? 'Update schedule' : 'Create schedule' ?></button>
        <?php if ($edit): ?><a href="?r=schedules" class="btn btn-outline-secondary w-100 mt-2">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card p-3">
      <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <h6 class="fw-bold mb-0"><i class="bi bi-list-ul"></i>
          <?= ['today'=>"Today's classes",'week'=>'This week','students'=>'Classes with students'][$when] ?? 'All schedules' ?>
          <span class="text-muted small">(<?= count($rows) ?>)</span></h6>
        <div class="btn-group btn-group-sm" role="group">
          <?php foreach (['today'=>'Today','week'=>'This week','students'=>'With students',''=>'All'] as $k=>$lbl): ?>
            <a href="?r=schedules<?= $k ? '&when='.$k : '' ?>"
               class="btn btn-<?= $when===$k ? 'anb' : 'outline-secondary' ?>"><?= e($lbl) ?></a>
          <?php endforeach; ?>
        </div>
        <div class="input-group input-group-sm" style="max-width:260px;">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" id="schedSearch" class="form-control" placeholder="Filter by location…" autocomplete="off">
        </div>
      </div>
      <div id="schedNoMatch" class="text-muted small py-2" style="display:none;">No classes at that location.</div>

      <?php
      if (!$rows) {
          echo '<div class="text-muted small py-2">'
             . ($when === 'today'    ? 'No classes are scheduled for today.'
             : ($when === 'week'     ? 'No classes in the last or next seven days.'
             : ($when === 'students' ? 'No class has anybody booked into it yet.'
             :                         'No schedules yet — create your first one on the left.')))
             . '</div>';
      }
      $curKey = null;
      foreach ($rows as $sc):
          $loc = $sc['location'] ?: 'No location';
          $key = $sc['start_date'].'|'.$loc;
          if ($key !== $curKey):
              if ($curKey !== null) echo '</div>'; // close previous group body
              $curKey = $key;
              $dayLabel = strtoupper(date('l, j F Y', strtotime($sc['start_date'])));
      ?>
        <div class="sched-group" data-loc="<?= e(strtolower($loc)) ?>">
          <div class="fw-bold small mt-3 mb-1 pb-1 border-bottom" style="color:#2F1D3A;letter-spacing:.02em;">
            <i class="bi bi-geo-alt-fill text-danger"></i> <?= e($dayLabel) ?> &nbsp;·&nbsp; <span class="text-danger"><?= e(strtoupper($loc)) ?></span>
          </div>
      <?php endif; ?>
          <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <div class="small">
              <span class="text-muted"><?= e(date('g:iA', strtotime($sc['start_time'] ?: '00:00'))) ?><?= $sc['end_time'] ? ' – '.e(date('g:iA', strtotime($sc['end_time']))) : '' ?></span>
              &nbsp; <span class="fw-semibold"><?= e($sc['course_code']) ?></span> <?= e(ltrim(preg_replace('/^'.preg_quote($sc['course_code'],'/').'\s*/','',(string)$sc['plan_title']))) ?>
              <?php if ($sc['trainer_name']): ?><span class="text-muted"> · <?= e($sc['trainer_name']) ?></span><?php endif; ?>
              &nbsp;<span class="badge text-bg-light border"><?= (int)$sc['enrolled'] ?>/<?= (int)$sc['total_places'] ?> booked</span>
              <?php if ((int)($sc['no_login'] ?? 0) > 0): ?>
                &nbsp;<a href="?r=pipeline&schedule_id=<?= (int)$sc['id'] ?>" class="badge text-bg-danger text-decoration-none"
                   title="These students have not received their login details for the online modules"><i class="bi bi-envelope-exclamation"></i> <?= (int)$sc['no_login'] ?> no login</a>
              <?php endif; ?>
            </div>
            <div class="text-end" style="white-space:nowrap;">
              <button type="button" class="btn btn-sm btn-outline-success" title="Copy enrolment link to share" onclick="anbCopyEnrol(<?= (int)$sc['id'] ?>,this)"><i class="bi bi-link-45deg"></i> Link</button>
              <a href="?r=pipeline&schedule_id=<?= (int)$sc['id'] ?>" class="btn btn-sm btn-anb" title="Class pipeline"><i class="bi bi-list-check"></i></a>
              <a href="?r=schedule_duplicate&id=<?= (int)$sc['id'] ?>" class="btn btn-sm btn-outline-primary" title="Duplicate to next week"><i class="bi bi-files"></i></a>
              <a href="?r=schedules&edit=<?= (int)$sc['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
              <?php if ((int)$sc['enrolled'] === 0): ?>
                <a href="?r=schedule_delete&id=<?= (int)$sc['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this schedule?')"><i class="bi bi-trash"></i></a>
              <?php endif; ?>
            </div>
          </div>
      <?php endforeach; if ($rows) echo '</div>'; // close last group ?>
    </div>
  </div>
</div>
<script>
(function(){
  var box=document.getElementById('schedSearch'); if(!box) return;
  var groups=[].slice.call(document.querySelectorAll('.sched-group'));
  var noMatch=document.getElementById('schedNoMatch');
  box.addEventListener('input', function(){
    var q=box.value.trim().toLowerCase(), shown=0;
    groups.forEach(function(g){
      var hit = !q || (g.getAttribute('data-loc')||'').indexOf(q)>-1;
      g.style.display = hit ? '' : 'none';
      if(hit) shown++;
    });
    noMatch.style.display = (q && shown===0) ? 'block' : 'none';
  });
})();
function anbCopyEnrol(id, btn){
  var url = location.origin + '/?r=selfenrol&c=' + id;
  var done = function(){ var t=btn.innerHTML; btn.innerHTML='<i class="bi bi-check2"></i> Copied'; setTimeout(function(){btn.innerHTML=t;},1500); };
  if (navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText(url).then(done, function(){ window.prompt('Copy this enrolment link:', url); }); }
  else { window.prompt('Copy this enrolment link:', url); }
}
</script>
