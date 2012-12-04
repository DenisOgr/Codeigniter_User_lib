<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');
/*Библиотека для работы с пользователями
 *
 *initialize($array) - функция, инициализирует параметры для работы с библиотекой
 * error() - функция, возвращает ошибки
 * validate($rules) - функция проверяет правильность ввода через форму. Проверяте тогда, когда есть  параметр valid_me
 *      При успехе- возвращает параметры из формы и записывает в переменную $data/при ошибку-FALSE. Если не указан $rules - берет $validation_name или $table *
 * add($param=array()) - функция нужна, для добавления пользователя. Принимает массив, который нужно добавить в БД.
 *  * Если $param - не указан, то, берет массив $data (результат функции validate($rules))
 *
 * _is_table()-функция проверяет указанна ли таблица в БД
 * _get_form($param=array(),$rules=null) - функция нужна, что бы получить данные из формы. Параметр $param- массив параметров которые сливаются с массивом из формы
 *      (для записи в БД параметров не из формы), Если не указан $rules - берет $validation_name или $table
 * edit(id_user=null,$param=array()) - функция нужна, для редактирования  данных для пользователя.
 * delete(id_user=null) - функция нужна, что бы пользователь мог удалить свою анкету
 *
 * view($id_user) - функция нужна, что бы вывеси пользователю данные пользователя
 * get_list() - функция выводит список пользователей
 * activate($id)- функция нужна для активации учетной записи по email или phone
 * _activate_to_email($id, $email) функция нужна для активации учетной записи по email(private)
 * _activate_to_phone($id, $phone) функция, нужна для активации учетной записи через телефон номер (private) в разработке
 *  check_activate() - проверка активации. Принимает параметр  $_GET['activate_param'] с кодом активации
 *
 * send_mail()-функция, нужна для отправки письма. перед ее вызовом должны быть установленны параметр для отправки:
 * array('mail_name_from'=>, 'mail_subject'=>,'mail_body'=>, 'mail_email_from' => , 'mail_email_to'=>)
 * send_sms()-функция, нужна для отправки SMS.
 * get_lost_pass($param) - функция нужна, что бы восстановить пароль (1. Сгенерировать новый. 2.отправить пользователю на email/sms новый пароль)
 *
 *  login($redirect = null) - функция нужна, что выводить форму и проверять данные для авторизации $redirect - точка для редиректа
 * Поля (логин, пароль) функция узнает из массива правил в config/forma_validation.php)
 * 1 - логин
 * 2-пароль
 *logout() - функция, нужна, что бы разлогинивать
 *
 * _set_session($param=array()) - функция, нужна, что бы установить сессию. получает ассоциативный массив,
 *   и устанавливает на основе его сессии $_SESSION[$session_pref.$key] = $value
 *
 * _send_mime_mail() - функция нужна, что бы отправлять  email(private). Данные для отправки получает из свойств класса($mail_name_from,  $mail_subject,
 *   $mail_body,  $mail_email_from = 'admin@admin.ru', $mail_email_to)
 * _mime_header_encode($str, $data_charset, $send_charset) - вспомогательная функция для  формирования и отправки писем
 * _send_sms($param) - функция нужна, что бы отправить SMS
 *
 * РАБОТА С СОЦИАЛЬНЫМИ КНОПКАМИ
 * view_social()-функция, нужна, что бы вернуть HTML код  кнопой соц. сетей
 * handler_social() - функция нужна, что бы обработать ответ  полученный от соц. сети.  Возвращает массив данных.
 * В массиве есть параметр $array['is_user'] (TRUE/LASE), который сообщает есть ли такой пользователь в сети
 * login_social($redirect = null,$param=null) - функция нужна , чтобы залогировать пользователя через соц. сеть.
 * ПС для работы с соц. кнопками в режиме $soc_type_result = 'boolean', без перезагрузки нужно:
 * 1)написать JS функцию  на подобие handler_social($token), только со своим обработчиком.
 *  Функция посылает $_POST['token']  и получает данные пользователя в формате JSON
 * 2)указать функцию в параметре $soc_js_func; Пример $soc_js_func='handler_social'
 * 3) прописать  относительный путь к view: ulogin_xd.html в переменную $soc_ulogin_xd и положить файл в нужное место .
 * Пример: http://www.site.ru/data/ulogin/ulogin_xd.html
 *
* работа с группами
 * -создание группы
 * -установка юзер-группа
 * установка своих сообщений
 * соц. плагины
 * авторизация сразу после регистрации
 * перед  работой с БД проверить поля в таблице
 * */

