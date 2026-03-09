<?php
declare(strict_types=1);

namespace Admin\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\View\Exception\MissingTemplateException;

class ModulosController extends AppController
{
    public function initialize() : void{
        parent::initialize();

        $this->loadModel('Admin.SysModulo');
    }

    public function load()
    {
        $int_id = $this->force_get_id($this->SysModulo, 'uid');

        $where = ['SysModulo.id' => $int_id];

        $ent_reg = $this->SysModulo->find()
            ->select([
                'SysModulo.uid','SysModulo.name','SysModulo.description','SysModulo.file','SysModulo.active','SysModulo.controller',
                'SysPermiso.uid'
            ])
            ->join([
                'SysPermiso' => ['table' => 'sys_permissions','type' => 'LEFT','conditions' => 'SysPermiso.id = SysModulo.permission_id'],
            ])
            ->where($where)->first();

        $array_data = array(
            'uid' => trim($ent_reg->uid),
            'name' => trim($ent_reg->name),
            'file' => trim($ent_reg->file),
            'description' => trim($ent_reg->description),
            'controller' => trim($ent_reg->controller),
            'active' => $ent_reg->active? true : false,
            'permission_uid' => dtrim($ent_reg->SysPermiso['uid']),
        );

        $this->Response->success();
        $this->Response->set('data', $array_data);
    }

    public function save(){
        $this->loadModel('Admin.SysPermiso');

        $int_modelo_id = $this->force_if_get_id($this->SysModulo, 'uid');

        $array_save = array(
            'name' => get('name',''),
            'file' => get('file',''),
            'description' => get('description',''),
            'controller' => get('controller',''),
            'active' => get('active','off') == 'on'? 1 : 0,
            'permission_id' => $this->force_if_get_id($this->SysPermiso, 'permission_uid'),
        );

        if($int_modelo_id == 0){
            // $array_save['parent_id'] = $this->force_get_id($this->SysModulo, 'parent_uid');
        }else{
            $array_save['id'] = $int_modelo_id;
        }

        $entity = $this->SysModulo->newEntity($array_save);
        if(!$entity->hasErrors()){
            if($this->SysModulo->save($entity)){
                $this->Response->success();
                $this->Response->set('uid', $entity->uid);
            }
        }else{
            $this->Response->add_errors($entity->getErrors());
        }
    }

    public function grid(){
        $int_start = get('start', 0);
        $int_limit = get('limit', 1000);

        $where = array(
            'SysModulo.deleted' => 0,
        );

        $total = $this->SysModulo->find()->where($where)->count();

        $arr_regs = $this->SysModulo->find()
            ->select([
                'SysModulo.uid',
                'SysModulo.name',
                'SysModulo.description',
                'SysModulo.active',
                'SysPermiso.name'
            ])
            ->join([
                'SysPermiso' => [
                    'table' => 'sys_permissions',
                    'type'  => 'LEFT',
                    'conditions' => [
                        'SysPermiso.id = SysModulo.permission_id'
                    ]
                ]
            ])
            ->where($where)
            ->order([
                'SysModulo.name' => 'ASC'
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
                'description' => $reg->description,
                'permissions' => $reg->SysPermiso['name'],
                'active' => $reg->active,
            );
        }

        $this->Response->set('total', $total);
        $this->Response->set('data', $arr_data);
        $this->Response->success(true);
    }

    public function delete(){
        $int_modelo_id = $this->force_if_get_id($this->SysModulo, 'uid');

        $module = $this->SysModulo->get($int_modelo_id);
        $module->deleted = 1;

        $this->SysModulo->save($module);
        if(!$module->hasErrors()){
            $this->Response->success();
        }else{
            $this->Response->add_errors('Error al borrar el registro.');
        }
    }

    public function combobox()
    {
        $rows = $this->SysModulo->find()
            ->select([
                'SysModulo.uid','SysModulo.name'
            ])
            ->where(['SysModulo.deleted' => 0])
            ->order(['SysModulo.name ASC']);

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

    public function modules()
    {
        // ob_start();
        $this->set('modules', $this->Access->get_modules());
        // $this->set('modules', ob_get_clean());
// debug($this->viewBuilder());exit;
        $this->viewBuilder()->setTemplate('/Main/modules');
    }
}