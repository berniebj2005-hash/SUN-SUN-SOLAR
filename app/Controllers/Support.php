<?php
namespace App\Controllers;
class Support extends BaseController
{
    private function allowed(): bool { return (bool)session('logged_in') && in_array(session('role'),['customer','admin','hr','dispatcher'],true); }
    public function index()
    {
        if (!$this->allowed()) return redirect()->to(site_url('login'));
        $q=db_connect()->table('support_tickets t')->select('t.ticket_id,t.subject,t.description,t.ticket_status,t.created_at,c.first_name,c.last_name')
            ->join('customer_profiles c','c.customer_id=t.customer_id');
        if(session('role')==='customer') $q->where('t.customer_id',(int)session('customer_id'));
        $tickets=$q->orderBy('t.created_at','DESC')->limit(100)->get()->getResultArray();
        return view('support/index',compact('tickets'));
    }
    public function create()
    {
        if (!$this->allowed() || session('role')!=='customer') return redirect()->to(site_url('login'));
        if (!$this->validate(['subject'=>'required|max_length[200]','description'=>'required|max_length[5000]']))
            return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        db_connect()->table('support_tickets')->insert(['customer_id'=>session('customer_id'),'subject'=>trim((string)$this->request->getPost('subject')),'description'=>trim((string)$this->request->getPost('description'))]);
        return redirect()->to(site_url('support'))->with('success','Support ticket created.');
    }
    public function show($id)
    {
        if (!$this->allowed()) return redirect()->to(site_url('login'));
        $db=db_connect();$ticket=$db->table('support_tickets')->where('ticket_id',(int)$id)->get()->getRowArray();
        if (!$ticket || (session('role')==='customer' && (int)$ticket['customer_id']!==(int)session('customer_id')))
            return redirect()->to(site_url('support'))->with('error','Ticket not found.');
        $messages=$db->table('ticket_messages m')->select('m.message_text,m.sent_at,u.username')->join('users u','u.user_id=m.sender_user_id')
            ->where('m.ticket_id',$ticket['ticket_id'])->orderBy('m.sent_at','ASC')->get()->getResultArray();
        return view('support/show',compact('ticket','messages'));
    }
    public function reply($id)
    {
        if (!$this->allowed()) return redirect()->to(site_url('login'));
        $db=db_connect();$ticket=$db->table('support_tickets')->where('ticket_id',(int)$id)->get()->getRowArray();
        if (!$ticket || (session('role')==='customer' && (int)$ticket['customer_id']!==(int)session('customer_id')))
            return redirect()->to(site_url('support'))->with('error','Ticket not found.');
        if (in_array($ticket['ticket_status'],['closed'],true)) return redirect()->back()->with('error','This ticket is closed.');
        if (!$this->validate(['message_text'=>'required|max_length[5000]'])) return redirect()->back()->with('error','Enter a message.');
        $db->table('ticket_messages')->insert(['ticket_id'=>$ticket['ticket_id'],'sender_user_id'=>session('user_id'),'message_text'=>trim((string)$this->request->getPost('message_text'))]);
        return redirect()->to(site_url('support/'.$id))->with('success','Reply added.');
    }
    public function status($id)
    {
        if (!$this->allowed() || session('role')==='customer') return redirect()->to(site_url('login'));
        if (!$this->validate(['ticket_status'=>'required|in_list[open,in_progress,resolved,closed]'])) return redirect()->back()->with('error','Invalid status.');
        db_connect()->table('support_tickets')->where('ticket_id',(int)$id)->update(['ticket_status'=>$this->request->getPost('ticket_status')]);
        return redirect()->to(site_url('support/'.$id))->with('success','Status updated.');
    }
}
