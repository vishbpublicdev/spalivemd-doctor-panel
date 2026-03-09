<?php
namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Utility\Hash;
use Cake\Validation\Validator;

class AgreementTable extends Table
{
    public function initialize(array $config) : void
    {
        $this->setTable('cat_agreements'); // Name of the table in the database, if absent convention assumes lowercase version of file prefix
        $this->addBehavior('Admin.My');
        $this->addBehavior('Timestamp'); // Allows your model to timestamp records on creation/modification
    }

}