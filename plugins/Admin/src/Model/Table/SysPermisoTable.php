<?php
namespace Admin\Model\Table;

use Cake\ORM\Table;
use Cake\Utility\Hash;
use Cake\Validation\Validator;

class SysPermisoTable extends Table
{
    public function initialize(array $config) : void
    {
        $this->setTable('sys_permissions'); // Name of the table in the database, if absent convention assumes lowercase version of file prefix

        $this->addBehavior('Tree');
        $this->addBehavior('Admin.My');
        $this->addBehavior('Admin.MyTree');
        $this->addBehavior('Timestamp'); // Allows your model to timestamp records on creation/modification
    }

    public function get_user_permissions($user_id)
    {
        return $this->find()
            ->select([
                'SysPermiso.uid',
                'SysPermiso.name',
                'SysPermiso.description',
                'SysUserPermission.access_level',
                'SysUserPermission.access_resources',
                'SysUserPermission.created',
            ])->join([
                'SysUserPermission' => [
                    'type' => 'INNER',
                    'table' => 'sys_users_permissions',
                    'conditions' => "SysUserPermission.deleted = 0 AND SysUserPermission.permission_id = SysPermiso.id"
                ],
            ])
            ->where(['SysUserPermission.user_id' => $user_id])
            ->order(['SysPermiso.name DESC']);
    }

    public function get_group_permissions($group_id)
    {
        return $this->find()
            ->select([
                'SysPermiso.uid',
                'SysPermiso.name',
                'SysPermiso.description',
                'SysGroupPermission.access_level',
                'SysGroupPermission.access_resources',
                'SysGroupPermission.created',
            ])->join([
                'SysGroupPermission' => [
                    'type' => 'INNER',
                    'table' => 'sys_groups_permissions',
                    'conditions' => "SysGroupPermission.deleted = 0 AND SysGroupPermission.permission_id = SysPermiso.id"
                ],
            ])
            ->where(['SysGroupPermission.group_id' => $group_id])
            ->order(['SysPermiso.name DESC']);
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