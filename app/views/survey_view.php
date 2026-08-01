<?php require_once __DIR__ . '/../lib/survey.php';
$questions = survey_questions($survey['type']);
$answers = (array)json_decode($survey['answers'] ?? '[]', true);
?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;"><?= e(survey_title($survey['type'])) ?></h4>
    <div class="text-muted small">
      <?= e($survey['respondent_name'] ?: (($survey['first_name']??'').' '.($survey['last_name']??''))) ?>
      <?php if ($survey['company_name']): ?> · <?= e($survey['company_name']) ?><?php endif; ?>
      <?php if ($survey['course_title']): ?> · <?= e($survey['course_title']) ?><?php endif; ?>
      · Completed <?= e(substr((string)$survey['completed_at'],0,10)) ?></div></div>
  <a href="?r=surveys" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card p-3 mb-3">
  <table class="table align-middle mb-0">
    <thead><tr><th style="width:60%;">Statement</th><th>Response</th></tr></thead>
    <tbody>
    <?php foreach ($questions as $code=>$q): $v = $answers[$code] ?? null; ?>
      <tr>
        <td class="small"><?= e($q) ?></td>
        <td>
          <?php if ($v): $col = $v>=3?'success':($v==2?'warning':'danger'); ?>
            <span class="badge text-bg-<?= $col ?>"><?= e(SURVEY_SCALE[$v] ?? $v) ?></span>
          <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="row g-3">
  <div class="col-md-6"><div class="card p-3 h-100">
    <h6 class="fw-bold small text-uppercase text-muted">What we did best</h6>
    <p class="mb-0"><?= e($survey['comment_best'] ?: '—') ?></p>
  </div></div>
  <div class="col-md-6"><div class="card p-3 h-100">
    <h6 class="fw-bold small text-uppercase text-muted">What we could improve</h6>
    <p class="mb-0"><?= e($survey['comment_improve'] ?: '—') ?></p>
  </div></div>
</div>
