CREATE TABLE IF NOT EXISTS `SITE_NOTICE` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '流水編號',
  `notice_date` DATE NOT NULL COMMENT '首頁顯示日期',
  `type` ENUM('UPDATE','TOURNAMENT','NEWS') NOT NULL DEFAULT 'UPDATE' COMMENT '訊息類型',
  `title` VARCHAR(255) NOT NULL COMMENT '首頁顯示文字',
  `url` VARCHAR(500) DEFAULT NULL COMMENT '一般訊息連結網址',
  `tour_id` INT DEFAULT NULL COMMENT '關聯賽事編號',
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=顯示，0=隱藏',
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=置頂，0=一般',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT '同日期自訂排序，數字大者優先',
  PRIMARY KEY (`id`),
  KEY `idx_site_notice_list` (`is_visible`,`type`,`is_pinned`,`notice_date`,`sort_order`),
  KEY `idx_site_notice_tour` (`tour_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='網站首頁更新與比賽訊息';
