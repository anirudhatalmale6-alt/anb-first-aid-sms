<?php require_once __DIR__ . '/../lib/survey.php'; ?>
<div style="min-height:100vh;background:linear-gradient(135deg,#E53935,#8e24aa);padding:30px 16px;">
  <div style="max-width:760px;margin:0 auto;">
    <div class="text-center mb-3">
      <span class="logo-badge" style="width:52px;height:52px;font-size:1.2rem;">A&amp;B</span>
      <div class="text-white mt-2" style="font-weight:700;font-size:1.15rem;">A&amp;B First Aid Training</div>
    </div>

    <?php if (!empty($done)): ?>
      <div class="card p-4 text-center">
        <i class="bi bi-check-circle-fill text-success" style="font-size:2.4rem;"></i>
        <h5 class="fw-bold mt-2" style="color:#2F1D3A;">Thank you!</h5>
        <p class="text-muted mb-0">Your feedback has been recorded. It helps us meet our quality obligations and improve our training.</p>
      </div>

    <?php elseif (!$survey): ?>
      <div class="card p-4 text-center">
        <i class="bi bi-x-circle text-danger" style="font-size:2.2rem;"></i>
        <h6 class="fw-bold mt-2">Survey link not found</h6>
        <p class="text-muted small mb-0">This link may be incorrect or expired. Please contact us if you need a new one.</p>
      </div>

    <?php elseif ($survey['completed_at']): ?>
      <div class="card p-4 text-center">
        <i class="bi bi-check2-circle text-success" style="font-size:2.2rem;"></i>
        <h6 class="fw-bold mt-2">Already completed</h6>
        <p class="text-muted small mb-0">Thanks — we've already received your response to this survey.</p>
      </div>

    <?php else:
      $type = $survey['type']; $questions = survey_questions($type); ?>
      <div class="card p-4">
        <h5 class="fw-bold mb-1" style="color:#2F1D3A;"><?= e(survey_title($type)) ?></h5>
        <p class="text-muted small">
          <?php if ($type==='employer'): ?>
            Your employee recently completed <strong><?= e($survey['course_title'] ?: 'training') ?></strong> with us.
            This short survey (Employer Satisfaction Quality Indicator) helps us report on and improve our training.
          <?php else: ?>
            You recently completed <strong><?= e($survey['course_title'] ?: 'training') ?></strong> with us.
            This short survey (Learner Engagement Quality Indicator) helps us report on and improve our training.
          <?php endif; ?>
        </p>

        <form method="post" action="?r=survey&t=<?= e($token) ?>">
          <?php if ($type==='employer'): ?>
            <div class="row g-2 mb-3">
              <div class="col-md-6"><label class="form-label small fw-semibold">Your name</label>
                <input name="respondent_name" class="form-control" value="<?= e($survey['respondent_name']) ?>"></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Organisation</label>
                <input name="company_name" class="form-control" value="<?= e($survey['company_name']) ?>"></div>
            </div>
          <?php endif; ?>

          <table class="table align-middle">
            <thead><tr><th style="width:52%;">Statement</th>
              <?php foreach (SURVEY_SCALE as $n=>$lbl): ?><th class="text-center small"><?= e($lbl) ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($questions as $code=>$q): ?>
              <tr>
                <td class="small"><?= e($q) ?></td>
                <?php foreach (SURVEY_SCALE as $n=>$lbl): ?>
                  <td class="text-center"><input class="form-check-input" type="radio" name="q[<?= $code ?>]" value="<?= $n ?>" required></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>

          <label class="form-label small fw-semibold">What did we do best?</label>
          <textarea name="best" class="form-control mb-3" rows="2"></textarea>
          <label class="form-label small fw-semibold">What could we improve?</label>
          <textarea name="improve" class="form-control mb-3" rows="2"></textarea>

          <button class="btn btn-anb w-100">Submit feedback</button>
          <div class="text-muted small text-center mt-2">Collected under the AQTF / NCVER Quality Indicators.</div>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
