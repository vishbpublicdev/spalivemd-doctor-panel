<?php
declare(strict_types=1);

namespace Admin\Controller;


use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\View\Exception\MissingTemplateException;
use Cake\Utility\Hash;
use Cake\I18n\FrozenTime;
use Cake\Utility\Security;
use Cake\Utility\Text;

class PatientsController extends AppController
{
    public function initialize() : void{
        parent::initialize();
        $this->Session = $this->getRequest()->getSession();
        $this->loadModel('Admin.SysUsers');

        $this->URL_API = env('URL_API', 'https://api.myspalive.com/');
    }

    public function grid( $data_csv = true, $grid_type = " ")
    {
        $userType = $this->Session->read('_User.user_type');
        $this->Response->set('userType', $userType);		
        $this->loadModel('SysUsersAdmin');
        
        $page = intval(get('page', 1));
        $limit = get('limit', 300);
        if ($data_csv == false){
            $limit = get('limit', 1500);
        }else {
            $limit = get('limit', 50);
        }       

        $_having = [];
        
                
                //$_join = ['Admin' => ['table' => 'sys_users_admin', 'type' => 'LEFT', 'conditions' => 'Admin.id = SysUsers.md_id'],];
                $str_mfilter = get('mfilter','');
                if ($str_mfilter == 'DELETED USERS') {
                    $_where = ['SysUsersAdmin.deleted' => 1];
                }

                $str_type = get('type',$grid_type);
                if (!empty($str_type)) {
                    if ($str_type == 'patient') {                                
                        
                    } else {
                        //$_where['SysUsersAdmin.type'] = $str_type;
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
                    }
                }
            if($userType == 'MASTER'){
                $_where = ['SysUsersAdmin.deleted' => 0, 'SysUsersAdmin.user_type' => 'DOCTOR'];
                if(isset($search)){
                    $_where['OR'] = [
                        ['SysUsersAdmin.username LIKE' => "%$search%"], 
                        ['SysUsersAdmin.name LIKE' => "%$search%"],                    
                        //"MATCH(SysUsersAdmin.name,SysUsersAdmin.mname,SysUsersAdmin.lname) AGAINST ('{$matchValue}' IN BOOLEAN MODE)"
                    ];                    
                }
            }else{
                $user_id = USER_ID;
                $_where = ['SysUsersAdmin.deleted' => 0, 'SysUsersAdmin.user_type' => 'DOCTOR', 'SysUsersAdmin.id' => $user_id];
                if(isset($search)){
                    $_where['OR'] = [
                        ['SysUsersAdmin.username LIKE' => "%$search%"], 
                        ['SysUsersAdmin.name LIKE' => "%$search%"],                    
                        //"MATCH(SysUsersAdmin.name,SysUsersAdmin.mname,SysUsersAdmin.lname) AGAINST ('{$matchValue}' IN BOOLEAN MODE)"
                    ];
                }
            }
                $str_mOrder = get('mOrder', '');                       
                $order = ['SysUsersAdmin.id' => 'DESC'];
                $str_sort = get('sort','');                
                                
                $arrUsersCount = $this->SysUsersAdmin->find()->select()//->join($_join)
                ->where($_where)->having($_having)->order($order)->count();        
                $page_min = ceil($arrUsersCount / $limit);
                if ($page > $page_min){
                    $page = intval($page_min);
                }
                if($page < 1){ $page = 1; }
                
                $arrUsers = $this->SysUsersAdmin->find()->select()//->join($_join)
                ->where($_where)->having($_having)->order($order)->limit($limit)->page($page)->all();               
                $response_array = array();
                foreach ($arrUsers as $row) {            
                    $add_array = array(
                        "uid" => $row['uid'],
                        "id" => $row['id'],
                        "name" => $row['name'],
                        "username" => $row['username'],
                    );
                    $response_array[] = $add_array;                     
                }  
            


                          
            $this->Response->success();
            $this->Response->set('data', $response_array);
            
            $this->Response->set('total', $arrUsersCount);
            

            //$totals = $this->getSummaryUsersByStatus($str_type, "");
            $totals['total'] = $arrUsersCount;
            
            $this->Response->set('summary', $totals);
        
    }

    

    public function gridOtherStates( $data_csv = true)
    {
        
        $page = intval(get('page', 1));
        $limit = get('limit', 300);

        if ($data_csv == false){
            $limit = get('limit', 99999);
        }else {
            $limit = get('limit', 50);
        }

        $_where = ['SysUsers.deleted' => 0, 'State.id <>' => 43];
        $_having = [];

        $str_mfilter = get('mfilter','');
        if ($str_mfilter == 'DELETED USERS') {
            $_where = ['SysUsers.deleted' => 1];
        }

        $str_type = get('type','');
        if (!empty($str_type)) {
            $_where['SysUsers.type'] = $str_type;
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

                $_where['OR'] = [['SysUsers.short_uid LIKE' => "%$search%"], ['SysUsers.email LIKE' => "%$search%"],['SysUsers.phone LIKE' => "%$search%"],['SysUsers.name LIKE' => "%$search%"],['SysUsers.lname LIKE' => "%$search%"],
                    "MATCH(SysUsers.name,SysUsers.mname,SysUsers.lname) AGAINST ('{$matchValue}' IN BOOLEAN MODE)"];
            }
        }

        $to_add_cert = get('to_add_cert', 0);
        if($to_add_cert == 1){
            $_where['SysUsers.login_status IN'] = ['READY', 'CHANGEPASSWORD'];
        }

        $order = ['SysUsers.id' => 'DESC'];

        $str_sort = get('sort','');

        // [{"property":"score","direction":"ASC"}]
        if (!empty($str_sort)) {
            $order = [];
            $arr_sort = json_decode($str_sort,true);
            if (!empty($arr_sort)) {
                foreach($arr_sort as $e_sort) {
                    if ($e_sort['property'] == "score") {
                        $order['SysUsers.score'] = $e_sort['direction'];
                    } else if ($e_sort['property'] == "login_status") {
                        $order['SysUsers.login_status'] = $e_sort['direction'];
                    } else  if ($e_sort['property'] == "name") {
                        if ($str_type == 'clinic')
                            $order['SysUsers.bname'] = $e_sort['direction'];
                        else 
                            $order['SysUsers.name'] = $e_sort['direction'];
                    } else  if ($e_sort['property'] == "registration_date") {
                        $order['SysUsers.created'] = $e_sort['direction'];
                    } else  if ($e_sort['property'] == "last_purchase") {
                        $order['last_purchase'] = $e_sort['direction'];
                    } else  if ($e_sort['property'] == "last_training") {
                        $order['last_training'] = $e_sort['direction'];
                    } else  if ($e_sort['property'] == "last_treatment") {
                        $order['last_treatment'] = $e_sort['direction'];
                    } else  if ($e_sort['property'] == "last_exam") {
                        $order['last_exam'] = $e_sort['direction'];
                    } else  if ($e_sort['property'] == "a_purchases") {
                        $order['a_purchases'] = $e_sort['direction'];
                    } else  if ($e_sort['property'] == "n_purchases") {
                        $order['n_purchases'] = $e_sort['direction'];
                    } else  if ($e_sort['property'] == "n_treatments") {
                        $order['n_treatments'] = $e_sort['direction'];
                    } else  if ($e_sort['property'] == "a_treatments") {
                        $order['a_treatments'] = $e_sort['direction'];
                    }  else  if ($e_sort['property'] == "city") {
                        $order['SysUsers.city'] = $e_sort['direction'];
                    }  else  if ($e_sort['property'] == "state") {
                        $order['State.name'] = $e_sort['direction'];
                    }  else  if ($e_sort['property'] == "paid_from") {
                        $order['paid_from'] = $e_sort['direction'];
                    }  else  if ($e_sort['property'] == "flag") {
                        $order = 'CASE WHEN (n_purchases - n_treatments) > 1 then 1 WHEN ((rej_treatments * 100)/all_treatments) >= 10 then 1 else 0 END ' . $e_sort['direction'];
                    }  else  if ($e_sort['property'] == "is_ci_of_month") {
                        $order['is_ci_of_month'] = $e_sort['direction'];
                    } else  if ($e_sort['property'] == "most_reviewed") {
                        $order['most_reviewed'] = $e_sort['direction'];

                    } else  if ($e_sort['property'] == "invited_by") {
                        $ss = $e_sort['direction'] == 'ASC' ? 'DESC' : 'ASC';
                        
                        $order['invited_by'] = $ss;
                        
                    } else  if ($e_sort['property'] == "last_status_change") {
                        $order['last_status_change'] = $e_sort['direction'];
                    } else  if ($e_sort['property'] == "filter_icons") {
                        $order['filter_icons'] = $e_sort['direction'];
                    } else  if ($e_sort['property'] == "user_icons") {
                        $order['user_icons'] = $e_sort['direction'];
                    } else  if ($e_sort['property'] == "basic_course_date") {
                        $order['basic_course_date'] = $e_sort['direction'];
                    } 
                    
                }
            }
        }

