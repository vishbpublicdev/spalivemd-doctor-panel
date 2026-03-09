<?php
declare(strict_types=1);

namespace App\Controller;

use Admin\Controller\AppController;
use Cake\Utility\Security;
use Cake\Utility\Hash;


class TreatmentsController extends AppController{

	public function initialize() : void{
        parent::initialize();
        $this->loadModel('DataTreatment');
        $this->Session = $this->getRequest()->getSession();


         $this->sql_part1 = "SELECT DC.uid, CTC.name, CONCAT_WS(' ', Exa.name, Exa.mname, Exa.lname) as examnier
            FROM cat_treatments_ci CTC
            JOIN data_consultation_plan DCP ON DCP.treatment_id = CTC.treatment_id
            JOIN data_certificates DC ON DC.consultation_id = DCP.consultation_id
            JOIN data_consultation DCO ON DCO.id = DC.consultation_id
            LEFT JOIN sys_users Exa ON Exa.id = DCO.assistance_id
            WHERE CTC.deleted = 0 
            AND CTC.id IN ( SELECT DISTINCT DTD.cat_treatment_id FROM data_treatment_detail DTD WHERE DTD.treatment_id = ";
        $this->sql_part2 = " AND DTD.quantity > 0) AND DCP.proceed = 1 AND NOW() < DC.date_expiration AND DCO.patient_id = ";
    }

    public function grid(){
    	$this->loadModel('Admin.DataTreatment');
        $this->loadModel('DataTreatmentImage');
        $this->loadModel('Admin.SysUsers');
        $this->loadModel('DataCertificates');
        $page = intval(get('page', 1));
        $limit = get('limit', 50);
        $_order = ['DataTreatment.schedule_date' => 'DESC']; 
        $user_id = USER_ID;
        //'INIT','DONE','CANCEL','CONFIRM','REJECT'
        $replace_array = array(
            'INIT' => 'REQUESTED',
            'DONE' => 'DONE',
            'CANCEL' => 'CANCELED',
            'CONFIRM' => 'APMT CONFIRMED',
            'REJECT' => 'REJECTED',
            'INVITATION' => 'INVITATION',
            'REQUEST' => 'REQUEST',
            'DONESELFTREATMENT' => 'DONE',
        );

        $array_data = [];
        $_fields = ['DataTreatment.id','DataTreatment.payment','DataTreatment.uid','Injector.name','Injector.lname','Patient.uid','Patient.name','Patient.lname','Injector.phone','Injector.email','Payment.total','Payment.promo_code','DataTreatment.amount','DataTreatment.schedule_date','DataTreatment.status','Notes.notes','State.name','Review.score','Review.id','Doctor.name', 'DataTreatment.approved', 'DataTreatment.patient_id', 'DataTreatment.address','DataTreatment.city','DataTreatment.zip','DataTreatment.suite','GFE.id','GFE.assitance'];
        $_fields['_treatments'] = "(SELECT GROUP_CONCAT(CONCAT_WS(' ',DTD.quantity,CT.name)) FROM data_treatment_detail DTD JOIN cat_treatments_ci CT ON CT.id = DTD.cat_treatment_id WHERE DTD.treatment_id = DataTreatment.id AND DTD.quantity > 0)";
        $_fields['_rtreatments'] = "(SELECT GROUP_CONCAT(DISTINCT CT.name) FROM cat_treatments_ci CT WHERE FIND_IN_SET(CT.id,DataTreatment.treatments))";
        $_fields['doctor_notes'] = "(SELECT DN.notes FROM data_trtment_notes_doc DN WHERE DN.treatment_id = DataTreatment.id AND DN.doctor_id = {$user_id} ORDER BY DN.id DESC LIMIT 1)";
        $_fields['appr_date'] = "(SELECT DN.created FROM data_trtment_notes_doc DN WHERE DN.treatment_id = DataTreatment.id AND DN.doctor_id = {$user_id} ORDER BY DN.id DESC LIMIT 1)";
        $_fields['total_treatments_required'] = "(
                    SELECT COUNT(CT.id)
                    FROM cat_treatments_ci CT
                    JOIN cat_treatments CTT ON CTT.id = CT.treatment_id
                    LEFT JOIN sys_treatments_ot STO ON STO.id = CTT.other_treatment_id
                    WHERE FIND_IN_SET(CT.id, DataTreatment.treatments)
                    AND (STO.id IS NULL OR  STO.require_mdsub = 1 )
                ) ";
        $_fields['type_category'] = "(SELECT GROUP_CONCAT(CONCAT_WS(' ',CTC.type)) 
        FROM data_treatment_detail DTD
        JOIN cat_treatments_ci CT ON CT.id = DTD.cat_treatment_id 
        JOIN cat_treatments_category CTC ON CTC.id = CT.category_treatment_id 
        WHERE DTD.treatment_id = DataTreatment.id AND DTD.quantity > 0)";
        $_fields['type_category2'] = "(SELECT GROUP_CONCAT(CONCAT(CTC.type) SEPARATOR ', ') 
        FROM cat_treatments_ci CT 
        JOIN cat_treatments_category CTC ON CTC.id = CT.category_treatment_id 
        WHERE FIND_IN_SET(CT.id,DataTreatment.treatments) LIMIT 1)";
        $_fields['Examiner'] = "(SELECT concat(name,' ', lname) FROM sys_users  WHERE sys_users.id = (select assistance_id from data_consultation dc where  dc.patient_id = DataTreatment.patient_id and dc.status = 'CERTIFICATE' ORDER BY dc.id DESC LIMIT 1))  ";        
        //$_fields['Examiner'] = "(SELECT name FROM sys_users join data_consultation dc on dc.patient_id = DataTreatment.patient_id WHERE status = 'CERTIFICATE' ORDER BY dc.id DESC LIMIT 1)";        

        $userType = $this->Session->read('_User.user_type');
        $where = [ 'DataTreatment.status IN' => ['DONE','DONESELFTREATMENT'],'DataTreatment.deleted' => 0,'Doctor.deleted' => 0];
        $where['Patient.name NOT LIKE'] = '%test%';
        $where['Patient.mname NOT LIKE'] = '%test%';
        $where['Patient.lname NOT LIKE'] = '%test%';  

        if($userType != 'MASTER'){
            $where['DataTreatment.assigned_doctor'] = $user_id;
        }

        $usr_uid = get('user_uid', '');
        if(!empty($usr_uid)){
            $u_ent = $this->SysUsers->find()->where(['SysUsers.uid' => $usr_uid])->first();
            if(!empty($u_ent)){
                $where['Injector.id'] = $u_ent->id;
            }
        }

        $usr_uid = get('patient_uid', '');
        if(!empty($usr_uid)){
            $u_ent = $this->SysUsers->find()->where(['SysUsers.uid' => $usr_uid])->first();
            if(!empty($u_ent)){
                $where['Patient.id'] = $u_ent->id;
            }
        }

        $mfilter = get('mfilter','');
        if (!empty($mfilter)) {
            if ($mfilter != 'ALL')
                $where['DataTreatment.assigned_doctor'] = $mfilter;
        }

        if (get('filter','')) {
            $arr_filter = json_decode(get('filter'),true);
            if ($arr_filter[0]['property'] == "query") {

                $search = $arr_filter[0]['value'];
                $matchValue = str_replace(' ', ' +', $search);
                $where['OR'] = [['Injector.email LIKE' => "%$search%"], ['Patient.email LIKE' => "%$search%"], 
                    "MATCH(Injector.name,Injector.mname,Injector.lname) AGAINST ('+{$matchValue}' IN BOOLEAN MODE)", 
                    "MATCH(Patient.name,Patient.mname,Patient.lname) AGAINST ('+{$matchValue}' IN BOOLEAN MODE)"];
            } else if ($arr_filter[0]['property'] == "query") {
            }
        }
        $_having = [];
        $cat = get('cat','');
        if (!empty($cat) && $cat != 'ALL'){
            if ($cat == 'NEUROTOXINS BASIC') {
                $_having = ['type_category LIKE' => '%BASIC%'];
            } else if ($cat == 'NEUROTOXINS ADVANCED') {
                $_having = ['type_category LIKE' => '%ADVANCED%'];
            } else if ($cat == 'IV THERAPY') {
                $_having = ['type_category LIKE' => '%THERAPY%'];
            }
        }

        if (get('sort','')) {
            $arr_sort = json_decode(get('sort'),true);
            if ($arr_sort[0]['property'] == "amount") {
                $_order = ['Payment.total' => $arr_sort[0]['direction']]; 
            } else if ($arr_sort[0]['property'] == "patient") {
                $_order = ['Patient.name' => $arr_sort[0]['direction']]; 
            } else if ($arr_sort[0]['property'] == "injector") {
                $_order = ['Injector.name' => $arr_sort[0]['direction']]; 
            } else if ($arr_sort[0]['property'] == "schedule_date") {
                $_order = ['DataTreatment.schedule_date' => $arr_sort[0]['direction']]; 
            }
        }

        $_join = [
            'Injector' => ['table' => 'sys_users','type' => 'LEFT','conditions' => 'Injector.id = DataTreatment.assistance_id'],
            'Patient' => ['table' => 'sys_users','type' => 'INNER','conditions' => 'Patient.id = DataTreatment.patient_id'],
            'Notes' => ['table' => 'data_treatment_notes','type' => 'LEFT','conditions' => 'Notes.treatment_id = DataTreatment.id'],
            'Review' => ['table' => 'data_treatment_reviews','type' => 'LEFT','conditions' => 'Review.treatment_id = DataTreatment.id'],
            'State' => ['table' => 'cat_states','type' => 'LEFT','conditions' => 'State.id = Patient.state'],
            'Payment' => ['table' => 'data_payment','type' => 'LEFT','conditions' => 'Payment.uid = DataTreatment.uid AND Payment.is_visible = 1 AND Payment.id_to = 0  AND Payment.type = "TREATMENT"'],
            'Doctor' => ['table' => 'sys_users_admin','type' => 'INNER','conditions' => 'Doctor.id = DataTreatment.assigned_doctor'],
            'GFE' => ['table' => 'data_consultation','type' => 'LEFT','conditions' => 'GFE.patient_id = DataTreatment.patient_id and GFE.status = "CERTIFICATE"'],
        ];
        
        $entity = $this->DataTreatment->find()->select($_fields)->where($where)
        ->join($_join)->order($_order)->limit($limit)->page($page)->having($_having)->all();

        $entity_total = $this->DataTreatment->find()->where($where)->join($_join)->count();

        if(!empty($entity)){
            foreach($entity as $row){ $this->log(__LINE__ . ' ' . json_encode($row));


                // Skipping other treatments that doesn't require medical supervision
                if (intval($row['total_treatments_required']) == 0) continue;
                //$cart_sql = $this->sql_part1.$row['id'].$this->sql_part2.$row['patient_id'];
                //$arr_certs = $this->cleanArrCert($this->DataTreatment->getConnection()->execute($cart_sql)->fetchAll('assoc'), $examiners);
                /*$arr_certs = $arr_certs = $this->DataCertificates->find()->select(['DataCertificates.uid', 'DC.status', 'examnier' => "CONCAT_WS(' ',User.name, User.mname , User.lname)"])
                ->join([
                    'DC' => ['table' => 'data_consultation','type' => 'INNER','conditions' => 'DC.id = DataCertificates.consultation_id'],
                    'User' => ['table' => 'sys_users','type' => 'LEFT','conditions' => 'User.id = DC.assistance_id']
                ])
                ->where(['DC.patient_id' => $row['patient_id'], 'NOW() < DataCertificates.date_expiration'])->first();*/
                $arr_certs = $this->DataCertificates->find()->select(['DataCertificates.uid', 'DataCertificates.certificate_url', 'DC.status', 'examnier' => "CONCAT_WS(' ',User.name, User.mname , User.lname)"])
                ->join([
                    'DC' => ['table' => 'data_consultation','type' => 'INNER','conditions' => 'DC.id = DataCertificates.consultation_id'],
                    'User' => ['table' => 'sys_users','type' => 'LEFT','conditions' => 'User.id = DC.assistance_id']
                ])
                ->where(['DC.patient_id' => $row['patient_id'], 'NOW() < DataCertificates.date_expiration'])->first();

                $imgsTr = $this->DataTreatmentImage->find()->select(['DataTreatmentImage.file_id'])->where(['DataTreatmentImage.treatment_id' => $row['id']])->toArray();

                $state_name = !empty($row['State']['name']) ? $row['State']['name'] : '';
                $str_address = $row['address'] . ', ' . $row['city'] . ', ' . $state_name . ' ' . $row['zip'];
                if (!empty($row['suite'])) {
                    $str_address = $row['address'] . ', ' . $row['suite'] . ', ' . $row['city'] . ', ' . $state_name . ' ' . $row['zip'];
                }
                $arr_ct = !empty($row->type_category) ? explode(",", $row->type_category) : "";
                $arr_ct = !empty($arr_ct) ? array_unique($arr_ct) : "";
                $arr_ct = !empty($arr_ct) ? implode(',', $arr_ct) : "";
                $arr_ct2 = !empty($row->type_category2) ? explode(",", $row->type_category2) : "";
                $arr_ct2 = !empty($arr_ct2) ? array_unique($arr_ct2) : "";
                $arr_ct2 = !empty($arr_ct2) ? implode(',', $arr_ct2) : "";

                $this->loadModel('DataAgreements');       
                // $this->log(__LINE__ . ' ' . json_encode(__LINE__));
                $ent = $this->DataAgreements->find()->select(['CatAgreement.agreement_title','CatAgreement.deleted','State.name','DataAgreements.created','CatAgreement.uid'])
                    ->join([
                        'CatAgreement' => ['table' => 'cat_agreements', 'type' => 'INNER', 'conditions' => 'CatAgreement.uid = DataAgreements.agreement_uid'],
                        'State' => ['table' => 'cat_states', 'type' => 'LEFT', 'conditions' => 'State.id = CatAgreement.state_id'],
                            ])
                    ->where([
                        'DataAgreements.user_id' => $row['patient_id'],
                        'DataAgreements.deleted' => 0,
                        'CatAgreement.id > ' => 24,
                        'CatAgreement.user_type = ' => 'PATIENT',
                        'CatAgreement.agreement_title in ("Patient Consent","IV Theraphy")',
                        ])
                ->all();

                $result = array();
                foreach ($ent as $row_ag) {
                    $result[] = array(
                        'state' => $row_ag['State']['name'],
                        'created' => $row_ag['created'],
                        'agreement_title' => $row_ag['CatAgreement']['agreement_title'],
                        'uid' => $row_ag['CatAgreement']['uid'],
                        'type' => 'agreement',
                        'patient_uid' => $row['Patient']['uid']
                    );
                    
                }
                $examiner ='';
                if(!empty($row['GFE']['id']) && !empty($row['GFE']['id'] > 19970)){// started qualiphy
                    if(!empty($row['GFE']['assitan'])){
                        $examiner = $row['GFE']['assitance'];
                    }
                }else if(!empty($row['GFE']['id']) && !empty($row['GFE']['id'] <= 19970)){
                    $examiner = $row['Examiner'];
                }
                $array_data[] = array(
                    'uid' => trim($row['uid']),
                    'assigned_doctor' => !empty($row['Doctor']['name']) ? $row['Doctor']['name'] : '-',
                    'patient' => $row['Patient']['name'] . ' ' . $row['Patient']['lname'],
                    'injector' => isset($row['Injector']['name']) ? $row['Injector']['name'] . ' ' . $row['Injector']['lname'] : '',
                    'injector_phone' => isset($row['Injector']['phone']) ? $row['Injector']['phone'] : '',
                    'injector_email' => isset($row['Injector']['email']) ? $row['Injector']['email'] : '',
                    'schedule_date' => $row->schedule_date->i18nFormat('MM/dd/yyyy HH:mm'),
                    'amount' => round($row['Payment']['total'] / 100,2),//$row['Payment']['total'] > 0 ? round($row['Payment']['total'] / 100,2) : $row['amount'],
                    'treatments' => str_replace(',', ', ', (isset($row['_treatments']) ? $row['_treatments'] : '') ),
                    'rtreatments' => $row['_rtreatments'],
                    'status' => $replace_array[$row['status']],
                    'notes' => $row['Notes']['notes'],
                    'address' => $str_address,
                    'payment' => empty($row['payment']) ? 0 : 1,
                    'promo_code' => $row['Payment']['promo_code'],
                    'rating' => empty($row['Review']['id']) ? "" : number_format($row['Review']['score'] / 10,1),
                    'approved' => $row['approved'],
                    'examiners' => $examiner,//$row['Examiner'],
                    'photos' => !empty($imgsTr) ? 1 : 0,
                    'doctor_notes' => empty($row['doctor_notes']) ? '' : $row['doctor_notes'],
                    'certificates' => empty($arr_certs) ? array() : array('uid' => $arr_certs['uid'], 'name' => $arr_certs['DC']['status'],'certificate_url' => $arr_certs['certificate_url'],),
                    'appr_date' => empty($row['appr_date']) ? '' : $row['appr_date'],
                    'files' =>  isset($imgsTr) ? Hash::extract($imgsTr, '{n}.file_id') : [],
                    'tc1' => $arr_ct,
                    'tc2' => $arr_ct2,
                    'type_category' => str_replace(',', ', ', (!empty($arr_ct) ? $arr_ct : $arr_ct2)),
                    'agrement' => $result,

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

}