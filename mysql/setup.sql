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
  password_cipher  VARBINARY(512) NOT NULL,   -- ciphertext from AES_ENCRYPT(...)
  iv               BINARY(16) NOT NULL,       -- per-row IV; never reuse across rows
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

-- Ensures only one "current" password per account
CREATE UNIQUE INDEX uq_account_current
  ON passwords ((CASE WHEN is_current = 1 THEN account_ID END));