        $this->loadModel('DataTreatmentReview');
        $most_reviewed = $this->DataTreatmentReview->injectorMostReviewed();
        $arr_most_reviewed = implode(",", $most_reviewed);


        $fields = ['SysUsers.uid','SysUsers.id','SysUsers.short_uid','SysUsers.active','SysUsers.name','SysUsers.mname','SysUsers.lname','SysUsers.email','SysUsers.bname','SysUsers.score','SysUsers.phone','SysUsers.created','SysUsers.modified','SysUsers.login_status','SysUsers.steps','SysUsers.latitude','SysUsers.longitude','SysUsers.stripe_account','SysUsers.stripe_account_confirm', 'SysUsers.photo_id','SysUsers.city','State.name', 'SysUsers.show_in_map', 'SysUsers.show_most_review','SysUsers.last_status_change','SysUsers.tracers'];
        
        $fields['invited_by'] = "(SELECT CONCAT(SU.name,' ',SU.lname) FROM data_network_invitations DTI LEFT JOIN sys_users SU ON SU.id = DTI.parent_id WHERE DTI.email LIKE SysUsers.email LIMIT 1)";

        $fields['referred_by'] = "(SELECT CONCAT(SU.name,' ',SU.lname) FROM data_sales_representative_register DSR LEFT JOIN sys_users SU ON SU.id = DSR.representative_id WHERE DSR.user_id = SysUsers.id AND DSR.deleted = 0 LIMIT 1)";

        $fields['assigned_to'] = "(SELECT CONCAT(SU.name,' ',SU.lname) FROM data_assigned_to_register DSR LEFT JOIN sys_users SU ON SU.id = DSR.representative_id WHERE DSR.user_id = SysUsers.id AND DSR.deleted = 0 LIMIT 1)";

        //$fields['version'] = "(SELECT CONCAT(AK.type, ' | ', AD.app_version, ' | ', date_format(AD.created, '%c/%e/%Y %H:%i') ) FROM api_debug AD LEFT JOIN api_keys AK ON AK.id = AD.key_id WHERE AD.createdby = SysUsers.id AND AD.app_version <> '' GROUP BY AD.id ORDER BY AD.id DESC LIMIT 1)";
        $fields['version'] = "(SELECT CONCAT(AK.type, ' | ', AD.app_version, ' | ', date_format(AD.created, '%c/%e/%Y %H:%i') ) FROM sys_users_versions AD LEFT JOIN api_keys AK ON AK.id = AD.key_id WHERE AD.createdby = SysUsers.id AND AD.app_version <> '' LIMIT 1)";
        
        $fields['comments'] = "(SELECT SUBSTRING(`notes`, 1, 50) FROM data_users_notes DUN WHERE DUN.user_id = SysUsers.id)";

        if ($str_type == 'injector') {
            $fields['basic_course_payment'] = "
            (SELECT DP.total FROM
                data_payment DP WHERE
                DP.id_from = SysUsers.id AND
                DP.type = 'BASIC COURSE' AND DP.prod = 1 AND DP.is_visible = 1 AND DP.payment <> ''
                ORDER BY DP.id DESC LIMIT 1
            )
            ";
             
            $fields['last_training'] = "(SELECT CONCAT(CT.title,'|',CT.scheduled) FROM data_trainings DT JOIN cat_trainings CT ON CT.id = DT.training_id WHERE DT.user_id = SysUsers.id AND DT.deleted = 0 AND CT.scheduled < NOW() AND CT.deleted = 0 ORDER BY CT.scheduled DESC LIMIT 1)";

            $fields['cp_basic'] = "(SELECT COUNT(CT.id) FROM data_trainings DT JOIN cat_trainings CT ON CT.id = DT.training_id WHERE DT.user_id = SysUsers.id AND DT.deleted = 0 AND CT.scheduled < NOW() AND CT.deleted = 0 AND CT.level = 'LEVEL 1' ORDER BY CT.scheduled DESC)";
            
            $fields['cp_advanced'] = "(SELECT COUNT(CT.id) FROM data_trainings DT JOIN cat_trainings CT ON CT.id = DT.training_id WHERE DT.user_id = SysUsers.id AND DT.deleted = 0 AND CT.scheduled < NOW() AND CT.deleted = 0 AND CT.level = 'LEVEL 2' ORDER BY CT.scheduled DESC)";
            
            $fields['pay_advanced'] = "(SELECT COUNT(DP.id) FROM data_payment DP WHERE DP.id_from = SysUsers.id AND DP.payment <> '' AND DP.is_visible = 1 AND DP.type IN ('ADVANCED COURSE') )";

            $fields['pay_basic'] = "(SELECT COUNT(DP.id) FROM data_payment DP WHERE DP.id_from = SysUsers.id AND DP.payment <> '' AND DP.is_visible = 1 AND DP.type IN ('CI REGISTER', 'BASIC COURSE') )";
            
            $fields['pay_advanced_p'] = "(SELECT COUNT(DP.id) FROM data_purchases DP 
                                          JOIN data_purchases_detail DPD ON DPD.purchase_id = DP.id 
                                          JOIN cat_products CP ON CP.id = DPD.product_id 
                                          JOIN data_payment DPA ON DPA.uid = DP.uid 
                                          WHERE 
                                                DP.payment <> '' AND 
                                                DP.user_id = SysUsers.id AND 
                                                DPA.is_visible = 1 AND 
                                                DPA.payment <> '' AND
                                                DPA.id_to = 0 AND 
                                                DP.deleted = 0 AND 
                                                DPA.promo_code <> 'DSCT99SPA' AND 
                                                DPA.receipt <> '' AND 
                                                CP.id = 44)";

            $fields['last_treatment'] = "(SELECT DT.schedule_date FROM data_treatment DT WHERE DT.assistance_id = SysUsers.id AND DT.status = 'DONE' AND DT.deleted = 0 ORDER BY DT.schedule_date DESC LIMIT 1)";

            $fields['treatments'] = "(SELECT GROUP_CONCAT(CT.name) FROM cat_treatments_ci CT INNER JOIN data_treatments_prices DTP ON CT.id = DTP.treatment_id WHERE DTP.user_id = SysUsers.id)";

            $fields['last_purchase'] = "(SELECT DP.created FROM data_purchases DP WHERE DP.user_id = SysUsers.id AND DP.payment <> '' ORDER BY DP.created DESC LIMIT 1)";

            $fields['subscriptions'] = "(SELECT COUNT(DS.id) FROM data_subscriptions DS WHERE DS.user_id = SysUsers.id AND DS.deleted = 0 AND DS.status = 'ACTIVE')";

            $fields['subscriptions_cancel'] = "(SELECT COUNT(DS.id) FROM data_subscriptions DS WHERE DS.user_id = SysUsers.id AND DS.deleted = 0 AND DS.status = 'CANCELLED')";

            $fields['subscriptions_hold'] = "(SELECT COUNT(DS.id) FROM data_subscriptions DS WHERE DS.user_id = SysUsers.id AND DS.deleted = 0 AND DS.status = 'HOLD')";

            $fields['patient_model'] = "(SELECT COUNT(*) FROM data_model_patient dmp where dmp.email = SysUsers.email and  dmp.status = 'assigned' and  dmp.registered_training_id >  0  and deleted =0)";

            $fields['basic_course_date'] = "(SELECT DP.created FROM data_payment DP WHERE (DP.type = 'BASIC COURSE' OR DP.type= 'CI REGISTER') AND DP.intent <> '' AND DP.id_from = SysUsers.id AND DP.is_visible = 1 AND DP.payment <> '' LIMIT 1)";

