-- Security Center summaries for PB Gestion.
-- Durable server storage keeps pseudonymous tokens and summaries only.

CREATE TABLE IF NOT EXISTS car_security_networks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    network_token VARCHAR(96) NOT NULL,
    trust_state VARCHAR(32) NOT NULL DEFAULT 'pending',
    display_label VARCHAR(160) NULL,
    last_seen_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_security_network_owner_token (owner_id, network_token),
    KEY idx_security_networks_owner_state (owner_id, trust_state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_security_network_collectors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    network_id INT NOT NULL,
    collector_agent_id INT NOT NULL,
    collector_epoch BIGINT UNSIGNED NOT NULL,
    lease_expires_at DATETIME NOT NULL,
    last_renewed_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_security_network_collector (network_id),
    KEY idx_security_network_collectors_owner (owner_id, lease_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_security_devices_current (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    network_id INT NOT NULL,
    device_token VARCHAR(96) NOT NULL,
    device_kind VARCHAR(64) NOT NULL DEFAULT 'unknown',
    risk_level VARCHAR(32) NOT NULL DEFAULT 'unknown',
    first_seen_at DATETIME NULL,
    last_seen_at DATETIME NOT NULL,
    summary_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_security_device_owner_token (owner_id, device_token),
    KEY idx_security_devices_owner_network (owner_id, network_id),
    KEY idx_security_devices_risk (owner_id, risk_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_security_device_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    network_id INT NOT NULL,
    device_token VARCHAR(96) NOT NULL,
    change_type VARCHAR(64) NOT NULL,
    summary_json JSON NULL,
    detected_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_security_device_changes_owner_date (owner_id, detected_at),
    KEY idx_security_device_changes_device (owner_id, device_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_security_posture_current (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    agent_id INT NOT NULL,
    posture_state VARCHAR(32) NOT NULL DEFAULT 'unknown',
    risk_level VARCHAR(32) NOT NULL DEFAULT 'unknown',
    summary_json JSON NULL,
    reported_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_security_posture_agent (agent_id),
    KEY idx_security_posture_owner_state (owner_id, posture_state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_security_scan_summaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    agent_id INT NOT NULL,
    network_id INT NULL,
    collector_epoch BIGINT UNSIGNED NOT NULL DEFAULT 0,
    scan_type VARCHAR(64) NOT NULL DEFAULT 'passive',
    status VARCHAR(32) NOT NULL DEFAULT 'received',
    devices_seen INT UNSIGNED NOT NULL DEFAULT 0,
    changes_seen INT UNSIGNED NOT NULL DEFAULT 0,
    alerts_opened INT UNSIGNED NOT NULL DEFAULT 0,
    summary_json JSON NULL,
    scanned_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_security_scan_owner_date (owner_id, scanned_at),
    KEY idx_security_scan_agent (agent_id, scanned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_security_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    alert_uid CHAR(32) NOT NULL,
    logical_key VARCHAR(160) NOT NULL,
    severity VARCHAR(32) NOT NULL DEFAULT 'info',
    status VARCHAR(32) NOT NULL DEFAULT 'open',
    title VARCHAR(190) NOT NULL,
    summary VARCHAR(500) NOT NULL,
    opened_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    last_seen_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_security_alert_uid (alert_uid),
    UNIQUE KEY uq_security_alert_logical_open (owner_id, logical_key, status),
    KEY idx_security_alerts_owner_status (owner_id, status, severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_security_detail_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    agent_id INT NOT NULL,
    detail_uid CHAR(32) NOT NULL,
    request_uid CHAR(32) NOT NULL,
    purpose VARCHAR(120) NOT NULL,
    encrypted_payload MEDIUMBLOB NULL,
    payload_sha256 CHAR(64) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'requested',
    requested_at DATETIME NOT NULL,
    collected_at DATETIME NULL,
    read_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_security_detail_uid (detail_uid),
    UNIQUE KEY uq_security_detail_request_uid (request_uid),
    KEY idx_security_detail_owner_status (owner_id, status, expires_at),
    KEY idx_security_detail_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
