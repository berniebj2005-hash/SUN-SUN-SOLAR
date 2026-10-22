-- Sun Son Solar backend expansion (MariaDB 10.4+).
-- Review existing duplicates before adding the unique indexes below; do not run against production without a backup.

ALTER TABLE customer_information
  MODIFY Email VARCHAR(254) NOT NULL,
  MODIFY Phone_Number VARCHAR(24) NOT NULL,
  MODIFY Username VARCHAR(50) NOT NULL,
  MODIFY Password VARCHAR(255) NOT NULL,
  ADD COLUMN Account_Status VARCHAR(16) NOT NULL DEFAULT 'Active',
  ADD UNIQUE KEY uq_customer_username (Username),
  ADD UNIQUE KEY uq_customer_email (Email);

ALTER TABLE employee_db
  MODIFY Email VARCHAR(254) NOT NULL,
  MODIFY Phone_Number VARCHAR(24) NOT NULL,
  MODIFY Username VARCHAR(50) NOT NULL,
  MODIFY Password VARCHAR(255) NOT NULL,
  ADD COLUMN Account_Status VARCHAR(16) NOT NULL DEFAULT 'Active',
  ADD COLUMN Role VARCHAR(24) NOT NULL DEFAULT 'employee',
  ADD UNIQUE KEY uq_employee_username (Username),
  ADD UNIQUE KEY uq_employee_email (Email);

ALTER TABLE admin_db
  MODIFY Password VARCHAR(255) NOT NULL,
  ADD COLUMN Account_Status VARCHAR(16) NOT NULL DEFAULT 'Active',
  ADD UNIQUE KEY uq_admin_username (Username);

CREATE TABLE services (
  service_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10,2) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (service_id),
  KEY ix_services_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bookings (
  booking_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  requested_at DATETIME NOT NULL,
  address TEXT NOT NULL,
  details TEXT NOT NULL,
  status ENUM('Pending','Approved','Assigned','In Progress','Completed','Cancelled','Declined','Rescheduled') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (booking_id),
  KEY ix_bookings_customer (customer_id, created_at),
  KEY ix_bookings_schedule (requested_at, status),
  CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_id) REFERENCES customer_information(User_ID),
  CONSTRAINT fk_bookings_service FOREIGN KEY (service_id) REFERENCES services(service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE technician_assignments (
  assignment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id BIGINT UNSIGNED NOT NULL,
  employee_id INT NOT NULL,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('Assigned','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Assigned',
  PRIMARY KEY (assignment_id),
  UNIQUE KEY uq_booking_employee (booking_id, employee_id),
  KEY ix_assignment_employee (employee_id, status),
  CONSTRAINT fk_assignment_booking FOREIGN KEY (booking_id) REFERENCES bookings(booking_id),
  CONSTRAINT fk_assignment_employee FOREIGN KEY (employee_id) REFERENCES employee_db(Employee_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE technician_checkins (
  checkin_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id INT NOT NULL,
  assignment_id BIGINT UNSIGNED NOT NULL,
  checked_in_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  accuracy DECIMAL(8,2) NULL,
  PRIMARY KEY (checkin_id),
  UNIQUE KEY uq_assignment_checkin (assignment_id),
  CONSTRAINT fk_checkin_employee FOREIGN KEY (employee_id) REFERENCES employee_db(Employee_ID),
  CONSTRAINT fk_checkin_assignment FOREIGN KEY (assignment_id) REFERENCES technician_assignments(assignment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE technician_reports (
  report_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  assignment_id BIGINT UNSIGNED NOT NULL,
  category ENUM('Equipment issue','Installation problem','Maintenance concern','Customer concern','Safety issue','Other') NOT NULL,
  description TEXT NOT NULL,
  status ENUM('Open','Reviewing','Resolved') NOT NULL DEFAULT 'Open',
  reviewer_employee_id INT NULL,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at TIMESTAMP NULL,
  PRIMARY KEY (report_id),
  KEY ix_report_status (status, submitted_at),
  CONSTRAINT fk_report_assignment FOREIGN KEY (assignment_id) REFERENCES technician_assignments(assignment_id),
  CONSTRAINT fk_report_reviewer FOREIGN KEY (reviewer_employee_id) REFERENCES employee_db(Employee_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipient_type ENUM('customer','employee','all_employees') NOT NULL,
  recipient_id INT NULL,
  title VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (notification_id),
  KEY ix_notifications_recipient (recipient_type, recipient_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE support_tickets (
  ticket_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT NOT NULL,
  subject VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'General',
  status ENUM('Open','In Progress','Resolved','Closed') NOT NULL DEFAULT 'Open',
  response TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (ticket_id),
  KEY ix_ticket_customer (customer_id, created_at),
  KEY ix_ticket_status (status, created_at),
  CONSTRAINT fk_ticket_customer FOREIGN KEY (customer_id) REFERENCES customer_information(User_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_log (
  audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_type VARCHAR(24) NOT NULL,
  actor_id INT NOT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id BIGINT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (audit_id),
  KEY ix_audit_entity (entity_type, entity_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
