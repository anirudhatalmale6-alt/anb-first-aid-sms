<?php
$flash=$_SESSION['flash']??null; unset($_SESSION['flash']);
function ndays($d){ if(!$d) return null; $t=new DateTime('2026-08-04'); return (int)$t->diff(new DateTime($d))->format('%r%a'); }
$tabUrl=function($t) use($fSection,$fUnit,$fStatus,$fQ){ return '?r=compliance&tab='.$t; };
?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Compliance Management</h4>
    <div class="text-muted small">RTO compliance documentation, registers and audit trail — A&amp;B First Aid Training (RTO 46055)</div></div>
  <?php if(!$canEdit): ?><span class="badge text-bg-secondary"><i class="bi bi-eye"></i> Read-only access</span><?php endif; ?>
</div>
<?php if($flash): ?><div class="alert alert-info py-2"><i class="bi bi-info-circle"></i> <?= e($flash) ?></div><?php endif; ?>

<ul class="nav nav-pills mb-3" style="gap:6px;flex-wrap:wrap;">
  <?php foreach([['dashboard','Dashboard'],['register','Document Register'],['ci','Continuous Improvement'],['equipment','Equipment'],['trainers','Trainer Matrix'],['users','Users & Roles']] as $t): ?>
    <li class="nav-item"><a class="nav-link <?= $tab===$t[0]?'active':'' ?>" style="<?= $tab===$t[0]?'background:#E53935;':'' ?>" href="?r=compliance&tab=<?= $t[0] ?>"><?= $t[1] ?></a></li>
  <?php endforeach; ?>
</ul>

<?php if (!empty($editDoc) && isset($_GET['view'])):
  // ---------- DOCUMENT DETAIL ----------
  $dv=$editDoc; ?>
  <a href="?r=compliance&tab=register" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Back to register</a>
  <div class="card p-3 mb-3">
    <div class="d-flex justify-content-between">
      <h5 class="fw-bold mb-2"><?= e($dv['doc_name']) ?> <?= comp_status_badge($dv['status']) ?></h5>
      <?php if($dv['file_path']): ?><a href="?r=comp_download&id=<?= (int)$dv['id'] ?>" class="btn btn-sm btn-anb"><i class="bi bi-download"></i> Download</a><?php endif; ?>
    </div>
    <table class="table table-sm mb-0"><tbody>
      <tr><th style="width:220px;">Section</th><td><?= e($dv['section']) ?></td></tr>
      <tr><th>Category / Type</th><td><?= e($dv['subcategory']) ?></td></tr>
      <?php if($dv['unit_code']): ?><tr><th>Unit</th><td><?= e($dv['unit_code']) ?></td></tr><?php endif; ?>
      <tr><th>Version</th><td><?= e($dv['version']) ?></td></tr>
      <tr><th>Status</th><td><?= comp_status_badge($dv['status']) ?></td></tr>
      <tr><th>Approval date</th><td><?= e($dv['approval_date']) ?></td></tr>
      <tr><th>Review date</th><td><?= e($dv['review_date']) ?></td></tr>
      <tr><th>Approved by</th><td><?= e($dv['approved_by']) ?></td></tr>
      <tr><th>Document owner</th><td><?= e($dv['owner']) ?></td></tr>
      <tr><th>Last modified</th><td><?= e($dv['updated_at']) ?></td></tr>
      <tr><th>Notes</th><td><?= e($dv['notes']) ?></td></tr>
    </tbody></table>
    <?php if($canEdit): ?><a href="?r=compliance&tab=register&edit=<?= (int)$dv['id'] ?>" class="btn btn-sm btn-outline-danger mt-2"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
  </div>
  <div class="row g-3">
    <div class="col-lg-6"><div class="card p-3"><h6 class="fw-bold mb-2"><i class="bi bi-clock-history"></i> Version History</h6>
      <table class="table table-sm mb-0"><thead><tr><th>Version</th><th>File</th><th>Note</th><th>By</th><th>When</th></tr></thead><tbody>
      <?php foreach($verRows as $v): ?><tr><td><?= e($v['version']) ?></td><td class="small"><?= e($v['original_name']) ?></td><td class="small"><?= e($v['note']) ?></td><td class="small"><?= e($v['changed_by']) ?></td><td class="small"><?= e($v['changed_at']) ?></td></tr><?php endforeach; if(!$verRows): ?><tr><td colspan="5" class="text-muted small">No versions recorded.</td></tr><?php endif; ?>
      </tbody></table></div></div>
    <div class="col-lg-6"><div class="card p-3"><h6 class="fw-bold mb-2"><i class="bi bi-shield-check"></i> Audit Trail</h6>
      <table class="table table-sm mb-0"><thead><tr><th>Action</th><th>Detail</th><th>User</th><th>When</th></tr></thead><tbody>
      <?php foreach($auditRows as $a): ?><tr><td class="small fw-semibold"><?= e($a['action']) ?></td><td class="small"><?= e($a['detail']) ?></td><td class="small"><?= e($a['user_name']) ?></td><td class="small"><?= e($a['at']) ?></td></tr><?php endforeach; if(!$auditRows): ?><tr><td colspan="4" class="text-muted small">No audit entries.</td></tr><?php endif; ?>
      </tbody></table></div></div>
  </div>

