CREATE DATABASE IF NOT EXISTS `beeaccount`
USE `beeaccount`;

CREATE TABLE `users` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `username` NVARCHAR(45) NOT NULL,
  `password` VARCHAR(255) DEFAULT NULL,
  `rolebits` INT DEFAULT NULL,
  `source` VARCHAR(45) DEFAULT NULL,
  `accesstoken` VARCHAR(100) DEFAULT NULL,
  `accesstokensecret` VARCHAR(100) DEFAULT NULL,
  `externalname` NVARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
);

CREATE TABLE `apitoken` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(15) NOT NULL,
  `token` VARCHAR(100) NOT NULL,
  `secret` VARCHAR(100) NOT NULL,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
);