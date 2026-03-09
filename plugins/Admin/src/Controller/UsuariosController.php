<?php
declare(strict_types=1);

namespace Admin\Controller;

// use Admin\Controller\AppController;

use DateTime;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\View\Exception\MissingTemplateException;
use Cake\Utility\Security;

class UsuariosController extends AppController
{
    public function initialize() : void{
        parent::initialize();

        $this->loadModel('Admin.SysUsuario');
    }

    public function login()
    {
        $target_url = 'https://www.google.com/recaptcha/api/siteverify';
        $post = array(
            'secret' => '6LcCZ5saAAAAAGZg5xp_wUE9bGJU6WTH80J5sTOf',
            'response' => get('token','')
        );

        
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL,$target_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        $result = curl_exec ($ch);
        curl_close ($ch);

        if ($result)
            $decode = json_decode($result,true);
        if (!empty($decode)) {
        // if (empty($decode)) {
            // if ($decode['success'] == true) {
            if (1 == 1) {
                 $str_usermail = get('user','');
                    $str_passwd = get('passwd', '');

                    $result = $this->Access->login($str_usermail, $str_passwd);

                    if($result['success'] == true){
                        $this->Response->success();
                    }else{
                        $this->Response->message($result['message']);
                    }
            } else {
                $this->Response->message("Invalid reCAPTCHA");
            }
        } else {
            $this->Response->message("Invalid reCAPTCHA");
        }

       
    }

    public function loginu() {

        $str_username = get('user', '');
        $passwd =  get('passwd','');


        if (empty($str_username)) {
            $this->Response->message('invalid "email" parameter.');
            return;
        }
        if (empty($passwd)) {
            $this->Response->message('invalid "password" parameter.');
            return;
        }

        $strModel = 'SysUsers';
        $this->loadModel("SpaLiveV1.SysUsers");

        $ent_user = $this->$strModel->find()->select(["SysUsers.id","SysUsers.uid","SysUsers.email","SysUsers.password","SysUsers.name","SysUsers.active","SysUsers.type","SysUsers.login_status","SysUsers.score","SysUsers.photo_id","SysUsers.description"])
            ->where(["SysUsers.email" => $str_username, "{$strModel}.deleted" => 0,'SysUsers.active' => 1])->first();
            

        if(!empty($ent_user)){
            $str_passwd_sha256 = hash_hmac('sha256', $passwd, Security::getSalt());

            if($ent_user->active == 0){
                $this->Response->message('User inactive.');
                return;
            }elseif($str_passwd_sha256 != $ent_user->password) {
                $this->Response->message('Password incorrect.');
                return;
            }else{



                $this->loadModel("SpaLiveV1.DataCertificates");
                $ent_certificate = $this->DataCertificates->find()
                    ->join([
                        'Consultation' => ['table' => 'data_consultation', 'type' => 'INNER', 'conditions' => 'Consultation.id = DataCertificates.consultation_id AND Consultation.patient_id = ' . $ent_user->id],
                    ])->where(['DataCertificates.uid' => get('uid','')])->first();

                if (!empty($ent_certificate)) {


                    $array_save = array(
                        'token' => uniqid('', true),
                        'user_id' => $ent_user->id,
                        'user_role' => $ent_user->type,
                        'deleted' => 0,
                    );
                    $this->loadModel("SpaLiveV1.AppTokens");
                    $entity = $this->AppTokens->newEntity($array_save);
                    if(!$entity->hasErrors()){
                        if($this->AppTokens->save($entity)){
                            $this->Response->success();
                            $url = env('url_api', 'https://app.spalivemd.com/api/') . '?key=2fe548d5ae881ccfbe2be3f6237d7951&action=get-certificate&uid=' . get('uid','') . '&token=' . $array_save['token'] . '&ry34=v2rib982jfjbos93kgda2rg';
                            $this->Response->set('url', $url);
                        }
                    } else{
                        $this->Response->message('Unexpected error.');
                    }

                }

                
            }
        }else{
            $this->Response->message('User doesn\'t exist.');
        }

    }

    public function logout()
    {
        $this->Access->logout();
        $this->Response->success();
    }

