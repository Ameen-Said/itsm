-- ============================================================
-- IT Manager Pro — FIXED Database Schema
-- Safe to run multiple times (IF NOT EXISTS + IF EXISTS)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = '';
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `itsm_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `itsm_db`;

-- ── Roles & Permissions ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `roles` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(50)  NOT NULL UNIQUE,
  `description` VARCHAR(255),
  `is_system`   TINYINT(1)   DEFAULT 0,
  `color`       VARCHAR(20)  DEFAULT 'primary',
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `module` VARCHAR(50)  NOT NULL,
  `action` ENUM('view','add','edit','delete','export') NOT NULL,
  `label`  VARCHAR(100) NOT NULL,
  UNIQUE KEY `module_action` (`module`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  FOREIGN KEY (`role_id`)       REFERENCES `roles`(`id`)       ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Departments ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `departments` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL,
  `name_ar`     VARCHAR(100),
  `description` TEXT,
  `manager_id`  INT UNSIGNED NULL,
  `budget`      DECIMAL(15,2) DEFAULT 0,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Users ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username`       VARCHAR(50)  NOT NULL UNIQUE,
  `email`          VARCHAR(150) NOT NULL UNIQUE,
  `password_hash`  VARCHAR(255) NOT NULL,
  `role_id`        INT UNSIGNED NOT NULL,
  `department_id`  INT UNSIGNED NULL,
  `full_name`      VARCHAR(150) NOT NULL,
  `phone`          VARCHAR(30),
  `job_title`      VARCHAR(100),
  `avatar`         VARCHAR(255),
  `employee_id`    VARCHAR(50)  UNIQUE,
  `status`         ENUM('active','inactive','suspended') DEFAULT 'active',
  `vault_key_hash` VARCHAR(255) NULL,
  `vault_salt`     VARCHAR(64)  NULL,
  `theme`          ENUM('light','dark') DEFAULT 'light',
  `language`       ENUM('en','ar')      DEFAULT 'en',
  `last_login`     TIMESTAMP NULL,
  `login_attempts` TINYINT  DEFAULT 0,
  `locked_until`   TIMESTAMP NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`)       REFERENCES `roles`(`id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add manager FK safely (may already exist)
ALTER TABLE `departments`
  ADD COLUMN IF NOT EXISTS `manager_id_fk_added` TINYINT DEFAULT 0;
ALTER TABLE `departments`
  DROP COLUMN IF EXISTS `manager_id_fk_added`;

-- Safe FK add using procedure
DROP PROCEDURE IF EXISTS `add_dept_manager_fk`;
DELIMITER ;;
CREATE PROCEDURE `add_dept_manager_fk`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'departments'
      AND CONSTRAINT_NAME = 'fk_dept_manager'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE `departments`
      ADD CONSTRAINT `fk_dept_manager`
      FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;
  END IF;
END;;
DELIMITER ;
CALL `add_dept_manager_fk`();
DROP PROCEDURE IF EXISTS `add_dept_manager_fk`;

