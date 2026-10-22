<?php
namespace App\Controllers;
use App\Models\UserModel;
use App\Models\CustomerProfileModel;
class Auth extends BaseController
{
    public function register() { return view('auth/register'); }
    public function login() { return view('auth/login'); }
    public function registerProcess()
    {
        $rules = [
            'first_name' => 'required|max_length[100]',
            'middle_name' => 'permit_empty|max_length[100]',
            'last_name' => 'required|max_length[100]',
            'birthdate' => 'permit_empty|valid_date[Y-m-d]',
            'gender' => 'permit_empty|max_length[30]',
            'email' => 'required|valid_email|max_length[255]|is_unique[users.email]',
            'phone_number' => 'required|max_length[30]',
            'address' => 'required|max_length[2000]',
            'username' => 'required|min_length[3]|max_length[50]|alpha_dash|is_unique[users.username]',
            'password' => 'required|min_length[8]|max_length[72]',
            'password_confirm' => 'required|matches[password]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $db = db_connect();
        $role = $db->table('roles')->where('role_name', 'customer')->get()->getRowArray();
        if (!$role) return redirect()->back()->with('error', 'Customer role missing. Import the provided schema first.');
        $db->transBegin();
        try {
            $user = new UserModel();
            $id = $user->insert([
                'role_id' => $role['role_id'],
                'username' => trim((string)$this->request->getPost('username')),
                'email' => trim((string)$this->request->getPost('email')),
                'password_hash' => password_hash((string)$this->request->getPost('password'), PASSWORD_DEFAULT),
                'account_status' => 'active', // Demo policy: customer signup is immediately active.
            ], true);
            if (!$id) throw new \RuntimeException('Unable to save the user.');
            $profile = new CustomerProfileModel();
            if (!$profile->insert([
                'user_id' => $id,
                'first_name' => trim((string)$this->request->getPost('first_name')),
                'middle_name' => trim((string)$this->request->getPost('middle_name')) ?: null,
                'last_name' => trim((string)$this->request->getPost('last_name')),
                'birthdate' => $this->request->getPost('birthdate') ?: null,
                'gender' => $this->request->getPost('gender') ?: null,
                'phone_number' => trim((string)$this->request->getPost('phone_number')),
                'address' => trim((string)$this->request->getPost('address')),
            ])) throw new \RuntimeException('Unable to save the customer profile.');
            if ($db->transStatus() === false) throw new \RuntimeException('Registration failed.');
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Customer registration failed: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error','Unable to register. Please retry.');
        }
        return redirect()->to(site_url('login'))->with('success','Registration successful. Please log in.');
    }
    public function loginProcess()
    {
        if (!$this->validate(['username'=>'required','password'=>'required'])) {
            return redirect()->back()->with('error','Enter your username and password.');
        }
        $user = (new UserModel())->where('username', (string)$this->request->getPost('username'))->first();
        if (!$user || $user['account_status'] !== 'active' ||
            !password_verify((string)$this->request->getPost('password'), $user['password_hash'])) {
            return redirect()->back()->with('error','Invalid credentials or inactive account.');
        }
        $role = db_connect()->table('roles')->where('role_id', $user['role_id'])->get()->getRowArray();
        $roleName = $role['role_name'] ?? '';
        $sessionData = [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'role' => $roleName,
            'logged_in' => true,
        ];
        if ($roleName === 'customer') {
            $profile = (new CustomerProfileModel())->where('user_id', $user['user_id'])->first();
            if (!$profile) return redirect()->back()->with('error', 'Customer profile missing.');
            $sessionData['customer_id'] = $profile['customer_id'];
        } elseif (!in_array($roleName, ['admin','hr','dispatcher','technician'], true)) {
            return redirect()->back()->with('error','Unsupported account role.');
        }
        session()->regenerate();
        session()->set($sessionData);
        if ($roleName === 'customer') return redirect()->to(site_url('dashboard'));
        if ($roleName === 'technician') return redirect()->to(site_url('technician'));
        return redirect()->to(site_url('staff'));
    }
    public function logout()
    {
        session()->destroy();
        return redirect()->to(site_url('login'));
    }
}
