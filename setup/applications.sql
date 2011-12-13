SET NAMES utf8;
SET foreign_key_checks = 0;
SET time_zone = 'SYSTEM';
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE `applications`;
CREATE TABLE IF NOT EXISTS `applications` (
  `council` varchar(12) NOT NULL,
  `app_ref` varchar(20) NOT NULL,
  `imported` datetime NOT NULL,
  `status` varchar(25) NOT NULL,
  `received_date` date NOT NULL,
  `decision_date` date DEFAULT NULL,
  `decision` char(1) NOT NULL,
  `lat` double(13,10) DEFAULT NULL,
  `lng` double(13,10) DEFAULT NULL,
  `address` text,
  `details` text,
  `url` text,
  PRIMARY KEY `applications_pk` (`council`,`app_ref`),
  KEY `received_date` (`received_date`),
  KEY `lat` (`lat`),
  KEY `lng` (`lng`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
