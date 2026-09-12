-- =====================================================================
--  Sutera Care Provider — operations database
--
--  Target: MySQL 8 on cPanel, in a database of its OWN — create one in
--  cPanel → MySQL Databases, e.g. sutempck_provider, with its own user.
--  Do NOT load this into sutempck_suteracares: the charity and the
--  business are separate legal entities with separate SSM registration
--  and separate bank accounts, and their data should not share a schema
--  or a database login. When the business moves to its own domain and
--  hosting, a separate database moves with it in one step.
--
--  Run this in cPanel → phpMyAdmin → select that database → SQL tab.
--  It is idempotent enough to re-run on an empty database, but it will
--  NOT preserve data — do not run it over a live database.
--
--  Conventions
--    * utf8mb4 throughout: names and notes contain Malay, Chinese and
--      Tamil characters, and emoji arrive in free-text notes whether
--      you want them or not.
--    * Money is DECIMAL(10,2), never FLOAT. Floating point loses sen.
--    * Times are stored as given (Asia/Kuala_Lumpur). The application
--      must not silently convert.
--    * Deletes are soft where a record is evidence (visits, invoices).
--      You cannot delete your way out of a dispute.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  1. People and access
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name              VARCHAR(150)    NOT NULL,
  email             VARCHAR(190)    NOT NULL,
  phone             VARCHAR(30)     NULL,
  password_hash     VARCHAR(255)    NOT NULL,
  role              ENUM('admin','coordinator','caregiver','guardian','patient') NOT NULL,
  status            ENUM('active','suspended','invited') NOT NULL DEFAULT 'invited',
  locale            ENUM('en','ms','zh') NOT NULL DEFAULT 'en',
  two_factor_secret VARCHAR(255)    NULL,
  last_login_at     DATETIME        NULL,
  created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY ix_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sanctum-compatible. Present from day one so the React Native apps
