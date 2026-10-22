<?php
namespace App\Models;
use CodeIgniter\Model;
class BookingModel extends Model
{
    protected $table = 'bookings';
    protected $primaryKey = 'booking_id';
    protected $returnType = 'array';
    protected $allowedFields = ['customer_id','service_id','requested_start','service_address','notes','booking_status'];
}
