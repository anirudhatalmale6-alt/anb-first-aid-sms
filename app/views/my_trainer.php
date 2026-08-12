<?php $flash=$_SESSION['flash']??null; unset($_SESSION['flash']);
function tnd($d){ if(!$d) return null; $t=new DateTime('2026-08-04'); return (int)$t->diff(new DateTime($d))->format('%r%a'); } ?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">My Trainer Profile</h4>
    <div class="text-muted small">Complete your details, upload your documents, and sign your declaration</div></div>
</div>
<?php if($flash): ?><div class="alert alert-info py-2"><i class="bi bi-info-circle"></i> <?= e($flash) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-2">My Details</h6>
      <form method="post" action="?r=my_trainer_save">
        <label class="form-label small fw-semibold mb-0">Name</label><input name="name" class="form-control form-control-sm mb-2" value="<?= e($prof['name']) ?>">
        <label class="form-label small fw-semibold mb-0">Email</label><input class="form-control form-control-sm mb-2" value="<?= e($prof['email']) ?>" disabled>
        <label class="form-label small fw-semibold mb-0">Phone</label><input name="phone" class="form-control form-control-sm mb-2" value="<?= e($prof['phone']) ?>">
        <label class="form-label small fw-semibold mb-0">Position</label><input name="position" class="form-control form-control-sm mb-2" value="<?= e($prof['position']) ?>">
        <label class="form-label small fw-semibold mb-0">Notes</label><textarea name="notes" class="form-control form-control-sm mb-2" rows="2"><?= e($prof['notes']) ?></textarea>
        <button class="btn btn-anb btn-sm w-100"><i class="bi bi-save"></i> Save my details</button>
      </form>
    </div>

    <div class="card p-3">
      <h6 class="fw-bold mb-2">Declaration</h6>
      <?php if(!empty($prof['declaration_name'])): ?>
        <div class="alert alert-success py-2 small mb-0"><i class="bi bi-check-circle-fill"></i> Signed by <strong><?= e($prof['declaration_name']) ?></strong> on <?= e($prof['declaration_date']) ?>.</div>
      <?php else: ?>
        <p class="small mb-2">I declare that the information and documents I have provided are true, current and authentic; that I hold the vocational competencies, training &amp; assessment qualification and industry currency required for the units I deliver; and that I will notify A&amp;B First Aid Training of any change. I agree to keep student and business information confidential.</p>
        <form method="post" action="?r=my_trainer_declare">
          <label class="form-label small fw-semibold mb-0">Type your full name to sign</label>
          <input name="declaration_name" class="form-control form-control-sm mb-2" required>
          <button class="btn btn-anb btn-sm w-100"><i class="bi bi-pen"></i> Sign declaration</button>
        </form>
      <?php endif; ?>
    </div>

    <?php $itype=(string)($prof['insurance_type']??''); $has=function($t) use($itype){return strpos($itype,$t)!==false;}; $ind=tnd($prof['insurance_expiry']??null); ?>
    <div class="card p-3 mt-3" style="border-left:4px solid #8e24aa;">
      <h6 class="fw-bold mb-2">Insurance</h6>
      <form method="post" action="?r=my_trainer_insurance" enctype="multipart/form-data">
        <div class="form-check"><input class="form-check-input" type="checkbox" name="insurance_type[]" value="Covered under A&B First Aid Training insurance" id="i1" <?= $has('Covered under A&B')?'checked':'' ?>><label class="form-check-label small" for="i1">Covered under A&amp;B First Aid Training insurance</label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="insurance_type[]" value="Public Liability" id="i2" <?= $has('Public Liability')?'checked':'' ?>><label class="form-check-label small" for="i2">I maintain my own Public Liability Insurance</label></div>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="insurance_type[]" value="Professional Indemnity" id="i3" <?= $has('Professional Indemnity')?'checked':'' ?>><label class="form-check-label small" for="i3">I maintain my own Professional Indemnity Insurance</label></div>
        <div class="small text-muted mb-1">If you provide your own insurance, complete these and upload your certificate:</div>
        <label class="form-label small fw-semibold mb-0">Insurance provider</label><input name="insurance_provider" class="form-control form-control-sm mb-2" value="<?= e($prof['insurance_provider']??'') ?>">
        <div class="row g-2"><div class="col-7"><label class="form-label small fw-semibold mb-0">Policy number</label><input name="insurance_policy_no" class="form-control form-control-sm mb-2" value="<?= e($prof['insurance_policy_no']??'') ?>"></div>
        <div class="col-5"><label class="form-label small fw-semibold mb-0">Expiry date</label><input type="date" name="insurance_expiry" class="form-control form-control-sm mb-2" value="<?= e($prof['insurance_expiry']??'') ?>"></div></div>
        <?php if($ind!==null){ if($ind<0) echo '<div class="small text-danger mb-1">Insurance expired</div>'; elseif($ind<=90) echo '<div class="small" style="color:#b8860b;">Insurance expires in '.$ind.' days - please renew</div>'; } ?>
        <?php if(!empty($prof['insurance_file'])): ?><div class="small mb-1"><a href="?r=trainer_ins_download&id=<?= (int)$prof['id'] ?>"><i class="bi bi-download"></i> Current certificate</a></div><?php endif; ?>
        <label class="form-label small fw-semibold mb-0">Upload certificate of currency (PDF)</label><input type="file" name="file" class="form-control form-control-sm mb-2">
        <button class="btn btn-anb btn-sm w-100"><i class="bi bi-save"></i> Save insurance</button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-2">My Documents, Qualifications &amp; Currency</h6>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Type</th><th>Title</th><th>Code</th><th>Expiry</th><th>File</th></tr></thead><tbody>
        <?php foreach($quals as $q): $nd=tnd($q['expiry_date']); ?>
          <tr><td class="small"><?= e($q['qual_type']) ?></td><td class="small fw-semibold"><?= e($q['title']) ?></td><td class="small"><?= e($q['code']) ?></td>
          <td class="small"><?= e($q['expiry_date']) ?><?php if($nd!==null){ if($nd<0) echo ' <span class="badge text-bg-danger">Expired</span>'; elseif($nd<=60) echo ' <span class="badge text-bg-warning">Soon</span>'; } ?></td>
          <td class="small"><?php if($q['file_path']): ?><a href="?r=trainer_cert_download&id=<?= (int)$q['id'] ?>"><i class="bi bi-download"></i></a><?php else: echo '—'; endif; ?></td></tr>
        <?php endforeach; if(!$quals): ?><tr><td colspan="5" class="text-muted small">No documents uploaded yet — add them below.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>

    <div class="card p-3" style="border-left:4px solid #E53935;">
      <h6 class="fw-bold mb-2">Upload a document</h6>
      <p class="small text-muted mb-2">Upload your qualifications, first aid certificates, TAE, industry currency evidence, professional development, and signed forms (e.g. your contractor agreement).</p>
      <form method="post" action="?r=my_trainer_qual" enctype="multipart/form-data">
        <div class="row g-2">
          <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Type</label>
            <select name="qual_type" class="form-select form-select-sm"><?php foreach(['Qualification','Vocational Competency','Industry Currency','Professional Development','Employment Document','Certificate'] as $qt): ?><option><?= $qt ?></option><?php endforeach; ?></select></div>
          <div class="col-md-5"><label class="form-label small fw-semibold mb-0">Title</label><input name="title" class="form-control form-control-sm" placeholder="e.g. HLTAID011, TAE40122, Contractor Agreement" required></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Code (optional)</label><input name="code" class="form-control form-control-sm"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Issued date</label><input type="date" name="issued_date" class="form-control form-control-sm"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Expiry date</label><input type="date" name="expiry_date" class="form-control form-control-sm"></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">File</label><input type="file" name="file" class="form-control form-control-sm"></div>
        </div>
        <button class="btn btn-anb btn-sm mt-2"><i class="bi bi-upload"></i> Upload document</button>
      </form>
    </div>
  </div>
</div>
