-- Sun Son Solar incremental backend expansion. Requires migration 002.
-- Do not apply until schema preflight in README is clean. Review and run manually against a disposable database first.
-- This migration preserves rows and does not seed production users or business data.

ALTER TABLE employee_db ADD COLUMN Birth_date DATE NULL;
ALTER TABLE services ADD COLUMN category VARCHAR(80) NOT NULL DEFAULT 'General';
ALTER TABLE bookings ADD COLUMN duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60;
ALTER TABLE notifications MODIFY recipient_type ENUM('customer','employee','all_employees','department') NOT NULL;
ALTER TABLE notifications
  ADD COLUMN recipient_department VARCHAR(80) NULL,
  ADD COLUMN created_by_employee_id INT NULL,
  ADD CONSTRAINT fk_notification_creator FOREIGN KEY (created_by_employee_id) REFERENCES employee_db(Employee_ID);
ALTER TABLE technician_assignments ADD KEY ix_assignment_slot (employee_id, status, assigned_at);
ALTER TABLE technician_checkins ADD KEY ix_checkin_time (checked_in_at, employee_id);

CREATE TABLE service_availability (
  availability_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_id INT UNSIGNED NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday through 6=Saturday',
  opens_at TIME NOT NULL,
  closes_at TIME NOT NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (availability_id),
  UNIQUE KEY uq_service_weekday_open (service_id, day_of_week, opens_at),
  CONSTRAINT fk_availability_service FOREIGN KEY (service_id) REFERENCES services(service_id),
  CONSTRAINT chk_availability_day CHECK (day_of_week BETWEEN 0 AND 6),
  CONSTRAINT chk_availability_window CHECK (closes_at > opens_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  payment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id BIGINT UNSIGNED NOT NULL,
  customer_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL,
  method VARCHAR(40) NOT NULL,
  reference VARCHAR(120) NULL,
  status ENUM('paid','pending','void','refunded') NOT NULL DEFAULT 'paid',
  transaction_at DATETIME NOT NULL,
  created_by_role VARCHAR(24) NOT NULL,
  created_by_account_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (payment_id),
  KEY ix_payment_customer_date (customer_id, transaction_at),
  KEY ix_payment_revenue (status, transaction_at),
  CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(booking_id),
  CONSTRAINT fk_payment_customer FOREIGN KEY (customer_id) REFERENCES customer_information(User_ID),
  CONSTRAINT chk_payment_amount CHECK (amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE receipts (
  receipt_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id BIGINT UNSIGNED NOT NULL,
  receipt_number VARCHAR(40) NOT NULL,
  issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (receipt_id),
  UNIQUE KEY uq_receipt_payment (payment_id),
  UNIQUE KEY uq_receipt_number (receipt_number),
  CONSTRAINT fk_receipt_payment FOREIGN KEY (payment_id) REFERENCES payments(payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Preflight reports for existing data. Resolve conflicts manually before applying 002's unique constraints.
-- SELECT Username, COUNT(*) AS n FROM customer_information GROUP BY Username HAVING n > 1;
-- SELECT Email, COUNT(*) AS n FROM customer_information GROUP BY Email HAVING n > 1;
-- SELECT Username, COUNT(*) AS n FROM employee_db GROUP BY Username HAVING n > 1;
-- SELECT Email, COUNT(*) AS n FROM employee_db GROUP BY Email HAVING n > 1;
-- SELECT Username, COUNT(*) AS n FROM admin_db GROUP BY Username HAVING n > 1;
