<?php
declare(strict_types=1);

namespace Admin\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\View\Exception\MissingTemplateException;

class MenuController extends AppController
{
    public function initialize() : void{
        parent::initialize();

        $this->loadModel('Admin.SysMenu');
    }

    public function load()
    {
        $int_id = $this->force_get_id($this->SysMenu, 'uid');

        $where = ['SysMenu.id' => $int_id];

        $ent_reg = $this->SysMenu->find()
            ->select([
                'SysMenu.uid','SysMenu.name','SysMenu.active','SysMenu.script','SysMenu.description',
                'SysModulo.uid','SysParentMenu.uid'
            ])
            ->join([
                'SysModulo' => ['table' => 'sys_modules','type' => 'LEFT','conditions' => 'SysModulo.id = SysMenu.module_id'],
                'SysParentMenu' => ['table' => 'sys_menus','type' => 'LEFT','conditions' => 'SysParentMenu.id = SysMenu.parent_id'],
            ])
            ->where($where)->first();

        $array_data = array(
            'uid' => trim($ent_reg->uid),
            'name' => trim($ent_reg->name),
            'active' => $ent_reg->active? true : false,
            'description' => trim($ent_reg->description),
            'script' => trim($ent_reg->script),
            'module_uid' =>  dtrim($ent_reg->SysModulo['uid']),
            'parent_uid' =>  dtrim($ent_reg->SysParentMenu['uid']),
        );

        $this->Response->success();
        $this->Response->set('data', $array_data);
    }

    public function save(){
        $this->loadModel('Admin.SysModulo');

        $int_modelo_id = $this->force_if_get_id($this->SysMenu, 'uid');

        $array_save = array(
            'name' => get('name',''),
            'description' => get('description',''),
            'script' => get('script',''),
            // 'icon' => get('icon',''),
            'active' => get('active','off') == 'on'? 1 : 0,
            'module_id' => $this->force_if_get_id($this->SysModulo, 'module_uid'),
        );

        if($int_modelo_id == 0){
            $array_save['parent_id'] = $this->force_get_id($this->SysMenu, 'parent_uid');
        }else{
            $array_save['id'] = $int_modelo_id;
        }

        $entity = $this->SysMenu->newEntity($array_save);

        if(!$entity->hasErrors()){
            if($this->SysMenu->save($entity)){
                $this->Response->success();
                $this->Response->set('uid', $entity->uid);
            }
        }else{
            $this->Response->add_errors($entity->getErrors());
        }
    }

    public function move(){
        $position = get('drop_position', '');
        $int_node_id = $this->force_get_id($this->SysMenu, 'uid');
        $int_parent_id = $this->force_get_id($this->SysMenu, 'parent_uid');

        if($int_parent_id == 0){
            $this->Response->add_errors('No puede asignar un registro como principal.');
            return;
        }

		if($position == 'append'){
            $int_index = $this->SysMenu->find()->where(['SysMenu.parent_id' => $int_parent_id])->count()+1;
        }elseif($position == 'before'){
            $node = $this->SysMenu->find()->select(['SysMenu.parent_id','SysMenu.order'])->where(['SysMenu.id' => $int_node_id])->first();
            $parent = $this->SysMenu->find()->select(['SysMenu.parent_id','SysMenu.order'])->where(['SysMenu.id' => $int_parent_id])->first();
            if($node->parent_id == $parent->parent_id){
                $int_index = $parent->order;
                $int_parent_id = $node->parent_id;
            }else{
                $int_index = $parent->order;
                $int_parent_id = $parent->parent_id;
            }
        }elseif($position == 'after'){
            // $int_index = $this->SysMenu->field('order',['SysMenu.id' => $int_parent_id])+1;
            $node = $this->SysMenu->find()->select(['SysMenu.parent_id','SysMenu.order'])->where(['SysMenu.id' => $int_node_id])->first();
            $parent = $this->SysMenu->find()->select(['SysMenu.parent_id','SysMenu.order'])->where(['SysMenu.id' => $int_parent_id])->first();
            if($node->parent_id == $parent->parent_id){
                $int_index = $parent->order+1;
                $int_parent_id = $node->parent_id;
            }else{
                $int_index = $parent->order+1;
                $int_parent_id = $parent->parent_id;
            }
        }

        if($this->SysMenu->move($int_node_id, $int_parent_id, $int_index)){
            $this->Response->success();
        }else{
            $this->Response->add_errors('Error inesperado.');
        }
    }

    public function delete(){
        $int_modelo_id = $this->force_if_get_id($this->SysMenu, 'uid');

        $module = $this->SysMenu->get($int_modelo_id);
        $module->deleted = 1;

        $this->SysMenu->save($module);
        if(!$module->hasErrors()){
            $this->Response->success();
        }else{
            $this->Response->add_errors('Error al borrar el registro.');
        }
    }

    public function tree_grid()
    {
        $checkbox = get('checkbox', false);
        // $this->Response->success();
        // $this->Response->set('data',$this->Access->get_menu());
        $ent_rows = $this->SysMenu->find()
            ->select(['SysMenu.id','SysMenu.uid','SysMenu.parent_id','SysMenu.name','SysMenu.script','SysMenu.description','SysMenu.active','SysModule.name','SysParentMenu.uid'])
            ->join([
                'SysModule' => ['table' => 'sys_modules','type' => 'LEFT','conditions' => 'SysModule.id = SysMenu.module_id'],
                'SysParentMenu' => ['table' => 'sys_menus','type' => 'LEFT','conditions' => 'SysParentMenu.id = SysMenu.parent_id'],
            ])
            ->where(['SysMenu.deleted' => 0])
            ->order(['SysMenu.lft ASC'])
            ->toArray();

        $array_tree = $this->SysMenu->generate_tree($ent_rows, function($row, $is_children, $level) use($checkbox){
            $item = [
                // 'node_id' => $row->id,
                'uid' => $row->uid,
                'parent_uid' => dtrim($row->SysParentMenu['uid']),
                'text' => $row->name,
                'description' => $row->description,
                'script' => $row->script,
                'active' => $row->active,
                'module' => empty($row->SysModule['name'])? '' : trim($row->SysModule['name']),
                'expanded' => true,
                'root' => false,
                'leaf' => empty($row->SysModule['name'])? false : $is_children
            ];

            if($checkbox == true){
                $item['checked'] = true;
            }

            return $item;
        }, 0);

        $this->Response->success();

        // $array_tree[0]['root'] = true;
        $this->Response->set('_', $array_tree);
    }
}