-- phpMyAdmin SQL Dump
-- version 4.5.4.1deb2ubuntu2
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Erstellungszeit: 25. Dez 2019 um 23:22
-- Server-Version: 5.7.23-0ubuntu0.16.04.1
-- PHP-Version: 7.0.30-0ubuntu0.16.04.1

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `comments`, `cryptokeys`, `domainmetadata`, `domains`, `options`, `permissions`, `records`, `remote`, `supermasters`, `tsigkeys`, `users`;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `pdnsnew`
--

-- --------------------------------------------------------

CREATE TABLE domains (
  id                    INT AUTO_INCREMENT,
  name                  VARCHAR(255) NOT NULL,
  master                VARCHAR(128) DEFAULT NULL,
  last_check            INT DEFAULT NULL,
  type                  VARCHAR(6) NOT NULL,
  notified_serial       INT UNSIGNED DEFAULT NULL,
  account               VARCHAR(40) CHARACTER SET 'utf8' DEFAULT NULL,
  PRIMARY KEY (id)
) Engine=InnoDB CHARACTER SET 'latin1';

CREATE UNIQUE INDEX name_index ON domains(name);


CREATE TABLE records (
  id                    BIGINT AUTO_INCREMENT,
  domain_id             INT DEFAULT NULL,
  name                  VARCHAR(255) DEFAULT NULL,
  type                  VARCHAR(10) DEFAULT NULL,
  content               VARCHAR(64000) DEFAULT NULL,
  ttl                   INT DEFAULT NULL,
  prio                  INT DEFAULT NULL,
  disabled              TINYINT(1) DEFAULT 0,
  ordername             VARCHAR(255) BINARY DEFAULT NULL,
  auth                  TINYINT(1) DEFAULT 1,
  PRIMARY KEY (id)
) Engine=InnoDB CHARACTER SET 'latin1';

CREATE INDEX nametype_index ON records(name,type);
CREATE INDEX domain_id ON records(domain_id);
CREATE INDEX ordername ON records (ordername);


CREATE TABLE supermasters (
  ip                    VARCHAR(64) NOT NULL,
  nameserver            VARCHAR(255) NOT NULL,
  account               VARCHAR(40) CHARACTER SET 'utf8' NOT NULL,
  PRIMARY KEY (ip, nameserver)
) Engine=InnoDB CHARACTER SET 'latin1';


CREATE TABLE comments (
  id                    INT AUTO_INCREMENT,
  domain_id             INT NOT NULL,
  name                  VARCHAR(255) NOT NULL,
  type                  VARCHAR(10) NOT NULL,
  modified_at           INT NOT NULL,
  account               VARCHAR(40) CHARACTER SET 'utf8' DEFAULT NULL,
  comment               TEXT CHARACTER SET 'utf8' NOT NULL,
  PRIMARY KEY (id)
) Engine=InnoDB CHARACTER SET 'latin1';

CREATE INDEX comments_name_type_idx ON comments (name, type);
CREATE INDEX comments_order_idx ON comments (domain_id, modified_at);


CREATE TABLE domainmetadata (
  id                    INT AUTO_INCREMENT,
  domain_id             INT NOT NULL,
  kind                  VARCHAR(32),
  content               TEXT,
  PRIMARY KEY (id)
) Engine=InnoDB CHARACTER SET 'latin1';

CREATE INDEX domainmetadata_idx ON domainmetadata (domain_id, kind);


CREATE TABLE cryptokeys (
  id                    INT AUTO_INCREMENT,
  domain_id             INT NOT NULL,
  flags                 INT NOT NULL,
  active                BOOL,
  published             BOOL DEFAULT 1,
  content               TEXT,
  PRIMARY KEY(id)
) Engine=InnoDB CHARACTER SET 'latin1';

CREATE INDEX domainidindex ON cryptokeys(domain_id);


CREATE TABLE tsigkeys (
  id                    INT AUTO_INCREMENT,
  name                  VARCHAR(255),
  algorithm             VARCHAR(50),
  secret                VARCHAR(255),
  PRIMARY KEY (id)
) Engine=InnoDB CHARACTER SET 'latin1';

CREATE UNIQUE INDEX namealgoindex ON tsigkeys(name, algorithm);

ALTER TABLE records ADD CONSTRAINT `records_domain_id_ibfk` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE comments ADD CONSTRAINT `comments_domain_id_ibfk` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE domainmetadata ADD CONSTRAINT `domainmetadata_domain_id_ibfk` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE cryptokeys ADD CONSTRAINT `cryptokeys_domain_id_ibfk` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- --------------------------------------------------------

--
-- Daten für Tabelle `domains`
--

