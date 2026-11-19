#!/usr/bin/env bash
set -euo pipefail
php scripts/lint.php
php tests/security_test.php
php tests/profile_photo_unit.php
php tests/database_integration.php
php tests/profile_photo_integration.php
