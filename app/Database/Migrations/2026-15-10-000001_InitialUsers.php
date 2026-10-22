<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Email;

class InitialUSers extends Migration
{
        public function up()
        {
            $table = $this->db->table('users');

            $table->insert([
                'FirstName' => 'Katherine',
                'LastName' => 'Sinagaraw',
                'Email' => 'katherine.sinagaraw@gmail.com',
                'Username' => 'KittyKath26',
                'Password' => password_hash('KittyKath26', PASSWORD_DEFAULT),
            ]);

            $table->insert([
                'FirstName' => 'Sol',
                'LastName' => 'Solis',
                'Email' => 'sol.solis@gmail.com',
                'Username' => 'admin',
                'Password' => password_hash('admin123', PASSWORD_DEFAULT),
            ]);
        }

        public function down()
        {
            $this->db->table('users')->where('Username', 'KittyKath26')->delete();
            $this->db->table('users')->where('Username', 'admin')->delete();
        }
}