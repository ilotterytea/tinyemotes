CREATE TABLE IF NOT EXISTS users (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT,
    secret_key TEXT NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP,
    last_active_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS connections (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id),
    alias_id TEXT NOT NULL,
    platform TEXT NOT NULL,
    data TEXT NOT NULL,
    connected_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS emotes (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    code TEXT NOT NULL,
    mime TEXT NOT NULL,
    ext TEXT NOT NULL,
    uploaded_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL,
    visibility INTEGER NOT NULL,
    is_featured BOOLEAN NOT NULL DEFAULT false
);

CREATE TABLE IF NOT EXISTS emote_sets (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    owner_id INTEGER NOT NULL REFERENCES users(id),
    linked_to INTEGER REFERENCES emote_sets(id),
    name TEXT NOT NULL,
    size INTEGER,
    is_global BOOLEAN NOT NULL DEFAULT false
);

CREATE TABLE IF NOT EXISTS emote_set_contents (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    emote_set_id INTEGER NOT NULL REFERENCES emote_sets(id),
    emote_id INTEGER NOT NULL REFERENCES emotes(id),
    name TEXT,
    added_by INTEGER NOT NULL REFERENCES users(id),
    added_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS acquired_emote_sets (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    user_id INTEGER NOT NULL,
    emote_set_id INTEGER NOT NULL,
    is_default BOOLEAN NOT NULL DEFAULT false
);

CREATE TABLE IF NOT EXISTS ratings (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id),
    emote_id INTEGER NOT NULL REFERENCES emotes(id),
    rate INTEGER NOT NULL,
    rated_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inbox_messages (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    recipient_id INTEGER NOT NULL REFERENCES users(id),
    message_type INTEGER NOT NULL,
    contents TEXT NOT NULL,
    link TEXT,
    sent_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP,
    has_read BOOLEAN NOT NULL DEFAULT false
);

CREATE TABLE IF NOT EXISTS reports (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    sender_id INTEGER NOT NULL REFERENCES users(id),
    contents TEXT NOT NULL,
    sent_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP,
    resolved_by INTEGER REFERENCES users(id),
    response_message TEXT
);

CREATE TABLE IF NOT EXISTS roles (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    priority INTEGER NOT NULL DEFAULT 0,
    name TEXT NOT NULL,
    foreground_color TEXT NOT NULL DEFAULT '000,000,000',
    background_color TEXT NOT NULL DEFAULT 'solid:255,255,255',
    badge_id INTEGER NOT NULL DEFAULT 0,

    -- permissions
    permission_upload BOOLEAN NOT NULL DEFAULT true,
    permission_rate BOOLEAN NOT NULL DEFAULT true,
    permission_emoteset_own BOOLEAN NOT NULL DEFAULT true,
    permission_emoteset_all BOOLEAN NOT NULL DEFAULT false,
    permission_report BOOLEAN NOT NULL DEFAULT true,
    permission_report_review BOOLEAN NOT NULL DEFAULT false,
    permission_approve_emotes BOOLEAN NOT NULL DEFAULT false,
    permission_useredit_own BOOLEAN NOT NULL DEFAULT true,
    permission_useredit_all BOOLEAN NOT NULL DEFAULT false,
    permission_modsystem BOOLEAN NOT NULL DEFAULT false
);

CREATE TABLE IF NOT EXISTS role_assigns(
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    user_id INTEGER NOT NULL UNIQUE REFERENCES users(id),
    role_id INTEGER NOT NULL REFERENCES roles(id)
);