-- ── Settings ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `key`        VARCHAR(100) NOT NULL PRIMARY KEY,
  `value`      TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Vendors ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `vendors` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`         VARCHAR(150) NOT NULL,
  `contact_name` VARCHAR(100),
  `email`        VARCHAR(150),
  `phone`        VARCHAR(30),
  `address`      TEXT,
  `website`      VARCHAR(255),
  `notes`        TEXT,
  `status`       ENUM('active','inactive') DEFAULT 'active',
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Asset Categories ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `asset_categories` (
  `id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `icon` VARCHAR(50)  DEFAULT 'bi-cpu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Assets ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `assets` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `asset_code`      VARCHAR(50)  NOT NULL UNIQUE,
  `barcode`         VARCHAR(100) UNIQUE,
  `category_id`     INT UNSIGNED NULL,
  `name`            VARCHAR(150) NOT NULL,
  `brand`           VARCHAR(100),
  `model`           VARCHAR(100),
  `serial_number`   VARCHAR(100),
  `purchase_date`   DATE,
  `warranty_expiry` DATE,
  `price`           DECIMAL(12,2) DEFAULT 0,
  `vendor_id`       INT UNSIGNED NULL,
  `department_id`   INT UNSIGNED NULL,
  `assigned_to`     INT UNSIGNED NULL,
  `status`          ENUM('available','assigned','maintenance','retired') DEFAULT 'available',
  `location`        VARCHAR(150),
  `notes`           TEXT,
  `image`           VARCHAR(255),
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`)   REFERENCES `asset_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`vendor_id`)     REFERENCES `vendors`(`id`)           ON DELETE SET NULL,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)       ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`)   REFERENCES `users`(`id`)             ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `asset_assignments` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `asset_id`    INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NULL,
  `assigned_by` INT UNSIGNED NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `returned_at` TIMESTAMP NULL,
  `notes`       TEXT,
  FOREIGN KEY (`asset_id`)    REFERENCES `assets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)  ON DELETE SET NULL,
  FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Licenses ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `licenses` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `software_name` VARCHAR(150) NOT NULL,
  `license_key`   TEXT,
  `type`          ENUM('per_user','per_device','enterprise','subscription','open_source') DEFAULT 'per_user',
  `seats`         INT UNSIGNED DEFAULT 1,
  `seats_used`    INT UNSIGNED DEFAULT 0,
  `vendor_id`     INT UNSIGNED NULL,
  `purchase_date` DATE,
  `expiry_date`   DATE,
  `price`         DECIMAL(12,2) DEFAULT 0,
  `notes`         TEXT,
  `status`        ENUM('active','expired','cancelled') DEFAULT 'active',
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `license_assignments` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `license_id`  INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NULL,
  `asset_id`    INT UNSIGNED NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes`       TEXT,
  FOREIGN KEY (`license_id`) REFERENCES `licenses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE SET NULL,
  FOREIGN KEY (`asset_id`)   REFERENCES `assets`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Document Folders ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `document_folders` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(150) NOT NULL,
  `parent_id`   INT UNSIGNED NULL,
  `description` TEXT,
  `created_by`  INT UNSIGNED NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`parent_id`)  REFERENCES `document_folders`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)            ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Documents ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `documents` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `folder_id`     INT UNSIGNED NULL,
  `title`         VARCHAR(255) NOT NULL,
  `description`   TEXT,
  `category`      ENUM('contract','manual','invoice','policy','other') DEFAULT 'other',
  `filename`      VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `file_size`     INT UNSIGNED DEFAULT 0,
  `mime_type`     VARCHAR(100),
  `version`       VARCHAR(20)  DEFAULT '1.0',
  `asset_id`      INT UNSIGNED NULL,
  `user_id`       INT UNSIGNED NULL,
  `vendor_id`     INT UNSIGNED NULL,
  `uploaded_by`   INT UNSIGNED NOT NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`folder_id`)   REFERENCES `document_folders`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`asset_id`)    REFERENCES `assets`(`id`)           ON DELETE SET NULL,
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)            ON DELETE SET NULL,
  FOREIGN KEY (`vendor_id`)   REFERENCES `vendors`(`id`)          ON DELETE SET NULL,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)            ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Password Vault ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `vault_entries` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT UNSIGNED NOT NULL,
  `system_name`  VARCHAR(150) NOT NULL,
  `url`          VARCHAR(500),
  `username`     VARCHAR(150),
  `password_enc` TEXT         NOT NULL,
  `iv`           VARCHAR(64)  NOT NULL,
  `notes_enc`    TEXT,
  `notes_iv`     VARCHAR(64),
  `category`     VARCHAR(50)  DEFAULT 'general',
  `is_favourite` TINYINT(1)   DEFAULT 0,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_vault_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Email ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `email_configs` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `smtp_host`  VARCHAR(255),
  `smtp_port`  SMALLINT UNSIGNED DEFAULT 587,
  `smtp_user`  VARCHAR(150),
  `smtp_pass`  VARCHAR(255),
  `from_email` VARCHAR(150),
  `from_name`  VARCHAR(100),
  `is_default` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `email_logs` (
  `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `to_email`  VARCHAR(150) NOT NULL,
  `subject`   VARCHAR(255) NOT NULL,
  `body`      TEXT,
  `status`    ENUM('sent','failed','pending') DEFAULT 'pending',
  `error_msg` TEXT,
  `sent_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Notifications ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NULL,
  `title`      VARCHAR(255) NOT NULL,
  `message`    TEXT         NOT NULL,
  `type`       ENUM('info','warning','danger','success') DEFAULT 'info',
  `is_read`    TINYINT(1)   DEFAULT 0,
  `link`       VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_notif_user` (`user_id`,`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Audit Logs ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NULL,
  `action`     VARCHAR(100) NOT NULL,
  `module`     VARCHAR(50)  NOT NULL,
  `record_id`  INT UNSIGNED NULL,
  `old_values` JSON,
  `new_values` JSON,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(500),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_audit_module` (`module`,`created_at`),
  INDEX `idx_audit_user`   (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Purchase Orders ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `po_number`     VARCHAR(50) NOT NULL UNIQUE,
  `vendor_id`     INT UNSIGNED NULL,
  `department_id` INT UNSIGNED NULL,
  `total_amount`  DECIMAL(15,2) DEFAULT 0,
  `status`        ENUM('draft','approved','received','cancelled') DEFAULT 'draft',
  `ordered_at`    DATE,
  `received_at`   DATE NULL,
  `notes`         TEXT,
  `created_by`    INT UNSIGNED NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`vendor_id`)     REFERENCES `vendors`(`id`)     ON DELETE SET NULL,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`)    REFERENCES `users`(`id`)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTE: total_price is a regular column (no GENERATED ALWAYS - breaks INSERT)
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `po_id`       INT UNSIGNED NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `quantity`    INT UNSIGNED DEFAULT 1,
  `unit_price`  DECIMAL(12,2) DEFAULT 0,
  `total_price` DECIMAL(12,2) DEFAULT 0,
  FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Seed Permissions ──────────────────────────────────────
