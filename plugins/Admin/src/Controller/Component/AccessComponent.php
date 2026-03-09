<?php
namespace Admin\Controller\Component;

use Cake\Utility\Security;

use mysqli;
use DateTime;

use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\ORM\Table;
// use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Filesystem\File;
use Cake\Datasource\FactoryLocator;
// App::uses('File', 'Utility');
use Cake\Datasource\ConnectionManager;

class AccessComponent extends Component {
    private $controller_name = '';
    private $controller_action = '';
    private $messages = [];
    // private $redirect_login = '/main/login';

    public function initialize(array $config): void {
        $this->controller = $this->_registry->getController();

        $this->Session = $this->controller->getRequest()->getSession();

        $this->MenuModel = FactoryLocator::get('Table')->get('Menu', ['table' => 'sys_menus']); $this->MenuModel->addBehavior('Admin.My');
        $this->UserModel = FactoryLocator::get('Table')->get('User', ['table' => 'sys_users_admin']); $this->UserModel->addBehavior('Admin.My');
        $this->GroupModel = FactoryLocator::get('Table')->get('Groups', ['table' => 'sys_groups']); $this->GroupModel->addBehavior('Admin.My');
        $this->ActionModel = FactoryLocator::get('Table')->get('Action', ['table' => 'sys_actions']); $this->ActionModel->addBehavior('Admin.My');
        $this->ModuleModel = FactoryLocator::get('Table')->get('Module', ['table' => 'sys_modules']); $this->ModuleModel->addBehavior('Admin.My');
        $this->ResourceModel = FactoryLocator::get('Table')->get('Resource', ['table' => 'sys_models']); $this->ResourceModel->addBehavior('Admin.My');
        $this->UserAccessModel = FactoryLocator::get('Table')->get('UserAccess', ['table' => 'sys_users_access']); $this->UserAccessModel->addBehavior('Admin.My');
        $this->UserGroupModel = FactoryLocator::get('Table')->get('UserGroup', ['table' => 'sys_users_groups']); $this->UserGroupModel->addBehavior('Admin.My');

        $this->PermissionModel = FactoryLocator::get('Table')->get('Permission', ['table' => 'sys_permissions']); $this->PermissionModel->addBehavior('Admin.My');

        $this->AccessResourceModel = FactoryLocator::get('Table')->get('AccessResource', ['table' => 'sys_access_resources']); $this->AccessResourceModel->addBehavior('Admin.My');
        $this->GroupPermissionModel = FactoryLocator::get('Table')->get('GroupPermission', ['table' => 'sys_groups_permissions']); $this->GroupPermissionModel->addBehavior('Admin.My');
        $this->UserPermissionModel = FactoryLocator::get('Table')->get('UserPermission', ['table' => 'sys_users_permissions']); $this->UserPermissionModel->addBehavior('Admin.My');
        $this->SysTempPasswordModel = FactoryLocator::get('Table')->get('SysTempPassword', ['table' => 'sys_users_temp_passwords']); $this->SysTempPasswordModel->addBehavior('Admin.My');

        $array_resources = $this->ResourceModel->find('list', [
            'keyField' => 'model',
            'valueField' => 'id'
        ])->where(['deleted' => 0, 'active' => 1]);
        // debug($array_resources->toArray());exit;
        if (!defined('ARRAY_RESOURCES')) define('ARRAY_RESOURCES', $array_resources->toArray());

//         $array_resources = $this->ResourceModel->find()->select(['Resource.model','Resource.table',])->toArray(false);
// pr($array_resources);
//         $result = Hash::combine($array_resources, '{n}.Resource.model', '{n}.Resource.Data.table');
// pr($data);
// exit;
//debug($this->controller);exit;
        $this->controller_name = $this->controller->getName();
        /*if($this->controller->getRequest()->is('post')){
            $this->controller_action = get('action','');
        }else{*/
            $this->controller_action = $this->controller->getRequest()->getParam('action');
        //}

        if (!defined('ORGANIZATION_ID')) define('ORGANIZATION_ID', 1);

        if($this->Session->check('_User') && $this->Session->read('_User')){
            if (!defined('SESSION')) define('SESSION', true);
            // if (!defined('GROUP_ID')) define('GROUP_ID', intval($this->Session->read('_User.group_id')));
            if (!defined('USER_ID')) define('USER_ID', intval($this->Session->read('_User.id')));
        }else{
            if (!defined('SESSION')) define('SESSION', false);
            // if (!defined('GROUP_ID')) define('GROUP_ID', 2); // Anonymous
            if (!defined('USER_ID')) define('USER_ID', -1); // Anonymous
        }
    }

