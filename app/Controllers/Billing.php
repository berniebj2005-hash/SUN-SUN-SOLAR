<?php
namespace App\Controllers;
class Billing extends BaseController
{
    private function allowed(array $roles): bool { return (bool)session('logged_in') && in_array(session('role'),$roles,true); }
    public function index()
    {
        if (!$this->allowed(['customer','admin','hr','dispatcher'])) return redirect()->to(site_url('login'));
        $db=db_connect();
        $q=$db->table('payments p')->select('p.payment_id,p.booking_id,p.amount,p.currency,p.payment_method,p.payment_status,p.paid_at,r.receipt_number')
            ->join('bookings b','b.booking_id=p.booking_id')->join('receipts r','r.payment_id=p.payment_id','left');
        if (session('role')==='customer') $q->where('b.customer_id',(int)session('customer_id'));
        $payments=$q->orderBy('p.recorded_at','DESC')->get()->getResultArray();
        $bookings=[];
        if ($this->allowed(['admin','hr','dispatcher'])) $bookings=$db->table('bookings')->select('booking_id')->orderBy('booking_id','DESC')->limit(200)->get()->getResultArray();
        return view('billing/index',compact('payments','bookings'));
    }
    public function record()
    {
        if (!$this->allowed(['admin'])) return redirect()->to(site_url('login'));
        if (!$this->validate(['booking_id'=>'required|is_natural_no_zero','amount'=>'required|decimal','payment_method'=>'required|max_length[60]','payment_status'=>'required|in_list[pending,paid,failed,refunded]']))
            return redirect()->back()->with('error','Invalid payment details.');
        $amount=(float)$this->request->getPost('amount');if ($amount<0) return redirect()->back()->with('error','Amount cannot be negative.');
        $db=db_connect();$booking=$db->table('bookings')->where('booking_id',(int)$this->request->getPost('booking_id'))->get()->getRowArray();
        if (!$booking) return redirect()->back()->with('error','Booking not found.');
        $status=(string)$this->request->getPost('payment_status');
        $db->transBegin();
        try {
            $ok=$db->table('payments')->insert(['booking_id'=>$booking['booking_id'],'amount'=>$amount,'payment_method'=>$this->request->getPost('payment_method'),'payment_status'=>$status,'paid_at'=>$status==='paid'?date('Y-m-d H:i:s'):null]);
            if (!$ok) throw new \RuntimeException('Unable to add payment');
            $paymentId=$db->insertID();
            if ($status==='paid') {
                $receiptNo='SSS-'.str_pad((string)$paymentId,8,'0',STR_PAD_LEFT);
                if (!$db->table('receipts')->insert(['payment_id'=>$paymentId,'receipt_number'=>$receiptNo])) throw new \RuntimeException('Unable to issue receipt');
            }
            if (!$db->transStatus()) throw new \RuntimeException('Transaction failed');
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback(); log_message('error','Payment record: '.$e->getMessage());
            return redirect()->back()->with('error','Unable to record payment.');
        }
        return redirect()->to(site_url('payments'))->with('success','Payment entry recorded.');
    }
}
