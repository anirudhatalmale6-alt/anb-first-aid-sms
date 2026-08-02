<?php $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); ?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Practical Observation</h4>
    <div class="text-muted small"><?= e($module['course_code']) ?> — <?= e($module['title']) ?></div></div>
  <a href="?r=content" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to content</a>
</div>
<?php if ($flash): ?><div class="alert alert-success py-2"><?= e($flash) ?></div><?php endif; ?>
<div class="card p-3">
  <table class="table table-sm align-middle mb-0">
    <thead><tr><th>Learner</th><th class="text-center">Learner acknowledgement</th><th class="text-center">Observation result</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($learners as $L): ?>
      <tr>
        <td class="fw-semibold"><?= e($L['first_name'].' '.$L['last_name']) ?><div class="text-muted small"><?= e($L['email']) ?></div></td>
        <td class="text-center"><?php if ($L['ack']): ?><span class="badge text-bg-success">Acknowledged</span><?php else: ?><span class="badge text-bg-light border text-muted">Not yet</span><?php endif; ?></td>
        <td class="text-center">
          <?php if ($L['overall']==='satisfactory'): ?><span class="badge text-bg-success">Satisfactory</span>
          <?php elseif ($L['overall']==='not_yet'): ?><span class="badge text-bg-warning">Not yet satisfactory</span>
          <?php else: ?><span class="text-muted small">Not assessed</span><?php endif; ?>
        </td>
        <td class="text-end"><a href="?r=obs_mark&module_id=<?= (int)$module['id'] ?>&enrol=<?= (int)$L['enrolment_id'] ?>" class="btn btn-sm btn-anb"><i class="bi bi-check2-square"></i> <?= $L['overall']?'Update':'Mark' ?></a></td>
      </tr>
    <?php endforeach; if (!$learners): ?><tr><td colspan="4" class="text-muted small">No learners enrolled in this course yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
