
--
-- Table structure for table `chatbot`
--

DROP TABLE IF EXISTS `chatbot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chatbot` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `queries` longtext NOT NULL,
  `replies` longtext NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chatbot`
--

LOCK TABLES `chatbot` WRITE;
/*!40000 ALTER TABLE `chatbot` DISABLE KEYS */;
INSERT INTO `chatbot` VALUES (1,'Hello','May I help you ?');
/*!40000 ALTER TABLE `chatbot` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `confirmation_code_ips`
--

DROP TABLE IF EXISTS `confirmation_code_ips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `confirmation_code_ips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_id` (`code_id`,`ip_address`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `confirmation_code_ips`
--

LOCK TABLES `confirmation_code_ips` WRITE;
/*!40000 ALTER TABLE `confirmation_code_ips` DISABLE KEYS */;
/*!40000 ALTER TABLE `confirmation_code_ips` ENABLE KEYS */;
UNLOCK TABLES;

CREATE TABLE confirmation_code_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(255) NOT NULL,
    attempt_time DATETIME NOT NULL
);


--
-- Table structure for table `confirmation_codes`
--

DROP TABLE IF EXISTS `confirmation_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `confirmation_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL,
  `ip_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `content` (`content`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `confirmation_codes`
--

LOCK TABLES `confirmation_codes` WRITE;
/*!40000 ALTER TABLE `confirmation_codes` DISABLE KEYS */;
INSERT INTO `confirmation_codes` VALUES (1,'ABC123','active',0),(2,'DEF456','multi-direction warning',0),(3,'XYZ789','disabled',0);
/*!40000 ALTER TABLE `confirmation_codes` ENABLE KEYS */;
UNLOCK TABLES;
