# CAT 2 evidence and completion tracker

Status key: **Implemented** means code/documentation exists; **Automated** means the named check passed in CI or was run locally; **VM evidence** requires a screenshot from the actual Kali VM. Never present a checklist or CI output as proof of a VM action.

## Part A — 4 marks

| Rubric item | Code/document | Automated status | Required submission evidence |
|---|---|---|---|
| Essential Linux host packages (1) | `README.md`, `docs/KALI_SETUP.md` | Document review only | Actual Kali install/VM screenshot |
| Role of PHP (1) | `README.md` | Document review only | Include explanation in report |
| Git/GitHub/Linux and serverless comparison (2) | `README.md` | Document review only | Repository and VM screenshots; explain VM hosting is conventional server hosting |

## Part B — 22 marks

| Rubric item | Implemented code | Automated test | Kali VM evidence |
|---|---|---|---|
| Signup/login pages pushed and cloned (3) | `public/signup.php`, `public/login.php`; Git remote configured | Auth integration test implemented; CI result pending | Not yet captured: clone and browser pages |
| Install/configure MariaDB (1) | `scripts/setup-kali.sh`, migrations | CI MariaDB service | Not yet captured: service status and DB setup |
| Create CAT2 database (1) | `database/migrations/001_schema.sql` and migration runner | CI applies migrations | Not yet captured: `SHOW DATABASES`, tables |
| PHP to DB connection (3) | PDO config in `config/db.php` | DB integration implemented; CI result pending | Not yet captured: real Kali configuration/dashboard |
| Register CNSA/CNSB (4) | Normal signup supports required usernames | Registration/auth integration covers equivalent real registration flows | Not yet captured: create the exact two usernames on VM |
| Authenticate both (4) | Password-hash login and protected dashboard | Auth integration implemented; CI result pending | Not yet captured: log in as each actual account |
| Additional form POST/retrieval (4) | Requests, detail, ownership and history pages | Database integration implemented; CI result pending | Not yet captured: submit and retrieve on VM |
| Lessons/challenges (2) | `README.md`, this checklist | Not applicable | Write your own actual reflection after the VM run |

The “Automated test” column is only complete after a recorded passing CI run or a local run with the test database configured. The VM evidence column remains incomplete until screenshots are taken from the actual Kali VM.
