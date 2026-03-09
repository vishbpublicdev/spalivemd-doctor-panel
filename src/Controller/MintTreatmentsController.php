<?php
declare(strict_types=1);

namespace App\Controller;

use Admin\Controller\AppController;
use Cake\Utility\Security;
use Cake\Utility\Text;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\View\Exception\MissingTemplateException;
use Cake\Utility\Hash;

class MintTreatmentsController extends AppController{

    public function initialize() : void{
        parent::initialize();

        $this->loadModel('Admin.SysUsers');
        $this->sql_part1 = "SELECT DC.uid, CTC.name, CONCAT_WS(' ', Exa.name, Exa.mname, Exa.lname) as examnier
            FROM cat_treatments_ci CTC
            JOIN data_consultation_plan DCP ON DCP.treatment_id = CTC.treatment_id
            JOIN data_certificates DC ON DC.consultation_id = DCP.consultation_id
            JOIN data_consultation DCO ON DCO.id = DC.consultation_id
            LEFT JOIN sys_users Exa ON Exa.id = DCO.assistance_id
            WHERE CTC.deleted = 0 
            AND CTC.id IN ('";
        $this->sql_part2 = "') AND DCP.proceed = 1 AND NOW() < DC.date_expiration AND DCO.patient_id = ";
    }

    public function grid()
    {
        $this->loadModel('Admin.DataTreatment');
        $this->loadModel('DataTreatmentImage');
        $this->loadModel('Admin.SysUsers');
        $this->loadModel('DataTreatmentDetail');
        $this->loadModel('DataPayment');
        $this->loadModel('DataCertificates');
        $this->loadModel('DataClaimTreatments');
        $this->loadModel('DataTreatmentNotes');
        $this->loadModel('DataTreatmentMint');

        $page = intval(get('page', 1));
        $limit = get('limit', 50);   
        $_order = ['DataTreatmentMint.created' => 'DESC']; 
        $user_id = USER_ID;
        //'INIT','DONE','CANCEL','CONFIRM','REJECT'
        $replace_array = array(
            'INIT' => '1-1 REQUEST',
            'REQUEST' => '1-1 REQUEST',
            'TEST' => 'REQUESTED',
            'DONE' => 'DONE',
            'CANCELED BY PATIENT OPEN' => 'CANCELED BY PATIENT OPEN',
            'CANCELED BY PATIENT' => 'CANCELED BY PATIENT',
            'OPEN APPT Rejected by Injector' => 'OPEN APPT Rejected by Injector',
            '1 to 1 Rejected by Injector' => '1 to 1 Rejected by Injector',
            'INVITATION' => '1-1 REQUEST',
            'Open Request (Expired)' => 'Open Request (Expired)',
            '1 to 1 APPT CONFIRMED' => '1 to 1 APPT CONFIRMED',
            'OPEN APPT CONFIRMED' => 'OPEN APPT CONFIRMED',
            'Open Request - PENDING CLAIM' => 'Open Request - PENDING CLAIM',
            'Open Request - CLAIMED (pending confirmation)' => 'Open Request - CLAIMED (pending confirmation)',
            'OPEN APPT DONE' => 'OPEN APPT DONE',
            '1-1 APPT DONE' => '1-1 APPT DONE',
            'Open Treatment - Waiting for payment' => 'Open Treatment - Waiting for payment',
            '1-1 - Waiting for payment' => '1-1 - Waiting for payment',
            'STOP' => 'STOP',
            'STOP OPEN' => 'STOP OPEN',
            'DRAFT' => 'DRAFT',
            'DRAFT OPEN' => 'DRAFT OPEN',
            'ALL OPEN REQUEST' => 'ALL OPEN REQUEST',
            'REJECTED' => 'REJECTED',
            'REJECTED OPEN' => 'REJECTED OPEN',
            'DONESELFTREATMENT' => 'DONESELFTREATMENT',
        );



        $array_data = [];
        $_fields = ['DataTreatmentMint.id','DataTreatmentMint.created','DataTreatmentMint.payment','DataTreatmentMint.treatments','DataTreatmentMint.tip','DataTreatmentMint.uid','Injector.uid','Injector.name','Injector.lname','Patient.name','Patient.lname',
                    'Payment.total','Payment.promo_code','DataTreatmentMint.amount','Patient.uid','DataTreatmentMint.schedule_date','DataTreatmentMint.status','Notes.notes','State.name','Review.score','Review.like','Review.id','Review.starts','Doctor.name', 
                    'DataTreatmentMint.approved', 'DataTreatmentMint.patient_id', 'DataTreatmentMint.notes','PaymentCancel.total','PaymentCancel.id_from','PaymentCancel.id_to'];
        $_fields['_treatments'] = "(SELECT GROUP_CONCAT(CONCAT_WS(' ',DTD.quantity,CT.name)) FROM data_treatment_detail DTD JOIN cat_treatments_ci CT ON CT.id = DTD.cat_treatment_id WHERE DTD.treatment_id = DataTreatmentMint.id AND DTD.quantity > 0)";
        $_fields['payed_from']  = "(CreatedBy.type)";
        $_fields['_rtreatments'] = "(SELECT GROUP_CONCAT(CONCAT(CTC.name,' (',CT.name, ')') SEPARATOR ', ') 
                                    FROM cat_treatments_ci CT 
                                    JOIN cat_treatments_category CTC ON CTC.id = CT.category_treatment_id 
                                    WHERE FIND_IN_SET(CT.id,DataTreatmentMint.treatments) LIMIT 1)";
        $_fields['doctor_notes'] = "(SELECT DN.notes FROM data_trtment_notes_doc DN WHERE DN.treatment_id = DataTreatmentMint.id AND DN.doctor_id = {$user_id} ORDER BY DN.id DESC LIMIT 1)";
        $_fields['appr_date'] = "(SELECT DN.created FROM data_trtment_notes_doc DN WHERE DN.treatment_id = DataTreatmentMint.id AND DN.doctor_id = {$user_id} ORDER BY DN.id DESC LIMIT 1)";
        $_fields['claims'] = "(SELECT COUNT(*) FROM data_claim_treatments DCT WHERE DCT.treatment_uid = DataTreatmentMint.uid AND DCT.deleted = 0)";
        $_fields['who_claimed'] = "(SELECT CONCAT(SU.name, ' ', SU.lname) name_claimed FROM data_claim_treatments DCT 
                                    INNER JOIN sys_users SU ON SU.id = DCT.injector_id
                                    WHERE DCT.treatment_uid = DataTreatmentMint.uid AND DCT.deleted = 0
                                    LIMIT 1)";
        $_fields['branch'] = "( SELECT CB.name
                                FROM data_branches DB
                                    INNER JOIN cat_branches CB ON CB.id = DB.branch_id
                                WHERE DB.user_id = DataTreatmentMint.assistance_id)";
        

        $_fields['purchase_after'] = "(SELECT COUNT(DatPur.id) FROM data_purchases DatPur WHERE DatPur.user_id = DataTreatmentMint.assistance_id AND DatPur.created BETWEEN DataTreatmentMint.created AND DataTreatmentMint.schedule_date  )";

        $where = ['DataTreatmentMint.deleted' => 0];
        
        $usr_uid = get('user_uid', '');
        if(!empty($usr_uid)){
            $u_ent = $this->SysUsers->find()->where(['SysUsers.uid' => $usr_uid])->first();
            if(!empty($u_ent)){
                $where['OR'] = ['Injector.id' => $u_ent->id, 'Patient.id' => $u_ent->id,];
            }
        }

        $usr_uid = get('patient_uid', '');
        if(!empty($usr_uid)){
            $u_ent = $this->SysUsers->find()->where(['SysUsers.uid' => $usr_uid])->first();
            if(!empty($u_ent)){
                $where['Patient.id'] = $u_ent->id;
            }
        }

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
                $where['OR'] = [['Injector.email LIKE' => "%$search%"], ['Patient.email LIKE' => "%$search%"], 
                    "MATCH(Injector.name,Injector.mname,Injector.lname) AGAINST ('{$matchValue}' IN BOOLEAN MODE)", 
                    "MATCH(Patient.name,Patient.mname,Patient.lname) AGAINST ('{$matchValue}' IN BOOLEAN MODE)"];
            } else if ($arr_filter[0]['property'] == "query") {
            }
        } 

        $_having = [];
        // $str_mfilter = get('mfilter','');
        $str_mfilter = get('mfilter',0);
        if ($str_mfilter > 0) {
            $where['BranchInjector.branch_id'] = $str_mfilter;
        }
        // if (!empty($str_mfilter)) {

        //     if ($str_mfilter == '1-1 REQUEST') {
        //         $where['OR'] = ['DataTreatmentMint.status' => 'INIT', 'DataTreatmentMint.status' => 'REQUEST', 'DataTreatmentMint.status' => 'INVITATION'];
        //     } else if ($str_mfilter == 'REQUESTED') {
        //         $where['DataTreatmentMint.status'] = 'TEST';
        //     } else if ($str_mfilter == 'DONE') {
        //         $where['DataTreatmentMint.status'] = 'DONE';
        //     } else if ($str_mfilter == 'CANCELED BY PATIENT') {
        //         $where['DataTreatmentMint.status'] = 'CANCEL';
        //     } else if ($str_mfilter == 'REJECTED') {
        //         $where['DataTreatmentMint.status'] = 'REJECT';
        //     } else if ($str_mfilter == '1to1 APPT CONFIRMED') {
        //         $where['DataTreatmentMint.status'] = 'CONFIRM';
        //         $where['DataTreatmentMint.type_uber'] = 0;
        //     } else if ($str_mfilter == 'OPEN APPT CONFIRMED') {
        //         $where['DataTreatmentMint.status'] = 'CONFIRM';
        //         $where['DataTreatmentMint.type_uber'] = 1;
        //     } else if ($str_mfilter == '1to1 APPT CONFIRMED') {
        //         $where['DataTreatmentMint.status'] = 'PETITION';
        //         $where['DataTreatmentMint.type_uber'] = 0;
        //     } else if ($str_mfilter == 'Open Request - CLAIMED (pending confirmation)') {
        //         $where['DataTreatmentMint.status'] = 'PETITION';
        //         $_having = ['claims >' => 0];
        //     } else if ($str_mfilter == 'Open Request - PENDING CLAIM') {
        //         $where['DataTreatmentMint.status'] = 'PETITION';
        //         $_having = ['claims' => 0];
        //     } else if ($str_mfilter == 'ALL OPEN REQUEST') {
        //         $where['DataTreatmentMint.type_uber'] = 1;
        //         $where['DataTreatmentMint.status NOT IN'] = array('STOP', 'REJECTED', 'REJECT', 'DRAFT');
        //     } else if ($str_mfilter == 'WAITING FOR ADMIN') {
        //         $where['DataTreatmentMint.status'] = 'STOP';
        //     } else if ($str_mfilter == 'REJECT') {
        //         $where['DataTreatmentMint.status'] = 'REJECTED';
        //     } else if ($str_mfilter == 'DRAFT') {
        //         $where['DataTreatmentMint.status'] = 'DRAFT';
        //     }
        // }



        // pr($where); exit;


        if (get('sort','')) {
            $arr_sort = json_decode(get('sort'),true);
            if ($arr_sort[0]['property'] == "amount") {
                $_order = ['Payment.total' => $arr_sort[0]['direction']]; 
            } else if ($arr_sort[0]['property'] == "patient") {
                $_order = ['Patient.name' => $arr_sort[0]['direction']]; 
            } else if ($arr_sort[0]['property'] == "injector") {
                $_order = ['Injector.name' => $arr_sort[0]['direction']]; 
            } else if ($arr_sort[0]['property'] == "schedule_date") {
                $_order = ['DataTreatmentMint.schedule_date' => $arr_sort[0]['direction']]; 
            } else if ($arr_sort[0]['property'] == "status") {
                if ($arr_sort[0]['direction'] == 'ASC')
                    $_order = "(CASE status WHEN 'CONFIRM' THEN 1 WHEN 'INIT' THEN 2 WHEN 'DONE' THEN 3 WHEN 'CANCEL' THEN 4 ELSE 5  END)";
                else
                    $_order = "(CASE status WHEN 'CONFIRM' THEN 5 WHEN 'INIT' THEN 4 WHEN 'DONE' THEN 3 WHEN 'CANCEL' THEN 2 ELSE 1  END)";

            }
        }

        $_join = [
            'Injector' => ['table' => 'sys_users','type' => 'LEFT','conditions' => 'Injector.id = DataTreatmentMint.assistance_id'],
            'Patient' => ['table' => 'sys_users','type' => 'INNER','conditions' => 'Patient.id = DataTreatmentMint.patient_id'],
            'Notes' => ['table' => 'data_treatment_notes','type' => 'LEFT','conditions' => 'Notes.treatment_id = DataTreatmentMint.id'],
            'Review' => ['table' => 'data_treatment_reviews','type' => 'LEFT','conditions' => 'Review.treatment_id = DataTreatmentMint.id'],
            'State' => ['table' => 'cat_states','type' => 'INNER','conditions' => 'State.id = Patient.state'],
            'Payment' => ['table' => 'data_payment','type' => 'LEFT','conditions' => 'Payment.uid = DataTreatmentMint.uid AND Payment.is_visible = 1 AND Payment.id_to = 0 AND Payment.type = "TREATMENT"'],
            'Doctor' => ['table' => 'sys_users_admin','type' => 'LEFT','conditions' => 'Doctor.id = DataTreatmentMint.assigned_doctor'],            
            'CreatedBy' => ['table' => 'sys_users', 'type' => 'LEFT', 'conditions' => 'CreatedBy.id = Payment.createdby'],            
            'PaymentCancel' => ['table' => 'data_payment','type' => 'LEFT','conditions' => 'PaymentCancel.uid = DataTreatmentMint.uid AND PaymentCancel.is_visible = 1 AND PaymentCancel.receipt <> "" AND PaymentCancel.type = "CANCEL_TREATMENT"'],
            'BranchInjector' => ['table' => 'data_branches','type' => 'LEFT','conditions' => 'BranchInjector.user_id = DataTreatmentMint.assistance_id'],
        ];
        
        $entity = $this->DataTreatmentMint->find()->select($_fields)->where($where)
        ->join($_join)->order($_order)->limit($limit)->page($page)->having($_having)->all();
        
        $entity_total = $this->DataTreatmentMint->find()->where($where)->join($_join)->count();

        if(!empty($entity)){
            foreach($entity as $row){
                if(!empty($row['payment'])){
                    $trDetails = $this->DataTreatmentDetail->find()->select(['DataTreatmentDetail.price','DataTreatmentDetail.quantity','DataTreatmentDetail.total', 'product_name' => 'CatTreat.name', 'product_detail' => 'CatTreat.details'])
                    ->join(['CatTreat' => ['table' => 'cat_treatments_ci','type' => 'INNER','conditions' => 'CatTreat.id = DataTreatmentDetail.cat_treatment_id']])
                    ->where(['DataTreatmentDetail.treatment_id' => $row->id])->toArray();

                    $trComissions = $this->DataPayment->find()->select(['name' => 'User.name', 'lname' => 'User.lname','DataPayment.total','DataPayment.id_from','DataPayment.comission_payed'])
                    ->join(['User' => ['table' => 'sys_users','type' => 'INNER','conditions' => 'User.id = DataPayment.id_to']])
                    ->where(['DataPayment.id_to <>' => 0,'DataPayment.uid' => $row->uid, 'DataPayment.is_visible' => 1])->toArray();

                } else { $trDetails = []; $trComissions = []; }

                $cart_sql = $this->sql_part1.$row['treatments'].$this->sql_part2.$row['patient_id'];
                //$arr_certs = $this->cleanArrCert($this->DataTreatment->getConnection()->execute($cart_sql)->fetchAll('assoc'), $examiners);
                $arr_certs = $this->DataCertificates->find()->select(['DataCertificates.uid', 'DC.status', 'examnier' => "CONCAT_WS(' ',User.name, User.mname , User.lname)"])
                ->join([
                    'DC' => ['table' => 'data_consultation','type' => 'INNER','conditions' => 'DC.id = DataCertificates.consultation_id'],
                    'User' => ['table' => 'sys_users','type' => 'LEFT','conditions' => 'User.id = DC.assistance_id']
                ])
                ->where(['DC.patient_id' => $row['patient_id'], 'NOW() < DataCertificates.date_expiration'])->first();
                
                $imgsTr = $this->DataTreatmentImage->find()->select(['DataTreatmentImage.file_id','DataTreatmentImage.typeImage'])->where(['DataTreatmentImage.treatment_id' => $row['id'] , 'DataTreatmentImage.typeImage'=>'before'])->toArray();
                $imgsTrAfter = $this->DataTreatmentImage->find()->select(['DataTreatmentImage.file_id','DataTreatmentImage.typeImage'])->where(['DataTreatmentImage.treatment_id' => $row['id'], 'DataTreatmentImage.typeImage'=>'after'])->toArray();

                
                if($row['status'] == 'CONFIRM'){
                    $row['status'] = $row['type_uber'] == 1 ? 'OPEN APPT CONFIRMED' : '1 to 1 APPT CONFIRMED';
                } else if ($row['status'] == 'PETITION') {
                    $ent_claim = $this->DataClaimTreatments->find()->where(['DataClaimTreatments.treatment_uid' => $row['uid'], 'DataClaimTreatments.deleted' => 0])->count();
                    $row['status'] = $ent_claim > 0 ? 'Open Request - CLAIMED (pending confirmation)' : 'Open Request - PENDING CLAIM';
                    $now = date('Y-m-d H:i:s');
                    if( ($now > date('Y-m-d H:i:s', strtotime($row['created']->i18nFormat('yyyy-MM-dd HH:mm:ss','America/Chicago') . ' + 2 day'))) || $now > date('Y-m-d H:i:s', strtotime($row['schedule_date']->i18nFormat('yyyy-MM-dd HH:mm:ss','America/Chicago'))) ) {
                        $row['status'] = 'Open Request (Expired)';
                    }
                } else if($row['status'] == 'REJECT') {
                    $row['status'] = $row['type_uber'] == 1 ? 'OPEN APPT Rejected by Injector' : '1 to 1 Rejected by Injector';
                } else if($row['status'] == 'DONE') {
                    if (!empty($row['payment'])) {
                        $row['status'] = $row['type_uber'] == 1 ? 'OPEN APPT DONE' : '1-1 APPT DONE';
                    } else {
                        $row['status'] = $row['type_uber'] == 1 ? 'Open Treatment - Waiting for payment' : '1-1 - Waiting for payment';
                    }
                } else if($row['status'] == 'CANCEL'){
                    $row['status'] = $row['type_uber'] == 1 ? 'CANCELED BY PATIENT OPEN' : 'CANCELED BY PATIENT';
                } else if($row['status'] == 'STOP'){
                    $row['status'] = $row['type_uber'] == 1 ? 'STOP OPEN' : 'STOP';
                } else if($row['status'] == 'REJECTED'){
                    $row['status'] = $row['type_uber'] == 1 ? 'REJECTED OPEN' : 'REJECTED';
                } else if($row['status'] == 'DRAFT'){
                    $row['status'] = $row['type_uber'] == 1 ? 'DRAFT OPEN' : 'DRAFT';
                }
                
                $injector_name = isset($row['Injector']['name']) ? $row['Injector']['name'] . ' ' . $row['Injector']['lname'] : '';
                if($row['status'] == 'Open Request - CLAIMED (pending confirmation)' || $row['status'] == 'Open Request (Expired)'){
                    $injector_name = isset($row['who_claimed']) ? '('.$row['who_claimed'].')' : '';
                }    
                
                $begin_after_pictures = date('Y-m-d', strtotime($row->schedule_date->i18nFormat('yyyy-MM-dd') . ' + 14 days'));
                $end_after_pictures = date('Y-m-d', strtotime($row->schedule_date->i18nFormat('yyyy-MM-dd') . ' + 21 days'));
                $show_add_after_pictures = (date('Y-m-d') >= $begin_after_pictures && date('Y-m-d') <= $end_after_pictures) ? true : false;
                
                // $this->loadModel('SpaLiveV1.DataTreatmentImage');
                // $images_after = $this->DataTreatmentImage->find()->where(['DataTreatmentImage.treatment_id' => $row['id'], 'DataTreatmentImage.typeImage' => 'after'])->toArray();
                
                // $show_add_after_pictures = $show_add_after_pictures && count($images_after) == 0 ? true : false;

                $array_data[] = array(
                    'uid' => trim($row['uid']),
                    'patient_notes' => trim($row['notes']) == '' ? 'NONE' : trim($row['notes']),
                    'assigned_doctor' => isset($row['Doctor']['name']) ? $row['Doctor']['name'] : '-',
                    'patient' => $row['Patient']['name'] . ' ' . $row['Patient']['lname'],
                    'injector' => $injector_name, // isset($row['Injector']['name']) ? $row['Injector']['name'] . ' ' . $row['Injector']['lname'] : '',
                    'injector_uid' => isset($row['Injector']['uid']) ? $row['Injector']['uid'] : '',
                    'patient_uid' => isset($row['Patient']['uid']) ? $row['Patient']['uid'] : '',
                    'schedule_date' => $row->schedule_date->i18nFormat('yyyy-MM-dd HH:mm:ss','America/Chicago'),
                    'amount' => round($row['Payment']['total'] / 100,2),//$row['Payment']['total'] > 0 ? round($row['Payment']['total'] / 100,2) : $row['amount'],
                    'tip' => number_format(round($row['tip'] / 100,2),2),
                    'treatments' => str_replace(',', ', ', (isset($row['_treatments']) ? $row['_treatments'] : $row['_rtreatments']) ),
                    'rtreatments' => $row['_rtreatments'],
                    'created' => $row['created']->i18nFormat('yyyy-MM-dd HH:mm:ss','America/Chicago'),
                    'status' => isset($replace_array[$row['status']]) ? $replace_array[$row['status']] : '',
                    'notes' => empty($row['Notes']['notes'])  ? 'PENDING' : trim($row['Notes']['notes']),                    
                    'payment' => empty($row['payment']) ? 0 : 1,
                    'promo_code' => $row['Payment']['promo_code'],
                    'rating' => empty($row['Review']['id']) ? "" : number_format($row['Review']['score'] / 10,1),
                    'approved' => $row['status'] == 'OPEN APPT DONE' || $row['status'] == '1-1 APPT DONE' ? $row['approved'] : '',
                    'type_uber' => $row['type_uber'] ? $row['type_uber'] : false,
                    'examiners' =>empty($arr_certs) ? '' : $arr_certs['examnier'],
                    'payed_from' => $row['payed_from'],
                    'photos' => (!empty($imgsTr) || !empty($imgsTrAfter))? 1 : 0,
                    'doctor_notes' => empty($row['doctor_notes']) ? 'PENDING' : $row['doctor_notes'],
                    'certificates' => empty($arr_certs) ? array() : array('uid' => $arr_certs['uid'], 'name' => $arr_certs['DC']['status']),
                    'appr_date' => empty($row['appr_date']) ? '' : $row['appr_date']->i18nFormat('yyyy-MM-dd HH:mm:ss','America/Chicago'),
                    'files' =>  isset($imgsTr) ? Hash::extract($imgsTr, '{n}.file_id') : [],
                    'filesAfter' =>  isset($imgsTrAfter) ? Hash::extract($imgsTrAfter, '{n}.file_id') : [],////
                    'purchase_after' =>  intval($row->purchase_after),
                    'details' => $trDetails,
                    'comissions' => $trComissions,
                    'like' => empty($row['Review']['like']) ? "NOTVALUED" : $row['Review']['like'],                    
                    'normal_status' => $row['status'],
                    'who_claimed' => isset($row['who_claimed']) ? $row['who_claimed'] : '',
                    'starts' => empty($row['Review']['id']) ? "NOTVALUED" : (empty($row['Review']['starts']) || ($row['Review']['starts']) ==0 ? (empty($row['Review']['like']) ? "NOTVALUED" : $row['Review']['like']) : ($row['Review']['starts'])),
                    'feeCancel' => isset($row['PaymentCancel']['total']) ?  number_format(round(($row['PaymentCancel']['total']*.95) / 100,2),2) : '',
                    'show_add_after_pictures' => $show_add_after_pictures,
                    'begin_after_pictures' => $begin_after_pictures,
                    'end_after_pictures' => $end_after_pictures,
                    'branch' => $row['branch'],
                );
            }
        }

        $this->Response->success();
        $this->Response->set('data', $array_data);
        $this->Response->set('total', $entity_total);
        $this->Response->set('summary', $this->getSummaryByStatus());
    }

    public function catDoctors() {
        $this->loadModel('Admin.SysUsersAdmin');
        $ent_doc = $this->SysUsersAdmin->find()->where(['SysUsersAdmin.user_type' => 'DOCTOR','SysUsersAdmin.deleted' => 0])->all();

        if(!empty($ent_doc)){
            $result = array();
            foreach ($ent_doc as $row) {
                $result[] = array(
                    'id' => $row['id'],
                    'name' => $row['name'],
                );
            }
        }

        $this->Response->success();
        $this->Response->set('data', $result);

    }

    public function updateDoctor() {

        $this->loadModel('Admin.DataTreatment');
        $ent_doc = $this->DataTreatment->find()->where(['DataTreatment.uid' => get('uid','')])->first();
        if(!empty($ent_doc)) {

            $a_doctor = get('doctor',0);
            if ($a_doctor > 0) {
                $ent_doc->assigned_doctor = $a_doctor;
                $this->DataTreatment->save($ent_doc);
                $this->Response->success();
            }
        }        


    }

    public function catExams()
    {
        $this->loadModel('Admin.CatTreatments');


        $ent_exams = $this->CatTreatments->find()->where(['CatTreatments.parent_id >' => 0,'CatTreatments.deleted' => 0])->order(['CatTreatments.name' => 'ASC'])->all();
        if(!empty($ent_exams)){
            $result = array();
            foreach ($ent_exams as $row) {
                $result[] = array(
                    'id' => $row['id'],
                    'name' => $row['name'],
                );
                
            }
        }

        $this->Response->success();
        $this->Response->set('data', $result);


    }

    public function catProducts()
    {
        $this->loadModel('Admin.CatProducts');


        $ent_exams = $this->CatProducts->find()->where(['CatProducts.deleted' => 0])->order(['CatProducts.name' => 'ASC'])->all();
        if(!empty($ent_exams)){
            $result = array();
            foreach ($ent_exams as $row) {
                $result[] = array(
                    'id' => $row['id'],
                    'name' => $row['name'],
                );
                
            }
        }

        $this->Response->success();
        $this->Response->set('data', $result);


    }

    public function gridTreatments()
    {
        $this->loadModel('Admin.CatTreatments');
        // $this->loadModel('Admin.CatCITreatment');


        $str_query = "
                SELECT CTC.*,CT.name exam,CTCAT.name category FROM cat_treatments_ci CTC
                JOIN cat_treatments CT ON CT.id = CTC.treatment_id
                JOIN cat_treatments_category CTCAT ON CTCAT.id = CTC.category_treatment_id
                WHERE CTC.deleted = 0
                   " ;

        $ent_exams = $this->CatTreatments->getConnection()->execute($str_query)->fetchAll('assoc');

        if(!empty($ent_exams)){
            $result = array();
            foreach ($ent_exams as $row) {
                $result[] = array(
                    'ct_id' => $row['id'],
                    'treatment' => $row['name'],
                    'details' => $row['details'],
                    'treatment_id' => $row['treatment_id'],
                    'product_id' => $row['product_id'],
                    'exam' => $row['exam'],
                    'qty' => $row['qty'],
                    'min' => $row['min'] / 100,
                    'max' => $row['max'] / 100,
                    'std_price' => $row['std_price'] / 100,
                    'category' => $row['category'],
                );
                 
            }
        }


        $this->Response->success();
        $this->Response->set('data', $result);


    }

    public function save() {

        $id = get('ct_id',0);
        $this->loadModel('Admin.CatTreatmentsCi');
        if ($id > 0) {
            $ent_treatment = $this->CatTreatmentsCi->find()->where(['CatTreatmentsCi.id' => $id,'CatTreatmentsCi.deleted' => 0])->first();
            
            $int_max = get('max',1) * 100;
            $int_min = get('min',1) * 100;
            $std_price = get('std_price',1) * 100;

            $ent_treatment->name = get('treatment','');
            $ent_treatment->treatment_id = get('treatment_id',0);
            $ent_treatment->max = $int_max;
            $ent_treatment->min = $int_min;
            $ent_treatment->qty = 1;
            $ent_treatment->product_id = get('product_id',0);
            $ent_treatment->details = get('details','');
            $ent_treatment->std_price = $std_price;

            $sql = "
                UPDATE data_treatments_prices SET price = {$int_min}
                WHERE treatment_id = {$id} AND price < {$int_min}
            ";
            
            $this->SysUsers->getConnection()->execute($sql);

            $sql = "
                UPDATE data_treatments_prices SET price = {$int_max}
                WHERE treatment_id = {$id} AND price > {$int_max}
            ";
            
            $this->SysUsers->getConnection()->execute($sql);

        
            
            $this->CatTreatmentsCi->save($ent_treatment);
            if(!$ent_treatment->hasErrors()){
                $this->Response->success();
            }else{
                $this->Response->add_errors('Internal Error.');
            }
        } else {
            $s_array = array(
                'name' => get('treatment',''),
                'treatment_id' => get('treatment_id',''),
                'product_id' => get('product_id',''),
                'max' => get('max',0) * 100,
                'min' => get('min',0) * 100,
                'qty' => 1,
                'details' => get('details',''),
                'created' => date('Y-m-d H:i:s'),
                'std_price' => get('std_price',''),
            );

            $c_entity = $this->CatTreatmentsCi->newEntity($s_array);
            if(!$c_entity->hasErrors()) {
                if ($this->CatTreatmentsCi->save($c_entity)) {
                    $this->Response->success();
                }
            }
        }
    }

    public function delete() {
         $id = get('ct_id',0);
        $this->loadModel('Admin.CatTreatmentsCi');
        if ($id > 0) {
            $ent_treatment = $this->CatTreatmentsCi->find()->where(['CatTreatmentsCi.id' => $id,'CatTreatmentsCi.deleted' => 0])->first();
            
            if (!empty($ent_treatment)) {
                $ent_treatment->deleted = 1;
                
                $this->CatTreatmentsCi->save($ent_treatment);
                if(!$ent_treatment->hasErrors()){
                    $this->Response->success();
                }else{
                    $this->Response->add_errors('Internal Error.');
                }
            }
        } 
    }

    public function deletet() {        
        $this->loadModel('DataTreatmentMint');
        if (!empty($uid)) {
            $ent_treatment = $this->DataTreatmentMint->find()->where(['DataTreatmentMint.uid' => $uid])->first();
            
            if (!empty($ent_treatment)) {
                $ent_treatment->deleted = 1;
                
                $this->DataTreatmentMint->save($ent_treatment);
                if(!$ent_treatment->hasErrors()){
                    $this->Response->success();
                }else{
                    $this->Response->add_errors('Internal Error.');
                }
            }
        } 
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

    private function getSummaryByStatus(){
        $join = "
            INNER JOIN sys_users User ON U.patient_id = User.id 
            INNER JOIN cat_states State ON U.state = State.id";
        $sql = "
            SELECT 
                (SELECT COUNT(U.id) FROM data_treatment U ".$join." WHERE U.status <> 'Cancel' AND U.deleted = 0) as total,
                (SELECT COUNT(U.id) FROM data_treatment U ".$join." WHERE U.status <> 'Cancel' AND U.status = 'INIT' AND U.deleted = 0) as total_init,
                (SELECT COUNT(U.id) FROM data_treatment U ".$join." WHERE U.status <> 'Cancel' AND U.status = 'DONE' AND U.deleted = 0) as total_done,
                (SELECT COUNT(U.id) FROM data_treatment U ".$join." WHERE U.status <> 'Cancel' AND U.status = 'CONFIRM' AND U.deleted = 0) as total_confirm,
                (SELECT COUNT(U.id) FROM data_treatment U ".$join." WHERE U.status <> 'Cancel' AND U.status = 'REJECT' AND U.deleted = 0) as total_rejc,
                (SELECT COUNT(U.id) FROM data_treatment U ".$join." WHERE U.status <> 'Cancel' AND U.status = 'CANCEL' AND U.deleted = 0) as total_cancel
        ";

        return $this->SysUsers->getConnection()->execute($sql)->fetchAll('assoc')[0];
    }

    public function loadTreatment() {

        $uid = get('uid','');
        $this->loadModel('Admin.DataTreatment');
        $this->loadModel('Admin.SysUsers');

        $arr_trainings = array(
            'LEVEL 1' => 'NEUROTOXINS BASIC',
            'NEUROTOXINS BASIC' => 'NEUROTOXINS BASIC',
            'LEVEL 2' => 'NEUROTOXINS ADVANCED',
            'NEUROTOXINS ADVANCED' => 'NEUROTOXINS ADVANCED',
        );


        if (!empty($uid)) {
            $__fields = ['User.name','User.lname','DataTreatment.uid','DataTreatment.latitude','DataTreatment.longitude','DataTreatment.treatments','DataTreatment.assistance_id'];
            $__fields['cats'] = '(SELECT GROUP_CONCAT( DISTINCT CTC.type_uber) FROM cat_treatments_ci CT JOIN cat_treatments_category CTC ON CTC.id = CT.category_treatment_id WHERE FIND_IN_SET(CT.id,DataTreatment.treatments))';
            $ent_treatment = $this->DataTreatment->find()->select($__fields)
            ->join(['User' => ['table' => 'sys_users','type' => 'INNER','conditions' => 'User.id = DataTreatment.patient_id','DataTreatment.treatments']])
            ->where(['DataTreatment.uid' => $uid])->first();
            
            if (!empty($ent_treatment)) {

                $data_treatment = array(
                    'name' => $ent_treatment['User']['name'],
                    'lname' => $ent_treatment['User']['lname'],
                    'uid' => $ent_treatment['uid'],
                    'latitude' => $ent_treatment['latitude'],
                    'longitude' => $ent_treatment['longitude'],
                    'cats' => $ent_treatment['cats'],
                    'assistance_id' => $ent_treatment['assistance_id'],
                    'type' => 'Patient',
                );



                $lat = $ent_treatment->latitude;
                $lon = $ent_treatment->longitude;

                $fields = ['SysUsers.name','SysUsers.lname','SysUsers.latitude','SysUsers.longitude','SysUsers.radius','SysUsers.id','SysUsers.uid','SysUsers.gender','SysUsers.description'];
                $fields['trainings'] = '(SELECT GROUP_CONCAT( DISTINCT CT.level) FROM data_trainings  DT JOIN cat_trainings CT ON CT.id = DT.training_id WHERE DT.user_id = SysUsers.id)';
                $fields['claim'] = '(SELECT COUNT(id) FROM data_claim_treatments DCT WHERE DCT.injector_id = SysUsers.id AND DCT.deleted = 0 AND DCT.treatment_uid = "' . $ent_treatment['uid'] . '")';
                $fields['likes'] = "(SELECT Count(DTR.id) FROM data_treatment_reviews DTR WHERE DTR.injector_id = SysUsers.id AND DTR.like = 'LIKE')";
                $fields['distance_in_mi'] = "(69.09 * DEGREES(ACOS(LEAST(1.0, COS(RADIANS(" . $lat . "))
                                         * COS(RADIANS(SysUsers.latitude))
                                         * COS(RADIANS(" . $lon . " - SysUsers.longitude))
                                         + SIN(RADIANS(" . $lat . "))
                                         * SIN(RADIANS(SysUsers.latitude))))))";
                $where = ['SysUsers.deleted' => 0, 'SysUsers.active' => 1,'SysUsers.type IN' => array('injector','gfe+ci'),'SysUsers.steps' => 'HOME'];
                // $where['OR'] = ['SysUsers.type' => 'injector', 'SysUsers.type' => 'gfe+ci'];
                $_having = ['distance_in_mi <' => 100];
                $entUsers = $this->SysUsers->find()->select($fields)->where($where)->having($_having)->toArray();

                $data_users = array();


                foreach ($entUsers as $user) {

                    if (empty($user['trainings'])) continue;
                    if (strpos($data_treatment['cats'], 'ADVANCED') !== false && strpos($user['trainings'], 'LEVEL 2') == false) continue;
                    if ($user['distance_in_mi'] > $user['radius']) continue;

                    $this->loadModel('SysLicence');
                    $licenseFields = ['SysLicence.id','SysLicence.type', 'SysLicence.number','state.name'];                
                    $licenceItem = $this->SysLicence->find()->select($licenseFields)
                    ->join([
                        'state' => ['table' => 'cat_states', 'type' => 'INNER', 'conditions' => 'state.id = SysLicence.state'],
                        ])
                        ->where(['SysLicence.user_id' => $user['id'], 'SysLicence.deleted' => 0])->group(['SysLicence.number'])->all();
                    

                    $this->loadModel('DataTrainings');
                    $trainingsFields = ['DataTrainings.id','DataTrainings.training_id', 'training_data.title', 'training_data.scheduled', 'training_data.level'];
                    $trainingsInstance = $this->DataTrainings->find()->select($trainingsFields)
                        ->join([
                            'training_data' => ['table' => 'cat_trainings', 'type' => 'INNER', 'conditions' => 'training_data.id = DataTrainings.training_id'],
                        ])
                        ->where(['DataTrainings.user_id' => $user['id'], 'DataTrainings.deleted' => 0])->order(['training_data.level' => 'ASC'])->all();

                    $trainings_user = $this->get_courses_user($user['id']);

                    // echo 'Distance: ' . $user['distance_in_mi'] . ' - Radius: ' . $user['radius'] . '<br>';
                    //Colocale en el título que significa cada color. "Red: available; Yellow: Claimed it (unconfirmed); Blue: Confirmed claim"
                    $color = 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png';
                    if (intval($user['claim']) > 0) $color = 'https://maps.google.com/mapfiles/ms/icons/yellow-dot.png';
                    if ($data_treatment['assistance_id'] == $user['id']) $color = 'https://maps.google.com/mapfiles/ms/icons/green-dot.png';
                    $data_users[] = array(
                        'name' => $user['name'],
                        'lname' => $user['lname'],
                        'gender' => $user['gender'],
                        'likes' => $user['likes'],
                        'description' => $user['description'],
                        'latitude' => $user['latitude'],
                        'longitude' => $user['longitude'],
                        'radius' => $user['radius'],
                        'claim' => intval($user['claim']),
                        'distance_in_mi' => $user['distance_in_mi'],
                        'type' => 'Injector',
                        'icon' => $color,
                        'licenses' => $licenceItem,
                        'trainings' => $trainingsInstance,
                        'treatmets_provided' => $trainings_user['has_advanced_course'] ? 'Neurotoxins (Basic), Neurotoxins (Advanced)' : 'Neurotoxins (Basic)',
                    );
                }


                $this->Response->set('count', count($data_users));


                $this->Response->success();
                $this->Response->set('data', $data_treatment);
                $this->Response->set('injectors', $data_users);
            }
        } 

    }

    public function openRequest() {

        $this->loadModel('Admin.DataTreatment');
        $this->loadModel('Admin.SysUsers');

        $arr_trainings = array(
            'LEVEL 1' => 'NEUROTOXINS BASIC',
            'NEUROTOXINS BASIC' => 'NEUROTOXINS BASIC',
            'LEVEL 2' => 'NEUROTOXINS ADVANCED',
            'NEUROTOXINS ADVANCED' => 'NEUROTOXINS ADVANCED',
        );

        $last_day = date('Y-m-d');
        $first_day = date('Y-m-d', strtotime('-14 days'));

        $__fields = ['User.name','User.lname','DataTreatment.uid','DataTreatment.latitude','DataTreatment.longitude','DataTreatment.treatments','DataTreatment.assistance_id'];
        $__fields['cats'] = '(SELECT GROUP_CONCAT( DISTINCT CTC.type_uber) FROM cat_treatments_ci CT JOIN cat_treatments_category CTC ON CTC.id = CT.category_treatment_id WHERE FIND_IN_SET(CT.id,DataTreatment.treatments))';
        $ent_treatment = $this->DataTreatment->find()->select($__fields)
        ->join(['User' => ['table' => 'sys_users','type' => 'INNER','conditions' => 'User.id = DataTreatment.patient_id','DataTreatment.treatments']])
        ->where(['DataTreatment.deleted' => 0, 'DataTreatment.type_uber' => 1, "(DataTreatment.created BETWEEN '{$first_day}' AND '{$last_day}')"])
        ->all();
        
        $data_treatments = array();
        if (Count($ent_treatment) > 0) {

            $data_treatments = array();

            foreach ($ent_treatment as $key => $value) {
                $color = 'https://maps.google.com/mapfiles/ms/icons/green-dot.png';
                $data_treatment = array(
                    'name' => $value['User']['name'],
                    'lname' => $value['User']['lname'],
                    'uid' => $value['uid'],
                    'latitude' => $value['latitude'],
                    'longitude' => $value['longitude'],
                    'cats' => $value['cats'],
                    'assistance_id' => $value['assistance_id'],
                    'type' => 'Patient',
                    'icon' => $color,
                );
                
                $data_treatments[] = $data_treatment;
            }
        }
            $fields = ['SysUsers.name','SysUsers.lname','SysUsers.latitude','SysUsers.longitude','SysUsers.radius','SysUsers.id','SysUsers.uid','SysUsers.gender','SysUsers.description'];
            $fields['trainings'] = '(SELECT GROUP_CONCAT( DISTINCT CT.level) FROM data_trainings  DT JOIN cat_trainings CT ON CT.id = DT.training_id WHERE DT.user_id = SysUsers.id)';
            $fields['likes'] = "(SELECT Count(DTR.id) FROM data_treatment_reviews DTR WHERE DTR.injector_id = SysUsers.id AND DTR.like = 'LIKE')";

            $where = ['SysUsers.deleted' => 0, 'SysUsers.state' => 43, 'SysUsers.active' => 1,'SysUsers.type IN' => array('injector','gfe+ci'),'SysUsers.steps' => 'HOME'];

            $entUsers = $this->SysUsers->find()->select($fields)->where($where)->toArray();

            $data_users = array();

            foreach ($entUsers as $user) {
                
                //$courses = $this->preview_provider_profile($user['uid']);    
                
                if (empty($user['trainings'])) continue;
                $value2 = in_array($user['id'], array_column($data_users, 'id'));

                if($value2){
                    continue;
                } 

                $this->loadModel('SysLicence');
                $licenseFields = ['SysLicence.id','SysLicence.type', 'SysLicence.number','state.name'];                
                $licenceItem = $this->SysLicence->find()->select($licenseFields)
                ->join([
                    'state' => ['table' => 'cat_states', 'type' => 'INNER', 'conditions' => 'state.id = SysLicence.state'],
                    ])
                    ->where(['SysLicence.user_id' => $user['id'], 'SysLicence.deleted' => 0])->group(['SysLicence.number'])->all();
                

                $this->loadModel('DataTrainings');
                $trainingsFields = ['DataTrainings.id','DataTrainings.training_id', 'training_data.title', 'training_data.scheduled', 'training_data.level'];
                $trainingsInstance = $this->DataTrainings->find()->select($trainingsFields)
                    ->join([
                        'training_data' => ['table' => 'cat_trainings', 'type' => 'INNER', 'conditions' => 'training_data.id = DataTrainings.training_id'],
                    ])
                    ->where(['DataTrainings.user_id' => $user['id'], 'DataTrainings.deleted' => 0])->order(['training_data.level' => 'ASC'])->all();

                $trainings_user = $this->get_courses_user($user['id']);
                
                $color = 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png';
                $data_users[] = array(
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'lname' => $user['lname'],
                    'gender' => $user['gender'],
                    'likes' => $user['likes'],
                    'description' => $user['description'],
                    'latitude' => $user['latitude'],
                    'longitude' => $user['longitude'],
                    'radius' => $user['radius'],
                    'claim' => intval($user['claim']),
                    'distance_in_mi' => $user['distance_in_mi'],
                    'type' => 'Injector',
                    'icon' => $color,
                    'licenses' => $licenceItem,
                    'trainings' => $trainingsInstance,
                    'treatmets_provided' => $trainings_user['has_advanced_course'] ? 'Neurotoxins (Basic), Neurotoxins (Advanced)' : 'Neurotoxins (Basic)',
                );  //print_r($data_users); exit;
            }
            
            //$this->Response->set('count', count($data_users));

            $this->Response->success();
            $this->Response->set('data', $data_treatments);
            $this->Response->set('injectors', $data_users);
        //}

    }

    public function get_courses_user($user_id){
        
		$this->loadModel('SpaLiveV1.DataTrainings');
        $this->loadModel('SpaLiveV1.CatTrainings');

        $fields = [
            'CatTrainings.id',
            'CatTrainings.title',
            'CatTrainings.scheduled',
            'CatTrainings.address',
            'CatTrainings.city',
            'CatTrainings.state_id',
            'CatTrainings.zip',
            'CatTrainings.level',    
            'CatTrainings.created',

            'attended' => 'DataTrainings.attended'
        ];
        $courses = $this->CatTrainings->find('all', [
            'conditions' => [
                'DataTrainings.user_id' => $user_id
            ],            
        ])
        ->select($fields)
        ->join([
            'table' => 'data_trainings',
            'alias' => 'DataTrainings',
            'type' => 'INNER',
            'conditions' => 'CatTrainings.id = DataTrainings.training_id'
        ])
        ->where([
            'DataTrainings.deleted' => 0,
            'CatTrainings.deleted' => 0,            
        ])
        ->toArray();

        
        $user_courses = array();
        $level_type = array_unique(array_column($courses, 'level'));
        
        $now = date('Y-m-d H:i:s');
        foreach ($level_type as $level) {
            $filteredCourses = array_filter($courses, function($course) use ($level) {
                return $course['level'] == $level;
            });                        
            foreach ($filteredCourses as $course) {
                $c_date = date('Y-m-d',strtotime('2023-02-27'));
                $isBeforeChange = $c_date > $course->created->i18nFormat('yyyy-MM-dd');
                if($isBeforeChange){
                    $course['status'] = $course['scheduled']->i18nFormat('yyyy-MM-dd HH:mm:ss') < $now ? 'DONE' : 'PENDING';
                }else {
                    $course['status'] = $course['attended'] == 1 ? 'DONE' : 'PENDING';
                }
            }
            $user_courses[$level] = $filteredCourses;
        }

        $has_basic_course = false;
        $level = 'LEVEL 1'; 
        if(array_key_exists($level, $user_courses)){
            $status = 'DONE';
            $has_basic_course = count(array_filter($user_courses[$level], function($course) use ($status) {
                return $course['status'] == $status;
            })) > 0;
        }

        $has_advanced_course = false;
        $level = 'LEVEL 2'; 
        if(array_key_exists($level, $user_courses)){
            $status = 'DONE';
            $has_advanced_course = count(array_filter($user_courses[$level], function($course) use ($status) {
                return $course['status'] == $status;
            })) > 0;
        }

        $courses_profile = array();
        foreach ($courses as $course) {
            $c_date = date('Y-m-d',strtotime('2023-02-27'));
            $isBeforeChange = $c_date > $course->created->i18nFormat('yyyy-MM-dd');
            if($isBeforeChange){
                $course['status'] = $course['scheduled']->i18nFormat('yyyy-MM-dd HH:mm:ss') < $now ? 'DONE' : 'PENDING';
            }else {
                $course['status'] = $course['attended'] == 1 ? 'DONE' : 'PENDING';
            }
            if($course['status'] == 'DONE'){
                $courses_profile[] = $course;
            }
        }

        $data = array(
            'has_basic_course'    =>  $has_basic_course,
            'has_advanced_course' =>  $has_advanced_course,
            'courses'             =>  $user_courses,
            'courses_profile'     =>  $courses_profile
        );

        return $data;
    }


    public function loadNotes(){

        $html = '';

        $this->loadModel('DataTreatmentMint');
        $treatment_id = $this->DataTreatmentMint->find()
        ->where(['DataTreatmentMint.uid' => get('id','')])->first();
        $this->loadModel('DataTreatmentNotes');
        $notes = $this->DataTreatmentNotes->find()
        ->where(['DataTreatmentNotes.treatment_id' => $treatment_id->id])->first();
        
        $this->log(__LINE__ . " " . $notes);
        if (!empty($notes)) {

            $result = array(
                'id' => $notes->treatment_id,
                'notes' => $notes->notes,
            );

            $this->Response->success();
            $this->Response->set('data', $result);
        }
    }

    public function saveNotes(){

        $notes = get('notes','');
        $uid = get('uid','');
        $this->loadModel('DataTreatmentMint');
        $this->loadModel('DataTreatmentNotes');

        $dt  = $this->DataTreatmentMint->find()->select(['id'])
                ->where(['uid'=> $uid])->first();
        $this->log(__LINE__ . " " .json_encode($dt));

        $dtn  = $this->DataTreatmentNotes->find()->select(['id'])
                ->where(['treatment_id'=> $dt->id])->first();



        $this->log(__LINE__ . " " .json_encode($dtn));

        if(isset($dtn->id)){
            $this->log(__LINE__ . " update " );
            $this->DataTreatmentNotes->updateAll(
                ['notes' => $notes, 'treatment_id' => $dt->id], 
                ['id' => $dtn->id]
            );
        }else{        
            $this->log(__LINE__ . " insert " );
            $ti_entity = $this->DataTreatmentNotes->newEntity(['notes' => $notes, 'treatment_id' => $dt->id]);
            if(!$ti_entity->hasErrors()) {
                if ($this->DataTreatmentNotes->save($ti_entity)) {
                    
                }
            }
        }

        
        $this->Response->success();
    }

    public function uploadImage()
    {        
        $this->loadModel('SpaLiveV1.DataTreatmentMint');
        $this->loadModel('SpaLiveV1.DataTreatmentImage');
        $this->loadModel('SpaLiveV1.SysUsers');
        $uid = get('id','');
        if(empty($uid)){
            $this->Response->message('Invalid treatment x1.');
            return;
        }

        $treatment = $this->DataTreatmentMint->find()->where(['DataTreatmentMint.uid' => $uid])->first();

        if(empty($treatment)){
            $this->Response->message('Invalid treatment x2.');
            return;
        }
        $typeImageItem="";
        if(!isset($_POST['typeImagearr'])){
            $typeImageItem = get('typeImage', '');
            if(empty($typeImageItem)){
                $this->Response->message('Invalid type Image');
                return;
            }    
        }else{            
            $typeImagearr = $_POST['typeImagearr'];
            // $this->Response->set('typeImage',isset($typeImage[1][$_FILES['file']['name']])? $typeImage[1][$_FILES['file']['name']]: 'not found');return;
            for ($i=0; $i < count($typeImagearr); $i++) { 
                if (isset($typeImagearr[$i][$_FILES['file']['name']])){
                    $typeImageItem =  $typeImagearr[$i][$_FILES['file']['name']];
                }                        
            }
            $this->Response->set('imgname',$typeImageItem);            
        }                
        if (!isset($_FILES['file'])) {
            $this->Response->set('error_file',$_FILES);
            return;
        }

        if (!isset($_FILES['file']['name'])) {
            $this->Response->set('error_name',$_FILES['file']);
            return;
        }

        $str_name = $_FILES['file']['name'];
        $_file_id = $this->Files->upload([
            'name' => $str_name,
            'type' => $_FILES['file']['type'],
            'path' => $_FILES['file']['tmp_name'],
            'size' => $_FILES['file']['size'],
        ]);

        if($_file_id <= 0){
            $this->Response->message('Error in save content file.');
            return;
        }

        $arrSave = [
            'treatment_id' => $treatment->id,
            'file_id' => $_file_id,
            'typeImage' => $typeImageItem == "" ? "before" : $typeImageItem
        ];

        $ti_entity = $this->DataTreatmentImage->newEntity($arrSave);
        if(!$ti_entity->hasErrors()) {
            if ($this->DataTreatmentImage->save($ti_entity)) {
                $this->Response->set('image_id', $_file_id);
                $this->Response->success();
            }
        }else{
            $this->Response->message('Error in save file to treatment.');
        }
    }

    public function approve_treatment(){
        $this->loadModel('DataTreatment');
        $treatment = $this->DataTreatment->find()
        ->where(['DataTreatment.uid' => get('uid','')])->first();

        if(empty($treatment)){
            $this->Response->message('Invalid treatment.');
            return;
        }

        if($treatment->type_uber == 1){
            $this->DataTreatment->updateAll(
                ['status' => 'PETITION'],
                ['id' => $treatment->id]
            );
        }else{
            $this->DataTreatment->updateAll(
                ['status' => 'REQUEST'],
                ['id' => $treatment->id]
            );
        }

        $this->Response->success();
    }

    public function reject_treatment(){
        $this->loadModel('DataTreatment');
        $treatment = $this->DataTreatment->find()
        ->where(['DataTreatment.uid' => get('uid','')])->first();

        if(empty($treatment)){
            $this->Response->message('Invalid treatment.');
            return;
        }

        $this->DataTreatment->updateAll(
            ['status' => 'REJECTED'],
            ['id' => $treatment->id]
        );

        $this->Response->success();
    }
}