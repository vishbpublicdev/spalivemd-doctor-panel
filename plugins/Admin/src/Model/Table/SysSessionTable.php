<?php
namespace Admin\Model\Table;

use Cake\ORM\Table;

class SysSessionTable extends Table
{
    public function initialize(array $config) : void
    {
        $this->setTable('sys_sessions'); // Name of the table in the database, if absent convention assumes lowercase version of file prefix
    }
}