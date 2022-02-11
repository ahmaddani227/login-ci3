<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User extends CI_Controller{
    public function __construct()
    {
        parent::__construct();
        is_logged_in();
    }
    public function index()
    {
        $data['title'] = 'My Profile';
        $data['user'] = $this->db->get_where('user', ['email' => $this->session->userdata('email')])->row_array();

        $this->load->view('templates/header', $data);
        $this->load->view('templates/sidebar', $data);
        $this->load->view('templates/topbar', $data);
        $this->load->view('user/index', $data);
        $this->load->view('templates/footer');
    }

    public function edit()
    {
        $data['title'] = 'Edit Profile';
        $data['user'] = $this->db->get_where('user', ['email' => $this->session->userdata('email')])->row_array();
        
        $this->form_validation->set_rules('name', 'Full name', 'required|trim');
        if ($this->form_validation->run() == FALSE){
            $this->load->view('templates/header', $data);
            $this->load->view('templates/sidebar', $data);
            $this->load->view('templates/topbar', $data);
            $this->load->view('user/edit', $data);
            $this->load->view('templates/footer');
        }else{
            $email = $this->input->post('email');
            $name = $this->input->post('name');

            // cek jika ada gambar yang diupload
            $upload_image = $_FILES['image']['name'];
            if ( $upload_image ){
                $config['upload_path'] = './assets/img/profile/';
                $config['allowed_types'] = 'gif|jpg|png';
                $config['max_size']     = '2048';

                $this->load->library('upload', $config);

                if( $this->upload->do_upload('image') ){
                    $old_image = $data['user']['image'];
                    if( $old_image != 'default.png' ){
                        unlink(FCPATH . 'assets/img/profile/' . $old_image);
                    }

                    // berisi nama file baru jika user mengupload file
                    // dan akan di ubah ke database-nya
                    $new_image = $this->upload->data('file_name');
                    $this->db->set('image', $new_image);
                }else{
                    echo $this->upload->display_errors();
                }
            }

            $this->db->set('name', $name);
            $this->db->where('email', $email);
            $this->db->update('user');

            $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">Your profile has been updated!</div>');
            redirect('user');

            // untuk mangecek jika ada error ketika upload gambar
            $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">' . $this->upload->display_errors() . '</div>');
            redirect('user');
        }
    }

    public function changePassword()
    {
        $data['title'] = 'Change Password';
        $data['user'] = $this->db->get_where('user', ['email' => $this->session->userdata('email')])->row_array();
        
        $this->form_validation->set_rules('current_password', 'Current Password', 'required|trim');
        $this->form_validation->set_rules('new_password1', 'New Password', 'required|trim|matches[new_password2]|min_length[3]|max_length[12]', [
            'matches'       => 'password dont match!',
            'min_length'    => 'password too short!',
            'max_length'    => 'password is too long!'
        ]);
        $this->form_validation->set_rules('new_password2', 'Confirm New Password', 'required|trim|matches[new_password1]', [
            'matches' => 'password dont match!',
            'min_length' => 'password too short!',
            'max_length' => 'password is too long!'
        ]);
        if( $this->form_validation->run() == false ){
            $this->load->view('templates/header', $data);
            $this->load->view('templates/sidebar', $data);
            $this->load->view('templates/topbar', $data);
            $this->load->view('user/changepassword', $data);
            $this->load->view('templates/footer');
        }else{
            $current_password = $this->input->post('current_password');
            $new_password = $this->input->post('new_password1');

            if( !password_verify($current_password, $data['user']['password']) ){
                //cek jika current_password tidak sama dengan dengan yang ada di database
                $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">Wrong Current password!</div>');
                redirect('user/changepassword');
            }else{
                // cek jika current_password sama dengan new_password
                if( $current_password == $new_password ){
                    $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">New password cannot be the same as current password</div>');
                    redirect('user/changepassword');
                }else{
                    // password sudah ok
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                    // $this->db->where('email', $data['user']['password']);
                    $this->db->set('password', $password_hash);
                    $this->db->where('email', $this->session->userdata('email'));
                    $this->db->update('user');
                    $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">New password cannot be the same as current password</div>');
                    redirect('user/changepassword');
                }
            }
        }
    }
}