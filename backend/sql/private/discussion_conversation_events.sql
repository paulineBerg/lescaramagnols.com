-- FamilyDiscussion append-only conversation events.

CREATE TABLE IF NOT EXISTS car_discussion_conversation_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    actor_user_id INT NULL,
    event_type VARCHAR(64) NOT NULL,
    event_payload_json TEXT NULL,
    client_event_id VARCHAR(120) NULL,
    request_id VARCHAR(128) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_discussion_events_client (conversation_id, client_event_id),
    KEY idx_discussion_events_conversation (conversation_id, id),
    KEY idx_discussion_events_type (event_type, created_at),
    KEY idx_discussion_events_actor (actor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