class User_lib
{
    var $table = 'user'; /*data base table*/
    var $validation_name = 'user'; /*array in form_validation*/
    var $validation_login_name = 'login'; /*array in form_validation*/
    var $session_pref = ''; /*приставка для сесии*/
    var $session_var = array('id_user', 'pass2_user'); /*параметры, которые хранить в сессиях*/
    var $errors = array(); /*array errors*/
    var $result; /*result*/
    var $logs = array(); /*array logs*/
    var $data = array(); /*array var to insert/update ti DB*/
    /*АКТИВАЦИЯ*/
    var $type_activate = 'email'; /*по умолчанию активация через email*/
    var $is_activate = FALSE; /*по умолчанию не активировать пользователя*/
    var $subject_mail_activate = 'Активация регистрации.'; /*тема письма*/
    var $body_mail_activate = 'Здравствуйте!<br> Ссылка для активации:[%LINK%]'; /*шаблон для письма с активацией обязательно [%LINK%]*/
    var $body_phone_activate = 'Activacionni kod: [%AC_KOD%]'; /*шаблон для SMS с активацией  обязательно [%AC_KOD%]!*/
    var $site_point_act = ''; /*контроллер/метод для активации. В него будет передан $_GET['activate_param']*/
    var $field_with_activate_code;
    var $field_with_email;
    var $field_with_phone;
    var $field_with_pass;

    /*отправка писем*/
    var $mail_name_from = 'Администрация';
    var $mail_subject = '';
    var $mail_body = '';
    var $mail_email_from = 'admin@admin.ru';
    var $mail_email_to = '';

    /*восстановление пароля*/
    var $field_send_lost_pass = ''; /*имя поля, по которому ищу в БД параметр, который введет пользователь. например, email_user*/
    var $type_send_lost_pass = 'email'; /*метод восстановления пароля email/pass */
    var $subject_mail_lost_pass = 'Восстановление пароля'; /*тема письма*/
    var $body_mail_lost_pass = 'Здравствуйте!<br> Ваш новый пароль:[%PASS%]'; /*шаблон для письма с восстановлением  пароля. обязательно [%PASS%]!*/

    /*социальные кнопки*/
    /*массив соц. сетей. 'vkontakte','odnoklassniki','mailru','facebook','twitter','google','yandex','livejournal','openid'*/
    var $soc_providers = array('vkontakte', 'odnoklassniki');
    /*массив полей Доступны следующие поля: first_name - имя пользователя, last_name - фамилия, email - e-mail,
     nickname - псевдоним, bdate - дата рождения, sex - пол, photo - квадратная аватарка (до 100*100),
    photo_big - самая большая аватарка, которая выдаётся выбранной соц. сетью, city - город, country - страна.*/
    var $soc_user_fields = array('first_name', 'last_name', 'photo');
    /*Вид виджета 'small','panel','window';*/
    var $soc_type_widget = 'panel';
    /*тип результата: redirect - редирект на страницу(должен быть указан $soc_redirect_url)
    reload = перезагрузка текущей страницы,
    boolean - TRUE/FALSE*/
    var $soc_type_result = 'reload';
    /*урл для перенаправления. ФОрмат: controller/method  Если $soc_type_result= 'redirect'*/
    var $soc_url = '/';
    var $field_with_social;
    var $soc_js_func = 'handler_social';
    var $soc_ulogin_xd = '';


