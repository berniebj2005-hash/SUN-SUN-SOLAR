<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Fresh database only. Matches the provided MySQL 8.0 schema.
 * Do NOT run against existing imported schema or the old login_system DB.
 */
class CreateSunSonSolarSchema extends Migration
{
    public function up()
    {
        $tables = ['roles', 'departments', 'users', 'customer_profiles', 'employee_profiles', 'product_categories', 'products', 'services', 'bookings', 'technician_assignments', 'technician_checkins', 'service_reports', 'quote_requests', 'payments', 'receipts', 'support_tickets', 'ticket_messages', 'notifications', 'announcements', 'testimonials', 'faqs', 'blog_posts', 'system_settings', 'audit_logs'];
        foreach ($tables as $table) {
            if ($this->db->tableExists($table)) {
                throw new RuntimeException('Existing table ' . $table . ' detected. Migration cancelled; preserve your data.');
            }
        }
        $sql = [
            <<<'SQL'
CREATE TABLE roles (
  role_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255) NULL
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE departments (
  department_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE users (
  user_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id BIGINT UNSIGNED NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  account_status ENUM('pending','active','inactive') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE customer_profiles (
  customer_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NOT NULL,
  birthdate DATE NULL,
  gender VARCHAR(30) NULL,
  phone_number VARCHAR(30) NULL,
  address TEXT NULL,
  CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE employee_profiles (
  employee_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  department_id BIGINT UNSIGNED NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NOT NULL,
  birthdate DATE NULL,
  gender VARCHAR(30) NULL,
  phone_number VARCHAR(30) NULL,
  address TEXT NULL,
  position_title VARCHAR(100) NULL,
  CONSTRAINT fk_employees_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments(department_id)
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE product_categories (
  category_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE products (
  product_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  product_name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  display_price DECIMAL(12,2) NULL,
  image_path VARCHAR(255) NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES product_categories(category_id)
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE services (
  service_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_name VARCHAR(120) NOT NULL UNIQUE,
  description TEXT NULL,
  base_price DECIMAL(12,2) NULL,
  expected_duration_minutes INT UNSIGNED NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE bookings (
  booking_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  requested_start DATETIME NOT NULL,
  scheduled_start DATETIME NULL,
  scheduled_end DATETIME NULL,
  service_address TEXT NOT NULL,
  notes TEXT NULL,
  booking_status ENUM('pending','approved','declined','cancelled','in_progress','completed') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_id) REFERENCES customer_profiles(customer_id),
  CONSTRAINT fk_bookings_service FOREIGN KEY (service_id) REFERENCES services(service_id),
  CONSTRAINT chk_booking_schedule CHECK (scheduled_end IS NULL OR (scheduled_start IS NOT NULL AND scheduled_end > scheduled_start)),
  INDEX idx_booking_status_date (booking_status, requested_start),
  INDEX idx_booking_customer (customer_id)
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE technician_assignments (
  assignment_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  assigned_by_user_id BIGINT UNSIGNED NULL,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  assignment_status ENUM('assigned','in_progress','completed','removed') NOT NULL DEFAULT 'assigned',
  CONSTRAINT fk_assignments_booking FOREIGN KEY (booking_id) REFERENCES bookings(booking_id),
  CONSTRAINT fk_assignments_employee FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id),
  CONSTRAINT fk_assignments_by FOREIGN KEY (assigned_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  UNIQUE KEY uq_assignment_booking_employee (booking_id, employee_id),
  INDEX idx_assignments_employee (employee_id)
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE technician_checkins (
  checkin_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id BIGINT UNSIGNED NOT NULL,
  checked_in_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  location_accuracy_m DECIMAL(10,2) NULL,
  CONSTRAINT fk_checkins_assignment FOREIGN KEY (assignment_id) REFERENCES technician_assignments(assignment_id),
  CONSTRAINT chk_latitude CHECK (latitude BETWEEN -90 AND 90),
  CONSTRAINT chk_longitude CHECK (longitude BETWEEN -180 AND 180),
  INDEX idx_checkins_assignment_time (assignment_id, checked_in_at)
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE service_reports (
  report_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id BIGINT UNSIGNED NOT NULL,
  issue_type VARCHAR(100) NULL,
  report_text TEXT NOT NULL,
  report_status ENUM('submitted','reviewed','resolved') NOT NULL DEFAULT 'submitted',
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reports_assignment FOREIGN KEY (assignment_id) REFERENCES technician_assignments(assignment_id)
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE quote_requests (
  quote_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NULL,
  service_id BIGINT UNSIGNED NULL,
  contact_name VARCHAR(200) NOT NULL,
  contact_email VARCHAR(255) NOT NULL,
  contact_phone VARCHAR(30) NULL,
  details TEXT NOT NULL,
  quote_status ENUM('new','in_review','responded','closed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_quotes_customer FOREIGN KEY (customer_id) REFERENCES customer_profiles(customer_id) ON DELETE SET NULL,
  CONSTRAINT fk_quotes_service FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE SET NULL
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE payments (
  payment_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'PHP',
  payment_method VARCHAR(60) NULL,
  payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL,
  recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(booking_id),
  CONSTRAINT chk_payment_amount CHECK (amount >= 0)
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE receipts (
  receipt_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id BIGINT UNSIGNED NOT NULL UNIQUE,
  receipt_number VARCHAR(80) NOT NULL UNIQUE,
  issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_receipts_payment FOREIGN KEY (payment_id) REFERENCES payments(payment_id)
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE support_tickets (
  ticket_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  assigned_employee_id BIGINT UNSIGNED NULL,
  subject VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  ticket_status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tickets_customer FOREIGN KEY (customer_id) REFERENCES customer_profiles(customer_id),
  CONSTRAINT fk_tickets_assignee FOREIGN KEY (assigned_employee_id) REFERENCES employee_profiles(employee_id) ON DELETE SET NULL
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE ticket_messages (
  message_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id BIGINT UNSIGNED NOT NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  message_text TEXT NOT NULL,
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_messages_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(ticket_id),
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE notifications (
  notification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  notification_type ENUM('booking','reminder','promotion','system') NOT NULL DEFAULT 'system',
  is_read BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  INDEX idx_notifications_unread (user_id, is_read)
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE announcements (
  announcement_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  body TEXT NOT NULL,
  audience ENUM('all_employees','technicians','customers','all_users') NOT NULL DEFAULT 'all_employees',
  published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_announcements_author FOREIGN KEY (author_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE testimonials (
  testimonial_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NULL,
  display_name VARCHAR(150) NOT NULL,
  quote_text TEXT NOT NULL,
  is_approved BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_testimonials_customer FOREIGN KEY (customer_id) REFERENCES customer_profiles(customer_id) ON DELETE SET NULL
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE faqs (
  faq_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question VARCHAR(255) NOT NULL,
  answer TEXT NOT NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_published BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE blog_posts (
  post_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  content LONGTEXT NOT NULL,
  published_at DATETIME NULL,
  CONSTRAINT fk_posts_author FOREIGN KEY (author_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE system_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
SQL,
            <<<'SQL'
CREATE TABLE audit_logs (
  audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id BIGINT UNSIGNED NULL,
  details TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_audit_action_date (action, created_at)
) ENGINE=InnoDB
SQL
        ];
        // DDL in MySQL commits implicitly; preflight on an empty DB only.
        foreach ($sql as $statement) {
            $this->db->query($statement);
        }
    }

    public function down()
    {
        // Intentionally non-destructive: user, booking, payment and audit history matters.
        throw new RuntimeException('Automatic rollback disabled to protect business records.');
    }
}
