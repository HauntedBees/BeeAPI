CREATE DATABASE  IF NOT EXISTS `beeaccount`
USE `beeaccount`;

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(45) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `rolebits` int DEFAULT NULL,
  `source` varchar(45) DEFAULT NULL,
  `accesstoken` varchar(100) DEFAULT NULL,
  `accesstokensecret` varchar(100) DEFAULT NULL,
  `externalname` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
);

CREATE TABLE `apitoken` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `type` varchar(15) NOT NULL,
  `token` varchar(100) NOT NULL,
  `secret` varchar(100) NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
);