-- MySQL dump 10.9
--
-- Host: localhost    Database: dns
-- ------------------------------------------------------
-- Server version	4.1.11-Debian_4-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `dns_records`
--

DROP TABLE IF EXISTS `dns_records`;
CREATE TABLE `dns_records` (
  `zone` text,
  `host` text,
  `type` text,
  `data` text NOT NULL,
  `ttl` int(11) default NULL,
  `mx_priority` text,
  `refresh` int(11) default NULL,
  `retry` int(11) default NULL,
  `expire` int(11) default NULL,
  `minimum` int(11) default NULL,
  `serial` bigint(20) default NULL,
  `resp_person` text,
  `primary_ns` text,
  `rid` int(11) NOT NULL auto_increment,
  PRIMARY KEY  (`rid`),
  KEY `host_index` (`host`(20)),
  KEY `zone_index` (`zone`(30)),
  KEY `type_index` (`type`(8))
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

