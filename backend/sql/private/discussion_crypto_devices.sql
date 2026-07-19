-- FamilyDiscussion client-side encryption devices.

CREATE TABLE IF NOT EXISTS car_discussion_crypto_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    device_id VARCHAR(64) NOT NULL,
    device_label VARCHAR(120) NOT NULL DEFAULT '',
    public_key_jwk MEDIUMTEXT NOT NULL,
    algorithm VARCHAR(64) NOT NULL DEFAULT 'RSA-OAEP-256',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,
    UNIQUE KEY uq_discussion_crypto_device (private_user_id, device_id),
    KEY idx_discussion_crypto_devices_user (private_user_id, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
