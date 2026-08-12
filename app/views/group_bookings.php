<?php $flash=$_SESSION['flash']??null; unset($_SESSION['flash']);
$sb=['New'=>'danger','Quoted'=>'warning','Confirmed'=>'info','Completed'=>'success','Cancelled'=>'secondary']; ?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Group Bookings</h4>
    <div class="text-muted small">Corporate / onsite booking requests</div></div>
  <a href="?r=group_booking" target="_blank" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-up-right"></i> Open booking link</a>
</div>
<?php if($flash): ?><div class="alert alert-info py-2"><i class="bi bi-info-circle"></i> <?= e($flash) ?></div><?php endif; ?>

<div class="card p-2 mb-3">
  <div class="small">
    <a href="?r=group_bookings" class="btn btn-sm <?= $status===''?'btn-anb':'btn-outline-secondary' ?>">All</a>
    <?php foreach(['New','Quoted','Confirmed','Completed','Cancelled'] as $s): ?>
      <a href="?r=group_bookings&status=<?= $s ?>" class="btn btn-sm <?= $status===$s?'btn-anb':'btn-outline-secondary' ?>"><?= $s ?> (<?= (int)($counts[$s]??0) ?>)</a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card p-2 mb-3" style="background:#f8f4fb;">
  <div class="small"><i class="bi bi-link-45deg"></i> Private booking link to share with companies (not shown anywhere on your public website):
    <br><code>https://sms.anbfirstaidtraining.com.au/?r=group_booking</code></div>
</div>

<div class="card p-3">
  <div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>Received</th><th>Company</th><th>Contact</th><th>Course</th><th>Pax</th><th>Preferred date</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td class="small"><?= e(substr((string)$r['created_at'],0,10)) ?></td>
        <td class="small fw-semibold"><?= e($r['company']) ?></td>
        <td class="small"><?= e($r['contact_name']) ?><div class="text-muted"><?= e($r['email']) ?></div></td>
        <td class="small"><?= e($r['course_label']) ?></td>
        <td class="small"><?= (int)$r['participants']?:'' ?></td>
        <td class="small"><?= e($r['preferred_date']) ?></td>
        <td><span class="badge text-bg-<?= $sb[$r['status']]??'light' ?>"><?= e($r['status']) ?></span></td>
        <td class="text-end"><a href="?r=group_booking_view&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary py-0">Open</a></td>
      </tr>
    <?php endforeach; if(!$rows): ?><tr><td colspan="8" class="text-muted small">No group bookings yet. Share the private link above with companies.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
