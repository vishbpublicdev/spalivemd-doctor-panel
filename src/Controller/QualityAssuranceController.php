<?php
declare(strict_types=1);

namespace App\Controller;

use Admin\Controller\AppController;
use Cake\Utility\Security;
use Cake\Utility\Hash;


class QualityAssuranceController extends AppController{

	public function initialize() : void{
        parent::initialize();
        $this->loadModel('Admin.DataTreatmentSurvey'); 
        $this->Session = $this->getRequest()->getSession();       
    }

    public function grid(){
        $page = intval(get('page', 1));
        $limit = get('limit', 50);

        $user_id = USER_ID;
		$userType = $this->Session->read('_User.user_type');                
        if($userType == 'MASTER'){
            $_where = ['DataTreatmentSurvey.deleted' => 0];
        }else{
            $_where = ['DataTreatmentSurvey.deleted' => 0, 'Treatment.assigned_doctor' => $user_id ];
        }        

        if (get('filter','')) {
            $arr_filter = json_decode(get('filter'),true);
            if ($arr_filter[0]['property'] == "query") {
                $search = $arr_filter[0]['value'];
                $_where['OR'] = [['User.name LIKE' => "%$search%"],['User.lname LIKE' => "%$search%"],['User.email LIKE' => "%$search%"]];
            } 
        }

        $fields = [
            'DataTreatmentSurvey.treatment_uid',
            'DataTreatmentSurvey.id',
            'DataTreatmentSurvey.injector_name',
            'DataTreatmentSurvey.pacient_name',
            'DataTreatmentSurvey.created',
            'Treatment.schedule_date',
            'DataTreatmentSurvey.experience',
            'DataTreatmentSurvey.injector_behave',
            'DataTreatmentSurvey.injector_confident',
            'DataTreatmentSurvey.injector_explain',
            'DataTreatmentSurvey.company_future',
            'DataTreatmentSurvey.negative_answers',
            'DataTreatmentSurvey.done_improve'
        ];

        $ent_survey = $this->DataTreatmentSurvey->find()->select($fields)

        ->join([
            'Treatment' => ['table' => 'data_treatment', 'type' => 'INNER', 'conditions' => 'Treatment.uid = DataTreatmentSurvey.treatment_uid'],
            'User' => ['table' => 'sys_users', 'type' => 'INNER', 'conditions' => 'User.id = Treatment.patient_id']
        ])->where($_where)->order(['DataTreatmentSurvey.id' => 'DESC'])->limit($limit)->page($page)->toArray();

        $data = array();
        foreach ($ent_survey as $row) {
            $data[] = array(
                'id' => $row->id,
                'injector_name' => $row->injector_name,
                'pacient_name' => $row->pacient_name,
                'created' => $row->created,
                'schedule_date' => $row['Treatment']['schedule_date'],
                'experience' => $row->experience,
                'injector_behave' => $row->injector_behave,
                'injector_confident' => $row->injector_confident,
                'injector_explain' => $row->injector_explain,
                'company_future' => $row->company_future,
                'negative_answers' => $row->negative_answers,
                'done_improve' => $row->done_improve,
            );
        }

        $this->Response->success();
        $this->Response->set('data', $data);
    }
}