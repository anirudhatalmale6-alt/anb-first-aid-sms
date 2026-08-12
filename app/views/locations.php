<?php $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); ?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Locations</h4>
    <div class="text-muted small">Your training venues - used in schedules, enrolments and AVETMISS reporting</div></div>
</div>

<?php if ($flash): ?><div class="alert alert-info py-2"><?= e($flash) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card p-3">
      <h6 class="fw-bold mb-3"><i class="bi <?= $edit?'bi-pencil-square':'bi-geo-alt' ?> text-danger"></i> <?= $edit ? 'Edit location' : 'Add a location' ?></h6>
      <form method="post" action="?r=location_save">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <label class="form-label small fw-semibold">Location name *</label>
        <input name="name" class="form-control mb-2" value="<?= e($edit['name'] ?? '') ?>" placeholder="e.g. St Marys Training Centre" required>
        <label class="form-label small fw-semibold">Street address</label>
        <input name="address" class="form-control mb-2" value="<?= e($edit['address'] ?? '') ?>" placeholder="e.g. 1 McFarlane St">
        <label class="form-label small fw-semibold">Identifier / code</label>
        <input name="identifier" class="form-control mb-2" value="<?= e($edit['identifier'] ?? '') ?>" placeholder="Optional short code">
        <label class="form-label small fw-semibold">Suburb</label>
        <input name="suburb" class="form-control mb-2" value="<?= e($edit['suburb'] ?? '') ?>">
        <div class="row g-2">
          <div class="col-6"><label class="form-label small fw-semibold">State</label>
            <input name="state" class="form-control mb-2" value="<?= e($edit['state'] ?? '') ?>" placeholder="NSW"></div>
          <div class="col-6"><label class="form-label small fw-semibold">Postcode</label>
            <input name="postcode" class="form-control mb-2" value="<?= e($edit['postcode'] ?? '') ?>" placeholder="2760"></div>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="active" id="loc_active" <?= (!$edit || $edit['active']) ? 'checked' : '' ?>>
          <label class="form-check-label small" for="loc_active">Active (available for new schedules)</label>
        </div>
        <button class="btn btn-anb w-100"><i class="bi bi-save"></i> <?= $edit ? 'Update location' : 'Add location' ?></button>
        <?php if ($edit): ?><a href="?r=locations" class="btn btn-outline-secondary w-100 mt-2">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card p-3">
      <h6 class="fw-bold mb-2"><i class="bi bi-list-ul"></i> All locations <span class="text-muted small">(<?= count($rows) ?>)</span></h6>
      <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Name</th><th>Address</th><th class="text-center">In&nbsp;use</th><th class="text-center">Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $l): ?>
          <tr class="<?= $l['active'] ? '' : 'text-muted' ?>">
            <td class="fw-semibold"><?= e($l['name']) ?>
              <?php if (!empty($l['identifier'])): ?><div class="text-muted small"><?= e($l['identifier']) ?></div><?php endif; ?></td>
            <td class="small"><?= e(trim(($l['address'] ?? '').' '.($l['suburb'] ?? '').' '.($l['state'] ?? '').' '.($l['postcode'] ?? ''), ' ,')) ?: '—' ?></td>
            <td class="text-center"><span class="badge text-bg-light border" title="Schedules using this location"><?= (int)$l['uses'] ?></span></td>
            <td class="text-center"><?php if ($l['active']): ?><span class="badge text-bg-success">Active</span><?php else: ?><span class="badge text-bg-secondary">Inactive</span><?php endif; ?></td>
            <td class="text-end" style="white-space:nowrap;">
              <a href="?r=locations&edit=<?= (int)$l['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
              <?php if ($l['active']): ?>
                <a href="?r=location_delete&id=<?= (int)$l['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Deactivate this location? It will stay on past records but won\'t appear for new schedules.')"><i class="bi bi-slash-circle"></i></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; if (!$rows): ?><tr><td colspan="5" class="text-muted small">No locations yet - add your first one on the left.</td></tr><?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>