            $fields['n_treatments'] = "(SELECT COUNT(id) FROM data_treatment DT WHERE DT.assistance_id = SysUsers.id AND DT.status = 'DONE' AND DT.deleted = 0 AND DT.payment <> '')";

            $fields['a_treatments'] = "(SELECT SUM(amount) FROM data_treatment DT WHERE DT.payment <> '' AND DT.status = 'DONE' AND DT.deleted = 0 AND DT.assistance_id = SysUsers.id)";

            $fields['n_purchases'] = "(SELECT COUNT(id) FROM data_purchases DP WHERE DP.user_id = SysUsers.id AND DP.deleted = 0 AND DP.payment <> '')";

            $fields['a_purchases'] = "(SELECT SUM(total) FROM data_payment DP WHERE DP.id_from = SysUsers.id AND DP.id_to = 0 AND DP.payment <> '' AND DP.is_visible = 1  AND DP.type = 'PURCHASE')";
            
            $fields['paid_from'] = "(SELECT SUM(total) FROM data_payment DP WHERE DP.id_to = SysUsers.id AND DP.payment <> '' AND DP.is_visible = 1 AND DP.comission_payed = 1)";

            $fields['n_invitees'] = "(SELECT COUNT(DTI.id) FROM data_network_invitations DTI LEFT JOIN sys_users Usr ON Usr.email LIKE DTI.email WHERE DTI.parent_id = SysUsers.id)";

            $fields['scheduled_training'] = "(SELECT DATE_FORMAT(CT.scheduled, '%m-%d-%Y %H:%i') AS date FROM data_trainings DT JOIN cat_trainings CT ON CT.id = DT.training_id WHERE DT.user_id = SysUsers.id AND CT.level = 'LEVEL 1' AND DT.deleted = 0 AND CT.deleted = 0 ORDER BY CT.scheduled DESC LIMIT 1)";

            $yesterday = FrozenTime::now()->subDays(1);
            $yesterday = $yesterday->i18nFormat('yyyy-MM-dd HH:mm:ss');
            $fields['all_treatments'] = "(SELECT COUNT(id) FROM data_treatment DT WHERE DT.assistance_id = SysUsers.id AND DT.deleted = 0)";
            $fields['rej_treatments'] = "(SELECT COUNT(id) FROM data_treatment DT WHERE DT.assistance_id = SysUsers.id AND DT.deleted = 0 AND DT.modified < '{$yesterday}' AND DT.status IN ('REJECT', 'INIT') )";
            

            $fields['training_notes'] = "(SELECT SUBSTRING(`notes`, 1, 50) FROM data_users_training_notes DUN WHERE DUN.user_id = SysUsers.id)";

            $first_day = date('Y-m-01');
            $last_day = date('Y-m-t');
            $fields['is_ci_of_month'] = "(SELECT COUNT(InjM.id) FROM data_injector_month InjM WHERE InjM.injector_id = SysUsers.id AND InjM.deleted = 0 AND InjM.date_injector BETWEEN '{$first_day}' AND '{$last_day}')";

            $fields['most_reviewed'] = "(FIND_IN_SET(SysUsers.id,'{$arr_most_reviewed}'))";

            $fields['user_icons'] = "(SELECT GROUP_CONCAT(CatIcon.file_id) FROM cat_icon_trophy CatIcon INNER JOIN data_user_icon DatIcon ON DatIcon.icon_id = CatIcon.id WHERE CatIcon.deleted = 0 AND CatIcon.type_icon = 'ICON' AND DatIcon.user_id = SysUsers.id)";

            $fields['filter_icons'] = "(SELECT GROUP_CONCAT(CatIcon.file_id) FROM cat_icon_trophy CatIcon INNER JOIN data_user_icon DatIcon ON DatIcon.icon_id = CatIcon.id WHERE CatIcon.deleted = 0 AND CatIcon.type_icon = 'FILTER' AND DatIcon.user_id = SysUsers.id)";

