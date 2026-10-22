<?php
namespace App\Models;
use CodeIgniter\Model;
class ServiceModel extends Model
{
    protected $table = 'services';
    protected $primaryKey = 'service_id';
    protected $returnType = 'array';
    protected $allowedFields = ['service_name','description','base_price','expected_duration_minutes','is_active'];
}
