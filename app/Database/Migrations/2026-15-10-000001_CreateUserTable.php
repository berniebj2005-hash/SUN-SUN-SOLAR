<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUSersTable extends Migration
{
        public function up()
        {
            $this->forge->addField([
                'ID'=> [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,

                ],
                'FirstName' =>[
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'LastName' =>[
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'Email' =>[
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'Username' =>[
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'Password' =>[
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
            ]);

            $this->forge->addKey('ID', true);
            $this->forge->createTable('users');
        }

        public function down()
        {
            $this->forge->dropTable('users');
        }
}