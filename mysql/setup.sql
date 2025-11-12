-- Fresh DB
DROP DATABASE IF EXISTS students_passwords;
CREATE DATABASE students_passwords DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE students_passwords;

-- =========================
-- Tables
-- =========================
CREATE TABLE IF NOT EXISTS sites (
  site_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  url     VARCHAR(512) NOT NULL,
  PRIMARY KEY (site_ID),
  UNIQUE KEY uq_site_url (url)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS accounts (
  account_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_ID    INT UNSIGNED NOT NULL,
  email      VARCHAR(256) NOT NULL,
  username   VARCHAR(256) NOT NULL,
  PRIMARY KEY (account_ID),
  UNIQUE KEY uq_site_email_username (site_ID, email, username),
  KEY ix_accounts_site (site_ID),
  CONSTRAINT fk_accounts_site
    FOREIGN KEY (site_ID) REFERENCES sites(site_ID)
    ON DELETE CASCADE,
  CONSTRAINT chk_identity_nonempty CHECK (email <> '' OR username <> '')
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS passwords (
  pass_ID          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_ID       INT UNSIGNED NOT NULL,
  password_cipher  VARBINARY(512) NOT NULL,   -- AES_ENCRYPT(...) goes here
  iv               BINARY(16) NOT NULL,       -- per-row IV (RANDOM_BYTES(16))
  key_version      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  time_of_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  comment          VARCHAR(256) NULL,
  is_current       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (pass_ID),
  KEY ix_passwords_account_time (account_ID, time_of_creation),
  CONSTRAINT fk_passwords_account
    FOREIGN KEY (account_ID) REFERENCES accounts(account_ID)
    ON DELETE CASCADE,
  CONSTRAINT chk_is_current CHECK (is_current IN (0,1))
) ENGINE=InnoDB;

-- Enforce only one "current" password per account (MySQL 8.0.13+)
CREATE UNIQUE INDEX uq_account_current
  ON passwords ((CASE WHEN is_current = 1 THEN account_ID END));

-- =========================
-- Seed data: sites, accounts
-- =========================
INSERT INTO sites (site_ID, url) VALUES
  (1, 'https://mail.google.com'),
  (2, 'http://facebook.com'),
  (3, 'https://github.com'),
  (4, 'https://youtube.com'),
  (5, 'http://steam.com'),
  (6, 'https://hartford.edu');

INSERT INTO accounts (site_ID, email, username) VALUES
  (1, 'theshowman@gmail.com',        'theshowman'),       -- account_ID = 1
  (1, 'feliperam1990@gmail.com',     'feliperam'),        -- account_ID = 2
  (2, 'feliperam1990@gmail.com',     'feliper44'),        -- account_ID = 3
  (3, 'felipeprofessional@gmail.com','felipe24'),         -- account_ID = 4
  (4, 'pipesuper24@gmail.com',       'NextBigThing99+'),  -- account_ID = 5
  (5, 'feliperam1990@gmail.com',     'WeBall1234'),       -- account_ID = 6
  (5, 'theshowman@gmail.com',        'TheShowManIsHere!'),-- account_ID = 7
  (6, 'jdoe@hartford.edu',           'jdoe');             -- account_ID = 8

-- =========================
-- AES session setup
-- =========================
SET block_encryption_mode = 'aes-256-cbc';
-- 32-byte key for AES-256 (in practice, load from your app/secret manager)
SET @k = UNHEX(SHA2('the dog in the field', 256));

-- =========================
-- Password inserts (per-row IVs)
-- =========================
-- account 1
SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
VALUES (1, AES_ENCRYPT('HughJackman1234', @k, @iv), @iv, '2025-10-06', NULL, 1);

-- account 2 (history + current)
SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
VALUES (2, AES_ENCRYPT('SuperMario123', @k, @iv), @iv, '2010-06-24', 'Childish, and forgot', 0);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
VALUES (2, AES_ENCRYPT('ProfessionalPassword26$', @k, @iv), @iv, '2022-02-27', NULL, 1);

-- account 3 (history + current)
SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
VALUES (3, AES_ENCRYPT('GoldenRetriver56!', @k, @iv), @iv, '2012-04-29', 'Got Hacked', 0);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
VALUES (3, AES_ENCRYPT('ThaWorldo98&', @k, @iv), @iv, '2017-01-31', NULL, 1);

-- account 4
SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
VALUES (4, AES_ENCRYPT('FelipeRamirez9900*', @k, @iv), @iv, '2018-09-30', NULL, 1);

-- account 5 (two old, one current)
SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
VALUES (5, AES_ENCRYPT('DaBoss67', @k, @iv), @iv, '2014-11-08', 'Forgot it', 0);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
VALUES (5, AES_ENCRYPT('RemeberThisTime11#', @k, @iv), @iv, '2017-12-20', 'Forgot it again', 0);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
VALUES (5, AES_ENCRYPT('DogGolden420@', @k, @iv), @iv, '2020-03-15', NULL, 1);

-- account 6 (history + current)
SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
VALUES (6, AES_ENCRYPT('ShowmanshipIsKey12', @k, @iv), @iv, '2014-11-08', 'It is!', 0);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
VALUES (6, AES_ENCRYPT('Dexter8877%', @k, @iv), @iv, '2023-08-20', NULL, 1);

-- account 7
SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
VALUES (7, AES_ENCRYPT('IAmHere@', @k, @iv), @iv, '2022-08-20', NULL, 1);

-- account 8
SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
VALUES (8, AES_ENCRYPT('SchoolAppropriate78@', @k, @iv), @iv, '2020-12-31', NULL, 1);
