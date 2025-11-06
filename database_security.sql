-- Security tables for Jembatan Timbangan Application
-- Run this script to create security-related tables

CREATE TABLE IF NOT EXISTS security_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_type VARCHAR(50) NOT NULL,
    description TEXT,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_ip_address (ip_address),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create indexes for performance
CREATE INDEX idx_security_logs_composite ON security_logs(ip_address, created_at);

-- Add sample security event types
INSERT INTO settings (setting_key, setting_value, description) VALUES
('security_log_retention_days', '90', 'Number of days to retain security logs'),
('max_login_attempts', '5', 'Maximum failed login attempts before lockout'),
('login_lockout_duration', '1800', 'Login lockout duration in seconds'),
('csrf_token_lifetime', '3600', 'CSRF token lifetime in seconds'),
('rate_limit_requests', '100', 'Rate limit requests per hour'),
('rate_limit_window', '3600', 'Rate limit time window in seconds')
ON DUPLICATE KEY UPDATE setting_key = setting_key;