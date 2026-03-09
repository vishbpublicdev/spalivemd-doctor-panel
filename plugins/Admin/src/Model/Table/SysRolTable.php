<?php
namespace Admin\Model\Table;

use Cake\ORM\Table;
use Cake\Utility\Hash;
use Cake\Validation\Validator;

class SysRolTable extends Table
{
    public function initialize(array $config) : void
    {
        $this->setTable('sys_groups'); // Name of the table in the database, if absent convention assumes lowercase version of file prefix

        $this->addBehavior('Admin.My');
        $this->addBehavior('Timestamp'); // Allows your model to timestamp records on creation/modification
    }

    public function get_user_groups($user_id)
    {
        return $this->find()
            ->select([
                'SysRol.id',
                'SysRol.uid',
                'SysRol.name',
                'SysRol.description',
                'SysUserGroup.created',
            ])->join([
                'SysUserGroup' => [
                    'type' => 'INNER',
                    'table' => 'sys_users_groups',
                    'conditions' => "SysUserGroup.deleted = 0 AND SysUserGroup.group_id = SysRol.id"
                ],
            ])
            ->where(['SysUserGroup.user_id' => $user_id])
            ->order(['SysRol.name ASC']);
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