<?php $res = $ob['results_arr'] ?? []; ?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Observation checklist</h4>
    <div class="text-muted small"><?= e($module['course_code']) ?> — <?= $learner ? e($learner['first_name'].' '.$learner['last_name']) : 'Learner' ?></div></div>
  <a href="?r=obs_list&module_id=<?= (int)$module['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<form method="post" action="?r=obs_save">
  <input type="hidden" name="module_id" value="<?= (int)$module['id'] ?>">
  <input type="hidden" name="enrol" value="<?= (int)$enrolId ?>">
  <div class="card p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
      <div class="text-muted small">Mark each skill the learner demonstrated during the face-to-face practical assessment.</div>
      <button type="button" class="btn btn-sm btn-outline-success" onclick="anbAllSatisfactory()"><i class="bi bi-check2-all"></i> Mark all Satisfactory</button>
    </div>
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Skill demonstrated</th><th class="text-center" style="width:90px;">S</th><th class="text-center" style="width:130px;">NYS</th></tr></thead>
      <tbody>
      <?php foreach ($skills as $i=>$sk): $cur = $res[(string)$i] ?? ($res[$i] ?? ''); ?>
        <tr>
          <td class="small"><?= e($sk) ?></td>
          <td class="text-center"><input class="form-check-input" type="radio" name="r[<?= $i ?>]" value="S" <?= $cur==='S'?'checked':'' ?>></td>
          <td class="text-center"><input class="form-check-input" type="radio" name="r[<?= $i ?>]" value="NYS" <?= $cur==='NYS'?'checked':'' ?>></td>
        </tr>
      <?php endforeach; if (!$skills): ?><tr><td colspan="3" class="text-muted small">No skills defined for this activity.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <div class="text-muted small mt-2">S = Satisfactory &nbsp;·&nbsp; NYS = Not Yet Satisfactory</div>
  </div>

  <div class="card p-3 mb-4">
    <label class="form-label small fw-semibold">Overall outcome</label>
    <div class="mb-3">
      <label class="me-4"><input type="radio" name="overall" value="satisfactory" <?= ($ob['overall']??'')==='satisfactory'?'checked':'' ?>> Satisfactory</label>
      <label><input type="radio" name="overall" value="not_yet" <?= ($ob['overall']??'')==='not_yet'?'checked':'' ?>> Not Yet Satisfactory</label>
    </div>
    <div class="row g-2">
      <div class="col-md-5"><label class="form-label small fw-semibold">Assessor name</label>
        <input name="assessor" class="form-control form-control-sm" value="<?= e($ob['assessor'] ?? '') ?>"></div>
      <div class="col-md-7"><label class="form-label small fw-semibold">Comments (optional)</label>
        <input name="comments" class="form-control form-control-sm" value="<?= e($ob['comments'] ?? '') ?>"></div>
    </div>
    <button class="btn btn-anb mt-3"><i class="bi bi-save"></i> Save observation</button>
  </div>
</form>
<script>
function anbAllSatisfactory(){
  document.querySelectorAll('input[type=radio][value="S"]').forEach(function(r){ r.checked = true; });
  var o = document.querySelector('input[name="overall"][value="satisfactory"]'); if (o) o.checked = true;
}
</script>