    public function profile()
    {
        $where = ['SysUsuario.id' => USER_ID];

        $ent_reg = $this->SysUsuario->find()
            ->select([
                'SysUsuario.uid','SysUsuario.name','SysUsuario.username','SysUsuario.active',
            ])
            ->where($where)->first();

        $array_data = array(
            'uid' => trim($ent_reg->uid),
            'name' => trim($ent_reg->name),
            'username' => trim($ent_reg->username),
            'active' => $ent_reg->active? true : false,
        );

        $this->Response->success();
        $this->Response->set('data', $array_data);
    }

    public function load()
    {
        $this->loadModel('Admin.SysRol');
        $this->loadModel('Admin.SysModel');
        $this->loadModel('Admin.SysPermiso');

        $int_id = $this->force_get_id($this->SysUsuario, 'uid');

        $where = ['SysUsuario.id' => $int_id];

        $ent_reg = $this->SysUsuario->find()
            ->select([
                'SysUsuario.uid','SysUsuario.name','SysUsuario.username','SysUsuario.active',
            ])
            ->where($where)->first();

        $array_data = array(
            'uid' => trim($ent_reg->uid),
            'name' => trim($ent_reg->name),
            'username' => trim($ent_reg->username),
            'active' => $ent_reg->active? true : false,
            'groups' => $this->_user_groups($this->SysRol->get_user_groups($int_id)),
            'permissions' => $this->_user_permissions($this->SysPermiso->get_user_permissions($int_id)),
            'resources' => $this->_user_resources($this->SysModel->get_user_resources($int_id)),
        );

        $this->Response->success();
        $this->Response->set('data', $array_data);
    }

    private function _user_groups($rows){
        $result = [];

        foreach($rows as $row){
            $result[] = [
                'uid' => $row->uid,
                'title' => $row->name,
                // 'subtitle' => "{$row->SysGroupPermission['access_level']} - {$row->SysGroupPermission['access_resources']}",
                'detail' => $row->description,
                'created' => $row->SysUserGroup['created'],
                'permissions' => $this->_group_permissions($this->SysPermiso->get_group_permissions($row->id)),
                'resources' => $this->_group_resources($this->SysModel->get_group_resources($row->id)),
            ];
        }

        return $result;
    }

    public function save(){
        $this->loadModel('Admin.SysPermiso');

        $int_modelo_id = $this->force_if_get_id($this->SysUsuario, 'uid');

        $array_save = array(
            'name' => get('name',''),
            'username' => get('username',''),
            'active' => get('active','off') == 'on'? 1 : 0,
        );

        $groups = json_decode(get('groups','[]'), true);
        $resources = json_decode(get('resources','[]'), true);
        $permissions = json_decode(get('permissions','[]'), true);

        if($int_modelo_id > 0){
            $array_save['id'] = $int_modelo_id;
        }

        $entity = $this->SysUsuario->newEntity($array_save);
        if(!$entity->hasErrors()){
            if($this->SysUsuario->save($entity)){
                $this->Response->success();
                $this->Response->set('uid', $entity->uid);
                $this->Response->set('new', !$entity->isNew());

                $this->Access->set_groups_user($entity->id, $groups);
                $this->Access->save_resources('User', $entity->id, $resources);
                $this->Access->save_permissions('User', $entity->id, $permissions);
            }
        }else{
            $this->Response->add_errors($entity->getErrors());
        }
    }

    public function upasswd(){
        $passwd1 = get('passwd1','');
        $passwd2 = get('passwd2','');

        if(empty($passwd1) || strlen($passwd1) < 6){
            $this->Response->add_errors('Nueva Contraseña Invalida.');
            return;
        }
        if(empty($passwd2) || strlen($passwd2) < 6){
            $this->Response->add_errors('Confirmación de Contraseña Invalida.');
            return;
        }

        if($passwd1 != $passwd2){
            $this->Response->add_errors('La Nueva Contraseña y su Confirmación deben ser Iguales.');
            return;
        }

        $array_save = array(
            'id' => USER_ID,
            'password' => $this->Access->get_password_sha256($passwd1),
        );

        $entity = $this->SysUsuario->newEntity($array_save);
        if(!$entity->hasErrors()){
            if($this->SysUsuario->save($entity)){
                $this->Response->success();

                $this->Access->Session('_Session.change_password', false);
            }
        }else{
            $this->Response->add_errors($entity->getErrors());
        }
    }

