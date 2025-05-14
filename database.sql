CREATE TABLE IF NOT EXISTS users (
    id CHAR(32) NOT NULL PRIMARY KEY DEFAULT REPLACE(UUID(), '-', ''),
    username TEXT NOT NULL UNIQUE,
    password TEXT,
    secret_key TEXT NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP,
    last_active_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_preferences (
    id CHAR(32) NOT NULL PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    private_profile BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS connections (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    user_id CHAR(32) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    alias_id TEXT NOT NULL,
    platform TEXT NOT NULL,
    data TEXT NOT NULL,
    connected_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS emotes (
    id CHAR(32) NOT NULL PRIMARY KEY DEFAULT REPLACE(UUID(),'-',''),
    code TEXT NOT NULL,
    notes TEXT,
    uploaded_by CHAR(32) REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP,
    visibility INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS emote_sets (
    id CHAR(32) NOT NULL PRIMARY KEY DEFAULT REPLACE(UUID(),'-',''),
    owner_id CHAR(32) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    is_global BOOLEAN NOT NULL DEFAULT false,
    is_featured BOOLEAN NOT NULL DEFAULT false
);

CREATE TABLE IF NOT EXISTS emote_set_contents (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    emote_set_id CHAR(32) NOT NULL REFERENCES emote_sets(id) ON DELETE CASCADE,
    emote_id CHAR(32) NOT NULL REFERENCES emotes(id) ON DELETE CASCADE,
    code TEXT,
    added_by CHAR(32) REFERENCES users(id),
    added_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS acquired_emote_sets (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    user_id CHAR(32) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    emote_set_id CHAR(32) NOT NULL REFERENCES emote_sets(id) ON DELETE CASCADE,
    is_default BOOLEAN NOT NULL DEFAULT false
);

CREATE TABLE IF NOT EXISTS ratings (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    user_id CHAR(32) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    emote_id CHAR(32) NOT NULL REFERENCES emotes(id) ON DELETE CASCADE,
    rate INTEGER NOT NULL,
    rated_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inbox_messages (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    recipient_id CHAR(32) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message_type INTEGER NOT NULL,
    contents TEXT NOT NULL,
    link TEXT,
    sent_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP,
    has_read BOOLEAN NOT NULL DEFAULT false
);

CREATE TABLE IF NOT EXISTS reports (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    sender_id CHAR(32) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    contents TEXT NOT NULL,
    sent_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP,
    resolved_by CHAR(32) REFERENCES users(id),
    response_message TEXT
);

CREATE TABLE IF NOT EXISTS badges (
    id CHAR(32) NOT NULL PRIMARY KEY DEFAULT REPLACE(UUID(),'-',''),
    uploaded_by CHAR(32) REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_badges (
    id CHAR(32) NOT NULL PRIMARY KEY DEFAULT REPLACE(UUID(),'-',''),
    user_id CHAR(32) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    badge_id CHAR(32) NOT NULL REFERENCES badges(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS roles (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    priority INTEGER NOT NULL DEFAULT 0,
    name TEXT NOT NULL,
    foreground_color TEXT NOT NULL DEFAULT '000,000,000',
    background_color TEXT NOT NULL DEFAULT 'solid:255,255,255',
    badge_id CHAR(32) REFERENCES badges(id),

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
    user_id CHAR(32) NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    role_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS mod_actions(
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    user_id CHAR(32) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    emote_id CHAR(32) NOT NULL REFERENCES emotes(id) ON DELETE CASCADE,
    verdict INTEGER NOT NULL,
    comment TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS actions (
    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
    user_id CHAR(32) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    action_type TEXT NOT NULL,
    action_payload TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP
);

-- -------------------------
--       ALTERS      
-- -------------------------

ALTER TABLE emotes ADD COLUMN IF NOT EXISTS source TEXT;

-- -------------------------
--       INSERTIONS      
-- -------------------------

-- CREATING A ROLE FOR USERS
INSERT IGNORE INTO roles(id, name) VALUES (1, 'User');

INSERT IGNORE INTO user_preferences(id) SELECT id FROM users;

-- -------------------------
--       TRIGGERS        
-- -------------------------

DROP TRIGGER IF EXISTS create_user;

-- CREATE EMOTESET AND ASSIGN ROLE FOR NEW USER
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS create_user
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO user_preferences(id) VALUES (NEW.id);
    INSERT INTO role_assigns(user_id, role_id) VALUES (NEW.id, 1);
    INSERT INTO emote_sets(owner_id, name) VALUES (NEW.id, CONCAT(NEW.username, '''s emoteset'));
END$$
DELIMITER ;

-- NULLIFY EMOTE AUTHORS ON USER DELETION
DROP TRIGGER IF EXISTS user_deletion;

DELIMITER $$
CREATE TRIGGER IF NOT EXISTS user_deletion
BEFORE DELETE ON users
FOR EACH ROW
BEGIN
    UPDATE emotes SET uploaded_by = NULL WHERE uploaded_by = OLD.id;
    UPDATE emote_set_contents SET added_by = NULL WHERE added_by = OLD.id;
    UPDATE reports SET resolved_by = NULL WHERE resolved_by = OLD.id;
    UPDATE badges SET uploaded_by = NULL WHERE uploaded_by = OLD.id;
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