<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Survey Reporting</h4>
    <div class="text-muted small">AQTF / NCVER Quality Indicators — Learner Engagement &amp; Employer Satisfaction</div></div>
</div>

<div class="row g-3 mb-3">
  <?php
  $cards = [
    ['learner','Learner Questionnaire','bi-person-check','#E53935'],
    ['employer','Employer Questionnaire','bi-building-check','#8e24aa'],
  ];
  foreach ($cards as [$key,$name,$ico,$col]): $s = $stats[$key]; ?>
    <div class="col-md-6">
      <div class="card stat-card p-3">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small text-uppercase fw-semibold"><?= e($name) ?></div>
            <div class="num"><?= $s['done'] ?><span class="text-muted" style="font-size:1rem;">/<?= $s['sent'] ?> completed</span></div>
            <div class="small mt-1">
              <span class="badge text-bg-light">Response rate <?= $s['rate'] ?>%</span>
              <?php if ($s['avg']!==null): ?>
                <span class="badge text-bg-success ms-1">Avg score <?= $s['avg'] ?> / 4</span>
              <?php endif; ?>
            </div>
          </div>
          <i class="bi <?= $ico ?> ico" style="color:<?= $col ?>;"></i>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card p-3">
  <h6 class="fw-bold mb-3">All surveys</h6>
  <table class="table align-middle mb-0">
    <thead><tr><th>Type</th><th>Respondent</th><th>Course</th><th>Sent</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $sv):
      $link = (isset($_SERVER['HTTP_HOST'])?('http'.(($_SERVER['HTTPS']??'')?'s':'').'://'.$_SERVER['HTTP_HOST']):'').'/?r=survey&t='.$sv['token'];
    ?>
      <tr>
        <td><span class="badge <?= $sv['type']==='employer'?'text-bg-primary':'text-bg-danger' ?>"><?= ucfirst($sv['type']) ?></span></td>
        <td class="small fw-semibold"><?= e($sv['respondent_name'] ?: (($sv['first_name']??'').' '.($sv['last_name']??''))) ?>
          <?php if ($sv['company_name']): ?><div class="text-muted" style="font-size:.78rem;"><?= e($sv['company_name']) ?></div><?php endif; ?>
        </td>
        <td class="small"><?= e($sv['course_code'] ?? '') ?></td>
        <td class="small text-muted"><?= e(substr((string)$sv['sent_at'],0,10)) ?></td>
        <td>
          <?php if ($sv['completed_at']): ?>
            <span class="badge text-bg-success">Completed</span>
          <?php else: ?>
            <span class="badge text-bg-warning">Awaiting</span>
          <?php endif; ?>
        </td>
        <td class="text-end">
          <?php if ($sv['completed_at']): ?>
            <a class="btn btn-sm btn-outline-secondary" href="?r=survey_view&id=<?= $sv['id'] ?>">View</a>
          <?php else: ?>
            <button class="btn btn-sm btn-outline-danger" onclick="navigator.clipboard.writeText('<?= e($link) ?>');this.textContent='Link copied';">Copy link</button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="text-muted small mt-3"><i class="bi bi-info-circle"></i> A learner survey link is generated automatically with every certificate. Employer links can be sent to the funding organisation. Responses roll up into your annual Quality Indicator summary.</div>
</div>
