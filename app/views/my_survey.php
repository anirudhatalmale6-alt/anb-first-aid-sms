<?php $likert=['Strongly disagree','Disagree','Neutral','Agree','Strongly agree']; ?>
<div style="background:#f4f5f7;min-height:100vh;">
  <div style="background:linear-gradient(135deg,#2F1D3A,#4a2d5c);color:#fff;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;">
    <div style="display:flex;align-items:center;gap:12px;">
      <img src="assets/logo.png" alt="A&amp;B First Aid Training" style="height:38px;width:auto;">
      <div style="border-left:1px solid rgba(255,255,255,.25);padding-left:12px;font-size:.8rem;letter-spacing:.06em;opacity:.85;">STUDENT SURVEY</div>
    </div>
    <a href="?r=my" style="color:#ffb3b0;text-decoration:none;font-size:.9rem;"><i class="bi bi-arrow-left"></i> My portal</a>
  </div>

  <div style="max-width:820px;margin:0 auto;padding:24px 16px;">
    <div class="card p-3 mb-3" style="border-left:4px solid #E53935;">
      <h5 class="fw-bold mb-1" style="color:#2F1D3A;">Before you download your certificate</h5>
      <div class="text-muted small">Please help us improve by taking this quick survey - it only takes a minute. Your feedback is confidential.</div>
    </div>

    <form method="post" action="?r=my_survey_save">
      <input type="hidden" name="num" value="<?= e($num ?? '') ?>">

      <div class="card p-3 mb-3">
        <label class="fw-semibold mb-2">Overall, were you satisfied with your recent training?</label><br>
        <div class="btn-group" role="group">
          <input type="radio" class="btn-check" name="satisfied" id="s1" value="Satisfied" required><label class="btn btn-outline-success" for="s1"><i class="bi bi-emoji-smile"></i> Satisfied</label>
          <input type="radio" class="btn-check" name="satisfied" id="s2" value="Not Satisfied"><label class="btn btn-outline-danger" for="s2"><i class="bi bi-emoji-frown"></i> Not Satisfied</label>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <label class="fw-semibold mb-1">How would you rate the quality of the training delivery?</label>
        <div class="small text-muted mb-2">(1 = very poor, 10 = excellent)</div>
        <div class="d-flex flex-wrap gap-2">
          <?php for($i=1;$i<=10;$i++): ?><div class="form-check"><input class="form-check-input" type="radio" name="quality" id="q<?=$i?>" value="<?=$i?>" required><label class="form-check-label small" for="q<?=$i?>"><?=$i?></label></div><?php endfor; ?>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <label class="fw-semibold mb-2">Did you receive sufficient information about your upcoming Training Course?</label><br>
        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="sufficient_info" value="Yes" required><label class="form-check-label">Yes</label></div>
        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="sufficient_info" value="No"><label class="form-check-label">No</label></div>
      </div>

      <?php
      $agreeQs=[['q_fair','The assessment was conducted in a way that was fair and the expectations were clear'],
                ['q_skills','I developed the skills and knowledge I expected from this training'],
                ['q_trainer','I found the trainer approachable and easy to understand'],
                ['q_facilities','The facilities and equipment available for the training were sufficient for my needs']];
      foreach($agreeQs as $aq): ?>
        <div class="card p-3 mb-3">
          <label class="fw-semibold mb-2"><?= e($aq[1]) ?></label>
          <div class="d-flex flex-wrap gap-3">
            <?php foreach($likert as $opt): ?><div class="form-check"><input class="form-check-input" type="radio" name="<?= $aq[0] ?>" value="<?= e($opt) ?>" required><label class="form-check-label small"><?= e($opt) ?></label></div><?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="card p-3 mb-3">
        <label class="fw-semibold mb-1">How likely are you to use us for training again?</label>
        <div class="small text-muted mb-2">(1 = very unlikely, 10 = very likely)</div>
        <div class="d-flex flex-wrap gap-2">
          <?php for($i=1;$i<=10;$i++): ?><div class="form-check"><input class="form-check-input" type="radio" name="likely_again" id="la<?=$i?>" value="<?=$i?>" required><label class="form-check-label small" for="la<?=$i?>"><?=$i?></label></div><?php endfor; ?>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <label class="fw-semibold mb-2">If you completed pre-course study: the pre-course study was informative and prepared me for my face-to-face training. <span class="text-muted small">(optional)</span></label>
        <div class="d-flex flex-wrap gap-3">
          <?php foreach($likert as $opt): ?><div class="form-check"><input class="form-check-input" type="radio" name="precourse" value="<?= e($opt) ?>"><label class="form-check-label small"><?= e($opt) ?></label></div><?php endforeach; ?>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <label class="fw-semibold mb-1">Would you like to make a comment about your training experience? <span class="text-muted small">(optional)</span></label>
        <textarea name="comment" class="form-control" rows="3"></textarea>
      </div>

      <div class="card p-3 mb-3">
        <label class="fw-semibold mb-2">Would you like to discuss this survey?</label><br>
        <div class="form-check"><input class="form-check-input" type="radio" name="contact_pref" id="c1" value="I do not wish to be contacted" checked><label class="form-check-label" for="c1">I do not wish to be contacted about this survey</label></div>
        <div class="form-check"><input class="form-check-input" type="radio" name="contact_pref" id="c2" value="Please contact me"><label class="form-check-label" for="c2">Please contact me about my survey</label></div>
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <a href="?r=mycert&num=<?= urlencode($num ?? '') ?>&skip=1" class="text-muted small text-decoration-none"><i class="bi bi-x"></i> Skip &amp; download</a>
        <button class="btn btn-anb"><i class="bi bi-check2-circle"></i> Submit survey &amp; download certificate</button>
      </div>
    </form>
    <div class="py-4"></div>
  </div>
</div>
