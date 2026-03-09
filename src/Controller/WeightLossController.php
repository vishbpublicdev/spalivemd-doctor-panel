<?php
declare(strict_types=1);

namespace App\Controller;

use Admin\Controller\AppController;
use Cake\Utility\Security;
use Cake\Utility\Hash;


class WeightLossController extends AppController{

	public function initialize() : void{
        parent::initialize();
        $this->loadModel('DataTreatment');
        $this->loadModel('DataConsultationOtherServices');
        $this->Session = $this->getRequest()->getSession();


        
    }

    public function grid(){
    	$page = intval(get('page', 1));
        $limit = get('limit', 50);
        $array_data = [];

        $where = ['DataConsultationOtherServices.deleted' => 0, 'CheckIn.call_type != ' => 'CHECK IN','CheckIn.status' => 'COMPLETED','Patient.deleted' => 0];
        $user_id = USER_ID;
        $_fields = [
                'DataConsultationOtherServices.assistance_id', 
                'DataConsultationOtherServices.patient_id',
                'DataConsultationOtherServices.service_uid',
                'DataConsultationOtherServices.amount',
                'DataConsultationOtherServices.created',
                //'DataConsultationOtherServices.status',
                'DataConsultationOtherServices.payment',
                'DataConsultationOtherServices.uid',
                'DataConsultationOtherServices.schedule_date',
                'Injector.name','Injector.lname',
                'Patient.name','Patient.lname',
                'State.name',
                //'Payment.type','Payment.uid','Payment.service_uid',
                'Service.uid','Service.title',
                'CheckIn.status','CheckIn.support_id',
                'CheckIn.call_title','CheckIn.call_type',
                'Purchases.shipping_date',
                'Purchases.tracking',
                'Questions.data',
                'DataConsultationOtherServices.id',
            ];

        //$_fields['prepaid'] = "(SELECT total FROM data_payment DP WHERE DP.prepaid = 1 AND DP.id_from = DataConsultationOtherServices.patient_id AND DP.payment <> '' AND DP.is_visible = 1 AND DP.created < DataConsultationOtherServices.created LIMIT 1)";

        $_join = [
            'Patient' => ['table' => 'sys_users','type' => 'INNER','conditions' => 'Patient.id = DataConsultationOtherServices.patient_id'],
            'Service' => ['table' => 'cat_other_services','type' => 'LEFT','conditions' => 'Service.uid = DataConsultationOtherServices.service_uid'],
            /*'Notes' => ['table' => 'data_treatment_notes','type' => 'LEFT','conditions' => 'Notes.treatment_id = DataConsultation.id'],
            'Review' => ['table' => 'data_treatment_reviews','type' => 'LEFT','conditions' => 'Review.treatment_id = DataConsultation.id'],*/
            'State' => ['table' => 'cat_states','type' => 'INNER','conditions' => 'State.id = Patient.state'],
            /*'DataCertificates' => ['table' => 'data_certificates', 'type' => 'LEFT', 'conditions' => 'DataCertificates.consultation_id = DataConsultation.id'],
            'Clinic' => ['table' => 'sys_users','type' => 'LEFT','conditions' => 'Clinic.id = DataConsultation.createdby AND DataConsultation.createdby <> DataConsultation.patient_id AND Clinic.type = "clinic"'],*/
            //'Crby' => ['table' => 'sys_users','type' => 'LEFT','conditions' => 'Crby.id = Patient.createdby'],
            /*'Requestedby' => ['table' => 'sys_users', 'type' => 'LEFT', 'conditions' => 'Requestedby.id = DataConsultation.createdby'],*/
            //'Payment' => ['table' => 'data_payment', 'type' => 'LEFT', 'conditions' => 'Payment.intent = DataConsultationOtherServices.payment'],
            'CheckIn' => ['table' => 'data_other_services_check_in', 'type' => 'LEFT', 'conditions' => 'CheckIn.consultation_uid = DataConsultationOtherServices.uid'],
            'Injector' => ['table' => 'sys_users','type' => 'LEFT','conditions' => 'Injector.id = CheckIn.support_id'],
            'Purchases' => ['table' => 'data_purchases_other_services', 'type' => 'LEFT', 'conditions' => 'Purchases.id = CheckIn.purchase_id'],            
            'Questions' => ['table' => 'data_consultation_postexam_other_services', 'type' => 'LEFT', 'conditions' => 'Questions.consultation_id = DataConsultationOtherServices.id'],            
        ];

        if (get('filter','')) {
            $arr_filter = json_decode(get('filter'),true);
            if ($arr_filter[0]['property'] == "query") {

                $search = $arr_filter[0]['value'];

                $arr_val = explode(' ', str_replace('@', '', $search));
                $matchValue = '';
                $sep = '';
                foreach ($arr_val as $value) {
                    $matchValue .= $sep.'+'.$value.'*';
                    $sep = ' ';
                }

                $where['OR'] = [['Patient.email LIKE' => "%$search%"], ['Injector.email LIKE' => "%$search%"], 
                    "MATCH(Patient.name,Patient.mname,Patient.lname) AGAINST ('{$matchValue}' IN BOOLEAN MODE)", 
                    "MATCH(Injector.name,Injector.mname,Injector.lname) AGAINST ('{$matchValue}' IN BOOLEAN MODE)"];
                    
            }
        }
        $userType = $this->Session->read('_User.user_type');
        if($userType != 'MASTER'){
            $where['Patient.md_id'] = $user_id;
        }//debug($this->DataConsultationOtherServices->find()->select($_fields)->where($where)->join($_join)); exit;
        $entity = $this->DataConsultationOtherServices->find()->select($_fields)->where($where)->join($_join)->order(['DataConsultationOtherServices.created' => 'DESC'])->limit($limit)->page($page)->all();
        $entity_total = $this->DataConsultationOtherServices->find()->select($_fields)->where($where)->join($_join)->count();
        ;//$this->DataTreatment->find()->where($where)->join($_join)->count();
        if(!empty($entity)){
            foreach($entity as $row) {

                $status = $row['CheckIn']['status'];
                /*if ($status == 'PENDING') {
                    $status = 'SCHEDULED';
                }*/
                $payment = 0;
                if (!empty($row['payment'])) {
                    $payment = 1;
                }

                /*$str_extra_payment = '';
                if ($status == 'CANCEL' && $row['prepaid']) {
                    $payment = 'Paid but pending';
                    $str_extra_payment = ' credit';

                }

                if ($status == 'INIT') {
                    $row['amount'] = 0;
                    $row['prepaid'] = 0;
                }*/

                $url_panel = env('url_panel', 'https://app.spalivemd.com/panel/');

                $examiner = '';
                if (isset($row['Injector']['name'])) {$this->log(json_encode( $row['Injector']));
                    $examiner = $row['Injector']['name'] . ' ' . $row['Injector']['lname'];
                }

//                 $this->log(__LINE__ . ' ' . ($row['Questions']['data']));
                 if($row['Questions']['data'] != ""){
                    //$this->log(__LINE__ . ' ' . (($row['Questions']['data'])));                    
                    $ar_ques = json_decode($row['Questions']['data'], true);
                    
                    for ($i=0; $i < count($ar_ques); $i++) { 
                        $this->log(__LINE__ . ' ' . json_encode($ar_ques[$i]));    
                    }
                 }else{
                    $ar_ques = [];
                 }
                    
                 //foreach ($row['Questions']['data'] as $key => $value) {
                    # code...
                 //}
                $array_data[] = array(
                    'examiner'       => $examiner,
                    'patient'        => $row['Patient']['name'] . ' ' . $row['Patient']['lname'],
                    //'service'        => $row['Service']['title'],
                    'date_time'      => $row->created,
                    //'state'          => $row['State']['name'],
                    'status'         => $status,
                    'payment_status' => $payment,
                    //'receipt'        => !empty($row['Payment']['uid']) ? "{$url_panel}/user-receipt-shopping/?action=rcpt_purchase&trgp=".$row['Payment']['uid'] : '' ,
                    'call_title'         => $row['CheckIn']['call_title'],
                    'call_type'         => $row['CheckIn']['call_type'],
                    'shipping_date'         => $row['Purchases']['shipping_date'],
                    'tracking'         => $row['Purchases']['tracking'],  
                    //'schedule_date'         => $row['schedule_date'],
                    //'uid'         => $row['uid'],
                    'Questions'         => $ar_ques,
                    //'id'         => $row['id'],
                    'product_type' => 'Vial'
                    
                );
            }
        }

        $this->Response->success();
        $this->Response->set('data', $array_data);
        $this->Response->set('total', $entity_total);
    }

