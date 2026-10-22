<?php
namespace App\Models;
use CodeIgniter\Model;
class ProductModel extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'product_id';
    protected $returnType = 'array';
    protected $allowedFields = ['category_id','product_name','description','display_price','image_path','is_active'];
}
