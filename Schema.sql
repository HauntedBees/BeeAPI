SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS `beeaccount` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `beeaccount`;

CREATE TABLE IF NOT EXISTS `apitoken` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `type` varchar(15) NOT NULL,
  `token` varchar(100) NOT NULL,
  `secret` varchar(100) NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(45) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rolebits` int(11) DEFAULT NULL,
  `source` varchar(45) DEFAULT NULL,
  `accesstoken` varchar(100) DEFAULT NULL,
  `accesstokensecret` varchar(100) DEFAULT NULL,
  `externalname` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;