ALTER TABLE users
    ADD COLUMN IF NOT EXISTS profile_photo_key CHAR(53) NULL DEFAULT NULL AFTER student_number;