INSERT INTO `domains` (`id`, `name`, `master`, `last_check`, `type`, `notified_serial`, `account`) VALUES
(1, 'example.com', NULL, NULL, 'MASTER', NULL, NULL),
(2, 'slave.example.net', '12.34.56.78', NULL, 'SLAVE', NULL, NULL),
(3, 'foo.de', NULL, NULL, 'NATIVE', NULL, NULL),
(4, 'bar.net', NULL, NULL, 'MASTER', NULL, NULL),
(5, 'baz.org', NULL, NULL, 'MASTER', NULL, NULL),
(6, '.arpa', NULL, NULL, 'MASTER', NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `options`
--

DROP TABLE IF EXISTS `options`;
CREATE TABLE `options` (
  `name` varchar(255) NOT NULL,
  `value` varchar(2000) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Daten für Tabelle `options`
--

INSERT INTO `options` (`name`, `value`) VALUES
('schema_version', '1');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `permissions`
--

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `domain_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Daten für Tabelle `permissions`
--

INSERT INTO `permissions` (`domain_id`, `user_id`) VALUES
(1, 2),
(2, 2),
(6, 2);

-- --------------------------------------------------------

--
-- Daten für Tabelle `records`
--

INSERT INTO `records` (`id`, `domain_id`, `name`, `type`, `content`, `ttl`, `prio`, `disabled`, `ordername`, `auth`) VALUES
(1, 1, 'test.example.com', 'A', '12.34.56.78', 86400, 0, 0, NULL, 1),
(2, 1, 'sdfdf.example.com', 'TXT', 'foo bar baz', 60, 10, 0, NULL, 1),
(3, 1, 'foo.example.com', 'AAAA', '::1', 86400, 0, 0, NULL, 1),
(4, 3, 'foo.de', 'A', '9.8.7.6', 86400, 0, 0, NULL, 1),
(5, 1, 'example.com', 'SOA', 'ns1.example.com hostmaster.example.com 2018041300 3600 900 604800 86400', 86400, NULL, 0, NULL, 1);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `remote`
--

DROP TABLE IF EXISTS `remote`;
CREATE TABLE `remote` (
  `id` int(11) NOT NULL,
  `record` bigint(20) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `type` varchar(20) NOT NULL,
  `security` varchar(2000) NOT NULL,
  `nonce` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Daten für Tabelle `remote`
--

INSERT INTO `remote` (`id`, `record`, `description`, `type`, `security`, `nonce`) VALUES
(1, 1, 'Password Test', 'password', '$2y$10$abocd6jj/Tw4jzDtqTnjreNzwcerzkXwoVc.JvZBoZ6p0grEKDWoW', NULL),
(2, 4, 'Key Test', 'key', '-----BEGIN PUBLIC KEY-----\nMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA5mu3aH90uSXY9sVLgVSz\nKj4FEctrpFDPyVC4ufbJa/44fuLABFe+IizgZUheNBBO7FjpLJYvsL24o6TEeht4\no5j0KHrRHXqp4WQuAL3ZREv/AhNaOC9/xyjoGwUkKkdC2bIfh0J/ACkezxvUrPsh\nbzhzY+co/M9PqlgTbjKjvlv/pRj2dSp98FzUme3HCh7Nn1EOM3yPMtaKNA9Qkkz1\noalfR3xmJjIanoS9zcK77/yyQ8VwI//CgxvnpnWbORZG0B9W2ZBoI8Bj4zprbbFG\nKNmrb403wfDijYF3MXpSMjKvJ5YVuZsn35EWIi5tqFc0oV7Ryy9nBHzKeoYN7Szs\nrXIS5+ZcQDLuN+pqJ7ByVaw4aVn85py8IdO0IYD5xeKd1i0iqm+KSoFTS1jiNSZu\n6iVl4odixWtW7oPLYBbd/vD2F7Ua5cLd12Rs+6kEVtlpnIf7txyFQL4QHYJxB7fI\ny+m70mfufVvKbFh/mHkhe+Arv71ERDMfAV3AD8++axLqYfU/LLFzanjwIBctAA9a\nj++G0lwl1adURwnBeq8+YrMU4/wg9efquKXLR40dU9nkMJOm5tPm+XHt4o3wio4X\n2FqnD57I7qJCWVc00HtpeWno5vHL+eJu0TdxjBuYXnQfwa1z9pWvGaoBtg7tyHgv\ng7YZJzF1MW5N9ZqnkdFJVEsCAwEAAQ==\n-----END PUBLIC KEY-----', NULL),
(3, 1, 'Key Test 2', 'key', '-----BEGIN PUBLIC KEY-----\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCrJ/UoQoN5rO1nwrWBNDr3TgPB\nkm6UmN/B6NY7RXcYTJOFEP6iWqTj9Pw8aT8/DSn2uTMeQK6kWNUAWmRaylQI2QHQ\ndPtrI6piTpjvKm+KbR+n3e4QJ/zOcg06cHYJJiyhPjfC12j3ZxINOV3LDbEKq4s0\nHxMGYZHPu+UezapeeQIDAQAB\n-----END PUBLIC KEY-----', NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

DROP TABLE IF EXISTS `logging`;
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `backend` varchar(50) NOT NULL,
  `type` varchar(20) NOT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `logging` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  `log` varchar(2000) NOT NULL,
  PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Daten für Tabelle `users`
--

INSERT INTO `users` (`id`, `name`, `backend`, `type`, `password`) VALUES
(1, 'admin', 'native', 'admin', '$2y$10$9iIDHWgjY0pEsz8pZLXPx.gkMNDxTMzb7U0Um5hUGjKmUUHWQNXcW'),
(2, 'user', 'native', 'user', '$2y$10$MktCI4XcfD0FpIFSkxex6OVifnIw3Nqw6QJueWmjVte99wx6XGBoq'),
(3, 'configuser', 'config', 'user', NULL);

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `options`
--
ALTER TABLE `options`
  ADD PRIMARY KEY (`name`);

--
-- Indizes für die Tabelle `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`domain_id`,`user_id`),
  ADD KEY `permissions_ibfk_2` (`user_id`);

--
-- Indizes für die Tabelle `remote`
--
ALTER TABLE `remote`
  ADD PRIMARY KEY (`id`),
  ADD KEY `remote_ibfk_1` (`record`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `remote`
--
ALTER TABLE `remote`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `permissions`
--
ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `permissions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `remote`
--
ALTER TABLE `remote`
  ADD CONSTRAINT `remote_ibfk_1` FOREIGN KEY (`record`) REFERENCES `records` (`id`);

ALTER TABLE `logging`
  ADD CONSTRAINT `logging_domain_id_ibfk` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `logging_user_id_ibfk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS=1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
