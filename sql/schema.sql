CREATE TABLE `{prefix}settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `key_name` VARCHAR(80) NOT NULL UNIQUE,
  `value_text` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `{prefix}users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(190) NOT NULL UNIQUE,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `timezone` VARCHAR(60) NOT NULL DEFAULT 'UTC',
  `country_code` VARCHAR(3) NOT NULL DEFAULT 'ALL',
  `credits` INT NOT NULL DEFAULT 0,
  `exchange_ratio` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `click_bonus` INT NOT NULL DEFAULT 1,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `is_approved` TINYINT(1) NOT NULL DEFAULT 1,
  `agree_rules` TINYINT(1) NOT NULL DEFAULT 0,
  `email_verified_at` DATETIME NULL,
  `verify_token` VARCHAR(80) NULL,
  `reset_token` VARCHAR(80) NULL,
  `reset_expires_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `{prefix}moderators` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `rights_json` JSON NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `{prefix}users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `{prefix}categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `size_key` VARCHAR(20) NOT NULL,
  `name` VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `{prefix}banner_sizes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `size_key` VARCHAR(20) NOT NULL UNIQUE,
  `width` INT NOT NULL,
  `height` INT NOT NULL,
  `exchange_ratio` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `max_banners_per_user` INT NOT NULL DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `{prefix}banners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `size_key` VARCHAR(20) NOT NULL,
  `category_id` INT NOT NULL,
  `type` ENUM('gif','jpg','png','swf','html') NOT NULL,
  `image_url` VARCHAR(255) NULL,
  `html_code` TEXT NULL,
  `target_url` VARCHAR(255) NULL,
  `alt_text` VARCHAR(180) NOT NULL DEFAULT '',
  `countries` VARCHAR(255) NOT NULL DEFAULT 'ALL',
  `allowed_days` VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5,6,7',
  `start_hour` TINYINT NOT NULL DEFAULT 0,
  `end_hour` TINYINT NOT NULL DEFAULT 23,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_sponsored` TINYINT(1) NOT NULL DEFAULT 0,
  `priority` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `{prefix}users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `{prefix}impressions` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `banner_id` INT NOT NULL,
  `viewer_user_id` INT NOT NULL,
  `event_token` VARCHAR(64) NOT NULL UNIQUE,
  `ip` VARCHAR(45) NOT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX (`banner_id`),
  INDEX (`viewer_user_id`),
  INDEX (`created_at`),
  FOREIGN KEY (`banner_id`) REFERENCES `{prefix}banners`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `{prefix}clicks` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `banner_id` INT NOT NULL,
  `event_token` VARCHAR(64) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX (`banner_id`),
  INDEX (`event_token`),
  INDEX (`created_at`),
  FOREIGN KEY (`banner_id`) REFERENCES `{prefix}banners`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `{prefix}credit_ledger` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `amount` INT NOT NULL,
  `reason` VARCHAR(80) NOT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `{prefix}users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `{prefix}email_campaigns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_line` VARCHAR(255) NOT NULL,
  `body_text` MEDIUMTEXT NOT NULL,
  `status` ENUM('draft','sending','sent') NOT NULL DEFAULT 'draft',
  `sent_count` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `sent_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
