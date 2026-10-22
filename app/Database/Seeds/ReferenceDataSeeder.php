<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run()
    {
        $statements = [
            <<<'SQL'
INSERT IGNORE INTO roles (role_name, description) VALUES
('admin','System administrator'),
('customer','Customer account'),
('hr','Human resources employee'),
('dispatcher','Booking and technician coordinator'),
('technician','Assigned field technician')
SQL,
            <<<'SQL'
INSERT IGNORE INTO departments (department_name) VALUES
('Administration'),('IT'),('Dispatch'),('Accounting'),('HR'),
('Marketing'),('Sales'),('Customer Service')
SQL,
            <<<'SQL'
INSERT IGNORE INTO product_categories (category_name) VALUES
('Solar Panels'),('Inverters'),('Batteries'),('Racking and Mounting'),('Wires')
SQL,
            <<<'SQL'
INSERT IGNORE INTO services (service_name) VALUES
('Consultation'),('Designing'),('Permitting'),('Installation'),
('Maintenance'),('Repair'),('Monitoring')
SQL
        ];
        foreach ($statements as $statement) {
            $this->db->query($statement);
        }
    }
}
