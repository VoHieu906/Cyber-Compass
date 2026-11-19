-- A separate database for the backend test suite (phpunit.xml: DB_DATABASE=cybercompass_testing),
-- so running tests never wipes development data.
CREATE DATABASE IF NOT EXISTS `cybercompass_testing` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `cybercompass_testing`.* TO 'cybercompass'@'%';
FLUSH PRIVILEGES;
