-- Fresh DB and user
DROP DATABASE IF EXISTS student_passwords;
CREATE DATABASE student_passwords DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

CREATE USER IF NOT EXISTS 'passwords_user'@'localhost' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON student_passwords.* TO 'passwords_user'@'localhost';
FLUSH PRIVILEGES;

USE student_passwords;

-- Tables
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
  password_cipher  VARBINARY(512) NOT NULL,  -- AES ciphertext
  iv               BINARY(16) NOT NULL,      -- per-row IV
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

-- At most one current password per account
CREATE UNIQUE INDEX uq_account_current
  ON passwords ((CASE WHEN is_current = 1 THEN account_ID END));

/* -------------------- Seed data -------------------- */

-- Sites
INSERT INTO sites (url) VALUES
  ('https://mail.google.com'),
  ('http://facebook.com'),
  ('https://github.com'),
  ('https://youtube.com'),
  ('http://steam.com'),
  ('https://hartford.edu');

-- Accounts (use site lookups to get site_ID)
INSERT INTO accounts (site_ID, email, username)
SELECT site_ID, 'theshowman@gmail.com', 'theshowman' FROM sites WHERE url='https://mail.google.com';
INSERT INTO accounts (site_ID, email, username)
SELECT site_ID, 'feliperam1990@gmail.com', 'feliperam' FROM sites WHERE url='https://mail.google.com';

INSERT INTO accounts (site_ID, email, username)
SELECT site_ID, 'feliperam1990@gmail.com', 'feliper44' FROM sites WHERE url='http://facebook.com';

INSERT INTO accounts (site_ID, email, username)
SELECT site_ID, 'felipeprofessional@gmail.com', 'felipe24' FROM sites WHERE url='https://github.com';

INSERT INTO accounts (site_ID, email, username)
SELECT site_ID, 'pipesuper24@gmail.com', 'NextBigThing99+' FROM sites WHERE url='https://youtube.com';

INSERT INTO accounts (site_ID, email, username)
SELECT site_ID, 'feliperam1990@gmail.com', 'WeBall1234' FROM sites WHERE url='http://steam.com';
INSERT INTO accounts (site_ID, email, username)
SELECT site_ID, 'theshowman@gmail.com', 'TheShowManIsHere!' FROM sites WHERE url='http://steam.com';

INSERT INTO accounts (site_ID, email, username)
SELECT site_ID, 'jdoe@hartford.edu', 'jdoe' FROM sites WHERE url='https://hartford.edu';

-- AES passphrase for this seed
SET @k := 'student_passwords_demo_key';

-- Helper to insert one password row by account lookup
-- (Each block sets a fresh IV so every row has a unique IV)
SET @iv := RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, comment, is_current, time_of_creation)
SELECT a.account_ID,
       AES_ENCRYPT('HughJackman1234', UNHEX(SHA2(@k,256)), @iv),
       @iv,
       NULL,
       1,
       '2025-10-06'
FROM accounts a
JOIN sites s ON s.site_ID = a.site_ID
WHERE s.url='https://mail.google.com' AND a.email='theshowman@gmail.com' AND a.username='theshowman';

SET @iv := RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, comment, is_current, time_of_creation)
SELECT a.account_ID,
       AES_ENCRYPT('SuperMario123', UNHEX(SHA2(@k,256)), @iv),
       @iv,
       'Childish, and forgot',
       0,
       '2010-06-24'
FROM accounts a
JOIN sites s ON s.site_ID = a.site_ID
WHERE s.url='https://mail.google.com' AND a.email='feliperam1990@gmail.com' AND a.username='feliperam';

SET @iv := RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, comment, is_current, time_of_creation)
SELECT a.account_ID,
       AES_ENCRYPT('ProfessionalPassword26$', UNHEX(SHA2(@k,256)), @iv),
       @iv,
       NULL,
       1,
       '2022-02-27'
FROM accounts a
JOIN sites s ON s.site_ID = a.site_ID
WHERE s.url='http://facebook.com' AND a.email='feliperam1990@gmail.com' AND a.username='feliper44';

SET @iv := RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, comment, is_current, time_of_creation)
SELECT a.account_ID,
       AES_ENCRYPT('GoldenRetriver56!', UNHEX(SHA2(@k,256)), @iv),
       @iv,
       'Got Hacked',
       0,
       '2012-04-29'
FROM accounts a
JOIN sites s ON s.site_ID = a.site_ID
WHERE s.url='https://github.com' AND a.email='felipeprofessional@gmail.com' AND a.username='felipe24';

SET @iv := RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, comment, is_current, time_of_creation)
SELECT a.account_ID,
       AES_ENCRYPT('ThaWorldo98&', UNHEX(SHA2(@k,256)), @iv),
       @iv,
       NULL,
       1,
       '2017-01-31'
FROM accounts a
JOIN sites s ON s.site_ID = a.site_ID
WHERE s.url='https://github.com' AND a.email='felipeprofessional@gmail.com' AND a.username='felipe24';

SET @iv := RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, comment, is_current, time_of_creation)
SELECT a.account_ID,
       AES_ENCRYPT('FelipeRamirez9900*', UNHEX(SHA2(@k,256)), @iv),
       @iv,
       NULL,
       1,
       '2018-09-30'
FROM accounts a
JOIN sites s ON s.site_ID = a.site_ID
WHERE s.url='https://youtube.com' AND a.email='pipesuper24@gmail.com' AND a.username='NextBigThing99+';

SET @iv := RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, comment, is_current, time_of_creation)
SELECT a.account_ID,
       AES_ENCRYPT('DaBoss67', UNHEX(SHA2(@k,256)), @iv),
       @iv,
       'Forgot it',
       0,
       '2014-11-08'
FROM accounts a
JOIN sites s ON s.site_ID = a.site_ID
WHERE s.url='http://steam.com' AND a.email='feliperam1990@gmail.com' AND a.username='WeBall1234';

SET @iv := RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, comment, is_current, time_of_creation)
SELECT a.account_ID,
       AES_ENCRYPT('RemeberThisTime11#', UNHEX(SHA2(@k,256)), @iv),
       @iv,
       'Forgot it again',
       0,
       '2017-12-20'
FROM accounts a
JOIN sites s ON s.site_ID = a.site_ID
WHERE s.url='http://steam.com' AND a.email='feliperam1990@gmail.com' AND a.username='WeBall1234';

SET @iv := RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, comment, is_current, time_of_creation)
SELECT a.account_ID,
       AES_ENCRYPT('DogGolden420@', UNHEX(SHA2(@k,256)), @iv),
       @iv,
       NULL,
       1,
       '2020-03-15'
FROM accounts a
JOIN sites s ON s.site_ID = a.site_ID
WHERE s.url='http://steam.com' AND a.email='theshowman@gmail.com' AND a.username='TheShowManIsHere!';

SET @iv := RANDOM_BYTES(16);
INSERT INTO passwords (account_ID, password_cipher, iv, comment, is_current, time_of_creation)
SELECT a.account_ID,
       AES_ENCRYPT('ShowmanshipIsKey12', UNHEX(SHA2(@k,256)), @iv),
       @iv,
       'It is!',
       1,
       '2014-11-08'
FROM accounts a
JOIN sites s ON s.site_ID = a.site_ID
WHERE s.url='https://hartford.edu' AND a.email='jdoe@hartford.edu' AND a.username='jdoe';
