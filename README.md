# A&B First Aid Training — Student Management System (SMS)

A custom, self-hosted RTO platform for **A&B First Aid Training (RTO 46055)** — built to
replace the third-party RTO Data Cloud so all student data lives in the RTO's **own system**.

This repository is **Phase 1: the SMS core** (student & enrolment engine). It is built
**AVETMISS 8.0-ready** from day one so government (NCVER/NSW) reporting drops in cleanly.

## What's in this first working version

- **Secure staff login** (authorised signatory account)
- **Dashboard** — live counts, renewals due, and completed-ready-to-certify queue
- **Students** — full AVETMISS demographic record, USI (with verified flag / "no USI" alerts), search
- **Enrolments** — student ↔ course/plan/schedule, status, payment
- **Schedules** — dated classes per location with live enrolment counts
- **Courses** — nationally recognised training, validity (drives certificate expiry)
- **Certificates** — issued register with expiry tracking
- **Renewal Reminders** — automated 6-week / 2-week expiry reminder engine (the piece that broke in the migration)
- **AVETMISS** — NAT-file export builder (NAT00010–NAT00130) — scaffolded for Phase 4

## Tech

- PHP 8 + PDO. Portable: **SQLite** for this demo, the same code runs on **MySQL** in production.
- Bootstrap 5 admin UI. No framework lock-in; deploys to any standard PHP host (incl. the RTO's existing hosting).

## Run locally

```bash
php -S 127.0.0.1:8099 -t app/public
# open http://127.0.0.1:8099/?r=login
# demo login:  admin@anbfirstaidtraining.com.au  /  demo1234
```

The SQLite database + realistic sample data is created automatically on first run.

## Roadmap

| Phase | Scope | Status |
|-------|-------|--------|
| 1 | SMS core (students, enrolments, schedules, courses, payments) | **this build** |
| 2 | Certificate generation (Statement of Attainment, QR verify, generate + email) | next |
| 3 | LMS (online modules, quizzes, progress, practical assessment + trainer sign-off) | planned |
| 4 | AVETMISS NAT-file export + reporting dashboards | planned |

Built by Anirudha for A&B First Aid Training.
