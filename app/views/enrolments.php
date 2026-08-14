<?php
/**
 * Enrolments, searchable.
 *
 * @var array $rows    the current page
 * @var int   $total   matching rows across all pages
 * @var int   $page,$pages
 * @var string $q,$fStatus,$fPay,$fWhen
 * @var int   $fCourse
 * @var array $courses for the course filter
 */
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

/** Keep the current filters when moving between pages. */
$qs = function (array $over = []) use ($q, $fStatus, $fPay, $fCourse, $fWhen, $page) {
    $p = array_merge([
        'r' => 'enrolments', 'q' => $q, 'status' => $fStatus, 'pay' => $fPay,
        'course' => $fCourse ?: '', 'when' => $fWhen, 'page' => $page,
    ], $over);
    return '?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== 0));
};
?>
<div class="topbar">
  <div>
    <h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Enrolments</h4>
    <div class="text-muted small">
      <?= number_format($total) ?> <?= ($q !== '' || $fStatus || $fPay || $fCourse || $fWhen) ? 'matching' : 'total' ?>
    </div>
  </div>
  <a href="?r=enrol_new" class="btn btn-anb"><i class="bi bi-journal-plus"></i> New enrolment</a>
</div>

<?php if ($flash): ?><div class="alert alert-info py-2"><?= e($flash) ?></div><?php endif; ?>

<div class="card p-3 mb-3">
  <form method="get" class="row g-2 align-items-end">
    <input type="hidden" name="r" value="enrolments">
    <div class="col-md-4">
      <label class="form-label small fw-bold mb-1">Find a student</label>
      <input class="form-control" name="q" value="<?= e($q) ?>"
             placeholder="name, email, USI or course code" autofocus>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Class</label>
      <select class="form-select" name="when">
        <option value="">Any</option>
        <option value="upcoming" <?= $fWhen==='upcoming'?'selected':'' ?>>Still to run</option>
        <option value="past"     <?= $fWhen==='past'?'selected':'' ?>>Already run</option>
        <option value="none"     <?= $fWhen==='none'?'selected':'' ?>>Not in a class</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Status</label>
      <select class="form-select" name="status">
        <option value="">Any</option>
        <?php foreach (['enrolled','complete','issued','incomplete','withdrawn'] as $st): ?>
          <option value="<?= $st ?>" <?= $fStatus===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Payment</label>
      <select class="form-select" name="pay">
        <option value="">Any</option>
        <option value="paid"   <?= $fPay==='paid'?'selected':'' ?>>Paid</option>
        <option value="part"   <?= $fPay==='part'?'selected':'' ?>>Part paid</option>
        <option value="unpaid" <?= $fPay==='unpaid'?'selected':'' ?>>Unpaid</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Course</label>
      <select class="form-select" name="course">
        <option value="">Any</option>
        <?php foreach ($courses as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $fCourse===(int)$c['id']?'selected':'' ?>><?= e($c['code']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12">
      <button class="btn btn-anb btn-sm"><i class="bi bi-search"></i> Search</button>
      <a href="?r=enrolments" class="btn btn-link btn-sm">Clear</a>
    </div>
  </form>
</div>

<div class="card p-3">
  <div class="table-responsive">
  <table class="table align-middle">
    <thead><tr><th>Student</th><th>Course / Plan</th><th>Schedule</th><th>Location</th><th>Status</th><th>Payment</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $e): ?>
      <tr>
        <td class="fw-semibold">
          <a href="?r=student&id=<?= (int)$e['student_id'] ?>" class="text-decoration-none"><?= e($e['first_name'].' '.$e['last_name']) ?></a>
          <div class="text-muted small fw-normal"><?= e((string)$e['email']) ?></div>
        </td>
        <td class="small"><span class="fw-semibold"><?= e($e['course_code']) ?></span><div class="text-muted"><?= e($e['plan_title']) ?></div></td>
        <td class="small">
          <?php if (!empty($e['schedule_id']) && $e['sched_date']): ?>
            <a href="?r=pipeline&schedule_id=<?= (int)$e['schedule_id'] ?>" class="text-decoration-none">
              <?= e((string)$e['sched_date']) ?>
            </a>
            <?php if ($e['sched_time']): ?>
              <div class="text-muted" style="font-size:.72rem;"><?= e(substr((string)$e['sched_time'],0,5)) ?></div>
            <?php endif; ?>
          <?php else: ?>
            <span class="text-muted">not in a class</span>
          <?php endif; ?>
        </td>
        <td class="small"><?= e($e['location'] ?: '—') ?></td>
        <td><?= status_badge($e['status']) ?></td>
        <td><span class="badge text-bg-<?= $e['payment_status']==='paid'?'success':($e['payment_status']==='part'?'warning':'secondary') ?>"><?= ucfirst($e['payment_status']) ?></span>
            <div class="text-muted small">$<?= number_format((float)$e['amount_paid'],2) ?> / $<?= number_format((float)$e['amount_due'],2) ?></div></td>
        <td class="text-end">
          <?php if ($e['status'] !== 'issued'): ?>
            <a href="?r=enrol_move&id=<?= (int)$e['id'] ?>" class="btn btn-sm btn-outline-secondary py-0"
               title="Move to another class or date"><i class="bi bi-calendar-event"></i> Reschedule</a>
          <?php else: ?>
            <span class="text-muted small">issued</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; if(!$rows): ?>
      <tr><td colspan="7" class="text-muted p-3">
        Nothing matches that. <a href="?r=enrolments">Clear the search</a> to see everything.
      </td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>

  <?php if ($pages > 1): ?>
    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
      <div class="small text-muted">Page <?= $page ?> of <?= $pages ?></div>
      <div class="btn-group btn-group-sm">
        <a class="btn btn-outline-secondary <?= $page<=1?'disabled':'' ?>" href="<?= e($qs(['page'=>$page-1])) ?>">Previous</a>
        <a class="btn btn-outline-secondary <?= $page>=$pages?'disabled':'' ?>" href="<?= e($qs(['page'=>$page+1])) ?>">Next</a>
      </div>
    </div>
  <?php endif; ?>
</div>
