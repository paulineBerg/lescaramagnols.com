-- Document hub - document versions. A correction creates a new version pointing to a new object;
-- previous objects are never overwritten.

CREATE TABLE IF NOT EXISTS car_private_document_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    object_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL DEFAULT '',
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_private_document_versions_number (document_id, version_number),
    KEY idx_private_document_versions_object (object_id),
    CONSTRAINT fk_private_document_versions_document
        FOREIGN KEY (document_id)
        REFERENCES car_private_document_library (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_private_document_versions_object
        FOREIGN KEY (object_id)
        REFERENCES car_private_document_objects (id),
    CONSTRAINT fk_private_document_versions_created_by
        FOREIGN KEY (created_by)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
