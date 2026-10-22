<?php
namespace App\Controllers;
use App\Models\UserModel;
class Staff extends BaseController
{
    private function permit(array $roles): bool { return (bool)session('logged_in') && in_array(session('role'),$roles,true); }
    private function blocked() { return redirect()->to(site_url('login'))->with('error','Permission denied.'); }
    public function dashboard()
    {
        if (!$this->permit(['admin','hr','dispatcher'])) return $this->blocked();
        $db=db_connect();
        $bookings=$db->table('bookings b')->select('b.booking_id,b.booking_status,b.requested_start,b.scheduled_start,s.service_name,cp.first_name,cp.last_name')
            ->join('services s','s.service_id=b.service_id')->join('customer_profiles cp','cp.customer_id=b.customer_id')
            ->orderBy('b.created_at','DESC')->limit(50)->get()->getResultArray();
        $technicians=$db->table('employee_profiles e')->select('e.employee_id,e.first_name,e.last_name')
            ->join('users u','u.user_id=e.user_id')->join('roles r','r.role_id=u.role_id')
            ->where('r.role_name','technician')->where('u.account_status','active')->get()->getResultArray();
        $employees=$db->table('employee_profiles e')->select('e.first_name,e.last_name,e.position_title,d.department_name,u.username,u.account_status,r.role_name')
            ->join('users u','u.user_id=e.user_id')->join('roles r','r.role_id=u.role_id')
            ->join('departments d','d.department_id=e.department_id')->orderBy('e.employee_id','DESC')->limit(50)->get()->getResultArray();
        return view('staff/dashboard',compact('bookings','technicians','employees'));
    }
    public function newEmployee()
    {
        if (!$this->permit(['admin','hr'])) return $this->blocked();
        $db=db_connect();
        return view('staff/employee_form',[
            'departments'=>$db->table('departments')->orderBy('department_name')->get()->getResultArray(),
            'roles'=>$db->table('roles')->whereIn('role_name',['hr','dispatcher','technician'])->orderBy('role_name')->get()->getResultArray(),
        ]);
    }
    public function createEmployee()
    {
        if (!$this->permit(['admin','hr'])) return $this->blocked();
        $rules=[
            'first_name'=>'required|max_length[100]','middle_name'=>'permit_empty|max_length[100]',
            'last_name'=>'required|max_length[100]','birthdate'=>'permit_empty|valid_date[Y-m-d]',
            'gender'=>'permit_empty|max_length[30]','phone_number'=>'permit_empty|max_length[30]',
            'address'=>'permit_empty|max_length[2000]','position_title'=>'permit_empty|max_length[100]',
            'email'=>'required|valid_email|max_length[255]|is_unique[users.email]',
            'username'=>'required|alpha_dash|min_length[3]|max_length[50]|is_unique[users.username]',
            'password'=>'required|min_length[12]|max_length[72]','password_confirm'=>'required|matches[password]',
            'role_id'=>'required|is_natural_no_zero','department_id'=>'required|is_natural_no_zero',
        ];
        if (!$this->validate($rules)) return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        $db=db_connect();
        $role=$db->table('roles')->where('role_id',(int)$this->request->getPost('role_id'))->get()->getRowArray();
        $dept=$db->table('departments')->where('department_id',(int)$this->request->getPost('department_id'))->get()->getRowArray();
        if (!$role || !in_array($role['role_name'],['hr','dispatcher','technician'],true) || !$dept)
            return redirect()->back()->withInput()->with('error','Select a valid employee role and department.');
        $db->transBegin();
        try {
            $id=(new UserModel())->insert([
                'role_id'=>$role['role_id'],'username'=>trim((string)$this->request->getPost('username')),
                'email'=>trim((string)$this->request->getPost('email')),
                'password_hash'=>password_hash((string)$this->request->getPost('password'),PASSWORD_DEFAULT),
                'account_status'=>'active'
            ],true);
            if (!$id) throw new \RuntimeException('User insert failed');
            $ok=$db->table('employee_profiles')->insert([
                'user_id'=>$id,'department_id'=>$dept['department_id'],
                'first_name'=>trim((string)$this->request->getPost('first_name')),
                'middle_name'=>trim((string)$this->request->getPost('middle_name')) ?: null,
                'last_name'=>trim((string)$this->request->getPost('last_name')),
                'birthdate'=>$this->request->getPost('birthdate') ?: null,
                'gender'=>$this->request->getPost('gender') ?: null,
                'phone_number'=>trim((string)$this->request->getPost('phone_number')) ?: null,
                'address'=>trim((string)$this->request->getPost('address')) ?: null,
                'position_title'=>trim((string)$this->request->getPost('position_title')) ?: null
            ]);
            if (!$ok || !$db->transStatus()) throw new \RuntimeException('Employee insert failed');
            $db->transCommit();
        } catch(\Throwable $e) {
            $db->transRollback(); log_message('error','Employee creation failed: '.$e->getMessage());
            return redirect()->back()->withInput()->with('error','Unable to create employee.');
        }
        return redirect()->to(site_url('staff'))->with('success','Employee account created. Share credentials privately and ask the employee to change the password.');
    }
    public function changeBooking()
    {
        if (!$this->permit(['admin','dispatcher'])) return $this->blocked();
        if (!$this->validate(['booking_id'=>'required|is_natural_no_zero','status'=>'required|in_list[pending,approved,declined,cancelled,in_progress,completed]']))
            return redirect()->back()->with('error','Invalid booking change.');
        $db=db_connect();$id=(int)$this->request->getPost('booking_id');
        $booking=$db->table('bookings')->where('booking_id',$id)->get()->getRowArray();
        if (!$booking) return redirect()->back()->with('error','Booking not found.');
        $db->table('bookings')->where('booking_id',$id)->update(['booking_status'=>$this->request->getPost('status')]);
        $customer=$db->table('customer_profiles')->where('customer_id',$booking['customer_id'])->get()->getRowArray();
        if ($customer) $db->table('notifications')->insert(['user_id'=>$customer['user_id'],'title'=>'Booking updated','message'=>'Your booking #'.$id.' is now '.$this->request->getPost('status'),'notification_type'=>'booking']);
        return redirect()->to(site_url('staff'))->with('success','Booking status updated.');
    }
    public function assignTechnician()
    {
        if (!$this->permit(['admin','dispatcher'])) return $this->blocked();
        if (!$this->validate(['booking_id'=>'required|is_natural_no_zero','employee_id'=>'required|is_natural_no_zero']))
            return redirect()->back()->with('error','Choose a booking and technician.');
        $db=db_connect();$bid=(int)$this->request->getPost('booking_id');$eid=(int)$this->request->getPost('employee_id');
        $booking=$db->table('bookings')->where('booking_id',$bid)->get()->getRowArray();
        $employee=$db->table('employee_profiles e')->select('e.employee_id,e.user_id')
            ->join('users u','u.user_id=e.user_id')->join('roles r','r.role_id=u.role_id')
            ->where('e.employee_id',$eid)->where('r.role_name','technician')->where('u.account_status','active')->get()->getRowArray();
        if (!$booking || !$employee || in_array($booking['booking_status'],['cancelled','declined','completed'],true))
            return redirect()->back()->with('error','Booking or technician is not eligible.');
        $existing=$db->table('technician_assignments')->where('booking_id',$bid)->where('employee_id',$eid)->get()->getRowArray();
        if ($existing) {
            if ($existing['assignment_status']==='removed') $db->table('technician_assignments')->where('assignment_id',$existing['assignment_id'])->update(['assignment_status'=>'assigned']);
            else return redirect()->back()->with('error','Technician already assigned.');
        } else $db->table('technician_assignments')->insert(['booking_id'=>$bid,'employee_id'=>$eid,'assigned_by_user_id'=>session('user_id')]);
        $db->table('notifications')->insert(['user_id'=>$employee['user_id'],'title'=>'New assignment','message'=>'You have been assigned booking #'.$bid,'notification_type'=>'booking']);
        return redirect()->to(site_url('staff'))->with('success','Technician assigned.');
    }
}
