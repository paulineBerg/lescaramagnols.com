-- Document hub - links between logical documents and business entities.
-- entity_type is a controlled code declared by a module DocumentIntegration (e.g. rental.property).

CREATE TABLE IF NOT EXISTS car_private_document_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id VARCHAR(64) NOT NULL,
    link_role VARCHAR(32) NOT NULL DEFAULT 'attachment',
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_private_document_links_unique (document_id, entity_type, entity_id, link_role),
    KEY idx_private_document_links_entity (entity_type, entity_id),
    KEY idx_private_document_links_document (document_id),
    CONSTRAINT fk_private_document_links_document
        FOREIGN KEY (document_id)
        REFERENCES car_private_document_library (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_private_document_links_created_by
        FOREIGN KEY (created_by)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
