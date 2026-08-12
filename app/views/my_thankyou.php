<?php $dl = $num!=='' ? ('?r=mycert&num='.urlencode($num).'&skip=1') : ''; $happy = ($satisfied==='Satisfied'); ?>
<div style="background:#f4f5f7;min-height:100vh;">
  <div style="background:linear-gradient(135deg,#2F1D3A,#4a2d5c);color:#fff;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;">
    <div style="display:flex;align-items:center;gap:12px;">
      <img src="assets/logo.png" alt="A&amp;B First Aid Training" style="height:38px;width:auto;">
      <div style="border-left:1px solid rgba(255,255,255,.25);padding-left:12px;font-size:.8rem;letter-spacing:.06em;opacity:.85;">THANK YOU</div>
    </div>
    <a href="?r=my" style="color:#ffb3b0;text-decoration:none;font-size:.9rem;"><i class="bi bi-arrow-left"></i> My portal</a>
  </div>

  <div style="max-width:640px;margin:0 auto;padding:28px 16px;">
    <div class="card p-4 mb-3 text-center">
      <div style="font-size:2.4rem;line-height:1;color:#2e7d32;"><i class="bi bi-check-circle-fill"></i></div>
      <h4 class="fw-bold mt-2 mb-1" style="color:#2F1D3A;">Thanks for your feedback!</h4>
      <?php if($dl): ?>
        <p class="text-muted mb-3">Your certificate is downloading now. If it doesn't start automatically, use the button below.</p>
        <a href="<?= e($dl) ?>" class="btn btn-anb"><i class="bi bi-download"></i> Download my certificate</a>
      <?php else: ?>
        <p class="text-muted mb-0">Your feedback has been recorded.</p>
      <?php endif; ?>
    </div>

    <?php if(!empty($reviewUrl)): ?>
      <div class="card p-4 text-center" style="border:2px solid #FFC107;background:#fffdf5;">
        <div style="color:#FFB300;font-size:1.6rem;letter-spacing:2px;">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
        <h5 class="fw-bold mt-1 mb-1" style="color:#2F1D3A;">
          <?= $happy ? 'So glad you enjoyed your training!' : 'Enjoyed your training with us?' ?>
        </h5>
        <p class="text-muted mb-3">We're a small local team and a quick Google review makes a huge difference. It only takes 30 seconds - thank you!</p>
        <a href="<?= e($reviewUrl) ?>" target="_blank" rel="noopener" class="btn btn-warning fw-semibold">
          <i class="bi bi-google"></i> Leave us a Google review
        </a>
      </div>
    <?php endif; ?>

    <div class="py-4"></div>
  </div>

  <?php if($dl): ?><iframe src="<?= e($dl) ?>" style="display:none" title="certificate download"></iframe><?php endif; ?>
</div>
