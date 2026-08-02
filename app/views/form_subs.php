<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Incident Report submissions</h4>
    <div class="text-muted small"><?= e($module['course_code']) ?> — <?= e($module['title']) ?></div></div>
  <a href="?r=content" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to content</a>
</div>
<div class="card p-3">
  <table class="table table-sm align-middle mb-0">
    <thead><tr><th>Student</th><th>Submitted</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($subs as $s): ?>
      <tr>
        <td class="fw-semibold"><?= e($s['first_name'].' '.$s['last_name']) ?><div class="text-muted small"><?= e($s['email']) ?></div></td>
        <td class="small"><?= e($s['updated_at']) ?></td>
        <td class="text-end"><a href="?r=form_view&sub=<?= (int)$s['id'] ?>" class="btn btn-sm btn-anb"><i class="bi bi-eye"></i> View</a></td>
      </tr>
    <?php endforeach; if (!$subs): ?><tr><td colspan="3" class="text-muted small">No submissions yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
