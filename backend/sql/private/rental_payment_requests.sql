-- Private real estate rental schema - payment requests

CREATE TABLE IF NOT EXISTS car_rental_payment_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_rent_id INT NOT NULL,
    rental_lease_id INT NOT NULL,
    rental_property_id INT NOT NULL,
    rental_unit_id INT NOT NULL,
    recipient_email VARCHAR(190) NOT NULL,
    subject VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    signature TEXT NULL,
    channel ENUM('email', 'pdf', 'copy') NOT NULL DEFAULT 'email',
    status ENUM('sent', 'failed', 'exported') NOT NULL DEFAULT 'sent',
    idempotency_key CHAR(64) NOT NULL,
    snapshot_payload JSON NULL,
    failure_reason VARCHAR(180) NULL,
    sent_by_private_user_id INT NOT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rental_payment_requests_idempotency (idempotency_key),
    KEY idx_rental_payment_requests_property_status (rental_property_id, status, created_at),
    KEY idx_rental_payment_requests_rent (rental_rent_id, channel, status),
    KEY idx_rental_payment_requests_due (rental_property_id, sent_at),
    CONSTRAINT fk_rental_payment_requests_rent
        FOREIGN KEY (rental_rent_id)
        REFERENCES car_rental_rents (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_payment_requests_lease
        FOREIGN KEY (rental_lease_id)
        REFERENCES car_rental_leases (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_payment_requests_property
        FOREIGN KEY (rental_property_id)
        REFERENCES car_rental_properties (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_payment_requests_unit
        FOREIGN KEY (rental_unit_id)
        REFERENCES car_rental_units (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_payment_requests_sent_by
        FOREIGN KEY (sent_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
