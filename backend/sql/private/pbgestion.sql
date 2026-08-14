-- Sécurité réseau webapp schema.
-- Raw local network details stay on the agent by default; server tables keep
-- opaque identifiers, state, summaries and short-lived encrypted details.

CREATE TABLE IF NOT EXISTS car_pb_agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    agent_uid CHAR(32) NOT NULL,
    display_name VARCHAR(160) NOT NULL,
    public_key_base64 VARCHAR(128) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    os_family VARCHAR(32) NOT NULL DEFAULT 'windows',
    os_version VARCHAR(80) NULL,
    agent_version VARCHAR(80) NULL,
    location_label VARCHAR(160) NULL,
    capabilities_json JSON NULL,
    last_seen_at DATETIME NULL,
    last_sequence BIGINT UNSIGNED NOT NULL DEFAULT 0,
    revoked_at DATETIME NULL,
    revoked_reason VARCHAR(160) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pb_agents_uid (agent_uid),
    KEY idx_pb_agents_owner_status (owner_id, status),
    KEY idx_pb_agents_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_pb_agent_capabilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    agent_id INT NOT NULL,
    capability_code VARCHAR(80) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pb_agent_capability (agent_id, capability_code),
    KEY idx_pb_agent_capabilities_owner (owner_id, capability_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_pb_agent_sync_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    agent_id INT NOT NULL,
    last_sequence BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_sync_at DATETIME NULL,
    last_posture_at DATETIME NULL,
    last_scan_summary_at DATETIME NULL,
    coverage_state VARCHAR(32) NOT NULL DEFAULT 'interrupted',
    collector_epoch BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pb_agent_sync_state_agent (agent_id),
    KEY idx_pb_agent_sync_state_owner (owner_id, coverage_state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_pb_agent_request_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    agent_id INT NOT NULL,
    request_uuid CHAR(36) NOT NULL,
    request_path VARCHAR(160) NOT NULL,
    sequence_number BIGINT UNSIGNED NOT NULL,
    received_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pb_agent_request_uuid (agent_id, request_uuid),
    KEY idx_pb_agent_request_expiry (expires_at),
    KEY idx_pb_agent_request_owner (owner_id, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_pb_enrollment_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    token_uid CHAR(32) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    location_label VARCHAR(160) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
    expires_at DATETIME NOT NULL,
    claimed_at DATETIME NULL,
    claimed_agent_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pb_enrollment_token_uid (token_uid),
    KEY idx_pb_enrollment_owner_status (owner_id, status, expires_at),
    KEY idx_pb_enrollment_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_pb_commands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    agent_id INT NOT NULL,
    command_uid CHAR(32) NOT NULL,
    command_type VARCHAR(64) NOT NULL,
    payload_json JSON NOT NULL,
    idempotency_key VARCHAR(96) NOT NULL,
    server_sequence BIGINT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    requested_by VARCHAR(254) NULL,
    result_code VARCHAR(80) NULL,
    result_message VARCHAR(240) NULL,
    expires_at DATETIME NOT NULL,
    delivered_at DATETIME NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pb_command_uid (command_uid),
    UNIQUE KEY uq_pb_command_idempotency (owner_id, agent_id, idempotency_key),
    UNIQUE KEY uq_pb_command_agent_sequence (agent_id, server_sequence),
    KEY idx_pb_commands_owner_status (owner_id, status),
    KEY idx_pb_commands_poll (agent_id, status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_pb_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    policy_uid CHAR(32) NOT NULL,
    policy_json JSON NOT NULL,
    signature_base64 VARCHAR(128) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'desired',
    created_by VARCHAR(254) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pb_policy_uid (policy_uid),
    KEY idx_pb_policies_owner_status (owner_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_pb_agent_policy_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    agent_id INT NOT NULL,
    desired_policy_id INT NULL,
    received_policy_id INT NULL,
    applied_policy_id INT NULL,
    desired_at DATETIME NULL,
    received_at DATETIME NULL,
    applied_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pb_agent_policy_state_agent (agent_id),
    KEY idx_pb_agent_policy_state_owner (owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_pb_backup_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    agent_id INT NOT NULL,
    snapshot_state VARCHAR(32) NOT NULL DEFAULT 'unknown',
    external_backup_state VARCHAR(32) NOT NULL DEFAULT 'unknown',
    external_volume_token VARCHAR(96) NULL,
    last_snapshot_at DATETIME NULL,
    last_external_backup_at DATETIME NULL,
    last_verify_at DATETIME NULL,
    restore_state VARCHAR(32) NOT NULL DEFAULT 'not_requested',
    reported_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pb_backup_status_agent (agent_id),
    KEY idx_pb_backup_owner_state (owner_id, external_backup_state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_pb_agent_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(80) NOT NULL,
    os_family VARCHAR(32) NOT NULL DEFAULT 'windows',
    min_supported_version VARCHAR(80) NULL,
    manifest_sha256 CHAR(64) NOT NULL,
    manifest_signature_base64 VARCHAR(128) NOT NULL,
    package_state VARCHAR(32) NOT NULL DEFAULT 'ready',
    windows_10_warning TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pb_agent_versions_version_os (version, os_family),
    KEY idx_pb_agent_versions_state (package_state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO car_private_modules (`code`, `is_active`, `display_name`, `description`)
    VALUES
        ('pbgestion', 1, 'Sécurité réseau', 'Alias historique du socle agent PbGestion.'),
        ('security_center', 1, 'Sécurité réseau', 'Alias transitoire de la webapp Sécurité réseau.'),
        ('network_security', 1, 'Sécurité réseau', 'Pilotage des agents locaux, couverture, alertes et synthèses de sécurité.'),
        ('photo_geo_renamer', 1, 'Photo rename', 'Renommage local de photos avec aperçu, géolocalisation et mode restreint sans agent.');

INSERT IGNORE INTO car_private_user_module_permissions (
    private_user_id,
    private_module_id,
    is_active,
    granted_by_admin_email,
    granted_at
)
SELECT
    permissions.private_user_id,
    target_modules.id,
    permissions.is_active,
    permissions.granted_by_admin_email,
    permissions.granted_at
FROM car_private_user_module_permissions permissions
INNER JOIN car_private_modules legacy_module
    ON legacy_module.id = permissions.private_module_id
   AND legacy_module.code = 'pbgestion'
INNER JOIN car_private_modules target_modules
    ON target_modules.code IN ('network_security', 'photo_geo_renamer')
WHERE permissions.is_active = 1;

INSERT IGNORE INTO car_private_user_module_permissions (
    private_user_id,
    private_module_id,
    is_active,
    granted_by_admin_email,
    granted_at
)
SELECT
    permissions.private_user_id,
    target_modules.id,
    permissions.is_active,
    permissions.granted_by_admin_email,
    permissions.granted_at
FROM car_private_user_module_permissions permissions
INNER JOIN car_private_modules legacy_module
    ON legacy_module.id = permissions.private_module_id
   AND legacy_module.code = 'security_center'
INNER JOIN car_private_modules target_modules
    ON target_modules.code = 'network_security'
WHERE permissions.is_active = 1;
