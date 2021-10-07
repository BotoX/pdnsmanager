--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `domain_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`domain_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `remote`
--

CREATE TABLE `remote` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `record` bigint(20) NOT NULL,
  `description` varchar(255) NOT NULL,
  `type` varchar(20) NOT NULL,
  `security` varchar(2000) NOT NULL,
  `nonce` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `backend` varchar(50) NOT NULL,
  `type` varchar(20) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `options`
--

CREATE TABLE `options` (
  `name` varchar(255) NOT NULL,
  `value` varchar(2000) DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `options`
--

INSERT INTO `options` (`name`, `value`) VALUES
('schema_version', '1');

-- --------------------------------------------------------

--
-- Table structure for table `logging`
--

CREATE TABLE `logging` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain_id` int(11),
  `user_id` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  `log` varchar(2000) NOT NULL,
  PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Indexes for dumped tables
--

--
-- Constraints for table `permissions`
--
ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_domain_id_ibfk` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `permissions_user_id_ibfk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `remote`
--
ALTER TABLE `remote`
  ADD CONSTRAINT `remote_record_ibfk` FOREIGN KEY (`record`) REFERENCES `records` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
