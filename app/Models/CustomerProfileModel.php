<?php
namespace App\Models;
use CodeIgniter\Model;
class CustomerProfileModel extends Model
{
    protected $table = 'customer_profiles';
    protected $primaryKey = 'customer_id';
    protected $returnType = 'array';
    protected $allowedFields = ['user_id','first_name','middle_name','last_name','birthdate','gender','phone_number','address'];
}
