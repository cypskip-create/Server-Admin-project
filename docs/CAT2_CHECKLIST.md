# CAT 2 evidence and completion tracker

Status key: **Implemented** means code/documentation exists; **Successfully tested** names a check that actually passed (include run date/link or captured output); **VM demonstrated** requires an observation from the actual Kali VM; **Externally verified** means the public service was independently checked. A CI result does not prove a Kali VM action. Update the test column only after a real pass; do not infer it from the workflow definition.

## Part A — 4 marks

| Rubric item | Code/document | Automated status | Required submission evidence |
|---|---|---|---|
| Essential Linux host packages (1) | `README.md`, `docs/KALI_SETUP.md` | Not run in this checkout | Actual Kali install/VM screenshot |
| Role of PHP (1) | `README.md` | Not run in this checkout | Include explanation in report |
| Git/GitHub/Linux and serverless comparison (2) | `README.md` | Not run in this checkout | Repository and VM screenshots; explain VM hosting is conventional server hosting |

## Part B — 22 marks

| Rubric item | Implemented code | Automated test | Kali VM evidence |
|---|---|---|---|
| Signup/login pages pushed and cloned (3) | `public/signup.php`, `public/login.php`; remote is configured | Pending automated test | Not captured: GitHub push/clone and browser pages from Kali VM |
| Install/configure MariaDB (1) | `scripts/setup-kali.sh`, migrations | Pending automated test | Not captured: Kali service status and setup |
| Create CAT2 database (1) | `database/migrations/001_schema.sql` and migration runner | Pending automated test | Not captured: Kali `SHOW DATABASES`, tables |
| PHP to DB connection (3) | PDO config in `config/db.php` | Pending automated test | Not captured: Kali database-backed page |
| Register CNSA/CNSB (4) | Signup supports the required usernames | Pending automated test | Not captured: register the exact two users on the VM |
| Authenticate both (4) | Password hashing and protected dashboard | Pending automated test | Not captured: log in as each actual account |
| Additional form POST/retrieval (4) | Requests, detail, ownership, history, SLA and notifications | Pending automated test | Not captured: submit/retrieve from Kali browser |
| Lessons/challenges (2) | README reflection prompt | Not applicable | Students must record their actual lessons after their VM run |

The “Successfully tested” column is only complete after a recorded passing CI run or a local run with the test database configured. The VM evidence column remains incomplete until screenshots are taken from the actual Kali VM.
