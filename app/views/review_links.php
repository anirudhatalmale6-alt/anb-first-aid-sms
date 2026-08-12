<?php $flash=$_SESSION['flash']??null; unset($_SESSION['flash']); ?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Google Reviews</h4>
    <div class="text-muted small">Set each location's Google review link. Students are invited to leave a review after they finish their survey.</div></div>
</div>
<?php if($flash): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> <?= e($flash) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card p-3">
      <form method="post" action="?r=review_links_save">
        <h6 class="fw-bold mb-2">Review link for each location</h6>
        <p class="small text-muted mb-3">When a student completes their end-of-course survey, they're shown a "Leave us a Google review" button that opens the review page for the location they attended. This catches the students who didn't scan the QR code in class.</p>

        <?php foreach($locs as $l): $k='review_url_'.$l['id']; ?>
          <label class="form-label small fw-semibold mb-0"><?= e($l['name']) ?><?php if($l['suburb']): ?> <span class="text-muted">(<?= e($l['suburb']) ?>)</span><?php endif; ?></label>
          <input name="review_url[<?= (int)$l['id'] ?>]" class="form-control form-control-sm mb-2" placeholder="https://g.page/r/..."
                 value="<?= e($settings[$k] ?? '') ?>">
        <?php endforeach; if(!$locs): ?><div class="text-muted small mb-2">No active locations found - add locations first under the Locations menu.</div><?php endif; ?>

        <hr>
        <label class="form-label small fw-semibold mb-0">Default review link <span class="text-muted">(used if a student's location has no link above)</span></label>
        <input name="review_url_default" class="form-control form-control-sm mb-3" placeholder="https://g.page/r/..."
               value="<?= e($settings['review_url_default'] ?? '') ?>">

        <button class="btn btn-anb btn-sm"><i class="bi bi-save"></i> Save review links</button>
      </form>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card p-3" style="border-left:4px solid #E53935;">
      <h6 class="fw-bold mb-2"><i class="bi bi-info-circle"></i> How to find your review link</h6>
      <ol class="small mb-2" style="padding-left:1.1rem;">
        <li class="mb-1">Open your Google Business Profile for that location.</li>
        <li class="mb-1">Click "Ask for reviews" / "Get more reviews".</li>
        <li class="mb-1">Copy the short link Google gives you (it looks like <code>https://g.page/r/...</code>).</li>
        <li class="mb-1">Paste it next to the matching location and Save.</li>
      </ol>
      <p class="small text-muted mb-0">Tip: it's the same link that's behind the QR code you already use in class - if you have that link handy, just paste it here.</p>
    </div>
  </div>
</div>
