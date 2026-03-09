<?php
namespace Admin\Model\Behavior;

use ArrayObject;
use Cake\ORM\Entity;
use Cake\Event\Event;
use Cake\ORM\Behavior;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;

use Cake\Utility\Text;
use Cake\Datasource\FactoryLocator;

class MyBehavior extends Behavior {

	public function initialize(array $config): void {
	    // Some initialization code here
	}

	public function new_uid() {
		return Text::uuid(); //uniqid('', true);
    }

	public function id_to_uid($id, $bypassResource = false) {
		$id = intval($id);
		if($id == 0) return '';

		$alias = $this->_table->getAlias();

		$array_query = $this->_table->find()->select(["{$alias}.uid"])->applyOptions(['bypassResource' => $bypassResource])->where(["{$alias}.id" => $id,"{$alias}.deleted" => 0]);
		return $array_query->count() == 0? '' : $array_query->first()->uid;
    }

	public function uid_to_id($uid, $bypassResource = false) {
		$uid = trim($uid);
		// echo "*{$uid}*";exit;
		if(empty($uid)) return 0;

		$alias = $this->_table->getAlias();

		$array_query = $this->_table->find()->select(["{$alias}.id"])->applyOptions(['bypassResource' => $bypassResource])->where(["{$alias}.uid" => $uid,"{$alias}.deleted" => 0]);
		return $array_query->count() == 0? 0 : $array_query->first()->id;
    }

	public function new_entity($array_data) {
    	$new_row = $this->_table->newEntity($array_data);

    	$new_row = $this->_table->save($new_row);

    	return $new_row == false? false : $new_row;
	}

	public function beforeFind(Event $event, Query $query, ArrayObject $options, $primary)
	{
		$user_id = defined('USER_ID')? USER_ID : 0;

		$alias = $this->_table->getAlias();
		$array_models = defined('ARRAY_RESOURCES')? ARRAY_RESOURCES : [];
		$int_model_id = isset($array_models[$alias])? intval($array_models[$alias]) : 0;

		if($int_model_id){
			$bypassResource = isset($options['bypassResource']) && $options['bypassResource'] === true? true : false;
			// debug(ACCESS_RESOURCE);debug(ACCESS_LEVEL);
			if(!in_array(ACCESS_LEVEL,['Administrator','Owner']) && $bypassResource == false){
				$grupos_ids = defined('GROUP_IDs')? GROUP_IDs : [];

				if(in_array(ACCESS_LEVEL,['Contributed','Reader']) && ACCESS_RESOURCE == 'Own'){
					$query->where([
						"{$alias}.createdby" => USER_ID
					]);
				}elseif(in_array(ACCESS_LEVEL,['Contributed','Reader']) && ACCESS_RESOURCE == 'Both'){
					$query->distinct();
					$query->innerJoin(['_SysModel' => 'sys_models'],["`_SysModel`.`id` = {$int_model_id}",]);
					$query->leftJoin(['_SysAccessResource' => 'sys_access_resources'],[
						"`_SysAccessResource`.`deleted` = 0",
						"`_SysAccessResource`.`model_id` = `_SysModel`.`id`",
						"`_SysAccessResource`.`resource_id` IN(`{$alias}`.`id`,-9)",
						"(`_SysAccessResource`.`user_id` = {$user_id} OR `_SysAccessResource`.`group_id` IN(" . implode(',', $grupos_ids) . "))" //Rol_id
					]);

					$query->where([
						'OR' => [
							"`{$alias}`.`createdby` = {$user_id}",
							"`_SysAccessResource`.`id` IS NOT NULL",
						]
					]);
				}else{
					$query->distinct();
					$query->innerJoin(['_SysModel' => 'sys_models'],["`_SysModel`.`id` = {$int_model_id}",]);
					$query->innerJoin(['_SysAccessResource' => 'sys_access_resources'],[
						"`_SysAccessResource`.`deleted` = 0",
						"`_SysAccessResource`.`model_id` = `_SysModel`.`id`",
						"`_SysAccessResource`.`resource_id` IN(`{$alias}`.`id`,-9)",
						"(`_SysAccessResource`.`user_id` = {$user_id} OR `_SysAccessResource`.`group_id` IN(" . implode(',', $grupos_ids) . "))" //Rol_id
					]);
				}
				// debug($query->join);
			}
		}
		// pr($query->sql());
		// exit;
	}