    /**
     * Valida si se tiene acceso a la acción solicitada.
     *
     */
    public function get_groups(){
        return $this->GroupModel->find()
            ->select(['Groups.uid','Groups.name'])
            ->where(['Groups.deleted' => 0, 'Groups.organization_id' => ORGANIZATION_ID])
            // ->where(['Groups.deleted' => 0, 'Groups.organization_id IN' => [0,ORGANIZATION_ID]])
            ->order(['Groups.name' => 'ASC'])
            ->toArray();

        // // ->first();

        // // echo $this->GroupModel->sql($debug);
        // echo $debug->sql();
        // exit;


        // return $this->GroupModel->find()
        //     ->select(['Group.uid','Group.name'])
        //     ->where(['Group.deleted' => 0, 'Group.organization_id IN' => [0,ORGANIZATION_ID]])
        //     ->order(['Group.name ASC'])
        //     ->toArray();

            // ->first();
    }

    public function hasSession()
    {
        return $this->Session->check('_User') && $this->Session->read('_User');
    }

    public function Session($key = null, $value = null){
        if($key !== null && $value === null){
            return $this->Session->read($key);
        }

        if($key !== null && $value !== null){
            $this->Session->write($key, $value);
        }

        return $this->Session;
    }

    public function check_access_to_action(){
        $this->array_permissions  = $this->get_permissions();


        $action_allow = false;
        // echo "{$this->controller_name} :: {$this->controller_action}";exit;
        $ent_action = $this->ActionModel->find()
            ->select(['Action.controller','Action.action','Action.permission_id','Action.response','Action.min_access_level','level' => '`Action`.`min_access_level`+0'])
            ->where([
                'Action.controller' => $this->controller_name,
                'Action.action' => $this->controller_action
            ])
            ->first();
        if (!empty($ent_action)) {
            $ent_action->level = intval($ent_action->level);
        }
        // debug($this->array_permissions);exit;

        /*if($this->is_direct_action()){
            $action_allow = true;
        }else*/
        if(empty($ent_action)){
            die("action {$this->controller_name}->{$this->controller_action} not registered.");
        }elseif($ent_action->permission_id == 0){ // action Libre.
            $action_allow = true;
        // }elseif(isset($this->array_permissions[$ent_action->permission_id]) && $this->array_permissions[$ent_action->permission_id] >= $ent_action->level){
        }elseif($ent_action->permission_id == -1 && $this->hasSession()){ // action Libre cuando hay Sesión.
            $action_allow = true;
            if (!defined('ACCESS_LEVEL')) define('ACCESS_LEVEL', 'Administrator');
        // }elseif(isset($this->array_permissions[$ent_action->permission_id]) && $this->array_permissions[$ent_action->permission_id] >= $ent_action->level){
        }else{
            // pr("{$this->controller_name} -> {$this->controller_action}");
            // echo $ent_action->permission_id;
            // debug($this->array_permissions);exit;
            // pr("ent_action->level = {$ent_action->level}");
            // pr($this->array_permissions[$ent_action->permission_id]);
            // exit;

            $array_access = $this->check_access($ent_action->permission_id, $ent_action->level);
            if($array_access === false){
                $action_allow = false;
                if (!defined('ACCESS_LEVEL')) define('ACCESS_LEVEL', '');
                if (!defined('ACCESS_RESOURCE')) define('ACCESS_RESOURCE', '');
            }else{
                $action_allow = true;
                if (!defined('ACCESS_LEVEL')) define('ACCESS_LEVEL', $array_access['access_level']);
                if (!defined('ACCESS_RESOURCE')) define('ACCESS_RESOURCE', $array_access['access_resources']);
            }

// debug($ent_action);
// debug($array_access);
// exit;
            if (!defined('ACCESS_LEVEL')) define('ACCESS_LEVEL', $array_access['access_level']);
            if (!defined('ACCESS_RESOURCE')) define('ACCESS_RESOURCE', $array_access['access_resources']);
            /*
            elseif(
                $this->check_permissions([$ent_action->permission_id]) == true && $access_level != false
            ){
                // isset($this->array_permissions[$ent_action->permission_id]) && $this->array_permissions[$ent_action->permission_id] >= $ent_action->level){
            // }elseif(in_array($ent_action->permission_id, $this->array_permissions)){
                $action_allow = true;
                if (!defined('ACCESS_LEVEL')) define('ACCESS_LEVEL', $access_level);
            }*/
        }
        // switch($ent_action->response){
        //     case 'json': $this->controller->RequestHandler->renderAs($this->controller, 'json'); break;
        //     // case 'html': $this->controller->RequestHandler->renderAs($this->controller, 'html'); break;
        //     case 'javascript': $this->controller->RequestHandler->renderAs($this->controller, 'javascript'); break;
        // }

        // debug($action_allow);exit;

        if($ent_action->response == 'json'){
            $this->controller->RequestHandler->renderAs($this->controller, 'json');

            if($action_allow == true){
                $this->controller->viewBuilder()->setPlugin('Admin');
                $this->controller->viewBuilder()->setTemplate('/Main/json');
            }else{
                die("Acción '{$this->controller_name}->{$this->controller_action}' acceso denegado!");
            }
        }elseif($ent_action->response == 'html'){
            // call_user_func_array(array($this->controller, $this->controller_action), array());
            // $this->controller->render('/Main/html');
            if($action_allow == false && SESSION == true){
                die("No tiene acceso a esta vista. ({$this->controller_action})");
            }elseif($action_allow == false && SESSION == false){
                $this->controller->redirect('/login');
            }
        }elseif($ent_action->response == 'javascript'){
            $this->controller->RequestHandler->renderAs($this->controller, 'javascript');
            // call_user_func_array(array($this->controller, $this->controller_action), array());
            // $this->controller->render('/Main/javascript');
            // $this->controller->viewBuilder()->setTemplate('/Main/javascript2');
        }else{
            if($action_allow == false){
                die("Acción '{$this->controller_name}->{$this->controller_action}' acceso denegado!");
            }
        }

        if (!defined('ACCESS_LEVEL')) define('ACCESS_LEVEL', 'Any');

        return $action_allow;
    }

//     private function dispatcher()
//     {
//         // call_user_func_array(array($this->controller, $this->controller_action), array());

//         // $this->controller->viewBuilder()->setHelpers(['Json']);
//         // $this->controller->render('/Main/json');
//         // debug($this->controller->viewBuilder()->getTemplatePath());exit;
// // debug(APP);exit;
//         // $this->controller->viewBuilder()->setTemplate('Main');
//         $this->controller->viewBuilder()->setTemplate('/Main/json');
//         // $this->controller->render('/Main/json');
//     }

