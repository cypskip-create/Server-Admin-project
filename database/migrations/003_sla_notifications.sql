ALTER TABLE service_requests
    ADD COLUMN recommended_priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal' AFTER priority,
    ADD COLUMN sla_due_at DATETIME NULL AFTER expected_days,
    ADD COLUMN first_response_at DATETIME NULL AFTER sla_due_at,
    ADD INDEX idx_sla_due(sla_due_at,first_response_at);

UPDATE service_requests
SET sla_due_at=DATE_ADD(created_at, INTERVAL expected_days DAY)
WHERE sla_due_at IS NULL;

UPDATE service_requests
SET first_response_at=(
    SELECT MIN(h.created_at) FROM request_status_history h
    WHERE h.request_id=service_requests.id AND h.new_status<>'submitted'
)
WHERE first_response_at IS NULL
  AND EXISTS (SELECT 1 FROM request_status_history h WHERE h.request_id=service_requests.id AND h.new_status<>'submitted');

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    request_id BIGINT UNSIGNED NOT NULL,
    notification_type VARCHAR(40) NOT NULL,
    message VARCHAR(255) NOT NULL,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_request FOREIGN KEY(request_id) REFERENCES service_requests(id) ON DELETE CASCADE,
    INDEX idx_user_unread(user_id,read_at,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