    public function save(){
        $this->loadModel('DataTrtmentNotesDoc');

        $notes = get('notes', '');
        $treatment = $this->DataTreatment->find()->where(['DataTreatment.uid' => get('treatment_uid', '')])->first();
        if(empty($treatment)){
            $this->Response->add_errors('Invalid treatment.');
            return;
        }
        // if(strlen($notes) < 10){
        //     $this->Response->add_errors('Notes too short, minimum 10 characters.');
        //     return;
        // }

        $notes_entity = $this->DataTrtmentNotesDoc->find()->where(['DataTrtmentNotesDoc.uid' => get('uid', '')])->first();
        if(empty($notes_entity)){
            $array_save = [
                'uid' => $this->DataTrtmentNotesDoc->new_uid(),
                'notes' => $notes,
                'treatment_id' => $treatment->id,
                'doctor_id' => USER_ID,
            ];
            $notes_entity = $this->DataTrtmentNotesDoc->newEntity($array_save);
        }else{
            $notes_entity->notes = $notes;
            $notes_entity->treatment_id = $treatment->id;
            $notes_entity->doctor_id = USER_ID;
        }
        
        $treatment->approved = get('approved', 'PENDING');
        $treatment->approved_date = date('Y-m-d H:i:s');

        if($this->DataTreatment->save($treatment)){
            if($this->DataTrtmentNotesDoc->save($notes_entity)){
                $this->Response->success();
            }else{
                $this->Response->add_errors('Failed to save notes.');
            }
        }else{
            $this->Response->add_errors('Failed to update the approval.');           
        }
    }

