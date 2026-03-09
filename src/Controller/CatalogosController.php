<?php
declare(strict_types=1);

namespace App\Controller;

use Admin\Controller\AppController;

class CatalogosController extends AppController
{
    public $models = [
        'Chofer',
        'Camion',
        'Proveedor',
        'Documentador',
        'Fletero',
        'Clase',
        'Clasificacion',
        'Departamento',
        'Destinatario',
        'Producto',
        'ProveedorRemision'
    ];

    public function initialize() : void{
        parent::initialize();
    }

	public function grid(){
		$int_page = get('page', 1);
		$int_limit = get('limit', 100);
		$Model = get('model', '');

        $this->loadModel($Model);

        $conditions = array("{$Model}.deleted" => 0);

        $config = $this->$Model->_config();

        $this->_filters($Model, $config['search'], $conditions);

        $array_data = array();

        $array_regs = $this->$Model->find()->where($conditions)->order(["{$Model}.id DESC"]);
        $total = $this->$Model->find()->where($conditions)->count();

        // $total = $this->$Model->find('count', array('conditions' => $array_conditions ));

		foreach ($array_regs as $reg) {
			// $Model = $reg[$Model];
// debug($reg);
// debug($reg->getVisible());exit;
			$array_columns = array();
			foreach ($reg->getVisible() as $column) {
				if(in_array($column, array('id','deleted','created','createdby','modified','modifiedby'))){
					continue;
				}

				$array_columns[$column] = $reg->$column;
			}

			$array_data[] = $this->$Model->find_middleware($array_columns);
		}

        $this->Response->success();
		$this->Response->set('data', $array_data);
        $this->Response->set('total', $total);
    }

    public function load(){
		$Model = get('model', '');

        $this->loadModel($Model);

        $int_modelo_id = $this->force_if_get_id($this->$Model, 'uid');

        $entity = $this->$Model->find()->where(["{$Model}.id" => $int_modelo_id])->first();
		if(!empty($entity)){
            $array_columns = array();
            foreach ($entity->getVisible() as $column) {
                if(in_array($column, array('id','deleted','created','createdby','modified','modifiedby'))){
                    continue;
                }

                $array_columns[$column] = $entity->$column;
            }

            $this->Response->success();
			$this->Response->set('data', $this->$Model->find_middleware($array_columns));
		}
    }


	public function delete(){
		$Model = get('model', '');
        $this->loadModel($Model);

        $int_modelo_id = $this->force_if_get_id($this->$Model, 'uid');

        $entity = $this->$Model->find()->where(["{$Model}.id" => $int_modelo_id])->first();
        $entity->deleted = 1;

        $this->$Model->save($entity);
        if(!$entity->hasErrors()){
            $this->Response->success();
        }else{
            $this->Response->add_errors('Error al borrar el registro.');
        }
    }

    public function save(){
		$str_uid = get('uid', '');
		$Model = get('model', '');

        $this->loadModel($Model);

        $int_modelo_id = $this->force_if_get_id($this->$Model, 'uid');

		$array_fields = $this->$Model->_config()['fields'];

		$array_save = [
            'id' => $int_modelo_id
        ];
		foreach ($array_fields as $reg) {
            $value = get($reg['dataIndex'], null);
            if($value !== null){
                $array_save[$reg['dataIndex']] = $value;
            }
		}

        $entity = $this->$Model->newEntity( $this->$Model->save_middleware($array_save) );
        if(!$entity->hasErrors()){
            if($this->$Model->save($entity)){
                $this->Response->success();
                $this->Response->set('uid', $entity->uid);
            }
        }else{
            $this->Response->add_errors($entity->getErrors());
        }

		// $this->$Model->create();
		// if($this->$Model->save($this->$Model->save_middleware($array_save))){
		// 	$this->success();
		// }
    }

	public function combobox(){
		$Model = get('model', '');

        $this->loadModel($Model);

        $conditions = array("{$Model}.deleted" => 0);

        $array_data = array();

        $array_regs = $this->$Model->find()->select([$this->$Model->_key, $this->$Model->_value])->where($conditions)->order(["{$this->$Model->_order} DESC"]);
		foreach ($array_regs as $reg) {
            $_k = $this->$Model->_key;
            $_v = $this->$Model->_value;
            // debug($_k );debug($reg);exit;
            $array_data[] = [
                'uid' => $reg->$_k,
                'name' => $reg->$_v,
            ];
            // $this->$Model->find_middleware($array_columns);
		}

        $this->Response->success();
		$this->Response->set('_', $array_data);
    }

    public function dataview()
    {
        $arr_data = array();
        foreach($this->models as $reg){
            $this->loadModel($reg);

            $config = $this->$reg->_config();

            $arr_data[] = array(
                'model' => $reg,
                'title' => $reg,
                'detail' => $config['decription'],
                'config' => $config,
                'info2' => '( '.$this->$reg->find()->where(["{$reg}.deleted" => 0])->count().' )'
            );
        }

        // $this->Response->set('total', $total);
        $this->Response->set('data', $arr_data);
        $this->Response->success(true);
    }
}