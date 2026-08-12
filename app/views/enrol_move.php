<?php $flash=$_SESSION['flash']??null; unset($_SESSION['flash']); ?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Move / Transfer Enrolment</h4>
    <div class="text-muted small">Move a student to a different class or course</div></div>
  <a href="?r=enrolments" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Enrolments</a>
</div>
<?php if($flash): ?><div class="alert alert-info py-2"><i class="bi bi-info-circle"></i> <?= e($flash) ?></div><?php endif; ?>

<div class="card p-3 mb-3">
  <h6 class="fw-bold mb-1">Current enrolment</h6>
  <div class="small"><strong><?= e($en['first_name'].' '.$en['last_name']) ?></strong> — currently in <strong><?= e($en['course_code']) ?> <?= e($en['course_title']) ?></strong><?= $en['start_date']?(' · starts '.e($en['start_date'])):'' ?></div>
</div>

<div class="card p-3">
  <form method="post" action="?r=enrol_move_save"><input type="hidden" name="id" value="<?= (int)$en['id'] ?>">
    <h6 class="fw-bold mb-2">Move to this class / course</h6>
    <select name="schedule_id" class="form-select form-select-sm mb-3" required>
      <option value="">— select the new class —</option>
      <?php foreach($schedules as $s): ?>
        <option value="<?= (int)$s['id'] ?>"><?= e($s['code']) ?> — <?= e($s['title']) ?> · <?= e(date('D j M Y', strtotime((string)$s['start_date']))) ?><?= $s['start_time']?(' '.e(date('g:iA',strtotime((string)$s['start_time'])))):'' ?><?= $s['loc']?(' · '.e($s['loc'])):'' ?></option>
      <?php endforeach; ?>
    </select>
    <p class="small text-muted">This updates the student's course, class, location and date to match the class you choose. Their online progress and records stay with them.</p>
    <button class="btn btn-anb btn-sm" onclick="return confirm('Move this student to the selected class/course?')"><i class="bi bi-arrow-left-right"></i> Move enrolment</button>
  </form>
</div>