    public function load(){
        $this->loadModel('DataTrtmentNotesDoc');
        $treatment = $this->DataTreatment->find()->where(['DataTreatment.uid' => get('uid', '')])->first();
        if(empty($treatment)){
            $this->Response->add_errors('Invalid treatment.');
            return;
        }

        $TrNotes = $this->DataTrtmentNotesDoc->find()->where(['DataTrtmentNotesDoc.treatment_id' => $treatment->id])->first();
        $result = [];
        if(!empty($TrNotes)){
            $result = [
                'uid' => $TrNotes->uid,
                'notes' => $TrNotes->notes,
                'approved' => $treatment->approved
            ];
        }

        $this->Response->set('data', $result);
        $this->Response->success();
    }

    private function cleanArrCert($arrCerts, &$examiners){
        if(sizeof($arrCerts) == 1){
            $examiners = $arrCerts[0]['examnier'];
            return $arrCerts;
        }
        $tmpExa = [];
        $tmp = [];
        foreach($arrCerts as $item ){
            $tmp[$item['name']] = $item['uid'];

            if(!in_array($item['examnier'], $tmpExa)){
                $tmpExa[] = $item['examnier'];
            }
        }

        $examiners = implode(', ', $tmpExa);
        $result = [];
        foreach($tmp as $key => $value){
            $result[] = ['uid' => $value, 'name' => $key];
        }

        return $result;
    }

    public function listmd() {

        $this->loadModel('SysUsersAdmin');
        $ent = $this->SysUsersAdmin->find()->where(['SysUsersAdmin.user_type' => 'DOCTOR','SysUsersAdmin.deleted' => 0])->all();
        if (USER_ID > 1) $ent = array();
        $result = array();
        $result[] = array('id' => 0, 'name' => 'ALL');
        foreach($ent as $row) {
            $result[] = array('id' => $row->id, 'name' => $row->name);
        }
        $this->Response->set('data', $result);

        $this->Response->success();

    }

