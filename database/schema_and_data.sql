-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: mohamed_youssef
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `_CollectionToUser`
--

DROP TABLE IF EXISTS `_CollectionToUser`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `_CollectionToUser` (
  `A` varchar(50) NOT NULL,
  `B` varchar(50) NOT NULL,
  PRIMARY KEY (`A`,`B`),
  KEY `B` (`B`),
  CONSTRAINT `_CollectionToUser_ibfk_1` FOREIGN KEY (`A`) REFERENCES `Collection` (`id`) ON DELETE CASCADE,
  CONSTRAINT `_CollectionToUser_ibfk_2` FOREIGN KEY (`B`) REFERENCES `User` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `_CollectionToUser`
--

LOCK TABLES `_CollectionToUser` WRITE;
/*!40000 ALTER TABLE `_CollectionToUser` DISABLE KEYS */;
/*!40000 ALTER TABLE `_CollectionToUser` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `_CourseToUser`
--

DROP TABLE IF EXISTS `_CourseToUser`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `_CourseToUser` (
  `A` varchar(50) NOT NULL,
  `B` varchar(50) NOT NULL,
  PRIMARY KEY (`A`,`B`),
  KEY `B` (`B`),
  CONSTRAINT `_CourseToUser_ibfk_1` FOREIGN KEY (`A`) REFERENCES `Course` (`id`) ON DELETE CASCADE,
  CONSTRAINT `_CourseToUser_ibfk_2` FOREIGN KEY (`B`) REFERENCES `User` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `_CourseToUser`
--

LOCK TABLES `_CourseToUser` WRITE;
/*!40000 ALTER TABLE `_CourseToUser` DISABLE KEYS */;
/*!40000 ALTER TABLE `_CourseToUser` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Attendance`
--

DROP TABLE IF EXISTS `Attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Attendance` (
  `id` varchar(50) NOT NULL,
  `userId` varchar(50) NOT NULL,
  `attendanceDatetime` datetime NOT NULL,
  `metaData` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metaData`)),
  `createdAt` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`),
  CONSTRAINT `Attendance_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `User` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Attendance`
--

LOCK TABLES `Attendance` WRITE;
/*!40000 ALTER TABLE `Attendance` DISABLE KEYS */;
/*!40000 ALTER TABLE `Attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `CodeGroup`
--

DROP TABLE IF EXISTS `CodeGroup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `CodeGroup` (
  `id` varchar(50) NOT NULL,
  `courseId` varchar(50) NOT NULL,
  `collectionId` varchar(50) DEFAULT NULL,
  `createdAt` datetime DEFAULT current_timestamp(),
  `expiresAt` datetime NOT NULL,
  `maxUses` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `courseId` (`courseId`),
  KEY `collectionId` (`collectionId`),
  CONSTRAINT `CodeGroup_ibfk_1` FOREIGN KEY (`courseId`) REFERENCES `Course` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_codegroup_collection` FOREIGN KEY (`collectionId`) REFERENCES `Collection` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `CodeGroup`
--

LOCK TABLES `CodeGroup` WRITE;
/*!40000 ALTER TABLE `CodeGroup` DISABLE KEYS */;
INSERT INTO `CodeGroup` VALUES ('2w46Y','_RS_a',NULL,'2026-08-01 18:28:45','2026-08-31 20:59:59',1),('599FA','_RS_a','UTxbw','2026-10-11 06:52:10','2026-11-10 06:52:10',1),('B5tze','EG13A',NULL,'2026-10-13 20:52:56','2026-11-12 18:52:56',1),('PsHX8','EG13A',NULL,'2026-10-09 11:52:03','2026-11-08 09:52:03',1),('ZTZc8','_RS_a','UTxbw','2026-10-11 06:52:26','2026-11-10 06:52:26',1);
/*!40000 ALTER TABLE `CodeGroup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Collection`
--

DROP TABLE IF EXISTS `Collection`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Collection` (
  `id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `price` double DEFAULT NULL,
  `courseId` varchar(50) NOT NULL,
  `order` int(11) DEFAULT 0,
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `courseId` (`courseId`),
  CONSTRAINT `Collection_ibfk_1` FOREIGN KEY (`courseId`) REFERENCES `Course` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Collection`
--

LOCK TABLES `Collection` WRITE;
/*!40000 ALTER TABLE `Collection` DISABLE KEYS */;
INSERT INTO `Collection` VALUES ('-GFDu','الدرس الثاني',70,'EtFVX',1,'2026-09-06 19:46:20'),('74rJK','الدرس الثانى',70,'KGXOe',1,'2026-08-14 06:16:27'),('DaPaE','الدرس الأول',70,'EtFVX',0,'2026-09-06 19:46:03'),('e1edF','الدرس الرابع',70,'EtFVX',3,'2026-09-06 19:48:29'),('ejWMN','الدرس الثالث',70,'EtFVX',2,'2026-09-06 19:47:26'),('eqbaZ','الدرس الرابع',70,'EG13A',3,'2026-09-06 19:34:03'),('HfExH','الدرس الأول',70,'KGXOe',0,'2026-08-14 06:14:25'),('JEktz','الدرس الثاني',70,'EG13A',1,'2026-08-14 06:11:35'),('kGjHD','الدرس الخامس',70,'EG13A',4,'2026-09-06 19:36:19'),('N4QPC','الدرس الخامس',70,'EtFVX',4,'2026-09-06 19:49:08'),('s1Ctu','الدرس الأول',70,'EG13A',0,'2026-09-06 19:45:11'),('UTxbw','الدرس الأول',70,'_RS_a',0,'2026-08-29 04:50:22'),('WdlOy','الدرس الثالث',70,'EG13A',2,'2026-09-06 19:32:45');
/*!40000 ALTER TABLE `Collection` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Course`
--

DROP TABLE IF EXISTS `Course`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Course` (
  `id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` double NOT NULL DEFAULT 0,
  `thumbnail` text DEFAULT NULL,
  `sectionId` varchar(50) DEFAULT NULL,
  `order` int(11) DEFAULT 0,
  `published` tinyint(1) DEFAULT 1,
  `featured` tinyint(1) DEFAULT 0,
  `specialization` enum('ADABI','ELMI') DEFAULT NULL,
  `createdAt` datetime DEFAULT current_timestamp(),
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sectionId` (`sectionId`),
  CONSTRAINT `Course_ibfk_1` FOREIGN KEY (`sectionId`) REFERENCES `Section` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Course`
--

LOCK TABLES `Course` WRITE;
/*!40000 ALTER TABLE `Course` DISABLE KEYS */;
INSERT INTO `Course` VALUES ('_RS_a','النحو (للقسم العلمي)','شرح مادة النحو كاملة للصف الثالث الثانوي الأزهري للقسم العلمي',500,'/api/uploads/_______________1787330433480.jpg','FJuuB',2,1,0,'ELMI','2026-08-01 18:19:51','2026-09-06 19:50:42'),('EG13A','الصرف (للقسم العلمي)','شرح مادة الصرف كاملة للصف الثالث الثانوي الأزهري للقسم العلمي',500,'/api/uploads/________________1787330412071.jpg','FJuuB',1,1,0,'ELMI','2026-08-01 18:20:02','2026-08-31 09:21:58'),('EtFVX','الصرف (القسم الأدبي)','شرح منهج الصرف كامل للصف الثالث الثانوي الأأزهري القسم الأدبي',500,'/api/uploads/________________1788168385853.jpg','FJuuB',4,1,0,'ADABI','2026-08-31 09:28:00','2026-08-31 09:28:00'),('KGXOe','الشهر الأول','2 صرف + 2 نحو',250,'/api/uploads/_________________1788281699581.jpg','aHS5V',1,1,0,'ELMI','2026-08-01 19:00:36','2026-09-01 16:55:04'),('x7cih','البلاغة (للقسم العلمي)','شرح مادة البلاغة كاملة للصف الثالث الثانوي الأزهري للقسم العلمي',300,'/api/uploads/________________1787330385243.jpg','FJuuB',3,1,0,'ELMI','2026-08-13 16:24:03','2026-09-06 19:50:53'),('XY3ij','الشهر الثانى','نحو + بلاغة',250,'/api/uploads/__________________1788281751971.jpg','aHS5V',2,1,0,'ELMI','2026-08-01 19:00:59','2026-09-06 19:51:17');
/*!40000 ALTER TABLE `Course` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `CourseCode`
--

DROP TABLE IF EXISTS `CourseCode`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `CourseCode` (
  `id` varchar(50) NOT NULL,
  `code` varchar(100) NOT NULL,
  `courseId` varchar(50) NOT NULL,
  `collectionId` varchar(50) DEFAULT NULL,
  `expires` datetime NOT NULL,
  `userId` varchar(50) DEFAULT NULL,
  `accessTimes` int(11) DEFAULT 1,
  `codeGroupId` varchar(50) DEFAULT NULL,
  `boundDeviceIdentifier` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `userId` (`userId`),
  KEY `codeGroupId` (`codeGroupId`),
  KEY `collectionId` (`collectionId`),
  CONSTRAINT `CourseCode_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `User` (`id`) ON DELETE SET NULL,
  CONSTRAINT `CourseCode_ibfk_2` FOREIGN KEY (`codeGroupId`) REFERENCES `CodeGroup` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_coursecode_collection` FOREIGN KEY (`collectionId`) REFERENCES `Collection` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `CourseCode`
--

LOCK TABLES `CourseCode` WRITE;
/*!40000 ALTER TABLE `CourseCode` DISABLE KEYS */;
INSERT INTO `CourseCode` VALUES ('_yTWz','P2XnYTt7','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('1HeDB','MY-LLMBZ4','EG13A',NULL,'2026-11-12 18:52:56',NULL,10,'B5tze',NULL),('8gRMx','MY-4PESQH','EG13A',NULL,'2026-11-12 18:52:56',NULL,10,'B5tze',NULL),('937Sf','MY-S62G29','EG13A',NULL,'2026-11-12 18:52:56',NULL,10,'B5tze',NULL),('9J8wH','MY-AUWGXV','EG13A',NULL,'2026-11-12 18:52:56',NULL,10,'B5tze',NULL),('9T4Rg','MY-WLSRRA','EG13A',NULL,'2026-11-12 18:52:56',NULL,10,'B5tze',NULL),('AEUk5','TEST-3132D9','EG13A',NULL,'2026-11-09 19:46:09',NULL,10,NULL,NULL),('b4dTr','P0duBE6j','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('c0-yv','NjtgJELZ','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('eBhx1','kWhsqLmW','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('EiaSb','3PdrJkc3','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('eN0CX','uuJP4aJq','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('epP5P','2Vmn5W4L','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('F_Okb','rEnp443X','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('FDEsm','csBWrD1y','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('FL9fl','Yq2EB8x4','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('FwZPm','2kef1UwJ','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('FxDnm','sKIqVqV0','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('Gn1Cq','gRjiKE9q','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('JbgxV','kMupuCCM','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('jPSkw','Cqn1s9ir','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('JVLFz','MY-CN5EJZ','EG13A',NULL,'2026-11-12 18:52:56',NULL,10,'B5tze',NULL),('K2kLy','QehGvMir','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('kK_tD','5qLMHj3m','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('kk5Yt','dkKaPFuL','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('LLnRM','CAzt1g9L','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('MuUNv','MY-PJG8QL','EG13A',NULL,'2026-11-12 18:52:56',NULL,10,'B5tze',NULL),('NJsQ9','Rxw8fOjv','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('NYkZY','sBn6m1eo','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('Olc1r','xHhth9UF','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('p4jeV','MY-LZQRAJ','EG13A',NULL,'2026-11-12 18:52:56',NULL,10,'B5tze',NULL),('pEklf','HSA-TEST3845','_RS_a','UTxbw','2026-11-10 06:52:26',NULL,1,'ZTZc8',NULL),('ppvUg','L7JcMArb','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('pRaf-','DyPxXXSB','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('q0KGR','dVKj7GF8','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('Q0xx3','Ewh1j4yh','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('QjUk-','DrYzI8QH','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('qkx1m','LjKBdCEz','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('QqXUh','3ATdwiQT','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('rUfUe','tDh1OEns','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('SoV9B','Om5cDShD','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('T0ueO','fbzHSPoc','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('t2_Wo','QcNeIUOv','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('tbdwD','MY-6TD4GX','EG13A',NULL,'2026-11-12 18:52:56',NULL,10,'B5tze',NULL),('TlgKI','Tcz3ZM5i','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('u6tJJ','cBRxgAyh','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('UaVYA','C8nL8PVC','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('udOy8','ZQyIbgds','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('VPASO','ancafnLL','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('vw4Uy','PrYhxb3W','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('VZr5R','5wtvqx0w','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('x-KoW','kcyd1isk','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('xHuN1','ZZqVRVUr','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('y5tn4','MY-S5RFU7','EG13A',NULL,'2026-11-12 18:52:56',NULL,10,'B5tze',NULL),('yG_Xd','v5FqW8TA','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('YUuk7','msfoqFXy','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL),('yW3dA','xaSrbBui','_RS_a',NULL,'2026-08-31 20:59:59',NULL,1,'2w46Y',NULL);
/*!40000 ALTER TABLE `CourseCode` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Device`
--

DROP TABLE IF EXISTS `Device`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Device` (
  `id` varchar(50) NOT NULL,
  `userId` varchar(50) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `deviceType` varchar(50) DEFAULT 'other',
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `createdAt` datetime DEFAULT current_timestamp(),
  `lastActiveAt` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifier` (`identifier`),
  KEY `userId` (`userId`),
  CONSTRAINT `Device_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `User` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Device`
--

LOCK TABLES `Device` WRITE;
/*!40000 ALTER TABLE `Device` DISABLE KEYS */;
-- Device table is clean for production
/*!40000 ALTER TABLE `Device` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `File`
--

DROP TABLE IF EXISTS `File`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `File` (
  `id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `link` text NOT NULL,
  `courseId` varchar(50) NOT NULL,
  `collectionId` varchar(50) NOT NULL,
  `order` int(11) DEFAULT 0,
  `published` tinyint(1) DEFAULT 1,
  `createdAt` datetime DEFAULT current_timestamp(),
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `courseId` (`courseId`),
  KEY `collectionId` (`collectionId`),
  CONSTRAINT `File_ibfk_1` FOREIGN KEY (`courseId`) REFERENCES `Course` (`id`) ON DELETE CASCADE,
  CONSTRAINT `File_ibfk_2` FOREIGN KEY (`collectionId`) REFERENCES `Collection` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `File`
--

LOCK TABLES `File` WRITE;
/*!40000 ALTER TABLE `File` DISABLE KEYS */;
/*!40000 ALTER TABLE `File` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `GalleryImage`
--

DROP TABLE IF EXISTS `GalleryImage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `GalleryImage` (
  `id` varchar(50) NOT NULL,
  `url` text NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `createdAt` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `GalleryImage`
--

LOCK TABLES `GalleryImage` WRITE;
/*!40000 ALTER TABLE `GalleryImage` DISABLE KEYS */;
INSERT INTO `GalleryImage` VALUES ('_X2eL','/api/uploads/2__1788281450571.jpg','2ث.jpg','2026-09-01 16:50:50'),('61n--','/api/uploads/1__1788281463408.jpg','1ث.jpg','2026-09-01 16:51:03'),('ek-0A','/api/uploads/_________________1788281699581.jpg','الشهر الأول علمي.jpg','2026-09-01 16:54:59'),('F1HQD','/api/uploads/_______________1787330433480.jpg','كور النحو علمي.jpg','2026-08-21 16:40:33'),('HEyq3','/api/uploads/WhatsApp_Image_2026_09_01_at_1_46_02_AM_1788259959566.jpeg','WhatsApp Image 2026-09-01 at 1.46.02 AM.jpeg','2026-09-01 10:52:39'),('jVa9d','/api/uploads/________________1788168385853.jpg','كورس الصرف أدبي.jpg','2026-08-31 09:26:25'),('Kb8b4','/api/uploads/WhatsApp_Image_2026_09_01_at_1_46_02_AM__2__1788259991890.jpeg','WhatsApp Image 2026-09-01 at 1.46.02 AM (2).jpeg','2026-09-01 10:53:11'),('L4S5M','/api/uploads/________________1787330385243.jpg','كورس بلاغة علمي.jpg','2026-08-21 16:39:45'),('OxA0a','/api/uploads/________________1787330412071.jpg','كورس الصرف علمي.jpg','2026-08-21 16:40:12'),('S9f9H','/api/uploads/5555555555555_1788279601755.jpeg','5555555555555.jpeg','2026-09-01 16:20:01'),('VJSPq','/api/uploads/__________________1788281751971.jpg','الشهر الثاني علمي.jpg','2026-09-01 16:55:51');
/*!40000 ALTER TABLE `GalleryImage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Progress`
--

DROP TABLE IF EXISTS `Progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Progress` (
  `id` varchar(50) NOT NULL,
  `userId` varchar(50) NOT NULL,
  `passedQuizzes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`passedQuizzes`)),
  `quizzesGrades` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`quizzesGrades`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `userId` (`userId`),
  CONSTRAINT `Progress_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `User` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Progress`
--

LOCK TABLES `Progress` WRITE;
/*!40000 ALTER TABLE `Progress` DISABLE KEYS */;
/*!40000 ALTER TABLE `Progress` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Question`
--

DROP TABLE IF EXISTS `Question`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Question` (
  `id` varchar(50) NOT NULL,
  `questionHead` text NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`options`)),
  `rightAnswer` text NOT NULL,
  `image` text DEFAULT NULL,
  `quizId` varchar(50) NOT NULL,
  `order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `quizId` (`quizId`),
  CONSTRAINT `Question_ibfk_1` FOREIGN KEY (`quizId`) REFERENCES `Quiz` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Question`
--

LOCK TABLES `Question` WRITE;
/*!40000 ALTER TABLE `Question` DISABLE KEYS */;
/*!40000 ALTER TABLE `Question` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Quiz`
--

DROP TABLE IF EXISTS `Quiz`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Quiz` (
  `id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `courseId` varchar(50) NOT NULL,
  `collectionId` varchar(50) NOT NULL,
  `quizTime` int(11) DEFAULT 30,
  `order` int(11) DEFAULT 0,
  `published` tinyint(1) DEFAULT 1,
  `createdAt` datetime DEFAULT current_timestamp(),
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `courseId` (`courseId`),
  KEY `collectionId` (`collectionId`),
  CONSTRAINT `Quiz_ibfk_1` FOREIGN KEY (`courseId`) REFERENCES `Course` (`id`) ON DELETE CASCADE,
  CONSTRAINT `Quiz_ibfk_2` FOREIGN KEY (`collectionId`) REFERENCES `Collection` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Quiz`
--

LOCK TABLES `Quiz` WRITE;
/*!40000 ALTER TABLE `Quiz` DISABLE KEYS */;
INSERT INTO `Quiz` VALUES ('Q_TEST_01','امتحان النحو الشامل التجريبي',NULL,'KGXOe','UTxbw',30,0,1,'2026-10-13 21:40:52','2026-10-13 21:40:52');
/*!40000 ALTER TABLE `Quiz` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `QuizAttempt`
--

DROP TABLE IF EXISTS `QuizAttempt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `QuizAttempt` (
  `id` varchar(50) NOT NULL,
  `userId` varchar(50) NOT NULL,
  `quizId` varchar(50) NOT NULL,
  `submittedAt` datetime NOT NULL,
  `solvedCorrectly` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`solvedCorrectly`)),
  `numberOfQuestion` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`),
  KEY `quizId` (`quizId`),
  CONSTRAINT `QuizAttempt_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `User` (`id`) ON DELETE CASCADE,
  CONSTRAINT `QuizAttempt_ibfk_2` FOREIGN KEY (`quizId`) REFERENCES `Quiz` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `QuizAttempt`
--

LOCK TABLES `QuizAttempt` WRITE;
/*!40000 ALTER TABLE `QuizAttempt` DISABLE KEYS */;
-- QuizAttempt table is clean for production
/*!40000 ALTER TABLE `QuizAttempt` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Section`
--

DROP TABLE IF EXISTS `Section`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Section` (
  `id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `order` int(11) DEFAULT 0,
  `yearId` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `yearId` (`yearId`),
  CONSTRAINT `Section_ibfk_1` FOREIGN KEY (`yearId`) REFERENCES `Year` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Section`
--

LOCK TABLES `Section` WRITE;
/*!40000 ALTER TABLE `Section` DISABLE KEYS */;
INSERT INTO `Section` VALUES ('aHS5V','نظام الأشهر',2,'9Meni'),('FJuuB','نظام الفروع',1,'9Meni'),('SEC_5a94b','نظام الفروع',1,'d7UYZ'),('SEC_63f64','نظام الفروع',1,'RGKYc'),('SEC_aea94','نظام الأشهر',2,'RGKYc'),('SEC_e3ec1','نظام الأشهر',2,'d7UYZ');
/*!40000 ALTER TABLE `Section` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Setting`
--

DROP TABLE IF EXISTS `Setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Setting` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `createdAt` datetime DEFAULT current_timestamp(),
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Setting`
--

LOCK TABLES `Setting` WRITE;
/*!40000 ALTER TABLE `Setting` DISABLE KEYS */;
INSERT INTO `Setting` VALUES ('adabLink','','2026-07-22 17:49:45','2026-08-30 18:16:00'),('balaghaLink','','2026-07-22 17:49:45','2026-08-30 18:16:00'),('nahwLink','','2026-07-22 17:49:45','2026-08-30 18:16:00'),('paymobApiKey','','2026-07-22 17:49:45','2026-08-30 18:16:00'),('paymobEnabled','false','2026-07-22 17:49:45','2026-08-30 18:16:00'),('paymobIntegrationIds','','2026-07-22 17:49:45','2026-08-30 18:16:00'),('requireApproval','false','2026-07-22 17:49:45','2026-08-30 18:16:00'),('sarfLink','','2026-07-22 17:49:45','2026-08-30 18:16:00'),('vodafoneCashApiToken','23163fb0e4def5580f920e9aaa801ec51b2c2a8761f5ca9c7a63ef150363734d','2026-07-22 17:49:45','2026-08-30 18:16:00'),('vodafoneCashEnabled','true','2026-07-22 17:49:45','2026-08-30 18:16:00'),('vodafoneCashPhoneNumber',' 01092037257','2026-07-22 17:49:45','2026-08-30 18:16:00'),('vodafoneCashStoreId','101','2026-07-22 17:49:45','2026-08-30 18:16:00'),('whatsappFloatingEnabled','true','2026-08-30 18:16:00','2026-08-30 18:16:00'),('whatsappFloatingNumber','201092037257','2026-08-30 18:16:00','2026-08-30 18:16:00');
/*!40000 ALTER TABLE `Setting` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `User`
--

DROP TABLE IF EXISTS `User`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `User` (
  `id` varchar(50) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `fullName` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `parentPhone` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `buying` text DEFAULT NULL,
  `attendanceID` int(11) DEFAULT NULL,
  `role` enum('STUDENT','ADMIN','SUPERADMIN') DEFAULT 'STUDENT',
  `permissions` text DEFAULT NULL,
  `studentType` enum('ONLINE','CENTER') NOT NULL DEFAULT 'ONLINE',
  `centerCode` varchar(100) DEFAULT NULL,
  `maxDevices` int(11) DEFAULT 1,
  `status` enum('ACTIVE','PENDING','BANNED') DEFAULT 'ACTIVE',
  `yearId` varchar(50) DEFAULT NULL,
  `walletBalance` double DEFAULT 0,
  `joinedAt` datetime DEFAULT current_timestamp(),
  `lastLoginAt` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `attendanceID` (`attendanceID`),
  KEY `yearId` (`yearId`),
  KEY `idx_user_role` (`role`),
  KEY `idx_user_status` (`status`),
  KEY `idx_user_studentType` (`studentType`),
  KEY `idx_user_centerCode` (`centerCode`),
  CONSTRAINT `User_ibfk_1` FOREIGN KEY (`yearId`) REFERENCES `Year` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `User`
--

LOCK TABLES `User` WRITE;
/*!40000 ALTER TABLE `User` DISABLE KEYS */;
INSERT INTO `User` (`id`, `firstName`, `fullName`, `phone`, `parentPhone`, `password`, `buying`, `attendanceID`, `role`, `permissions`, `studentType`, `centerCode`, `maxDevices`, `status`, `yearId`, `walletBalance`, `joinedAt`, `lastLoginAt`) VALUES ('Jaw2x','محمد','محمد يوسف طه','01001893770','01064219402','mido*236',NULL,NULL,'SUPERADMIN','["all"]','ONLINE',NULL,1,'ACTIVE','9Meni',0,'2026-07-18 11:18:05',NOW());
/*!40000 ALTER TABLE `User` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Video`
--

DROP TABLE IF EXISTS `Video`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Video` (
  `id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `link` text NOT NULL,
  `courseId` varchar(50) NOT NULL,
  `collectionId` varchar(50) NOT NULL,
  `order` int(11) DEFAULT 0,
  `published` tinyint(1) DEFAULT 1,
  `createdAt` datetime DEFAULT current_timestamp(),
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `courseId` (`courseId`),
  KEY `collectionId` (`collectionId`),
  CONSTRAINT `Video_ibfk_1` FOREIGN KEY (`courseId`) REFERENCES `Course` (`id`) ON DELETE CASCADE,
  CONSTRAINT `Video_ibfk_2` FOREIGN KEY (`collectionId`) REFERENCES `Collection` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Video`
--

LOCK TABLES `Video` WRITE;
/*!40000 ALTER TABLE `Video` DISABLE KEYS */;
INSERT INTO `Video` VALUES ('BwnIk','همزة الوصل ','شرح درس همزة الوصل في مادة الصرف للصف الثالث الثانوي الأزهري ','k6qFi9H1Z6q8pdIIDES','EtFVX','DaPaE',0,1,'2026-09-06 19:45:46','2026-09-06 19:45:46'),('d6sB3','الإبدال والإعلال ','شرح مقدمة باب الإبدال والإعلال وتأسيس تمهيدي مهم للصرف للصف الثالث الثانوي الأزهري','kUbWzcKjQ5z4TgIWoma','EtFVX','ejWMN',0,1,'2026-09-06 19:48:10','2026-09-06 19:48:10'),('dbCWW',' مواضع قلب أحرف العلة همزة (أول موضعين)','شرح أول موضعين من مواضع قلب أحرف العلة همزة للصف الثاث الثانوي الأزهري','k6OtRGTClmnv1GIWCVw','EG13A','eqbaZ',0,1,'2026-09-06 19:35:48','2026-09-06 19:48:50'),('JmS5C',' مواضع قلب أحرف العلة همزة (أول موضعين)','شرح أول موضعين من مواضع قلب أحرف العلة همزة للصف الثاث الثانوي الأزهري','k6OtRGTClmnv1GIWCVw','EtFVX','e1edF',0,1,'2026-09-06 19:48:47','2026-09-06 19:48:47'),('kE5iY','النداء','شرح درس النداء من فرع النحو للصف الثالث الثانوي الأزهري','k4EI4Im39ReUlXJewNE','_RS_a','UTxbw',0,1,'2026-08-29 04:51:24','2026-08-29 04:51:24'),('LE9Uh','تابع مواضع قلب أحرف العلة همزة(الموضع الثالث والرابع والخامس)','شرح الموضع الثالث والرابع والخامس من مواضع قلب أحرف العلة همزة في مادة الصرف للصف الثالث الثانوي الأزهري','kk3vv8VwcLuRKvIWOTo','EG13A','kGjHD',0,1,'2026-09-06 19:37:14','2026-09-06 19:49:25'),('M8izq','همزة القطع','شرح درس همزة القطع من مادى الصرف للصف الثالث الثانوي الأزهري','k2m62fLyButvy8IJoZw','EG13A','JEktz',0,1,'2026-08-14 06:12:36','2026-08-14 06:12:36'),('QyhBW','همزة الوصل ','شرح درس همزة الوصل في مادة الصرف للصف الثالث الثانوي الأزهري ','k6qFi9H1Z6q8pdIIDES','EG13A','s1Ctu',0,1,'2026-08-13 16:21:25','2026-08-13 16:21:25'),('TiTeJ','الإبدال والإعلال ','شرح مقدمة باب الإبدال والإعلال وتأسيس تمهيدي مهم للصرف للصف الثالث الثانوي الأزهري','kUbWzcKjQ5z4TgIWoma','EG13A','WdlOy',0,1,'2026-09-06 19:33:39','2026-09-06 19:48:12'),('uCh2K','همزة القطع','شرح درس همزة القطع من مادة الصرف للصف الثالث الثانوي الأزهري','k2m62fLyButvy8IJoZw','EtFVX','-GFDu',0,1,'2026-09-06 19:47:13','2026-09-06 19:47:13'),('VoAPL','همزة القطع','شرح درس همزة القطع من مادة الصرف للصف الثالث الثانوي الأزهري','k2m62fLyButvy8IJoZw','KGXOe','74rJK',0,1,'2026-08-14 06:16:08','2026-08-14 06:16:08'),('vpjxd','تابع مواضع قلب أحرف العلة همزة(الموضع الثالث والرابع والخامس)','شرح الموضع الثالث والرابع والخامس من مواضع قلب أحرف العلة همزة في مادة الصرف للصف الثالث الثانوي الأزهري','kk3vv8VwcLuRKvIWOTo','EtFVX','N4QPC',0,1,'2026-09-06 19:49:24','2026-09-06 19:49:24'),('WHNX9','شرح همزة الوصل ','شرح درس همزة الوصل من مادة الصرف للصف الثالث الثانوي الأزهري','k6qFi9H1Z6q8pdIIDES','KGXOe','HfExH',0,1,'2026-08-14 06:14:11','2026-08-14 06:14:11'),('yt001','حصة تأسيسية من اليوتيوب (مشغل فيديو مؤمن ومحمي)','شرح تفصيلي مع تدريبات مكثفة وتطبيقات عملية على المنهج - مشغل فيديو مؤمن بالكامل ومحمي ضد التحميل المباشر وتصوير الشاشة وتسريب الروابط.','https://www.youtube.com/watch?v=k3_tw44QsZQ','EG13A','s1Ctu',1,1,'2026-10-10 21:41:07','2026-10-10 21:41:07');
/*!40000 ALTER TABLE `Video` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `VideoView`
--

DROP TABLE IF EXISTS `VideoView`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `VideoView` (
  `id` varchar(50) NOT NULL,
  `userId` varchar(50) NOT NULL,
  `videoId` varchar(50) NOT NULL,
  `courseId` varchar(50) NOT NULL,
  `viewCount` int(11) DEFAULT 1,
  `createdAt` datetime DEFAULT current_timestamp(),
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_video` (`userId`,`videoId`),
  KEY `videoId` (`videoId`),
  KEY `courseId` (`courseId`),
  CONSTRAINT `VideoView_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `User` (`id`) ON DELETE CASCADE,
  CONSTRAINT `VideoView_ibfk_2` FOREIGN KEY (`videoId`) REFERENCES `Video` (`id`) ON DELETE CASCADE,
  CONSTRAINT `VideoView_ibfk_3` FOREIGN KEY (`courseId`) REFERENCES `Course` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `VideoView`
--

LOCK TABLES `VideoView` WRITE;
/*!40000 ALTER TABLE `VideoView` DISABLE KEYS */;
/*!40000 ALTER TABLE `VideoView` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `WalletTransaction`
--

DROP TABLE IF EXISTS `WalletTransaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `WalletTransaction` (
  `id` varchar(50) NOT NULL,
  `userId` varchar(50) NOT NULL,
  `amount` double NOT NULL,
  `type` enum('VFC_OVERPAYMENT','COURSE_PURCHASE','COLLECTION_PURCHASE','ADMIN_ADJUSTMENT','WALLET_PAYMENT') NOT NULL,
  `description` text DEFAULT NULL,
  `referenceId` varchar(100) DEFAULT NULL,
  `createdAt` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`),
  CONSTRAINT `WalletTransaction_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `User` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `WalletTransaction`
--

LOCK TABLES `WalletTransaction` WRITE;
/*!40000 ALTER TABLE `WalletTransaction` DISABLE KEYS */;
/*!40000 ALTER TABLE `WalletTransaction` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Year`
--

DROP TABLE IF EXISTS `Year`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Year` (
  `id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `image` text DEFAULT NULL,
  `order` int(11) DEFAULT 0,
  `hasSpecializations` tinyint(1) DEFAULT 0,
  `createdAt` datetime DEFAULT current_timestamp(),
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `nahwLink` text DEFAULT NULL,
  `sarfLink` text DEFAULT NULL,
  `balaghaLink` text DEFAULT NULL,
  `adabLink` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Year`
--

LOCK TABLES `Year` WRITE;
/*!40000 ALTER TABLE `Year` DISABLE KEYS */;
INSERT INTO `Year` VALUES ('9Meni','الصف الثالث الثانوى','/api/uploads/5555555555555_1788279601755.jpeg',1,1,'2026-08-01 17:51:46','2026-09-01 16:50:39','','','',''),('d7UYZ','الصف الثانى الثانوى','/api/uploads/2__1788281450571.jpg',2,1,'2026-08-01 17:51:59','2026-09-01 16:50:54','','','',''),('RGKYc','الصف الأول الثانوى','/api/uploads/1__1788281463408.jpg',3,0,'2026-07-23 18:21:42','2026-09-01 16:51:06','','','','');
/*!40000 ALTER TABLE `Year` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-13 21:42:42
