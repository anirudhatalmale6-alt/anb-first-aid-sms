<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Certificates</h4>
    <div class="text-muted small"><?= count($rows) ?> issued</div></div>
</div>
<div class="card p-3">
  <div class="table-responsive">
  <table class="table align-middle">
    <thead><tr><th>Number</th><th>Student</th><th>Course</th><th>Issued</th><th>Expires</th><th>Emailed</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c): $d = days_until($c['expiry_date']); ?>
      <tr>
        <td class="fw-semibold small">
          <a href="?r=cert&num=<?= urlencode($c['certificate_number']) ?>" target="_blank"><?= e($c['certificate_number']) ?> <i class="bi bi-file-earmark-pdf"></i></a>
        </td>
        <td class="small"><a href="?r=student&id=<?= (int)$c['student_id'] ?>" class="text-decoration-none"><?= e($c['first_name'].' '.$c['last_name']) ?></a></td>
        <td class="small"><?= e($c['course_code']) ?> — <?= e($c['course_title']) ?></td>
        <td class="small"><?= $c['issue_date'] ? date('d-m-Y', strtotime((string)$c['issue_date'])) : '' ?></td>
        <td class="small"><?= $c['expiry_date'] ? date('d-m-Y', strtotime((string)$c['expiry_date'])) : '' ?>
          <?php if ($d !== null && $d < 0): ?><span class="badge text-bg-danger">Expired</span>
          <?php elseif ($d !== null && $d <= 60): ?><span class="badge text-bg-warning"><?= $d ?>d</span><?php endif; ?></td>
        <td><?php if ($c['emailed_at']): ?><i class="bi bi-check-circle-fill text-success"></i><?php else: ?><span class="text-muted small">—</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
