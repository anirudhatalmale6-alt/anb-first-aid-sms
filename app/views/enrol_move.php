<?php
/**
 * Reschedule one enrolment into a different class.
 *
 * @var array  $en        the enrolment, with student and course
 * @var array  $schedules upcoming classes first, then past ones
 * @var int    $backTo    student id to return to, 0 when opened from Enrolments
 * @var ?array $current   the class they are in now
 */
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$isErr = !empty($_SESSION['flash_error']); unset($_SESSION['flash_error']);
$backUrl = $backTo ? '?r=student&id='.(int)$backTo : '?r=enrolments';
?>
<div class="topbar">
  <div>
    <a href="<?= e($backUrl) ?>" class="text-muted small text-decoration-none">
      <i class="bi bi-arrow-left"></i> <?= $backTo ? 'Back to the student' : 'Enrolments' ?>
    </a>
    <h4 class="mb-0 fw-bold" style="color:#2F1D3A;">
      Reschedule — <?= e(trim($en['first_name'].' '.$en['last_name'])) ?>
    </h4>
  </div>
</div>

<?php if($flash): ?>
  <div class="alert alert-<?= $isErr ? 'danger' : 'info' ?> py-2"><?= e($flash) ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card p-3">
      <h6 class="fw-bold mb-2">Where they are now</h6>
      <div class="small mb-1"><strong><?= e($en['course_code']) ?></strong> <?= e($en['course_title']) ?></div>
      <?php if ($current): ?>
        <div class="small text-muted">
          <i class="bi bi-calendar3"></i>
          <?= e(date('D j M Y', strtotime((string)$current['start_date']))) ?>
          <?= $current['start_time'] ? e(date('g:iA', strtotime((string)$current['start_time']))) : '' ?>
          <?= $current['loc'] ? ' · '.e((string)$current['loc']) : '' ?>
        </div>
      <?php else: ?>
        <div class="small text-muted">Not attached to a class.</div>
      <?php endif; ?>

      <hr>
      <p class="small text-muted mb-0">
        Moving them keeps everything they have already done — online modules, quiz results,
        payments and their USI stay with the student. Only the class, date and location change.
      </p>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card p-3">
      <h6 class="fw-bold mb-2">Move them to</h6>
      <form method="post" action="?r=enrol_move_save"
            onsubmit="return confirm('Move <?= e(addslashes(trim($en['first_name'].' '.$en['last_name']))) ?> to the selected class?')">
        <input type="hidden" name="id" value="<?= (int)$en['id'] ?>">
        <input type="hidden" name="from" value="<?= (int)$backTo ?>">

        <select name="schedule_id" class="form-select mb-3" required size="12">
          <?php
            $shownPast = false;
            foreach ($schedules as $sc):
              $full = $sc['places'] !== null && (int)$sc['booked'] >= (int)$sc['places'];
              if ((int)$sc['past'] === 1 && !$shownPast):
                $shownPast = true; ?>
                <option disabled>──────── classes that have already run ────────</option>
          <?php endif; ?>
            <option value="<?= (int)$sc['id'] ?>" <?= (int)$sc['id'] === (int)$en['schedule_id'] ? 'disabled' : '' ?>>
              <?= e(date('D j M Y', strtotime((string)$sc['start_date']))) ?><?php
                ?><?= $sc['start_time'] ? ' '.e(date('g:iA', strtotime((string)$sc['start_time']))) : '' ?><?php
                ?> · <?= e($sc['code']) ?><?php
                ?><?= $sc['loc'] ? ' · '.e($sc['loc']) : '' ?><?php
                ?> (<?= (int)$sc['booked'] ?><?= $sc['places'] !== null ? '/'.(int)$sc['places'] : '' ?> booked<?= $full ? ' — FULL' : '' ?>)<?php
                ?><?= (int)$sc['id'] === (int)$en['schedule_id'] ? ' — currently in this one' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button class="btn btn-anb"><i class="bi bi-calendar-event"></i> Move to this class</button>
        <a href="<?= e($backUrl) ?>" class="btn btn-link">Cancel</a>
      </form>
    </div>
  </div>
</div>
