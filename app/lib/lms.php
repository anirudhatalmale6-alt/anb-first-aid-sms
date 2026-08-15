<?php
/**
 * A&B First Aid Training - SMS
 * LMS module: SCORM package upload/playback + tracking, and a built-in quiz builder.
 *
 * Online modules are attached to a COURSE. A learner sees the modules for the
 * course they are enrolled in, works through them, and their completion rolls
 * up into enrolments.online_complete (which the certificate pipeline checks).
 */
declare(strict_types=1);

/** Where extracted SCORM content lives so the web server can serve it statically. */
function lms_scorm_public_dir(): string { return __DIR__ . '/../public/scorm-content'; }
/** Public URL prefix for served SCORM content. */
function lms_scorm_url_prefix(): string { return 'scorm-content'; }

/** Create LMS tables if they don't exist yet (idempotent - safe on an existing DB). */
function lms_ensure_schema(PDO $p): void {
    $p->exec("
    CREATE TABLE IF NOT EXISTS course_modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        type TEXT NOT NULL DEFAULT 'scorm',   -- scorm | quiz
        scorm_dir TEXT,                        -- folder under public/scorm-content
        launch_url TEXT,                       -- entry file relative to scorm_dir
        pass_mark INTEGER DEFAULT 80,          -- quiz pass %
        position INTEGER DEFAULT 0,
        active INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS quiz_questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_id INTEGER NOT NULL,
        question TEXT NOT NULL,
        qtype TEXT NOT NULL DEFAULT 'single',  -- single | multi | truefalse
        options TEXT,                          -- JSON array of option strings
        correct TEXT,                          -- JSON array of correct option indexes
        position INTEGER DEFAULT 0,
        FOREIGN KEY(module_id) REFERENCES course_modules(id)
    );
    CREATE TABLE IF NOT EXISTS learner_progress (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        enrolment_id INTEGER NOT NULL,
        module_id INTEGER NOT NULL,
        status TEXT DEFAULT 'not_started',     -- not_started | in_progress | completed
        score REAL,
        attempts INTEGER DEFAULT 0,
        updated_at TEXT,
        UNIQUE(enrolment_id, module_id)
    );
    CREATE TABLE IF NOT EXISTS form_submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        enrolment_id INTEGER NOT NULL,
        module_id INTEGER NOT NULL,
        data TEXT,                             -- JSON of field => value
        updated_at TEXT,
        UNIQUE(enrolment_id, module_id)
    );
    ");
    // learner_progress.wrong_qids: JSON list of question ids still answered wrong
    // (so a re-attempt only re-tests what the learner got wrong, not the whole quiz).
    try { $p->exec("ALTER TABLE learner_progress ADD COLUMN wrong_qids TEXT"); } catch (Throwable $e) {}
    // course_modules.body holds scenario/instructions for incident_report + practical modules
    try { $p->exec("ALTER TABLE course_modules ADD COLUMN body TEXT"); } catch (Throwable $e) {}
    try { $p->exec("ALTER TABLE course_modules ADD COLUMN skills TEXT"); } catch (Throwable $e) {}   // JSON array of practical skills
    try { $p->exec("ALTER TABLE course_modules ADD COLUMN ack_text TEXT"); } catch (Throwable $e) {} // learner acknowledgement statement
    $p->exec("
    CREATE TABLE IF NOT EXISTS observations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        enrolment_id INTEGER NOT NULL,
        module_id INTEGER NOT NULL,
        results TEXT,                          -- JSON { skillIndex: 'S' | 'NYS' }
        overall TEXT,                          -- 'satisfactory' | 'not_yet'
        assessor TEXT,
        comments TEXT,
        updated_at TEXT,
        UNIQUE(enrolment_id, module_id)
    );
    ");
}

