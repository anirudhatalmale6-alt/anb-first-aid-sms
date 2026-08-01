<?php
/**
 * A&B First Aid Training - SMS core (demo build)
 * Portable PDO layer. Uses SQLite for the demo; the same code runs on MySQL
 * for production (swap the DSN). Schema mirrors database/schema.sql.
 */
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $file = __DIR__ . '/../data/sms.sqlite';
    if (!is_dir(dirname($file))) mkdir(dirname($file), 0775, true);
    $fresh = !file_exists($file);
    $pdo = new PDO('sqlite:' . $file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    if ($fresh) { db_migrate($pdo); db_seed($pdo); }
    return $pdo;
}

function db_migrate(PDO $p): void {
    $p->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'office', is_trainer INTEGER DEFAULT 0,
        signature_path TEXT, active INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now'))
    );
    CREATE TABLE locations (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, identifier TEXT,
        suburb TEXT, state TEXT, postcode TEXT, active INTEGER DEFAULT 1
    );
    CREATE TABLE units (
        id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, title TEXT NOT NULL,
        nominal_hours INTEGER, active INTEGER DEFAULT 1
    );
    CREATE TABLE courses (
        id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL, title TEXT NOT NULL,
        category TEXT, validity_months INTEGER, status TEXT DEFAULT 'current'
    );
    CREATE TABLE plans (
        id INTEGER PRIMARY KEY AUTOINCREMENT, course_id INTEGER NOT NULL, title TEXT NOT NULL,
        delivery_mode TEXT, price REAL DEFAULT 0, active INTEGER DEFAULT 1,
        FOREIGN KEY(course_id) REFERENCES courses(id)
    );
    CREATE TABLE schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT, plan_id INTEGER NOT NULL, location_id INTEGER,
        name TEXT, start_date TEXT NOT NULL, start_time TEXT, end_date TEXT, end_time TEXT,
        total_places INTEGER DEFAULT 15, trainer_id INTEGER,
        FOREIGN KEY(plan_id) REFERENCES plans(id), FOREIGN KEY(location_id) REFERENCES locations(id)
    );
    CREATE TABLE students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        salutation TEXT, first_name TEXT NOT NULL, middle_name TEXT, last_name TEXT NOT NULL,
        date_of_birth TEXT, gender TEXT,
        usi_number TEXT, usi_verified INTEGER DEFAULT 0,
        email TEXT, mobile_phone TEXT,
        unit_flat TEXT, street_number TEXT, street_name TEXT, suburb TEXT, state TEXT, postcode TEXT,
        town_of_birth TEXT, country_of_birth TEXT,
        highest_school_level TEXT, indigenous_status TEXT, labour_force_status TEXT,
        main_language TEXT, study_reason TEXT, disability_flag TEXT,
        created_at TEXT DEFAULT (datetime('now'))
    );
    CREATE TABLE enrolments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL, course_id INTEGER NOT NULL, plan_id INTEGER NOT NULL,
        schedule_id INTEGER, location_id INTEGER, start_date TEXT, end_date TEXT,
        status TEXT NOT NULL DEFAULT 'enrolled',
        amount_due REAL DEFAULT 0, amount_paid REAL DEFAULT 0, payment_status TEXT DEFAULT 'unpaid',
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY(student_id) REFERENCES students(id), FOREIGN KEY(course_id) REFERENCES courses(id),
        FOREIGN KEY(plan_id) REFERENCES plans(id), FOREIGN KEY(schedule_id) REFERENCES schedules(id)
    );
    CREATE TABLE enrolment_units (
        id INTEGER PRIMARY KEY AUTOINCREMENT, enrolment_id INTEGER NOT NULL, unit_id INTEGER NOT NULL,
        outcome_national TEXT DEFAULT '70', date_achieved TEXT,
        FOREIGN KEY(enrolment_id) REFERENCES enrolments(id), FOREIGN KEY(unit_id) REFERENCES units(id)
    );
    CREATE TABLE certificates (
        id INTEGER PRIMARY KEY AUTOINCREMENT, enrolment_id INTEGER NOT NULL, student_id INTEGER NOT NULL,
        type TEXT DEFAULT 'statement_of_attainment', certificate_number TEXT NOT NULL UNIQUE,
        issue_date TEXT NOT NULL, expiry_date TEXT, file_path TEXT, emailed_at TEXT,
        reminder_6wk_sent TEXT, reminder_2wk_sent TEXT,
        FOREIGN KEY(enrolment_id) REFERENCES enrolments(id), FOREIGN KEY(student_id) REFERENCES students(id)
    );
    ");
}

