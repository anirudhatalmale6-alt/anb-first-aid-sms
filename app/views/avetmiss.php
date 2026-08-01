<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">AVETMISS Reporting</h4>
    <div class="text-muted small">NCVER / NSW compliant NAT-file export</div></div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card p-3">
      <h6 class="fw-bold mb-3">Generate NAT files</h6>
      <label class="form-label small fw-semibold">Collection period</label>
      <select class="form-select mb-3"><option>2026 — Full year</option><option>2026 — Q3 (Jul–Sep)</option></select>
      <button class="btn btn-anb w-100"><i class="bi bi-download"></i> Build AVETMISS export (.zip)</button>
      <div class="text-muted small mt-2">Produces the NAT files ready to submit to NCVER / your STA.</div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card p-3">
      <h6 class="fw-bold mb-3">What gets generated</h6>
      <table class="table table-sm mb-0">
        <tbody>
        <?php
        $nat = [
          ['NAT00010','Training organisation'],
          ['NAT00020','Training organisation delivery location'],
          ['NAT00030','Program (course)'],
          ['NAT00060','Subject (unit of competency)'],
          ['NAT00080','Client (student)'],
          ['NAT00085','Client postal details / USI'],
          ['NAT00090','Client disability'],
          ['NAT00100','Client prior educational achievement'],
          ['NAT00120','Enrolment / subject outcome'],
          ['NAT00130','Program completed'],
        ];
        foreach ($nat as $n): ?>
          <tr><td class="fw-semibold small" style="width:120px;"><?= e($n[0]) ?></td><td class="small"><?= e($n[1]) ?></td>
              <td class="text-end"><span class="badge text-bg-success">ready</span></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="text-muted small mt-2"><i class="bi bi-shield-check text-success"></i> Built to AVETMISS 8.0. Validated against the NCVER edit rules before you submit.</div>
    </div>
  </div>
</div>
