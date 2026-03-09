<?php
declare(strict_types=1);

namespace Admin\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\View\Exception\MissingTemplateException;

class RolesController extends AppController
{
    public function initialize() : void{
        parent::initialize();

        $this->loadModel('Admin.SysRol');
    }

    public function load()
    {
        $this->loadModel('Admin.SysPermiso');
        $this->loadModel('Admin.SysModel');

        $int_id = $this->force_get_id($this->SysRol, 'uid');

        $where = ['SysRol.id' => $int_id];

        $ent_reg = $this->SysRol->find()
            ->select([
                'SysRol.uid','SysRol.name','SysRol.description','SysRol.active',
            ])
            ->where($where)->first();

        $array_data = array(
            'uid' => trim($ent_reg->uid),
            'name' => trim($ent_reg->name),
            'description' => trim($ent_reg->description),
            'permissions' => $this->_group_permissions($this->SysPermiso->get_group_permissions($int_id)),
            'resources' => $this->_group_resources($this->SysModel->get_group_resources($int_id)),
            'active' => $ent_reg->active? true : false,
        );

        $this->Response->success();
        $this->Response->set('data', $array_data);
    }

    public function save(){
        $this->loadModel('Admin.SysPermiso');

        $int_modelo_id = $this->force_if_get_id($this->SysRol, 'uid');

        $array_save = array(
            'name' => get('name',''),
            'description' => get('description',''),
            'active' => get('active','off') == 'on'? 1 : 0,
        );

        $resources = json_decode(get('resources','[]'), true);
        $permissions = json_decode(get('permissions','[]'), true);

        if($int_modelo_id > 0){
            $array_save['id'] = $int_modelo_id;
        }

        $entity = $this->SysRol->newEntity($array_save);
        if(!$entity->hasErrors()){
            if($this->SysRol->save($entity)){
                $this->Response->success();
                $this->Response->set('uid', $entity->uid);

                $this->Access->save_resources('Group', $entity->id, $resources);
                $this->Access->save_permissions('Group', $entity->id, $permissions);
            }
        }else{
            $this->Response->add_errors($entity->getErrors());
        }
    }

    public function grid(){
        $this->loadModel('Admin.SysModel');
        $this->loadModel('Admin.SysPermiso');

        // $int_start = get('start', 0);
        // $int_limit = get('limit', 1000);
        $where = array(
            'SysRol.deleted' => 0,
            'SysRol.organization_id' => ORGANIZATION_ID
        );

        $total = $this->SysRol->find()->where($where)->count();

        $arr_regs = $this->SysRol->find()
            ->select([
                'SysRol.id',
                'SysRol.uid',
                'SysRol.name',
                'SysRol.description',
                'SysRol.active',
            ])
            ->where($where)
            ->order([
                'SysRol.name' => 'ASC'
            ]);
            // ->offset($int_start)
            // ->limit($int_limit);
        $arr_data = array();
        foreach($arr_regs as $reg){
            $arr_data[] = array(
                'uid' => $reg->uid,
                'title' => $reg->name,
                'detail' => $reg->description,
                'active' => $reg->active,
                'permissions' => $this->_group_permissions($this->SysPermiso->get_group_permissions($reg->id)),
                'resources' => $this->_group_resources($this->SysModel->get_group_resources($reg->id)),
            );
        }

        $this->Response->set('total', $total);
        $this->Response->set('data', $arr_data);
        $this->Response->success(true);
    }

    public function delete(){
        $int_modelo_id = $this->force_if_get_id($this->SysRol, 'uid');

        $module = $this->SysRol->get($int_modelo_id);
        $module->deleted = 1;

        $this->SysRol->save($module);
        if(!$module->hasErrors()){
            $this->Response->success();
        }else{
            $this->Response->add_errors('Error al borrar el registro.');
        }
    }

    public function combobox()
    {
        $rows = $this->SysRol->find()
            ->select([
                'SysRol.uid','SysRol.name'
            ])
            ->where(['SysRol.deleted' => 0])
            ->order(['SysRol.name ASC']);

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
}