/** Students enrolled in a course (for trainer observation lists). */
function lms_course_learners(PDO $p, int $courseId): array {
    $st = $p->prepare("
        SELECT e.id enrolment_id, s.first_name, s.last_name, s.email
        FROM enrolments e JOIN students s ON s.id=e.student_id
        WHERE e.course_id=? ORDER BY s.last_name, s.first_name");
    $st->execute([$courseId]);
    return $st->fetchAll();
}

/** One learner's observation record (with decoded results) or null. */
function lms_observation(PDO $p, int $enrolmentId, int $moduleId): ?array {
    $st = $p->prepare("SELECT * FROM observations WHERE enrolment_id=? AND module_id=?");
    $st->execute([$enrolmentId, $moduleId]);
    $row = $st->fetch();
    if (!$row) return null;
    $row['results_arr'] = (array)json_decode($row['results'] ?? '{}', true);
    return $row;
}

/** Save/replace a trainer observation. */
function lms_save_observation(PDO $p, int $enrolmentId, int $moduleId, array $results, string $overall, string $assessor, string $comments): void {
    $json = json_encode($results); $now = date('Y-m-d H:i:s');
    $ex = $p->prepare("SELECT id FROM observations WHERE enrolment_id=? AND module_id=?");
    $ex->execute([$enrolmentId, $moduleId]);
    if ($id = $ex->fetchColumn()) {
        $p->prepare("UPDATE observations SET results=?,overall=?,assessor=?,comments=?,updated_at=? WHERE id=?")
          ->execute([$json,$overall,$assessor,$comments,$now,(int)$id]);
    } else {
        $p->prepare("INSERT INTO observations (enrolment_id,module_id,results,overall,assessor,comments,updated_at) VALUES (?,?,?,?,?,?,?)")
          ->execute([$enrolmentId,$moduleId,$json,$overall,$assessor,$comments,$now]);
    }
}

/** Get a learner's saved form submission (assoc) or null. */
function lms_form_submission(PDO $p, int $enrolmentId, int $moduleId): ?array {
    $st = $p->prepare("SELECT * FROM form_submissions WHERE enrolment_id=? AND module_id=?");
    $st->execute([$enrolmentId, $moduleId]);
    $row = $st->fetch();
    if (!$row) return null;
    $row['fields'] = (array)json_decode($row['data'] ?? '{}', true);
    return $row;
}

/** Save/replace a learner's form submission. */
function lms_save_form_submission(PDO $p, int $enrolmentId, int $moduleId, array $data): void {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $now = date('Y-m-d H:i:s');
    $ex = $p->prepare("SELECT id FROM form_submissions WHERE enrolment_id=? AND module_id=?");
    $ex->execute([$enrolmentId, $moduleId]);
    if ($id = $ex->fetchColumn()) {
        $p->prepare("UPDATE form_submissions SET data=?, updated_at=? WHERE id=?")->execute([$json,$now,(int)$id]);
    } else {
        $p->prepare("INSERT INTO form_submissions (enrolment_id,module_id,data,updated_at) VALUES (?,?,?,?)")
          ->execute([$enrolmentId,$moduleId,$json,$now]);
    }
}

/** All submissions for a module, with student names (staff view). */
function lms_module_submissions(PDO $p, int $moduleId): array {
    $st = $p->prepare("
        SELECT fs.*, s.first_name, s.last_name, s.email
        FROM form_submissions fs
        JOIN enrolments e ON e.id=fs.enrolment_id
        JOIN students s ON s.id=e.student_id
        WHERE fs.module_id=? ORDER BY fs.updated_at DESC");
    $st->execute([$moduleId]);
    return $st->fetchAll();
}

/** Seed a demo SCORM package + a demo quiz once, so the LMS is testable out of the box. */
function lms_seed_demo(PDO $p): void {
    $have = (int)$p->query("SELECT COUNT(*) FROM course_modules")->fetchColumn();
    if ($have > 0) return;

    // --- Demo SCORM package (extracted to public/scorm-content/demo-cpr) ---
    $dir = lms_scorm_public_dir() . '/demo-cpr';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    file_put_contents($dir . '/imsmanifest.xml', lms_demo_manifest());
    file_put_contents($dir . '/index.html', lms_demo_sco_html());

    // course 1 = HLTAID009 CPR, course 2 = HLTAID011 First Aid
    $ins = $p->prepare("INSERT INTO course_modules (course_id,title,type,scorm_dir,launch_url,pass_mark,position) VALUES (?,?,?,?,?,?,?)");
    $ins->execute([1,'CPR Online Module (SCORM)','scorm','demo-cpr','index.html',null,1]);
    $scormModuleId = (int)$p->lastInsertId();

    // Demo quiz on the same CPR course
    $ins->execute([1,'CPR Knowledge Check (Quiz)','quiz',null,null,80,2]);
    $quizId = (int)$p->lastInsertId();

    $q = $p->prepare("INSERT INTO quiz_questions (module_id,question,qtype,options,correct,position) VALUES (?,?,?,?,?,?)");
    $q->execute([$quizId,'What is the correct compression rate for adult CPR?','single',
        json_encode(['60-80 per minute','100-120 per minute','140-160 per minute','As fast as possible']),
        json_encode([1]),1]);
    $q->execute([$quizId,'What is the correct compression-to-breath ratio for a single adult rescuer?','single',
        json_encode(['15:2','30:2','5:1','30:1']),
        json_encode([1]),2]);
    $q->execute([$quizId,'DRSABCD stands for Danger, Response, Send for help, Airway, Breathing, CPR, Defibrillation.','truefalse',
        json_encode(['True','False']),
        json_encode([0]),3]);
    $q->execute([$quizId,'Which of the following are signs you should start CPR? (select all that apply)','multi',
        json_encode(['Person is unresponsive','Person is not breathing normally','Person is talking to you','No signs of life']),
        json_encode([0,1,3]),4]);

    // Attach an in-progress demo record so tracking is visible: Amna (enrolment 3, course 1)
    $lp = $p->prepare("INSERT OR IGNORE INTO learner_progress (enrolment_id,module_id,status,score,attempts,updated_at) VALUES (?,?,?,?,?,datetime('now'))");
    $lp->execute([3,$scormModuleId,'completed',100,1]);
}

/** The first module of a course (lowest position) - the compulsory LLN step. */
function lms_first_module(PDO $p, int $courseId): ?array {
    $st = $p->prepare("SELECT * FROM course_modules WHERE course_id=? AND active=1 ORDER BY position, id LIMIT 1");
    $st->execute([$courseId]);
    return $st->fetch() ?: null;
}

/** True if a given module is completed for an enrolment. */
function lms_module_completed(PDO $p, int $enrolmentId, int $moduleId): bool {
    $st = $p->prepare("SELECT status FROM learner_progress WHERE enrolment_id=? AND module_id=?");
    $st->execute([$enrolmentId, $moduleId]);
    return $st->fetchColumn() === 'completed';
}

/** Human labels for the common AVETMISS demographic codes (falls back to the raw code). */
function anb_demo_label(string $field, ?string $code): string {
    $code = trim((string)$code);
    if ($code === '' ) return '—';
    $maps = [
        'school' => ['12'=>'Completed Year 12','11'=>'Completed Year 11','10'=>'Completed Year 10','09'=>'Completed Year 9',
                     '08'=>'Completed Year 8 or below','02'=>'Did not attend school','@@'=>'Not stated'],
        'indig'  => ['1'=>'Aboriginal','2'=>'Torres Strait Islander','3'=>'Both Aboriginal & Torres Strait Islander',
                     '4'=>'Neither','@'=>'Not stated'],
        'labour' => ['01'=>'Full-time employee','02'=>'Part-time employee','03'=>'Self-employed (not employing)',
                     '04'=>'Employer','05'=>'Employed - unpaid family worker','06'=>'Unemployed - seeking full-time work',
                     '07'=>'Unemployed - seeking part-time work','08'=>'Not employed, not seeking work','@@'=>'Not stated'],
        'lang'   => ['1201'=>'English','5203'=>'Tagalog / Filipino','5207'=>'Tagalog','4202'=>'Arabic','5206'=>'Bisaya',
                     '5212'=>'Cebuano','6512'=>'Nepali','6511'=>'Hindi','7101'=>'Tongan','9799'=>'Another language'],
        'country'=> ['1101'=>'Australia','7103'=>'India','5204'=>'Philippines','9124'=>'Other','7105'=>'Nepal',
                     '7106'=>'Sri Lanka','1502'=>'Fiji','1201'=>'New Zealand'],
        'disab'  => ['Y'=>'Yes','N'=>'No'],
    ];
    return $maps[$field][$code] ?? $code;
}

/** Modules for a course, ordered. */
function lms_course_modules(PDO $p, int $courseId): array {
    $st = $p->prepare("SELECT * FROM course_modules WHERE course_id=? AND active=1 ORDER BY position, id");
    $st->execute([$courseId]);
    return $st->fetchAll();
}

/** All modules with course info + question counts (admin listing). */
function lms_all_modules(PDO $p): array {
    return $p->query("
        SELECT m.*, co.code course_code, co.title course_title,
          (SELECT COUNT(*) FROM quiz_questions q WHERE q.module_id=m.id) question_count
        FROM course_modules m JOIN courses co ON co.id=m.course_id
        WHERE m.active=1
        ORDER BY co.code, m.position, m.id")->fetchAll();
}

function lms_module(PDO $p, int $id): ?array {
    $st = $p->prepare("SELECT m.*, co.code course_code, co.title course_title FROM course_modules m JOIN courses co ON co.id=m.course_id WHERE m.id=?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function lms_questions(PDO $p, int $moduleId): array {
    $st = $p->prepare("SELECT * FROM quiz_questions WHERE module_id=? ORDER BY position, id");
    $st->execute([$moduleId]);
    return $st->fetchAll();
}

/** Progress for one enrolment keyed by module_id. */
function lms_progress_for_enrolment(PDO $p, int $enrolmentId): array {
    $st = $p->prepare("SELECT * FROM learner_progress WHERE enrolment_id=?");
    $st->execute([$enrolmentId]);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[(int)$r['module_id']] = $r;
    return $out;
}

/** Record/merge progress for a module and recompute the enrolment's online_complete flag. */
function lms_record_progress(PDO $p, int $enrolmentId, int $moduleId, string $status, ?float $score): void {
    $st = $p->prepare("SELECT * FROM learner_progress WHERE enrolment_id=? AND module_id=?");
    $st->execute([$enrolmentId, $moduleId]);
    $cur = $st->fetch();
    if ($cur) {
        $p->prepare("UPDATE learner_progress SET status=?, score=COALESCE(?,score), attempts=attempts+1, updated_at=datetime('now') WHERE id=?")
          ->execute([$status, $score, $cur['id']]);
    } else {
        $p->prepare("INSERT INTO learner_progress (enrolment_id,module_id,status,score,attempts,updated_at) VALUES (?,?,?,?,1,datetime('now'))")
          ->execute([$enrolmentId, $moduleId, $status, $score]);
    }
    lms_recompute_online_complete($p, $enrolmentId);
}

/** If every active module for the enrolment's course is completed, mark online_complete=1. */
function lms_recompute_online_complete(PDO $p, int $enrolmentId): void {
    $st = $p->prepare("SELECT course_id FROM enrolments WHERE id=?");
    $st->execute([$enrolmentId]);
    $courseId = (int)$st->fetchColumn();
    if (!$courseId) return;
    // An office tick wins. Somebody signed their name against "this student did
    // the theory in the room"; recomputing it back to 0 the next time they open
    // a module would undo that decision silently, and nobody would know why the
    // certificate stopped being available.
    $cols = $p->query("PRAGMA table_info(enrolments)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (in_array('online_marked_by', $cols, true)) {
        $m = $p->prepare("SELECT online_marked_by FROM enrolments WHERE id=?");
        $m->execute([$enrolmentId]);
        if (trim((string)$m->fetchColumn()) !== '') return;
    }
    $tc = $p->prepare("SELECT COUNT(*) FROM course_modules WHERE course_id=? AND active=1");
    $tc->execute([$courseId]); $total = (int)$tc->fetchColumn();
    if ($total === 0) return;
    $dc = $p->prepare("SELECT COUNT(*) FROM learner_progress lp JOIN course_modules m ON m.id=lp.module_id
                       WHERE lp.enrolment_id=? AND m.course_id=? AND m.active=1 AND lp.status='completed'");
    $dc->execute([$enrolmentId, $courseId]);
    $done = (int)$dc->fetchColumn();
    $flag = ($done >= $total) ? 1 : 0;
    $p->prepare("UPDATE enrolments SET online_complete=? WHERE id=?")->execute([$flag, $enrolmentId]);
}

/** Score a submitted quiz: returns [percent, correctCount, total, perQuestion]. */
function lms_grade_quiz(array $questions, array $submitted): array {
    $total = count($questions); $correct = 0; $per = [];
    foreach ($questions as $q) {
        $key = (array)json_decode($q['correct'] ?? '[]', true);
        sort($key);
        $ans = $submitted[$q['id']] ?? [];
        if (!is_array($ans)) $ans = [$ans];
        $ans = array_map('intval', $ans); sort($ans);
        $ok = ($ans === array_map('intval',$key));
        if ($ok) $correct++;
        $per[$q['id']] = $ok;
    }
    $pct = $total ? round($correct / $total * 100) : 0;
    return [$pct, $correct, $total, $per];
}

/* ---------------- SCORM package handling ---------------- */

/**
 * Accept an uploaded SCORM .zip, extract it under public/scorm-content/mod-<n>,
 * detect the launch file from imsmanifest.xml, and return [dir, launch_url] or throw.
 */
function lms_import_scorm_zip(string $tmpFile, string $slug): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('ZipArchive not available on this server.');
    $base = lms_scorm_public_dir();
    if (!is_dir($base)) mkdir($base, 0775, true);
    // unique folder
    $dir = $base . '/' . $slug;
    $n = 1; while (is_dir($dir)) { $dir = $base . '/' . $slug . '-' . (++$n); }
    mkdir($dir, 0775, true);

    $zip = new ZipArchive();
    if ($zip->open($tmpFile) !== true) throw new RuntimeException('Could not open the uploaded ZIP.');
    // guard against path traversal
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (strpos($name, '..') !== false || strpos($name, ':') !== false || str_starts_with($name, '/')) {
            $zip->close(); throw new RuntimeException('ZIP contains an unsafe path.');
        }
    }
    $zip->extractTo($dir);
    $zip->close();

    // some packages nest everything in a single top folder - detect manifest location
    $manifest = lms_find_file($dir, 'imsmanifest.xml');
    $root = $manifest ? dirname($manifest) : $dir;
    $launch = lms_scorm_launch_from_manifest($manifest, $root);
    if (!$launch) {
        // fallback: common entry file names
        foreach (['index_lms.html','index.html','story.html','launch.html','index.htm'] as $cand) {
            $f = lms_find_file($root, $cand);
            if ($f) { $launch = ltrim(str_replace($root, '', $f), '/'); break; }
        }
    }
    if (!$launch) throw new RuntimeException('Could not find a launch page (imsmanifest.xml / index.html) in the package.');

    // scorm_dir stored relative to public/scorm-content
    $relDir = ltrim(str_replace($base, '', $root), '/');
    return [$relDir, $launch];
}

/** Recursively find a file by name; returns absolute path or null. */
function lms_find_file(string $dir, string $name): ?string {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $f) {
        if (strcasecmp($f->getFilename(), $name) === 0) return $f->getPathname();
    }
    return null;
}

/** Parse imsmanifest.xml to find the default SCO launch href. */
function lms_scorm_launch_from_manifest(?string $manifest, string $root): ?string {
    if (!$manifest || !is_file($manifest)) return null;
    $xml = @simplexml_load_file($manifest);
    if (!$xml) return null;
    // resource identifiers -> href
    $resources = [];
    if (isset($xml->resources->resource)) {
        foreach ($xml->resources->resource as $res) {
            $id = (string)$res['identifier'];
            $href = (string)$res['href'];
            if ($href !== '') $resources[$id] = $href;
        }
    }
    // default organization -> first item's referenced resource
    if (isset($xml->organizations->organization)) {
        foreach ($xml->organizations->organization as $org) {
            foreach ($org->item as $item) {
                $ref = (string)$item['identifierref'];
                if ($ref && isset($resources[$ref])) return $resources[$ref];
            }
        }
    }
    // else first resource with an href
    foreach ($resources as $href) return $href;
    return null;
}

/* ---------------- demo package contents ---------------- */

function lms_demo_manifest(): string {
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="ANB_CPR_DEMO" version="1.0"
  xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
  xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">
  <metadata><schema>ADL SCORM</schema><schemaversion>1.2</schemaversion></metadata>
  <organizations default="ORG">
    <organization identifier="ORG">
      <title>CPR Online Module</title>
      <item identifier="ITEM1" identifierref="RES1"><title>CPR Online Module</title></item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="RES1" type="webcontent" adlcp:scormtype="sco" href="index.html">
      <file href="index.html"/>
    </resource>
  </resources>
</manifest>
XML;
}

function lms_demo_sco_html(): string {
    return <<<'HTML'
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>CPR Online Module</title>
<style>
 body{font-family:system-ui,Arial,sans-serif;margin:0;background:#faf7fb;color:#2F1D3A}
 .wrap{max-width:640px;margin:0 auto;padding:24px}
 .card{background:#fff;border-radius:14px;box-shadow:0 4px 14px rgba(0,0,0,.06);padding:24px;margin-bottom:16px}
 h1{font-size:1.35rem} h2{font-size:1.05rem;color:#E53935}
 .btn{background:#E53935;color:#fff;border:0;border-radius:8px;padding:10px 18px;font-size:1rem;cursor:pointer}
 .btn:disabled{opacity:.5;cursor:default}
 .ok{color:#2e7d32;font-weight:600}
 .bar{height:8px;background:#eee;border-radius:6px;overflow:hidden;margin:10px 0}
 .bar>div{height:100%;background:#2e7d32;width:0;transition:width .4s}
</style></head>
<body>
<script>
// Locate the SCORM 1.2 API provided by the LMS wrapper (parent window chain).
function findAPI(w){var n=0;while(w&&!w.API&&w.parent&&w.parent!=w&&n++<10)w=w.parent;return w?w.API:null;}
var API=findAPI(window)||(window.opener?findAPI(window.opener):null);
function set(k,v){if(API)try{API.LMSSetValue(k,v);}catch(e){}}
if(API){try{API.LMSInitialize("");}catch(e){}set("cmi.core.lesson_status","incomplete");try{API.LMSCommit("");}catch(e){}}
var page=0,pages=3;
function render(){
  document.getElementById('bar').style.width=Math.round(page/pages*100)+'%';
}
function next(){
  page++;
  document.getElementById('p'+page).style.display='block';
  if(page>1)document.getElementById('p'+(page-1)).style.display='none';
  render();
  if(page>=pages){document.getElementById('finish').style.display='inline-block';}
}
function finish(){
  set("cmi.core.lesson_status","completed");
  set("cmi.core.score.raw","100");
  if(API){try{API.LMSCommit("");}catch(e){}try{API.LMSFinish("");}catch(e){}}
  document.getElementById('done').style.display='block';
  document.getElementById('finish').disabled=true;
}
window.onload=render;
</script>
<div class="wrap">
 <div class="card"><h1>CPR Online Module</h1>
  <div class="bar"><div id="bar"></div></div>
  <div id="p0">
    <h2>1. Danger &amp; Response</h2>
    <p>Before approaching, check for danger to yourself, bystanders and the casualty. Then check for a response — talk and touch (squeeze the shoulders).</p>
    <button class="btn" onclick="next()">Next</button>
  </div>
  <div id="p1" style="display:none">
    <h2>2. Airway &amp; Breathing</h2>
    <p>Open the airway with a head-tilt/chin-lift. Look, listen and feel for normal breathing for up to 10 seconds.</p>
    <button class="btn" onclick="next()">Next</button>
  </div>
  <div id="p2" style="display:none">
    <h2>3. Compressions</h2>
    <p>If not breathing normally, start CPR: 30 compressions to 2 breaths, at a rate of 100–120 per minute, pressing one-third of chest depth.</p>
    <button class="btn" onclick="next()">Next</button>
  </div>
  <div id="p3" style="display:none">
    <h2>Well done!</h2>
    <p>You've completed the CPR online module. Click Finish to record your completion.</p>
  </div>
  <button id="finish" class="btn" style="display:none" onclick="finish()">Finish &amp; record completion</button>
  <p id="done" class="ok" style="display:none">✓ Completion recorded. You can close this window.</p>
 </div>
</div>
</body></html>
HTML;
}
