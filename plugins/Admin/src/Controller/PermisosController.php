<?php
declare(strict_types=1);

namespace Admin\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\View\Exception\MissingTemplateException;

class PermisosController extends AppController
{
    public function initialize() : void{
        parent::initialize();

        $this->loadModel('Admin.SysPermiso');
    }

    public function load()
    {
        $int_id = $this->force_get_id($this->SysPermiso, 'uid');

        $where = ['SysPermiso.id' => $int_id];

        $ent_reg = $this->SysPermiso->find()
            ->select([
                'SysPermiso.uid','SysPermiso.name','SysPermiso.active','SysPermiso.description'//,'SysPermisoParent.uid'
            ])
            /*->join([
                'SysPermisoParent' => ['table' => 'sys_conceptos','type' => 'INNER','conditions' => 'SysPermisoParent.id = SysPermiso.parent_id'],
            ])*/
            ->where($where)->first();

        $array_data = array(
            'uid' => trim($ent_reg->uid),
            'name' => trim($ent_reg->name),
            'permission_uid' => trim($ent_reg->name),
            'active' => $ent_reg->active? true : false,
            'description' => trim($ent_reg->description),
            // 'SysPermiso_ParentId' => trim($ent_reg->SysPermisoParent['uid']),
        );

        $this->Response->success();
        $this->Response->set('data', $array_data);
    }

    public function save(){
        $int_modelo_id = $this->force_if_get_id($this->SysPermiso, 'uid');

        $array_save = array(
            'name' => get('name',''),
            'description' => get('description',''),
            // 'icon' => get('icon',''),
            'active' => get('active','off') == 'on'? 1 : 0,
            // 'parent_id' => $this->force_if_get_id($this->SysPermiso, 'parent_uid'),
        );

        if($int_modelo_id == 0){
            $array_save['parent_id'] = $this->force_get_id($this->SysPermiso, 'parent_uid');
        }else{
            $array_save['id'] = $int_modelo_id;
        }

        $entity = $this->SysPermiso->newEntity($array_save);
        if(!$entity->hasErrors()){
            if($this->SysPermiso->save($entity)){
                $this->Response->success();
                $this->Response->set('uid', $entity->uid);
            }
        }else{
            $this->Response->add_errors($entity->getErrors());
        }
    }

    public function tree_grid()
    {
        $checkbox = get('checkbox', false);

        $show_id = get('show_id', 'false') == 'true';
        // $this->Response->success();
        // $this->Response->set('data',$this->Access->get_menu());
        $ent_rows = $this->SysPermiso->find()
            ->select(['SysPermiso.id','SysPermiso.uid','SysPermiso.parent_id','SysPermiso.name','SysPermiso.description','SysPermiso.active','SysParentPermiso.uid'])
            ->join([
                // 'SysModule' => ['table' => 'sys_modules','type' => 'LEFT','conditions' => 'SysModule.id = SysPermiso.module_id'],
                'SysParentPermiso' => ['table' => 'sys_menus','type' => 'LEFT','conditions' => 'SysParentPermiso.id = SysPermiso.parent_id'],
            ])
            ->where(['SysPermiso.deleted' => 0])
            ->order(['SysPermiso.lft ASC'])
            ->toArray();

        $array_tree = $this->SysPermiso->generate_tree($ent_rows, function($row, $is_children, $level) use($checkbox, $show_id){
            $item = [
                // 'node_id' => $row->id,
                'uid' => $row->uid,
                'parent_uid' => dtrim($row->SysParentPermiso['uid']),
                'text' => $show_id? "{$row->id} - {$row->name}" : $row->name,
                'description' => $row->description,
                'active' => $row->active,
                // 'module' => empty($row->SysModule['name'])? '' : trim($row->SysModule['name']),
                'expanded' => true,
                'status' => 'uncheck', // check, uncheck, default
                'root' => false,
                'leaf' => $is_children
            ];

            if($checkbox == true){
                $item['checked'] = false;
            }

            return $item;
        },0);

        $this->Response->success();

        $array_tree[0]['root'] = true;
        $this->Response->set('_', $array_tree);
    }

    public function combobox()
    {
        // $this->Response->success();
        // $this->Response->set('data',$this->Access->get_menu());
        $rows = $this->SysPermiso->find()
            ->select(['SysPermiso.uid','SysPermiso.name'])
            // ->join(['SysModule' => ['table' => 'sys_modules','type' => 'LEFT','conditions' => 'SysModule.id = SysPermiso.module_id']])
            ->where(['SysPermiso.deleted' => 0])
            ->order(['SysPermiso.lft ASC']);

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

    public function delete(){
        $int_modelo_id = $this->force_if_get_id($this->SysPermiso, 'uid');

        $module = $this->SysPermiso->get($int_modelo_id);
        $module->deleted = 1;

        $this->SysPermiso->save($module);
        if(!$module->hasErrors()){
            $this->Response->success();
        }else{
            $this->Response->add_errors('Error al borrar el registro.');
        }
    }

    public function move(){
        $position = get('drop_position', '');
        $int_node_id = $this->force_get_id($this->SysPermiso, 'uid');
        $int_parent_id = $this->force_get_id($this->SysPermiso, 'parent_uid');

        if($int_parent_id == 0){
            $this->Response->add_errors('No puede asignar un registro como principal.');
            return;
        }

		if($position == 'append'){
            $int_index = $this->SysPermiso->find()->where(['SysPermiso.parent_id' => $int_parent_id])->count()+1;
        }elseif($position == 'before'){
            $node = $this->SysPermiso->find()->select(['SysPermiso.parent_id','SysPermiso.order'])->where(['SysPermiso.id' => $int_node_id])->first();
            $parent = $this->SysPermiso->find()->select(['SysPermiso.parent_id','SysPermiso.order'])->where(['SysPermiso.id' => $int_parent_id])->first();
            if($node->parent_id == $parent->parent_id){
                $int_index = $parent->order;
                $int_parent_id = $node->parent_id;
            }else{
                $int_index = $parent->order;
                $int_parent_id = $parent->parent_id;
            }
        }elseif($position == 'after'){
            // $int_index = $this->SysPermiso->field('order',['SysPermiso.id' => $int_parent_id])+1;
            $node = $this->SysPermiso->find()->select(['SysPermiso.parent_id','SysPermiso.order'])->where(['SysPermiso.id' => $int_node_id])->first();
            $parent = $this->SysPermiso->find()->select(['SysPermiso.parent_id','SysPermiso.order'])->where(['SysPermiso.id' => $int_parent_id])->first();
            if($node->parent_id == $parent->parent_id){
                $int_index = $parent->order+1;
                $int_parent_id = $node->parent_id;
            }else{
                $int_index = $parent->order+1;
                $int_parent_id = $parent->parent_id;
            }
        }

        if($this->SysPermiso->move($int_node_id, $int_parent_id, $int_index)){
            $this->Response->success();
        }else{
            $this->Response->add_errors('Error inesperado.');
        }
    }
}