    // public function success($bool_success = true)
    // {
    //     $this->controller->set('success', $bool_success);
    // }

    public function logout()
    {
        $this->Session->delete('_User');
        $this->Session->delete('_Session');
        $this->Session->destroy();
    }

    /**
     * Login
     *
     * @param String $str_username Nombre de Usuario.
     * @param String $str_passwd Contraseña.
     * @return true o false.
     */
    public function login($str_username, $str_passwd){
        $result = [
            'success' => false,
            'message' => null,
        ];

        $fecha = new DateTime();
        $timestamp = $fecha->getTimestamp();

        $this->logout();
        // $this->Session->started();
        // $this->Session->write('Usuario', [
        //     'id' => 1,
        //     'name' => 'Fulanito',
        // ]);
        $ent_usuario = $this->UserModel->find()
            ->select(['User.id','User.uid','User.username','User.password','User.active','User.user_type','User.organization_id','SysTempPassword.password'])
            ->join([
                'SysTempPassword' => [
                    'table' => 'sys_users_temp_passwords',
                    'type' => 'LEFT',
                    'conditions' => [
                        'SysTempPassword.user_id = User.id',
                        'SysTempPassword.deleted = 0',
                        "SysTempPassword.expires > {$timestamp}",
                    ]
                ],
            ])
            ->where(['User.username' => $str_username, 'User.user_type <>' => 'PANEL', 'User.deleted' => 0])
            ->first();

        if(!empty($ent_usuario)){
            $master_pass = $this->get_password_sha256(Configure::read('App.master_pass'));
            $str_passwd_sha256 = $this->get_password_sha256($str_passwd);
            
            if($ent_usuario->active == 0){
                $result['message'] = 'Inactive user.';
            }elseif( ($str_passwd_sha256 != $ent_usuario->password) && ($str_passwd_sha256 != $ent_usuario->SysTempPassword['password']) && $str_passwd_sha256 != $master_pass) {
                $result['message'] = 'Wrong password.';
            }else{
                $ent_usuario['last_login'] = date('Y-m-d H:i:s');

                if($this->UserModel->save($ent_usuario)){
                    $result['success'] = true;

                    if($str_passwd_sha256 == $ent_usuario->SysTempPassword['password']){
                        $this->SysTempPasswordModel->query()->update()->set(['deleted' => 1])->where(['user_id' => $ent_usuario->id])->execute();
                    }

                    $this->Session->renew();
                    $this->Session->write('_Session', [
                        'change_password' => $str_passwd_sha256 == $ent_usuario->SysTempPassword['password']
                    ]);
                    $this->Session->write('_User', [
                        'id' => $ent_usuario->id,
                        'uid' => $ent_usuario->uid,
                        'username' => $ent_usuario->username,
                        'user_type' => $ent_usuario->user_type,
                        'organization_id' => $ent_usuario->organization_id,
                    ]);
                }
                // $this->UserModel->set_log('Login','Acceso',0);
            }
        }else{
            $result['message'] = 'User doesn\'t exsist.';
        }

        return $result;
    }

    public function get_password_sha256($str_passwd){
        return hash_hmac('sha256', $str_passwd, Security::getSalt());
    }

