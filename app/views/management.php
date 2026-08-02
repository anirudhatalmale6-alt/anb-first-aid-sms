<?php $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$icons = ['Meetings'=>'bi-people','Quality Improvement'=>'bi-graph-up-arrow','Complaints & Appeals'=>'bi-chat-left-dots',
          'Events'=>'bi-calendar-event','Document Management'=>'bi-folder','Compliance Management'=>'bi-shield-check']; ?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Management</h4>
    <div class="text-muted small">Store your organisation management files by category</div></div>
</div>
<?php if ($flash): ?><div class="alert alert-info py-2"><?= e($flash) ?></div><?php endif; ?>

<!-- category chips -->
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="?r=management" class="btn btn-sm <?= $cat===''?'btn-anb':'btn-outline-secondary' ?>">All <span class="badge text-bg-light border ms-1"><?= array_sum($counts) ?></span></a>
  <?php foreach ($cats as $c): ?>
    <a href="?r=management&cat=<?= urlencode($c) ?>" class="btn btn-sm <?= $cat===$c?'btn-anb':'btn-outline-secondary' ?>">
      <i class="bi <?= $icons[$c] ?? 'bi-folder' ?>"></i> <?= e($c) ?> <span class="badge text-bg-light border ms-1"><?= (int)($counts[$c] ?? 0) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card p-3">
      <h6 class="fw-bold mb-3"><i class="bi bi-upload text-danger"></i> Upload a file</h6>
      <form method="post" action="?r=management_upload" enctype="multipart/form-data">
        <label class="form-label small fw-semibold">Category</label>
        <select name="category" class="form-select mb-2" required>
          <option value="">Choose...</option>
          <?php foreach ($cats as $c): ?><option <?= $cat===$c?'selected':'' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
        <label class="form-label small fw-semibold">Title</label>
        <input name="title" class="form-control mb-2" placeholder="e.g. Q3 Team Meeting Minutes" required>
        <label class="form-label small fw-semibold">Notes (optional)</label>
        <textarea name="notes" class="form-control mb-2" rows="2"></textarea>
        <label class="form-label small fw-semibold">File</label>
        <input type="file" name="file" class="form-control mb-3" required>
        <button class="btn btn-anb w-100"><i class="bi bi-upload"></i> Upload</button>
      </form>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card p-3">
      <h6 class="fw-bold mb-2"><?= $cat!=='' ? e($cat) : 'All files' ?> <span class="text-muted small">(<?= count($rows) ?>)</span></h6>
      <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Title</th><th>Category</th><th>Uploaded</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $f): ?>
          <tr>
            <td class="small fw-semibold"><?= e($f['title']) ?>
              <div class="text-muted"><?= e($f['original_name']) ?></div>
              <?php if (!empty($f['notes'])): ?><div class="text-muted fst-italic"><?= e($f['notes']) ?></div><?php endif; ?></td>
            <td class="small"><span class="badge text-bg-light border"><?= e($f['category']) ?></span></td>
            <td class="small text-muted"><?= e(substr((string)$f['uploaded_at'],0,10)) ?></td>
            <td class="text-end" style="white-space:nowrap;">
              <a href="?r=management_download&id=<?= (int)$f['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i></a>
              <a href="?r=management_delete&id=<?= (int)$f['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this file?')"><i class="bi bi-trash"></i></a>
            </td>
          </tr>
        <?php endforeach; if (!$rows): ?><tr><td colspan="4" class="text-muted small">No files yet<?= $cat!==''?' in this category':'' ?> - upload one on the left.</td></tr><?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>
