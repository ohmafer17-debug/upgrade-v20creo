-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: upgrade_systems_db
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
-- Current Database: `upgrade_systems_db`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `upgrade_systems_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `upgrade_systems_db`;

--
-- Table structure for table `admin_ups`
--

DROP TABLE IF EXISTS `admin_ups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_ups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `email_secundario` varchar(100) DEFAULT NULL,
  `telefono_principal` varchar(20) DEFAULT NULL,
  `telefono_secundario` varchar(20) DEFAULT NULL,
  `pass` varchar(100) NOT NULL,
  `rol` varchar(50) NOT NULL DEFAULT 'Usuario Estándar',
  `estatus` varchar(20) DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_ups`
--

LOCK TABLES `admin_ups` WRITE;
/*!40000 ALTER TABLE `admin_ups` DISABLE KEYS */;
INSERT INTO `admin_ups` VALUES (1,'Mafer Administradora','ohmafer.17@gmail.com',NULL,NULL,NULL,'$2y$12$IBNOsC2xCxtcotNCauRxk.NJtAvN5mGK.ies0/Do7R61u2lGhTHx6','Administrador','Activo','2026-06-24 23:12:02'),(2,'Diego','berckwhiler@gmail.com',NULL,NULL,NULL,'$2y$10$KxJe0dXVMUndOp/OaIqsm.I8jSo3DgfXH3GEeAY9CeEwDHhLY7yJG','Usuario Estándar','Activo','2026-07-07 22:41:45');
/*!40000 ALTER TABLE `admin_ups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documentos_pc`
--

DROP TABLE IF EXISTS `documentos_pc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documentos_pc` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_cod` varchar(50) NOT NULL,
  `tipo_doc` varchar(100) NOT NULL,
  `nombre_personalizado` varchar(150) NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `fecha_subida_sistema` date DEFAULT curdate(),
  `estatus` int(11) DEFAULT 1,
  `nombre_archivo_fisico` varchar(255) NOT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp(),
  `subido_por` varchar(255) DEFAULT NULL,
  `actualizado_por` varchar(255) DEFAULT NULL,
  `visto_por` varchar(255) DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `notificar_correos` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documentos_pc`
--

LOCK TABLES `documentos_pc` WRITE;
/*!40000 ALTER TABLE `documentos_pc` DISABLE KEYS */;
INSERT INTO `documentos_pc` VALUES (1,'CONS-01','OTRO','LICENCIA_prueba [Reg: 2026-06-26 03:18:18]','2027-09-24','2026-06-26',1,'23cd65cad4391703cc8f698e3e9d2a4c.png','2026-06-26 03:19:02','CONSULTORIA PC',NULL,'CONSULTORIA PC',NULL,NULL),(2,'CONS-1bf5','Programa Interno de PC','ejemplo [Reg: 2026-06-26 05:46:47]','2026-07-09','2026-06-26',1,'06ddb0d1e26bc0ff21dacb93e00fa49b.png','2026-06-26 05:47:11','Fernanda Oliva',NULL,'Fernanda Oliva',NULL,NULL),(8,'CONS-02','Programa Interno de PC','test01| [Reg: 2026-07-06 00:02:13]','2027-08-15','2026-07-06',1,'4e1307e118db4225c76c1a7a4a151c3e.png','2026-07-06 04:48:24','Consultoría Alfa','Consultoría Alfa','Consultoría Alfa','lo usare',NULL),(9,'CONS-02','dictamen','test [Reg: 2026-07-06 00:14:21]','2025-09-06','2026-07-06',1,'1610e6f368a65d3a5a9058cd97efb8ce.png','2026-07-06 06:14:21','Consultoría Alfa',NULL,NULL,'',NULL),(10,'Roto123','Programa Interno de PC','Nueva version 2026 [Reg: 2026-07-07 22:52:17]','2026-07-07','2026-07-07',1,'ffb17a72bed3e1a75758868e2579aac2.jpg','2026-07-07 22:53:10','Porsche México',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `documentos_pc` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `empresas_clientes`
--

DROP TABLE IF EXISTS `empresas_clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empresas_clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cod` varchar(10) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `email_adicional` varchar(100) DEFAULT NULL,
  `telefono_principal` varchar(20) DEFAULT NULL,
  `telefono_adicional` varchar(20) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `coordenadas` varchar(100) DEFAULT NULL,
  `pass` varchar(255) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `rol` varchar(100) DEFAULT 'Socio Fundador',
  `logo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cod` (`cod`),
  UNIQUE KEY `email` (`email`),
  KEY `staff_id` (`staff_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `empresas_clientes`
