# CAT 2 evidence and completion tracker

Status meanings: **Implemented** means repository code or explanation exists; **CI/automated test** means an actual passing test with a recorded run; **Kali VM demonstrated** requires evidence from the student's actual Kali VM. CI and Azure results do not prove Kali VM activity.

## Part A — 4 marks

| Rubric item | Marks | Implemented | CI / automated test | Kali VM / report evidence still needed |
|---|---:|---|---|---|
| Essential Linux host packages | 1 | `docs/KALI_SETUP.md` and `scripts/setup-kali.sh`; PHP runtime packages/modules are covered | Bash syntax check passed in CI; this does not install the packages | Capture the actual Kali package/module install and `php -m` output. |
| Role of PHP in web hosting | 1 | Explanation in `README.md` | Not an executable feature; no automated test | Include the explanation in the submitted report. |
| Git/GitHub/Linux and serverless comparison | 2 | Explanation in `README.md` | GitHub workflow passed; no VM/serverless comparison is tested by CI | Include screenshots from the Kali VM/GitHub and explain that VM hosting is traditional server hosting. |

## Part B — 22 marks

| Rubric item | Marks | Implemented code | CI / deployed automated result | Kali VM evidence still needed |
|---|---:|---|---|---|
| Signup/login pages pushed and cloned | 3 | `public/signup.php`, `public/login.php`; GitHub remote contains the project | Registration/authentication integration tests passed in CI and on Azure | Screenshot clone/pull and signup/login pages from Kali. |
| Install/configure MariaDB | 1 | `scripts/setup-kali.sh`; CI provisions MariaDB 11 and Azure runs MariaDB 10.11 | MariaDB integration suite passed in both CI and Azure | Show Kali MariaDB service status and PHP PDO module. |
| Create CAT2 database | 1 | `scripts/migrate.php` and ordered migrations | CI applied migrations 001–004 into `CAT2`; Azure applied them to its separate `campusflow_prod` DB | Show `SHOW DATABASES`, `SHOW TABLES`, and migration ledger from Kali CAT2. |
| Connect PHP signup/login to database | 3 | PDO connection/config in `config/db.php`, AuthService and migration runner | Valid/invalid registration, uniqueness, hash, login and throttling checks passed | Demonstrate registration and login on the Kali-hosted site. |
| Register `studentCNSA` and `studentCNSB` | 4 | Signup accepts the rubric usernames; test creates both username patterns with unique suffixes | Registration and database uniqueness tests passed | Register the exact two usernames through the real signup form on Kali. |
| Authenticate both users | 4 | AuthService and session-backed portal | Integration suite checks valid authentication for both student account patterns and rejects wrong passwords | Log in separately with both exact registered accounts and capture their protected page. |
| Additional form POST and retrieval | 4 | Service request form, persistence, ownership checks, tracking, history, SLA and notifications | Request creation/retrieval, owner isolation, status update and notification tests passed | Submit a form in Kali and show the persisted record/details in the portal. |
| Lessons and challenges | 2 | Reflection prompts in `README.md` | Not applicable | Record the team's actual learning and challenge from the Kali VM run. |

## Current evidence boundary

GitHub Actions run [44398099946](https://github.com/cypskip-create/Server-Admin-project/actions/runs/44398099946) passed the PHP lint, security, MariaDB integration and Bash syntax checks for commit `917a591`. The Azure production integration suite also passed. **No Kali VM session or screenshots were captured in this deployment**, so the Kali-specific evidence items remain open until the students perform those steps on Kali.
