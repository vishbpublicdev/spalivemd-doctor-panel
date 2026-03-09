<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;

use Admin\Controller\AppController;

// use Cake\Core\Configure;
// use Cake\Http\Exception\ForbiddenException;
// use Cake\Http\Exception\NotFoundException;
// use Cake\Http\Response;
// use Cake\View\Exception\MissingTemplateException;

class MainController extends AppController
{
    public function index()
    {
        $this->set('allow_restart', Configure::read('App.allow_restart'));
        $this->set('menu', $this->Access->get_menu());
        // $this->loadModel('Admin.SysMenu');$this->SysMenu->recover_tree();exit;
        if(SESSION === true && $this->Access->Session('_Session.change_password') === true){
            $this->redirect('/c');
        }
    }

    public function login()
    {
        if($this->Access->hasSession()){
            $this->redirect('/');
        }else{
            $this->viewBuilder()->setLayout('login');

            $this->set('input', env('LOGIN_VALUE', ''));
            $this->set('input_p', env('LOGIN_PASS', ''));
        }
    }

    public function pdfLogin(){
        $val = get('file_uid', '');
        $this->viewBuilder()->setLayout('login');
        $this->set('input', env('LOGIN_VALUE', ''));
        $this->set('input_p', $val);
    }

    public function changePassword()
    {
        if(SESSION === true && $this->Access->Session('_Session.change_password') === false){
            $this->redirect('/');
        }

        $this->viewBuilder()->setLayout('login');

        $this->set('input', env('LOGIN_VALUE', ''));
    }
/*
    private function get_menu($arrMenu, $array_permisos, $level_1){
        $str_separator = '';
        // print_r($array_permisos);
        // print_r($arrMenu);
        // exit;

        $result = array();
        foreach ($arrMenu as $index => $Menu){
            $str_permisos = trim($Menu->Module['permisos']);
            $array_modulo_permisos = empty($str_permisos)? array() : explode(',', $str_permisos);

            $array_intersect = array_intersect($array_permisos, $array_modulo_permisos);

            if(!empty($array_intersect) || MASTER || empty($str_permisos)){
                $text = $Menu->nombre;
                if($text == '-'){
                    $str_separator = "'-'";
                }else{
                    $array_children = $this->get_menu($Menu->children, $array_permisos, false);

                    // $handler = empty($Menu->Module['url'])? (empty($Menu->module)? '' : ", handler:function(){ App.open_module('{$Menu->module}',{}); }") : ", handler:function(){ App.open_module('{$Menu->Module['name']}',{}); }";
                    $module_url = trim($Menu->Module['url']);
                    // $handler = empty($Menu->Module['name'])? '' : ",handler:function(){ App.open_module('{$Menu->Module['name']}',{}); }";
                    $iconCls = empty($Menu->icono)? '' : ",icon:'{$Menu->icono}'";
                    // $str_menu = "{text:'{$text}' {$handler} {$iconCls}" . (empty($Menu->children)? '}' : ", menu: { xtype:'menu', items:[".implode(',', $array_children).']}}');

                    if($level_1 == true){
                        $str_menu = "{ title: \"{$text}\", options: [" . implode(',', $array_children) . "]}";
                    }else{
                        $_id = str_replace(' ', '-', $text)."-{$index}";
                        $str_menu = "{ title: \"{$text}\", url: prefix + \"{$module_url}\" {$iconCls}, id: \"{$_id}\"}";
                    }

                    if(empty($module_url) && empty($str_permisos)){ // Es una carpeta del menu.
                        if(!empty($array_children)){
                            if(!empty($str_separator)){ $result[] = $str_separator; $str_separator = ''; }
                            $result[] = $str_menu;
                        }
                    }elseif(!empty($module_url) && empty($str_permisos)){ // Es un modulo sin permisos (libre).
                        if(!empty($str_separator)){ $result[] = $str_separator; $str_separator = ''; }
                        $result[] = $str_menu;
                    }else{
                        if(!empty($str_separator)){ $result[] = $str_separator; $str_separator = ''; }
                        $result[] = $str_menu;
                    }
                }
            }
        }
        return $result;
    }
*/
}