--

LOCK TABLES `empresas_clientes` WRITE;
/*!40000 ALTER TABLE `empresas_clientes` DISABLE KEYS */;
INSERT INTO `empresas_clientes` VALUES (1,'GIRD-01','GIRD CONSULTORIA','rocadi@gmail.com','','7224612052','','Alamo norte 113, 0','19.2762066,-99.6675493','$2y$10$M2nBPJO8m9dfSccjbaePFODhp1TEacpuFOVY2lRoHhxMnVr4GykOm',NULL,1,'Consultor',NULL),(2,'CONS-01','CONSULTORIA PC','rocalo@gmail.com','','7224612052','','Alamo norte 113, 0','19.2762066,-99.6675493','$2y$10$rTSUEyLP6vMJqiIUpQ3fvueCfIpbBndTzg5YNmdC2MrWvUEUjwree',NULL,1,'Consultor',NULL),(3,'CONS-2b63','Fernando Perez','fernando@empresa.com','fernando@personal.com','1234567890','7221472589',NULL,NULL,'$2y$10$iiGDQHd4ZRCTvcZEw8kyqOXm6mPA2AEOGtnGQ5rCrwgzmoiAhN8su',NULL,1,'Tipo 2',NULL),(4,'CONS-1bf5','Fernanda Oliva','ferss@principal.com','ferss@personal.com','7894556123','7418529663',NULL,NULL,'$2y$10$elYI32m6FATmQ/tNxqRDYefKV8B.W1uO3rwPLS5fcvBAukPBSni.C',NULL,1,'Responsable Nacional',NULL),(5,'CONS-02','Consultoría Alfa','alvaro@principal.com','alvaro@secundario.com','1478523690','1478523690','Alamo norte 113, 0','19.3061712,-99.5949204','$2y$10$mJYSga.llC5UzIrt7tZYHOke6wrfX0ugohkpc6ZpnnhI9uzMofeQe',NULL,1,'Consultor','1925fedf40427fbd5df4b4b362d13357.jpg'),(10,'Roto123','Porsche México','Porsche@empresa.com','berckwhiler@gmail.com','7225640952','','Galeana 411 Col.Francisco Murguía','19.2808824,-99.6563795','$2y$10$ZlLNMJb8zfKEaB5qHdMsPubf98MkfaZBz/PTCwhNyVteY6LKwv3M2',NULL,1,'Consultor','0355e707f4409060c4008cd43b414d41.jpg');
/*!40000 ALTER TABLE `empresas_clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_documentos`
--

DROP TABLE IF EXISTS `historial_documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historial_documentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `documento_id` int(11) NOT NULL,
  `empresa_cod` varchar(50) NOT NULL,
  `tipo_doc` varchar(100) NOT NULL,
  `nombre_personalizado` varchar(150) NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `nombre_archivo_fisico` varchar(255) NOT NULL,
  `subido_por` varchar(255) DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historial_documentos`
--

LOCK TABLES `historial_documentos` WRITE;
/*!40000 ALTER TABLE `historial_documentos` DISABLE KEYS */;
INSERT INTO `historial_documentos` VALUES (1,8,'CONS-02','Programa Interno de PC','test [Reg: 2026-07-06 04:45:15]','2025-08-15','aa189244ed9b27c01b8d41febcd216ef.png',NULL,'lo usare','2026-07-06 06:02:13');
/*!40000 ALTER TABLE `historial_documentos` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-16 21:43:02