INSERT IGNORE INTO `permissions` (`module`,`action`,`label`) VALUES
('dashboard','view','View Dashboard'),
('users','view','View Users'),('users','add','Add Users'),('users','edit','Edit Users'),('users','delete','Delete Users'),('users','export','Export Users'),
('roles','view','View Roles'),('roles','add','Add Roles'),('roles','edit','Edit Roles'),('roles','delete','Delete Roles'),
('departments','view','View Departments'),('departments','add','Add Departments'),('departments','edit','Edit Departments'),('departments','delete','Delete Departments'),
('assets','view','View Assets'),('assets','add','Add Assets'),('assets','edit','Edit Assets'),('assets','delete','Delete Assets'),('assets','export','Export Assets'),
('licenses','view','View Licenses'),('licenses','add','Add Licenses'),('licenses','edit','Edit Licenses'),('licenses','delete','Delete Licenses'),('licenses','export','Export Licenses'),
('vendors','view','View Vendors'),('vendors','add','Add Vendors'),('vendors','edit','Edit Vendors'),('vendors','delete','Delete Vendors'),
('documents','view','View Documents'),('documents','add','Upload Documents'),('documents','edit','Edit Documents'),('documents','delete','Delete Documents'),
('vault','view','View Own Vault'),('vault','add','Add Vault Entries'),('vault','edit','Edit Vault Entries'),('vault','delete','Delete Vault Entries'),
('email','view','View Email Logs'),('email','add','Send Emails'),
('procurement','view','View Procurement'),('procurement','add','Create POs'),('procurement','edit','Edit POs'),('procurement','delete','Delete POs'),
('reports','view','View Reports'),('reports','export','Export Reports'),
('audit','view','View Audit Logs'),
('settings','view','View Settings'),('settings','edit','Edit Settings');

-- ── Seed Roles ────────────────────────────────────────────
INSERT IGNORE INTO `roles` (`id`,`name`,`description`,`is_system`,`color`) VALUES
(1,'Administrator','Full system access',1,'danger'),
(2,'IT Manager','IT operations management',0,'primary'),
(3,'IT Staff','IT support staff',0,'info'),
(4,'HR','Human resources',0,'success'),
(5,'Viewer','Read-only access',0,'secondary');

-- Admin gets all permissions
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
  SELECT 1, `id` FROM `permissions`;