            // $fields['purchase_before'] = "(SELECT COUNT(DatPur.id) FROM data_purchases DatPur INNER JOIN data_purchases_detail DatPurDet ON DatPurDet.purchase_id = DatPur.id WHERE DatPur.user_id = SysUsers.id AND DatPur.payment <> '' AND DatPurDet.product_id NOT IN (SELECT CatTreat.product_id FROM data_treatment DatTreat INNER JOIN data_treatment_detail TrtDet ON TrtDet.treatment_id = DatTreat.id INNER JOIN cat_treatments_ci CatTreat ON CatTreat.id = TrtDet.cat_treatment_id WHERE DatTreat.assistance_id = SysUsers.id))";

        } else if ($str_type == 'clinic') {

            $fields['last_exam'] = "(SELECT DC.schedule_date FROM data_consultation DC WHERE DC.createdby = SysUsers.id AND DC.status = 'CERTIFICATE' ORDER BY DC.schedule_date DESC LIMIT 1)";

        } else if ($str_type == 'patient') {

            $fields['last_exam'] = "(SELECT DC.schedule_date FROM data_consultation DC WHERE DC.patient_id = SysUsers.id AND DC.status = 'CERTIFICATE' ORDER BY DC.schedule_date DESC LIMIT 1)";

            $fields['last_treatment'] = "(SELECT DT.schedule_date FROM data_treatment DT WHERE DT.patient_id = SysUsers.id AND DT.status = 'DONE' ORDER BY DT.schedule_date DESC LIMIT 1)";

            $fields['crby'] = "(SELECT CONCAT(SU.name,' ',SU.lname) FROM sys_users SU WHERE SU.id = SysUsers.createdby LIMIT 1)";
            
            $fields['credit'] = "(SELECT DP.id FROM data_payment DP WHERE DP.prepaid = 1 AND DP.id_from = SysUsers.id AND DP.payment <> '' AND DP.is_visible = 1 AND DP.service_uid = '' LIMIT 1)";

            $fields['first_treatment_uber'] = "(SELECT DT.type_uber FROM data_treatment DT WHERE DT.patient_id = SysUsers.id AND DT.`status`= 'DONE' LIMIT 1)";

        } else if ($str_type == 'examiner') {
            $fields['last_exam'] = "(SELECT DC.schedule_date FROM data_consultation DC WHERE DC.assistance_id = SysUsers.id AND DC.status = 'DONE' ORDER BY DC.schedule_date DESC LIMIT 1)";
        }

        $_join = [
            'State' => ['table' => 'cat_states','type' => 'INNER','conditions' => 'State.id = SysUsers.state'],
        ];

        

        $arrUsers = $this->SysUsers->find()->select($fields)->join($_join)->where($_where)->having($_having)->order($order)->limit($limit)->page($page)->all();
        $arrUsersCount = $this->SysUsers->find()->select($fields)->join($_join)->where($_where)->having($_having)->order($order)->count();

        $response_array = array();
        
        foreach ($arrUsers as $row) {
            $allTreat = $row['all_treatments'];
            $rejTreat = $row['rej_treatments'];
            $percTreat = 0;
            if($allTreat > 0){
                $percTreat = ($rejTreat * 100)/$allTreat;
            }
            if (!empty($str_mfilter)) {
                if ($str_mfilter == 'CP W/BASIC BUT NO SUBSCRIPTIONS') {
                    if($row['subscriptions'] < 2 && $row['cp_basic'] >= 1) {
                        
                    }else {
                        continue;
                    }
                }
            }

            $background = $row['tracers'];
            if (!empty($background)) {
                $background = json_decode($background, true);
                if (isset($background['numrecords'])){
                    if ($background['numrecords'] == 0){ 
                        $background = 'No criminal record';
                    }
                    else 
                        $background = 'Criminal record found ';
                }
                else {
                    if (!isset($background['criminalRecordCounts'])) $background['criminalRecordCounts'] = 0;
                    if ($background['criminalRecordCounts'] == 0){ 
                        $background = 'No criminal record';
                    }
                    else 
                        $background = 'Criminal record found';
                }
            }
            
            $str_status = empty($row['steps']) ? '' : $row['steps'];
            if ($str_mfilter == 'DELETED USERS') {
                $str_status = 'DELETED';
            }

            $show_in_map = $row['show_in_map'] == 0 ? 1 : $row['show_in_map'];
            $nname = !empty(trim($row['mname'])) ? $row['name'] . ' ' . trim($row['mname']) . ' ' . trim($row['lname']) : $row['name'] . ' ' . trim($row['lname']);
            $add_array = array(
                "uid" => $row['uid'],
                "id" => $row['id'],
                "background" => $background, 
                "basic_course_date" => isset($row['basic_course_date']) ? $row['basic_course_date'] : '',
                "basic_course_payment" => isset($row['basic_course_payment']) ? $row['basic_course_payment'] / 100 : '0',   
                "first_treatment_uber" => $row['first_treatment_uber'],
                "invited_by" => $row['invited_by'],
                "referred_by" => $row['referred_by'],
                "assigned_to" => $row['assigned_to'],
                "short_uid" => $row['short_uid'],
                "active" => $row['active'],
                "name" => $nname,
                "user" => $row['email'],
                "bname" => $row['bname'],
                "score" => $row['score'] > 0 ? $row['score'] :'N/A',
                "phone" => $row['phone'],
                "stripe" => $row['stripe_account_confirm'] == 1 ? $row['stripe_account'] : '',
                "version" => $row['version'] ? str_replace('version ','', $row['version']) : '',
                "last_training" => $row['last_training'],
                "latitude" => $row['latitude'],
                "longitude" => $row['longitude'],
                "crby" => $row['crby'],
                "last_treatment" => $row['last_treatment'],
                "last_purchase" => $row['last_purchase'],
                "last_exam" => $row['last_exam'],
                "last_status_change" => $row['last_status_change'],
                "photo_id" => $row['photo_id'],
                "treatments" => $row['treatments'],
                "registration_date" => isset($row['created']) ? $row['created']->i18nFormat('yyyy-MM-dd HH:mm') : '',
                "last_date" => isset($row['modified']) ? $row['modified']->i18nFormat('yyyy-MM-dd HH:mm') : '',
                "login_status" => $str_status,
                'credit' => isset($row['credit']) ? 1 : 0,
                'comments' => isset($row['comments']) ? $row['comments'] : '',
                'training_notes' => !empty($row['training_notes']) ? $row['training_notes'] : $row['scheduled_training'],
                'state' => $row['State']['name'],
                'city' => $row['city'],
                'n_treatments' => $row['n_treatments'] > 0 ? $row['n_treatments'] : 0,
                'a_treatments' => isset($row['a_treatments']) ? $row['a_treatments'] / 100 : 0,
                'n_purchases' => $row['n_purchases'] > 0 ? $row['n_purchases'] : 0,
                'a_purchases' => isset($row['a_purchases']) ? $row['a_purchases'] / 100 : 0,
                'paid_from' => isset($row['paid_from']) ? $row['paid_from'] / 100 : 0,
                'show_in_map' => intval($show_in_map),
                'flag' => (intval($row['n_purchases']) - intval($row['n_treatments'])) > 1 || $percTreat >= 10 ? 1 : 0,
                'most_reviewed' => ($row['show_most_review'] == 'DEFAULT') ? ($row['most_reviewed'] > 0 ? 1 : 0) : (($row['show_most_review'] == 'FORCED') ? 1 : 0),
                'percent_rej_treatments' => $percTreat,
                'user_icons' => $row['user_icons'],
                'filter_icons' => $row['filter_icons'],
                'subscriptions_cancel' => $row['subscriptions_cancel'],
                'subscriptions_hold' => $row['subscriptions_hold'],
                'patient_model' => $row['patient_model'],
                
            );
            if($str_type == 'injector'){
                $add_array['licenses'] = json_encode($this->getLicensesInjector($row['id']));
            }

            if(isset($row['treatments'])){
                $add_array['treatments'] = $row['treatments'];
            }
            if(isset($row['n_invitees'])){
                $add_array['n_invitees'] = $row['n_invitees'];
            }
            if(isset($row['purchase_before'])){
                $add_array['purchase_before'] = $row['purchase_before'];
            }
            if(isset($row['is_ci_of_month'])){
                $add_array['is_ci_of_month'] = ($row['is_ci_of_month'] > 0 ? 1 : 0);
            }
            $response_array[] = $add_array;
        }
        
        if ($data_csv == false){
            $this->Response->set('data', $response_array);
            return $response_array;
        }else {
            $this->Response->success();
            $this->Response->set('data', $response_array);
            $this->Response->set('total', $arrUsersCount);
            //$this->Response->set('summary', $this->getSummaryUsersByStatus($str_type));
        }
    }

    public function loadgfeci()
    {

         $status_array = array(
            'INIT' => 'WAITING FOR APPROVAL',
            'REJECTED' => 'REJECTED REGISTRATION',
            'READY' => 'READY',
            '' => '',
        );

        $fields = ['SysUsers.id','SysUsers.active','SysUsers.amount','SysUsers.bname','SysUsers.city','SysUsers.created','SysUsers.createdby','SysUsers.deleted','SysUsers.dob','SysUsers.ein','SysUsers.email','SysUsers.i_nine_id','SysUsers.latitude','SysUsers.lname','SysUsers.login_status','SysUsers.longitude','SysUsers.mname','SysUsers.modified','SysUsers.modifiedby','SysUsers.name','SysUsers.payment','SysUsers.payment_intent','SysUsers.phone','SysUsers.photo_id','SysUsers.radius','SysUsers.score','SysUsers.short_uid','SysUsers.state','SysUsers.street','SysUsers.ten_nintynine_id','SysUsers.tracers','SysUsers.type','SysUsers.uid','SysUsers.zip','SysUsers.description','DataRequestGfeCi.status','SysUsers.show_in_map', 'SysUsers.show_most_review','SysUsers.receipt_url'];

        $fields['treatments'] = "(SELECT GROUP_CONCAT(CTC.name,' $', ROUND(DTP.price / 100,2) SEPARATOR '\n') FROM data_treatments_prices DTP JOIN cat_treatments_ci CTC ON CTC.id = DTP.treatment_id WHERE DTP.user_id = SysUsers.id AND DTP.deleted = 0)";
        //$fields['av_model'] = "(SELECT GROUP_CONCAT( CONCAT(DSM.days,'|',DSM.time_start,'|',DSM.time_end) SEPARATOR '-') FROM data_schedule_model DSM WHERE DSM.injector_id = SysUsers.id AND DSM.deleted = 0 AND DSM.model = 'injector' AND DSM.days NOT LIKE '%,%')";
        $fields['cpr_lic_id'] = "(SELECT DUDL.file_id FROM data_user_cpr_licence DUDL WHERE DUDL.user_id = SysUsers.id ORDER BY DUDL.id DESC LIMIT 1)";
        $fields['weight_loss'] = "(SELECT COUNT(*) FROM data_examiners_other_services WHERE user_id = SysUsers.id AND deleted = 0 AND aprovied = 'APPROVED' LIMIT 1)";
        $fields['mint_aprovied'] = "(SELECT COUNT(*) FROM data_examiners_clinics WHERE user_id = SysUsers.id AND deleted = 0 AND aprovied = 'APPROVED' LIMIT 1)";
        $arrUsers = $this->SysUsers->find()->select($fields)
        ->join([
            'DataRequestGfeCi' => ['table' => 'data_request_gfe_ci', 'type' => 'INNER', 'conditions' => 'DataRequestGfeCi.user_id = SysUsers.id'],
            ])
        ->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();


        $this->loadModel('DataInjectorMonth');
        $this->loadModel('SpaLiveV1.DataPurchases');
        $this->loadModel('SpaLiveV1.DataNetwork');

        $first_day = date('Y-m-01');
        $last_day = date('Y-m-t');
        $injMonth = $this->DataInjectorMonth->find()->where(["(DataInjectorMonth.date_injector BETWEEN '{$first_day}' AND '{$last_day}')", 'DataInjectorMonth.deleted' => 0, 'DataInjectorMonth.injector_id' => $arrUsers->id])->first();
        $arrUsers['is_ci_of_month'] = empty($injMonth) ? 0 : 1;
        // $this->Response->set('is_ci_of_month', empty($injMonth) ? 0 : 1);

        $str_now = date('Y-m-d H:i:s');
        $ent_purchases = $this->DataPurchases->find()->select(['DataPurchases.uid'])
        ->where(['DataPurchases.user_id' => $arrUsers->id, 'DataPurchases.payment <>' => '', "DATEDIFF('{$str_now}', DataPurchases.created) BETWEEN 0 AND 90"])
        ->first();
        
        $userInPyr = $this->DataNetwork->find()->where(['DataNetwork.user_id' => $arrUsers->id])->first();

        $this->set('has_commissions', !empty($ent_purchases) ? 1 : 0);
        $this->set('isInPyramid', !empty($userInPyr) ? 1 : 0);


        
        if (!empty($arrUsers)) {

            $usr_id = $arrUsers->id;
            $qr_skd_av = "SELECT GROUP_CONCAT( SkdUsr.skd_usr SEPARATOR '||||') as av_model FROM (SELECT CONCAT(DSM.days,'|',DSM.time_start,'|',DSM.time_end) skd_usr FROM data_schedule_model DSM WHERE DSM.injector_id = {$usr_id} AND DSM.deleted = 0 AND DSM.model = 'injector' AND DSM.days NOT LIKE '%,%') SkdUsr";
            $skd_av = $this->SysUsers->getConnection()->execute($qr_skd_av)->fetchAll('assoc');


            if (isset($skd_av[0]['av_model'])) {
                // pr($skd_av[0]['av_model']);exit;
                $av_model = $skd_av[0]['av_model'];
                $listDays = explode('||||', $av_model);
                $result = 'Available: ';
                foreach ($listDays as $item) {
                    $arr_mdl = explode('|', $item);
                    $result .= "\n\t" . $arr_mdl[0]. ($arr_mdl[0] == 'MONDAY' || $arr_mdl[0] == 'FRIDAY' ? "    " : '') . "\t From " . $arr_mdl[1] . ':00 to ' . $arr_mdl[2] . ":00";
                }
                $result .= "\nRange: " . $arrUsers['radius'] . " miles";
                $arrUsers['av_model'] = $result;
            }
            
            if ($arrUsers['show_in_map'] == 0) {
                $arrUsers['show_in_map'] = 1;
            }


            $arrUsers->login_status = $status_array[strtoupper($arrUsers['DataRequestGfeCi']['status'])];

             $this->Response->success();
             $this->Response->set('data', $arrUsers);
        }
    } 

    

   

    public function cat_states(){

        $this->loadModel('SpaLiveV1.CatStates');
        $ent_states = $this->CatStates->find()->where(['CatStates.deleted' => 0,'CatStates.enabled' => 1])->all();
        if(!empty($ent_states)){
            $result = array();
            foreach ($ent_states as $row) {
                $result[] = array(
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'abv' => $row['abv'],
                );
                
            }
        }

        $this->Response->success();
        $this->Response->set('data', $result);

    }

    public function load()
    {

        $status_array = array(
            'PAYMENT' => 'PAYMENT PENDING',
            'APPROVE' => 'WAITING FOR APPROVAL',
            'REJECT' => 'REJECTED REGISTRATION',
            'W9' => 'W9 PENDING',
            'CHANGEPASSWORD' => 'UPDATE PASSWORD PENDING',
            'READY' => 'READY',
            '' => '',
        );

        $fields = ['SysUsers.id','SysUsers.active','SysUsers.amount','SysUsers.bname','SysUsers.city','SysUsers.created','SysUsers.createdby','SysUsers.deleted','SysUsers.dob','SysUsers.ein','SysUsers.email','SysUsers.i_nine_id','SysUsers.latitude','SysUsers.lname','SysUsers.login_status','SysUsers.longitude','SysUsers.mname','SysUsers.modified','SysUsers.modifiedby','SysUsers.name','SysUsers.payment','SysUsers.payment_intent','SysUsers.phone','SysUsers.photo_id','SysUsers.radius','SysUsers.score','SysUsers.short_uid','SysUsers.state','SysUsers.street','SysUsers.ten_nintynine_id','SysUsers.tracers','SysUsers.type','SysUsers.uid','SysUsers.zip','SysUsers.description','SysUsers.suite','SysUsers.show_in_map', 'SysUsers.show_most_review','SysUsers.receipt_url','SysUsers.provider_url','SysUsers.speak_spanish'];

        $fields['treatments'] = "(SELECT GROUP_CONCAT(CTC.name,' $', ROUND(DTP.price / 100,2) SEPARATOR '\n') FROM data_treatments_prices DTP JOIN cat_treatments_ci CTC ON CTC.id = DTP.treatment_id WHERE DTP.user_id = SysUsers.id AND DTP.deleted = 0)";
        $fields['driver_lic_id'] = "(SELECT DUDL.file_id FROM data_user_driver_licence DUDL WHERE DUDL.user_id = SysUsers.id ORDER BY DUDL.id DESC LIMIT 1)";
        $fields['cpr_lic_id'] = "(SELECT DUDL.file_id FROM data_user_cpr_licence DUDL WHERE DUDL.user_id = SysUsers.id ORDER BY DUDL.id DESC LIMIT 1)";
        $fields['representative'] = "(SELECT COUNT(id) FROM data_sales_representative DSR WHERE DSR.user_id = SysUsers.id AND DSR.deleted = 0)";
        $fields['thumbs_up'] = "(SELECT COUNT(data_treatment_reviews.like) FROM data_treatment_reviews WHERE injector_id = SysUsers.id AND data_treatment_reviews.like = 'LIKE' GROUP BY data_treatment_reviews.like)";
        $fields['down_up'] = "(SELECT COUNT(data_treatment_reviews.like) FROM data_treatment_reviews WHERE injector_id = SysUsers.id AND data_treatment_reviews.like = 'DISLIKE' GROUP BY data_treatment_reviews.like)";
        $fields['weight_loss'] = "(SELECT COUNT(*) FROM data_examiners_other_services WHERE user_id = SysUsers.id AND deleted = 0 AND aprovied = 'APPROVED' LIMIT 1)";
        $fields['mint_aprovied'] = "(SELECT COUNT(*) FROM data_examiners_clinics WHERE user_id = SysUsers.id AND deleted = 0 AND aprovied = 'APPROVED' LIMIT 1)";
        //$fields['av_model'] = "(SELECT GROUP_CONCAT( SkdUsr.skd_usr SEPARATOR '||||') FROM (SELECT CONCAT(DSM.days,'|',DSM.time_start,'|',DSM.time_end) skd_usr FROM data_schedule_model DSM WHERE DSM.injector_id = {$usr_id} AND DSM.deleted = 0 AND DSM.model = 'injector' AND DSM.days NOT LIKE '%,%') SkdUsr)";

        $arrUsers = $this->SysUsers->find()->select($fields)
        ->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();

        if (!empty($arrUsers)) {
            if ($arrUsers['show_in_map'] == 0) {
                $arrUsers['show_in_map'] = 1;
            }

            if($arrUsers->type == 'injector' || $arrUsers->type == 'gfe+ci'){
                $this->loadModel('DataInjectorMonth');
                $this->loadModel('SpaLiveV1.DataPurchases');
                $this->loadModel('SpaLiveV1.DataNetwork');

                $usr_id = $arrUsers->id;
                $qr_skd_av = "SELECT GROUP_CONCAT( SkdUsr.skd_usr SEPARATOR '||||') as av_model FROM (SELECT CONCAT(DSM.days,'|',DSM.time_start,'|',DSM.time_end) skd_usr FROM data_schedule_model DSM WHERE DSM.injector_id = {$usr_id} AND DSM.deleted = 0 AND DSM.model = 'injector' AND DSM.days NOT LIKE '%,%') SkdUsr";
                $skd_av = $this->SysUsers->getConnection()->execute($qr_skd_av)->fetchAll('assoc');


                if (isset($skd_av[0]['av_model'])) {
                    // pr($skd_av[0]['av_model']);exit;
                    $av_model = $skd_av[0]['av_model'];
                    $listDays = explode('||||', $av_model);
                    $result = 'Available: ';
                    foreach ($listDays as $item) {
                        $arr_mdl = explode('|', $item);
                        $result .= "\n\t" . $arr_mdl[0]. ($arr_mdl[0] == 'MONDAY' || $arr_mdl[0] == 'FRIDAY' ? "    " : '') . "\t From " . $arr_mdl[1] . ':00 to ' . $arr_mdl[2] . ":00";
                    }
                    $result .= "\nRange: " . $arrUsers['radius'] . " miles";
                    $arrUsers['av_model'] = $result;
                }else{
                    $qr_skd_av_2 = "SELECT GROUP_CONCAT( SkdUsr.skd_usr SEPARATOR '||||') as av_model FROM (SELECT CONCAT(DSM.days,'|',DSM.time_start,'|',DSM.time_end) skd_usr FROM data_schedule_model DSM WHERE DSM.injector_id = {$usr_id} AND DSM.deleted = 0 AND DSM.model = 'injector' ) SkdUsr";
                    $skd_av_2 = $this->SysUsers->getConnection()->execute($qr_skd_av_2)->fetchAll('assoc');
                    if (isset($skd_av_2[0]['av_model'])) {
                         //pr($skd_av_2[0]['av_model']);exit;
                        $av_model = $skd_av_2[0]['av_model'];
                        $listDays = explode('||||', $av_model);
                        $result = 'Available: ';$arrUsers['listDays'] = $listDays;
                        foreach ($listDays as $item) {
                            $arr_mdl = explode('|', $item);
                            $result .= "\n\t" . $arr_mdl[0]. ($arr_mdl[0] == 'MONDAY' || $arr_mdl[0] == 'FRIDAY' ? "    " : '') . "\t From " . $arr_mdl[1] . ':00 to ' . $arr_mdl[2] . ":00";
                            break;
                        }
                        $result .= "\nRange: " . $arrUsers['radius'] . " miles";
                        $arrUsers['av_model'] = $result;
                    }
                }

                //WEIGHT LOSS SPECIALISTS
                if($arrUsers->type == 'injector'){
                    $this->loadModel('DataScheduleModel');
                    $this->loadModel('DataUsersOtherServicesCheckIn');

                    $_fields = [
                        'DataScheduleModel.days', 
                        'DataScheduleModel.time_start', 
                        'DataScheduleModel.time_end', 
                    ];

                    $_join = [
                        'CheckIn' => ['table' => 'data_users_other_services_check_in', 'type' => 'LEFT', 'conditions' => 'CheckIn.user_id = DataScheduleModel.injector_id'],            
                    ];

                    $where = ['DataScheduleModel.deleted' => 0, 'DataScheduleModel.model' => 'other_services', 'DataScheduleModel.injector_id' => $usr_id,
                              'CheckIn.status' => "WLSHOME"];
                
                    $weight_loss_availability = $this->DataScheduleModel->find()->select($_fields)->where($where)->join($_join)->all();

                    if(count($weight_loss_availability)>0){
                        $result_w = 'Available: ';

                        foreach ($weight_loss_availability as $w) {
                            $result_w .= "\n\t" . $w->days. ($w->days == 'MONDAY' || $w->days == 'FRIDAY' ? "    " : '') . "\t From " . $w->time_start . ':00 to ' . $w->time_end . ":00";
                        }

                        $arrUsers['av_weight_loss'] = $result_w;
                    }
                    
                }

                $first_day = date('Y-m-01');
                $last_day = date('Y-m-t');
                $injMonth = $this->DataInjectorMonth->find()->where(["(DataInjectorMonth.date_injector BETWEEN '{$first_day}' AND '{$last_day}')", 'DataInjectorMonth.deleted' => 0, 'DataInjectorMonth.injector_id' => $arrUsers->id])->first();
                $arrUsers['is_ci_of_month'] = empty($injMonth) ? 0 : 1;
                // $this->Response->set('is_ci_of_month', empty($injMonth) ? 0 : 1);

                $str_now = date('Y-m-d H:i:s');
                $ent_purchases = $this->DataPurchases->find()->select(['DataPurchases.uid'])
                ->where(['DataPurchases.user_id' => $arrUsers->id, 'DataPurchases.payment <>' => '', "DATEDIFF('{$str_now}', DataPurchases.created) BETWEEN 0 AND 90"])
                ->first();
                
                $userInPyr = $this->DataNetwork->find()->where(['DataNetwork.user_id' => $arrUsers->id])->first();

                $this->set('has_commissions', !empty($ent_purchases) ? 1 : 0);
                $this->set('isInPyramid', !empty($userInPyr) ? 1 : 0);
            }

            $arrUsers->login_status = $status_array[strtoupper($arrUsers->login_status)];
            unset($arrUsers['id']);

            $this->Response->success();
            $this->Response->set('data', $arrUsers);
        }
        else {
            $this->Response->success();
            $this->Response->set('data', $arrUsers['deleted'] = 1);
        }
    }

    

    

    public function save() {
        $this->loadModel('DataExaminersOtherServices');

        $arrUsers = $this->SysUsers->find()->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();
        if(empty($arrUsers)){
            // echo 'empty'; exit;
            // $this->message('Invalid Injector.');
            return;
        }

        //
        $uid = get('uid','');
        if($uid=='')
            return;
        $provider_url = get('provider_url','');
        $provider_url = "$provider_url";
        $uid = "$uid";
        if($provider_url!=""){
            if($uid !=""){        
                $str_query ="select count(*) as total from sys_users where provider_url = '".$provider_url."' and id !=  " .$arrUsers->id;
                $total = $this->SysUsers->getConnection()->execute($str_query)->fetch('assoc');            
                if($total['total'] > 0){
                    $this->Response->message('Shareable URL was taken already.');
                    return;
                }
            }
        }
        $dob = get('dob','');
        if (!empty($dob)) {

            $arr_dob = explode('-', $dob);
            if (count($arr_dob) > 2) 
                $dob = $arr_dob[2] . '-' . $arr_dob[0] . '-' . $arr_dob[1];
        } else {
            $dob = date('Y-m-t');
        }

        $active = get('active',1);
        $speak_spanish = get('speak_spanish',0);
        $save_array = array(
                'id' => $arrUsers->id,
                'bname' => get('bname',''),
                'name' => get('name',''),
                'nname' => get('nname',''),
                'mname' => get('mname',''),
                'lname' => get('lname',''),
                'zip' => get('zip',''),
                'ein' => get('ein',''),
                'email' => get('email',''),
                'description' => get('description',''),
                'state' => get('state',0),
                'phone' => get('phone',''),
                'street' => get('street',''),
                'city' => get('city',''),
                'suite' => get('suite',''),
                'dob' => $dob,
                'active' => $active, 
                'speak_spanish' => $speak_spanish, 
                'show_in_map' => get('show_in_map',1), 
                'show_most_review' => get('show_most_review', 'DEFAULT'), 
                'provider_url' => $provider_url, 
            );
        if($arrUsers->active == 1 && $active == 0){
            $save_array['show_in_map'] = 0;
        }


        $psw = get('password','');
        if (!empty($psw)) {
            $str_newpass_sha256 = hash_hmac('sha256', $psw, Security::getSalt());
            $save_array['password'] = $str_newpass_sha256;
        }


        if ($active == 0) {
            $str_quer = "UPDATE app_tokens SET `deleted` = 1 WHERE user_id = " . $arrUsers->id;
            $this->SysUsers->getConnection()->execute($str_quer);
        }

        $weight_loss = get('weight_loss','');
        if($weight_loss == 0){  // Se eliminan todos los registros aprobados
            $str_quer = "UPDATE data_examiners_other_services SET `deleted` = 1 WHERE aprovied = 'APPROVED' AND user_id = " . $arrUsers->id;
            $this->SysUsers->getConnection()->execute($str_quer);
        } else if($weight_loss == 1){
            // Se eliminan todos los registros
            $str_quer = "UPDATE data_examiners_other_services SET `deleted` = 1 WHERE user_id = " . $arrUsers->id;
            $this->SysUsers->getConnection()->execute($str_quer);
            // Se genera un nuevo registro
            $str_quer = "
                INSERT INTO data_examiners_other_services (user_id, service_uid, aprovied, deleted, created)
                VALUES (" . $arrUsers->id .", '1q2we3-r4t5y6-7ui8o990', 'APPROVED', 0, NOW())";
            $this->SysUsers->getConnection()->execute($str_quer);
        }

        $mint_aprovied = get('mint_aprovied','');
        if($mint_aprovied == 0){  // Se eliminan todos los registros aprobados
            $str_quer = "UPDATE data_examiners_clinics SET `deleted` = 1 WHERE aprovied = 'APPROVED' AND user_id = " . $arrUsers->id;
            $this->SysUsers->getConnection()->execute($str_quer);
        } else if($mint_aprovied == 1){
            // Se eliminan todos los registros
            $str_quer = "UPDATE data_examiners_clinics SET `deleted` = 1 WHERE user_id = " . $arrUsers->id;
            $this->SysUsers->getConnection()->execute($str_quer);
            // Se genera un nuevo registro
            $str_quer = "
                INSERT INTO data_examiners_clinics (user_id, aprovied, deleted, created)
                VALUES (" . $arrUsers->id .", 'APPROVED', 0, NOW())";
            $this->SysUsers->getConnection()->execute($str_quer);
        }

        $c_entity = $this->SysUsers->newEntity($save_array);
        if(!$c_entity->hasErrors()) {
            if ($this->SysUsers->save($c_entity)) {
                
                $ci_of_month = get('is_ci_of_month',0);
                $this->updateCIMonth($ci_of_month, $arrUsers->id);  

                if($active==0){
                    $this->Response->success();
                }else //si el usuario se va a activar y no esta eliminado
                if ($active == 1 && $arrUsers->deteled==0) {
                    $this->loadModel('SpaLiveV1.DataAssignedToRegister');
                    $this->loadModel('SpaLiveV1.DataSalesRepresentative');

                    $assigned = $this->DataAssignedToRegister->find()->select(['DataAssignedToRegister.id','Rep.id'])->join([
                        'Rep' => ['table' => 'data_sales_representative', 'type' => 'LEFT', 'conditions' => 'Rep.user_id = DataAssignedToRegister.representative_id'],
                    ])->where(['Rep.deleted' => 0,'DataAssignedToRegister.manual' => 0])->order(['DataAssignedToRegister.id' => 'DESC'])->first();
                    
                    $findRep = $this->DataSalesRepresentative->find()->select(['User.uid','DataSalesRepresentative.user_id'])->join([
                        'User' => ['table' => 'sys_users', 'type' => 'LEFT', 'conditions' => 'User.id = DataSalesRepresentative.user_id'],
                    ])->where(['DataSalesRepresentative.id >' => $assigned['Rep']['id'], 'DataSalesRepresentative.deleted' => 0,'User.deleted' => 0,'DataSalesRepresentative.sales_person' => 1])->first();
                        
                    if (empty($findRep)) {
                        $findRep = $this->DataSalesRepresentative->find()->select(['User.uid','DataSalesRepresentative.user_id'])->join([
                            'User' => ['table' => 'sys_users', 'type' => 'LEFT', 'conditions' => 'User.id = DataSalesRepresentative.user_id'],
                        ])->where(['DataSalesRepresentative.deleted' => 0,'User.deleted' => 0, 'DataSalesRepresentative.sales_person' => 1])->first();
                    }

                    $testRep = $this->DataAssignedToRegister->find()->where(['DataAssignedToRegister.user_id' => $arrUsers->id,'DataAssignedToRegister.deleted' => 0])->first();

                    if (!empty($findRep) && empty($testRep)) {

                        $array_save = array(
                            'representative_id' => $findRep->user_id,
                            'user_id' => $arrUsers->id,
                            'created' => date('Y-m-d H:i:s'),
                            'manual' => 0,
                            'deleted' => 0,
                        );

                        $entity = $this->DataAssignedToRegister->newEntity($array_save);
                        if(!$entity->hasErrors()){
                            $this->DataAssignedToRegister->save($entity);
                        }
                    }

                    $this->Response->success();
                }else{
                    $this->Response->message('User is already active but he is deleted, verify');
                    return;
                }
                
            }
        }
    }


    public function get_agreement() {

        $ent_user = $this->SysUsers->find()->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();
        if(empty($ent_user)){
            return;
        }

        // pr($ent_user); exit;
        $this->loadModel('Admin.DataAgreements');

        $ent_reviews = $this->DataAgreements->find()
         ->join([
                'Agreement' => ['table' => 'cat_agreements', 'type' => 'LEFT', 'conditions' => 'Agreement.uid = DataAgreements.agreement_uid'],
            ])
         ->where(['DataAgreements.user_id' => $ent_user->id, 'Agreement.agreement_type' => 'REGISTRATION'])->first();
        
        $this->Response->success();
        $this->Response->set('data', $ent_reviews);
    }

     public function get_wn() {

        $fields['referred_by'] = "(SELECT CONCAT(SU.name,' ',SU.lname) FROM data_sales_representative_register DSR LEFT JOIN sys_users SU ON SU.id = DSR.representative_id WHERE DSR.user_id = SysUsers.id LIMIT 1)";
        $ent_user = $this->SysUsers->find()->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();
        $this->Response->set('user_id', $ent_user->id);
        if(empty($ent_user)){
            return;
        }

        // pr($ent_user); exit;


        $this->loadModel('Admin.DataWn');

        $ent_reviews = $this->DataWn->find()->where(['DataWn.user_id' => $ent_user->id])->first();

        
        if (!empty($ent_reviews)) {

            $this->Response->success();
            $this->Response->set('data', $ent_reviews);
            

        } 
        
    }

    public function save_wn() {

        $this->loadModel('Admin.DataWn');

        $ent_data = $this->DataWn->find()->where(['DataWn.id' => get('id',0)])->first();

        if (!empty($ent_data)) {

             $save_array = array(
                'id' => $ent_data->id,
                'bname' => get('bname',''),
                'name' => get('name',''),
                'address' => get('address',''),
                'city' => get('city',''),
                'payee' => get('payee',''),
                'fatca' => get('fatca',''),
                'ein' => get('ein',''),
                'tax' => get('tax',''),
                'ssn' => get('ssn',''),
                'requesters' => get('requesters',0),
                'account' => get('account',''),
            );


            $c_entity = $this->DataWn->newEntity($save_array);
            if(!$c_entity->hasErrors()) {
                if ($this->DataWn->save($c_entity)) {
                    $this->Response->success();

                    $ent_user = $this->SysUsers->find()->where(['SysUsers.id' => $ent_data->user_id])->first();
                    if(!empty($ent_user)){
                        $ent_user->login_status = 'READY';
                        $this->SysUsers->save($ent_user);
                    }
                }
            }
        } else {

            $ent_user = $this->SysUsers->find()->where(['SysUsers.id' => get('uuid',''),'SysUsers.deleted' => 0])->first();
            
            if(empty($ent_user)){
                return;
            }

             $save_array = array(
                'uid' => Text::uuid(),
                'user_id' => $ent_user->id,
                'bname' => get('bname',''),
                'name' => get('name',''),
                'address' => get('address',''),
                'city' => get('city',''),
                'payee' => get('payee',''),
                'fatca' => get('fatca',''),
                'ein' => get('ein',''),
                'tax' => get('tax',''),
                'ssn' => get('ssn',''),
                'requesters' => get('requesters',0),
                'account' => get('account',''),
            );

            $c_entity = $this->DataWn->newEntity($save_array);
            if(!$c_entity->hasErrors()) {
                if ($this->DataWn->save($c_entity)) {
                    $this->Response->success();

                    $ent_user_save = $this->SysUsers->find()->where(['SysUsers.id' => get('uuid',''),'SysUsers.deleted' => 0])->first();
                    if(!empty($ent_user_save)){
                        $ent_user_save->login_status = 'READY';
                        $this->SysUsers->save($ent_user_save);
                    }               
                }
            }

        }
    }

    public function amounts() {

        
        $fields = ['SysUsers.id'];
        $fields['treatment'] = "(SELECT SUM(amount) FROM data_treatment DT WHERE DT.patient_id = SysUsers.id)";
        $fields['gfe'] = "(SELECT SUM(amount) FROM data_consultation DC WHERE DC.patient_id = SysUsers.id)";
        

        $ent_user = $this->SysUsers->find()->select($fields)
            ->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->toArray();

        $gfe = 0;
        $treatment = 0;

        if(!empty($ent_user)){
            $gfe = isset($ent_user[0]->gfe) ? $ent_user[0]->gfe : 0;
            $treatment = isset($ent_user[0]->treatment) ? $ent_user[0]->treatment : 0;
        }
        
        $this->Response->success();
        $this->Response->set('treatment', round($treatment / 100,2));
        $this->Response->set('gfe', round($gfe / 100,2));
    }


    public function treatments() {

        $ent_user = $this->SysUsers->find()->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();
        if(empty($ent_user)){
            return;
        }

        $this->loadModel('SpaLiveV1.DataTreatment');

        $fields = ['DataTreatment.uid','DataTreatment.schedule_date','DataTreatment.status','DataTreatment.payment'];
        $fields['assistance'] = "(SELECT CONCAT_WS(' ', U.name, U.lname) FROM sys_users U WHERE U.id = DataTreatment.assistance_id)";
        $fields['notes'] = "(SELECT notes FROM data_treatment_notes DTN WHERE DTN.treatment_id = DataTreatment.id LIMIT 1)";
        $_where = ['DataTreatment.deleted' => 0];
        $_where['DataTreatment.status !='] = "DONE";
        $_where['DataTreatment.patient_id'] = $ent_user->id;
        $_where['DataTreatment.status !='] = "CANCEL";
       

        $certTreatment = $this->DataTreatment->find()->select($fields)
            ->where($_where)->order(['DataTreatment.id' => 'DESC'])->all();
                        
        $arr_treatments = array();
        if (!empty($certTreatment)) {
            foreach ($certTreatment as $row) {
                    $arr_treatments[] = array(
                        'uid' => $row['uid'],
                        'date' => $row['schedule_date']->i18nFormat('yyyy-MM-dd HH:mm'),
                        'assistance' => $row['assistance'],
                        'status' => $row['status'],
                        'payment' => empty($row['payment']) ? 'No' : 'Yes',
                        'notes' => $row['notes'],
                    );
            }
            $this->Response->success();
            $this->Response->set('data', $arr_treatments);
        }
    

    }

    public function gfe() {

        $ent_user = $this->SysUsers->find()->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();
        if(empty($ent_user)){
            return;
        }

        $fields = ['DataConsultation.uid','DataConsultation.payment','DataConsultation.schedule_date','DataCertificates.uid','DataCertificates.date_start','DataCertificates.date_expiration','DataConsultation.assistance_id'];
        $fields['assistance'] = "(SELECT CONCAT(UP.name,' ',UP.lname) FROM sys_users UP WHERE UP.id = DataConsultation.assistance_id)";
        $fields['expirate_soon'] = "(IF(DATEDIFF(NOW(), DataCertificates.date_expiration) < 30,1,0))";

        $_where = ['DataConsultation.deleted' => 0];
        $_where['DataConsultation.patient_id'] = $ent_user->id;
        // $_where['DataConsultation.status'] = "DONE";
        // $_where['DataConsultation.status'] = "CERTIFICATE";
        $_where['OR'] = [['DataConsultation.status' => "DONE"], ['DataConsultation.status' => "CERTIFICATE"]];
        
    
        $this->loadModel('SpaLiveV1.DataConsultation');
        $certItem = $this->DataConsultation->find()->select($fields)
        ->join([
            'DataCertificates' => ['table' => 'data_certificates', 'type' => 'LEFT', 'conditions' => 'DataCertificates.consultation_id = DataConsultation.id'],
        ])
        ->where($_where)->order(['DataConsultation.id' => 'DESC'])->all();
        
        $arr_certificates = array();
        if (!empty($certItem)) {
           foreach ($certItem as $row) {

                $assin = $row['assistance_id'] > 0 ? $row['assistance'] : 'OVERRIDEN GFE';
                $arr_certificates[] = array(
                    'consultation_uid' => $row['uid'],
                    'payment' => empty($row['payment']) ? 'No' : 'Yes',
                    'certificate_uid' => empty($row['payment']) ? "" : ($row->DataCertificates['uid'] != null ? $row->DataCertificates['uid'] : ""),
                    'date_start' => empty($row->DataCertificates['date_start']) ? $row['schedule_date']->i18nFormat('yyyy-MM-dd') : $row->DataCertificates['date_start'],
                    'date_expiration' => empty($row->DataCertificates['date_expiration']) ? "" : $row->DataCertificates['date_expiration'],
                    'assistance_name' => $assin,
                    'expirate_soon' => false//isset($row['expirate_soon']) ? ($row['expirate_soon'] == 1 ? true : false) : '',
                );
            
            }
            
            $this->Response->set('data', $arr_certificates);
            $this->Response->success();
        }

    }

    public function load_notes() {
        $html = '';

        $this->loadModel('SysUsers');
        $ent_user = $this->SysUsers->find()
        ->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();

        if (!empty($ent_user)) {
            $this->loadModel('DataUsersNotes');
            $ent_notes = $this->DataUsersNotes->find()->where(['DataUsersNotes.user_id' => $ent_user->id])->first();
            if (!empty($ent_notes)) {
                $html = $ent_notes->notes;
            }

            $result = array(
                'uid' => $ent_user->uid,
                'name' => $ent_user->name . ' ' . $ent_user->lname . ' (' . $ent_user->email . ')',
                'notes' => $html
            );

            $this->Response->success();
            $this->Response->set('data', $result);
        }
    }

    public function save_notes() {




        $notes = get('notes','');

        $this->loadModel('SysUsers');
        $ent_user = $this->SysUsers->find()
        ->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();

        if (!empty($ent_user)) {



            // if (!MASTER) {
            //     $entSysUser = $this->DataAssignedToRegister->find()->join([
            //         'SysUsers' => ['table' => 'sys_users','type' => 'INNER','conditions' => 'SysUsers.id = DataAssignedToRegister.representative_id'],
            //         'SysUserAdmin' => ['table' => 'sys_users_admin','type' => 'INNER','conditions' => 'SysUserAdmin.username = SysUsers.email AND SysUserAdmin.user_type = "PANEL"'],
            //     ])->where(['DataAssignedToRegister.user_id' => $ent_user->id,'DataAssignedToRegister.deleted' => 0])->first();
            //     if (empty($entSysUser)) {
            //         $this->Response->message('You dont have permissions.');
            //         return;
            //     }
            // }

            $this->loadModel('DataUsersNotes');
            $ent_notes = $this->DataUsersNotes->find()->where(['DataUsersNotes.user_id' => $ent_user->id])->first();
            $n_id = 0;
            if (!empty($ent_notes)) {
                $n_id = $ent_notes->id;
            }

            $save_array = array(
                'id' => $n_id,
                'notes' => $notes,
                'user_id' => $ent_user->id
            );

            $c_entity = $this->DataUsersNotes->newEntity($save_array);
            if(!$c_entity->hasErrors()) {
                if ($this->DataUsersNotes->save($c_entity)) {
                    $this->Response->success();
                }
            }

        }

    }
    
    function unique_multidim_array($array, $key) {
        $temp_array = array();    
        $i = 0;    
        $key_array = array();            
    
        foreach($array as $val) {    
            if (!in_array($val[$key], $key_array)) {    
                $key_array[$i] = $val[$key];    
                $temp_array[$i] = $val;    
                $i++;    
            }    
        }    
        return $temp_array;    
    }

    

    private function getLicensesInjector($injector_id){
        $this->loadModel('SysLicence');
        $licenses = $this->SysLicence->find()->select(['SysLicence.type','SysLicence.number','SysLicence.start_date','SysLicence.exp_date'])->where(['SysLicence.user_id' => $injector_id])->toArray();
        return $licenses;
    }

    private function updateCIMonth($result, $user_id){
        $this->loadModel('SysUsers');
        $this->loadModel('DataInjectorMonth');
        $ent_user = $this->SysUsers->find()
        ->where(['SysUsers.id' => $user_id,'SysUsers.deleted' => 0, 'SysUsers.active' => 1])->first();

        if(empty($ent_user)) return; 
        if($ent_user->login_status != 'READY') return;

        $first_day = date('Y-m-01');
        $last_day = date('Y-m-t');
        $_where = ["(DataInjectorMonth.date_injector BETWEEN '{$first_day}' AND '{$last_day}')", 'DataInjectorMonth.deleted' => 0];

        $injMonth = $this->DataInjectorMonth->find()->where($_where)->first();
        
        if (!empty($injMonth)) {
            if($ent_user->id == $injMonth->injector_id){
                if ($result == 1) return;
                else {
                    $this->DataInjectorMonth->updateAll(
                        ['deleted' => 1],
                        ['id' => $injMonth->id]
                    );
                    return;
                }
            } 
        }

        if ($result == 0) return;

        $arrSave = [
            'injector_id' => $ent_user->id,
            'state' => $ent_user->state,
            'date_injector' => date('Y-m-d')
        ];

        
        $this->DataInjectorMonth->updateAll(
            ['deleted' => 1],
            ['deleted' => 0]
        );
        

        $inj_entity = $this->DataInjectorMonth->newEntity($arrSave);
        if(!$inj_entity->hasErrors()){
            if($this->DataInjectorMonth->save($inj_entity)){
                $this->Response->success();
            }
        }
    }

}  