-- authenticate against the same table the web portal already uses.
DROP TABLE IF EXISTS personal_access_tokens;
CREATE TABLE personal_access_tokens (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tokenable_type VARCHAR(255)    NOT NULL,
  tokenable_id   BIGINT UNSIGNED NOT NULL,
  name           VARCHAR(255)    NOT NULL,
  token          VARCHAR(64)     NOT NULL,
  abilities      TEXT            NULL,
  device_name    VARCHAR(120)    NULL,
  last_used_at   DATETIME        NULL,
  expires_at     DATETIME        NULL,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pat_token (token),
  KEY ix_pat_tokenable (tokenable_type, tokenable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS password_resets;
CREATE TABLE password_resets (
  email      VARCHAR(190) NOT NULL,
  token      VARCHAR(255) NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  2. Clients, families and staff
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS patients;
CREATE TABLE patients (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code            VARCHAR(20)     NOT NULL COMMENT 'Human reference, e.g. SCP-0042',
  user_id         BIGINT UNSIGNED NULL COMMENT 'Only if the patient logs in themselves',
  name            VARCHAR(150)    NOT NULL,
  ic_number_enc   VARBINARY(512)  NULL COMMENT 'ENCRYPTED. Never store the IC in plain text',
  dob             DATE            NULL,
  gender          ENUM('female','male','other') NULL,
  address         VARCHAR(500)    NULL,
  area            VARCHAR(120)    NULL COMMENT 'Used for caregiver travel matching',
  postcode        VARCHAR(10)     NULL,
  mobility_level  ENUM('independent','walks_with_aid','wheelchair','bed_bound') NULL,
  languages       VARCHAR(200)    NULL,
  allergies       TEXT            NULL,
  notes_enc       VARBINARY(4096) NULL COMMENT 'ENCRYPTED. Free-text health notes',
  consent_given_at DATETIME       NULL COMMENT 'PDPA: explicit consent for sensitive personal data',
  consent_by      VARCHAR(150)    NULL COMMENT 'Who gave it, if not the patient',
  status          ENUM('enquiry','assessment','active','paused','closed') NOT NULL DEFAULT 'enquiry',
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_patients_code (code),
  KEY ix_patients_status (status),
  KEY ix_patients_area (area),
  CONSTRAINT fk_patients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A link table, not a column on patients. A patient often has several
-- family members involved with different levels of access, and a single
-- guardian_id would force you to pick a favourite child.
DROP TABLE IF EXISTS guardians;
CREATE TABLE guardians (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id            BIGINT UNSIGNED NOT NULL,
  patient_id         BIGINT UNSIGNED NOT NULL,
  relationship       VARCHAR(60)     NULL COMMENT 'Daughter, son, spouse, ...',
  is_primary         TINYINT(1)      NOT NULL DEFAULT 0,
  is_bill_payer      TINYINT(1)      NOT NULL DEFAULT 0,
  can_view_notes     TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'Personal-care notes are intimate; families disagree',
  can_view_invoices  TINYINT(1)      NOT NULL DEFAULT 0,
  can_request_changes TINYINT(1)     NOT NULL DEFAULT 1,
  created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_guardian_link (user_id, patient_id),
  KEY ix_guardians_patient (patient_id),
  CONSTRAINT fk_guardians_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_guardians_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS caregivers;
CREATE TABLE caregivers (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id                 BIGINT UNSIGNED NOT NULL,
  code                    VARCHAR(20)     NOT NULL COMMENT 'e.g. CG-014',
  ic_number_enc           VARBINARY(512)  NULL COMMENT 'ENCRYPTED',
  gender                  ENUM('female','male','other') NULL,
  dob                     DATE            NULL,
  languages               VARCHAR(200)    NULL,
  skills                  VARCHAR(500)    NULL COMMENT 'Comma list; dementia, hoisting, post-natal, massage',
  has_own_transport       TINYINT(1)      NOT NULL DEFAULT 0,
  max_travel_km           SMALLINT        NULL,
  base_area               VARCHAR(120)    NULL,
  hourly_rate             DECIMAL(10,2)   NULL COMMENT 'What we pay them, not what we charge',
  police_check_expires_at DATE            NULL COMMENT 'Dashboard warns 60 days out',
  right_to_work_verified  TINYINT(1)      NOT NULL DEFAULT 0,
  status                  ENUM('applicant','vetting','active','inactive','left') NOT NULL DEFAULT 'applicant',
  created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at              DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_caregivers_code (code),
  UNIQUE KEY uq_caregivers_user (user_id),
  KEY ix_caregivers_status (status),
  CONSTRAINT fk_caregivers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  3. Care plans — versioned, and separate from who delivers them
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS care_plans;
CREATE TABLE care_plans (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  patient_id     BIGINT UNSIGNED NOT NULL,
  version        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  effective_from DATE            NOT NULL,
  effective_to   DATE            NULL,
  agreed_by      VARCHAR(150)    NULL COMMENT 'Family member who agreed it',
  agreed_at      DATETIME        NULL,
  author_user_id BIGINT UNSIGNED NULL,
  notes          TEXT            NULL,
  status         ENUM('draft','active','superseded') NOT NULL DEFAULT 'draft',
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plan_version (patient_id, version),
  KEY ix_care_plans_status (status),
  CONSTRAINT fk_plans_patient FOREIGN KEY (patient_id)     REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_plans_author  FOREIGN KEY (author_user_id) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS care_plan_tasks;
CREATE TABLE care_plan_tasks (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  care_plan_id BIGINT UNSIGNED NOT NULL,
  category     ENUM('personal_care','mobility','household','companionship','appointment','wellness') NOT NULL,
  description  VARCHAR(400)    NOT NULL,
  frequency    ENUM('every_visit','daily','weekly','as_needed') NOT NULL DEFAULT 'every_visit',
  time_of_day  ENUM('morning','midday','evening','night','any') NOT NULL DEFAULT 'any',
  sort_order   SMALLINT        NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_tasks_plan (care_plan_id),
  CONSTRAINT fk_tasks_plan FOREIGN KEY (care_plan_id) REFERENCES care_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  4. Assignment, scheduling and what actually happened
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS assignments;
CREATE TABLE assignments (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  patient_id    BIGINT UNSIGNED NOT NULL,
  caregiver_id  BIGINT UNSIGNED NOT NULL,
  care_plan_id  BIGINT UNSIGNED NULL,
  role          ENUM('primary','relief') NOT NULL DEFAULT 'primary',
  start_date    DATE            NOT NULL,
  end_date      DATE            NULL,
  charge_rate   DECIMAL(10,2)   NULL COMMENT 'What the client pays per hour',
  status        ENUM('proposed','active','ended') NOT NULL DEFAULT 'proposed',
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_assign_patient (patient_id, status),
  KEY ix_assign_caregiver (caregiver_id, status),
  CONSTRAINT fk_assign_patient   FOREIGN KEY (patient_id)   REFERENCES patients(id)   ON DELETE CASCADE,
  CONSTRAINT fk_assign_caregiver FOREIGN KEY (caregiver_id) REFERENCES caregivers(id) ON DELETE CASCADE,
  CONSTRAINT fk_assign_plan      FOREIGN KEY (care_plan_id) REFERENCES care_plans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shifts are generated dated rows, not a recurrence rule. Real life is
-- full of exceptions — a public holiday, a hospital admission, a
-- caregiver off sick — and you cannot cancel one day of a rule.
DROP TABLE IF EXISTS shifts;
CREATE TABLE shifts (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  assignment_id     BIGINT UNSIGNED NOT NULL,
  shift_date        DATE            NOT NULL,
  start_time        TIME            NOT NULL,
  end_time          TIME            NOT NULL,
  status            ENUM('scheduled','in_progress','completed','missed','cancelled') NOT NULL DEFAULT 'scheduled',
  cancel_reason     VARCHAR(300)    NULL,
  covered_by_id     BIGINT UNSIGNED NULL COMMENT 'Relief caregiver, if the regular one could not attend',
  created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_shifts_date_status (shift_date, status),
  KEY ix_shifts_assignment (assignment_id),
  CONSTRAINT fk_shifts_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_shifts_cover      FOREIGN KEY (covered_by_id) REFERENCES caregivers(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The evidence table. "Was anyone actually there on Tuesday" is the
-- question you will be asked in a dispute, and memory is not evidence.
DROP TABLE IF EXISTS visit_logs;
CREATE TABLE visit_logs (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  shift_id         BIGINT UNSIGNED NOT NULL,
  caregiver_id     BIGINT UNSIGNED NOT NULL COMMENT 'Who actually attended, which may differ from the assignment',
  check_in_at      DATETIME        NULL,
  check_out_at     DATETIME        NULL,
  check_in_lat     DECIMAL(10,7)   NULL,
  check_in_lng     DECIMAL(10,7)   NULL,
  tasks_completed  JSON            NULL COMMENT 'Array of care_plan_task ids ticked off',
  notes            TEXT            NULL COMMENT 'Short account of how the visit went',
  concern_flagged  TINYINT(1)      NOT NULL DEFAULT 0,
  concern_detail   VARCHAR(600)    NULL,
  minutes_worked   SMALLINT UNSIGNED NULL COMMENT 'Derived at check-out; billing reads this',
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_visit_shift (shift_id),
  KEY ix_visits_caregiver (caregiver_id),
  KEY ix_visits_concern (concern_flagged),
  CONSTRAINT fk_visits_shift     FOREIGN KEY (shift_id)     REFERENCES shifts(id)     ON DELETE CASCADE,
  CONSTRAINT fk_visits_caregiver FOREIGN KEY (caregiver_id) REFERENCES caregivers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anyone can raise one, including the patient. Routed to a coordinator.
DROP TABLE IF EXISTS concerns;
CREATE TABLE concerns (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  raised_by_id  BIGINT UNSIGNED NOT NULL,
  patient_id    BIGINT UNSIGNED NULL,
  shift_id      BIGINT UNSIGNED NULL,
  category      ENUM('care_quality','attendance','safety','billing','staff_conduct','other') NOT NULL,
  detail        TEXT            NOT NULL,
  status        ENUM('open','investigating','resolved','closed') NOT NULL DEFAULT 'open',
  assigned_to_id BIGINT UNSIGNED NULL,
  resolution    TEXT            NULL,
  resolved_at   DATETIME        NULL,
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_concerns_status (status),
  CONSTRAINT fk_concerns_raiser  FOREIGN KEY (raised_by_id)   REFERENCES users(id)    ON DELETE RESTRICT,
  CONSTRAINT fk_concerns_patient FOREIGN KEY (patient_id)     REFERENCES patients(id) ON DELETE SET NULL,
  CONSTRAINT fk_concerns_shift   FOREIGN KEY (shift_id)       REFERENCES shifts(id)   ON DELETE SET NULL,
  CONSTRAINT fk_concerns_owner   FOREIGN KEY (assigned_to_id) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  5. Services and billing
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS services;
CREATE TABLE services (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code        VARCHAR(30)     NOT NULL,
  name        VARCHAR(150)    NOT NULL,
  category    ENUM('personal_care','daily_care','live_in','appointment','wellness') NOT NULL,
  unit        ENUM('hour','visit','day','month','session') NOT NULL,
  base_rate   DECIMAL(10,2)   NOT NULL,
  active      TINYINT(1)      NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_services_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS invoices;
CREATE TABLE invoices (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  number        VARCHAR(30)     NOT NULL COMMENT 'e.g. INV-2026-0118',
  patient_id    BIGINT UNSIGNED NOT NULL,
  bill_to_id    BIGINT UNSIGNED NULL COMMENT 'Guardian who pays',
  period_start  DATE            NOT NULL,
  period_end    DATE            NOT NULL,
  subtotal      DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  surcharges    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  total         DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  amount_paid   DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  currency      CHAR(3)         NOT NULL DEFAULT 'MYR',
  due_date      DATE            NULL,
  status        ENUM('draft','sent','part_paid','paid','overdue','void') NOT NULL DEFAULT 'draft',
  issued_at     DATETIME        NULL,
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invoice_number (number),
  KEY ix_invoices_status (status, due_date),
  CONSTRAINT fk_invoices_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT,
  CONSTRAINT fk_invoices_billto  FOREIGN KEY (bill_to_id) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lines point at shifts, so every invoice can be explained line by line.
DROP TABLE IF EXISTS invoice_lines;
CREATE TABLE invoice_lines (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id  BIGINT UNSIGNED NOT NULL,
  shift_id    BIGINT UNSIGNED NULL,
  service_id  BIGINT UNSIGNED NULL,
  description VARCHAR(300)    NOT NULL,
  quantity    DECIMAL(8,2)    NOT NULL DEFAULT 1.00,
  rate        DECIMAL(10,2)   NOT NULL,
  amount      DECIMAL(10,2)   NOT NULL,
  PRIMARY KEY (id),
  KEY ix_lines_invoice (invoice_id),
  CONSTRAINT fk_lines_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  CONSTRAINT fk_lines_shift   FOREIGN KEY (shift_id)   REFERENCES shifts(id)   ON DELETE SET NULL,
  CONSTRAINT fk_lines_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id  BIGINT UNSIGNED NOT NULL,
  amount      DECIMAL(10,2)   NOT NULL,
  method      ENUM('bank_transfer','duitnow','cash','cheque','card') NOT NULL,
  reference   VARCHAR(120)    NULL,
  paid_on     DATE            NOT NULL,
  recorded_by_id BIGINT UNSIGNED NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_payments_invoice (invoice_id),
  CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id)     REFERENCES invoices(id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_user    FOREIGN KEY (recorded_by_id) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  6. Website intake — the two forms on provider.suteracares.org
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS enquiries;
CREATE TABLE enquiries (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_name         VARCHAR(150)    NOT NULL,
  client_phone        VARCHAR(30)     NOT NULL,
  client_email        VARCHAR(190)    NULL,
  client_relationship VARCHAR(80)     NULL,
  patient_name        VARCHAR(150)    NULL,
  patient_age         SMALLINT        NULL,
  patient_area        VARCHAR(120)    NULL,
  patient_mobility    VARCHAR(60)     NULL,
  needs               TEXT            NULL,
  schedule_wanted     VARCHAR(200)    NULL,
  source              VARCHAR(60)     NULL DEFAULT 'website',
  status              ENUM('new','contacted','assessment_booked','converted','declined','lost') NOT NULL DEFAULT 'new',
  patient_id          BIGINT UNSIGNED NULL COMMENT 'Set when the enquiry becomes a client',
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_enquiries_status (status, created_at),
  CONSTRAINT fk_enquiries_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS job_applications;
CREATE TABLE job_applications (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name           VARCHAR(150)    NOT NULL,
  phone          VARCHAR(30)     NOT NULL,
  email          VARCHAR(190)    NULL,
  area           VARCHAR(120)    NULL,
  role_applied   VARCHAR(120)    NULL,
  years_experience SMALLINT      NULL,
  availability   VARCHAR(60)     NULL,
  languages      VARCHAR(200)    NULL,
  notes          TEXT            NULL,
  status         ENUM('new','screening','interview','offered','hired','rejected') NOT NULL DEFAULT 'new',
  caregiver_id   BIGINT UNSIGNED NULL COMMENT 'Set on hire',
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_apps_status (status, created_at),
  CONSTRAINT fk_apps_caregiver FOREIGN KEY (caregiver_id) REFERENCES caregivers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  7. Audit — PDPA requires you to know who looked at what
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS audit_log;
CREATE TABLE audit_log (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NULL,
  action       VARCHAR(60)     NOT NULL COMMENT 'viewed, created, updated, deleted, exported, login_failed',
  subject_type VARCHAR(60)     NULL COMMENT 'patient, caregiver, invoice, ...',
  subject_id   BIGINT UNSIGNED NULL,
  detail       VARCHAR(500)    NULL,
  ip_address   VARCHAR(45)     NULL,
  user_agent   VARCHAR(300)    NULL,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_subject (subject_type, subject_id),
  KEY ix_audit_user_time (user_id, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  Seed data — the service catalogue from the website's pricing section.
--  Rates are the indicative ones published; adjust as real pricing lands.
-- =====================================================================

INSERT INTO services (code, name, category, unit, base_rate) VALUES
  ('HOURLY-PC',   'Personal care — hourly visit',        'personal_care', 'hour',    35.00),
  ('DAILY-8H',    'Daily care — 8 hour day',             'daily_care',    'day',    180.00),
  ('LIVEIN-M',    'Live-in care — monthly',              'live_in',       'month', 3500.00),
  ('ESCORT',      'Hospital or clinic appointment escort','appointment',   'visit',  120.00),
  ('MASSAGE-60',  'Comfort massage — 60 minutes',        'wellness',      'session', 120.00),
  ('MASSAGE-PN',  'Post-natal massage — 60 minutes',     'wellness',      'session', 150.00),
  ('WELLNESS-ST', 'Assisted stretching session',         'wellness',      'session',  90.00);

-- ---------------------------------------------------------------------
--  First administrator.
--
--  The hash below is a PLACEHOLDER and does not correspond to any
--  usable password — it cannot be logged into. Generate a real one and
--  UPDATE this row before the portal goes live:
--
--      php -r "echo password_hash('your-strong-password', PASSWORD_BCRYPT);"
--
--  Do not put a real password in this file. This file is in git.
-- ---------------------------------------------------------------------

INSERT INTO users (name, email, password_hash, role, status) VALUES
  ('Sutera Administrator', 'admin@suteracares.org', 'PLACEHOLDER-REPLACE-ME', 'admin', 'invited');