    public function get_checkin(){        

        $consultation_uid = get('consultation_uid', '');
        if(empty($consultation_uid) || $consultation_uid == ''){
            $this->message('Empty consultation_uid.');
            return;
        }
        
        $this->loadModel('SpaLiveV1.DataConsultationOtherServices');
        $this->loadModel('SpaLiveV1.DataOtherServicesCheckIn');
        $this->loadModel('SpaLiveV1.DataPurchases');
        $this->loadModel('SpaLiveV1.DataPurchasesDetail');
        $this->loadModel('SpaLiveV1.DataPurchasesOtherServices');
        $this->loadModel('SpaLiveV1.DataPurchasesDetailOtherServices');
        $this->loadModel('SpaLiveV1.CatProductsOtherServices');
        $this->loadModel('SpaLiveV1.SysUsers');

        $_fields = ['examiner_name' => "CONCAT_WS(' ', SysUsers.name, SysUsers.lname)"];
        
        $checkins = $this->DataOtherServicesCheckIn->find()
        ->select($this->DataOtherServicesCheckIn)
        ->select($this->DataPurchases)
        ->select($_fields)
        ->join([
            'DataPurchases' => [
                'table' => 'data_purchases', 
                'type' => 'LEFT', 
                'conditions' => 'DataPurchases.id = DataOtherServicesCheckIn.purchase_id'],
            'SysUsers' => [
                'table' => 'sys_users', 
                'type' => 'LEFT', 
                'conditions' => 'SysUsers.id = DataOtherServicesCheckIn.support_id'],
        ])->where(['DataOtherServicesCheckIn.consultation_uid' =>  $consultation_uid])->orderDesc('DataOtherServicesCheckIn.id')->all();
            

        foreach($checkins as $row) {
            $array_products = [];
            $i = 0;            
            
            $fecha_objeto1 = $row->call_date;
            $fecha_modificada1 = $fecha_objeto1->format('m-d-Y');
            $row->call_date_string = $fecha_modificada1;
            if($row->status == 'SCHEDULED'){                
                 $dcos  = $this->DataConsultationOtherServices->find()      
                    ->where(['DataConsultationOtherServices.uid' =>  $row->consultation_uid])->first();
                if(!empty($dcos)){
                    $schedule_date = $dcos->schedule_date->i18nFormat('MM-dd-yyyy HH:mm'); 
                    $row->call_date_string = $schedule_date;
                }
            }
            if (isset($row['DataPurchases'])) {
                $dataPurchases = $row['DataPurchases'];                
                // Verificar si la propiedad 'shipping_date' existe en 'DataPurchases'
                if (isset($dataPurchases['shipping_date'])) {
                    $shippingDate = $dataPurchases['shipping_date'];
                    // Convertir la fecha de envío a un timestamp
                    $timestamp = strtotime($shippingDate);
                    // Obtener la fecha de envío formateada como una cadena (mm-dd-yyyy)
                    $shippingDateString = date('m-d-Y', $timestamp);
                    // Agregar la nueva variable 'shipping_date_string' al arreglo 'dataPurchases'
                    $row['DataPurchases']['shipping_date_string'] = $shippingDateString;
                }
            }            
            if($row->purchase_id){
                $ent_products = $this->DataPurchasesDetail->find()
                ->select($this->DataPurchasesDetail)
                ->select(['name' => 'CatProductsOtherServices.name'])
                ->join([
                    'CatProductsOtherServices' => ['table' => 'cat_products_other_services', 
                    'type' => 'LEFT', 
                    'conditions' => 'CatProductsOtherServices.id = DataPurchasesDetail.product_id'],
                ])->where(['DataPurchasesDetail.purchase_id' => $row->purchase_id])->all();

                foreach($ent_products as $row_pro) {
                    $array_products[] = array(
                        //"id"    => $row_pro->id,
                        "name"  => $row_pro->name,
                        //"qty"   => $row_pro->qty,   
                    );
                }

                $row["products"] = json_encode($array_products);
            }
        }
        
        $this->Response->set('data', $array_data); 
        $this->success();
    }

}