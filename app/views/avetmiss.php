<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">AVETMISS Reporting</h4>
    <div class="text-muted small">NCVER AVETMISS 8.0 — NAT-file export, built from your live data</div></div>
</div>

<?php
$labels = [
  'NAT00010'=>'Training organisation',
  'NAT00020'=>'Delivery location',
  'NAT00030'=>'Program (course)',
  'NAT00060'=>'Subject (unit of competency)',
  'NAT00080'=>'Client (student)',
  'NAT00085'=>'Client postal details / USI',
  'NAT00090'=>'Client disability',
  'NAT00100'=>'Client prior educational achievement',
  'NAT00120'=>'Enrolment / training activity',
  'NAT00130'=>'Program completed',
];
$totalRecords = array_sum($summary);
?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-3"><i class="bi bi-calendar-range text-danger"></i> Collection period</h6>
      <form method="get" class="mb-0">
        <input type="hidden" name="r" value="avetmiss">
        <label class="form-label small fw-semibold">Year</label>
        <select name="year" class="form-select mb-3" onchange="this.form.submit()">
          <?php foreach ([2024,2025,2026,2027] as $y): ?>
            <option value="<?= $y ?>" <?= $y===$year?'selected':'' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
        <label class="form-label small fw-semibold">Range</label>
        <select name="q" class="form-select mb-3" onchange="this.form.submit()">
          <?php foreach (['full'=>'Full year','q1'=>'Q1 (Jan–Mar)','q2'=>'Q2 (Apr–Jun)','q3'=>'Q3 (Jul–Sep)','q4'=>'Q4 (Oct–Dec)'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $k===$q?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <div class="text-muted small mb-3"><i class="bi bi-info-circle"></i> Reporting <strong><?= e($label) ?></strong> (<?= e($from) ?> → <?= e($to) ?>).</div>
      <a href="?r=avetmiss_export&year=<?= $year ?>&q=<?= e($q) ?>" class="btn btn-anb w-100">
        <i class="bi bi-download"></i> Build AVETMISS export (.zip)
      </a>
      <div class="text-muted small mt-2"><?= $totalRecords ?> data records across 10 NAT files, ready to submit to NCVER / your STA.</div>
    </div>

    <div class="card p-3">
      <h6 class="fw-bold mb-2"><i class="bi bi-shield-check text-success"></i> Compliance</h6>
      <ul class="small text-muted mb-0" style="padding-left:18px;">
        <li>Fixed-width fields to the AVETMISS 8.0 data element definitions.</li>
        <li>National outcome, funding-source, language &amp; country codes applied.</li>
        <li>USI carried on every client record (NAT00085).</li>
        <li>Load the .zip into NCVER's free AVS validator, then lodge — any edit-rule flags on your real data are tuned here first.</li>
      </ul>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card p-3">
      <h6 class="fw-bold mb-3">Files in this export</h6>
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th style="width:110px;">File</th><th>Contents</th><th class="text-center">Records</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($summary as $file=>$count):
          $base = str_replace('.txt','',$file); ?>
          <tr>
            <td class="fw-semibold small"><?= e($base) ?></td>
            <td class="small"><?= e($labels[$base] ?? '') ?></td>
            <td class="text-center">
              <?php if ($count>0): ?><span class="badge text-bg-success"><?= $count ?></span>
              <?php else: ?><span class="badge text-bg-light text-muted">0</span><?php endif; ?>
            </td>
            <td class="text-end">
              <?php if ($count>0): ?>
                <a class="small" target="_blank" href="?r=avetmiss_preview&year=<?= $year ?>&q=<?= e($q) ?>&file=<?= e($file) ?>">preview</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="border-top"><td colspan="2" class="fw-semibold small text-end">Total records</td>
          <td class="text-center fw-bold"><?= $totalRecords ?></td><td></td></tr></tfoot>
      </table>
      <div class="text-muted small mt-3"><i class="bi bi-lightbulb text-warning"></i>
        Files with 0 records are still included — NCVER expects the full NAT set, and empty files are valid where there's no activity (e.g. no disabilities recorded this period).</div>
    </div>
  </div>
</div>
