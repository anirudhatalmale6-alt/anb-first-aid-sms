-- ============================================================================
-- A&B First Aid Training - Student Management System (SMS)
-- Custom RTO platform (replaces RTO Data Cloud). RTO 46055.
-- Schema: MySQL 8 / MariaDB 10.4+  (utf8mb4)
-- Designed AVETMISS 8.0 (NCVER) ready so NAT-file reporting drops in cleanly.
-- Phase 1: SMS core. Later phases add LMS activities + NAT export builder.
-- ============================================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ---------------------------------------------------------------------------
-- Staff / trainers (admin login)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    email           VARCHAR(190) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    role            ENUM('admin','trainer','office') NOT NULL DEFAULT 'office',
    -- trainer credentials for AVETMISS / sign-off
    is_trainer      TINYINT(1) NOT NULL DEFAULT 0,
    signature_path  VARCHAR(255) NULL,          -- authorised signatory image
    active          TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Delivery locations  (AVETMISS NAT00020)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS locations (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,          -- e.g. "St. Marys", "Blacktown (Max Webber Function Centre)"
    identifier      VARCHAR(10)  NULL,              -- AVETMISS delivery location identifier
    address_line    VARCHAR(200) NULL,
    suburb          VARCHAR(100) NULL,
    state           VARCHAR(3)   NULL,              -- NSW etc
    postcode        VARCHAR(4)   NULL,
    active          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Units of competency  (AVETMISS NAT00060 - subject)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS units (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(20)  NOT NULL UNIQUE,   -- e.g. HLTAID009
    title           VARCHAR(255) NOT NULL,          -- Provide cardiopulmonary resuscitation
    nominal_hours   SMALLINT UNSIGNED NULL,         -- 12 / 36 etc (validation numbers)
    field_of_education VARCHAR(6) NULL,             -- AVETMISS FOE identifier
    vet_flag        TINYINT(1) NOT NULL DEFAULT 1,  -- VET vs non-VET
    active          TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Courses / programs / skill sets  (AVETMISS NAT00030 - program)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS courses (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(20)  NOT NULL,          -- HLTAID009 etc (course/program code)
    title           VARCHAR(255) NOT NULL,
    category        VARCHAR(120) NULL,
    validity_months SMALLINT UNSIGNED NULL,         -- 12 (CPR) / 36 (First Aid) -> cert expiry
    recognition     ENUM('nationally_recognised','other') NOT NULL DEFAULT 'nationally_recognised',
    program_level   VARCHAR(10) NULL,               -- AVETMISS program recognition identifier
    status          ENUM('current','archived') NOT NULL DEFAULT 'current',
    exclude_avetmiss TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Which units make up a course (core/elective)
CREATE TABLE IF NOT EXISTS course_units (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id       BIGINT UNSIGNED NOT NULL,
    unit_id         BIGINT UNSIGNED NOT NULL,
    type            ENUM('core','elective') NOT NULL DEFAULT 'core',
    UNIQUE KEY uniq_course_unit (course_id, unit_id),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id)   REFERENCES units(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Delivery plans (a sellable offering of a course: Express / Regular)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plans (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id       BIGINT UNSIGNED NOT NULL,
    title           VARCHAR(255) NOT NULL,          -- "HLTAID009 CPR Regular (Online + 45 Mins Face-to-Face)"
    delivery_mode   VARCHAR(60)  NULL,              -- online+f2f etc
    price           DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- authoritative price (CPR 45 / FA 89 / CC 99)
    active          TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Schedules (a dated class of a plan at a location)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schedules (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id         BIGINT UNSIGNED NOT NULL,
    location_id     BIGINT UNSIGNED NULL,
    name            VARCHAR(255) NULL,
    start_date      DATE NOT NULL,
    start_time      TIME NULL,
    end_date        DATE NOT NULL,
    end_time        TIME NULL,
    total_places    SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    calendar_colour VARCHAR(9) NULL,
    private_flag    TINYINT(1) NOT NULL DEFAULT 0,
    trainer_id      BIGINT UNSIGNED NULL,           -- assigned trainer
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id)     REFERENCES plans(id)     ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (trainer_id)  REFERENCES users(id)     ON DELETE SET NULL,
    INDEX idx_sched_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Students / learners  (AVETMISS NAT00080 + NAT00085 + NAT00090 + NAT00100)
-- Full AVETMISS demographic set captured up-front.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- identity
    client_identifier       VARCHAR(10) NULL,        -- AVETMISS client id (assigned)
    salutation              VARCHAR(10) NULL,
    first_name              VARCHAR(40) NOT NULL,
    middle_name             VARCHAR(40) NULL,
    last_name               VARCHAR(40) NOT NULL,
    date_of_birth           DATE NULL,
    gender                  ENUM('M','F','X','') NULL,   -- AVETMISS sex/gender codes
    -- USI
    usi_number              VARCHAR(10) NULL,
    usi_exemption           VARCHAR(30) NULL,
    usi_verified            TINYINT(1) NOT NULL DEFAULT 0,
    -- contact
    email                   VARCHAR(190) NULL,
    mobile_phone            VARCHAR(20) NULL,
    phone_home              VARCHAR(20) NULL,
    -- residential address
    building_property       VARCHAR(50) NULL,
    unit_flat               VARCHAR(30) NULL,
    street_number           VARCHAR(15) NULL,
    street_name             VARCHAR(70) NULL,
    suburb                  VARCHAR(50) NULL,
    state                   VARCHAR(3)  NULL,
    postcode                VARCHAR(4)  NULL,
    -- postal (NAT00085) if different
    postal_address          VARCHAR(200) NULL,
    -- birth
    town_of_birth           VARCHAR(50) NULL,
    country_of_birth        VARCHAR(4)  NULL,        -- SACC code
    -- AVETMISS demographic codes
    at_school_flag          CHAR(1) NULL,            -- Y/N
    highest_school_level    VARCHAR(2) NULL,         -- 08/10/11/12 ...
    year_highest_completed  SMALLINT UNSIGNED NULL,
    prior_achievement_flag  CHAR(1) NULL,            -- Y/N
    indigenous_status       VARCHAR(1) NULL,         -- 1/2/3/4/9
    labour_force_status     VARCHAR(2) NULL,         -- 01..05,08,09
    main_language           VARCHAR(4) NULL,         -- ASCL code (1201 English ...)
    proficiency_in_english  VARCHAR(1) NULL,         -- 1..4,9
    study_reason            VARCHAR(2) NULL,         -- 01..13
    -- disability (NAT00090) - flag + types held in student_disabilities
    disability_flag         CHAR(1) NULL,            -- Y/N
    -- misc
    country_of_citizenship  VARCHAR(4) NULL,
    notes                   TEXT NULL,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_name (last_name, first_name),
    INDEX idx_student_usi  (usi_number),
    INDEX idx_student_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Multiple disability types per student (NAT00090)
CREATE TABLE IF NOT EXISTS student_disabilities (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      BIGINT UNSIGNED NOT NULL,
    disability_code VARCHAR(2) NOT NULL,             -- 11..19,99
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_student_disability (student_id, disability_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Prior educational achievements (NAT00100)
CREATE TABLE IF NOT EXISTS student_prior_achievements (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      BIGINT UNSIGNED NOT NULL,
    achievement_code VARCHAR(3) NOT NULL,            -- 008/410/420/511/514/521/524/990
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_student_prior (student_id, achievement_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Enrolments (a student in a plan/schedule) (drives NAT00120 + NAT00130)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS enrolments (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id          BIGINT UNSIGNED NOT NULL,
    course_id           BIGINT UNSIGNED NOT NULL,
    plan_id             BIGINT UNSIGNED NOT NULL,
    schedule_id         BIGINT UNSIGNED NULL,
    location_id         BIGINT UNSIGNED NULL,
    start_date          DATE NULL,
    end_date            DATE NULL,
    status              ENUM('enrolled','complete','issued','incomplete','withdrawn') NOT NULL DEFAULT 'enrolled',
    -- AVETMISS enrolment fields
    funding_source_national VARCHAR(3) NULL,         -- e.g. 20 (Domestic - other revenue)
    client_tuition_fee  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    study_reason        VARCHAR(2) NULL,
    commencing_flag     TINYINT(1) NULL,
    -- payment quick-status (detail in payments)
    amount_due          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount_paid         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_status      ENUM('unpaid','part','paid') NOT NULL DEFAULT 'unpaid',
    stripe_payment_intent VARCHAR(60) NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)  REFERENCES students(id)  ON DELETE CASCADE,
    FOREIGN KEY (course_id)   REFERENCES courses(id)   ON DELETE RESTRICT,
    FOREIGN KEY (plan_id)     REFERENCES plans(id)     ON DELETE RESTRICT,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    INDEX idx_enr_status (status),
    INDEX idx_enr_schedule (schedule_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-enrolment unit results / outcomes (NAT00120)
CREATE TABLE IF NOT EXISTS enrolment_units (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrolment_id        BIGINT UNSIGNED NOT NULL,
    unit_id             BIGINT UNSIGNED NOT NULL,
    type                ENUM('core','elective') NOT NULL DEFAULT 'core',
    -- AVETMISS national outcome identifier:
    -- 20 competency achieved, 30 not achieved, 40 withdrawn, 51 RPL granted,
    -- 60 credit transfer, 70 continuing enrolment, 85 not started ...
    outcome_national    VARCHAR(2) NOT NULL DEFAULT '70',
    start_date          DATE NULL,
    end_date            DATE NULL,
    date_achieved       DATE NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (enrolment_id) REFERENCES enrolments(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id)      REFERENCES units(id)      ON DELETE RESTRICT,
    UNIQUE KEY uniq_enrol_unit (enrolment_id, unit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Payments
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrolment_id        BIGINT UNSIGNED NOT NULL,
    amount_due          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount_received     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    received_date       DATE NULL,
    description         VARCHAR(190) NULL,
    gst_included        TINYINT(1) NOT NULL DEFAULT 1,
    method              VARCHAR(30) NULL,            -- stripe/cash/eftpos
    stripe_reference    VARCHAR(60) NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enrolment_id) REFERENCES enrolments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Certificates (Statement of Attainment etc)  (NAT00130 program completed)
-- Expiry drives the automated renewal reminder.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS certificates (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrolment_id        BIGINT UNSIGNED NOT NULL,
    student_id          BIGINT UNSIGNED NOT NULL,
    type                ENUM('statement_of_attainment','qualification','certificate_of_achievement','record_of_results') NOT NULL DEFAULT 'statement_of_attainment',
    certificate_number  VARCHAR(30) NOT NULL UNIQUE, -- e.g. SA46055-22193
    template            VARCHAR(120) NULL,
    issue_date          DATE NOT NULL,
    expiry_date         DATE NULL,                   -- issue + course.validity_months
    file_path           VARCHAR(255) NULL,           -- generated PDF
    emailed_at          DATETIME NULL,
    -- renewal reminder tracking
    reminder_6wk_sent   DATETIME NULL,
    reminder_2wk_sent   DATETIME NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (enrolment_id) REFERENCES enrolments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id)   REFERENCES students(id)   ON DELETE CASCADE,
    INDEX idx_cert_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- AVETMISS reference code tables (populated from NCVER standards + RTO lists)
-- Kept in-DB so dropdowns + NAT export use the exact codes.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ref_codes (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain          VARCHAR(40) NOT NULL,            -- outcome_national / labour_force / indigenous_status / highest_school / prior_achievement / disability / study_reason / language / country / funding_source ...
    code            VARCHAR(6)  NOT NULL,
    label           VARCHAR(190) NOT NULL,
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    active          TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uniq_domain_code (domain, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- AVETMISS reporting runs (audit of NAT-file exports)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS avetmiss_exports (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    collection_year SMALLINT UNSIGNED NOT NULL,
    period_start    DATE NOT NULL,
    period_end      DATE NOT NULL,
    file_path       VARCHAR(255) NULL,               -- zipped NAT files
    generated_by    BIGINT UNSIGNED NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET foreign_key_checks = 1;
-- ============================================================================
-- End of Phase 1 SMS core schema. LMS module tables (modules, activities,
-- assessment_tasks, observation_criteria, attempts) added in Phase 3.
-- ============================================================================