    /**
     * Constructor
     *
     * @param    string
     * @return    void
     */
    public function __construct($props = array())
    {
        /*устанавливаю настройки по умолчанию*/
        $this->field_with_activate_code = 'verif_' . $this->table;
        $this->field_with_email = 'email_' . $this->table;
        $this->field_with_phone = 'phone_' . $this->table;
        $this->field_with_pass = 'pass_' . $this->table;
        $this->field_with_id = 'id_' . $this->table;
        $this->field_send_lost_pass = $this->field_with_email;
        $this->field_with_social = array('vkontakte' => 'vk_' . $this->table, 'odnoklassniki' => 'od_' . $this->table, 'mailru' => 'ml_' . $this->table, 'facebook' => 'fb_' . $this->table, 'twitter' => 'tw_' . $this->table, 'yandex' => 'yn_' . $this->table, 'openid' => 'op_' . $this->table, 'livejournal' => 'lj_' . $this->table, 'google' => 'gm_' . $this->table,);

        if (count($props) > 0)
        {
            $this->initialize($props);
        }
        array_push($this->logs, "User Lib Class успешно иницилизирован!");
    }


    /**
     * initialize image preferences
     *
     * @access    public
     * @param    array
     * @return    bool
     */

    public function initialize($props = array())
    {
        /*
           * Convert array elements into class variables
           */
        if (count($props) > 0)
        {
            foreach ($props as $key => $val)
            {
                $this->$key = $val;
            }
        }

    }


    /**
     * return array errors
     *
     * @access    public
     * @param    none
     * @return    array
     */
    public function error()
    {
        if (!empty($this->errors)) return '<div style="background-color: #F2BABA;   border: 2px dashed red;  font-size:14px;  font-family: arial; padding: 15px;">' . implode('<br>', $this->errors) . '</div>'; else
            return FALSE;
    }


    /**
     * validate
     *
     * @access    public
     * @param    array
     * @return    int id new row
     */
    public function validate($rules = null)
    {
        $CI =& get_instance();
        $valid_me = $CI->input->post('valid_me');
        /*$valid_me = (!empty($valid_me))?$valid_me:$CI->input->get('valid_me');*/
        if (!$valid_me)
        {
            // array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Валидация не произвелась, так как не передан парамет valid_me');
            return FALSE;
        }
        $CI->load->library(array('form_validation'));
        if (!$rules) $this->validation_name = (empty($this->validation_name)) ? $this->table : $this->validation_name; else
            $this->validation_name = $rules;

        if ($CI->form_validation->run($this->validation_name))
        {
            return $this->data = $this->_get_form_array($this->validation_name);
        }
        return FALSE;
    }