-- IT Manager: everything except delete roles
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
  SELECT 2, `id` FROM `permissions`
  WHERE NOT (`module` = 'roles' AND `action` = 'delete')
    AND NOT (`module` = 'settings' AND `action` = 'edit');

-- IT Staff: assets, licenses, documents, vault (view/add/edit)
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
  SELECT 3, `id` FROM `permissions`
  WHERE `module` IN ('dashboard','assets','licenses','documents','vault')
    AND `action` IN ('view','add','edit');

-- HR: users, departments
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
  SELECT 4, `id` FROM `permissions`
  WHERE `module` IN ('dashboard','users','departments')
    AND `action` IN ('view','add','edit');

-- Viewer: view only on safe modules
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
  SELECT 5, `id` FROM `permissions`
  WHERE `action` = 'view'
    AND `module` IN ('dashboard','assets','licenses','departments','vendors');

-- ── Seed Departments ──────────────────────────────────────
INSERT IGNORE INTO `departments` (`id`,`name`,`description`) VALUES
(1,'Information Technology','IT Department'),
(2,'Human Resources','HR Department'),
(3,'Finance','Finance Department'),
(4,'Operations','Operations Department');

-- ── Seed Admin User  (password: Admin@1234) ───────────────
INSERT IGNORE INTO `users`
  (`id`,`username`,`email`,`password_hash`,`role_id`,`department_id`,`full_name`,`job_title`,`employee_id`,`status`)
VALUES
  (1,'admin','admin@company.com',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   1,1,'System Administrator','IT Administrator','EMP001','active');

-- ── Seed Asset Categories ─────────────────────────────────
INSERT IGNORE INTO `asset_categories` (`name`,`icon`) VALUES
('Laptop','bi-laptop'),('Desktop','bi-pc-display'),('Server','bi-server'),
('Printer','bi-printer'),('Network Equipment','bi-router'),('Monitor','bi-display'),
('Phone','bi-phone'),('Tablet','bi-tablet'),('Other','bi-box');

-- ── Seed Settings ─────────────────────────────────────────
INSERT IGNORE INTO `settings` (`key`,`value`) VALUES
('company_name','My Company'),
('company_logo',''),
('date_format','d M Y'),
('currency','USD'),
('timezone','UTC'),
('session_timeout','60'),
('warranty_alert_days','30'),
('license_alert_days','30'),
('pagination_limit','25'),
('maintenance_mode','0'),
('default_language','en');

-- ── Seed Default Document Folder ─────────────────────────
INSERT IGNORE INTO `document_folders` (`id`,`name`,`description`) VALUES
(1,'General','Default folder');

-- ── Add missing columns to existing installs ─────────────
-- vault_entries.notes_iv (may not exist in older installs)
ALTER TABLE `vault_entries`
  ADD COLUMN IF NOT EXISTS `notes_iv` VARCHAR(64) NULL AFTER `notes_enc`;

-- users.language (may not exist)
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `language` ENUM('en','ar') DEFAULT 'en' AFTER `theme`;

-- asset_categories.icon (may not exist)
ALTER TABLE `asset_categories`
  ADD COLUMN IF NOT EXISTS `icon` VARCHAR(50) DEFAULT 'bi-cpu';

-- documents.folder_id (may not exist)
ALTER TABLE `documents`
  ADD COLUMN IF NOT EXISTS `folder_id` INT UNSIGNED NULL AFTER `id`;

-- settings table (may not exist in v1)
CREATE TABLE IF NOT EXISTS `settings` (
  `key`        VARCHAR(100) NOT NULL PRIMARY KEY,
  `value`      TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `settings` (`key`,`value`) VALUES
('company_name','My Company'),('company_logo',''),('date_format','d M Y'),
('currency','USD'),('timezone','UTC'),('session_timeout','60'),
('warranty_alert_days','30'),('license_alert_days','30'),
('pagination_limit','25'),('maintenance_mode','0'),('default_language','en');

-- document_folders table (may not exist in v1)
CREATE TABLE IF NOT EXISTS `document_folders` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(150) NOT NULL,
  `parent_id`   INT UNSIGNED NULL,
  `description` TEXT,
  `created_by`  INT UNSIGNED NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `document_folders` (`id`,`name`,`description`) VALUES (1,'General','Default folder');
