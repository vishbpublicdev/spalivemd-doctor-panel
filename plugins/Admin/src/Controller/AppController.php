<?php
declare(strict_types=1);

namespace Admin\Controller;

use Cake\Event\EventInterface;
use Cake\Controller\Controller;

class AppController extends Controller
{
    public function initialize(): void
    {
        parent::initialize();

        // $this->loadComponent('RequestHandler');
        // $this->loadComponent('Flash');

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/4/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');

        $this->loadComponent('Admin.Response');
        $this->loadComponent('Admin.Access', [
            'direct_actions' => [
                'Usuarios' => ['login','logout','loginu'],
                'Main' => ['login'],
            ]
        ]);

        $this->loadComponent('RequestHandler', [
            // 'enableBeforeRedirect' => false,
        ]);

        // $this->Session = $this->getRequest()->getSession();
    }

    public function beforeFilter(EventInterface $event)
    {
        $this->viewBuilder()->setLayout(null);

        $this->Access->check_access_to_action();

        parent::beforeFilter($event);
    }

    public function force_if_get_id($Model, $model_uid, $error = 'El registro no existe.')
    {
        $int_model_id = 0;

        $str_model_uid = get($model_uid, '');
		// echo "*{$str_model_uid}*";exit;

        if(!empty($str_model_uid)){
            $int_model_id = $this->force_get_id($Model, $model_uid, $error);
        }

        return $int_model_id;
    }

    public function force_get_id($Model, $model_uid, $error = 'El registro no existe.')
    {
        $str_model_uid = get($model_uid, '');
		// echo "*{$model_uid}*";exit;
        $int_model_id = $Model->uid_to_id($str_model_uid);

        if($int_model_id == 0){
            $this->Response->message($error);

            // $this->viewVars['success'] = $this->success;
            // $this->viewVars['message'] = $this->array_message;
            header("Content-type:application/json");
            echo json_encode([
                'success' => false,
                'message' => $error,
            ]);
            // echo json_encode($this->viewVars);
            exit;
        }

        return $int_model_id;
    }

    public function _user_resources($rows){
        return $rows;
        $result = [];

        foreach($rows as $row){
            $result[] = [
                'uid' => $row['uid'],
                'title' => $row['name'],
                'subtitle' => "{$row->SysUserPermission['access_level']} - {$row->SysUserPermission['access_resources']}",
                'detail' => $row['description'],
                'created' => $row->SysUserPermission['created'],
                'access_level' => $row->SysUserPermission['access_level'],
                'access_resources' => $row->SysUserPermission['access_resources'],
            ];
        }

        return $result;
    }

    public function _group_resources($rows){
        return $rows;
        $result = [];

        foreach($rows as $row){
            $result[] = [
                'uid' => $row['uid'],
                'title' => $row['name'],
                'subtitle' => "{$row->SysGroupPermission['access_level']} - {$row->SysGroupPermission['access_resources']}",
                'detail' => $row['description'],
                'created' => $row->SysGroupPermission['created'],
                'access_level' => $row->SysGroupPermission['access_level'],
                'access_resources' => $row->SysGroupPermission['access_resources'],
            ];
        }

        return $result;
    }

    public function _user_permissions($rows){
        $result = [];

        foreach($rows as $row){
            $result[] = [
                'uid' => $row['uid'],
                'title' => $row['name'],
                'subtitle' => "{$row->SysUserPermission['access_level']} - {$row->SysUserPermission['access_resources']}",
                'detail' => $row['description'],
                'created' => $row->SysUserPermission['created'],
                'access_level' => $row->SysUserPermission['access_level'],
                'access_resources' => $row->SysUserPermission['access_resources'],
            ];
        }

        return $result;
    }

    public function _group_permissions($rows){
        $result = [];

        foreach($rows as $row){
            $result[] = [
                'uid' => $row['uid'],
                'title' => $row['name'],
                'subtitle' => "{$row->SysGroupPermission['access_level']} - {$row->SysGroupPermission['access_resources']}",
                'detail' => $row['description'],
                'created' => $row->SysGroupPermission['created'],
                'access_level' => $row->SysGroupPermission['access_level'],
                'access_resources' => $row->SysGroupPermission['access_resources'],
            ];
        }

        return $result;
    }

    public function _filters($Model, $fields, &$array_conditions){
        $result = [];

        $str_filter = get('filter', false);
        if($str_filter != false){
            $array_filter = json_decode($str_filter, true);

            $property = $array_filter[0]['property'];
            $value = $array_filter[0]['value'];

            $conditions = [];
            foreach ($fields as $field) {
                $conditions["{$Model}.$field LIKE"] = "%$value%";
            }
            $result['OR'] = $conditions;
        }

        $array_conditions = array_merge($array_conditions, $result);

        return $result;
    }
}