    /**
     * add
     *
     * @access    public
     * @param    array
     * @return    int id new row
     */
    public function add($param = array())
    {
        $CI =& get_instance();
        $param = (!empty($param)) ? $param : $this->data;
        if (empty($param))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Нет данных для добавления в БД');
            return FALSE;
        }
        #TODO insert
        if ($this->_is_table() && $CI->db->insert($this->table, $param))
        {
            return $CI->db->insert_id();
        } else
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Ошибка добавления в БД');
            return FALSE;
        }


    }

    /**
     * check table
     *
     * @access    private
     * @param    none
     * @return    bool
     */
    private function _is_table()
    {
        if (empty($this->table))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не указанна таблица');
            return FALSE;
        }
        return TRUE;
    }

    /**
     * get_param_from form
     *
     * @access    private
     * @param    string
     * @return    array
     */

    private function _get_form_array($rules = null)
    {
        $CI =& get_instance();
        $CI->config->load('form_validation', true);
        $config_form_validation = $CI->config->item("form_validation");
        if (!$rules) $this->validation_name = (empty($this->validation_name)) ? $this->table : $this->validation_name; else
            $this->validation_name = $rules;

        foreach ($config_form_validation[$this->validation_name] as $item)
        {
            $form_array[$item['field']] = set_value($item['field']);

        }
        return $form_array;
    }


    /**
     * edit user
     *
     * @access    public
     * @param    $id, $array-
     * @return   bool
     */
    public function edit($id = null, $param = array())
    {
        $CI =& get_instance();
        $param = (!empty($param)) ? $param : $this->data;
        if (empty($param))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Нет данных для редактирования записи  в БД');
            return FALSE;
        }

        if ($this->_is_table() && $CI->db->update($this->table, $param, array($this->field_with_id => $id)))
        {
            return TRUE;
        } else
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Ошибка при обновлении  в БД');
            return FALSE;
        }
    }

    /**
     * delete user
     *
     * @access    public
     * @param    int $id
     * @return    bool
     */
    public function delete($id = null)
    {
        $CI =& get_instance();
        if (empty($id))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Нет данных для удаления в БД');
            return FALSE;
        }

        if ($this->_is_table() && $CI->db->delete($this->table, array($this->field_with_id => $id)))
        {
            return TRUE;
        } else
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Ошибка при удалении  в БД');
            return FALSE;
        }
    }

    /**
     * delete user
     * @access    public
     * @param    $id
     * @return    bool
     */
    public function view($id_user = null)
    {
        $CI =& get_instance();
        if (empty($id_user))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Нет данных для отображения пользователя');
            return FALSE;
        }

        if ($this->_is_table())
        {
            #TODO select
            $CI->db->select('*');
            $CI->db->from($this->table);
            $CI->db->where(array($this->field_with_id => $id_user));
            $CI->db->limit(1);
            return $CI->db->get()->row();

        }
        return FALSE;
    }

    /**
     * get_list user
     *
     * @access    public
     * @param    none
     * @return    bool
     */
    public function get_list()
    {
        $CI =& get_instance();

        if ($this->_is_table())
        {
            $CI->db->select('*');
            $CI->db->from($this->table);
            $CI->db->order_by($this->field_with_id . ' DESC');
            return $CI->db->get()->result();
        }
        return FALSE;
    }

    /**
     * activate user
     *
     * @access    public
     * @param    $id_user
     * @return    bool
     */
    public function activate($id_user = null)
    {
        if (empty($id_user))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b> Нет ID пользователя для активации его аккаунта');
            return FALSE;
        }

        if (!$this->_is_table()) return FALSE;
        $oUser = $this->view($id_user);
        //prn($oUser);
        if (empty($oUser))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Нет  пользователя (ID: ' . $id_user . ') для активации  аккаунта');
            return FALSE;
        }

        if ($this->type_activate == 'email')
        {
            $email = $oUser->{$this->field_with_email};
            if (empty($email))
            {
                array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не указан email для отправки. Причина: пустое поле/нет такого поля: ' . $this->field_with_email . '.
                Укажите нужное поле в свойстве: $field_with_email');
                return FALSE;
            }
            $this->_activate_to_email($id_user, $email);
            return TRUE;
        }

        if ($this->type_activate == 'phone')
        {
            $phone = $oUser->{$this->field_with_phone};
            if (empty($phone))
            {
                array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не указан phone для отправки. Причина: пустое поле/нет такого поля:' . $this->field_with_email . '.
                Укажите нужное поле в свойстве: $field_with_email');
                return FALSE;
            }
            $this->_activate_to_phone($id_user, $phone);
            return TRUE;
        }
        array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не указан/ не известный метод активации');
        return FALSE;

    }

    /**
     * activate to email
     *
     * @access    private
     * @param    int
     * @param    string
     * @return    bool
     */
    private function _activate_to_email($id, $email)
    {
        $CI =& get_instance();
        if (empty($this->site_point_act))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Для активации не указан параметр $site_point_act,
                в котором указывается контроллер/метод для получения кода активации $_GET[activate_param]');
            return FALSE;
        }
        $hash = md5(time());
        #TODO update
        $CI->db->update($this->table, array($this->field_with_activate_code => $hash), array($this->field_with_id => $id));
        //отправляю письмо
        $this->mail_subject = (strlen($this->mail_subject)) ? $this->mail_subject : $this->subject_mail_activate;
        $this->mail_body = (strlen($this->mail_body)) ? $this->mail_body : $this->body_mail_activate;
        $this->mail_body = str_replace('[%LINK%]', site_url($this->site_point_act . '?activate_param=' . $hash), $this->mail_body);
        $this->mail_email_to = $email;
        return $this->_send_mime_mail();

    }

    /**
     * check activate
     *
     * @access    public
     * @param    $_GET['activate_param']
     * @return    bool
     */
    public function  check_activate()
    {
        $CI =& get_instance();
        $param = htmlspecialchars($CI->input->get('activate_param'));
        if (empty($param))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не передан параметр ($_GET["activate_param"]) для активации');
            return FALSE;
        }
        $CI =& get_instance();
        $CI->db->update($this->table, array($this->field_with_activate_code => 1), array($this->field_with_activate_code => $param));
        return TRUE;

    }

    /**
     * activate to phone
     *
     * @access    private
     * @param    $id_user
     * @return    bool
     */

    private function _activate_to_phone($id, $phone)
    {
        $CI =& get_instance();
        $CI->load->helper('string');
        $hash = random_string('alnum', 5);

        $CI->db->update($this->table, array($this->field_for_activate => $hash), array($this->field_with_id => $id));
        //отправляю SMS
        # todo в разработке: определиться с библиотекой для отправки СМС

        return $this->_send_sms();
    }

    /**
     * send mail (public)
     * @access    public
     * @param    $id_user
     * @return    bool
     */
    public function  send_mail()
    {
        return $this->_send_mime_mail();
    }

    /**
     * send sms (public)
     * @access    public     *
     * @return    bool
     */
    public function  send_sms()
    {
        return $this->_send_sms();
    }

    /**
     * get_lost_pass
     * @access    public
     * @param    $param (email/phone)
     * @return    bool
     */
    public function  get_lost_pass($param = null)
    {
        if (!$param)
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>При вызове функции get_lost_pass() был передан параметр NULL(куда отправлять новый пароль).');
            return FALSE;
        }
        $CI =& get_instance();
        if ($this->_is_table())
        {
            $CI->db->select('*');
            $CI->db->from($this->table);
            $CI->db->where(array($this->field_send_lost_pass => $param));
            $CI->db->limit(1);
            $oUser = $CI->db->get()->row();

        }
        if (empty($oUser))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Нет такого пользователя');
            $this->result = 'empty_email';
            return FALSE;
        }

        $CI->load->helper('string');
        $new_pass = random_string('alnum', 7);
        $CI->db->update($this->table, array($this->field_with_pass => md5($new_pass)), array($this->field_send_lost_pass => $param));
        if ($this->type_send_lost_pass == 'email')
        {
            $this->mail_subject = (strlen($this->mail_subject)) ? $this->mail_subject : $this->subject_mail_lost_pass;
            $this->mail_body = (strlen($this->mail_body)) ? $this->mail_body : $this->body_mail_lost_pass;
            $this->mail_body = str_replace('[%PASS%]', $new_pass, $this->mail_body);
            $this->mail_email_to = $oUser->{$this->field_with_email};
            $this->_send_mime_mail();
        }
        if ($this->type_send_lost_pass == 'phone')
        {
            $new_pass; //-новый пароль отправить на телефон
            return FALSE;
        }

        return TRUE;


    }

    /**
     * login
     *
     * @access    public
     * @param    string redirect - controller/url
     * @return    bool
     */

    public function  login($redirect = null)
    {
        $CI =& get_instance();
        $CI->load->library(array('form_validation'));
        if ($CI->form_validation->run($this->validation_login_name) == true)
        {
            //узнаю поле  с логиином и паролем в БД
            $CI->config->load('form_validation', true);
            $config_form_validation = $CI->config->item("form_validation");
            $arLP = $config_form_validation[$this->validation_login_name];
            $login_field = (!empty($arLP[0]['field'])) ? $arLP[0]['field'] : NULL;
            $pass_field = (!empty($arLP[1]['field'])) ? $arLP[1]['field'] : NULL;

            if (empty($login_field) || empty($pass_field))
            {
                array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не указанны поля для логинизации.  в config/form_validation.php  Перввый параметр-логин, второй пароль');
                return FALSE;
            }
            $var_login_field = htmlspecialchars($CI->input->post($login_field));
            $pass_login_field = trim($CI->input->post($pass_field));
            $md5_pass_login_field = md5($pass_login_field);
            //проверяю логин
            $CI->db->select('*');
            $CI->db->from($this->table);
            $CI->db->where(array($login_field => $var_login_field));
            $CI->db->limit(1);
            $oUser = $CI->db->get()->row();
            if (empty($oUser))
            {
                array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Нет такого логина');
                $this->result = 'wrong_login';
                return FALSE;
            }
            if ($md5_pass_login_field != $oUser->{$pass_field})
            {
                array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не верный пароль');
                $this->result = 'wrong_pass';
                return FALSE;
            }

            if ($this->is_activate && $oUser->{$this->field_with_activate_code} != 1)
            {
                array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не активированна ученая запись');
                $this->result = 'wrong_verif';
                return FALSE;
            }
            /*формирую массив  переменных  для сессий*/
            $arUser = (array)($oUser);
            $CI->load->helper('array_helper');
            if (empty($this->session_var) || (!is_array($this->session_var)))
            {
                array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не указан массив переменных, которые нужно добавлять в сесии. $session_var');
                return FALSE;
            }
            $session_array = elements($this->session_var, $arUser);
            $this->_set_session($session_array);
            if ($redirect) redirect(site_url($redirect));
            return TRUE;

        }
    }

    /**
     * login by id
     *
     * @access    public
     * @param    int  id_user
     * @param    string redirect - controller/url
     * @return    bool
     */

    public function  login_by_id($id_user = null, $redirect = null)
    {
        $id_user = (int)$id_user;
        if (empty($id_user))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Нет ID пользователя');
            return FALSE;
        }
        $CI =& get_instance();
        $CI->db->select('*');
        $CI->db->from($this->table);
        $CI->db->where(array($this->field_with_id => $id_user));
        $CI->db->limit(1);
        $oUser = $CI->db->get()->row();
        if (empty($oUser))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Нет такого пользователя');
            $this->result = 'wrong_id';
            return FALSE;
        }
        /*формирую массив  переменных  для сессий*/
        $arUser = (array)($oUser);
        $CI->load->helper('array_helper');
        if (empty($this->session_var) || (!is_array($this->session_var)))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не указан массив переменных, которые нужно добавлять в сесии. $session_var');
            return FALSE;
        }
        $session_array = elements($this->session_var, $arUser);
        $this->_set_session($session_array);
        if ($redirect) redirect(site_url($redirect));
        return TRUE;


    }


    /**
     * log out user
     *
     * @access    public
     * @param    none
     * @return    bool
     */
    public function logout($redirect = null)
    {
        $oCI = & get_instance();

        $aKeys = array_keys($oCI->session->userdata);

        foreach ($aKeys as $item)
        {
            $data_unset[$item] = "";
        }
        $oCI->session->unset_userdata($data_unset);
        if ($redirect) redirect(site_url($redirect));
        return TRUE;
    }

    /**
     * set session
     * @access    private
     * @param    array $ses_array
     * @return    bool
     */
    private function _set_session($ses_array = null)
    {
        if (!$ses_array)
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не указанны переменные, которое нужно добавить в сессию');
            return FALSE;
        }
        $oCI = & get_instance();
        foreach ($ses_array as $key => $value)
        {
            $data_set[$this->session_pref . $key] = $value;
        }
        $oCI->session->set_userdata($data_set);

    }

    /*******************************************Работа с социальными кнопками*********************************************************/
    /**
     * view social button
     * @access    public
     * @return    html
     */
    public function view_social()
    {
        //проверяю есть ли библиотеки
        if (!is_file(realpath('.') . '/' . APPPATH . 'libraries/Ulogin.php'))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Нет библиотеки Ulogin.php ');
            return FALSE;
        }
        if (!is_file(realpath('.') . '/' . APPPATH . 'libraries/Uauth.php'))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Нет библиотеки Uauth.php ');
            return FALSE;
        }
        $oCI = & get_instance();
        $array_social = array('types' => $this->soc_type_widget, 'fields' => $this->soc_user_fields, 'providers' => $this->soc_providers);
        $oCI->load->library('ulogin', $array_social);
        if ($this->soc_type_result == 'boolean')
        {
            if ((!empty($this->soc_js_func)) && (!empty($this->soc_ulogin_xd)))
            {
                array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b> Для работы с кнопками в режиме boolean (без перезагрузки),
                не указанны параметры:  $soc_js_func или $soc_ulogin_xd');
                return FALSE;
            }
            $oCI->ulogin->set_callback($this->soc_js_func, $this->soc_ulogin_xd);
        }

        if ($this->soc_type_result == 'redirect') $oCI->ulogin->set_url(site_url($this->soc_url));

        return $oCI->ulogin->get_html();

    }

    /**
     * handler social button
     * @access    public
     * @return    array
     */
    public function handler_social()
    {
        $oCI = & get_instance();
        $token = $oCI->input->post('token');
        if (!$token)
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b> Нет переменной $_POST[token]');
            return FALSE;
        }
        $array_social = array('types' => $this->soc_type_widget, 'fields' => $this->soc_user_fields, 'providers' => $this->soc_providers,);
        $oCI->load->library('ulogin', $array_social);
        $result = $oCI->ulogin->userdata();

        if (empty($this->field_with_social[$result['network']]))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b> Нет поля в БД для  этой соц. сети. Смотреть  переменную $field_with_social');
            return FALSE;
        }
        if ($this->_is_table())
        {
            $oCI->db->select('*');
            $oCI->db->from($this->table);
            $oCI->db->where(array($this->field_with_social[$result['network']] => $result['uid']));
            $oCI->db->limit(1);
            $result2 = $oCI->db->get()->row();
        }
        $result['id_user'] = (!empty($result2)) ? $result2->{$this->field_with_id} : FALSE;
        return $result;

    }

    /*************************функции для отправки писем/sms********************************/
    /**
     * send to email
     *
     * @access    private
     * @param    none
     * @return    bool
     */
    private function _send_mime_mail()
    {
        $send_charset = 'utf8';
        $data_charset = 'utf8';
        $flag = true;
        if (!strlen($this->mail_body))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не указанна тема письма ($main_body)');
            $flag = FALSE;
        }
        if (!strlen($this->mail_name_from))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не указанно имя отправителя ($mail_name_from)');
            $flag = ($flag) ? FALSE : $flag;
        }
        if (!strlen($this->mail_subject))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не указанна тема письма ($mail_subject)');
            $flag = ($flag) ? FALSE : $flag;
        }
        if (!strlen($this->mail_email_from))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не указан email отправителя ($mail_email_from)');
            $flag = ($flag) ? FALSE : $flag;
        }
        if (!strlen($this->mail_email_to))
        {
            array_push($this->errors, '<b>' . __METHOD__ . '(line: ' . __LINE__ . '): </b>Не указан email получателя ($mail_email_to)');
            $flag = ($flag) ? FALSE : $flag;
        }
        if (!$flag)
        {
            return FALSE;
        }
        $to = $this->mail_email_to;
        $subject = $this->_mime_header_encode($this->mail_subject, $data_charset, $send_charset);
        $from = $this->_mime_header_encode($this->mail_name_from, $data_charset, $send_charset) . ' <' . $this->mail_email_from . '>';
        $body = $this->mail_body;
        if ($data_charset != $send_charset)
        {
            $body = iconv($data_charset, $send_charset, $this->mail_body);
        }

        $headers = "Content-type: text/html; charset=\"" . $send_charset . "\"\n";
        $headers .= "From: $from\n";
        $headers .= "Mime-Version: 1.0\n";
        return mail($to, $subject, $body, $headers);
    }

    /* mime header encode
    *
    * @access    private
    * @param    sting
    * @param    sting
    * @param    sting
    * @return    bool
    */
    private function _mime_header_encode($str, $data_charset, $send_charset)
    {
        if ($data_charset != $send_charset)
        {
            $str = iconv($data_charset, $send_charset, $str);
        }
        return '=?' . $send_charset . '?B?' . base64_encode($str) . '?=';
    }

    /**
     * send to sms
     *
     * @access    private
     * @param    none
     * @return    bool
     */
    private function _send_sms()
    {
        return FALSE;

    }
    /*************************************************************************************/
}

?>
