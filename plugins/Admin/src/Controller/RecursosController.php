<?php
declare(strict_types=1);

namespace Admin\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\View\Exception\MissingTemplateException;

class RecursosController extends AppController
{
    public function initialize() : void{
        parent::initialize();

        $this->loadModel('Admin.SysModel');
    }

    public function grid()
    {
        $array_data = [];

        $int_id = $this->force_get_id($this->SysModel, 'uid');

        $where = ['SysModel.id' => $int_id];

        $entity = $this->SysModel->find()->select(['SysModel.id','SysModel.uid','SysModel.name','SysModel.model','SysModel.table',])->where($where)->first();

        if(!empty($entity)){
            $Model = $this->loadModel($entity->model);

            $array_query = $Model->_sources([
                // 'SysModelResource' => [
                //     'type' => 'LEFT',
                //     'table' => 'sys_models_resources',
                //     'conditions' => "SysModelResource.deleted = 0 AND SysModelResource.{$field} = {$model_id} AND SysModelResource.model_id = {$entity->id} AND SysModelResource.resource_id = {$entity->model}.id"
                // ],
            ],'Embarque.id')->toArray();

            foreach($array_query as $row){
                // debug($row);exit;
                $array_data[] = array(
                    'uid' => trim($row['uid']),
                    'title' => trim($row['_title']),
                    'model' => $entity->model,
                    'assigned' => empty($row->Embarque['id'])? false : true,
                );
            }
        }

        $this->Response->success();
        $this->Response->set('data', $array_data);
    }

    public function combobox()
    {
        $rows = $this->SysModel->find()
            ->select([
                'SysModel.uid','SysModel.name','SysModel.model'
            ])
            ->where(['SysModel.deleted' => 0])
            ->order(['SysModel.name ASC']);

        $array_data = array();
        foreach($rows as $row){
            // $row->level = intval($row->level) - 1;

            $array_data[] = array(
                'uid' => trim($row->uid),
                'name' => trim($row->name),
                'model' => trim($row->model),
            );
        }

        $this->Response->success();
        $this->Response->set('data', $array_data);
    }
}