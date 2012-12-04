<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');
require_once ('frontend.php');
/*
 * @property CI_DB_active_record $db
 * @property User_lib $user_lib
 *
 * */
class User extends Frontend
{

    public function __construct()
    {

        parent::__construct();
        $this->load->library('User_lib');
        $this->load->helper('html_form_helper.php');
        $param = array('is_activate' => TRUE, 'site_point_act' => 'user/chek_act', 'session_pref' => 'pref1_',
            'session_var' => array('id_user', 'pass_user', 'email_user'), 'soc_providers' => array('vkontakte', 'facebook'),
            'soc_user_fields' => array('first_name', 'photo', 'email'),'soc_type_result' => 'boolean',
            'soc_js_func' => 'handler_social','soc_ulogin_xd '=>base_url().'/data/ulogin/ulogin_xd.html');
        $this->user_lib->initialize($param);
        $this->data['view'] = 'frontend/user/index';
        $this->data['extra_head'] .= js_tag('user_lib.js');
    }


    /*описание библиотеки*/
    public function index()
    {
        $this->data['LEFT_NAV'] = "Главная";
        $this->data['view_in'] = 'frontend/user/index';
        $this->render();
    }

   /*регистрация*/
    public function add()
    {

        $this->data['LEFT_NAV'] = "Регистрация";
        $this->data['result'] = $this->user_lib->validate('user');
        if ($this->data['result'])
        {
            //делаю операции с переменными, удаляю, добавляю, изменяю  переменные
            $this->data['result']['pass_user'] = md5($this->data['result']['pass_user']);
            $this->data['result']['url_user'] = makeClickableLinks($this->data['result']['url_user']);
            $id = $this->user_lib->add($this->data['result']);
            $this->data['result_add'] = TRUE;

            //активация
            $this->user_lib->activate($id);

        }
        //echo $this->user_lib->error();
        $this->data['view'] = 'frontend/user/_add_forma';
        $this->data['country'] = get_array('id_country', 'country', $this->ST->get_data_tabl('country'));
        $this->render();
    }


    //проверка активации
    public function chek_act()
    {
        $this->data['LEFT_NAV'] = "Результат активации";
        $this->data['result'] = ($this->user_lib->check_activate());
        $this->data['view'] = 'frontend/user/_activ';
        $this->render();
    }

    //авторизация
    public function login()
    {
        $this->data['LEFT_NAV'] = "Авторизация";
        $this->user_lib->login('user/my');
        $this->data['result'] = $this->user_lib->result;

        $this->data['view'] = 'frontend/user/_login_forma';
        $this->render();

    }

    //выйти
    function logout()
    {
        $this->data['LEFT_NAV'] = "Выход";
        $this->user_lib->logout();
        $this->data['view'] = 'frontend/user/_logout';
        $this->render();
    }

    //мой кабинет. редактирование данных
    //не делал проверку в БД, так как здесь цель-показать редактирование данных
    public function  my()
    {
        $this->data['LEFT_NAV'] = "Мой кабинет";
        $id = $this->session->userdata('pref1_id_user');
        if ($id)
        {
            $result = $this->user_lib->validate('user_edit');
            if ($result)
            {
                $this->user_lib->edit($id, $result);
                $this->data['result_edit'] = TRUE;
            }
            //$this->user_lib->error();

            $this->data['country'] = get_array('id_country', 'country', $this->ST->get_data_tabl('country'));
            $this->data['oDefault'] = $this->user_lib->view($id);
            $this->data['view'] = 'frontend/user/_edit_forma';
            $this->render();
        } else
            $this->login();
    }
//восстановление пароля
    public function  lost_pass()
    {

        $this->data['LEFT_NAV'] = "Восстановление пароля";
        if ($result = $this->user_lib->validate('lost_pass'))
        {
            $this->data['result'] = ($this->user_lib->get_lost_pass($result['email'])) ? TRUE:FALSE;
            $this->data['error']=$this->user_lib->result;
        }
       // echo $this->user_lib->error();
        $this->data['view'] = 'frontend/user/_lostpass_form';
        $this->render();
    }

   //социальные кнопки для регистрации/авторизации(сервер)
    public function  send_mail()
    {

        $this->data['LEFT_NAV'] = "Отправить  письмо";
        if ($result = $this->user_lib->validate('send_mail'))
        {
            $array_param = array('mail_name_from' => $result['name'], 'mail_subject' => $result['tema'], 'mail_body' => $result['text'], 'mail_email_from' => $result['email'], 'mail_email_to' => 'admin@admin.ru');
            $this->user_lib->initialize($array_param);
            $this->data['result'] =($this->user_lib->send_mail()) ? TRUE : FALSE;
            echo $this->user_lib->error();
        }
        $this->data['view'] = 'frontend/user/_send_mail';
        $this->render();

    }

    public function list_user()
    {
        $this->data['LEFT_NAV'] = "Список";
        $this->data['result']=$this->user_lib->get_list();
        //здесь можно было LEFT JOIN прикрепить название страны, но  задача стоит  показать как работать с библиотекой
        $this->data['country'] = get_array('id_country', 'country', $this->ST->get_data_tabl('country'));
        $this->data['view'] = 'frontend/user/_list';
        $this->render();

    }


    //социальные кнопки для регистрации/авторизации (клиент)
    public function social()
    {

        $this->data['LEFT_NAV'] = "Социальные кнопки (авторизация/регистрация)";
        $this->data['social_no_reload'] = $this->user_lib->view_social();

        $this->data['view'] = 'frontend/user/_social';
        $this->render();
    }

//социальные кнопки для регистрации/авторизации (сервер)
    public function set_user()
    {
        $this->data['result'] = $this->user_lib->handler_social();
        // prn($a);
        if ($this->data['result'])
        {
            if ($this->data['result']['id_user'])
            {
                $this->user_lib->login_by_id($this->data['result']['id_user']);
            } else
            {
                $a2 = array('email_user' => $this->data['result']['email'], 'vk_user' => $this->data['result']['uid'],'login_user' => 'denis2','pass_user' => md5('denis'));
                $id = $this->user_lib->add($a2);
                //$this->user_lib->login_by_id($id);
            }

        }
        echo json_encode(array('status'=>1,'data'=>$this->load->view('frontend/user/_userinfo',$this->data,TRUE)));
    }

    //удаление пользователя
    public function delete_me()
    {
        $this->data['LEFT_NAV'] = "Удалить анкету";
        $this->data['view'] = 'frontend/user/_delete_me';
        $this->render();
    }
    //отлов ошибок
    public function error()
    {
        $this->data['LEFT_NAV'] = "Отлов ошибок";
        $this->data['view'] = 'frontend/user/_error';
        $this->render();
    }
}


?>
