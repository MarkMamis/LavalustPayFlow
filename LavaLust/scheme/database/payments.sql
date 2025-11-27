-- Simplified payments table for PayFlow / LavaLust
-- Created: 2025-11-26
-- Simple schema intended for basic recording of disbursements

CREATE TABLE `payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payroll_id` BIGINT UNSIGNED DEFAULT NULL,
  `employee_id` BIGINT UNSIGNED DEFAULT NULL,
  `amount` DECIMAL(14,2) NOT NULL COMMENT 'Amount in major units (e.g., 18.50)',
  `currency` VARCHAR(8) NOT NULL COMMENT 'ISO currency code (e.g., USD)',
  `amount_minor` BIGINT NOT NULL COMMENT 'Amount in minor units (e.g., cents) sent to provider',
  `stripe_payment_id` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_stripe_payment_id` (`stripe_payment_id`),
  KEY `idx_payroll_id` (`payroll_id`),
  KEY `idx_employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example INSERT (adjust values as needed)
INSERT INTO `payments` (
  `payroll_id`, `employee_id`, `amount`, `currency`, `amount_minor`, `stripe_payment_id`, `status`, `notes`
) VALUES (
  123, 45, 18.50, 'USD', 1850, 'pi_test_1Example', 'succeeded', 'Sample simplified row'
);

-- Import note:
-- mysql -u root -p your_database < "C:\\xampp2\\htdocs\\PayFlowNew\\LavaLust\\scheme\\database\\payments.sql"