function db_seed(PDO $p): void {
    // Admin login (authorised signatory)
    $p->prepare("INSERT INTO users (name,email,password,role,is_trainer) VALUES (?,?,?,?,1)")
      ->execute(['Gloria Omoregie','admin@anbfirstaidtraining.com.au', password_hash('demo1234', PASSWORD_DEFAULT),'admin']);

    // Locations
    $locs = [['St. Marys','NSW','2760'],['Blacktown (Max Webber Function Centre)','NSW','2148'],
             ['Seven Hills','NSW','2147'],['Wetherill Park','NSW','2164']];
    $ls = $p->prepare("INSERT INTO locations (name,state,postcode) VALUES (?,?,?)");
    foreach ($locs as $l) $ls->execute($l);

    // Units
    $units = [['HLTAID009','Provide cardiopulmonary resuscitation',12],
              ['HLTAID011','Provide first aid',18],
              ['HLTAID012','Provide first aid in an education and care setting',22],
              ['HLTAID010','Provide basic emergency life support',12]];
    $us = $p->prepare("INSERT INTO units (code,title,nominal_hours) VALUES (?,?,?)");
    foreach ($units as $u) $us->execute($u);

    // Courses (validity months drive cert expiry)
    $courses = [['HLTAID009','Provide cardiopulmonary resuscitation','First Aid',12],
                ['HLTAID011','Provide first aid','First Aid',36],
                ['HLTAID012','Provide first aid in an education and care setting','First Aid',36],
                ['HLTAID010','Provide basic emergency life support','First Aid',12]];
    $cs = $p->prepare("INSERT INTO courses (code,title,category,validity_months) VALUES (?,?,?,?)");
    foreach ($courses as $c) $cs->execute($c);

    // Plans (Express/Regular) with real prices
    $plans = [
        [1,'HLTAID009 CPR - Express (Online + 45 Mins Face-to-Face)','online_f2f',45.00],
        [1,'HLTAID009 CPR - Regular (Online + 45 Mins Face-to-Face)','online_f2f',45.00],
        [2,'HLTAID011 First Aid + CPR - Express (Online + 1-2hrs Face-to-Face)','online_f2f',89.00],
        [2,'HLTAID011 First Aid + CPR - Regular (Online + 2.5hrs Face-to-Face)','online_f2f',89.00],
        [3,'HLTAID012 Child Care First Aid - Regular (Online + 3hrs Face-to-Face)','online_f2f',99.00],
    ];
    $ps = $p->prepare("INSERT INTO plans (course_id,title,delivery_mode,price) VALUES (?,?,?,?)");
    foreach ($plans as $pl) $ps->execute($pl);

    // Schedules (a few upcoming)
    $today = new DateTime('2026-08-01');
    $ss = $p->prepare("INSERT INTO schedules (plan_id,location_id,name,start_date,start_time,end_date,end_time,total_places) VALUES (?,?,?,?,?,?,?,?)");
    $schedRows = [
        [1,1,'CPR Express - St. Marys','2026-08-01','09:30','2026-08-01','10:15',15],
        [3,2,'First Aid - Blacktown','2026-08-08','09:00','2026-08-08','13:00',15],
        [5,1,'Child Care First Aid - St. Marys','2026-08-15','09:00','2026-08-15','15:00',12],
        [2,3,'CPR Regular - Seven Hills','2026-08-22','09:30','2026-08-22','10:15',15],
    ];
    foreach ($schedRows as $r) $ss->execute($r);

    // Students (realistic AVETMISS-ish sample)
    $students = [
        ['Ms','Feroza','','Haidari','1992-03-14','F','3000000001',1,'feroza.h@example.com','0400111222','12','Mary St','St. Marys','NSW','2760'],
        ['Mr','Howard','','Lucasan','1988-07-02','M','3000000002',1,'howard.l@example.com','0400333444','5','King St','Blacktown','NSW','2148'],
        ['Ms','Amna','','Abdelrasoul','1995-11-20','F','3000000003',1,'amna.a@example.com','0400555666','44','Queen St','Seven Hills','NSW','2147'],
        ['Mr','Daniel','James','Okafor','1990-01-09','M','3000000004',1,'daniel.o@example.com','0400777888','9','Park Rd','St. Marys','NSW','2760'],
        ['Ms','Priya','','Sharma','1998-05-27','F',null,0,'priya.s@example.com','0400999000','2','Elm St','Wetherill Park','NSW','2164'],
        ['Mr','Liam','','Nguyen','1985-09-16','M','3000000006',1,'liam.n@example.com','0401222333','18','Oak Ave','Blacktown','NSW','2148'],
    ];
    $st = $p->prepare("INSERT INTO students (salutation,first_name,middle_name,last_name,date_of_birth,gender,usi_number,usi_verified,email,mobile_phone,street_number,street_name,suburb,state,postcode,country_of_birth,highest_school_level) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'1101','12')");
    foreach ($students as $s) $st->execute($s);

    // Enrolments + units + certificates with a spread of statuses/expiries
    $en = $p->prepare("INSERT INTO enrolments (student_id,course_id,plan_id,schedule_id,location_id,start_date,end_date,status,amount_due,amount_paid,payment_status) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $eu = $p->prepare("INSERT INTO enrolment_units (enrolment_id,unit_id,outcome_national,date_achieved) VALUES (?,?,?,?)");
    $ce = $p->prepare("INSERT INTO certificates (enrolment_id,student_id,type,certificate_number,issue_date,expiry_date,emailed_at) VALUES (?,?,?,?,?,?,?)");

    // helper to add cert with expiry = issue + months
    $addCert = function($enrId,$stuId,$num,$issue,$months) use ($ce) {
        $exp = (new DateTime($issue))->modify("+{$months} months")->format('Y-m-d');
        $ce->execute([$enrId,$stuId,'statement_of_attainment',$num,$issue,$exp,$issue.' 10:05:00']);
    };

    // 1) Feroza - CPR completed & issued, expiring soon (issued ~11 months ago -> expiry in ~1 month) => reminder due
    $en->execute([1,1,1,1,1,'2025-09-05','2025-09-05','issued',45,45,'paid']);
    $eu->execute([1,1,'20','2025-09-05']);
    $addCert(1,1,'SA46055-22001','2025-09-05',12);

    // 2) Howard - Child Care completed & issued (3yr validity, plenty of time)
    $en->execute([2,3,5,3,1,'2026-07-01','2026-07-01','issued',99,99,'paid']);
    $eu->execute([2,3,'20','2026-07-01']);
    $addCert(2,2,'SA46055-22002','2026-07-01',36);

    // 3) Amna - CPR complete, not yet issued
    $en->execute([3,1,2,4,3,'2026-08-01','2026-08-01','complete',45,45,'paid']);
    $eu->execute([3,1,'20','2026-08-01']);

    // 4) Daniel - First Aid enrolled, online pending (not complete)
    $en->execute([4,2,3,2,2,'2026-08-08','2026-08-08','enrolled',89,89,'paid']);
    $eu->execute([4,2,'70',null]);

    // 5) Priya - CPR enrolled, no USI yet, unpaid
    $en->execute([5,1,1,1,1,'2026-08-01','2026-08-01','enrolled',45,0,'unpaid']);
    $eu->execute([5,1,'70',null]);

    // 6) Liam - CPR issued last year, EXPIRED last month (overdue renewal)
    $en->execute([6,1,1,null,2,'2025-06-20','2025-06-20','issued',45,45,'paid']);
    $eu->execute([6,1,'20','2025-06-20']);
    $addCert(6,6,'SA46055-21988','2025-06-20',12);
}
