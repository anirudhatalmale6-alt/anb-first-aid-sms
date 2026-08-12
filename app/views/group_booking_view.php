<?php $flash=$_SESSION['flash']??null; unset($_SESSION['flash']);
$sb=['New'=>'danger','Quoted'=>'warning','Confirmed'=>'info','Completed'=>'success','Cancelled'=>'secondary']; ?>
<div class="topbar">
  <div><a href="?r=group_bookings" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Group Bookings</a>
    <h4 class="mb-0 fw-bold" style="color:#2F1D3A;"><?= e($b['company']) ?></h4></div>
  <span class="badge text-bg-<?= $sb[$b['status']]??'light' ?> fs-6"><?= e($b['status']) ?></span>
</div>
<?php if($flash): ?><div class="alert alert-info py-2"><i class="bi bi-info-circle"></i> <?= e($flash) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7"><div class="card p-3">
    <h6 class="fw-bold mb-2">Request details</h6>
    <table class="table table-sm mb-0"><tbody>
      <tr><th style="width:180px;">Received</th><td><?= e($b['created_at']) ?></td></tr>
      <tr><th>Company</th><td><?= e($b['company']) ?></td></tr>
      <tr><th>Contact</th><td><?= e($b['contact_name']) ?></td></tr>
      <tr><th>Email</th><td><?= e($b['email']) ?></td></tr>
      <tr><th>Phone</th><td><?= e($b['phone']) ?></td></tr>
      <tr><th>Course</th><td><?= e($b['course_label']) ?></td></tr>
      <tr><th>Participants</th><td><?= (int)$b['participants']?:'' ?></td></tr>
      <tr><th>Preferred date(s)</th><td><?= e($b['preferred_date']) ?></td></tr>
      <tr><th>Onsite location</th><td><?= e($b['location']) ?></td></tr>
      <tr><th>Attendees</th><td style="white-space:pre-line;"><?= e($b['attendees']) ?></td></tr>
      <tr><th>Notes</th><td style="white-space:pre-line;"><?= e($b['notes']) ?></td></tr>
    </tbody></table>
  </div></div>
  <div class="col-lg-5"><div class="card p-3">
    <h6 class="fw-bold mb-2">Manage</h6>
    <form method="post" action="?r=group_booking_save"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
      <label class="form-label small fw-semibold mb-0">Status</label>
      <select name="status" class="form-select form-select-sm mb-2"><?php foreach(['New','Quoted','Confirmed','Completed','Cancelled'] as $s): ?><option <?= $b['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
      <label class="form-label small fw-semibold mb-0">Staff notes</label>
      <textarea name="staff_notes" class="form-control form-control-sm mb-2" rows="6"><?= e($b['staff_notes']) ?></textarea>
      <button class="btn btn-anb btn-sm w-100"><i class="bi bi-save"></i> Save</button>
    </form>
    <a href="mailto:<?= e($b['email']) ?>" class="btn btn-outline-danger btn-sm w-100 mt-2"><i class="bi bi-envelope"></i> Email <?= e($b['contact_name']) ?></a>
  </div></div>
</div>