<?php elseif ($tab==='dashboard'): ?>
  <div class="row g-3 mb-3">
    <?php foreach([['Total documents',$dash['total'],'bi-folder2','#2F1D3A'],['Active',$dash['active'],'bi-check-circle','#2e7d32'],['Drafts',$dash['draft'],'bi-pencil-square','#b8860b'],['Archived',$dash['archived'],'bi-archive','#777'],['Certificates issued',$dash['certs_issued'],'bi-award','#E53935']] as $c): ?>
      <div class="col"><div class="card stat-card p-3"><div class="d-flex justify-content-between align-items-center"><div><div class="num"><?= (int)$c[1] ?></div><div class="text-muted small"><?= $c[0] ?></div></div><i class="bi <?= $c[2] ?> ico" style="color:<?= $c[3] ?>"></i></div></div></div>
    <?php endforeach; ?>
  </div>
  <div class="row g-3">
    <div class="col-lg-6"><div class="card p-3">
      <h6 class="fw-bold mb-2 text-danger"><i class="bi bi-exclamation-triangle"></i> Reviews Overdue (<?= count($dash['overdue']) ?>)</h6>
      <table class="table table-sm mb-0"><tbody>
      <?php foreach($dash['overdue'] as $o): ?><tr><td class="small"><a href="?r=compliance&tab=register&view=<?= (int)$o['id'] ?>"><?= e($o['doc_name']) ?></a></td><td class="small text-danger text-end"><?= e($o['review_date']) ?></td></tr><?php endforeach; if(!$dash['overdue']): ?><tr><td class="text-muted small">Nothing overdue 🎉</td></tr><?php endif; ?>
      </tbody></table></div></div>
    <div class="col-lg-6"><div class="card p-3">
      <h6 class="fw-bold mb-2" style="color:#b8860b;"><i class="bi bi-calendar-event"></i> Reviews Due (next 60 days) (<?= count($dash['due_soon']) ?>)</h6>
      <table class="table table-sm mb-0"><tbody>
      <?php foreach($dash['due_soon'] as $o): ?><tr><td class="small"><a href="?r=compliance&tab=register&view=<?= (int)$o['id'] ?>"><?= e($o['doc_name']) ?></a></td><td class="small text-end"><?= e($o['review_date']) ?></td></tr><?php endforeach; if(!$dash['due_soon']): ?><tr><td class="text-muted small">Nothing due soon.</td></tr><?php endif; ?>
      </tbody></table></div></div>
    <div class="col-lg-6"><div class="card p-3">
      <h6 class="fw-bold mb-2" style="color:#b8860b;"><i class="bi bi-pencil-square"></i> Drafts Pending Approval (<?= count($dash['drafts']) ?>)</h6>
      <table class="table table-sm mb-0"><tbody>
      <?php foreach($dash['drafts'] as $o): ?><tr><td class="small"><a href="?r=compliance&tab=register&view=<?= (int)$o['id'] ?>"><?= e($o['doc_name']) ?></a></td><td class="small text-end"><?= e($o['section']) ?></td></tr><?php endforeach; if(!$dash['drafts']): ?><tr><td class="text-muted small">No drafts.</td></tr><?php endif; ?>
      </tbody></table></div></div>
    <div class="col-lg-6"><div class="card p-3">
      <h6 class="fw-bold mb-2 text-danger"><i class="bi bi-arrow-repeat"></i> Outstanding Improvement Actions (<?= count($dash['ci_open']) ?>)</h6>
      <table class="table table-sm mb-0"><tbody>
      <?php foreach($dash['ci_open'] as $o): ?><tr><td class="small"><?= e($o['ref']) ?> — <?= e($o['description']) ?></td><td class="small text-end"><?= e($o['due_date']) ?></td></tr><?php endforeach; if(!$dash['ci_open']): ?><tr><td class="text-muted small">No outstanding actions.</td></tr><?php endif; ?>
      </tbody></table></div></div>
    <div class="col-lg-6"><div class="card p-3">
      <h6 class="fw-bold mb-2" style="color:#b8860b;"><i class="bi bi-tools"></i> Equipment Maintenance Due (<?= count($dash['equip_due']) ?>)</h6>
      <table class="table table-sm mb-0"><tbody>
      <?php foreach($dash['equip_due'] as $o): ?><tr><td class="small"><?= e($o['name']) ?></td><td class="small text-end"><?= e($o['next_service_date']) ?></td></tr><?php endforeach; if(!$dash['equip_due']): ?><tr><td class="text-muted small">Nothing due — set service dates in the Equipment tab.</td></tr><?php endif; ?>
      </tbody></table></div></div>
    <div class="col-lg-6"><div class="card p-3">
      <h6 class="fw-bold mb-2 text-danger"><i class="bi bi-person-badge"></i> Trainer Qualifications Expiring (<?= count($dash['qual_exp']) ?>)</h6>
      <table class="table table-sm mb-0"><tbody>
      <?php foreach($dash['qual_exp'] as $o): ?><tr><td class="small"><?= e($o['trainer_name']) ?> — <?= e($o['title']) ?></td><td class="small text-end"><?= e($o['expiry_date']) ?></td></tr><?php endforeach; if(!$dash['qual_exp']): ?><tr><td class="text-muted small">Nothing expiring — set expiry dates in the Trainer Matrix.</td></tr><?php endif; ?>
      </tbody></table></div></div>
    <div class="col-lg-6"><div class="card p-3">
      <h6 class="fw-bold mb-2 text-danger"><i class="bi bi-shield-exclamation"></i> Trainer Insurance Expiring (<?= count($dash['ins_exp']??[]) ?>)</h6>
      <table class="table table-sm mb-0"><tbody>
      <?php foreach(($dash['ins_exp']??[]) as $o): ?><tr><td class="small"><?= e($o['name']) ?><?= $o['insurance_provider']?(' — '.e($o['insurance_provider'])):'' ?></td><td class="small text-end"><?= e($o['insurance_expiry']) ?></td></tr><?php endforeach; if(empty($dash['ins_exp'])): ?><tr><td class="text-muted small">Nothing expiring — insurance is recorded per trainer.</td></tr><?php endif; ?>
      </tbody></table></div></div>
  </div>