    public function password(){
        $this->loadModel('Admin.SysTempPassword');

        
        $str_passwd = get('password','');
        $str_new_passwd = get('password_new','');
        $str_confirm_passwd = get('password_confirm','');

        if(empty($str_passwd)){
            $this->Response->add_errors('Password cant be empty.');
            return;
        }

        $where = ['SysUsuario.id' => USER_ID];

        $ent_reg = $this->SysUsuario->find()->where($where)->first();

        $shapassword = hash_hmac('sha256', $str_passwd, Security::getSalt());

        if (empty($ent_reg)) {
            $this->Response->add_errors('Wrong user.');
            return;
        }

        if ($ent_reg->password != $shapassword) {
            $this->Response->add_errors('Wrong password.');
            return;
        }

        if ($str_new_passwd != $str_confirm_passwd) {
             $this->Response->add_errors('Password and confirmation are not equal.');
            return;
        }


        // if(strlen($str_passwd) < 7){
        //     $this->Response->add_errors('La contraseña de ser de al menos 6 caracteres.');
        //     return;
        // }

        // $this->SysTempPassword->query()->update()->set(['deleted' => 1])->where(['user_id' => $int_modelo_id])->execute();

        $ent_reg->password = hash_hmac('sha256', $str_new_passwd, Security::getSalt());
        if($this->SysUsuario->save($ent_reg)){
            $this->Response->success();
        }
    }

    public function grid(){
        // $int_start = get('start', 0);
        // $int_limit = get('limit', 1000);

        $where = array(
            'SysUsuario.deleted' => 0,
            'SysUsuario.id >' => 1,
        );

        $total = $this->SysUsuario->find()->where($where)->count();

        $arr_regs = $this->SysUsuario->find()
            ->select([
                'SysUsuario.uid',
                'SysUsuario.name',
                'SysUsuario.username',
                'SysUsuario.active',
                'SysUsuario.last_login',
                'groups' => "(SELECT GROUP_CONCAT(G.name SEPARATOR ',' ) FROM sys_users_groups UG JOIN sys_groups G ON G.id = UG.group_id AND G.deleted = 0 WHERE UG.user_id = SysUsuario.id AND UG.deleted = 0)",
            ])
            ->where($where)
            ->order([
                'SysUsuario.name' => 'ASC'
            ]);
            // ->offset($int_start)
            // ->limit($int_limit);
// debug($arr_regs);
//             echo '%';
//             echo $arr_regs->sql();
//             echo '%';
// exit;
        $arr_data = array();
        foreach($arr_regs as $reg){
            $arr_data[] = array(
                'uid' => $reg->uid,
                'name' => $reg->name,
                'username' => $reg->username,
                'groups' => $reg->groups,
                'active' => $reg->active,
                'last_login' => $reg->last_login,
            );
        }

        $this->Response->set('total', $total);
        $this->Response->set('data', $arr_data);
        $this->Response->success(true);
    }

    public function delete(){
        $int_modelo_id = $this->force_if_get_id($this->SysUsuario, 'uid');

        $module = $this->SysUsuario->get($int_modelo_id);
        $module->deleted = 1;

        $this->SysUsuario->save($module);
        if(!$module->hasErrors()){
            $this->Response->success();
        }else{
            $this->Response->add_errors('Error al borrar el registro.');
        }
    }

    public function combobox()
    {
        $rows = $this->SysUsuario->find()
            ->select([
                'SysUsuario.uid','SysUsuario.name'
            ])
            ->where(['SysUsuario.deleted' => 0])
            ->order(['SysUsuario.name ASC']);

        $array_data = array();
        foreach($rows as $row){
            $row->level = intval($row->level) - 1;

            $array_data[] = array(
                'uid' => trim($row->uid),
                'name' => trim($row->name),
            );
        }

        $this->Response->success();
        $this->Response->set('data', $array_data);
    }

    public function _()
    {
        // TODO; revisar y validar que sea un propietario.
        if(Configure::read('App.allow_restart') === true){
            if($this->Access->restart_db()){
                $this->Access->logout();
                $this->Response->success();
            }
        }
    }
}