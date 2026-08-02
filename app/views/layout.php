<?php $u = current_user(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?: 'A&B First Aid - SMS') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  :root{ --anb:#E53935; --anb-dark:#2F1D3A; }
  body{ background:#f4f5f7; }
  .sidebar{ background:var(--anb-dark); min-height:100vh; width:240px; position:fixed; }
  .sidebar .brand{ color:#fff; font-weight:700; font-size:1.05rem; padding:18px 20px; border-bottom:1px solid rgba(255,255,255,.12); }
  .sidebar .brand small{ color:#e0a; }
  .sidebar a{ color:#cfc9d6; display:flex; gap:10px; align-items:center; padding:11px 20px; text-decoration:none; font-size:.94rem; }
  .sidebar a:hover, .sidebar a.active{ background:rgba(255,255,255,.08); color:#fff; border-left:3px solid var(--anb); }
  .content{ margin-left:240px; padding:26px 32px; }
  .topbar{ display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; }
  .stat-card{ border:none; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.05); }
  .stat-card .num{ font-size:1.9rem; font-weight:700; color:var(--anb-dark); }
  .stat-card .ico{ font-size:1.6rem; opacity:.9; }
  .card{ border:none; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.05); }
  .table thead th{ font-size:.78rem; text-transform:uppercase; letter-spacing:.03em; color:#8a8a8a; }
  .btn-anb{ background:var(--anb); color:#fff; } .btn-anb:hover{ background:#c62828; color:#fff; }
  .logo-badge{ width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#E53935,#8e24aa);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:800; }
</style>
</head>
<body>
<?php if ($u): $r = $_GET['r'] ?? 'dashboard'; ?>
<div class="sidebar">
  <div class="brand"><span class="logo-badge">A&amp;B</span> First Aid<br><small>Training SMS</small></div>
  <a href="?r=dashboard"    class="<?= $r==='dashboard'?'active':'' ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
  <a href="?r=trainer"      class="<?= $r==='trainer'?'active':'' ?>"><i class="bi bi-clipboard2-pulse"></i> Trainer</a>
  <a href="?r=students"     class="<?= $r==='students'||$r==='student'?'active':'' ?>"><i class="bi bi-people"></i> Students</a>
  <a href="?r=enrolments"   class="<?= $r==='enrolments'?'active':'' ?>"><i class="bi bi-journal-check"></i> Enrolments</a>
  <a href="?r=schedules"    class="<?= $r==='schedules'?'active':'' ?>"><i class="bi bi-calendar3"></i> Schedules</a>
  <a href="?r=courses"      class="<?= $r==='courses'?'active':'' ?>"><i class="bi bi-mortarboard"></i> Courses</a>
  <a href="?r=content"      class="<?= in_array($r,['content','quiz_edit'],true)?'active':'' ?>"><i class="bi bi-collection-play"></i> Course Content</a>
  <a href="?r=certificates" class="<?= $r==='certificates'?'active':'' ?>"><i class="bi bi-award"></i> Certificates</a>
  <a href="?r=surveys"      class="<?= $r==='surveys'||$r==='survey_view'?'active':'' ?>"><i class="bi bi-ui-checks"></i> Survey Reporting</a>
  <a href="?r=reminders"    class="<?= $r==='reminders'?'active':'' ?>"><i class="bi bi-bell"></i> Renewal Reminders</a>
  <a href="?r=avetmiss"     class="<?= $r==='avetmiss'?'active':'' ?>"><i class="bi bi-file-earmark-bar-graph"></i> AVETMISS</a>
  <a href="?r=logout" style="margin-top:20px;border-top:1px solid rgba(255,255,255,.12)"><i class="bi bi-box-arrow-right"></i> Sign out</a>
</div>
<div class="content">
  <?= $content ?>
</div>
<?php else: ?>
  <?= $content ?>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
