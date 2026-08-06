CREATE TABLE IF NOT EXISTS {{table:trusted_devices}} (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_scope` VARCHAR(16) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `user_identifier_hash` CHAR(64) NOT NULL,
  `public_id` CHAR(32) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `device_type` VARCHAR(32) NOT NULL,
  `user_agent_hash` CHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `last_seen_at` DATETIME NULL,
  `last_ip_hash` CHAR(64) NOT NULL DEFAULT '',
  `trusted_until` DATETIME NOT NULL,
  `revoked_at` DATETIME NULL,
  `revoked_reason` VARCHAR(120) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trusted_devices_public_id` (`public_id`),
  KEY `trusted_devices_user_lookup` (`user_scope`, `user_identifier_hash`, `user_id`),
  KEY `trusted_devices_trusted_until` (`trusted_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{table:persistent_session_tokens}} (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trusted_device_id` BIGINT UNSIGNED NOT NULL,
  `selector` CHAR(32) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `scope` VARCHAR(16) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `last_used_at` DATETIME NULL,
  `expires_at` DATETIME NOT NULL,
  `rotated_at` DATETIME NULL,
  `revoked_at` DATETIME NULL,
  `revoked_reason` VARCHAR(120) NULL,
  `replaced_by_token_id` BIGINT UNSIGNED NULL,
  `token_family_id` CHAR(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `persistent_session_tokens_selector` (`selector`),
  KEY `persistent_session_tokens_device_scope` (`trusted_device_id`, `scope`),
  KEY `persistent_session_tokens_family` (`token_family_id`),
  KEY `persistent_session_tokens_expires` (`expires_at`),
  CONSTRAINT `fk_persistent_session_tokens_device`
    FOREIGN KEY (`trusted_device_id`) REFERENCES {{table:trusted_devices}} (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
