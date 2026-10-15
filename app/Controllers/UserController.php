<?php

namespace App\Controllers;

use App\Models\UserModel;

class UserController extends BaseController
{
    public function save()
    {
        $username = $this->request->getPost('username');
        $user = (new UserModel())->where('Username', $username)->first();

        if($user){
            return redirect()->to('/register')->with('message', 'Username already exists');
        }
        
        $userModel = new UserModel();

        $userModel->insert([
            'FirstName' => $this->request->getPost('firstname'),
            'LastName' => $this->request->getPost('lastname'),
            'Email' => $this->request->getPost('email'),
            'Username' => $this->request->getPost('username'),
            'Password' => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT),
        ]);

        return $this->response->redirect('/');
    }

    public function login()
    {
        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');

        $user = (new UserModel())->where('Username', $username)->first();

        if($user===null){
            return redirect()->to('/login')->with('message', 'No user found');
        }else{
            $storedPassword = (string) $user['Password'];
            $passwordValid  = password_verify($password, $storedPassword);

            if($passwordValid){
                return $this->response->redirect('/dashboard');
            }else{
                return redirect()->to('/login')->with('message', 'Wrong password');
            }
           
        }
    }
}