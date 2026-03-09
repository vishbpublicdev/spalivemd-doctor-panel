<?php
namespace Admin\Model\Table;

use Cake\ORM\Table;
use Cake\Utility\Hash;
use Cake\Validation\Validator;

// use Cake\ORM\TableRegistry;
use Cake\Datasource\FactoryLocator;

class SysModelTable extends Table
{
    public function initialize(array $config) : void
    {
        $this->setTable('sys_models'); // Name of the table in the database, if absent convention assumes lowercase version of file prefix

        $this->addBehavior('Admin.My');
        $this->addBehavior('Timestamp'); // Allows your model to timestamp records on creation/modification
    }

    public function get_user_resources($user_id)
    {
        $result = [];

        $rows = $this->find()
            ->select([
                'SysModel.id',
                'SysModel.uid',
                'SysModel.name',
                'SysModel.model',
                'SysModel.table',
                'SysAccessResource.resource_id',
                'total' => 'COUNT(SysModel.id)'
            ])->join([
                'SysAccessResource' => [
                    'type' => 'INNER',
                    'table' => 'sys_access_resources',
                    'conditions' => "SysAccessResource.deleted = 0 AND SysAccessResource.model_id = SysModel.id"
                ],
            ])
            ->where(['SysAccessResource.user_id' => $user_id])
            ->group('SysModel.id')
            ->order(['SysModel.name ASC']);

        foreach($rows as $row){
            // $str_resources = '-';
            if($row->SysAccessResource['resource_id'] == '-9'){
                // $str_resources = 'Todos';
                $result[] = array(
                    'uid' => '_',
                    'name' => trim($row->name),
                    'title' => 'Todos los Recursos de '.$row->name,
                    'model' => $row->model,
                    'access' => 'all',
                );
            }elseif(intval($row->total) > 0){
                // $Model = $this->loadModel($row->model);
                // $Model = TableRegistry::get($row->model, ['table' => $row->table]);
                // Cake\ORM\Locator\TableLocator::get()
                // $Model = $this->TableLocator->get($row->model, ['table' => $row->table]);

                $Model = FactoryLocator::get('Table')->get($row->model, ['table' => $row->table]);
                // $Model = TableRegistry::getTableLocator()->get('Articles');

                // $Model = FactoryLocator::get($row->model, ['table' => $row->table]);

                $array_query = $Model->_sources([
                    'SysAccessResource' => [
                        'type' => 'INNER',
                        'table' => 'sys_access_resources',
                        'conditions' => [
                            'SysAccessResource.deleted' => 0,
                            "SysAccessResource.resource_id = {$row->model}.id",
                            'SysAccessResource.user_id' => $user_id,
                            'SysAccessResource.model_id' => $row->id,
                        ]
                    ],
                ],"{$row->model}.id"); //->toArray();

                foreach ($array_query as $reg) {
                    $result[] = array(
                        'uid' => trim($reg->uid),
                        'name' => trim($row->name),
                        'title' => trim($reg->_title),
                        'model' => $row->model,
                        'access' => 'one',
                    );
                }
            }
        }

        return $result;
    }

    public function get_group_resources($group_id)
    {
        $result = [];

        $rows = $this->find()
            ->select([
                'SysModel.id',
                'SysModel.uid',
                'SysModel.name',
                'SysModel.model',
                'SysModel.table',
                'SysAccessResource.resource_id',
                'total' => 'COUNT(SysModel.id)'
            ])->join([
                'SysAccessResource' => [
                    'type' => 'INNER',
                    'table' => 'sys_access_resources',
                    'conditions' => "SysAccessResource.deleted = 0 AND SysAccessResource.model_id = SysModel.id"
                ],
            ])
            ->where(['SysAccessResource.group_id' => $group_id])
            ->group('SysModel.id')
            ->order(['SysModel.name ASC']);

        foreach($rows as $row){
            // $str_resources = '-';
            if($row->SysAccessResource['resource_id'] == '-9'){
                // $str_resources = 'Todos';
                $result[] = array(
                    'uid' => '_',
                    'name' => trim($row->name),
                    'title' => 'Todos los Recursos de '.$row->name,
                    'model' => $row->model,
                    'access' => 'all',
                );
            }elseif(intval($row->total) > 0){
                // $Model = $this->loadModel($row->model);
                // $Model = TableRegistry::get($row->model, ['table' => $row->table]);
                // Cake\ORM\Locator\TableLocator::get()
                // $Model = $this->TableLocator->get($row->model, ['table' => $row->table]);

                $Model = FactoryLocator::get('Table')->get($row->model, ['table' => $row->table]);
                // $Model = TableRegistry::getTableLocator()->get('Articles');

                // $Model = FactoryLocator::get($row->model, ['table' => $row->table]);

                $array_query = $Model->_sources([
                    'SysAccessResource' => [
                        'type' => 'INNER',
                        'table' => 'sys_access_resources',
                        'conditions' => [
                            'SysAccessResource.deleted' => 0,
                            "SysAccessResource.resource_id = {$row->model}.id",
                            'SysAccessResource.group_id' => $group_id,
                            'SysAccessResource.model_id' => $row->id,
                        ]
                    ],
                ],"{$row->model}.id"); //->toArray();

                foreach ($array_query as $reg) {
                    $result[] = array(
                        'uid' => trim($reg->uid),
                        'name' => trim($row->name),
                        'title' => trim($reg->_title),
                        'model' => $row->model,
                        'access' => 'one',
                    );
                }
            }
        }

        return $result;
    }

    // public function validationDefault(Validator $validator){
    //     $validator
    //         ->notEmpty('nombre', 'El nombre del Rol no puede quedar vacio');
    //     //     ->requirePresence([
    //     //     'uid' => [
    //     //         'mode' => 'create',
    //     //         'message' => 'Se requiere una clave UID'
    //     //     ],
    //     // ]);

    //     return $validator;
    // }
}