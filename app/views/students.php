<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Students</h4>
    <div class="text-muted small"><?= count($rows) ?> record<?= count($rows)===1?'':'s' ?></div></div>
  <a href="?r=student_portal" class="btn btn-outline-danger"><i class="bi bi-envelope-paper"></i> Portal Access emails</a>
</div>
<div class="card p-3">
  <form class="mb-3" method="get">
    <input type="hidden" name="r" value="students">
    <div class="input-group" style="max-width:420px;">
      <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
      <input name="q" class="form-control" placeholder="Search name, USI or email" value="<?= e($q) ?>">
      <button class="btn btn-outline-secondary">Search</button>
    </div>
  </form>
  <div class="table-responsive">
  <table class="table align-middle">
    <thead><tr><th>Name</th><th>USI</th><th>Contact</th><th>Suburb</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $s): ?>
      <tr>
        <td class="fw-semibold"><?= e(trim($s['salutation'].' '.$s['first_name'].' '.$s['last_name'])) ?>
          <div class="text-muted small">DOB <?= e($s['date_of_birth']) ?> &middot; <?= e($s['gender']) ?></div></td>
        <td>
          <?php if ($s['usi_number']): ?><span class="badge text-bg-light border"><?= e($s['usi_number']) ?></span>
            <?php if ($s['usi_verified']): ?><i class="bi bi-patch-check-fill text-success" title="Verified"></i><?php endif; ?>
          <?php else: ?><span class="badge text-bg-warning">No USI</span><?php endif; ?>
        </td>
        <td class="small"><?= e($s['email']) ?><br><span class="text-muted"><?= e($s['mobile_phone']) ?></span></td>
        <td class="small"><?= e($s['suburb']) ?>, <?= e($s['state']) ?></td>
        <td class="text-end"><a href="?r=student&id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-secondary">Open</a></td>
      </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="5" class="text-muted">No students found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
