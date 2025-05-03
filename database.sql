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

CREATE TABLE IF NOT EXISTS mod_actions(
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id),
    emote_id INTEGER NOT NULL REFERENCES emotes(id),
    verdict INTEGER NOT NULL,
    comment TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP
);

---------------------------
--       INSERTIONS      --
---------------------------

-- CREATING A ROLE FOR USERS
INSERT IGNORE INTO roles(id, name) VALUES (1, 'User');

---------------------------
--       TRIGGERS        --
---------------------------

-- CREATE EMOTESET AND ASSIGN ROLE FOR NEW USER
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS create_user
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO role_assigns(user_id, role_id) VALUES (NEW.id, 1);
    INSERT INTO emote_sets(owner_id, name) VALUES (NEW.id, CONCAT(NEW.username, '''s emoteset'));
END$$
DELIMITER ;

-- NULLIFY EMOTE AUTHORS ON USER DELETION
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS user_deletion
BEFORE DELETE ON users
FOR EACH ROW
BEGIN
    UPDATE emotes SET uploaded_by = NULL WHERE uploaded_by = OLD.id;
    UPDATE emote_set_contents SET added_by = NULL WHERE added_by = OLD.id;
    UPDATE reports SET resolved_by = NULL WHERE resolved_by = OLD.id;
END$$
DELIMITER ;

-- ONLY ONE EMOTESET CAN BE GLOBAL AND FEATURED
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS check_global_and_featured_emote_sets
BEFORE INSERT ON emote_sets
FOR EACH ROW
BEGIN
    IF NEW.is_global = TRUE THEN
        IF (SELECT COUNT(*) FROM emote_sets WHERE is_global = TRUE) > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only one emote_set can have is_global = TRUE.';
        END IF;
    END IF;
    IF NEW.is_featured = TRUE THEN
        IF (SELECT COUNT(*) FROM emote_sets WHERE is_featured = TRUE) > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only one emote_set can have is_featured = TRUE.';
        END IF;
    END IF;
END$$
DELIMITER ;

-- ASSIGN EMOTESET ON CREATION
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS acquire_emote_set
AFTER INSERT ON emote_sets
FOR EACH ROW
BEGIN
    INSERT INTO acquired_emote_sets(user_id, emote_set_id, is_default)
    VALUES (
        NEW.owner_id,
        NEW.id,
        IF (
            (SELECT COUNT(*) FROM emote_sets WHERE owner_id = NEW.owner_id) = 1,
            TRUE,
            FALSE
        )
    );
END$$
DELIMITER ;