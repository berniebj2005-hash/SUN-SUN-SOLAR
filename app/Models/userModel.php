<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'ID';
    protected $allowedFields = ['FirstName', 'LastName', 'Email', 'Username', 'Password'];
}