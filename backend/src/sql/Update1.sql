CREATE TABLE `logging` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain_id` int(11),
  `user_id` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  `log` varchar(2000) NOT NULL,
  PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

UPDATE options SET value=1 WHERE name='schema_version';
