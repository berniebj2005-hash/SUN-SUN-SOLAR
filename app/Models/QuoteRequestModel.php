<?php
namespace App\Models;
use CodeIgniter\Model;
class QuoteRequestModel extends Model
{
    protected $table = 'quote_requests';
    protected $primaryKey = 'quote_id';
    protected $returnType = 'array';
    protected $allowedFields = ['customer_id','service_id','contact_name','contact_email','contact_phone','details'];
}
