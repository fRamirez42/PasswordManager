DROP DATABASE IF EXISTS passwords;
CREATE DATABASE passwords DEFAULT CHARACTER SET utf8mb4;
USE passwords;

SET block_encryption_mode = 'aes-256-cbc';
SET @key_str = UNHEX(SHA2('the dog in the field', 512));

CREATE TABLE IF NOT EXISTS sites (
  site_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  url     VARCHAR(512) NOT NULL,
  PRIMARY KEY (site_ID),
  UNIQUE KEY unique_site_url (url)
);

CREATE TABLE IF NOT EXISTS accounts (
  account_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_ID    INT UNSIGNED NOT NULL,
  email      VARCHAR(256) NOT NULL,
  username   VARCHAR(256) NOT NULL,
  PRIMARY KEY (account_ID)
);

CREATE TABLE IF NOT EXISTS passwords_data (
  pass_ID          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  account_ID       INT UNSIGNED   NOT NULL,
  password         VARBINARY(256) NOT NULL,
  time_of_creation DATE           NOT NULL DEFAULT (CURRENT_DATE),
  comment          VARCHAR(256)   NULL,
  is_current       TINYINT(1)     NOT NULL,
  PRIMARY KEY (pass_ID)
);

CREATE TABLE password_iv_store (
  pass_ID   INT UNSIGNED NOT NULL,
  iv_value  VARBINARY(16) NOT NULL,
  PRIMARY KEY (pass_ID),
  CONSTRAINT fk_iv_pass
    FOREIGN KEY (pass_ID)
    REFERENCES passwords_data(pass_ID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

INSERT INTO sites (site_ID, url) VALUES
  (1, 'https://mail.google.com'),
  (2, 'http://facebook.com'),
  (3, 'https://github.com'),
  (4, 'https://youtube.com'),
  (5, 'http://steam.com'),
  (6, 'https://hartford.edu');

INSERT INTO accounts (site_ID, email, username) VALUES
  (1, 'theshowman@gmail.com', 'theshowman'),
  (1, 'feliperam1990@gmail.com', 'feliperam'),
  (2, 'feliperam1990@gmail.com', 'feliper44'),
  (3, 'felipeprofessional@gmail.com', 'felipe24'),
  (4, 'pipesuper24@gmail.com', 'NextBigThing99+'),
  (5, 'feliperam1990@gmail.com', 'WeBall1234'),
  (5, 'theshowman@gmail.com', 'TheShowManIsHere!'),
  (6, 'jdoe@hartford.edu', 'jdoe');

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords_data (account_ID, password, time_of_creation, comment, is_current)
  VALUES (1, AES_ENCRYPT('HughJackman1234', @key_str, @iv), '2025-10-06', NULL, 1);
INSERT INTO password_iv_store (pass_ID, iv_value) VALUES (LAST_INSERT_ID(), @iv);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords_data (account_ID, password, time_of_creation, comment, is_current)
  VALUES (2, AES_ENCRYPT('SuperMario123', @key_str, @iv), '2010-06-24', 'Childish, and forgot', 0);
INSERT INTO password_iv_store (pass_ID, iv_value) VALUES (LAST_INSERT_ID(), @iv);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords_data (account_ID, password, time_of_creation, comment, is_current)
  VALUES (2, AES_ENCRYPT('ProfessionalPassword26$', @key_str, @iv), '2022-02-27', NULL, 1);
INSERT INTO password_iv_store (pass_ID, iv_value) VALUES (LAST_INSERT_ID(), @iv);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords_data (account_ID, password, time_of_creation, comment, is_current)
  VALUES (3, AES_ENCRYPT('GoldenRetriver56!', @key_str, @iv), '2012-04-29', 'Got Hacked', 0);
INSERT INTO password_iv_store (pass_ID, iv_value) VALUES (LAST_INSERT_ID(), @iv);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords_data (account_ID, password, time_of_creation, comment, is_current)
  VALUES (3, AES_ENCRYPT('ThaWorldo98&', @key_str, @iv), '2017-01-31', NULL, 1);
INSERT INTO password_iv_store (pass_ID, iv_value) VALUES (LAST_INSERT_ID(), @iv);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords_data (account_ID, password, time_of_creation, comment, is_current)
  VALUES (4, AES_ENCRYPT('FelipeRamirez9900*', @key_str, @iv), '2018-09-30', NULL, 1);
INSERT INTO password_iv_store (pass_ID, iv_value) VALUES (LAST_INSERT_ID(), @iv);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords_data (account_ID, password, time_of_creation, comment, is_current)
  VALUES (5, AES_ENCRYPT('DaBoss67', @key_str, @iv), '2014-11-08', 'Forgot it', 0);
INSERT INTO password_iv_store (pass_ID, iv_value) VALUES (LAST_INSERT_ID(), @iv);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords_data (account_ID, password, time_of_creation, comment, is_current)
  VALUES (5, AES_ENCRYPT('RemeberThisTime11#', @key_str, @iv), '2017-12-20', 'Forgot it again', 0);
INSERT INTO password_iv_store (pass_ID, iv_value) VALUES (LAST_INSERT_ID(), @iv);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords_data (account_ID, password, time_of_creation, comment, is_current)
  VALUES (5, AES_ENCRYPT('DogGolden420@', @key_str, @iv), '2020-03-15', NULL, 0);
INSERT INTO password_iv_store (pass_ID, iv_value) VALUES (LAST_INSERT_ID(), @iv);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords_data (account_ID, password, time_of_creation, comment, is_current)
  VALUES (6, AES_ENCRYPT('ShowmanshipIsKey12', @key_str, @iv), '2014-11-08', 'It is!', 1);
INSERT INTO password_iv_store (pass_ID, iv_value) VALUES (LAST_INSERT_ID(), @iv);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords_data (account_ID, password, time_of_creation, comment, is_current)
  VALUES (6, AES_ENCRYPT('Dexter8877%', @key_str, @iv), '2023-08-20', NULL, 1);
INSERT INTO password_iv_store (pass_ID, iv_value) VALUES (LAST_INSERT_ID(), @iv);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords_data (account_ID, password, time_of_creation, comment, is_current)
  VALUES (7, AES_ENCRYPT('IAmHere@', @key_str, @iv), '2022-08-20', NULL, 1);
INSERT INTO password_iv_store (pass_ID, iv_value) VALUES (LAST_INSERT_ID(), @iv);

SET @iv = RANDOM_BYTES(16);
INSERT INTO passwords_data (account_ID, password, time_of_creation, comment, is_current)
  VALUES (8, AES_ENCRYPT('SchoolAppropriate78@', @key_str, @iv), '2020-12-31', NULL, 1);
INSERT INTO password_iv_store (pass_ID, iv_value) VALUES (LAST_INSERT_ID(), @iv);
