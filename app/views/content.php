<?php $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); ?>
<div class="topbar">
  <div><h4 class="mb-0 fw-bold" style="color:#2F1D3A;">Course Content (LMS)</h4>
    <div class="text-muted small">Upload SCORM online modules and build assessment quizzes — learners complete these before their class is signed off.</div></div>
</div>

<?php if ($flash): ?><div class="alert alert-info py-2"><?= e($flash) ?></div><?php endif; ?>

<div class="row g-3 mb-3">
  <!-- Upload SCORM -->
  <div class="col-lg-6">
    <div class="card p-3 h-100">
      <h6 class="fw-bold mb-3"><i class="bi bi-file-earmark-zip text-danger"></i> Upload SCORM package</h6>
      <form method="post" action="?r=content_upload" enctype="multipart/form-data">
        <label class="form-label small fw-semibold">Course</label>
        <select name="course_id" class="form-select mb-2" required>
          <option value="">Select course…</option>
          <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['code'].' — '.$c['title']) ?></option><?php endforeach; ?>
        </select>
        <label class="form-label small fw-semibold">Module title</label>
        <input name="title" class="form-control mb-2" placeholder="e.g. CPR Online Theory" required>
        <label class="form-label small fw-semibold">SCORM .zip file</label>
        <input type="file" name="scorm" accept=".zip" class="form-control mb-3" required>
        <button class="btn btn-anb w-100"><i class="bi bi-upload"></i> Upload &amp; publish</button>
      </form>
      <div class="text-muted small mt-2"><i class="bi bi-info-circle"></i> Export your online course from Articulate / iSpring / Rise etc. as a SCORM 1.2 or 2004 zip. We read the imsmanifest, detect the launch page, and track completion automatically.</div>
    </div>
  </div>

  <!-- Create quiz -->
  <div class="col-lg-6">
    <div class="card p-3 h-100">
      <h6 class="fw-bold mb-3"><i class="bi bi-ui-checks-grid text-primary"></i> Build an assessment quiz</h6>
      <form method="post" action="?r=module_new">
        <label class="form-label small fw-semibold">Course</label>
        <select name="course_id" class="form-select mb-2" required>
          <option value="">Select course…</option>
          <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['code'].' — '.$c['title']) ?></option><?php endforeach; ?>
        </select>
        <label class="form-label small fw-semibold">Quiz title</label>
        <input name="title" class="form-control mb-2" value="Knowledge Check" required>
        <label class="form-label small fw-semibold">Pass mark (%)</label>
        <input name="pass_mark" type="number" min="1" max="100" value="80" class="form-control mb-3">
        <button class="btn btn-outline-danger w-100"><i class="bi bi-plus-circle"></i> Create quiz &amp; add questions</button>
      </form>
      <div class="text-muted small mt-2"><i class="bi bi-info-circle"></i> Single-choice, multiple-choice and true/false questions. Passing the quiz marks that part of the learner's online requirement complete.</div>
    </div>
  </div>
</div>

<div class="card p-3">
  <h6 class="fw-bold mb-3">Online modules</h6>
  <table class="table align-middle mb-0">
    <thead><tr><th>Course</th><th>Module</th><th>Type</th><th class="text-center">Content</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($modules as $m): ?>
      <tr>
        <td class="small fw-semibold"><?= e($m['course_code']) ?></td>
        <td class="small"><?= e($m['title']) ?></td>
        <td><span class="badge <?= $m['type']==='quiz'?'text-bg-primary':'text-bg-danger' ?>"><?= strtoupper($m['type']) ?></span></td>
        <td class="text-center small">
          <?php if ($m['type']==='quiz'): ?><?= (int)$m['question_count'] ?> question<?= $m['question_count']==1?'':'s' ?>
          <?php else: ?><span class="text-muted"><?= e($m['launch_url']) ?></span><?php endif; ?>
        </td>
        <td class="text-end">
          <a class="btn btn-sm btn-outline-secondary" href="?r=learn&module_id=<?= (int)$m['id'] ?>" target="_blank"><i class="bi bi-play-circle"></i> Preview</a>
          <?php if ($m['type']==='quiz'): ?>
            <a class="btn btn-sm btn-outline-primary" href="?r=quiz_edit&id=<?= (int)$m['id'] ?>"><i class="bi bi-pencil"></i> Edit</a>
          <?php endif; ?>
          <a class="btn btn-sm btn-outline-danger" href="?r=module_delete&id=<?= (int)$m['id'] ?>" onclick="return confirm('Remove this module?')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
    <?php endforeach; if(!$modules): ?>
      <tr><td colspan="5" class="text-muted small text-center py-3">No modules yet — upload a SCORM package or build a quiz above.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
