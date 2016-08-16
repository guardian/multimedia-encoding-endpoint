-- MySQL dump 10.13  Distrib 5.5.50, for debian-linux-gnu (x86_64)
--
-- Host: gnm-mm-interactivevids.cuey4k0bnsmn.eu-west-1.rds.amazonaws.com    Database: interactivevids
-- ------------------------------------------------------
-- Server version	5.6.19-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `encodings`
--

DROP TABLE IF EXISTS `encodings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `encodings` (
  `encodingid` int(11) NOT NULL AUTO_INCREMENT,
  `contentid` int(11) NOT NULL,
  `url` text NOT NULL,
  `format` varchar(254) CHARACTER SET ascii NOT NULL,
  `mobile` tinyint(1) NOT NULL,
  `multirate` tinyint(1) NOT NULL,
  `vcodec` text,
  `acodec` text,
  `vbitrate` int(11) DEFAULT NULL,
  `abitrate` int(11) DEFAULT NULL,
  `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `frame_width` int(11) NOT NULL,
  `frame_height` int(11) NOT NULL,
  `duration` float NOT NULL,
  `file_size` int(11) NOT NULL,
  `fcs_id` varchar(64) NOT NULL,
  `octopus_id` int(11) NOT NULL,
  `aspect` text NOT NULL,
  PRIMARY KEY (`encodingid`),
  KEY `contentid` (`contentid`),
  KEY `format` (`format`),
  KEY `fcs_id` (`fcs_id`)
) ENGINE=InnoDB AUTO_INCREMENT=171087 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `idmapping`
--

DROP TABLE IF EXISTS `idmapping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idmapping` (
  `contentid` bigint(20) NOT NULL AUTO_INCREMENT,
  `filebase` varchar(32000) NOT NULL,
  `project` varchar(32000) DEFAULT NULL,
  `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `octopus_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`contentid`),
  KEY `filebase` (`filebase`(767)),
  KEY `octopus_id` (`octopus_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30538 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mime_equivalents`
--

DROP TABLE IF EXISTS `mime_equivalents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mime_equivalents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `real_name` varchar(16378) NOT NULL,
  `mime_equivalent` varchar(16378) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `real_name` (`real_name`(767)),
  KEY `real_name_2` (`real_name`(767))
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `posterframes`
--

DROP TABLE IF EXISTS `posterframes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `posterframes` (
  `posterid` int(11) NOT NULL AUTO_INCREMENT,
  `encodingid` int(11) NOT NULL,
  `contentid` int(11) NOT NULL,
  `poster_url` varchar(32768) NOT NULL,
  `mime_type` varchar(128) NOT NULL,
  PRIMARY KEY (`posterid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `xor_urls`
--

DROP TABLE IF EXISTS `xor_urls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xor_urls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2016-08-16 11:24:23
