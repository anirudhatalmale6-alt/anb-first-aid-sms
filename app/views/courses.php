<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Courses</h4>
    <div class="text-muted small">Nationally recognised training</div></div>
  <a href="#" class="btn btn-anb"><i class="bi bi-plus-lg"></i> New course</a>
</div>
<div class="card p-3">
  <div class="table-responsive">
  <table class="table align-middle">
    <thead><tr><th>Code</th><th>Title</th><th>Category</th><th>Validity</th><th>Plans</th><th>Enrolments</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c): ?>
      <tr>
        <td class="fw-semibold"><?= e($c['code']) ?></td>
        <td class="small"><?= e($c['title']) ?></td>
        <td class="small"><?= e($c['category']) ?></td>
        <td class="small"><?= $c['validity_months'] ? (int)$c['validity_months'].' months' : '—' ?></td>
        <td><span class="badge text-bg-light border"><?= (int)$c['plans'] ?></span></td>
        <td><span class="badge text-bg-light border"><?= (int)$c['enrolments'] ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
