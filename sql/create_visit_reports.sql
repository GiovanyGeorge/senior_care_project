-- Visit reports (Pal -> Admin) for each service/visit
CREATE TABLE IF NOT EXISTS `visit_reports` (
  `report_ID` int(10) NOT NULL AUTO_INCREMENT,
  `visit_ID` int(10) NOT NULL,
  `pal_user_ID` int(10) NOT NULL,
  `summary` varchar(255) DEFAULT NULL,
  `report_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`report_ID`),
  KEY `visit_ID` (`visit_ID`),
  KEY `pal_user_ID` (`pal_user_ID`),
  CONSTRAINT `visit_reports_ibfk_1` FOREIGN KEY (`visit_ID`) REFERENCES `visit_requests` (`visit_ID`) ON DELETE CASCADE,
  CONSTRAINT `visit_reports_ibfk_2` FOREIGN KEY (`pal_user_ID`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

