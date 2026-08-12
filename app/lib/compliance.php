<?php
// Compliance Management Module - schema, taxonomy, helpers.
declare(strict_types=1);

function comp_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS compliance_docs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        section TEXT NOT NULL, subcategory TEXT, unit_code TEXT,
        doc_name TEXT NOT NULL, version TEXT DEFAULT '1.0',
        status TEXT DEFAULT 'Draft',            -- Draft | Active | Archived
        approval_date TEXT, review_date TEXT, approved_by TEXT, owner TEXT,
        notes TEXT, file_path TEXT, original_name TEXT,
        created_at TEXT DEFAULT (datetime('now')), updated_at TEXT DEFAULT (datetime('now'))
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS compliance_versions (
        id INTEGER PRIMARY KEY AUTOINCREMENT, doc_id INTEGER, version TEXT,
        file_path TEXT, original_name TEXT, note TEXT, changed_by TEXT,
        changed_at TEXT DEFAULT (datetime('now'))
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS compliance_audit (
        id INTEGER PRIMARY KEY AUTOINCREMENT, doc_id INTEGER, action TEXT, detail TEXT,
        user_name TEXT, at TEXT DEFAULT (datetime('now'))
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS ci_register (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ref TEXT, date_raised TEXT, source TEXT,
        description TEXT, action_required TEXT, responsible TEXT, due_date TEXT,
        status TEXT DEFAULT 'Open',             -- Open | In Progress | Completed
        completed_date TEXT, linked_type TEXT, linked_ref TEXT,
        created_at TEXT DEFAULT (datetime('now'))
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS equipment (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, category TEXT, asset_id TEXT, location TEXT,
        purchase_date TEXT, last_service_date TEXT, next_service_date TEXT, replacement_date TEXT,
        status TEXT DEFAULT 'In Service', notes TEXT,
        created_at TEXT DEFAULT (datetime('now')), updated_at TEXT DEFAULT (datetime('now'))
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS trainer_profiles (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT, phone TEXT,
        position TEXT, notes TEXT, active INTEGER DEFAULT 1, created_at TEXT DEFAULT (datetime('now'))
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS trainer_quals (
        id INTEGER PRIMARY KEY AUTOINCREMENT, trainer_id INTEGER, qual_type TEXT, title TEXT, code TEXT,
        issued_date TEXT, expiry_date TEXT, file_path TEXT, original_name TEXT, notes TEXT
    )");
    comp_trainer_extra_schema($db);
}

// Add later trainer_profiles columns (declaration + insurance) if missing.
function comp_trainer_extra_schema(PDO $db): void {
    $c = $db->query("PRAGMA table_info(trainer_profiles)")->fetchAll(PDO::FETCH_COLUMN, 1);
    foreach (['declaration_name','declaration_date','insurance_type','insurance_provider',
              'insurance_policy_no','insurance_expiry','insurance_file','insurance_original_name'] as $col) {
        if (!in_array($col, $c, true)) $db->exec("ALTER TABLE trainer_profiles ADD COLUMN $col TEXT");
    }
}

function comp_equip_categories(): array { return ['CPR Manikins','AED Trainers','Training Equipment','First Aid Kits','Consumables','Other']; }
function comp_qual_types(): array { return ['Qualification','Vocational Competency','Industry Currency','Professional Development','Employment Document','Certificate']; }

function comp_tax(): array {
    return [
     'Governance' => ['Quality Management System (QMS)','Policies and Procedures Manual','Compliance Register','Legislative Register','Risk Register','Continuous Improvement Register','Internal Audit Register','Internal Audit Reports','Management Review Minutes','Organisational Chart','Position Descriptions','Document Register'],
     'Training & Assessment' => ['Training and Assessment Strategy (TAS)','Session Plan','Trainer Guide','Assessment Mapping Matrix','Learning Resources','Assessment Tools','Marking Guide','Observation Checklist','Practical Assessment','Knowledge Assessment','Learner Instructions','Assessment Validation Records'],
     'Trainer Management' => ['Personal Details','Resume','Qualifications','Vocational Competency','Industry Currency','Professional Development','Employment Documents','Trainer Matrix','Uploaded Certificates'],
     'Student Documentation' => ['Enrolment Form','LLN Assessment','USI Verification','Attendance','Assessment Results','Practical Assessment','Incident Report','Statements of Attainment','Certificates','Student File Checklist'],
     'Assessment Management' => ['Assessment Tools','Assessment Mapping','Assessment Instructions','Marking Guides','Practical Checklists','Assessment Feedback','Reassessment Records'],
     'Validation' => ['Validation Schedule','Validation Reports','Validation Meeting Minutes','Validation Attendance','Validation Register','Validation Action Plans'],
     'Industry Consultation' => ['Industry Consultation Register','Employer Feedback','Student Feedback','Trainer Feedback','Consultation Minutes','Industry Survey Results','Improvement Actions'],
     'Continuous Improvement' => ['Continuous Improvement Register','Corrective Actions','Preventive Actions','Improvement Log','Meeting Minutes'],
     'Student Handbook & Policies' => ['Student Handbook','Refund Policy','Complaints Policy','Appeals Policy','Privacy Policy','Access & Equity Policy','Code of Conduct','WHS Policy'],
     'WHS' => ['Venue Risk Assessments','Incident Reports','Emergency Procedures','Safety Checklists'],
     'Equipment' => ['CPR Manikins','AED Trainers','Training Equipment','First Aid Kits','Consumables'],
     'Certificates' => ['Statement of Attainment Templates','Certificate Templates','Certificate Register','Certificate Reissue Register'],
     'Forms' => ['Enrolment Form','Withdrawal Form','Credit Transfer','RPL','Complaint Form','Appeal Form','Incident Report','Reasonable Adjustment','Validation Form','Industry Consultation Form','Student Feedback','Employer Feedback'],
     'Registers' => ['Student Register','Trainer Register','Certificate Register','Validation Register','Industry Consultation Register','Complaints Register','Appeals Register','Continuous Improvement Register','Equipment Register','Document Register','Risk Register'],
    ];
}
function comp_units(): array { return ['HLTAID009','HLTAID010','HLTAID011','HLTAID012']; }

function comp_can_edit(): bool {
    $u = current_user();
    return $u && in_array($u['role'] ?? '', ['admin','compliance_manager'], true);
}
// creating/editing staff logins & roles is an administrator function
function comp_role_admin_or_cm(): bool {
    $u = current_user();
    return $u && ($u['role'] ?? '') === 'admin';
}

function comp_audit(PDO $db, int $docId, string $action, string $detail=''): void {
    $u = current_user(); $name = $u['name'] ?? 'System';
    $db->prepare("INSERT INTO compliance_audit (doc_id,action,detail,user_name) VALUES (?,?,?,?)")
       ->execute([$docId,$action,$detail,$name]);
}

// One-time idempotent import of the audit documents already in org_files.
function comp_migrate(PDO $db): int {
    $have = (int)$db->query("SELECT COUNT(*) FROM compliance_docs")->fetchColumn();
    if ($have > 0) return 0;
    $rows = $db->query("SELECT * FROM org_files WHERE category='Compliance Management' ORDER BY original_name")->fetchAll(PDO::FETCH_ASSOC);
    $typeMap = [
      'Assessment_Mapping_Matrix' => 'Assessment Mapping Matrix',
      'Training_and_Assessment_Strategy' => 'Training and Assessment Strategy (TAS)',
      'Knowledge_Assessment_Tool' => 'Knowledge Assessment',
      'Practical_Observation_Checklist' => 'Observation Checklist',
      'Incident_Report_Assessment_Tool' => 'Assessment Tools',
      'Assessment_Validation_Record' => 'Assessment Validation Records',
    ];
    $n = 0;
    foreach ($rows as $r) {
        $orig = $r['original_name'];
        if (preg_match('/^(HLTAID0\d\d)_(.+)\.docx$/', $orig, $m)) {
            $unit = $m[1]; $key = $m[2];
            $sub = $typeMap[$key] ?? str_replace('_',' ',$key);
            $section = 'Training & Assessment';
            $name = "$unit – $sub";
        } elseif (stripos($orig,'Student_Handbook') !== false) {
            $unit = null; $section = 'Student Handbook & Policies'; $sub = 'Student Handbook'; $name = 'Student Handbook';
        } else {
            $unit = null; $section = 'Governance'; $sub = 'Document Register'; $name = str_replace(['_','.docx'],[' ',''],$orig);
        }
        $db->prepare("INSERT INTO compliance_docs (section,subcategory,unit_code,doc_name,version,status,approval_date,review_date,approved_by,owner,notes,file_path,original_name)
                      VALUES (?,?,?,?, '1.0','Active','2026-08-04','2027-08-04','Gloria Omoregie (CEO)','Esosa Omoregie (Manager)', ?, ?, ?)")
           ->execute([$section,$sub,$unit,$name,'Migrated from compliance documentation set.',$r['file_path'],$orig]);
        $id = (int)$db->lastInsertId();
        $db->prepare("INSERT INTO compliance_versions (doc_id,version,file_path,original_name,note,changed_by) VALUES (?,?,?,?,?,?)")
           ->execute([$id,'1.0',$r['file_path'],$orig,'Initial version',(current_user()['name'] ?? 'System')]);
        comp_audit($db,$id,'created','Imported into Compliance Module');
        $n++;
    }
    return $n;
}

// Seed the continuous improvement register with the known items if empty.
function comp_seed_ci(PDO $db): void {
    if ((int)$db->query("SELECT COUNT(*) FROM ci_register")->fetchColumn() > 0) return;
    $db->prepare("INSERT INTO ci_register (ref,date_raised,source,description,action_required,responsible,due_date,status,completed_date,linked_type)
                  VALUES ('CI-2026-001','2026-08-04','Internal Audit','Quiz assessment answer positions were weighted to one option (validity risk).','Shuffle correct-answer positions evenly across all knowledge quizzes.','Gloria Omoregie','2026-08-04','Completed','2026-08-04','Validation')")->execute();
}

function comp_seed_stage2(PDO $db): void {
    if ((int)$db->query("SELECT COUNT(*) FROM equipment")->fetchColumn() === 0) {
        $items = [
          ['Adult CPR Manikins','CPR Manikins'],['Child CPR Manikins','CPR Manikins'],['Infant CPR Manikins','CPR Manikins'],
          ['AED Training Devices','AED Trainers'],['Adrenaline Auto-Injector Trainers','Training Equipment'],
          ['Asthma Inhaler & Spacer Trainers','Training Equipment'],['First Aid Kits','First Aid Kits'],
        ];
        $ins=$db->prepare("INSERT INTO equipment (name,category,status,notes) VALUES (?,?, 'In Service','Update purchase, service and replacement dates.')");
        foreach ($items as $it) $ins->execute($it);
    }
    if ((int)$db->query("SELECT COUNT(*) FROM trainer_profiles")->fetchColumn() === 0) {
        $tp=$db->prepare("INSERT INTO trainer_profiles (name,email,position) VALUES (?,?,?)");
        $tp->execute(['Gloria Omoregie','admin@anbfirstaidtraining.com.au','CEO / Trainer & Assessor']); $g=(int)$db->lastInsertId();
        $tp->execute(['Esosa Omoregie','admin@anbfirstaidtraining.com.au','Manager / Trainer & Assessor (Registered Nurse)']); $e=(int)$db->lastInsertId();
        $q=$db->prepare("INSERT INTO trainer_quals (trainer_id,qual_type,title,code,notes) VALUES (?,?,?,?,?)");
        foreach ([$g,$e] as $tid) {
            $q->execute([$tid,'Qualification','Certificate IV in Training and Assessment','TAE40122','']);
            $q->execute([$tid,'Vocational Competency','Provide Cardiopulmonary Resuscitation','HLTAID009','']);
            $q->execute([$tid,'Vocational Competency','Provide First Aid','HLTAID011','']);
            $q->execute([$tid,'Vocational Competency','Provide First Aid in an Education and Care Setting','HLTAID012','']);
        }
        $q->execute([$e,'Industry Currency','Registered Nurse - current clinical practice','','']);
        $q->execute([$g,'Industry Currency','Current first aid trainer - ongoing PD','','']);
    }
}

function comp_dashboard(PDO $db): array {
    $today = '2026-08-04';
    $soon  = date('Y-m-d', strtotime($today.' +60 days'));
    $d = [];
    $d['total']    = (int)$db->query("SELECT COUNT(*) FROM compliance_docs WHERE status<>'Archived'")->fetchColumn();
    $d['active']   = (int)$db->query("SELECT COUNT(*) FROM compliance_docs WHERE status='Active'")->fetchColumn();
    $d['draft']    = (int)$db->query("SELECT COUNT(*) FROM compliance_docs WHERE status='Draft'")->fetchColumn();
    $d['archived'] = (int)$db->query("SELECT COUNT(*) FROM compliance_docs WHERE status='Archived'")->fetchColumn();
    $d['overdue']  = $db->query("SELECT * FROM compliance_docs WHERE status='Active' AND review_date IS NOT NULL AND review_date<'$today' ORDER BY review_date")->fetchAll(PDO::FETCH_ASSOC);
    $d['due_soon'] = $db->query("SELECT * FROM compliance_docs WHERE status='Active' AND review_date>='$today' AND review_date<='$soon' ORDER BY review_date")->fetchAll(PDO::FETCH_ASSOC);
    $d['drafts']   = $db->query("SELECT * FROM compliance_docs WHERE status='Draft' ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $d['ci_open']  = $db->query("SELECT * FROM ci_register WHERE status<>'Completed' ORDER BY due_date")->fetchAll(PDO::FETCH_ASSOC);
    $d['certs_issued'] = (int)$db->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
    // equipment maintenance due (overdue or within 30 days)
    $soon30 = date('Y-m-d', strtotime($today.' +30 days'));
    $d['equip_due'] = $db->query("SELECT * FROM equipment WHERE status<>'Retired' AND next_service_date IS NOT NULL AND next_service_date<='$soon30' ORDER BY next_service_date")->fetchAll(PDO::FETCH_ASSOC);
    // trainer qualifications expiring (expired or within 60 days)
    $d['qual_exp'] = $db->query("SELECT tq.*, tp.name trainer_name FROM trainer_quals tq JOIN trainer_profiles tp ON tp.id=tq.trainer_id
        WHERE tq.expiry_date IS NOT NULL AND tq.expiry_date<='$soon' ORDER BY tq.expiry_date")->fetchAll(PDO::FETCH_ASSOC);
    // trainer insurance expiring (within 90 days or expired)
    $soon90 = date('Y-m-d', strtotime($today.' +90 days'));
    $d['ins_exp'] = $db->query("SELECT name, insurance_provider, insurance_expiry FROM trainer_profiles
        WHERE insurance_expiry IS NOT NULL AND insurance_expiry<>'' AND insurance_expiry<='$soon90' ORDER BY insurance_expiry")->fetchAll(PDO::FETCH_ASSOC);
    return $d;
}

function comp_status_badge(string $s): string {
    $map = ['Active'=>'success','Draft'=>'warning','Archived'=>'secondary'];
    $c = $map[$s] ?? 'light';
    return '<span class="badge text-bg-'.$c.'">'.htmlspecialchars($s).'</span>';
}
