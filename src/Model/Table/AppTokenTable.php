<?php
namespace App\Model\Table;


use Cake\ORM\Table;
use Cake\Utility\Hash;
use Cake\Validation\Validator;

class AppTokenTable extends Table
{
    public function initialize(array $config) : void
    {
        $this->setTable('app_tokens'); // Name of the table in the database, if absent convention assumes lowercase version of file prefix

        $this->addBehavior('Admin.My');
        // $this->addBehavior('Admin.MyTree');
        // $this->addBehavior('Tree');
        $this->addBehavior('Timestamp'); // Allows your model to timestamp records on creation/modification
    }


    public function validateToken($token){

    	$find = $this->getConnection()->query(
            "SELECT 
                T.user_id, U.uid ,U.type as user_role, U.state as user_state, U.email, U.name
            FROM app_tokens T
            INNER JOIN sys_users U ON U.id = T.user_id
            WHERE T.token = '{$token}'
            "
        )->fetchAll('assoc');
    	if(isset($find[0])){
            define('USER_ID', $find[0]['user_id']);
            define('USER_UID', $find[0]['uid']);
            define('USER_TYPE', $find[0]['user_role']);
            define('USER_NAME', $find[0]['name']);
            define('USER_EMAIL', $find[0]['email']);
            if (USER_ID == 1)
                define('MASTER', true);
            else
                define('MASTER', false);
    		return $find[0];
    	}
    	return false;
    }

}