<?php elseif ($tab==='ci'): ?>
  <?php $edci = null; if(isset($_GET['ci_edit'])){ foreach($ci as $row){ if((int)$row['id']===(int)$_GET['ci_edit']) $edci=$row; } } ?>
  <div class="row g-3">
    <div class="col-lg-8"><div class="card p-3">
      <h6 class="fw-bold mb-3"><i class="bi bi-arrow-repeat text-danger"></i> Continuous Improvement Register</h6>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Ref</th><th>Raised</th><th>Source</th><th>Description</th><th>Action</th><th>Responsible</th><th>Due</th><th>Status</th><?php if($canEdit): ?><th></th><?php endif; ?></tr></thead><tbody>
        <?php foreach($ci as $row): $sb=['Completed'=>'success','In Progress'=>'warning','Open'=>'secondary'][$row['status']]??'light'; ?>
          <tr><td class="small fw-semibold"><?= e($row['ref']) ?></td><td class="small"><?= e($row['date_raised']) ?></td>
          <td class="small"><?= e($row['source']) ?></td><td class="small"><?= e($row['description']) ?></td>
          <td class="small"><?= e($row['action_required']) ?></td><td class="small"><?= e($row['responsible']) ?></td>
          <td class="small"><?= e($row['due_date']) ?></td><td><span class="badge text-bg-<?= $sb ?>"><?= e($row['status']) ?></span></td>
          <?php if($canEdit): ?><td><a href="?r=compliance&tab=ci&ci_edit=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-pencil"></i></a></td><?php endif; ?></tr>
        <?php endforeach; if(!$ci): ?><tr><td colspan="9" class="text-muted small">No improvement items yet.</td></tr><?php endif; ?>
      </tbody></table></div></div></div>
    <?php if($canEdit): ?>
    <div class="col-lg-4"><div class="card p-3">
      <h6 class="fw-bold mb-2"><?= $edci?'Edit item':'Add improvement item' ?></h6>
      <form method="post" action="?r=ci_save">
        <input type="hidden" name="id" value="<?= (int)($edci['id']??0) ?>">
        <label class="form-label small fw-semibold mb-0">Reference</label>
        <input name="ref" class="form-control form-control-sm mb-2" value="<?= e($edci['ref']??('CI-2026-'.str_pad((string)(count($ci)+1),3,'0',STR_PAD_LEFT))) ?>">
        <div class="row g-2"><div class="col-6"><label class="form-label small fw-semibold mb-0">Date raised</label><input type="date" name="date_raised" class="form-control form-control-sm mb-2" value="<?= e($edci['date_raised']??'2026-08-04') ?>"></div>
        <div class="col-6"><label class="form-label small fw-semibold mb-0">Due date</label><input type="date" name="due_date" class="form-control form-control-sm mb-2" value="<?= e($edci['due_date']??'') ?>"></div></div>
        <label class="form-label small fw-semibold mb-0">Source</label>
        <select name="source" class="form-select form-select-sm mb-2"><?php foreach(['Complaint','Validation','Audit','Industry Consultation','Student Feedback','Employer Feedback','Other'] as $s): ?><option <?= (($edci['source']??'')===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
        <label class="form-label small fw-semibold mb-0">Description</label>
        <textarea name="description" class="form-control form-control-sm mb-2" rows="2"><?= e($edci['description']??'') ?></textarea>
        <label class="form-label small fw-semibold mb-0">Action required</label>
        <textarea name="action_required" class="form-control form-control-sm mb-2" rows="2"><?= e($edci['action_required']??'') ?></textarea>
        <label class="form-label small fw-semibold mb-0">Responsible</label>
        <input name="responsible" class="form-control form-control-sm mb-2" value="<?= e($edci['responsible']??'') ?>">
        <div class="row g-2"><div class="col-6"><label class="form-label small fw-semibold mb-0">Status</label><select name="status" class="form-select form-select-sm mb-2"><?php foreach(['Open','In Progress','Completed'] as $s): ?><option <?= (($edci['status']??'Open')===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
        <div class="col-6"><label class="form-label small fw-semibold mb-0">Completed date</label><input type="date" name="completed_date" class="form-control form-control-sm mb-2" value="<?= e($edci['completed_date']??'') ?>"></div></div>
        <label class="form-label small fw-semibold mb-0">Linked to</label>
        <input name="linked_type" class="form-control form-control-sm mb-2" placeholder="e.g. Validation / Complaint / Audit" value="<?= e($edci['linked_type']??'') ?>">
        <button class="btn btn-anb btn-sm w-100"><i class="bi bi-save"></i> Save</button>
        <?php if($edci): ?><a href="?r=compliance&tab=ci" class="btn btn-outline-secondary btn-sm w-100 mt-1">Cancel</a><?php endif; ?>
      </form>
    </div></div>
    <?php endif; ?>
  </div>

<?php elseif ($tab==='equipment'): $ee=$editEquip; ?>
  <?php if($canEdit): ?>
  <div class="card p-3 mb-3" style="border-left:4px solid #E53935;">
    <h6 class="fw-bold mb-2"><?= $ee?'Edit equipment':'Add equipment' ?></h6>
    <form method="post" action="?r=comp_equip_save"><input type="hidden" name="id" value="<?= (int)($ee['id']??0) ?>">
      <div class="row g-2">
        <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Name</label><input name="name" class="form-control form-control-sm" value="<?= e($ee['name']??'') ?>" required></div>
        <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Category</label><select name="category" class="form-select form-select-sm"><?php foreach(comp_equip_categories() as $c): ?><option <?= (($ee['category']??'')===$c)?'selected':'' ?>><?= $c ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Asset ID / serial</label><input name="asset_id" class="form-control form-control-sm" value="<?= e($ee['asset_id']??'') ?>"></div>
        <div class="col-md-2"><label class="form-label small fw-semibold mb-0">Status</label><select name="status" class="form-select form-select-sm"><?php foreach(['In Service','Out of Service','Retired'] as $s): ?><option <?= (($ee['status']??'In Service')===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Purchase date</label><input type="date" name="purchase_date" class="form-control form-control-sm" value="<?= e($ee['purchase_date']??'') ?>"></div>
        <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Last service</label><input type="date" name="last_service_date" class="form-control form-control-sm" value="<?= e($ee['last_service_date']??'') ?>"></div>
        <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Next service due</label><input type="date" name="next_service_date" class="form-control form-control-sm" value="<?= e($ee['next_service_date']??'') ?>"></div>
        <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Replacement date</label><input type="date" name="replacement_date" class="form-control form-control-sm" value="<?= e($ee['replacement_date']??'') ?>"></div>
        <div class="col-12"><label class="form-label small fw-semibold mb-0">Notes / service history</label><input name="notes" class="form-control form-control-sm" value="<?= e($ee['notes']??'') ?>"></div>
      </div>
      <button class="btn btn-anb btn-sm mt-2"><i class="bi bi-save"></i> <?= $ee?'Save':'Add equipment' ?></button>
      <?php if($ee): ?><a href="?r=compliance&tab=equipment" class="btn btn-outline-secondary btn-sm mt-2">Cancel</a><?php endif; ?>
    </form>
  </div>
  <?php endif; ?>
  <div class="card p-3"><h6 class="fw-bold mb-2">Equipment Register (<?= count($equip) ?>)</h6>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
      <thead><tr><th>Item</th><th>Category</th><th>Asset ID</th><th>Purchased</th><th>Last service</th><th>Next service</th><th>Replace</th><th>Status</th><?php if($canEdit): ?><th></th><?php endif; ?></tr></thead><tbody>
      <?php foreach($equip as $q): $nd=ndays($q['next_service_date']); ?>
        <tr><td class="small fw-semibold"><?= e($q['name']) ?></td><td class="small"><?= e($q['category']) ?></td><td class="small"><?= e($q['asset_id']) ?></td>
        <td class="small"><?= e($q['purchase_date']) ?></td><td class="small"><?= e($q['last_service_date']) ?></td>
        <td class="small"><?= e($q['next_service_date']) ?><?php if($nd!==null && $q['status']!=='Retired'){ if($nd<0) echo ' <span class="badge text-bg-danger">Overdue</span>'; elseif($nd<=30) echo ' <span class="badge text-bg-warning">Soon</span>'; } ?></td>
        <td class="small"><?= e($q['replacement_date']) ?></td>
        <td><span class="badge text-bg-<?= $q['status']==='In Service'?'success':($q['status']==='Retired'?'secondary':'warning') ?>"><?= e($q['status']) ?></span></td>
        <?php if($canEdit): ?><td><a href="?r=compliance&tab=equipment&equip_edit=<?= (int)$q['id'] ?>" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-pencil"></i></a></td><?php endif; ?></tr>
      <?php endforeach; if(!$equip): ?><tr><td colspan="9" class="text-muted small">No equipment yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>

<?php elseif ($tab==='trainers'):
  if (!empty($editTrainer)): $t=$editTrainer; $tqs=$tquals[$t['id']]??[]; ?>
    <a href="?r=compliance&tab=trainers" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Back to trainers</a>
    <div class="card p-3 mb-3"><h5 class="fw-bold mb-1"><?= e($t['name']) ?></h5>
      <div class="text-muted small"><?= e($t['position']) ?> · <?= e($t['email']) ?> <?= $t['phone']?('· '.e($t['phone'])):'' ?></div></div>
    <?php $itype=(string)($t['insurance_type']??''); $ihas=function($x) use($itype){return strpos($itype,$x)!==false;}; $ind=ndays($t['insurance_expiry']??null); ?>
    <div class="card p-3 mb-3" style="border-left:4px solid #8e24aa;">
      <h6 class="fw-bold mb-2">Insurance
        <?php if($ind!==null){ if($ind<0) echo '<span class="badge text-bg-danger ms-1">Expired</span>'; elseif($ind<=90) echo '<span class="badge text-bg-warning ms-1">Expires in '.$ind.' days</span>'; } ?>
      </h6>
      <?php if($canEdit): ?>
      <form method="post" action="?r=trainer_insurance_save" enctype="multipart/form-data"><input type="hidden" name="trainer_id" value="<?= (int)$t['id'] ?>">
        <div class="row g-2">
          <div class="col-md-12">
            <label class="d-block small"><input type="checkbox" name="insurance_type[]" value="Covered under A&B First Aid Training insurance" <?= $ihas('Covered under A&B')?'checked':'' ?>> Covered under A&amp;B insurance</label>
            <label class="d-block small"><input type="checkbox" name="insurance_type[]" value="Public Liability" <?= $ihas('Public Liability')?'checked':'' ?>> Own Public Liability</label>
            <label class="d-block small"><input type="checkbox" name="insurance_type[]" value="Professional Indemnity" <?= $ihas('Professional Indemnity')?'checked':'' ?>> Own Professional Indemnity</label>
          </div>
          <div class="col-md-5"><label class="form-label small fw-semibold mb-0">Provider</label><input name="insurance_provider" class="form-control form-control-sm" value="<?= e($t['insurance_provider']??'') ?>"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Policy number</label><input name="insurance_policy_no" class="form-control form-control-sm" value="<?= e($t['insurance_policy_no']??'') ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Expiry date</label><input type="date" name="insurance_expiry" class="form-control form-control-sm" value="<?= e($t['insurance_expiry']??'') ?>"></div>
          <div class="col-md-8"><label class="form-label small fw-semibold mb-0">Certificate of currency</label><input type="file" name="file" class="form-control form-control-sm"></div>
          <div class="col-md-4 d-flex align-items-end"><?php if(!empty($t['insurance_file'])): ?><a href="?r=trainer_ins_download&id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-download"></i> Current cert</a><?php endif; ?></div>
        </div>
        <button class="btn btn-anb btn-sm mt-2"><i class="bi bi-save"></i> Save insurance</button>
      </form>
      <?php else: ?>
        <div class="small"><?= e($t['insurance_type']?:'Not recorded') ?><?= $t['insurance_provider']?(' · '.e($t['insurance_provider'])):'' ?><?= $t['insurance_expiry']?(' · expires '.e($t['insurance_expiry'])):'' ?></div>
      <?php endif; ?>
    </div>

    <div class="card p-3 mb-3"><h6 class="fw-bold mb-2">Qualifications, Currency & Professional Development</h6>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Type</th><th>Title</th><th>Code</th><th>Issued</th><th>Expiry</th><th>Certificate</th><?php if($canEdit): ?><th></th><?php endif; ?></tr></thead><tbody>
        <?php foreach($tqs as $q): $nd=ndays($q['expiry_date']); ?>
          <tr><td class="small"><?= e($q['qual_type']) ?></td><td class="small fw-semibold"><?= e($q['title']) ?></td><td class="small"><?= e($q['code']) ?></td>
          <td class="small"><?= e($q['issued_date']) ?></td>
          <td class="small"><?= e($q['expiry_date']) ?><?php if($nd!==null){ if($nd<0) echo ' <span class="badge text-bg-danger">Expired</span>'; elseif($nd<=60) echo ' <span class="badge text-bg-warning">Soon</span>'; } ?></td>
          <td class="small"><?php if($q['file_path']): ?><a href="?r=trainer_cert_download&id=<?= (int)$q['id'] ?>"><i class="bi bi-download"></i></a><?php else: echo '—'; endif; ?></td>
          <?php if($canEdit): ?><td><a href="?r=compliance&tab=trainers&trainer_edit=<?= (int)$t['id'] ?>&qual_edit=<?= (int)$q['id'] ?>#qualform" class="btn btn-sm btn-outline-secondary py-0" title="Edit / set expiry"><i class="bi bi-pencil"></i></a></td><?php endif; ?></tr>
        <?php endforeach; if(!$tqs): ?><tr><td colspan="7" class="text-muted small">No qualifications recorded.</td></tr><?php endif; ?>
      </tbody></table></div></div>
    <?php if($canEdit): $eq=null; if(isset($_GET['qual_edit'])){ foreach($tqs as $qq){ if((int)$qq['id']===(int)$_GET['qual_edit']) $eq=$qq; } } ?>
    <div class="card p-3" id="qualform"><h6 class="fw-bold mb-2"><?= $eq?'Edit qualification / currency / PD':'Add qualification / currency / PD' ?></h6>
      <form method="post" action="?r=trainer_qual_save" enctype="multipart/form-data"><input type="hidden" name="trainer_id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="qid" value="<?= (int)($eq['id']??0) ?>">
        <div class="row g-2">
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Type</label><select name="qual_type" class="form-select form-select-sm"><?php foreach(comp_qual_types() as $qt): ?><option <?= (($eq['qual_type']??'')===$qt)?'selected':'' ?>><?= $qt ?></option><?php endforeach; ?></select></div>
          <div class="col-md-5"><label class="form-label small fw-semibold mb-0">Title</label><input name="title" class="form-control form-control-sm" value="<?= e($eq['title']??'') ?>" required></div>
          <div class="col-md-2"><label class="form-label small fw-semibold mb-0">Code</label><input name="code" class="form-control form-control-sm" value="<?= e($eq['code']??'') ?>"></div>
          <div class="col-md-2"><label class="form-label small fw-semibold mb-0">Certificate file</label><input type="file" name="file" class="form-control form-control-sm"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Issued date</label><input type="date" name="issued_date" class="form-control form-control-sm" value="<?= e($eq['issued_date']??'') ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Expiry date</label><input type="date" name="expiry_date" class="form-control form-control-sm" value="<?= e($eq['expiry_date']??'') ?>"></div>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Notes</label><input name="notes" class="form-control form-control-sm" value="<?= e($eq['notes']??'') ?>"></div>
        </div>
        <button class="btn btn-anb btn-sm mt-2"><i class="bi bi-save"></i> <?= $eq?'Save':'Add' ?></button>
        <?php if($eq): ?><a href="?r=compliance&tab=trainers&trainer_edit=<?= (int)$t['id'] ?>" class="btn btn-outline-secondary btn-sm mt-2">Cancel</a><?php endif; ?>
      </form></div>
    <?php endif; ?>
  <?php else: ?>
    <?php if($canEdit): ?>
    <div class="card p-3 mb-3" style="border-left:4px solid #E53935;"><h6 class="fw-bold mb-2">Add trainer / assessor</h6>
      <form method="post" action="?r=trainer_save"><div class="row g-2">
        <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Name</label><input name="name" class="form-control form-control-sm" required></div>
        <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Email</label><input name="email" class="form-control form-control-sm"></div>
        <div class="col-md-2"><label class="form-label small fw-semibold mb-0">Phone</label><input name="phone" class="form-control form-control-sm"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Position</label><input name="position" class="form-control form-control-sm"></div>
      </div><button class="btn btn-anb btn-sm mt-2"><i class="bi bi-person-plus"></i> Add trainer</button></form></div>
    <?php endif; ?>
    <div class="card p-3 mb-3"><h6 class="fw-bold mb-2">Trainer Matrix — Vocational Competency by Unit</h6>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Trainer</th><th>Position</th><?php foreach($units as $u): ?><th class="text-center"><?= $u ?></th><?php endforeach; ?><th>TAE</th></tr></thead><tbody>
        <?php foreach($trainers as $t): $codes=[]; foreach(($tquals[$t['id']]??[]) as $q){ if($q['code']) $codes[]=$q['code']; }
          $has=function($c) use($codes){ foreach($codes as $x){ if(stripos($x,$c)!==false) return true; } return false; }; ?>
          <tr><td class="small fw-semibold"><a href="?r=compliance&tab=trainers&trainer_edit=<?= (int)$t['id'] ?>"><?= e($t['name']) ?></a></td><td class="small"><?= e($t['position']) ?></td>
          <?php foreach($units as $u): ?><td class="text-center"><?= $has($u)?'<i class="bi bi-check-circle-fill text-success"></i>':'<span class="text-muted">—</span>' ?></td><?php endforeach; ?>
          <td><?= $has('TAE')?'<i class="bi bi-check-circle-fill text-success"></i>':'<span class="text-muted">—</span>' ?></td></tr>
        <?php endforeach; if(!$trainers): ?><tr><td colspan="7" class="text-muted small">No trainers yet.</td></tr><?php endif; ?>
      </tbody></table></div>
      <div class="small text-muted mt-2">Click a trainer to manage their qualifications, industry currency, PD and uploaded certificates (with expiry reminders).</div>
    </div>
  <?php endif; ?>

<?php elseif ($tab==='users'): $isAdmin = (current_user()['role']??'')==='admin'; $eu=null; if(isset($_GET['user_edit'])){ foreach($sysUsers as $su){ if((int)$su['id']===(int)$_GET['user_edit']) $eu=$su; } } ?>
  <?php if(!$isAdmin): ?><div class="alert alert-secondary py-2 small">Only the CEO/Administrator can manage user logins and roles.</div><?php endif; ?>
  <div class="row g-3">
    <div class="col-lg-7"><div class="card p-3"><h6 class="fw-bold mb-2">Staff Logins &amp; Roles (<?= count($sysUsers) ?>)</h6>
      <table class="table table-sm align-middle mb-0"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Active</th><?php if($isAdmin): ?><th></th><?php endif; ?></tr></thead><tbody>
      <?php $roleLabel=['admin'=>'CEO / Administrator','compliance_manager'=>'Compliance Manager','trainer'=>'Trainer / Assessor','office'=>'Administration','auditor'=>'Auditor (read-only)'];
      foreach($sysUsers as $su): ?><tr><td class="small fw-semibold"><?= e($su['name']) ?></td><td class="small"><?= e($su['email']) ?></td>
        <td class="small"><span class="badge text-bg-light border"><?= e($roleLabel[$su['role']]??$su['role']) ?></span></td>
        <td><?= $su['active']?'<i class="bi bi-check-circle-fill text-success"></i>':'<span class="text-muted">no</span>' ?></td>
        <?php if($isAdmin): ?><td><a href="?r=compliance&tab=users&user_edit=<?= (int)$su['id'] ?>" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-pencil"></i></a></td><?php endif; ?></tr>
      <?php endforeach; ?></tbody></table></div></div>
    <?php if($isAdmin): ?>
    <div class="col-lg-5"><div class="card p-3"><h6 class="fw-bold mb-2"><?= $eu?'Edit user':'Create user login' ?></h6>
      <form method="post" action="?r=user_save"><input type="hidden" name="id" value="<?= (int)($eu['id']??0) ?>">
        <label class="form-label small fw-semibold mb-0">Name</label><input name="name" class="form-control form-control-sm mb-2" value="<?= e($eu['name']??'') ?>" required>
        <label class="form-label small fw-semibold mb-0">Email (login)</label><input name="email" class="form-control form-control-sm mb-2" value="<?= e($eu['email']??'') ?>" required>
        <label class="form-label small fw-semibold mb-0">Role</label>
        <select name="role" class="form-select form-select-sm mb-2"><?php foreach($roleLabel as $rk=>$rl): ?><option value="<?= $rk ?>" <?= (($eu['role']??'office')===$rk)?'selected':'' ?>><?= $rl ?></option><?php endforeach; ?></select>
        <label class="form-label small fw-semibold mb-0">Password <?= $eu?'(leave blank to keep)':'' ?></label><input type="text" name="password" class="form-control form-control-sm mb-2" <?= $eu?'':'required' ?>>
        <button class="btn btn-anb btn-sm w-100"><i class="bi bi-save"></i> <?= $eu?'Save user':'Create user' ?></button>
        <?php if($eu): ?><a href="?r=compliance&tab=users" class="btn btn-outline-secondary btn-sm w-100 mt-1">Cancel</a><?php endif; ?>
      </form>
      <div class="small text-muted mt-2">Auditor = read-only access for external auditors. Compliance Manager can manage documents and registers.</div>
    </div></div>
    <?php endif; ?>
  </div>

<?php else: // ---------- REGISTER ---------- ?>
  <?php if($canEdit): $ed=$editDoc; ?>
  <div class="card p-3 mb-3" style="border-left:4px solid #E53935;">
    <h6 class="fw-bold mb-2"><?= $ed?'Edit document':'Add / register a document' ?></h6>
    <form method="post" action="?r=comp_save" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= (int)($ed['id']??0) ?>">
      <div class="row g-2">
        <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Section</label>
          <select name="section" class="form-select form-select-sm" required><option value="">— choose —</option>
          <?php foreach(array_keys($tax) as $s): ?><option <?= (($ed['section']??'')===$s)?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Category / type</label>
          <input name="subcategory" class="form-control form-control-sm" list="subcats" value="<?= e($ed['subcategory']??'') ?>">
          <datalist id="subcats"><?php foreach($tax as $s=>$items){ foreach($items as $it) echo '<option value="'.e($it).'">'; } ?></datalist></div>
        <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Unit (if applicable)</label>
          <select name="unit_code" class="form-select form-select-sm"><option value="">—</option><?php foreach($units as $u): ?><option <?= (($ed['unit_code']??'')===$u)?'selected':'' ?>><?= $u ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Document name</label><input name="doc_name" class="form-control form-control-sm" value="<?= e($ed['doc_name']??'') ?>" required></div>
        <div class="col-md-2"><label class="form-label small fw-semibold mb-0">Version</label><input name="version" class="form-control form-control-sm" value="<?= e($ed['version']??'1.0') ?>"></div>
        <div class="col-md-2"><label class="form-label small fw-semibold mb-0">Status</label><select name="status" class="form-select form-select-sm"><?php foreach(['Draft','Active','Archived'] as $s): ?><option <?= (($ed['status']??'Draft')===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label small fw-semibold mb-0">Version file</label><input type="file" name="file" class="form-control form-control-sm"></div>
        <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Approval date</label><input type="date" name="approval_date" class="form-control form-control-sm" value="<?= e($ed['approval_date']??'') ?>"></div>
        <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Review date</label><input type="date" name="review_date" class="form-control form-control-sm" value="<?= e($ed['review_date']??'') ?>"></div>
        <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Approved by</label><input name="approved_by" class="form-control form-control-sm" value="<?= e($ed['approved_by']??'Gloria Omoregie (CEO)') ?>"></div>
        <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Document owner</label><input name="owner" class="form-control form-control-sm" value="<?= e($ed['owner']??'') ?>"></div>
        <div class="col-12"><label class="form-label small fw-semibold mb-0">Notes</label><input name="notes" class="form-control form-control-sm" value="<?= e($ed['notes']??'') ?>"></div>
      </div>
      <button class="btn btn-anb btn-sm mt-2"><i class="bi bi-save"></i> <?= $ed?'Save changes':'Add document' ?></button>
      <?php if($ed): ?><a href="?r=compliance&tab=register" class="btn btn-outline-secondary btn-sm mt-2">Cancel</a><?php endif; ?>
    </form>
  </div>
  <?php endif; ?>

  <!-- filters -->
  <form method="get" class="card p-2 mb-3"><input type="hidden" name="r" value="compliance"><input type="hidden" name="tab" value="register">
    <div class="row g-2 align-items-end">
      <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Section</label><select name="section" class="form-select form-select-sm"><option value="">All sections</option><?php foreach(array_keys($tax) as $s): ?><option value="<?= e($s) ?>" <?= $fSection===$s?'selected':'' ?>><?= e($s) ?> (<?= (int)($secCounts[$s]??0) ?>)</option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label small fw-semibold mb-0">Unit</label><select name="unit" class="form-select form-select-sm"><option value="">All</option><?php foreach($units as $u): ?><option <?= $fUnit===$u?'selected':'' ?>><?= $u ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label small fw-semibold mb-0">Status</label><select name="status" class="form-select form-select-sm"><option value="">All</option><?php foreach(['Draft','Active','Archived'] as $s): ?><option <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label small fw-semibold mb-0">Search</label><input name="q" class="form-control form-control-sm" value="<?= e($fQ) ?>" placeholder="Document name / type / unit"></div>
      <div class="col-md-2"><button class="btn btn-sm btn-anb w-100"><i class="bi bi-search"></i> Filter</button></div>
    </div>
  </form>

  <div class="card p-3">
    <div class="d-flex justify-content-between mb-2"><h6 class="fw-bold mb-0">Document Register (<?= count($docs) ?>)</h6></div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
      <thead><tr><th>Document</th><th>Section</th><th>Unit</th><th>Ver.</th><th>Status</th><th>Approved by</th><th>Review date</th><th class="text-end">Actions</th></tr></thead><tbody>
      <?php foreach($docs as $d): $nd=ndays($d['review_date']); ?>
        <tr>
          <td class="small fw-semibold"><?= e($d['doc_name']) ?><div class="text-muted" style="font-weight:400;"><?= e($d['subcategory']) ?></div></td>
          <td class="small"><?= e($d['section']) ?></td>
          <td class="small"><?= e($d['unit_code']) ?></td>
          <td class="small"><?= e($d['version']) ?></td>
          <td><?= comp_status_badge($d['status']) ?></td>
          <td class="small"><?= e($d['approved_by']) ?></td>
          <td class="small"><?= e($d['review_date']) ?><?php if($nd!==null && $d['status']==='Active'){ if($nd<0) echo ' <span class="badge text-bg-danger">Overdue</span>'; elseif($nd<=60) echo ' <span class="badge text-bg-warning">Soon</span>'; } ?></td>
          <td class="text-end" style="white-space:nowrap;">
            <a href="?r=compliance&tab=register&view=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-secondary py-0" title="View"><i class="bi bi-eye"></i></a>
            <?php if($d['file_path']): ?><a href="?r=comp_download&id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-danger py-0" title="Download"><i class="bi bi-download"></i></a><?php endif; ?>
            <?php if($canEdit): ?>
              <a href="?r=compliance&tab=register&edit=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-secondary py-0" title="Edit"><i class="bi bi-pencil"></i></a>
              <?php if($d['status']!=='Archived'): ?><a href="?r=comp_archive&id=<?= (int)$d['id'] ?>&to=Archived" class="btn btn-sm btn-outline-secondary py-0" title="Archive" onclick="return confirm('Archive this document?')"><i class="bi bi-archive"></i></a>
              <?php else: ?><a href="?r=comp_archive&id=<?= (int)$d['id'] ?>&to=Active" class="btn btn-sm btn-outline-success py-0" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></a><?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if(!$docs): ?><tr><td colspan="8" class="text-muted small">No documents match your filters.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
<?php endif; ?>
