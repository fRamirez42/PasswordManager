DROP DATABASE IF EXISTS student_passwords;
CREATE DATABASE student_passwords DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

CREATE USER IF NOT EXISTS 'passwords_user'@'localhost' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON student_passwords.* TO 'passwords_user'@'localhost';
FLUSH PRIVILEGES;

USE student_passwords;

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
  password_cipher  VARBINARY(512) NOT NULL,
  iv               BINARY(16) NOT NULL,
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

CREATE UNIQUE INDEX uq_account_current
  ON passwords ((CASE WHEN is_current = 1 THEN account_ID END));

/* ---------- Seed data (uses the same passphrase as PHP) ---------- */
SET @k = 'GoldensRule123';

/* Sites */
INSERT INTO sites (url) VALUES
('http://facebook.com'),
('http://steam.com'),
('https://github.com'),
('https://hartford.edu'),
('https://mail.google.com'),
('https://youtube.com')
ON DUPLICATE KEY UPDATE url = VALUES(url);

/* Accounts */
INSERT INTO accounts (site_ID, email, username)
SELECT s.site_ID, 'feliperam1990@gmail.com', 'feliper44' FROM sites s WHERE s.url='http://facebook.com'
UNION ALL
SELECT s.site_ID, 'theshowman@gmail.com', 'TheShowManIsHere!' FROM sites s WHERE s.url='http://steam.com'
UNION ALL
SELECT s.site_ID, 'felipeprofessional@gmail.com', 'felipe24' FROM sites s WHERE s.url='https://github.com'
UNION ALL
SELECT s.site_ID, 'jdoe@hartford.edu', 'jdoe' FROM sites s WHERE s.url='https://hartford.edu'
UNION ALL
SELECT s.site_ID, 'theshowman@gmail.com', 'theshowman' FROM sites s WHERE s.url='https://mail.google.com'
UNION ALL
SELECT s.site_ID, 'pipesuper24@gmail.com', 'NextBigThing99+' FROM sites s WHERE s.url='https://youtube.com'
ON DUPLICATE KEY UPDATE email = VALUES(email), username = VALUES(username);

/* Passwords (per-row IV; ensure at most one current per account) */
-- Facebook
SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
SELECT a.account_ID,
       AES_ENCRYPT('theamericancman', UNHEX(SHA2(@k,256)), @iv),
       @iv, '2022-02-27 00:00:00', NULL, 1
FROM accounts a JOIN sites s ON s.site_ID=a.site_ID
WHERE s.url='http://facebook.com' AND a.email='feliperam1990@gmail.com';

-- Steam
SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
SELECT a.account_ID,
       AES_ENCRYPT('LuigiDaGoatBMinor', UNHEX(SHA2(@k,256)), @iv),
       @iv, '2020-03-15 00:00:00', NULL, 1
FROM accounts a JOIN sites s ON s.site_ID=a.site_ID
WHERE s.url='http://steam.com' AND a.email='theshowman@gmail.com';

-- GitHub
SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
SELECT a.account_ID,
       AES_ENCRYPT('felipe24', UNHEX(SHA2(@k,256)), @iv),
       @iv, '2017-01-31 00:00:00', NULL, 1
FROM accounts a JOIN sites s ON s.site_ID=a.site_ID
WHERE s.url='https://github.com' AND a.email='felipeprofessional@gmail.com';

-- Hartford
SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
SELECT a.account_ID,
       AES_ENCRYPT('jdoe', UNHEX(SHA2(@k,256)), @iv),
       @iv, '2014-11-08 00:00:00', 'It is!', 1
FROM accounts a JOIN sites s ON s.site_ID=a.site_ID
WHERE s.url='https://hartford.edu' AND a.email='jdoe@hartford.edu';

-- Gmail
SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
SELECT a.account_ID,
       AES_ENCRYPT('theshowman', UNHEX(SHA2(@k,256)), @iv),
       @iv, '2025-10-06 00:00:00', NULL, 1
FROM accounts a JOIN sites s ON s.site_ID=a.site_ID
WHERE s.url='https://mail.google.com' AND a.email='theshowman@gmail.com';

-- YouTube
SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, time_of_creation, comment, is_current)
SELECT a.account_ID,
       AES_ENCRYPT('FoxField99', UNHEX(SHA2(@k,256)), @iv),
       @iv, '2018-09-30 00:00:00', NULL, 1
FROM accounts a JOIN sites s ON s.site_ID=a.site_ID
WHERE s.url='https://youtube.com' AND a.email='pipesuper24@gmail.com';
