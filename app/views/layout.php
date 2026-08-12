<?php $u = current_user(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?: 'A&B First Aid - SMS') ?></title>
<link rel="icon" type="image/png" href="assets/logo-color.png">
<link rel="apple-touch-icon" href="assets/logo-color.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  :root{ --anb:#E53935; --anb-dark:#2F1D3A; }
  body{ background:#f4f5f7; }
  .sidebar{ background:var(--anb-dark); width:240px; position:fixed; top:0; left:0; height:100vh; display:flex; flex-direction:column; overflow:hidden; }
  .sidebar .brand{ color:#fff; font-weight:700; font-size:1.05rem; padding:16px 20px; border-bottom:1px solid rgba(255,255,255,.12); flex:0 0 auto; }
  .sidebar .brand small{ color:#e0a; }
  .sidebar .nav-scroll{ flex:1 1 auto; overflow-y:auto; overflow-x:hidden; }
  .sidebar .nav-scroll::-webkit-scrollbar{ width:6px; }
  .sidebar .nav-scroll::-webkit-scrollbar-thumb{ background:rgba(255,255,255,.2); border-radius:3px; }
  .sidebar a{ color:#cfc9d6; display:flex; gap:10px; align-items:center; padding:9px 20px; text-decoration:none; font-size:.9rem; }
  .sidebar a:hover, .sidebar a.active{ background:rgba(255,255,255,.08); color:#fff; border-left:3px solid var(--anb); }
  .sidebar .signout{ flex:0 0 auto; border-top:1px solid rgba(255,255,255,.12); }
  .content{ margin-left:240px; padding:26px 32px; }
  .topbar{ display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; }
  .stat-card{ border:none; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.05); }
  .stat-card .num{ font-size:1.9rem; font-weight:700; color:var(--anb-dark); }
  .stat-card .ico{ font-size:1.6rem; opacity:.9; }
  .card{ border:none; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.05); }
  .table thead th{ font-size:.78rem; text-transform:uppercase; letter-spacing:.03em; color:#8a8a8a; }
  .btn-anb{ background:var(--anb); color:#fff; } .btn-anb:hover{ background:#c62828; color:#fff; }
  .logo-badge{ width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#E53935,#8e24aa);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:800; }
  .mtopbar{ display:none; }
  .navoverlay{ display:none; }
  /* --- mobile / tablet: collapse the sidebar into an off-canvas drawer --- */
  @media (max-width: 991px){
    .sidebar{ transform:translateX(-100%); transition:transform .25s ease; z-index:1050; box-shadow:2px 0 14px rgba(0,0,0,.35); }
    .sidebar.open{ transform:translateX(0); }
    .content{ margin-left:0; padding:16px; padding-top:66px; }
    .mtopbar{ display:flex; align-items:center; gap:10px; position:fixed; top:0; left:0; right:0; height:54px; background:var(--anb-dark); color:#fff; padding:0 12px; z-index:1040; box-shadow:0 2px 8px rgba(0,0,0,.18); }
    .mtopbar .navtoggle{ background:none; border:0; color:#fff; font-size:1.6rem; line-height:1; padding:2px 8px; }
    .navoverlay.show{ display:block; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1045; }
    .topbar{ flex-wrap:wrap; gap:8px; }
    .topbar .btn, .topbar a.btn{ font-size:.85rem; }
    .table-responsive{ -webkit-overflow-scrolling:touch; }
    .stat-card .num{ font-size:1.5rem; }
  }
</style>
</head>
<body>
<?php if ($u && empty($studentChrome)): $r = $_GET['r'] ?? 'dashboard'; ?>
<div class="mtopbar">
  <button class="navtoggle" type="button" onclick="anbNav(true)" aria-label="Menu"><i class="bi bi-list"></i></button>
  <img src="assets/logo.png" alt="A&amp;B First Aid Training" style="height:30px;width:auto;">
</div>
<div class="navoverlay" id="navOverlay" onclick="anbNav(false)"></div>
<div class="sidebar">
  <div class="brand text-center py-3">
    <img src="assets/logo.png" alt="A&amp;B First Aid Training" style="max-width:180px;width:100%;height:auto;">
    <div class="small mt-2" style="color:#e0a;letter-spacing:.05em;">STUDENT MANAGEMENT</div>
  </div>
  <div class="nav-scroll">
  <?php if(role_allowed('dashboard')): ?><a href="?r=dashboard"    class="<?= $r==='dashboard'?'active':'' ?>"><i class="bi bi-speedometer2"></i> Dashboard</a><?php endif; ?>
  <?php if(role_allowed('trainer')): ?><a href="?r=trainer"      class="<?= $r==='trainer'?'active':'' ?>"><i class="bi bi-clipboard2-pulse"></i> Trainer</a><?php endif; ?>
  <?php if(($u['role']??'')==='trainer'): ?><a href="?r=my_trainer" class="<?= $r==='my_trainer'?'active':'' ?>"><i class="bi bi-person-vcard"></i> My Profile</a><?php endif; ?>
  <?php if(role_allowed('students')): ?><a href="?r=students"     class="<?= $r==='students'||$r==='student'?'active':'' ?>"><i class="bi bi-people"></i> Students</a><?php endif; ?>
  <?php if(role_allowed('enrolments')): ?><a href="?r=enrolments"   class="<?= $r==='enrolments'?'active':'' ?>"><i class="bi bi-journal-check"></i> Enrolments</a><?php endif; ?>
  <?php if(role_allowed('group_bookings')): ?><a href="?r=group_bookings" class="<?= in_array($r,['group_bookings','group_booking_view'],true)?'active':'' ?>"><i class="bi bi-building"></i> Group Bookings</a><?php endif; ?>
  <?php if(role_allowed('schedules')): ?><a href="?r=schedules"    class="<?= $r==='schedules'?'active':'' ?>"><i class="bi bi-calendar3"></i> Schedules</a><?php endif; ?>
  <?php if(role_allowed('locations')): ?><a href="?r=locations"    class="<?= $r==='locations'?'active':'' ?>"><i class="bi bi-geo-alt"></i> Locations</a><?php endif; ?>
  <?php if(role_allowed('courses')): ?><a href="?r=courses"      class="<?= $r==='courses'?'active':'' ?>"><i class="bi bi-mortarboard"></i> Courses</a><?php endif; ?>
  <?php if(role_allowed('content')): ?><a href="?r=content"      class="<?= in_array($r,['content','quiz_edit'],true)?'active':'' ?>"><i class="bi bi-collection-play"></i> Course Content</a><?php endif; ?>
  <?php if(role_allowed('certificates')): ?><a href="?r=certificates" class="<?= $r==='certificates'?'active':'' ?>"><i class="bi bi-award"></i> Certificates</a><?php endif; ?>
  <?php if(role_allowed('surveys')): ?><a href="?r=surveys"      class="<?= $r==='surveys'||$r==='survey_view'?'active':'' ?>"><i class="bi bi-ui-checks"></i> Survey Reporting</a><?php endif; ?>
  <?php if(role_allowed('review_links')): ?><a href="?r=review_links" class="<?= $r==='review_links'?'active':'' ?>"><i class="bi bi-star-fill"></i> Google Reviews</a><?php endif; ?>
  <?php if(role_allowed('reminders')): ?><a href="?r=reminders"    class="<?= $r==='reminders'?'active':'' ?>"><i class="bi bi-bell"></i> Renewal Reminders</a><?php endif; ?>
  <?php if(role_allowed('avetmiss')): ?><a href="?r=avetmiss"     class="<?= $r==='avetmiss'?'active':'' ?>"><i class="bi bi-file-earmark-bar-graph"></i> AVETMISS</a><?php endif; ?>
  <?php if(role_allowed('rto_sync')): ?><a href="?r=rto_sync"     class="<?= $r==='rto_sync'?'active':'' ?>"><i class="bi bi-cloud-arrow-up"></i> RTO Data Cloud</a><?php endif; ?>
  <?php if(role_allowed('emails')): ?><a href="?r=emails"       class="<?= $r==='emails'?'active':'' ?>"><i class="bi bi-envelope-paper"></i> Email Templates</a><?php endif; ?>
  <?php if(role_allowed('management')): ?><a href="?r=management"   class="<?= in_array($r,['management'],true)?'active':'' ?>"><i class="bi bi-folder2-open"></i> Management</a><?php endif; ?>
  <?php if(role_allowed('compliance')): ?><a href="?r=compliance"   class="<?= in_array($r,['compliance'],true)?'active':'' ?>"><i class="bi bi-shield-check"></i> Compliance</a><?php endif; ?>
  </div>
  <a href="?r=logout" class="signout"><i class="bi bi-box-arrow-right"></i> Sign out</a>
</div>
<div class="content">
  <?= $content ?>
</div>
<?php else: ?>
  <?= $content ?>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function anbNav(open){ var s=document.querySelector('.sidebar'), o=document.getElementById('navOverlay'); if(!s)return; s.classList.toggle('open',open); if(o)o.classList.toggle('show',open); }
document.addEventListener('click',function(e){ var a=e.target.closest('.sidebar a'); if(a) anbNav(false); });
</script>
</body>
</html>
