<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Schedules</h4>
    <div class="text-muted small">Upcoming &amp; recent classes</div></div>
  <a href="#" class="btn btn-anb"><i class="bi bi-plus-lg"></i> New schedule</a>
</div>
<div class="card p-3">
  <div class="table-responsive">
  <table class="table align-middle">
    <thead><tr><th>Course</th><th>Plan</th><th>Date &amp; time</th><th>Location</th><th>Enrolled</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $sc): ?>
      <tr>
        <td class="fw-semibold"><?= e($sc['course_code']) ?></td>
        <td class="small"><?= e($sc['plan_title']) ?></td>
        <td class="small"><?= e($sc['start_date']) ?><span class="text-muted"> <?= e(substr((string)$sc['start_time'],0,5)) ?>–<?= e(substr((string)$sc['end_time'],0,5)) ?></span></td>
        <td class="small"><?= e($sc['location'] ?: '—') ?></td>
        <td><span class="badge text-bg-light border"><?= (int)$sc['enrolled'] ?> / <?= (int)$sc['total_places'] ?></span></td>
        <td class="text-end"><a href="?r=pipeline&schedule_id=<?= (int)$sc['id'] ?>" class="btn btn-sm btn-anb"><i class="bi bi-list-check"></i> Pipeline</a></td>
      </tr>
    <?php endforeach; if(!$rows): ?><tr><td colspan="5" class="text-muted">No schedules.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