	public function beforeSave(Event $event, EntityInterface $entity, ArrayObject $options) {
		if(isset($entity->id) && $entity->id > 0) $entity->setNew(false);

		$alias = $this->_table->getAlias();
		$user_id = defined('USER_ID')? USER_ID : 0;

		if (!defined('USER_ID')) define('USER_ID', 0);
		$entity->set('modifiedby',  USER_ID);

		if($entity->isNew()){
			if (gettype($entity->uid) == 'undefined' || $entity->uid == '') {
				$entity->set('uid',  $this->new_uid());
			}
			if (!$entity->createdby || $entity->createdby == 0) $entity->set('createdby',  USER_ID);
			$entity->set('organization_id',  ORGANIZATION_ID);
		}

		$array_models = ARRAY_RESOURCES;
		// pr($array_models);exit;
		$int_model_id = isset($array_models[$alias])? intval($array_models[$alias]) : 0;
		// echo $alias;exit;
		if($int_model_id){
			if(!$entity->isNew()){
				$grupos_ids = defined('GROUP_IDs')? GROUP_IDs : [];

				$query = $this->_table->find()->select(["{$alias}.id"])->applyOptions(['bypassResource' => true]);
				$query->where(["`{$alias}`.`id` = {$entity->id}"]);

				if(in_array(ACCESS_LEVEL, ['Reader','Deny'])){
					return false;
				}elseif(ACCESS_LEVEL == 'Contributed' && ACCESS_RESOURCE == 'Own'){
					$query->where(["`{$alias}`.`createdby` = {$user_id}"]);
				}elseif(ACCESS_LEVEL == 'Contributed' && ACCESS_RESOURCE == 'Assigned'){
					$query->innerJoin(['_SysModel' => 'sys_models'],["`_SysModel`.`id` = {$int_model_id}",]);
					$query->innerJoin(['_SysAccessResource' => 'sys_access_resources'],[
						"`_SysAccessResource`.`deleted` = 0",
						"`_SysAccessResource`.`model_id` = `_SysModel`.`id`",
						"`_SysAccessResource`.`resource_id` IN(`{$alias}`.`id`,-9)",
						"(`_SysAccessResource`.`user_id` = {$user_id} OR `_SysAccessResource`.`group_id` IN(" . implode(',', $grupos_ids) . "))" //Rol_id
					]);
				}elseif(ACCESS_LEVEL == 'Contributed' && ACCESS_RESOURCE == 'Both'){
					$query->innerJoin(['_SysModel' => 'sys_models'],["`_SysModel`.`id` = {$int_model_id}",]);
					$query->leftJoin(['_SysAccessResource' => 'sys_access_resources'],[
						"`_SysAccessResource`.`deleted` = 0",
						"`_SysAccessResource`.`model_id` = `_SysModel`.`id`",
						"`_SysAccessResource`.`resource_id` IN(`{$alias}`.`id`,-9)",
						"(`_SysAccessResource`.`user_id` = {$user_id} OR `_SysAccessResource`.`group_id` IN(" . implode(',', $grupos_ids) . "))" //Rol_id
					]);
					$query->where([
						'OR' => [
							"`{$alias}`.`createdby` = {$user_id}",
							"`_SysAccessResource`.`id` IS NOT NULL",
						]
					]);
				}else{
					return true;
				}

				// debug(ACCESS_RESOURCE);debug(ACCESS_LEVEL);
				// debug($query->count());
				// exit;

				// Cancelamos el guardado si el recurso no es propio.
				if($query->count() == 0){
					return false;
				}
			}
		}
	}

	// public function afterSave(Event $event, EntityInterface $entity, ArrayObject $options){
	// 	$alias = $this->_table->getAlias();

	// 	if (!defined('USER_ID')) define('USER_ID', 0);

	// 	$array_models = ARRAY_RESOURCES;
	// 	$int_model_id = isset($array_models[$alias])? intval($array_models[$alias]) : 0;

	// 	if($int_model_id){
	// 		$grupos_ids = defined('GROUP_IDs')? GROUP_IDs : [];

	// 		$SysModelResource = FactoryLocator::get('Table')->get('SysModelResource', ['table' => 'sys_models_resources']);
	// 		$SysModelResource->addBehavior('Timestamp');
	// 		$resoure_acesss = $SysModelResource->find()->select(['model_id'])->where(['deleted' => 0, 'model_id' => $int_model_id, 'resource_id IN' => [-9, $entity->id], 'OR' => ['user_id' => USER_ID, 'group_id IN' => $grupos_ids]])->first();
	// 		if(empty($resoure_acesss)){
	// 			// $new_row = $SysModelResource->newEntity(['alias' => $alias, 'model_id' => $int_model_id, 'resource_id' => $entity->id, 'user_id' => USER_ID, 'group_id' => 0]);
	// 			// $new_row->createdby = USER_ID;
	// 			// $new_row->modifiedby = USER_ID;
	// 			// $SysModelResource->save($new_row);
	// 		}
	// 	}
    // }
}