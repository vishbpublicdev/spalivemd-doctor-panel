<?php
namespace Admin\Controller\Component;

use Cake\Utility\Security;

use Cake\Event\Event;

use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

class ResponseComponent extends Component {
    private $messages = [];
    private $success = false;
    private $controller = null;

    // private $post = null;
    private $errors = [];

    public function initialize(array $config): void {
        $this->controller = $this->_registry->getController();
    }

    public function beforeRender(Event $event)
    {
        // debug($this->controller->viewBuilder());exit;
        $viewBuilder = $this->controller->viewBuilder();
        $viewBuilder->setVar('success', $this->success);

        if(!empty($this->messages)){
            // $this->controller->viewVars['messages'] = $this->messages;
            $viewBuilder->setVar('messages', $this->messages);
        }

        if(!empty($this->errors)){
            // $this->controller->viewVars['errors'] = $this->errors;
            $viewBuilder->setVar('errors', $this->success);
        }

        // if(!isset($this->viewVars['message'])){
        //     $this->viewVars['message'] = [];
        // }

    	// 	$this->viewVars['message'] = array_merge($this->viewVars['message'], $obj_message);
    	// }else{
        //     // $this->set('success', $bool_success);
    	// }
    }

    public function success($_success = true)
    {
        $this->success = $_success;
    }

    public function set($key, $value)
    {
        $this->controller->set($key, $value);
    }

    public function data($_data = [])
    {
        $this->controller->viewVars['data'] = $_data;
    }

    public function add_errors($errors) {
        if(empty($errors)) return;

        if(is_string($errors)){
            $this->errors[] = $errors;
        }elseif(is_array($errors)){
            foreach($errors as $field => $rules){
                if(is_array($rules)){
                    foreach($rules as $rule => $error){
                        $this->errors[] = $error;
                    }
                }else{
                    $this->errors[] = $rules;
                }
            }
        }
    }

    public function add_messages($messages) {
        if(empty($messages)) return;

        if(is_string($messages)){
            $this->messages[] = $messages;
        }
    }

    public function is_error() {
        return count($this->errors)? true : false;
    }

    public function message($_message = null) {
        // if(!isset($this->viewVars['message'])){
        //     $this->viewVars['message'] = [];
        // }

    	if(is_array($_message)){
    		$this->messages = array_merge($this->messages, $_message);
    	}else{
            $this->messages[] = $_message;
            // $this->set('success', $bool_success);
    	}
    }

    // public function output($output)
    // {
    //     $this->output = $output;
    // }

    // public static function __data($array, $prefix = ''){
    //     $result = [];

    //     if(!is_array($array)){
    //         return [];
    //     }

    //     foreach ($array as $key => $value) {
    //         if(is_array($value)){
    //             // print_r($value);exit;
    //             $result = array_merge($result, self::__data($value, "{$key}."));
    //         }else{
    //             $result["{$prefix}{$key}"] = $value;
    //         }
    //     }

    //     return $result;
    // }

    // public static function post(){
    //     if(self::$post == null){
    //         self::$post = self::__data(json_decode(file_get_contents('php://input'), true));
    //     }

    //     return self::$post;
    // }

    // public static function ifadd(&$data, $key, $value){
    //     $key = trim($key);

    //     if($value !== null){
    //         $value = trim($value);

    //         $data[$key] = $value;
    //     }
    // }

    // public static function in_array($value, $array, $error){
    //     if($value === null) return null;
    //     $value = trim($value);

    //     if(!in_array($value, $array)){
    //         self::$array_errors[] = $error === false? 'Valor invalido.' : $error;
    //     }

    //     return $value;
    // }

    // // public static function is_valid_date($str_date, $format = 'Y-m-d'){
    // //     return $str_date == str_date($format, strtotime($date));
    // // }

    // public static function date($value, $format = 'Y-m-d', $error = false){
    //     if($value === null) return null;
    //     $value = trim($value);

    //     $timestamp = strtotime($value);
    //     if(!empty($timestamp)){
    //         $value = date($format, $timestamp);
    //     }else{
    //         self::$array_errors[] = $error === false? 'Fecha invalida.' : $error;
    //     }

    //     return $value;
    // }

    // public static function email($email, $error = false){
    //     $email = trim($email);

    //     if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
    //         self::$array_errors[] = $error === false? 'Correo invalido.' : $error;
    //     }

    //     return $email;
    // }


    // public static function int($value, $error = false){
    //     if($value === null) return null;

    //     if(is_numeric($value)){
    //         $value = intval($value);
    //     }else{
    //         self::$array_errors[] = $error === false? 'Numero invalido.' : $error;
    //     }

    //     return $value;
    // }

    // public static function boolean($key){
    //     $value = get($key, '');

    //     return intval($value);
    // }

    // public static function double($value, $error = false){
    //     if($value === null) return null;

    //     $value = is_string($value) && empty($value)? 0 : $value;
    //     $value = preg_replace('/[^0-9.]/', '', $value);

    //     if(is_double($value + 0.0)){
    //         $value = doubleval($value);
    //     }else{
    //         self::$array_errors[] = $error === false? 'Numero invalido.' : $error;
    //     }

    //     return $value;
    // }

    // public static function string($value, $length = false, $error = false){
    //     if($value === null) return null;
    //     $value = trim($value);

    //     if($length !== false && strlen($value) < $length){
    //         self::$array_errors[] = $error === false? 'Cadena invalida.' : $error;
    //     }

    //     return $value;
    // }

    // public static function add_error($str_error){
    //     self::$array_errors[] = $str_error;
    // }

    // public static function get_errors(){
    //     return self::$array_errors;
    // }

    // public static function is_valid(){
    //     return empty(self::$array_errors)? true : false;
    // }
}