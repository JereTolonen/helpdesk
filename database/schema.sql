-- =================================================================
-- OpiskelijaHelpdesk / AI HelpDesk – tietokantarakenne
-- MySQL 8+ / MariaDB 10.6+
--
-- Käyttö:
--   * Docker: ajetaan automaattisesti, kun tietokanta luodaan ensimmäisen kerran.
--   * Käsin:  mysql -u KÄYTTÄJÄ -p TIETOKANTA < database/schema.sql
--             tai phpMyAdmin -> Tuo (valitse ensin tietokanta).
--
-- Tiedostoa voi ajaa uudelleen turvallisesti (IF NOT EXISTS / ON DUPLICATE KEY).
-- =================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';


-- ---------------------------------------------------------------
-- users
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'support', 'admin') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
-- categories
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
-- tickets
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,

    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,

    category_id INT NULL,

    status ENUM(
        'Uusi',
        'Käsittelyssä',
        'Odottaa opiskelijaa',
        'Ratkaistu',
        'Suljettu'
    ) NOT NULL DEFAULT 'Uusi',

    priority ENUM(
        'Matala',
        'Normaali',
        'Korkea',
        'Kiireellinen'
    ) NOT NULL DEFAULT 'Normaali',

    created_by INT NOT NULL,
    assigned_to INT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_tickets_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_tickets_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_tickets_assigned_to
        FOREIGN KEY (assigned_to)
        REFERENCES users(id)
        ON DELETE SET NULL,

    INDEX idx_tickets_status (status),
    INDEX idx_tickets_priority (priority),
    INDEX idx_tickets_created_by (created_by),
    INDEX idx_tickets_category (category_id),

    FULLTEXT INDEX ft_tickets_title (title)

) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
-- comments (tiketin kommentit)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,

    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_comments_ticket
        FOREIGN KEY (ticket_id)
        REFERENCES tickets(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_comments_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    INDEX idx_comments_ticket (ticket_id)

) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
-- chat_conversations (AI-chatin keskustelut)
--
-- status:
--   diagnosing        AI kerää tietoja tarkentavilla kysymyksillä
--   solution_offered  AI on antanut ratkaisuehdotuksen
--   resolved          käyttäjä vahvisti, että ongelma ratkesi
--   escalated         keskustelusta luotiin tukipyyntö (ticket_id)
--
-- summary/category_id/priority = AI:n viimeisin diagnoosi, käytetään
-- tukipyynnön pohjana.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL DEFAULT 'Uusi keskustelu',

    status ENUM(
        'diagnosing',
        'solution_offered',
        'resolved',
        'escalated'
    ) NOT NULL DEFAULT 'diagnosing',

    summary TEXT NULL,
    category_id INT NULL,
    priority ENUM('Matala', 'Normaali', 'Korkea', 'Kiireellinen') NOT NULL DEFAULT 'Normaali',

    ticket_id INT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_conversations_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_conversations_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_conversations_ticket
        FOREIGN KEY (ticket_id)
        REFERENCES tickets(id)
        ON DELETE SET NULL,

    INDEX idx_conversations_user (user_id, updated_at),
    INDEX idx_conversations_status (status),
    INDEX idx_conversations_ticket (ticket_id)

) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
-- messages (AI-chatin viestit)
--
-- role:    user = käyttäjä, assistant = AI, system = järjestelmäilmoitus
-- meta:    JSON, esim. {"phase":"question","sources":["MySQL – Palvelin ei käynnisty"]}
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,

    conversation_id INT NOT NULL,
    role ENUM('user', 'assistant', 'system') NOT NULL,
    content TEXT NOT NULL,
    meta TEXT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_messages_conversation
        FOREIGN KEY (conversation_id)
        REFERENCES chat_conversations(id)
        ON DELETE CASCADE,

    INDEX idx_messages_conversation (conversation_id, id)

) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
-- Peruskategoriat (AI luokittelee keskustelut näihin)
-- ---------------------------------------------------------------
INSERT INTO categories (name, description) VALUES
    ('Verkko-ongelma', 'WLAN, VPN ja verkkoyhteysongelmat'),
    ('Tunnukset', 'Salasanat, kirjautuminen, käyttäjätunnukset'),
    ('Laitteisto', 'Tietokoneet, tulostimet, muut laitteet'),
    ('Ohjelmisto', 'Sovellusten asennus- ja käyttöongelmat'),
    ('Sähköposti', 'Outlook ja sähköpostin ongelmat'),
    ('Linux-palvelimet', 'Linux-palvelimet, SSH ja palvelut'),
    ('GitHub', 'Git, GitHub ja versionhallinta'),
    ('Tietokannat', 'MySQL, MariaDB ja SQL-virheet'),
    ('Ohjelmointi', 'Ohjelmointiin liittyvät kysymykset'),
    ('Muu', 'Muut tukipyynnöt')
ON DUPLICATE KEY UPDATE
    description = VALUES(description);


-- ---------------------------------------------------------------
-- Oletus-ylläpitäjä
-- Salasana: Admin1234!
-- VAIHDA HETI TUOTANNOSSA
-- ---------------------------------------------------------------
INSERT INTO users (name, email, password, role) VALUES
(
    'Pääkäyttäjä',
    'admin@example.com',
    '$2y$10$3U8guNp/CxAp6Fpe95faIuN/lUQ0gMEJNarl89zuA/FExJJSFcRQS',
    'admin'
)
ON DUPLICATE KEY UPDATE
    email = email;


-- ---------------------------------------------------------------
-- Demotunnukset testaukseen (poista tuotannossa)
--   opiskelija@example.com / Opiskelija123!
--   tuki@example.com       / Tuki1234!
-- ---------------------------------------------------------------
INSERT INTO users (name, email, password, role) VALUES
(
    'Olli Opiskelija',
    'opiskelija@example.com',
    '$2y$10$wrAukOVzitWP0b9bp5Hfqe/mA0yz1UMo7zv9MqfGauweL5FFPBaQq',
    'student'
),
(
    'Tiina Tukihenkilö',
    'tuki@example.com',
    '$2y$10$eiJmsLHIT3AkEvW5LNLdjuJ0U8t8JXz6nTn1Ug4RPbjVDZRBPZrSS',
    'support'
)
ON DUPLICATE KEY UPDATE
    email = email;