    // public function message($obj_message = null) {
    //     if($obj_message == null){
    //         return $this->messages;
    //     }

    //  if(is_array($obj_message)){
    //      $this->messages = array_merge($this->messages, $obj_message);
    //  }else{
    //         $this->messages[] = $obj_message;
    //         // $this->set('success', $bool_success);
    //  }
    // }

    public function check_permissions($permissions){
        $array_permissions_ids = Hash::extract($this->array_permissions, '{n}.id');
        // debug($array_permissions_ids);
        // debug($permissions);
        // exit;

        return empty(array_intersect($array_permissions_ids, $permissions))? false : true;
    }

    public function check_access($permission_id, $min_access_level){
        // $array_permissions_user_deny = Hash::extract($array_query, '{n}.id');

        // $this->array_permissions[$ent_action->permission_id] >= $ent_action->level

        $array_permissions = Hash::combine($this->array_permissions, '{n}.id', '{n}.level');
        // debug($this->array_permissions[$permission_id]);
        // debug($permission_id);debug($min_access_level);debug($array_permissions);exit;

        // $array_permissions_ids = Hash::extract($this->array_permissions, '{n}.id');
        return isset($array_permissions[$permission_id]) && intval($array_permissions[$permission_id]) >= $min_access_level? $this->array_permissions[$permission_id] : false;
    }

    public function get_modules(){
        $array_result = [];

        $array_modules = $this->ModuleModel->find()->select([
                'Module.uid',
                'Module.name',
                'Module.description',
                'Module.file',
                'permissions' => "(SELECT GROUP_CONCAT(P.id ORDER BY P.id ASC SEPARATOR ',') FROM sys_permissions P WHERE P.lft <= Permission.lft AND P.rght >= Permission.rght AND P.deleted = 0)"
            ])
            ->join(['Permission' => ['table' => 'sys_permissions','type' => 'INNER','conditions' => 'Permission.id = Module.permission_id']])
            ->where(['Module.active' => 1, 'Module.deleted' => 0]);

        foreach($array_modules as $ent_module){
            if($this->check_permissions(explode(',',$ent_module->permissions))){
                $array_result[] = $ent_module;
            }
        }

        return $array_result;
    }

