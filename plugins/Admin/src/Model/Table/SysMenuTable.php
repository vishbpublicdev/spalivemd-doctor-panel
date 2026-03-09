<?php
namespace Admin\Model\Table;

use Cake\ORM\Table;
use Cake\Utility\Hash;
use Cake\Validation\Validator;

class SysMenuTable extends Table
{
    public function initialize(array $config) : void
    {
        $this->setTable('sys_menus'); // Name of the table in the database, if absent convention assumes lowercase version of file prefix

        $this->addBehavior('Admin.My');
        $this->addBehavior('Admin.MyTree');
        // $this->addBehavior('Tree');
        $this->addBehavior('Timestamp'); // Allows your model to timestamp records on creation/modification
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