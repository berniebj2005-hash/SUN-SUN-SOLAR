<?php
namespace App\Controllers;
class Technician extends BaseController
{
    private function employee(): ?array
    {
        if (!session('logged_in') || session('role')!=='technician') return null;
        return db_connect()->table('employee_profiles')->where('user_id',(int)session('user_id'))->get()->getRowArray();
    }
    public function index()
    {
        $e=$this->employee();if (!$e) return redirect()->to(site_url('login'));
        $db=db_connect();
        $assignments=$db->table('technician_assignments a')
            ->select('a.assignment_id,a.assignment_status,b.booking_id,b.requested_start,b.scheduled_start,b.service_address,b.booking_status,s.service_name,c.first_name,c.last_name')
            ->join('bookings b','b.booking_id=a.booking_id')->join('services s','s.service_id=b.service_id')
            ->join('customer_profiles c','c.customer_id=b.customer_id')
            ->where('a.employee_id',$e['employee_id'])->where('a.assignment_status !=','removed')
            ->orderBy('b.requested_start','DESC')->get()->getResultArray();
        $announcements=$db->table('announcements')->whereIn('audience',['all_employees','technicians','all_users'])->orderBy('published_at','DESC')->limit(8)->get()->getResultArray();
        return view('technician/dashboard',compact('e','assignments','announcements'));
    }
    private function owns(int $assignmentId, array $employee): bool
    {
        return db_connect()->table('technician_assignments')->where('assignment_id',$assignmentId)
            ->where('employee_id',$employee['employee_id'])->where('assignment_status !=','removed')->countAllResults() > 0;
    }
    public function checkin()
    {
        $e=$this->employee();if (!$e) return redirect()->to(site_url('login'));
        if (!$this->validate(['assignment_id'=>'required|is_natural_no_zero','latitude'=>'required|decimal','longitude'=>'required|decimal']))
            return redirect()->back()->with('error','Allow location and try again.');
        $id=(int)$this->request->getPost('assignment_id');$lat=(float)$this->request->getPost('latitude');$lng=(float)$this->request->getPost('longitude');
        if (!$this->owns($id,$e) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180)
            return redirect()->back()->with('error','Invalid assignment or location.');
        $db=db_connect();$db->table('technician_checkins')->insert(['assignment_id'=>$id,'latitude'=>$lat,'longitude'=>$lng]);
        $db->table('technician_assignments')->where('assignment_id',$id)->update(['assignment_status'=>'in_progress']);
        return redirect()->to(site_url('technician'))->with('success','Check-in recorded.');
    }
    public function report()
    {
        $e=$this->employee();if (!$e) return redirect()->to(site_url('login'));
        if (!$this->validate(['assignment_id'=>'required|is_natural_no_zero','issue_type'=>'permit_empty|max_length[100]','report_text'=>'required|max_length[5000]']))
            return redirect()->back()->with('error','Enter a valid service report.');
        $id=(int)$this->request->getPost('assignment_id');
        if (!$this->owns($id,$e)) return redirect()->back()->with('error','Not your assignment.');
        db_connect()->table('service_reports')->insert(['assignment_id'=>$id,'issue_type'=>$this->request->getPost('issue_type') ?: null,'report_text'=>trim((string)$this->request->getPost('report_text'))]);
        return redirect()->to(site_url('technician'))->with('success','Report submitted.');
    }
}