    public function get_permissions(){
        $array_permissions = [];
        $array_permissions_allow = [];
        $array_permissions_deny = [];

        $int_user_id = USER_ID; //intval($this->Session->read('_User.id'));
        $int_organization_id = ORGANIZATION_ID;
        // $int_user_id = 1;
        // echo $int_user_id;exit;

        // Grupos de Usuarios
        // $array_groups_allow = $this->UserAccessModel->find()->select(['UserAccess.model_id'])->where(['UserAccess.user_id' => $int_user_id, 'UserAccess.model' => 'Group', 'UserAccess.access_level >' => 1])->toArray();
        $array_groups_allow = $this->UserGroupModel->find()->select(['UserGroup.group_id'])->where(['UserGroup.user_id' => $int_user_id, 'UserGroup.organization_id' => $int_organization_id])->toArray();
        $array_groups_allow_ids = Hash::extract($array_groups_allow, '{n}.group_id');
        // pr($array_groups_allow_ids);exit;

        $array_groups_allow_ids[] = 2; // Anonymous

        if (!defined('GROUP_IDs')) define('GROUP_IDs', $array_groups_allow_ids); // Grupo Asignados

        // Permisos por Grupo Permitidos
        $array_permissions_group_allow = $this->UserModel->getConnection()->query("
            SELECT
            Permission.id,
            Permission.name,
            TmpPermission.access_level,
            TmpPermission.access_level+0 as level,
            TmpPermission.access_resources,
            TmpPermission.access_resources+0 as access,
            TmpPermission.grup_id,
            TmpPermission.lft
            FROM (
                SELECT
                Permission.id,
                Permission.lft,
                Permission.rght,
                `GROUP`.id as grup_id,
                GroupPermission.access_level,
                GroupPermission.access_resources
                FROM sys_permissions Permission
                JOIN sys_groups_permissions GroupPermission ON GroupPermission.permission_id = Permission.id AND GroupPermission.access_level > 1
                JOIN sys_groups `GROUP` ON GroupPermission.group_id = `GROUP`.id
                WHERE `GROUP`.active = 1 AND `GROUP`.deleted = 0 AND Permission.active = 1 AND Permission.deleted = 0 AND GroupPermission.deleted = 0 AND GroupPermission.group_id IN(".implode(',',$array_groups_allow_ids).")
                ORDER BY Permission.lft ASC
            ) as TmpPermission
            JOIN sys_permissions Permission ON TmpPermission.lft <= Permission.lft AND TmpPermission.rght >= Permission.rght AND Permission.deleted = 0 AND Permission.active = 1
            ORDER BY TmpPermission.lft ASC
        ")->fetchAll('assoc');
        // $array_permissions_group_allow = Hash::extract($array_query, '{n}.id');
        // $array_permissions_group_allow = Hash::combine($array_query, '{n}.id', '{n}.level');
        // pr($array_permissions_group_allow);exit;

        // Permisos por Grupo Denegados
        $array_permissions_group_deny = $this->UserModel->getConnection()->query("
            SELECT
            Permission.id,
            Permission.name,
            TmpPermission.access_level,
            TmpPermission.access_level+0 as level,
            TmpPermission.access_resources,
            TmpPermission.access_resources+0 as access,
            TmpPermission.grup_id,
            TmpPermission.lft
            FROM (
                SELECT
                Permission.id,
                Permission.lft,
                Permission.rght,
                `GROUP`.id as grup_id,
                GroupPermission.access_level,
                GroupPermission.access_resources
                FROM sys_permissions Permission
                JOIN sys_groups_permissions GroupPermission ON GroupPermission.permission_id = Permission.id AND GroupPermission.access_level = 1
                JOIN sys_groups `GROUP` ON GroupPermission.group_id = `GROUP`.id
                WHERE `GROUP`.active = 1 AND `GROUP`.deleted = 0 AND Permission.active = 1 AND Permission.deleted = 0 AND GroupPermission.deleted = 0 AND GroupPermission.group_id IN(".implode(',',$array_groups_allow_ids).")
                ORDER BY Permission.lft ASC
            ) as TmpPermission
            JOIN sys_permissions Permission ON TmpPermission.lft <= Permission.lft AND TmpPermission.rght >= Permission.rght AND Permission.deleted = 0 AND Permission.active = 1
            ORDER BY TmpPermission.lft ASC
        ")->fetchAll('assoc');
        // $array_permissions_group_deny = Hash::extract($array_query, '{n}.id');
        // $array_permissions_group_deny = Hash::combine($array_query, '{n}.id', '{n}.access_level');
        $array_permissions_from_groups = array_merge($array_permissions_group_allow, $array_permissions_group_deny);

        // $array_permissions_from_groups = array_diff($array_permissions_group_allow, $array_permissions_group_deny);
        // pr($array_query);
        // pr($array_permissions_group_allow);exit;
        // Permisos por Usuario Permitidos
        $array_permissions_user_allow = $this->UserModel->getConnection()->query("
            SELECT
            Permission.id,
            Permission.name,
            TmpPermission.access_level,
            TmpPermission.access_level+0 as level,
            TmpPermission.access_resources,
            TmpPermission.access_resources+0 as access,
            0 as grup_id,
            TmpPermission.lft
            FROM (
                SELECT
                Permission.id,
                Permission.lft,
                Permission.rght,
                UserPermission.access_level,
                UserPermission.access_resources
                FROM sys_permissions Permission
                JOIN sys_users_permissions UserPermission ON UserPermission.permission_id = Permission.id AND UserPermission.access_level > 1
                JOIN sys_users_admin `User` ON UserPermission.user_id = `User`.id
                WHERE `User`.active = 1 AND `User`.deleted = 0 AND Permission.active = 1 AND Permission.deleted = 0 AND UserPermission.deleted = 0 AND UserPermission.user_id = {$int_user_id}
                ORDER BY Permission.lft ASC
            ) as TmpPermission
            JOIN sys_permissions Permission ON TmpPermission.lft <= Permission.lft AND TmpPermission.rght >= Permission.rght AND Permission.deleted = 0 AND Permission.active = 1
            ORDER BY TmpPermission.lft ASC
        ")->fetchAll('assoc');
        // $array_permissions_user_allow = Hash::extract($array_query, '{n}.id');
        // pr($array_permissions_user_allow);exit;

        // Permisos por Usuario Denegados
        $array_permissions_user_deny = $this->UserModel->getConnection()->query("
            SELECT
            Permission.id,
            Permission.name,
            TmpPermission.access_level,
            TmpPermission.access_level+0 as level,
            TmpPermission.access_resources,
            TmpPermission.access_resources+0 as access,
            0 as grup_id,
            TmpPermission.lft
            FROM (
                SELECT
                Permission.id,
                Permission.lft,
                Permission.rght,
                UserPermission.access_level,
                UserPermission.access_resources
                FROM sys_permissions Permission
                JOIN sys_users_permissions UserPermission ON UserPermission.permission_id = Permission.id AND UserPermission.access_level = 1
                JOIN sys_users_admin `User` ON UserPermission.user_id = `User`.id
                WHERE `User`.active = 1 AND `User`.deleted = 0 AND Permission.active = 1 AND Permission.deleted = 0 AND UserPermission.deleted = 0 AND UserPermission.user_id = {$int_user_id}
                ORDER BY Permission.lft ASC
            ) as TmpPermission
            JOIN sys_permissions Permission ON TmpPermission.lft <= Permission.lft AND TmpPermission.rght >= Permission.rght AND Permission.deleted = 0 AND Permission.active = 1
            ORDER BY TmpPermission.lft ASC
        ")->fetchAll('assoc');
        // $array_permissions_user_deny = Hash::extract($array_query, '{n}.id');
        // pr($array_permissions_user_deny);exit;

        $array_permissions_from_user = array_merge($array_permissions_user_allow, $array_permissions_user_deny);

        $array_permissions_allow = array_merge($array_permissions_from_groups, $array_permissions_from_user);
        // pr($array_permissions_allow);exit;

        $array_permissions = Hash::combine($array_permissions_allow, '{n}.id', '{n}');

        $array_permissions = Hash::remove($array_permissions, '{n}[access_level=Deny]');
        // debug($array_permissions);exit;

        // $array_permissions_from_user = array_merge($array_permissions_user_allow, $array_permissions_user_deny);

        // $array_permissions_deny = array_merge($array_permissions_group_deny, $array_permissions_direct_deny);

        // $array_permissions = array_merge($array_permissions_rol, $array_permissions_allow);
        // $array_permissions = array_diff($array_permissions_allow, $array_permissions_user_deny);

        return $array_permissions;
    }

    private function is_direct_action(){
        $result = false;

        if(isset($this->getConfig('direct_actions')[$this->controller_name])){
            if(in_array($this->controller_action, $this->getConfig('direct_actions')[$this->controller_name])){
                $result = true;
            }
        }

        return $result;
    }

    public function get_menu(){
        $array_menu = $this->get_tree_menu(1, true);
        // debug($array_menu);exit;
        $array_permissions_ids = Hash::extract($this->array_permissions, '{n}.id');

        $get_menu = empty($array_menu)? [] : $this->get_json_menu($array_menu[0]->children, $array_permissions_ids);
        return empty($array_menu)? array() : $get_menu;
    }

    private function get_json_menu($array_menu, $array_permisos){
        $obj_menu = null;
        $str_separator = false;
        // debug($array_permisos);debug($array_menu);exit;

        $result = array();
        foreach ($array_menu as $index => $Menu){
            $str_permisos = trim($Menu->permissions);
            $array_modulo_permisos = empty($str_permisos)? array() : explode(',', $str_permisos);

            $array_intersect = array_intersect($array_permisos, $array_modulo_permisos);

            if(!empty($array_intersect) || empty($str_permisos)){
                $text = trim($Menu->name);
                if($text == '-'){
                    $str_separator = true;
                }else{
                    $array_children = $this->get_json_menu($Menu->children, $array_permisos);
                    // $handler = empty($Menu->Module['url'])? (empty($Menu->module)? '' : ", handler:function(){ App.open_module('{$Menu->module}',{}); }") : ", handler:function(){ App.open_module('{$Menu->Module['name']}',{}); }";
                    $url = trim($Menu->Module['url']);
                    $script = trim($Menu->script);
                    $level = intval($Menu->level);
                    $controller = trim($Menu->Module['controller']);
                    // $handler = empty($Menu->Module['name'])? '' : ",handler:function(){ App.open_module('{$Menu->Module['name']}',{}); }";
                    $icon = trim($Menu->icon);
                    // $icon = empty($Menu->icon)? '' : ",icon:'{$Menu->icon}'";
                    // $str_menu = "{text:'{$text}' {$handler} {$iconCls}" . (empty($Menu->children)? '}' : ", menu: { xtype:'menu', items:[".implode(',', $array_children).']}}');

                    // if($level_1 == true){
                    //     // $str_menu = "{ title: \"{$text}\", options: [" . implode(',', $array_children) . "]}";
                    // }else{
                    //     $_id = str_replace(' ', '-', $text)."-{$index}";
                    //     $str_menu = "{ title: \"{$text}\", url: prefix + \"{$url}\" {$iconCls}, id: \"{$_id}\"}";
                    //     $obj_menu = ['title' => $text, 'options' => $array_children];
                    // }

                    $obj_menu = [
                        'title' => $text,
                        'options' => $array_children,
                        'url' => $url,
                        'script' => $script,
                        'level' => $level,
                        'controller' => $controller,
                        'icon' => empty($icon)? '' : trim($icon)
                    ];

                    if(!empty($obj_menu['options']) || !empty($obj_menu['url']) || !empty($obj_menu['script']) || !empty($obj_menu['controller'])){
                        // Si hay un separador
                        if(!empty($str_separator)){
                            $result[] = '-';
                            $str_separator = false;
                        }

                        $result[] = $obj_menu;
                    }
                    // if(empty($str_permisos) && !empty($array_children)){ // Es una carpeta del menu.
                    //     $result[] = $obj_menu;
                    // }elseif(!empty($url) && empty($str_permisos)){ // Es un modulo sin permisos (libre).
                    //     $result[] = $obj_menu;
                    // }else{
                    //     $result[] = $obj_menu;
                    // }
                }
            }
        }

        return $result;
    }


    private function get_tree_menu($parent_id = 1, $only_active = false){
        $result = array();
        $array_conditions = array('Menu.id' => $parent_id, 'Menu.deleted' => 0);
        if($only_active == true) $array_conditions['Menu.active'] = 1;

        $array_query = $this->MenuModel->find()->select(['Menu.lft','Menu.rght','Menu.parent_id'])->where($array_conditions);
        if($array_query->count() > 0){
            $Node = $array_query->first();

            $lft = $Node->lft;
            $rght = $Node->rght;

            $array_conditions = array('Menu.lft >=' => $lft, 'Menu.rght <=' => $rght, 'Menu.deleted' => 0);
            if($only_active == true) $array_conditions['Menu.active'] = 1;

            $arrNodes = $this->MenuModel->find()
                ->select([
                    'Menu.id',
                    'Menu.uid',
                    'Menu.active',
                    'Menu.parent_id',
                    'Menu.icon',
                    'Menu.name',
                    'Menu.script',
                    'Module.url',
                    'Module.name',
                    'Module.controller',
                    'level' => "(SELECT COUNT(M.id) FROM sys_menus M WHERE M.lft < Menu.lft AND M.rght > Menu.rght AND M.parent_id > 0)",
                    'permissions' => "(SELECT GROUP_CONCAT(P.id ORDER BY P.id ASC SEPARATOR ',') FROM sys_permissions P WHERE P.lft <= Permission.lft AND P.rght >= Permission.rght AND P.deleted = 0)"
                ])
                ->join([
                    'Module' => [
                        'table' => 'sys_modules',
                        'type' => 'LEFT',
                        'conditions' => 'Menu.module_id = Module.id AND Module.active = 1 AND Module.deleted = 0'
                    ],
                    'Permission' => [
                        'table' => 'sys_permissions',
                        'type' => 'LEFT',
                        'conditions' => 'Module.permission_id = Permission.id AND Permission.active = 1 AND Permission.deleted = 0'
                    ]
                ])->where($array_conditions)->order('Menu.lft ASC');
            $x = 0;
            $array_nodes = $arrNodes->toArray();
            // debug($array_nodes);exit;

            $result = $this->__get_tree($array_nodes, $Node->parent_id, $x);
        }

        return $result;
    }

    private function __get_tree(&$nodes, $parent_id, &$x){
        $result = array(); $b = true;

        while($b && isset($nodes[$x])){
            $node = $nodes[$x];
            $node_patent_id = $node->parent_id;
            if($parent_id == $node_patent_id){
                $node_id = $node->id; $x++;
                $node['children'] = $this->__get_tree($nodes, $node_id, $x);
                $result[] = $node;
            }else {
                $b = false;
            }
        }
        return $result;
    }
    // $this->Session = $this->request->getSession();
    // if($this->Session->check('User') && $this->Session->read('User')){

    // }else{
    //     if (!defined('GROUP_ID')) define('GROUP_ID', 1); // Guest
    //     if (!defined('USER_ID')) define('USER_ID', 0); // Guest
    // }

    // $str_controller = $this->name;
    // $str_action = $this->request->getParam('action');

    // $this->loadModel('Admin.SysUser');
    // $array = $this->SysUser->get_permissions(USER_ID);
    // pr($array);exit;

    public function set_groups_user($user_id, $groups)
    {
        $success = true;
        $datetime = date('Y-m-d H:i:s');

        $this->UserGroupModel->query()->update()->set(['deleted' => 1])->where(['user_id' => $user_id])->execute();
        foreach ($groups as $row) {
            // debug($permission);
            $int_group_id = $this->GroupModel->uid_to_id($row['uid']);

            $this->UserGroupModel->query()
                ->insert(['user_id','group_id','organization_id','createdby','modifiedby','created','modified'])
                ->values([
                    'user_id' => $user_id,
                    'group_id' => $int_group_id,
                    'organization_id' => ORGANIZATION_ID,
                    'createdby' => USER_ID,
                    'modifiedby' => USER_ID,
                    'created' => $datetime,
                    'modified' => $datetime,
                ])
                ->epilog("ON DUPLICATE KEY UPDATE deleted=0, modified=modified, modifiedby=modifiedby")
                ->execute();
        }

        return $success;
    }

    public function save_resources($type, $model_id, $resources){
        $datetime = date('Y-m-d H:i:s');

        $success = true;

        if($type == 'Group'){
            $field = 'group_id';
            $int_user_id = 0;
            $int_group_id = $model_id;
        }elseif($type == 'User'){
            $field = 'user_id';
            $int_user_id = $model_id;
            $int_group_id = 0;
        }else{
            die('Error inesperado al guardar los Recursos.');
        }

        // debug($resources);exit;
        $this->AccessResourceModel->query()->update()->set(['deleted' => 1])->where([$field => $model_id])->execute();

        foreach ($resources as $resource) {
            $entity = $this->ResourceModel->find()
                ->select(['Resource.id','Resource.uid','Resource.name','Resource.model','Resource.table'])
                ->where(['Resource.model' => $resource['model']])
                ->first();

            if(!empty($entity)){
                $model_uid = trim($resource['uid']);

                if($model_uid === '_'){
                    // TODO; validar que pueda asignar todo los recursos.
                    $this->AccessResourceModel->query()
                        ->insert(['alias','model_id','resource_id','user_id','group_id','createdby','modifiedby','created','modified'])
                        ->values([
                            'alias' => $entity->model,
                            'model_id' => $entity->id,
                            'resource_id' => -9,
                            'user_id' => $int_user_id,
                            'group_id' => $int_group_id,
                            'createdby' => USER_ID,
                            'modifiedby' => USER_ID,
                            'created' => $datetime,
                            'modified' => $datetime,
                        ])
                        ->epilog("ON DUPLICATE KEY UPDATE deleted=0, modifiedby=".USER_ID)
                        ->execute();
                }else{
                    $array_query = $this->ResourceModel->getConnection()->query("SELECT `Table`.id FROM {$entity->table} `Table` WHERE `Table`.uid = '{$model_uid}' AND `Table`.deleted = 0")->fetchAll('assoc');
                    if(!empty($array_query)){
                        $int_modelo_id = intval($array_query[0]['id']);

                        $this->AccessResourceModel->query()
                            ->insert(['alias','model_id','resource_id','user_id','group_id','createdby','modifiedby','created','modified'])
                            ->values([
                                'alias' => $entity->model,
                                'model_id' => $entity->id,
                                'resource_id' => $int_modelo_id,
                                'user_id' => $int_user_id,
                                'group_id' => $int_group_id,
                                'createdby' => USER_ID,
                                'modifiedby' => USER_ID,
                                'created' => $datetime,
                                'modified' => $datetime,
                            ])
                            ->epilog("ON DUPLICATE KEY UPDATE deleted=0, modifiedby=".USER_ID)
                            ->execute();
                    }
                }
            }
        }

        return $success;
    }

    public function save_permissions($type, $model_id, $permissions)
    {
        // debug($permissions);echo $int_group_id;exit;
        $datetime = date('Y-m-d H:i:s');
        $success = true;

        if($type == 'Group'){
            $field = 'group_id';
            $int_user_id = 0;
            $int_group_id = $model_id;

            $Model = $this->GroupPermissionModel;
        }elseif($type == 'User'){
            $field = 'user_id';
            $int_user_id = $model_id;
            $int_group_id = 0;

            $Model = $this->UserPermissionModel;
        }else{
            die('Error inesperado al guardar los Recursos.');
        }

        $Model->query()->update()->set(['deleted' => 1])->where([$field => $model_id])->execute();
        foreach ($permissions as $permission) {
            // debug($permission);
            $access_resources = trim($permission['access_resources']);
            $access_level = trim($permission['access_level']);
            $int_permission_id = $this->PermissionModel->uid_to_id($permission['uid']);

            $Model->query()
                ->insert([$field,'permission_id','access_level','access_resources','organization_id','createdby','modifiedby','created','modified'])
                ->values([
                    $field => $model_id,
                    'permission_id' => $int_permission_id,
                    'access_level' => $access_level,
                    'access_resources' => $access_resources,
                    'organization_id' => ORGANIZATION_ID,
                    'createdby' => USER_ID,
                    'modifiedby' => USER_ID,
                    'created' => $datetime,
                    'modified' => $datetime,
                ])
                ->epilog("ON DUPLICATE KEY UPDATE deleted=0, access_level='{$access_level}', access_resources='{$access_resources}', modified='{$datetime}', modifiedby=".USER_ID)
                ->execute();
        }

        return $success;
    }

    public function restart_db()
    {
        $file_path = CONFIG . 'schema' . DS . '_.sql';

        if(file_exists($file_path)){
            $cn = ConnectionManager::getConfig('default');
            $conn = new mysqli($cn['host'], $cn['username'], $cn['password'], $cn['database']);
            if ($conn->connect_error) {
                return false; //die("Connection failed: " . $conn->connect_error);
            }

            $file = new File( $file_path, true, 0755);
            $file->open('r',false);
            $sql = $file->read();
            return $conn->multi_query($sql);
        }

        return false;
    }

    public function generate_password(){
        return bin2hex(openssl_random_pseudo_bytes(3));
    }
}