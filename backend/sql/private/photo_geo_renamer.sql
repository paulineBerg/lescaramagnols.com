-- Photo Geo Renamer schema.
-- The server stores counters and metadata only; image files remain local.

CREATE TABLE IF NOT EXISTS car_photo_geo_sequences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commune_key VARCHAR(160) NOT NULL,
    commune_name VARCHAR(160) NOT NULL,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    initialized_manually TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_photo_geo_sequences_commune_key (commune_key),
    KEY idx_photo_geo_sequences_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_photo_geo_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_uid CHAR(32) NOT NULL,
    preview_uid CHAR(32) NULL,
    private_user_id INT NULL,
    agent_id INT NULL,
    agent_uid CHAR(32) NULL,
    root_uid VARCHAR(64) NULL,
    relative_dir VARCHAR(240) NULL,
    template_json JSON NULL,
    `separator` VARCHAR(4) NOT NULL DEFAULT '-',
    counter_digits TINYINT UNSIGNED NOT NULL DEFAULT 2,
    sort_order VARCHAR(32) NOT NULL DEFAULT 'chronological',
    total_files INT UNSIGNED NOT NULL DEFAULT 0,
    success_files INT UNSIGNED NOT NULL DEFAULT 0,
    failed_files INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_photo_geo_batches_uid (batch_uid),
    UNIQUE KEY uq_photo_geo_batches_preview (preview_uid),
    KEY idx_photo_geo_batches_user_status (private_user_id, status),
    KEY idx_photo_geo_batches_agent (agent_id, status),
    KEY idx_photo_geo_batches_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_photo_geo_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    file_uid VARCHAR(96) NULL,
    original_name VARCHAR(240) NOT NULL,
    new_name VARCHAR(240) NULL,
    relative_path VARCHAR(512) NOT NULL,
    commune_key VARCHAR(160) NULL,
    commune_name VARCHAR(160) NULL,
    assigned_number INT UNSIGNED NULL,
    taken_at DATETIME NULL,
    taken_at_source VARCHAR(64) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    error_code VARCHAR(80) NULL,
    error_message VARCHAR(240) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    KEY idx_photo_geo_operations_batch (batch_id),
    KEY idx_photo_geo_operations_status (status),
    KEY idx_photo_geo_operations_commune (commune_key, assigned_number),
    KEY idx_photo_geo_operations_created (created_at),
    CONSTRAINT fk_photo_geo_operations_batch
        FOREIGN KEY (batch_id) REFERENCES car_photo_geo_batches (id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_photo_geo_places (
    id INT AUTO_INCREMENT PRIMARY KEY,
    geo_key VARCHAR(64) NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    commune_key VARCHAR(160) NOT NULL,
    commune_name VARCHAR(160) NOT NULL,
    postal_code VARCHAR(16) NULL,
    department VARCHAR(120) NULL,
    region VARCHAR(120) NULL,
    country_code CHAR(2) NULL,
    provider VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_photo_geo_places_geo_key (geo_key),
    KEY idx_photo_geo_places_commune (commune_key),
    KEY idx_photo_geo_places_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
