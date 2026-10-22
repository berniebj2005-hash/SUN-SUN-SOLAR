<?php
namespace App\Controllers;
use App\Models\ServiceModel;
use App\Models\BookingModel;
use App\Models\CustomerProfileModel;
class Customer extends BaseController
{
    private function allowed(): bool
    {
        return (bool) session('logged_in') && session('role') === 'customer' && (bool)session('customer_id');
    }
    public function dashboard()
    {
        if (!$this->allowed()) return redirect()->to(site_url('login'));
        $customerId = (int)session('customer_id');
        $profile = (new CustomerProfileModel())->find($customerId);
        $bookings = db_connect()->table('bookings b')
            ->select('b.booking_id,b.requested_start,b.booking_status,b.service_address,s.service_name')
            ->join('services s','s.service_id = b.service_id')
            ->where('b.customer_id',$customerId)->orderBy('b.created_at','DESC')->limit(25)->get()->getResultArray();
        $notifications = db_connect()->table('notifications')->where('user_id',(int)session('user_id'))
            ->orderBy('created_at','DESC')->limit(10)->get()->getResultArray();
        return view('customer/dashboard',compact('profile','bookings','notifications'));
    }
    public function newBooking()
    {
        if (!$this->allowed()) return redirect()->to(site_url('login'));
        return view('customer/new_booking',[
            'services'=>(new ServiceModel())->where('is_active',1)->findAll(),
            'profile'=>(new CustomerProfileModel())->find((int)session('customer_id')),
        ]);
    }
    public function createBooking()
    {
        if (!$this->allowed()) return redirect()->to(site_url('login'));
        $rules = [
            'service_id'=>'required|is_natural_no_zero',
            'requested_start'=>'required',
            'service_address'=>'required|max_length[2000]',
            'notes'=>'permit_empty|max_length[2000]',
        ];
        if (!$this->validate($rules)) return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        $service = (new ServiceModel())->where('is_active',1)->find((int)$this->request->getPost('service_id'));
        if (!$service) return redirect()->back()->withInput()->with('error','Please select a valid service.');
        $raw = (string)$this->request->getPost('requested_start');
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $raw);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$dt || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $dt <= new \DateTimeImmutable('now')) {
            return redirect()->back()->withInput()->with('error','Choose a valid future date and time.');
        }
        (new BookingModel())->insert([
            'customer_id'=>(int)session('customer_id'),
            'service_id'=>(int)$service['service_id'],
            'requested_start'=>$dt->format('Y-m-d H:i:s'),
            'service_address'=>trim((string)$this->request->getPost('service_address')),
            'notes'=>trim((string)$this->request->getPost('notes')) ?: null,
            'booking_status'=>'pending',
        ]);
        return redirect()->to(site_url('dashboard'))->with('success','Your booking request was submitted for approval.');
    }
}
