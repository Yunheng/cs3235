DROP TABLE IF EXISTS `access`; 
CREATE TABLE `access` (
  `userId` varchar(64) NOT NULL,
  `lockId` varchar(16) NOT NULL,
  PRIMARY KEY (`userId`,`lockId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `access_tokens`; 
CREATE TABLE `access_tokens` (
  `userId` varchar(64) NOT NULL,
  `token` varchar(32) NOT NULL,
  `issued` TIMESTAMP NOT NULL,
  `expiry` TIMESTAMP NOT NULL,
  PRIMARY KEY (`userId`, `issued`, `expiry`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `otp_tokens`;
CREATE TABLE `otp_tokens` (
  `userId` varchar(64) NOT NULL,
  `token` varchar(6) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `issued` TIMESTAMP NOT NULL,
  `expiry` TIMESTAMP NOT NULL,
  PRIMARY KEY (`userId`)
);

DROP TABLE IF EXISTS `locks`; 
CREATE TABLE `locks` (
  `id` varchar(16) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `users`; 
CREATE TABLE `users` (
  `id` varchar(64) NOT NULL,
  `email` varchar(256) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `registered` TIMESTAMP NOT NULL,
  `last_update` TIMESTAMP NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `access`
  ADD CONSTRAINT `access_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `access_ibfk_2` FOREIGN KEY (`lockId`) REFERENCES `locks` (`id`);
  
ALTER TABLE `otp_tokens`
  ADD CONSTRAINT `otp_tokens_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`id`);
  
ALTER TABLE `access_tokens`
  ADD CONSTRAINT `access_tokens_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`id`);