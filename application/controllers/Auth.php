<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends CI_Controller{

    public function __construct()
    {
        parent::__construct();
        $this->load->library('form_validation');
    }
    
    public function index()
    {
        if( $this->session->userdata('email') ){
            redirect('user');
        }

        $this->form_validation->set_rules('email', 'Email', 'required|trim|valid_email');
        $this->form_validation->set_rules('password', 'Password', 'required|trim');
        if ($this->form_validation->run() == FALSE)
        {
            $data['title'] = 'Page Login';
            $this->load->view('templates/auth_header', $data);
            $this->load->view('auth/login');
            $this->load->view('templates/auth_footer');
        }else{
            $this->_login();
        }
    }

    private function _login()
    {
        $email = $this->input->post('email');
        $password = $this->input->post('password');

        $user = $this->db->get_where('user', ['email' => $email])->row_array();
        // jika $user mengembalikan nilai true (ada user di database)
        if ($user){
            // jika usernya aktif
            if( $user['is_active'] == 1 ){
                //cek password
                if( password_verify($password, $user['password']) ){
                    $data = [
                        'email' => $user['email'],
                        'role_id' => $user['role_id']
                    ];
                    $this->session->set_userdata($data);
                    if( $user['role_id'] == 1 ){
                        redirect('admin');
                    }else{

                        redirect('user');
                    }
                }else{
                    $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert"> Wrong password
                    </div>');
                    redirect('auth');
                }
            }else{
                $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">
                This email has not been activited!
              </div>');
                redirect('auth');
            }
        }else{
            $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">
            Email is not registered!
          </div>');
            redirect('auth');
        }
    }
    
    public function registration()
    {
        if( $this->session->userdata('email') ){
            redirect('user');
        }

        $this->form_validation->set_rules('name', 'Name', 'required|trim');
        $this->form_validation->set_rules('email', 'Email', 'required|trim|valid_email|is_unique[user.email]', [
            'is_unique' => 'This email has alredy registered!'
        ]);
        $this->form_validation->set_rules('password1', 'Password', 'required|trim|matches[password2]|min_length[3]|max_length[12]', [
            'matches' => 'password dont match!',
            'min_length' => 'password too short!',
            'max_length' => 'password is too long!'
        ]);
        $this->form_validation->set_rules('password2', 'Password', 'required|trim|matches[password1]');
        if ($this->form_validation->run() == FALSE){

            $data['title'] = 'Page Registration';
            $this->load->view('templates/auth_header', $data);
            $this->load->view('auth/registration');
            $this->load->view('templates/auth_footer');
        }else{
            $email = $this->input->post('email', true);
            $data = [
                'name' => htmlspecialchars($this->input->post('name', true)),
                'email' => htmlspecialchars($email),
                'image' => 'default.png',
                'password' => password_hash($this->input->post('password1'), PASSWORD_DEFAULT),
                'role_id' => 2,
                'is_active' => 1,
                // 'is_active' => 0,
                'date_created' => time()
            ];

            // siapkan token untuk EMAIL
            // $token = base64_encode(random_bytes(32));
            // $user_token = [
            //    'email' => $email,
            //    'token' => $token ,
            //    'date_created' => time()
            // ];

           $this->db->insert('user', $data);
            //$this->db->insert('user_token', $user_token );

            // METHOD EMAIL
            // $this->_sendEmail($token, 'verify');


            $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">
            Congratulation ! yaour account has been created. Please activate your account!
          </div>');
            redirect('auth');
        }

    }

    private function _sendEmail($token, $type)
    {
        $config = [
            'protocol'  => 'smtp',
            'smtp_host' => 'ssl://smtp.googlemail.com',
            'smtp_user' => 'your@example.com',
            'smtp_pass' => 'password',
            'smtp_port' => 465,
            'mailtype' => 'html',
            'charset' => 'utf-8',
            'newline' => "\r\n"
        ];

        $this->email->initialize($config);

        $this->email->from('your@example.com', 'Your Name');
        $this->email->to($this->input->post('email'));

        if ( $type == 'verify' ){
            $this->email->subject('Reset Password');
            $this->email->message('Click this link to verify you account : <a ref=" '. base_url() . 'auth/verify?email=' . $this->input->post('email') . '&token=' . urlencode($token) . ' ">Activate </a> ');
        }elseif( $type == 'forgot' ) {
            $this->email->subject('Accaount Verification');
            $this->email->message('Click this link to reset your password : <a ref=" '. base_url() . 'auth/resetpassword?email=' . $this->input->post('email') . '&token=' . urlencode($token) . ' ">Reset Password</a> ');
        }

        if ($this->email->send()){
            return true;
        }else{
            echo $this->email->print_debugger();
            die;
        }
    }

    public function verify()
    {
        $email = $this->input->get('email');
        $token = $this->input->get('token');

        // query ke tabel user uktuk mengecek email-nya
        $user = $this->db->get_where('user', ['email' => $email ])->row_array();
        if($user){
            // query ke tabel user_token uktuk mengecek token-nya
            $user_token = $this->db->get_where('user_token', ['token' => $token])->row_array();
            if($user_token){
                // cek jika lebih dari 24jam maka verification -nya gagal
                if( time() - $user_token['date_created'] < (60*60*24) ){
                    $this->db->set('is_active', 1);
                    $this->db->where('email', $email);
                    $this->db->update('user');
                    
                    $this->db->delete('user_token', ['email' => $email]);

                    $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">
                    '.$email.' has been activated! Please Login.
                    </div>');
                    redirect('auth');
                }else{
                    $this->db->delete('user', ['email' => $email]);
                    $this->db->delete('user_token', ['email' => $email]);

                    $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">
                    Accaont activation failed! Token expire.
                    </div>');
                    redirect('auth');
                }
            }else{
                $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">
                Accaont activation failed! Wrong token.
                </div>');
                redirect('auth');
            }
        }else{
            $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">
            Accaont activation failed! Wrong email.
            </div>');
            redirect('auth');
        }
    }

    public function logout()
    {
        $this->session->unset_userdata('email');
        $this->session->unset_userdata('role_id');

        $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">
            You have been logged out!
        </div>');
        redirect('auth');
    }

    public function blocked()
    {
        $this->load->view('auth/blocked');
    }

    public function forgotPassword()
    {
        $this->form_validation->set_rules('email', 'Email', 'required|trim|valid_email');
        if ($this->form_validation->run() == FALSE){
            $data['title'] = 'Forgot Password';
            $this->load->view('templates/auth_header', $data);
            $this->load->view('auth/forgot-password');
            $this->load->view('templates/auth_footer');
        }else{
            $email = $this->input->post('email');
            $user = $this->db->get_where('user', ['email' => $email, 'is_active' => 1])->row_array();
            
            if( $user ){
                $token = base64_encode(random_bytes(32));
                $user_token = [
                    'email' => $email,
                    'token' => $token ,
                    'date_created' => time()
                ];

                $this->db->insert('user_token', $user_token );
                $this->_sendEmail($token, 'forgot');

                $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">
                Please check your email to reset your password!
                </div>');
                redirect('auth/forgotpassword');
            }else{
                $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">
                Email is not registered or activated!
                </div>');
                redirect('auth/forgotpassword');
            }
        }
    }

    public function resetPassword()
    {
        $email = $this->input->get('email');
        $token = $this->input->get('token');

        $user = $this->db->get_where('user', ['email' => $email ])->row_array();
        if( $user ){
            // query ke tabel user_token uktuk mengecek token-nya
            $user_token = $this->db->get_where('user_token', ['token' => $token])->row_array();
            if($user_token){
                // cek jika lebih dari 24jam maka reset -nya gagal
                if( time() - $user_token['date_created'] < (60*60*24) ){
                    $this->session->set_userdata('reset_email', $email);
                    $this->changePassword();
                }else{
                    $this->db->delete('user_token', ['email' => $email]);

                    $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">
                    Accaont activation failed! Token expire.
                    </div>');
                    redirect('auth');
                }
            }else{
                $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">
                Reset password failed! Wrong token.
                </div>');
                redirect('auth');
            }
        }else{
            $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">
            Reset password failed! Wrong email.
            </div>');
            redirect('auth');
        }
    }

    public function changePassword()
    {
        if( !$this->session->userdata('reset_email') ){
            redirect('auth');
        }

        $this->form_validation->set_rules('password1', 'Password', 'required|trim|matches[password2]|min_length[3]|max_length[12]', [
            'matches' => 'password dont match!',
            'min_length' => 'password too short!',
            'max_length' => 'password is too long!'
        ]);
        $this->form_validation->set_rules('password2', 'Password', 'required|trim|matches[password1]');

        if ($this->form_validation->run() == FALSE){
            $data['title'] = 'Chage Password';
            $this->load->view('templates/auth_header', $data);
            $this->load->view('auth/change-password');
            $this->load->view('templates/auth_footer');
        }else{
            $password = password_hash($this->input->post('password1'), PASSWORD_DEFAULT);
            $email = $this->session->userdata('reset_email');
            
            $this->db->set('password', $password);
            $this->db->where('email', $email);
            $this->db->update('user');

            $this->session->unset_userdata('reset_email');

            $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">
            Password has been changed! Please Login.
            </div>');
            redirect('auth');
        }
    }
}