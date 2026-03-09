<?php
declare(strict_types=1);

namespace Admin\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\View\Exception\MissingTemplateException;

class DebugController extends AppController
{
    public function initialize() : void{
        parent::initialize();

        $this->loadModel('Admin.SysUsers');
    }

    public function grid()
    {
       

        $page = intval(get('page', 1));
        $limit = get('limit', 50);


        $this->loadModel('Admin.ApiDebug');
        $_where = [];
        $str_action = get('type','');
        if (!empty($str_action)) {
            $_where['ApiDebug.action'] = $str_action;
        }
        //filter: [{"property":"query","value":"jimmy"}]
        if (get('filter','')) {
            $arr_filter = json_decode(get('filter'),true);
            if ($arr_filter[0]['property'] == "query") {

                $search = $arr_filter[0]['value'];
                $_where['OR'] = [['ApiDebug.action LIKE' => "%$search%"], ['ApiDebug.ip LIKE' => "%$search%"], ['User.id' => "$search"], ['User.email LIKE' => "%$search%"]];

            }
        }

        
        $result = array();
        $count = 0;

        $_fields = ['ApiDebug.id','ApiDebug.created','ApiDebug.action','ApiDebug.post','ApiDebug.agent','ApiDebug.get','ApiDebug.result','ApiDebug.ip','KKey.type','User.email'];

        $ent_debug = $this->ApiDebug->find()->select($_fields)
        ->join([
             'KKey' => ['table' => 'api_keys', 'type' => 'INNER', 'conditions' => 'KKey.id = ApiDebug.key_id'],
             'Token' => ['table' => 'app_tokens', 'type' => 'LEFT', 'conditions' => 'Token.token = ApiDebug.token'],
             'User' => ['table' => 'sys_users', 'type' => 'LEFT', 'conditions' => 'User.id = Token.user_id'],
        ])
        ->where($_where)->order(['ApiDebug.id' => 'DESC'])->limit($limit)->page($page)->all();
        $count = $this->ApiDebug->find()
        ->join([
             'KKey' => ['table' => 'api_keys', 'type' => 'INNER', 'conditions' => 'KKey.id = ApiDebug.key_id'],
             'Token' => ['table' => 'app_tokens', 'type' => 'LEFT', 'conditions' => 'Token.token = ApiDebug.token'],
             'User' => ['table' => 'sys_users', 'type' => 'LEFT', 'conditions' => 'User.id = Token.user_id'],
        ])->where($_where)->count();

      


        if (!empty($ent_debug)) {
            foreach($ent_debug as $row) {
                $result[] = array(
                    'id' => $row->id,
                    'action' => $row->action,
                    'post' => $row->action != 'login' ? $row->post : 'private',
                    'get' => $row->get,
                    'result' => $row->result,
                    'agent' => $row->agent,
                    'ip' => $row->ip,
                    'source' => $row['KKey']['type'],
                    'user' => $row['User']['email'],
                    'created' => $row->created->i18nFormat('yyyy-MM-dd HH:mm:ss'),
                );

            }
           
        }

         $this->Response->success();
            $this->Response->set('data', $result);
            $this->Response->set('total', $count);
             
    }

     public function grid_bugs()
    {
       

        $page = intval(get('page', 1));
        $limit = get('limit', 50);


        $this->loadModel('Admin.AppBug');
        $_where = [];
        $str_action = get('type','');
        if (!empty($str_action)) {
            $_where['AppBug.action'] = $str_action;
        }
        //filter: [{"property":"query","value":"jimmy"}]
        if (get('filter','')) {
            $arr_filter = json_decode(get('filter'),true);
            if ($arr_filter[0]['property'] == "query") {
                $search = $arr_filter[0]['value'];
                $_where['OR'] = [['AppBug.description LIKE' => "%$search%"], ['AppBug.title LIKE' => "%$search%"]];

            }
        }

        
        $result = array();
        $count = 0;

        
        $ent_debug = $this->AppBug->find()
        ->where($_where)->order(['AppBug.id' => 'DESC'])->limit($limit)->page($page)->all();
        $count = $this->AppBug->find()->where($_where)->count();

      

         $this->Response->success();
            $this->Response->set('data', $ent_debug);
            $this->Response->set('total', $count);
             
    }

}