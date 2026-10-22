<?php
namespace App\Commands;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\BaseCommand;
class BootstrapAdmin extends BaseCommand
{
    protected $group='Sun Son Solar';
    protected $name='solar:create-admin';
    protected $description='Create an initial administrator interactively (CLI only).';
    public function run(array $params)
    {
        $db=db_connect();
        $role=$db->table('roles')->where('role_name','admin')->get()->getRowArray();
        if (!$role) { CLI::error('Import database/schema.sql first.');return; }
        $username=trim(CLI::prompt('Admin username'));
        $email=trim(CLI::prompt('Admin email'));
        $password=CLI::prompt('Strong admin password (12+ characters)');
        if (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/',$username) || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12) {
            CLI::error('Use valid username/email and password with at least 12 characters.');return;
        }
        if ($db->table('users')->groupStart()->where('username',$username)->orWhere('email',$email)->groupEnd()->countAllResults()) {
            CLI::error('Username or email already exists.');return;
        }
        $db->table('users')->insert(['role_id'=>$role['role_id'],'username'=>$username,'email'=>$email,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'account_status'=>'active']);
        CLI::write('Admin account created.');
    }
}
