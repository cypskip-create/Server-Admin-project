#!/usr/bin/env bash
set -euo pipefail
php scripts/lint.php
php tests/security_test.php
php tests/database_integration.php
