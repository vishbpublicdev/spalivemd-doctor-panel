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

class SettingsController extends AppController
{

    /**
	 * @var string|null Mailgun API key
	 */
	protected $mailgunKey = null;

    protected function getMailgunKey(): ?string
    {
        if ($this->mailgunKey === null) {
            $this->mailgunKey = env('MAILGUN_API_KEY');
        }
        return $this->mailgunKey;
    }

    public function initialize() : void{
        parent::initialize();

        $this->loadModel('Admin.SysUsers');

        $this->URL_API = env('URL_API', 'https://api.myspalive.com/');
    }

    public function grid( $data_csv = true, $grid_type = " ")
    {
        $this->loadModel('Admin.SysUsers');

        $page = intval(get('page', 1));
        $limit = get('limit', 300);
        if ($data_csv == false){
            $limit = get('limit', 1500);
        }else {
            $limit = get('limit', 50);
        }

        //'READY','APPROVE','REJECT','W9','CHANGEPASSWORD','PAYMENT'
       /*  $status_array = array(
            'PAYMENT' => 'PAYMENT PENDING',
            'APPROVE' => 'WAITING FOR APPROVAL',
            'REJECT' => 'REJECTED REGISTRATION',
            'W9' => 'W9 PENDING',
            'CHANGEPASSWORD' => 'UPDATE PASSWORD PENDING',
            'READY' => 'READY',
            '' => '',
        ); */
        
        $_having = [];
        $_where = ['SysUsers.deleted' => 0, 'State.id' => 43];

        $_join = [
            'State' => ['table' => 'cat_states','type' => 'INNER','conditions' => 'State.id = SysUsers.state'],
            'Admin' => ['table' => 'sys_users_admin', 'type' => 'LEFT', 'conditions' => 'Admin.id = SysUsers.md_id'],            
        ];

        

        if (USER_ID != 1) {
            $assigned_id = 0;
            if (USER_ID == 91) $assigned_id = 5015;
            if (USER_ID == 114) $assigned_id = 8468;
            //if (USER_ID == 104) $assigned_id = 6101;
            if ($assigned_id > 0) {
                $_join['Rep'] = ['table' => 'data_assigned_to_register','type' => 'INNER','conditions' => 'Rep.user_id = SysUsers.id AND Rep.deleted = 0'];
                $_where['Rep.representative_id'] = $assigned_id;
            }
        }

        $str_mfilter = get('mfilter','');
        if ($str_mfilter == 'DELETED USERS') {
            $_where = ['SysUsers.deleted' => 1];
        }

        $str_type = get('type',$grid_type);
        if (!empty($str_type)) {
            if ($str_type == 'patient') {
                if (!empty($str_mfilter) && $str_mfilter == 'INJECTORS') {
                    $_where['SysUsers.type'] = 'injector';
                    $_where[] = 'WL.id IS NOT NULL';
                } else {
                    $_where['SysUsers.type'] = 'patient';
                    $_where['OR'] =  
                        array('SysUsers.type' => 'patient','WL.id IS NOT NULL');
                }
               

                // $fields['weight_loss_patient'] = "(SELECT DCOS.id FROM data_consultation_other_services DCOS WHERE DCOS.patient_id = SysUsers.id AND DCOS.payment <> '' LIMIT 1)";
                $_join['WL'] = ['table' => 'data_consultation_other_services','type' => 'LEFT','conditions' => 'WL.patient_id = SysUsers.id AND WL.payment <> ""'];
            } else {
                $_where['SysUsers.type'] = $str_type;
            }

        }
        //filter: [{"property":"query","value":"jimmy"}]
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

        if (!empty($str_mfilter)) {
            if ($str_mfilter == 'ACTIVE USERS') {
                $_where['SysUsers.active'] = 1;
                $_where['SysUsers.deleted'] = 0;
            } else if ($str_mfilter == 'INACTIVE USERS') {
                $_where['SysUsers.active'] = 0;
            } else if ($str_mfilter == 'CP W/BASIC AND WITHOUT ADVANCED') {
                $_where['SysUsers.active'] = 1;
                $_having[] = ['cp_basic >=' => 1, 'cp_advanced' => 0];
                // $_having[] = ['pay_basic >=' => 1, 'pay_advanced' => 0, 'pay_advanced_p' => 0];
            } else if ($str_mfilter == 'YELLOW') {
                $_where['OR'] = [['SysUsers.steps' => 'CODEVERIFICATION', 'SysUsers.active' => 1], 
                        ['SysUsers.steps' => 'PAYMENTMETHOD', 'SysUsers.active' => 1], 
                        ['SysUsers.steps' => 'HOWITWORKS', 'SysUsers.active' => 1], 
                        ['SysUsers.steps' => 'BASICCOURSE', 'SysUsers.active' => 1], 
                        ['SysUsers.steps' => 'WAITINGSCHOOLAPPROVAL', 'SysUsers.active' => 1]
                    ];
            } else if ($str_mfilter == 'WHITE') {
                //$_where['OR'] = [['SysUsers.steps' => 'SELECTREFERRED' , 'SysUsers.active' => 1]];
            } else if ($str_mfilter == 'ORANGE') {
                $_where['OR'] = [['SysUsers.steps' => 'MSLSUBSCRIPTION' , 'SysUsers.active' => 1], 
                        ['SysUsers.steps' => 'MDSUBSCRIPTION' , 'SysUsers.active' => 1], 
                        ['SysUsers.steps' => 'W9', 'SysUsers.active' => 1], 
                        ['SysUsers.steps' => 'CPR', 'SysUsers.active' => 1]
                    ];
            } else if ($str_mfilter == 'BLUE') {
                $_where['OR'] = [['SysUsers.steps' => 'ADVANCEDCOURSE', 'SysUsers.active' => 1], 
                        ['SysUsers.steps' => 'SELECTADVANCEDCOURSE', 'SysUsers.active' => 1], 
                        ['SysUsers.steps' => 'SELECTBASICCOURSE', 'SysUsers.active' => 1], 
                        ['SysUsers.steps' => 'MATERIALS', 'SysUsers.active' => 1],
                        ['SysUsers.steps' => 'SELECTREFERRED' , 'SysUsers.active' => 1]
                    ];
            } else if ($str_mfilter == 'RED') {
                $_where['OR'] = [['SysUsers.steps' => 'TRACERS' ], 
                        ['SysUsers.steps' => 'DENIED'], 
                        ['SysUsers.steps' => 'STATENOTAVAILABLE'],
                        ['SysUsers.active' => 0]
                    ];
            } else if ($str_mfilter == 'GREEN') {
                $_where['OR'] = [['SysUsers.steps' => 'HOME' , 'SysUsers.active' => 1], 
                        ['SysUsers.steps' => 'STRIPEACCOUNT' , 'SysUsers.active' => 1], 
                        ['SysUsers.steps' => 'TREATMENTSETTINGS' , 'SysUsers.active' => 1]
                    ];
            } else if ($str_mfilter == 'LIKELY NOT AN INJECTOR') {
                $_where['SysUsers.active'] = 1;
                $_where['SysUsers.deleted'] = 0;
                //$_having = ['likely_injector IS NULL'];
                //$_having[] = ['likely_injector' => 0];
            } else if ($str_mfilter == 'LOOKING FOR PROVIDER') {
                $_where['OR'] = ['SysUsers.treatment_type' => 'OPENREQUETS']; 
                //$_having = ['likely_injector IS NULL'];
                //$_having[] = ['likely_injector' => 0];
            } else if ($str_mfilter == 'KNOWS A PROVIDER') {
                $_where['OR'] = ['SysUsers.treatment_type' => 'ONEBYONE']; 
                //$_having = ['likely_injector IS NULL'];
                //$_having[] = ['likely_injector' => 0];
            } else if ($str_mfilter == 'NONE') {
                $_where['OR']  = [['SysUsers.treatment_type IS NULL'],
                ['SysUsers.treatment_type' => ''],
                ['SysUsers.treatment_type' => 'none'],
                ['SysUsers.treatment_type' => 'NONE']]; 
                //$_having = ['likely_injector IS NULL'];
                //$_having[] = ['likely_injector' => 0];
            } 
            else if ($str_mfilter == 'WEIGHT LOSS') {
                // $_having[] = ['wl_status <>' => ''];
                $_having[] = ['is_wl >' => 0];
            }
            // else if ($str_mfilter == 'PENDING BASIC PURCHASE') {
            //     $_having[] = ['basic_course_payment' => '0'];
            // }
            else if ($str_mfilter == 'WEIGHT LOSS SPECIALIST') {                
                $_having[] = ['is_wls >' =>  0];
            }
            else if ($str_mfilter == 'SCHOOLS') {                
                $_having[] = ['with_school >' =>  0];
            }
        }

        $str_mOrder = get('mOrder', '');
        if(!empty($str_mOrder)){
            if($str_mOrder == 'READY'){
                $_where['SysUsers.login_status'] = 'READY';
            }
            else if($str_mOrder == 'W9 PENDING'){
                $_where['SysUsers.login_status'] = 'W9';
            }
            else if($str_mOrder == 'WAITING FOR APPROVAL'){
                $_where['SysUsers.login_status'] = 'APPROVE';
            }
            else if($str_mOrder == 'PAYMENT PENDING'){
                $_where['SysUsers.login_status'] = 'PAYMENT';
            }
            else if($str_mOrder == 'REJECTED REGISTRATION'){
                $_where['SysUsers.login_status'] = 'REJECT';
            }
            else if($str_mOrder == 'CHANGEPASSWORD'){
                $_where['SysUsers.login_status'] = 'CHANGEPASSWORD';
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

        $fields = ['SysUsers.uid','SysUsers.id','SysUsers.short_uid','SysUsers.active','SysUsers.name','SysUsers.mname','SysUsers.lname','SysUsers.email','SysUsers.bname','SysUsers.score','SysUsers.phone','SysUsers.created','SysUsers.modified','SysUsers.login_status','SysUsers.type',
                'SysUsers.steps','SysUsers.latitude','SysUsers.longitude','SysUsers.stripe_account','SysUsers.stripe_account_confirm', 'SysUsers.photo_id','SysUsers.city','State.name', 'SysUsers.show_in_map', 'SysUsers.show_most_review','SysUsers.last_status_change',
                'SysUsers.tracers','SysUsers.sales_rep_status','SysUsers.treatment_type','SysUsers.provider_url', 'SysUsers.speak_spanish','Admin.name'];
        
        $fields['invited_by'] = "(SELECT CONCAT(SU.name,' ',SU.lname) FROM data_network_invitations DTI LEFT JOIN sys_users SU ON SU.id = DTI.parent_id WHERE DTI.email LIKE SysUsers.email AND SU.deleted = 0 AND SU.active = 1 LIMIT 1)";

        //$fields['referred_by'] = "(SELECT CONCAT(SU.name,' ',SU.lname) FROM data_sales_representative_register DSR LEFT JOIN sys_users SU ON SU.id = DSR.representative_id WHERE DSR.user_id = SysUsers.id AND DSR.deleted = 0 LIMIT 1)";

        $fields['assigned_to'] = "(SELECT CONCAT(SU.name,' ',SU.lname) FROM data_assigned_to_register DSR LEFT JOIN sys_users SU ON SU.id = DSR.representative_id WHERE DSR.user_id = SysUsers.id AND DSR.deleted = 0 LIMIT 1)";

        //$fields['version'] = "(SELECT CONCAT(AK.type, ' | ', AD.app_version, ' | ', date_format(AD.created, '%c/%e/%Y %H:%i') ) FROM api_debug AD LEFT JOIN api_keys AK ON AK.id = AD.key_id WHERE AD.createdby = SysUsers.id AND AD.app_version <> '' GROUP BY AD.id ORDER BY AD.id DESC LIMIT 1)";
        $fields['version'] = "(SELECT CONCAT(AK.type, ' | ', AD.app_version, ' | ', date_format(AD.created, '%c/%e/%Y %H:%i') ) FROM sys_users_versions AD LEFT JOIN api_keys AK ON AK.id = AD.key_id WHERE AD.createdby = SysUsers.id AND AD.app_version <> '' LIMIT 1)";
        
        

        $fields['comments'] = "(SELECT SUBSTRING(`notes`, 1, 50) FROM data_users_notes DUN WHERE DUN.user_id = SysUsers.id)";
        $fields['welcome_call'] = "(SELECT SUBSTRING(`notes`, 1, 50) FROM data_users_welcome_call DUN WHERE DUN.user_id = SysUsers.id)";
 

        $fields['training_attended'] = "(SELECT DT.attended FROM data_trainings DT JOIN cat_trainings CT ON CT.id = DT.training_id WHERE DT.user_id = SysUsers.id AND CT.level = 'LEVEL 1' AND DT.deleted = 0 AND CT.deleted = 0 ORDER BY CT.scheduled DESC LIMIT 1)";
        $fields['scheduled_training_att'] = "(SELECT DATE_FORMAT(CT.scheduled, '%m-%d-%Y') AS date FROM data_trainings DT JOIN cat_trainings CT ON CT.id = DT.training_id WHERE DT.user_id = SysUsers.id AND CT.level = 'LEVEL 1' AND DT.deleted = 0 AND CT.deleted = 0 ORDER BY CT.scheduled DESC LIMIT 1)";

        if ($str_type == 'injector') {
            $fields['basic_course_payment'] = "
            (SELECT DP.total FROM
                data_payment DP WHERE
                DP.id_from = SysUsers.id AND
                DP.type = 'BASIC COURSE' AND DP.prod = 1 AND DP.is_visible = 1 AND DP.payment <> ''
                ORDER BY DP.id DESC LIMIT 1
            )
            ";

            $fields['payment_platform'] = "
            (SELECT DP.payment_platform FROM
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

            // $fields['funnel'] = '(
            //     SELECT CASE
            //         WHEN register_reserve = 1 THEN "RESERVE NOW"
            //         WHEN register_discount = 1 THEN "LOOKING FOR DISCOUNT"
            //             WHEN register_pay_later = 1 THEN "BUY NOW PAY LATER"
            //         ELSE ""
            //     END 
            //     FROM data_analytics DA WHERE DA.phone = SysUsers.phone AND SysUsers.deleted = 0 LIMIT 1)'; 
                
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

            $fields['school_cert'] = "(SELECT COUNT(id) FROM data_courses WHERE user_id = SysUsers.id AND deleted = 0 AND status = 'DONE')";

            // $fields['purchase_before'] = "(SELECT COUNT(DatPur.id) FROM data_purchases DatPur INNER JOIN data_purchases_detail DatPurDet ON DatPurDet.purchase_id = DatPur.id WHERE DatPur.user_id = SysUsers.id AND DatPur.payment <> '' AND DatPurDet.product_id NOT IN (SELECT CatTreat.product_id FROM data_treatment DatTreat INNER JOIN data_treatment_detail TrtDet ON TrtDet.treatment_id = DatTreat.id INNER JOIN cat_treatments_ci CatTreat ON CatTreat.id = TrtDet.cat_treatment_id WHERE DatTreat.assistance_id = SysUsers.id))";
            $fields['is_wls'] = "(SELECT COUNT(id) FROM data_users_other_services_check_in where deleted  = 0 and  user_id = SysUsers.id )";

            $fields['with_school'] = "(
                SELECT COUNT(DC.id)
                FROM data_courses DC
                    INNER JOIN cat_courses CC ON CC.id = DC.course_id
                    INNER JOIN data_school_register DSR ON DSR.id = CC.school_id
                    INNER JOIN sys_users U ON U.id = DC.user_id
                WHERE DC.user_id = SysUsers.id
                    AND DC.deleted = 0
                    AND U.deleted = 0
                    AND U.active = 1
                    AND CC.deleted = 0
                    AND DSR.deleted = 0
                ORDER BY DC.id DESC
            )";

            $fields['iv_therapy_status'] = "(SELECT dt.attended FROM cat_trainings ct INNER JOIN data_trainings dt ON dt.training_id = ct.id WHERE ct.level = 'LEVEL IV' AND dt.user_id = SysUsers.id)";

        } else if ($str_type == 'clinic') {

            $fields['last_exam'] = "(SELECT DC.schedule_date FROM data_consultation DC WHERE DC.createdby = SysUsers.id AND DC.status = 'CERTIFICATE' ORDER BY DC.schedule_date DESC LIMIT 1)";

        } else if ($str_type == 'patient') {

            $fields['last_exam'] = "(SELECT DC.schedule_date FROM data_consultation DC WHERE DC.patient_id = SysUsers.id AND DC.status = 'CERTIFICATE' ORDER BY DC.schedule_date DESC LIMIT 1)";

            //$fields['weight_loss_patient'] = "(SELECT DCOS.id FROM data_consultation_other_services DCOS WHERE DCOS.patient_id = SysUsers.id AND DCOS.payment <> '' LIMIT 1)";

            $fields['last_treatment'] = "(SELECT DT.schedule_date FROM data_treatment DT WHERE DT.patient_id = SysUsers.id AND DT.status = 'DONE' ORDER BY DT.schedule_date DESC LIMIT 1)";

            $fields['crby'] = "(SELECT CONCAT(SU.name,' ',SU.lname) FROM sys_users SU WHERE SU.id = SysUsers.createdby LIMIT 1)";
            
            $fields['credit'] = "(SELECT DP.id FROM data_payment DP WHERE DP.prepaid = 1 AND DP.id_from = SysUsers.id AND DP.payment <> '' AND DP.is_visible = 1 AND DP.service_uid = '' LIMIT 1)";

            $fields['first_treatment_uber'] = "(SELECT DT.type_uber FROM data_treatment DT WHERE DT.patient_id = SysUsers.id AND DT.`status`= 'DONE' LIMIT 1)";
            
            $fields['patient_model'] = "(SELECT COUNT(*) FROM data_model_patient dmp where dmp.email = SysUsers.email and dmp.deleted = 0)";
            // $fields['patient_model'] = "(SELECT COUNT(*) FROM data_model_patient dmp where dmp.email = SysUsers.email and  dmp.status = 'assigned' and  dmp.registered_training_id >  0  and deleted =0)";
            // Checks if the patient's name matches the name of any injectors      
            // $fields['likely_injector'] = "(SELECT COUNT(SU.id) FROM sys_users SU WHERE SU.type = 'injector' AND (SU.name = SysUsers.name) AND (SU.lname = SysUsers.lname) LIMIT 1)";          
            $fields['likely_injector'] = "(SELECT SU.id FROM sys_users SU WHERE SU.type = 'injector' AND (SU.name = SysUsers.name) AND (SU.lname = SysUsers.lname) LIMIT 1)";  
            
            $fields['register'] = "(SELECT IFNULL(dap.register, 0) register FROM data_analytics_patients dap WHERE dap.phone = SysUsers.phone LIMIT 1)";
            $fields['register_origin'] = "(SELECT dap.origin FROM data_analytics_patients dap WHERE dap.phone = SysUsers.phone LIMIT 1)";
            $fields['source'] = "(SELECT IFNULL(source, 'Other') source FROM sys_users_register sur WHERE sur.user_id = SysUsers.id LIMIT 1)";
            $fields['source_user'] = "( SELECT su.id
                                        FROM data_analytics_patients dap 
                                            INNER JOIN sys_users su ON su.phone = dap.phone
                                            AND su.phone != ''
                                        WHERE su.phone = SysUsers.phone
                                        LIMIT 1 )";
            
            $fields['recomended_by'] = "(SELECT CONCAT(R.name, ' ', R.lname)
                                        FROM data_referred_other_services OS
                                            INNER JOIN sys_users U ON U.id = OS.user_id
                                            INNER JOIN sys_users R ON R.id = OS.referred_id
                                        WHERE OS.user_id = SysUsers.id
                                        ORDER BY OS.id DESC
                                        LIMIT 1)";
            
            $fields['recomended_by_2'] = "(SELECT CONCAT(U.name, ' ', U.lname)
                                        FROM data_patient_clinic PC
                                            INNER JOIN sys_users U ON U.id = PC.injector_id
                                        WHERE PC.user_id = SysUsers.id 
                                            AND PC.type = 'weightloss'
                                        ORDER BY PC.id DESC 
                                        LIMIT 1)";
            
            $fields['recomended_by_3'] = "(SELECT CONCAT(U.name, ' ', U.lname)
                                        FROM data_patient_clinic PC
                                            INNER JOIN sys_users U ON U.id = PC.injector_id
                                        WHERE PC.user_id = SysUsers.id 
                                            AND PC.type = 'neurotoxin'
                                        ORDER BY PC.id DESC 
                                        LIMIT 1)";
            
            $fields['wl_call_title'] = "(SELECT call_title FROM data_other_services_check_in WHERE patient_id = SysUsers.id ORDER BY id DESC LIMIT 1)";            
            $fields['wl_status'] = "(SELECT status FROM data_other_services_check_in WHERE patient_id = SysUsers.id ORDER BY id DESC LIMIT 1)";
            $fields['wl_status_pu'] = "(SELECT PU.status FROM data_other_services_check_in CI LEFT JOIN data_purchases PU ON PU.id = CI.purchase_id WHERE CI.patient_id = SysUsers.id ORDER BY CI.id DESC, PU.id DESC LIMIT 1)";

            $fields['sales_rep'] = "(SELECT CONCAT(U.name, ' ', U.lname)
                                            FROM data_sales_team_patients DSTP
                                                INNER JOIN sys_users U ON U.id = DSTP.sales_team_id
                                            WHERE DSTP.patient_id = SysUsers.id AND DSTP.deleted = 0
                                            ORDER BY DSTP.id DESC
                                            LIMIT 1)";

            // $fields['is_wl'] = "(SELECT COUNT(id) FROM sys_patients_other_services WHERE patient_id = SysUsers.id AND deleted = 0 AND type = 'WEIGHT LOSS')";
            $fields['is_wl'] = "(
                SELECT 
                    (SELECT COUNT(id) 
                    FROM sys_patients_other_services 
                    WHERE patient_id = SysUsers.id AND deleted = 0 AND type = 'WEIGHT LOSS')
                    +
                    (SELECT COUNT(id)  FROM data_other_services_check_in WHERE patient_id = SysUsers.id)
                    AS cant)
            ";
            
                        
        } else if ($str_type == 'examiner') {
            $fields['last_exam'] = "(SELECT DC.schedule_date FROM data_consultation DC WHERE DC.assistance_id = SysUsers.id AND DC.status = 'DONE' ORDER BY DC.schedule_date DESC LIMIT 1)";
            $fields['weight_loss'] = "(SELECT aprovied FROM data_examiners_other_services WHERE user_id = SysUsers.id AND deleted = 0 LIMIT 1)";
            $fields['mint'] = "(SELECT Count(id) FROM data_examiners_clinics WHERE user_id = SysUsers.id AND deleted = 0 LIMIT 1)";
            $fields['mint_aprovied'] = "(SELECT aprovied FROM data_examiners_clinics WHERE user_id = SysUsers.id AND deleted = 0 LIMIT 1)";
        }
        
        

        //$arrUsers = $this->SysUsers->find()->select($fields)->join($_join)->where($_where)->having($_having)->order($order)->limit($limit)->page($page)->all();
        // print_r($arrUsers); exit;
        //$this->log(json_encode(debug($arrUsers)));
        //$arrUsersCount = $this->SysUsers->find()->select($fields)->join($_join)->where($_where)->having($_having)->order($order)->count();
        
        //$this->log(json_encode(debug($arrUsers)));
        $arrUsersCount = $this->SysUsers->find()->select($fields)->join($_join)->where($_where)->having($_having)->order($order)->count();
        
        $page_min = ceil($arrUsersCount / $limit);
        if ($page > $page_min){
            $page = intval($page_min);
        }
        if($page < 1){ $page = 1; }
        
        $arrUsers = $this->SysUsers->find()->select($fields)->join($_join)->where($_where)->having($_having)->order($order)->limit($limit)->page($page)->all();

        $count_lnai = 0;

        if($str_mfilter == "LIKELY NOT AN INJECTOR"){
            $arrUsers2 = $this->SysUsers->find()->select($fields)->join($_join)->where($_where)->having($_having)->order($order)->limit($limit)->all();
            foreach ($arrUsers2 as $row){
                if($row['likely_iñnjector'] != ''){
                    $count_lnai++;
                }
            }
        }

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
            /* if ($str_type == 'examiner') {
                $str_status = $row['login_status'];
            } */
            if ($str_mfilter == 'DELETED USERS') {
                $str_status = 'DELETED';
            }

            if($str_mfilter == 'LIKELY NOT AN INJECTOR' && $row['likely_injector'] != ''){
                continue;
            }
            // if ($str_type == 'injector' && $row['weight_loss_patient']) {
            //     continue;
            // }
            
            if ($str_type == 'patient' && $row['type'] == 'injector') {
                $str_status = '';
            }

            $show_in_map = $row['show_in_map'] == 0 ? 1 : $row['show_in_map'];
            $nname = !empty(trim($row['mname'])) ? $row['name'] . ' ' . trim($row['mname']) . ' ' . trim($row['lname']) : $row['name'] . ' ' . trim($row['lname']);

            $query_schools = "
                SELECT DSR.nameschool
                FROM data_courses DC
                    INNER JOIN cat_courses CC ON CC.id = DC.course_id
                    INNER JOIN data_school_register DSR ON DSR.id = CC.school_id
                WHERE DC.user_id = " . $row['id'] . "
                    AND DC.deleted = 0
                ORDER BY DC.id DESC
            ";

            $schools = '';
            $schools_sql = $this->SysUsers->getConnection()->execute($query_schools)->fetchAll('assoc');    
            foreach($schools_sql as $school){
                if($schools == '')
                    $schools = $school['nameschool'];
                else
                    $schools = $schools . ', ' . $school['nameschool'];
            }
            
            $add_array = array(
                "uid" => $row['uid'],
                "id" => $row['id'],
                "background" => $background, 
                "basic_course_date" => isset($row['basic_course_date']) ? $row['basic_course_date'] : '',
                "basic_course_payment" => isset($row['basic_course_payment']) ? $row['basic_course_payment'] / 100 : '0',   
                "first_treatment_uber" => $row['first_treatment_uber'],
                "invited_by" => $row['invited_by'],
                //"referred_by" => $row['referred_by'],
                "assigned_to" => $row['assigned_to'],
                "short_uid" => $row['short_uid'],
                "active" => $row['active'],
                "name" => $nname,
                "user" => $row['email'],
                "bname" => $row['bname'],
                // "funnel" => $row['funnel'],
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
                'weight_loss' => isset($row['weight_loss']) ? $row['weight_loss'] : '',
                'comments' => isset($row['comments']) ? $row['comments'] : '',
                'welcome_call' => isset($row['welcome_call']) ? $row['welcome_call'] : '',
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
                'sales_rep_status' => $row['sales_rep_status'],
                'likely_injector' => $row['likely_injector'],
                'treatment_type' => $row['treatment_type'],                
                'provider_url' => $row['provider_url'], 
                'subscriptions' => $row['subscriptions'],
                'subscriptions_cancel' => intval($row['subscriptions_cancel']),
                'subscriptions_hold' => intval($row['subscriptions_hold']),
                'patient_model' => $row['patient_model'],    
                'scheduled_training' => !empty($row['scheduled_training']) ? $row['scheduled_training'] : '',     
                'scheduled_training_att' => !empty($row['scheduled_training_att']) ? $row['scheduled_training_att'] : date('m-d-Y'),     
                'training_attended' => !empty($row['training_attended']) ? $row['training_attended'] : '',
                'cp_advanced' => !empty($row['cp_advanced']) ? $row['cp_advanced'] : '0',
                'today' => date('m-d-Y'),
                'speak_spanish' => $row['speak_spanish'],
                'source' => $row['register'] == 1 ? 'Came from ads (' . $row['register_origin'] . ')' : ($row['source'] ? $row['source'] : ($row['source_user'] ? 'Other' : '')),
                // 'recomended_by' => $recomended_by,
                'payment_platform' => $row['payment_platform'] == 'stripe' || $row['payment_platform'] == '' ? 'MySpaLive' : 'Affirm',
                'sales_rep' => $row['sales_rep'],
                'wl_call_title' => $row['wl_call_title'],
                'wl_status' => $row['wl_status'],
                'wl_status_pu' => $row['wl_status_pu'],
                'recommended_by' => $row['recomended_by'],
                'recommended_by_2' => $row['recomended_by_2'],
                'recommended_by_3' => $row['recomended_by_3'],
                'md_name' =>  $row['Admin']['name'],
                'mint_subscribed' => !empty($row['mint']) ? 'Yes' : 'No',
                'schools' => $schools,
                'mint_aprovied' => isset($row['mint_aprovied']) ? $row['mint_aprovied'] : '',
                'school_cert' => $row['school_cert'],
                'cp_basic' => $row['cp_basic'],
                'is_wl' => $row['is_wl'],
                'with_school' => $row['with_school'],
                'iv_therapy_status' => $row['iv_therapy_status'],
            );

            if($str_type == 'injector'){
                $add_array['licenses'] = json_encode($this->getLicensesInjector($row['id']));

                //WEIGHT LOSS SPECIALIST
                $this->loadModel('DataUsersOtherServicesCheckIn');

                $where = ['DataUsersOtherServicesCheckIn.deleted' => 0, 'DataUsersOtherServicesCheckIn.user_id' => $row['id']];

                $entity_wls = $this->DataUsersOtherServicesCheckIn->find()->select(['DataUsersOtherServicesCheckIn.status'])->where($where)->first();

                if(!empty($entity_wls)){
                    if($entity_wls->status=="AVAILABLEDAYS"){
                        $add_array['weight_loss_specialist'] = "APPLIED";
                    }else if($entity_wls->status=="WLSHOME"){
                        $this->loadModel('DataScheduleModel');

                        $where = ['DataScheduleModel.deleted' => 0, 'DataScheduleModel.model' => 'other_services', 'DataScheduleModel.injector_id' => $row['id']];
                
                        $injector_days = $this->DataScheduleModel->find()->where($where)->all();

                        if(count($injector_days)>=5){
                            $add_array['weight_loss_specialist'] = "COMPLETED";
                        }else{
                            $add_array['weight_loss_specialist'] = "Did not select his 5 days";
                        }
                    }else if($entity_wls->status=="CANCELLED"){                        
                        $add_array['weight_loss_specialist'] = "CANCELLED";
                    }else {
                        $add_array['weight_loss_specialist'] = "";
                    }                    
                }

                //OTHER SCHOOLS
                if($str_status=="CERTIFICATESCHOOLAPPROVED"){
                    $this->loadModel('DataCourses');

                    $_fields = [
                        'DataCourses.status',
                        'Course.type',
                        'Course.school_id',
                    ];

                    $where_os = ['Course.deleted' => 0, 'Course.type' => "NEUROTOXINS BASIC", 'DataCourses.deleted' => 0, 
                                'DataCourses.user_id' => $row['id'], 'DataCourses.status' => "DONE"];

                    $_join_os = [
                        'Course' => ['table' => 'cat_courses', 'type' => 'LEFT', 'conditions' => 'Course.id = DataCourses.course_id'],            
                    ];

                    $entity_os = $this->DataCourses->find()->select($_fields)->where($where_os)->join($_join_os)->first();

                    if(!empty($entity_os)){
                        $add_array['register_other_school'] = "BASIC";
                    }else{
                        $add_array['register_other_school'] = "ADVANCED";
                    }
                }
                
            }

            if ($str_type == 'patient') {
                $this->loadModel('SysPatientsOtherServices');
                $pat_reg = $this->SysPatientsOtherServices->find()->where([
                    'SysPatientsOtherServices.patient_id' => $row['id']
                ])->first();

                $tr = '';
                if(!empty($pat_reg)){
                    $add_array['type_registration'] = $pat_reg->type;
                    $tr = $pat_reg->type;
                }else{
                    $add_array['type_registration'] = "NEUROTOXIN";
                    $tr = "NEUROTOXIN";
                }
                
                if ($row['type'] == 'injector') {
                    $add_array['type_registration'] = 'WEIGHT LOSS';
                }
            }

            $recomended_by = $row['recomended_by'];
            if(empty($recomended_by)) {
                $recomended_by = $row['recomended_by_2'];            
            }
            if(!empty($row['recomended_by_3']) && ($tr == 'WEIGHT LOSS')) {
                $recomended_by = 'X';
            }

            $add_array['recomended_by'] = $recomended_by;

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

            if($str_mfilter == "LIKELY NOT AN INJECTOR"){
                $arrUsersCount = ($arrUsersCount - $count_lnai);
            }
            $this->Response->set('total', $arrUsersCount);
            //$this->Response->set('total', count($response_array));

            $totals = $this->getSummaryUsersByStatus($str_type, "");
            $totals['total'] = $arrUsersCount;
            
            $this->Response->set('summary', $totals);
        }
    }

    public function gridgfeci()
    {
        $this->loadModel('SpaLiveV1.DataRequestGfeCi');
        //'READY','APPROVE','REJECT','W9','CHANGEPASSWORD','PAYMENT'
        // $status_array = array(
        //     'INIT' => 'WAITING FOR APPROVAL',
        //     'REJECTED' => 'REJECTED REGISTRATION',
        //     'READY' => 'READY',
        //     '' => '',
        // );


        $_where = ['SysUsers.deleted' => 0 ];
        $_where_two = ['SysUsers.deleted' => 0 ];
         $_join = [
            'State' => ['table' => 'cat_states','type' => 'INNER','conditions' => 'State.id = SysUsers.state'],
        ];

        if (USER_ID != 1) {
            $assigned_id = 0;
            if (USER_ID == 91) $assigned_id = 5015;
            if (USER_ID == 114) $assigned_id = 8468;
            if (USER_ID == 104) $assigned_id = 6101;
            if ($assigned_id > 0) {
                $_join['Rep'] = ['table' => 'data_assigned_to_register','type' => 'INNER','conditions' => 'Rep.user_id = SysUsers.id AND Rep.deleted = 0'];
                $_where['Rep.representative_id'] = $assigned_id;
            }
        }



        $str_mfilter = get('mfilter','');
        if ($str_mfilter == 'DELETED USERS') {
            $_where = ['SysUsers.deleted' => 1];
            $_where_two = ['SysUsers.deleted' => 1];
        }


        $str_type = get('type','');
        if (!empty($str_type)) {
            $_where['SysUsers.type'] = $str_type;
            $_where_two['SysUsers.type'] = $str_type;
        }
        //filter: [{"property":"query","value":"jimmy"}]
        if (get('filter','')) {
            $arr_filter = json_decode(get('filter'),true);
            if ($arr_filter[0]['property'] == "query") {

                $search = $arr_filter[0]['value'];
                $_where['OR'] = [['SysUsers.name LIKE' => "%$search%"], ['SysUsers.lname LIKE' => "%$search%"],['SysUsers.short_uid LIKE' => "%$search%"], ['SysUsers.email LIKE' => "%$search%"],['SysUsers.phone LIKE' => "%$search%"]];
                $_where_two['OR'] = [['SysUsers.name LIKE' => "%$search%"], ['SysUsers.lname LIKE' => "%$search%"],['SysUsers.short_uid LIKE' => "%$search%"], ['SysUsers.email LIKE' => "%$search%"],['SysUsers.phone LIKE' => "%$search%"]];
            }
        }


        if (!empty($str_mfilter)) {
            if ($str_mfilter == 'ACTIVE USERS') {
                $_where['SysUsers.active'] = 1;
            } else if ($str_mfilter == 'INACTIVE USERS') {
                $_where['SysUsers.active'] = 0;
            }
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
                    } else  if ($e_sort['property'] == "filter_icons") {
                        $order['filter_icons'] = $e_sort['direction'];
                    } else  if ($e_sort['property'] == "user_icons") {
                        $order['user_icons'] = $e_sort['direction'];
                    } 
                }
            }
        }

        $this->loadModel('DataTreatmentReview');
        $most_reviewed = $this->DataTreatmentReview->injectorMostReviewed();
        $arr_most_reviewed = implode(",", $most_reviewed);


        $fields = ['SysUsers.id','SysUsers.uid','SysUsers.phone','SysUsers.short_uid','SysUsers.active','SysUsers.name','SysUsers.name','SysUsers.mname','SysUsers.mname','SysUsers.lname','SysUsers.type','SysUsers.email','SysUsers.latitude','SysUsers.longitude','SysUsers.bname','SysUsers.created','SysUsers.modified','SysUsers.login_status','DataRequestGfeCi.status','DataRequestGfeCi.created','SysUsers.show_in_map','SysUsers.city','State.name','SysUsers.score','SysUsers.show_most_review', 'SysUsers.steps','DataRequestGfeCi.approval_date','SysUsers.deleted',];
    
        //$fields['version'] = "(SELECT CONCAT(AK.type, ' | ', AD.app_version, ' | ', date_format(AD.created, '%c/%e/%Y %H:%i') ) FROM api_debug AD LEFT JOIN api_keys AK ON AK.id = AD.key_id WHERE AD.createdby = SysUsers.id AND AD.app_version <> '' GROUP BY AD.id ORDER BY AD.id DESC, AD.id DESC LIMIT 1)";
        $fields['version'] = "(SELECT CONCAT(AK.type, ' | ', AD.app_version, ' | ', date_format(AD.created, '%c/%e/%Y %H:%i') ) FROM sys_users_versions AD LEFT JOIN api_keys AK ON AK.id = AD.key_id WHERE AD.createdby = SysUsers.id AND AD.app_version <> '' LIMIT 1)";
        
        $fields['last_training'] = "(SELECT CONCAT(CT.title,'|',CT.scheduled) FROM data_trainings DT JOIN cat_trainings CT ON CT.id = DT.training_id WHERE DT.user_id = SysUsers.id AND DT.deleted = 0 AND CT.deleted = 0 ORDER BY CT.scheduled DESC LIMIT 1)";

        $fields['last_treatment'] = "(SELECT  DT.schedule_date FROM data_treatment DT WHERE DT.assistance_id = SysUsers.id AND DT.status = 'DONE' AND DT.deleted = 0 ORDER BY DT.schedule_date DESC LIMIT 1)";

        $fields['assigned_to'] = "(SELECT CONCAT(SU.name,' ',SU.lname) FROM data_assigned_to_register DSR LEFT JOIN sys_users SU ON SU.id = DSR.representative_id WHERE DSR.user_id = SysUsers.id AND DSR.deleted = 0 LIMIT 1)";

        $fields['subscriptions'] = "(SELECT COUNT(DS.id) FROM data_subscriptions DS WHERE DS.user_id = SysUsers.id AND DS.deleted = 0 AND DS.status = 'ACTIVE')";

        $fields['subscriptions_cancel'] = "(SELECT COUNT(DS.id) FROM data_subscriptions DS WHERE DS.user_id = SysUsers.id AND DS.deleted = 0 AND DS.status = 'CANCELLED')";

        $fields['treatments'] = "(SELECT GROUP_CONCAT(CT.name) FROM cat_treatments_ci CT INNER JOIN data_treatments_prices DTP ON CT.id = DTP.treatment_id WHERE DTP.user_id = SysUsers.id)";

        $fields['last_purchase'] = "(SELECT DP.created FROM data_purchases DP WHERE DP.user_id = SysUsers.id AND DP.payment <> '' ORDER BY DP.created DESC LIMIT 1)";

        $fields['n_treatments'] = "(SELECT COUNT(id) FROM data_treatment DT WHERE DT.assistance_id = SysUsers.id AND DT.status = 'DONE' AND DT.deleted = 0 AND DT.payment <> '')";

        $fields['a_treatments'] = "(SELECT SUM(total) FROM data_payment DP WHERE DP.id_from = SysUsers.id AND DP.id_to = 0 AND DP.payment <> '' AND DP.is_visible = 1  AND DP.type = 'TREATMENT')";

        $fields['n_purchases'] = "(SELECT COUNT(id) FROM data_purchases DP WHERE DP.user_id = SysUsers.id AND DP.deleted = 0 AND DP.payment <> '')";

        $fields['a_purchases'] = "(SELECT SUM(total) FROM data_payment DP WHERE DP.id_from = SysUsers.id AND DP.id_to = 0 AND DP.payment <> '' AND DP.is_visible = 1  AND DP.type = 'PURCHASE')";
        
        $fields['paid_from'] = "(SELECT SUM(total) FROM data_payment DP WHERE DP.id_to = SysUsers.id AND DP.payment <> '' AND DP.is_visible = 1 AND DP.comission_payed = 1)";

        $fields['n_invitees'] = "(SELECT COUNT(DTI.id) FROM data_network_invitations DTI LEFT JOIN sys_users Usr ON Usr.email LIKE DTI.email WHERE DTI.parent_id = SysUsers.id)";

        $fields['comments'] = "(SELECT SUBSTRING(`notes`, 1, 50) FROM data_users_notes DUN WHERE DUN.user_id = SysUsers.id)";

        $fields['training_notes'] = "(SELECT SUBSTRING(`notes`, 1, 50) FROM data_users_training_notes DUN WHERE DUN.user_id = SysUsers.id)";

        $yesterday = FrozenTime::now()->subDays(1);
        $yesterday = $yesterday->i18nFormat('yyyy-MM-dd HH:mm:ss');
        $fields['all_treatments'] = "(SELECT COUNT(id) FROM data_treatment DT WHERE DT.assistance_id = SysUsers.id AND DT.deleted = 0)";
        $fields['rej_treatments'] = "(SELECT COUNT(id) FROM data_treatment DT WHERE DT.assistance_id = SysUsers.id AND DT.deleted = 0 AND DT.modified < '{$yesterday}' AND DT.status IN ('REJECT', 'INIT') )";

        $first_day = date('Y-m-01');
        $last_day = date('Y-m-t');
        $fields['is_ci_of_month'] = "(SELECT COUNT(InjM.id) FROM data_injector_month InjM WHERE InjM.injector_id = SysUsers.id AND InjM.deleted = 0 AND InjM.date_injector BETWEEN '{$first_day}' AND '{$last_day}')";

        $fields['most_reviewed'] = "(FIND_IN_SET(SysUsers.id,'{$arr_most_reviewed}'))";

        $fields['weight_loss'] = "(SELECT aprovied FROM data_examiners_other_services WHERE user_id = SysUsers.id AND deleted = 0 LIMIT 1)";
        $fields['mint_aprovied'] = "(SELECT aprovied FROM data_examiners_clinics WHERE user_id = SysUsers.id AND deleted = 0 LIMIT 1)";
        $fields['iv_therapy_status'] = "(SELECT dt.attended FROM cat_trainings ct INNER JOIN data_trainings dt ON dt.training_id = ct.id WHERE ct.level = 'LEVEL IV' AND dt.user_id = SysUsers.id LIMIT 1)";

        $is_dev = env('IS_DEV', false);
        if (!empty($str_mfilter)) {
            if ($str_mfilter == 'ACTIVE USERS') {
                $_where['SysUsers.active'] = 1;
                $_where['SysUsers.type'] = 'gfe+ci' ; 
                $_where['SysUsers.deleted'] = 0;                    

                $_where_two['SysUsers.active'] = 1;                
                $_where_two['SysUsers.deleted'] = 0;
            } else if ($str_mfilter == 'INACTIVE USERS') {
                $_where['SysUsers.active'] = 0;
                $_where['SysUsers.type'] = 'gfe+ci' ; 
                $_where['SysUsers.deleted'] = 0;                

                $_where_two['SysUsers.active'] = 0;                
                $_where_two['SysUsers.deleted'] = 0;
            }
        }else{
            $_where['SysUsers.type'] = 'gfe+ci' ; 
            $_where['SysUsers.deleted'] = 0;      
        }
        $gfeci = $this->SysUsers->find()->select(['SysUsers.id'])        
        ->where($_where)
        ->all();
        //$this->log(__LINE__ . " ". json_encode($gfeci));$this->log(__LINE__ . " ". json_encode($_where));$this->log(__LINE__ . " ". json_encode($_where_two));
        $ids_str;
        
            
        
        if($is_dev){
            $ag_uid = '5f1re6-asd2d0-6er854-6ewr51g-wae61few';
        }else{
            $ag_uid = 'sr5t413sw68r-rg56eg-we851g6we-bsd2gh-6e58rgw6g';
        }
        $ids_one = array(); $ids_two = array();
            $arrUsers = $this->SysUsers->find()->select($fields)
            ->join([
                'DataRequestGfeCi' => ['table' => 'data_request_gfe_ci', 'type' => 'INNER', 'conditions' => 'DataRequestGfeCi.user_id = SysUsers.id'],
                'State' => ['table' => 'cat_states','type' => 'INNER','conditions' => 'State.id = SysUsers.state'],
                'da' => ['table' => 'data_agreements','type' => 'INNER','conditions' => "da.user_id = SysUsers.id   and da.deleted = 0 and da.agreement_uid = '$ag_uid'"],
                ])
                ->where($_where_two)                        
                ->order($order)->all();            
                if(count($gfeci)>0){                    
                    $ids_two = collection($gfeci)->extract('id')->toArray();                                     
                }
                if(count($arrUsers)>0){
                    $ids_one = collection($arrUsers)->extract('id')->toArray();                        
                }
                $ids = array_merge($ids_one,$ids_two);    
                if(!empty($ids))    {
                    $arrUsers_gfe = $this->SysUsers->find()->select($fields)
                ->join([
                'DataRequestGfeCi' => ['table' => 'data_request_gfe_ci', 'type' => 'LEFT', 'conditions' => 'DataRequestGfeCi.user_id = SysUsers.id'],
                'State' => ['table' => 'cat_states','type' => 'INNER','conditions' => 'State.id = SysUsers.state'],
                'da' => ['table' => 'data_agreements','type' => 'LEFT','conditions' => "da.user_id = SysUsers.id   and da.deleted = 0 and da.agreement_uid = '$ag_uid'"],
                ])
                ->where(['SysUsers.id IN ' => $ids])                        
                ->order($order)->all();                                                    
                }else{
                    $arrUsers_gfe = array();
                }                                   
                
        $total_pend_gfeci=0;
        $total_ready_gfeci=0;                        
        $total=0;
        $total_inactive=0;
        $total_rejected=0;
        if (!empty($arrUsers_gfe)) {
            $inactive=false;
            $details = $this->unique_multidim_array($arrUsers_gfe,'id');
            //$this->log(__LINE__ ." ".json_encode($details));
            //$this->log(__LINE__ ." ".count($details));
            $response_array = array();

            //$total = count($details);
            foreach ($details as $row) {
                //if($row['deleted'] == 0 && $row['active'] == 1){
                    $total++;
                //}
                if($row['deleted'] == 0 && $row['active'] == 0){
                    $total_inactive++;
                    $inactive=true;
                }

                $allTreat = $row['all_treatments'];
                $rejTreat = $row['rej_treatments'];
                $percTreat = 0;
                if($allTreat > 0){
                    $percTreat = ($rejTreat * 100)/$allTreat;
                }

                $str_application = 'CI+GFE';
                if ($row['type'] == 'injector')
                    $str_application = 'CI applying as a GFE';

                if ($row['type'] == 'examiner')
                    $str_application = 'GFE applying as a CI';

                $show_in_map = $row['show_in_map'] == 0 ? 1 : $row['show_in_map'];

                // $str_status = $status_array[strtoupper($row['DataRequestGfeCi']['status'])];
                $str_status = empty($row['steps']) ? '' : $row['steps'];
                if ($str_mfilter == 'DELETED USERS') {
                    $str_status = 'DELETED';
                }
                if(isset($row['DataRequestGfeCi']['status'])){
                    if($row['DataRequestGfeCi']['status'] == 'REJECTED'){
                        $str_status = 'REJECTED';
                        $total_rejected++;
                    }	
                    if($row['DataRequestGfeCi']['status'] == 'INIT'){
                        $total_pend_gfeci++;
                    }
                    if($row['type'] == 'gfe+ci' && $row['DataRequestGfeCi']['status'] == 'READY'){
                        $total_ready_gfeci++;
                    }/*else if ($inactive == false){
                        $total_inactive++;
                    }*/
                    
                }

                $add_array = array(
                    "id" => $row['id'],
                    "uid" => $row['uid'],
                    "short_uid" => $row['short_uid'],
                    "active" => $row['active'],
                    "name" => $row['name'] . ' ' . $row['mname'] . ' ' . $row['lname'],
                    "user" => $row['email'],
                    "bname" => $row['bname'],
                    "phone" => $row['phone'],
                    "score" => $row['score'],
                    "assigned_to" => $row['assigned_to'],
                    "latitude" => $row['latitude'],
                    "longitude" => $row['longitude'],
                    "version" => $row['version'],
                    "treatments" => $row['treatments'],
                    'state' => $row['State']['name'],
                    'city' => $row['city'],
                    'comments' => $row['comments'],
                    'training_notes' => $row['training_notes'],
                    "show_in_map" => intval($show_in_map),
                    "application" => $str_application,
                    "registration_date" => isset($row['DataRequestGfeCi']['created']) ? $row['DataRequestGfeCi']['created'] : '',
                    "last_date" => isset($row['modified']) ? $row['modified']->i18nFormat('yyyy-MM-dd HH:mm') : '',
                    "approval_date" => isset($row['DataRequestGfeCi']['approval_date']) ? $row['DataRequestGfeCi']['approval_date'] : '',
                    "login_status" => $str_status,
                    'n_treatments' => $row['n_treatments'] > 0 ? $row['n_treatments'] : 0,
                    'n_invitees' => $row['n_invitees'] > 0 ? $row['n_invitees'] : 0,
                    'a_treatments' => isset($row['a_treatments']) ? $row['a_treatments'] / 100 : 0,
                    'n_purchases' => $row['n_purchases'] > 0 ? $row['n_purchases'] : 0,
                    'a_purchases' => isset($row['a_purchases']) ? $row['a_purchases'] / 100 : 0,
                    'paid_from' => isset($row['paid_from']) ? $row['paid_from'] / 100 : 0,
                    "last_treatment" => $row['last_treatment'],
                    "last_purchase" => $row['last_purchase'],
                    "last_training" => $row['last_training'],
                    "is_ci_of_month" => $row['is_ci_of_month'] > 0 ? 1 : 0,
                    'most_reviewed' => ($row['show_most_review'] == 'DEFAULT') ? ($row['most_reviewed'] > 0 ? 1 : 0) : (($row['show_most_review'] == 'FORCED') ? 1 : 0),
                    "purchase_before" => $row['purchase_before'],
                    'flag' => (intval($row['n_purchases']) - intval($row['n_treatments'])) > 1 || $percTreat >= 10 ? 1 : 0,
                    'subscriptions' => $row['subscriptions'],
                    'subscriptions_cancel' => intval($row['subscriptions_cancel']),
                    'weight_loss' => isset($row['weight_loss']) ? $row['weight_loss'] : '',
                    'mint_aprovied' => isset($row['mint_aprovied']) ? $row['mint_aprovied'] : '',
                    'iv_therapy_status' => isset($row['iv_therapy_status']) ? $row['iv_therapy_status'] : '',
                );

                $response_array[] = $add_array;
            }
                $this->Response->success();
                $this->Response->set('data', $response_array);
        }else{
            $this->Response->success();
                $this->Response->set('data', []);
        }
        $summary = array(
            'total_pend_gfeci'=> $total_pend_gfeci,
            'total_ready_gfeci'=>$total_ready_gfeci,    
            'total'=>$total,
            'total_inactive'=>$total_inactive,
            'total_rejected'=>$total_rejected,
            
        );
        $this->Response->set('summary', $summary);
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
            $this->Response->set('summary', $this->getSummaryUsersByStatus($str_type));
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

    public function delete(){
        
        $recover = get('recover',false);

        $ent_user = $this->SysUsers->find()->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => $recover ? 1 : 0])->first();
        if(empty($ent_user)){
            // $this->message('Invalid Injector.');
            return;
        }
        
        /*if (!empty($ent_user)) {
            if (strpos(strtolower($ent_user->name), 'test') !== false || strpos(strtolower($ent_user->lname), 'test') !== false || strpos(strtolower($ent_user->mname), 'test') !== false) {
                 return;
             }
        }*/

        $ent_user->deleted = $recover ? 0 : 1;

        $this->SysUsers->save($ent_user);
        if(!$ent_user->hasErrors()){

                if($ent_user->type == 'injector')
                {                    
                    $this->loadModel('SpaLiveV1.AppTokens');
                    $token = uniqid('', true);

                    $array_save = array(
                        'token' => $token,
                        'user_id' => 1,
                        'user_role' => 'Panel',
                        'deleted' => 0,
                        'created' => date('Y-m-d H:i:s'),
                    );

                    $entity = $this->AppTokens->newEntity($array_save);
                    if(!$entity->hasErrors()){
                        $this->AppTokens->save($entity);
                    }
                    
                    $data = array(
                        'action'    => 'update_user_inactive_in_ghl',
                        'key'    => 'fdg32jmudsrfbqi28ghjsdodguhusdi',
                        'token' => $token,
                        'uid' => $ent_user->uid,
                    );
        
                    
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_URL, env('url_api', 'https://api.myspalive.com'));
                    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
                    curl_setopt($curl, CURLOPT_USERAGENT, 'SpaLiveMD Panel');
                    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
                    curl_setopt($curl, CURLOPT_POST, true); 
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        
                    $result = curl_exec($curl);
                    $json_result = json_decode($result,true);
                    
                    curl_close($curl);
                }

            if($recover){
                if($ent_user->active==1){
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
        
                    $testRep = $this->DataAssignedToRegister->find()->where(['DataAssignedToRegister.user_id' => $ent_user->id,'DataAssignedToRegister.deleted' => 0])->first();
        
                    if (!empty($findRep) && empty($testRep)) {
        
                        $array_save = array(
                            'representative_id' => $findRep->user_id,
                            'user_id' => $ent_user->id,
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
                    $this->Response->message('User recovered but is not active, verify.');
                    return;
                }
            }else{
                $this->Response->success();
            }   

        }else{
            $this->Response->add_errors('Internal Error.');
        }
    }

    public function bring_back()
    {            
        $ent_user = $this->SysUsers->find()->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 1])->first();
        if(empty($ent_user)){
            return;
        }

        $ent_user->deleted = 0;

        $this->SysUsers->save($ent_user);
        if(!$ent_user->hasErrors()){
            $this->Response->success();
        }else{
            $this->Response->add_errors('Internal Error.');
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

    public function get_data_certifications(){
        $entUser = $this->SysUsers->find()->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();
        if(empty($entUser)){
            return;
        }

        $usr_id = $entUser->id;

        $query_basic = "(SELECT 'MySpaLive' AS nameschool, DUC.file_id AS front FROM data_user_certification DUC WHERE DUC.user_id = " . $usr_id . " AND DUC.type = 'basic' ORDER BY DUC.id DESC LIMIT 1)";
        $basic_certification = $this->SysUsers->getConnection()->execute($query_basic)->fetchAll('assoc'); 

        $query_advanced = "(SELECT 'MySpaLive' AS nameschool, DUC.file_id AS front FROM data_user_certification DUC WHERE DUC.user_id = " . $usr_id . " AND DUC.type = 'advanced' ORDER BY DUC.id DESC LIMIT 1)";
        $advanced_certification = $this->SysUsers->getConnection()->execute($query_advanced)->fetchAll('assoc'); 

        // $query_certificates = "
        //     SELECT DSR.nameschool, CC.title, DC.front, DC.back, DC.status,
        //     (CASE WHEN DC.status = 'DONE' THEN 'Approved' WHEN DC.status = 'REJECTED' THEN 'Denied' WHEN DC.status = 'PENDING' THEN 'Pending' END) status
        //     FROM data_courses DC
        //         INNER JOIN cat_courses CC ON CC.id = DC.course_id
        //         INNER JOIN data_school_register DSR ON DSR.id = CC.school_id
        //     WHERE DC.deleted = 0
        //         AND DC.user_id = " . $usr_id . "
        //     ORDER BY DC.id DESC
        // ";

        // $arr_certificates = $this->SysUsers->getConnection()->execute($query_certificates)->fetchAll('assoc'); 
        
        $result = array();
        if ($basic_certification) {
            $result[] = array(
                'nameschool' => 'MySpaLive',
                'title' => 'Basic Certification',
                'front' => !empty($basic_certification) ? $basic_certification[0]['front'] : '',
                'back' => '',
                'status' => 'Approved',
            );
        }
        if ($advanced_certification) {
            $result[] = array(
                'nameschool' => 'MySpaLive',
                'title' => 'Advanced Certification',
                'front' => !empty($advanced_certification) ? $advanced_certification[0]['front'] : '',
                'back' => '',
                'status' => 'Approved',
            );
        }
        // foreach ($arr_certificates as $item) {
        //     $result[] = $item;
        // }

        $this->Response->success();
        $this->Response->set('data', $result);
    }

    public function check_tracers() {

        
        $ent = $this->SysUsers->find()
        ->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();

        if (empty($ent)) return;
        // if ($ent->login_status != 'APPROVE' && $ent->login_status != 'READY') return;


        /*$is_dev = env('IS_DEV', false);
        if($is_dev){
            $response = '{"criminalRecords":[],"criminalRecordCounts":0,"pagination":{"currentPageNumber":0,"resultsPerPage":10,"totalPages":0},"searchCriteria":[],"totalRequestExecutionTimeMs":152,"requestId":"7fd76d87-d6f6-4b4c-ba3d-2bbe65d4965d","requestType":"Search","requestTime":"2022-04-01T11:45:10.0431156-07:00","isError":false}';
            $ent->tracers = $response;
            $this->Response->success();    
            $this->Response->set('data', $response);
            $this->SysUsers->save($ent);
            return;
        }*/


        // $response = "";

        // $ap_name = "guardhub";
        // $ap_pass = "52a7724f7f334bc5afdaee73b95962db";
        // $url = "https://api.galaxysearchapi.com/CriminalSearch/V2";
        
        // if (empty($ent->dob)) {
        //     return;
        // }
        // $postData = [
        //     'FirstName' => $ent->name,
        //     'LastName' => $ent->lname,
        //     'Dob' => $ent->dob->i18nFormat('MM/dd/yyyy'),
        // ];
        
        
        // $curl = curl_init();
        //     curl_setopt_array($curl, array(
        //     CURLOPT_URL => $url,
        //     CURLOPT_SSL_VERIFYPEER => true,
        //     CURLOPT_RETURNTRANSFER => true,
        //     CURLOPT_POST => true,
        //     CURLOPT_POSTFIELDS => json_encode($postData),
        //     CURLOPT_HTTPHEADER => array(
        //         "galaxy-ap-name: " . $ap_name,
        //         "galaxy-ap-password: " . $ap_pass,
        //         "galaxy-search-type: CriminalV2",
        //         "content-type: application/json",
        //       ),
        // ));
        // $response = curl_exec($curl);

        // $err = curl_error($curl);
         
        // curl_close($curl);
        
        // if (!$err) {
        //     $ent->tracers = $response;
        //     $this->Response->success();    
        //     $this->SysUsers->save($ent);
        //     $this->Response->set('data', $response);
        
        // }

        $post_data = array(
            'login_id' => "StaffWizard",
            'state_id' => "UID",
            'password' => "1987CHECK"
        );
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://login.publicdata.com/pdmain.php/logon/checkAccess?disp=XML");
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        //Send CURL Request
        $result = curl_exec($curl);
        curl_close($curl);
        $xml = simplexml_load_string($result, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xml);
        $response_arr = json_decode($json, TRUE);

        $firstName = $ent->name;
        $lastName = $ent->lname;
        $dob = $ent->dob->i18nFormat('yyyyMMdd');
        // $dob = str_replace("/", "",$dob);
        $middlename = $ent->mname;
        if(empty($middlename) || $middlename == ''){
            $query_string = $firstName.' '.$lastName.' '.$dob;
        }
        else{
            $query_string = $firstName.' '.$middlename.' '.$lastName.' '.$dob;
        }
        
        $post_data = array(
            'login_id' => "StaffWizard",
            'input' => "grp_cri_advanced_name",
            'type' => "advanced",
            'p1' => $query_string,
            'matchany' => "all",
            'dlnumber' => $response_arr['user']['dlnumber'], //Guardhub
            'dlstate' => $response_arr['user']['dlstate'],
            'id' => $response_arr['user']['id'],
        
        );
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://login.publicdata.com/pdsearch.php?o=grp_master&disp=XML");
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        //Send CURL Request
        $result = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        //echo $result; exit;

        //$ci->common_model->add_record('hr_public_data_api_log', $log_data);
        //end
        $xml = simplexml_load_string($result, "SimpleXMLElement", LIBXML_NOCDATA);
        //$xml = simplexml_load_string($xml);
        $json = json_encode($xml);

        $response_arr = json_decode($json, TRUE);
        

        $response = array(
            'numrecords' => $response_arr['results']['@attributes']['numrecords'],
            'message' => isset($response_arr['results']['record']['disp_fld']) ? $response_arr['results']['record']['disp_fld'] : '',
            'ismore' => isset($response_arr['results']['@attributes']['ismore']) ? $response_arr['results']['@attributes']['ismore'] : '',
        );
        
        $response_arr['numrecords'] = $response_arr['results']['@attributes']['numrecords'];
        
        $response_json = json_encode($response_arr);



        //echo $json;
        //print_r($response_arr); exit;

        if (!$err) {
            $ent->tracers = $response_json;
            $this->Response->success();    
            $this->SysUsers->save($ent);
            $this->Response->set('data', $response_json);
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

    public function reviews() {

        $arrUsers = $this->SysUsers->find()->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();
        if(empty($arrUsers)){
            $this->Response->set('data', array());
            $this->Response->success();
            return;
        }
        
        if ($arrUsers->type == 'patient') {
            $_where = ['DataTreatmentReviews.createdby' => $arrUsers->id,'DataTreatmentReviews.deleted' => 0];
        } else {
            $_where = ['DataTreatmentReviews.injector_id' => $arrUsers->id,'DataTreatmentReviews.deleted' => 0];
        }

        $this->loadModel('Admin.DataTreatmentReviews');


        $ent_reviews = $this->DataTreatmentReviews->find()->where($_where)->all()->toArray();
        
        $arr_reviews = [];
        foreach ($ent_reviews as $row) {
                $arr_reviews[] = array(
                    'comments' => !empty($row['comments']) ? $row['comments'] : "No comments",
                    'score' => round($row['score']/10,1),
                    'created' => $row['created']->i18nFormat('MM-dd-yyyy'),
                );
        }
        $this->Response->success();
        $this->Response->set('data', $arr_reviews);

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

    public function amount_clinic() {

        $this->loadModel('Admin.DataConsultation');


        $ent_user = $this->SysUsers->find()
            ->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();

        if (empty($ent_user)) {
            return;
        }
        $fields = ['DataConsultation.uid','Injector.name','Injector.lname','Patient.name','Patient.lname','DataConsultation.amount','DataConsultation.schedule_date','DataConsultation.status','DataConsultation.payment'];
        $fields['treatments'] = "(SELECT GROUP_CONCAT(CT.name) FROM cat_treatments CT WHERE FIND_IN_SET(CT.id,DataConsultation.treatments))";
        $ent_con = $this->DataConsultation->find()->select($fields)->join([
            'Injector' => ['table' => 'sys_users','type' => 'LEFT','conditions' => 'Injector.id = DataConsultation.assistance_id'],
            'Patient' => ['table' => 'sys_users','type' => 'INNER','conditions' => 'Patient.id = DataConsultation.patient_id'],
        ])
         ->where(['DataConsultation.createdby' => $ent_user->id,'DataConsultation.deleted' => 0,'DataConsultation.payment <>' => ''])->all();
        $amount = 0;
        
        $result = array();
        foreach ($ent_con as $row) {
            $result[] = array(
                'uid' => trim($row['uid']),
                    'patient' => $row['Patient']['name'] . ' ' . $row['Patient']['lname'],
                    'examiner' => isset($row['Injector']['name']) ? $row['Injector']['name'] . ' ' . $row['Injector']['lname'] : '',
                    'schedule_date' => $row->schedule_date->i18nFormat('MM/dd/yyyy HH:mm'),
                    'amount' => '' . round($row['amount'] / 100,2),
                    'treatments' => $row['treatments'],
                    'comission' => 3,
            );
        }

        // if(!empty($ent_user)){
        //     $gfe = isset($ent_user[0]->gfe) ? $ent_user[0]->gfe : 0;
        //     $treatment = isset($ent_user[0]->treatment) ? $ent_user[0]->treatment : 0;
        // }
        
        $this->Response->success();
        $this->Response->set('amount', count($result) * 3);
        $this->Response->set('data', $result);
        // $this->Response->set('gfe', round($gfe / 100,2));
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

    public function map_info(){
        $this->loadModel('CatTreatmentsCi');
        $ent_treatments = $this->CatTreatmentsCi->find()->where(['CatTreatmentsCi.deleted' => 0])->join([
            'Exam' => ['table' => 'cat_treatments', 'type' => 'INNER', 'conditions' => 'Exam.id = CatTreatmentsCi.treatment_id']
        ])->toArray();
        

        $catTretaments = array();
        foreach ($ent_treatments as $row) {
            $t_array = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'details' => $row['details'],
                'exam_id' => 5,//$row['details'],
                'exam_name' => $row['details'],
            );

            $catTretaments[] = $t_array;
        }
        
        $this->loadModel('DataTreatmentReview');
        $most_reviewed = $this->DataTreatmentReview->injectorMostReviewed();
        $arr_most_reviewed = implode(",", $most_reviewed);

        $treatmentsID = json_decode(get('treatments','[]'));

        $_having = [];
        $_where = ['SysUsers.deleted' => 0, 'SysUsers.type IN' => array('injector','gfe+ci'),'SysUsers.login_status' => 'READY'];

        $name = get('name', '');
        if(isset($name)){
            $_where['OR'] = [['SysUsers.name LIKE' => "%$name%"], ['SysUsers.lname LIKE' => "%$name%"], ['SysUsers.email LIKE' => "%$name%"]];
        }

        $fields = ['SysUsers.bname','SysUsers.city','SysUsers.email','SysUsers.latitude','SysUsers.lname','SysUsers.longitude','SysUsers.mname','SysUsers.name','SysUsers.phone','SysUsers.photo_id','SysUsers.score','SysUsers.state','SysUsers.street','SysUsers.uid','SysUsers.zip','SysUsers.show_in_map','SysUsers.active'];

        $fields['treatments'] = "(SELECT GROUP_CONCAT(CTC.name,' $', ROUND(DTP.price / 100,2) SEPARATOR '\n') FROM data_treatments_prices DTP JOIN cat_treatments_ci CTC ON CTC.id = DTP.treatment_id WHERE DTP.user_id = SysUsers.id AND DTP.deleted = 0)";

        $fields['most_reviewed'] = "(FIND_IN_SET(SysUsers.id,'{$arr_most_reviewed}'))";

        $fields['comments'] = "(SELECT COUNT(id) FROM data_treatment_reviews DTR WHERE SysUsers.id = DTR.injector_id AND DTR.deleted = 0)";

        $fields['last_training'] = "(SELECT CONCAT(CT.title,'|',CT.scheduled) FROM data_trainings DT JOIN cat_trainings CT ON CT.id = DT.training_id WHERE DT.user_id = SysUsers.id AND DT.deleted = 0 AND CT.deleted = 0 ORDER BY CT.scheduled DESC LIMIT 1)";

        $first_day = date('Y-m-01');
        $last_day = date('Y-m-t');
        $fields['is_ci_of_month'] = "(SELECT COUNT(InjM.id) FROM data_injector_month InjM WHERE InjM.injector_id = SysUsers.id AND InjM.deleted = 0 AND InjM.date_injector BETWEEN '{$first_day}' AND '{$last_day}')";



        $joins = [];



        if(!empty($treatmentsID)){
            $fields['counting'] = "(COUNT( DISTINCT DataTreatment.treatment_id))";
            $joins['DataTreatment'] = [
                'table' => 'data_treatments_prices', 'type' => 'INNER', 'conditions' => 'DataTreatment.user_id = SysUsers.id'
            ];
            $_where['DataTreatment.treatment_id IN'] = $treatmentsID;    
            $_having = ['counting' => count($treatmentsID)];
        }

        // $field['d_prices'] = "(SELECT COUNT(TrP.id) FROM data_treatments_prices TrP WHERE TrP.user_id = SysUsers.id)";
        $joins['DSM'] = [
            'table' => 'data_schedule_model', 'type' => 'INNER', 'conditions' => 'DSM.injector_id = SysUsers.id AND DSM.deleted = 0'
        ];
        $_where['DSM.days <>'] = '';
        $_where[] = "(SELECT COUNT(TrP.id) FROM data_treatments_prices TrP WHERE TrP.user_id = SysUsers.id AND TrP.deleted = 0) > 0 ";




        //$join = "INNER JOIN data_schedule_model DSM ON DSM.injector_id = DC.id AND DSM.deleted = 0 ";
        //$conditions = " AND DSM.days <> '' AND (SELECT COUNT(TrP.id) FROM data_treatments_prices TrP WHERE TrP.user_id = DC.id) > 0 ";



        $arrUsers = $this->SysUsers->find()->select($fields)->distinct(['SysUsers.id'])->join($joins)->where($_where)->having($_having)->toArray();

        $arr_result = array();
        foreach ($arrUsers as $row) {
            $show_in_map = $row['show_in_map'] == 0 ? 1 : $row['show_in_map'];
            if ($show_in_map == 2) continue;
            if (($show_in_map == 1 && !empty($row['last_training']) && $row['active'] == 1) || ($show_in_map == 3)) {
                $arr_result[] = $row;
            }
            
        }

        // debug($arrUsers); exit;
        if (!empty($arrUsers)) {
             $this->Response->success();
             $this->Response->set('data', $arr_result);//$response_array);
             $this->Response->set('treatments', $catTretaments);//$response_array);
        }

    }

    public function zip(){


        $zip = get('zip',0);
        if ($zip == 0) {
            return;
        }

        
        require_once(ROOT . DS . 'vendor' . DS  . 'zipcodes' . DS . 'init.php');
        $data = isset(\zipcodes\Zipcodes::DATA[$zip]) ? \zipcodes\Zipcodes::DATA[$zip] : null;

        $data = isset(\zipcodes\Zipcodes::DATA[$zip]) ? \zipcodes\Zipcodes::DATA[$zip] : null;
            if ($data) {
                $latitude = $data['lat'];
                $longitude = $data['lng']; 
            } else {

                for($i = 1; $i <= 100; $i++) {
                     $nzip = intval($zip)+$i;
                     $bnzip = 5 - strlen(strval($nzip));
                     $rzip = $nzip;
                     if (strlen(strval($nzip)) < 5) {
                        for($c = 0; $c < $bnzip ;$c++) {
                            $rzip = '0' . $rzip;
                        }
                    }
                    
                     $data = isset(\zipcodes\Zipcodes::DATA[$rzip]) ? \zipcodes\Zipcodes::DATA[$rzip] : null;
                     if ($data) {
                        $latitude = $data['lat'];
                        $longitude = $data['lng'];
                        break;
                     }

                 }

            }
        if ($data) {
            
            $this->Response->set('latitude', $latitude);//$response_array);
            $this->Response->set('longitude', $longitude);//$response_array); 
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

    private function getSummaryUsersByStatus($userType, $join = ""){
        $condition = empty($userType) ? "" : " AND type = '{$userType}'";
        
        $gfe_colums = empty($join) ? "" : "
        ,(SELECT COUNT(U.id) FROM sys_users U ".$join." WHERE DRG.status = 'INIT' AND deleted = 0 AND active = 1) as total_pend_gfeci,
        (SELECT COUNT(U.id) FROM sys_users U ".$join." WHERE DRG.status = 'REJECTED' AND deleted = 0 AND active = 1) as total_rejct_gfeci,
        (SELECT COUNT(U.id) FROM sys_users U ".$join." WHERE DRG.status = 'READY' AND deleted = 0 AND active = 1) as total_ready_gfeci";

        $joinInjTrain = "
        JOIN data_trainings DT ON DT.user_id = U.id AND DT.deleted = 0 JOIN cat_trainings CT ON CT.id = DT.training_id AND CT.deleted = 0 
        ";

        $injtr_col = $userType == "injector" ? ",
        (SELECT COUNT(DISTINCT U.id) FROM sys_users U ".$joinInjTrain." WHERE U.login_status = 'READY' AND U.deleted = 0 AND U.active = 1 AND CT.scheduled > NOW()) as total_skd_train_ci,
        (SELECT COUNT(DISTINCT U.id) FROM sys_users U ".$joinInjTrain." WHERE U.login_status = 'READY' AND U.deleted = 0 AND U.active = 1 AND NOW() > CT.scheduled) as total_with_train_ci" 
        : "";
        
        $sql = "
        SELECT 
            (SELECT COUNT(U.id) FROM sys_users U ".$join." WHERE U.deleted = 0 AND U.active = 1 ".$condition.") as total,
            (SELECT COUNT(U.id) FROM sys_users U WHERE U.login_status = 'READY' AND U.deleted = 0 AND U.active = 1 ".$condition.") as total_ready,
            (SELECT COUNT(U.id) FROM sys_users U WHERE U.login_status = 'APPROVE' AND U.deleted = 0 AND U.active = 1 ".$condition.") as total_appr,
            (SELECT COUNT(U.id) FROM sys_users U WHERE U.login_status = 'REJECT' AND U.deleted = 0 AND U.active = 1 ".$condition.") as total_reject,
            (SELECT COUNT(U.id) FROM sys_users U WHERE U.login_status = 'W9' AND U.deleted = 0 AND U.active = 1 ".$condition.") as total_w9,
            (SELECT COUNT(U.id) FROM sys_users U WHERE U.login_status = 'CHANGEPASSWORD' AND U.deleted = 0 AND U.active = 1 ".$condition.") as total_pass,
            (SELECT COUNT(U.id) FROM sys_users U WHERE U.login_status = 'PAYMENT' AND U.deleted = 0 AND U.active = 1 ".$condition.") as total_payment,
            (SELECT COUNT(U.id) FROM sys_users U ".$join." WHERE U.active = 1 AND U.deleted = 0 ".$condition.") as total_active,
            (SELECT COUNT(U.id) FROM sys_users U ".$join." WHERE U.active = 0 AND U.deleted = 0 ".$condition.") as total_inactive
            {$gfe_colums}
            {$injtr_col}
        ";
    
        return $this->SysUsers->getConnection()->execute($sql)->fetchAll('assoc')[0];
    }

    public function get_all_users(){
        $page = intval(get('page', 1));
        $limit = get('limit', 50);

        $_where = ['SysUsers.deleted' => 0 ];
        $str_type = get('type','');
        if (!empty($str_type)) {
            $_where['SysUsers.type'] = $str_type;
        }
        //filter: [{"property":"query","value":"jimmy"}]
        if (get('filter','')) {
            $arr_filter = json_decode(get('filter'),true);
            if ($arr_filter[0]['property'] == "query") {

                $search = $arr_filter[0]['value'];
                $_where['OR'] = [['SysUsers.name LIKE' => "%$search%"], ['SysUsers.lname LIKE' => "%$search%"],['SysUsers.short_uid LIKE' => "%$search%"], ['SysUsers.email LIKE' => "%$search%"]];

            }
        }

        $_where['SysUsers.active'] = 1;
        $order = ['SysUsers.id' => 'DESC'];
        $fields = ['SysUsers.uid','SysUsers.id','SysUsers.short_uid','SysUsers.active','SysUsers.name','SysUsers.mname','SysUsers.lname','SysUsers.email','SysUsers.bname','SysUsers.score','SysUsers.phone','SysUsers.created','SysUsers.modified','SysUsers.login_status','SysUsers.latitude','SysUsers.longitude','SysUsers.stripe_account','SysUsers.stripe_account_confirm', 'SysUsers.photo_id','SysUsers.city','State.name'];


        //$fields['version'] = "(SELECT CONCAT(AK.type, ' | ', AD.app_version, ' | ', date_format(AD.created, '%c/%e/%Y %H:%i') ) FROM api_debug AD LEFT JOIN api_keys AK ON AK.id = AD.key_id WHERE AD.createdby = SysUsers.id AND AD.app_version <> '' GROUP BY AD.id ORDER BY AD.id DESC, AD.id DESC LIMIT 1)";
        $fields['version'] = "(SELECT CONCAT(AK.type, ' | ', AD.app_version, ' | ', date_format(AD.created, '%c/%e/%Y %H:%i') ) FROM sys_users_versions AD LEFT JOIN api_keys AK ON AK.id = AD.key_id WHERE AD.createdby = SysUsers.id AND AD.app_version <> '' LIMIT 1)";
        
        $_join = [
            'State' => ['table' => 'cat_states','type' => 'INNER','conditions' => 'State.id = SysUsers.state'],
        ];

        $arrUsers = $this->SysUsers->find()->select($fields)->join($_join)->where($_where)->order($order)->limit($limit)->page($page)->all();
        $arrUsersCount = $this->SysUsers->find()->where($_where)->count();

        
        $response_array = array();

        foreach ($arrUsers as $row) {
            $add_array = array(
                "uid" => $row['uid'],
                "id" => $row['id'],
                "short_uid" => $row['short_uid'],
                "active" => $row['active'],
                "name" => $row['name'] . ' ' . $row['mname'] . ' ' . $row['lname'],
                "user" => $row['email'],
                "bname" => $row['bname'],
                "score" => $row['score'],
                "phone" => $row['phone'],
                "version" => $row['version'] ? str_replace('version ','', $row['version']) : '',
                "latitude" => $row['latitude'],
                "longitude" => $row['longitude'],
                "photo_id" => $row['photo_id'],
                "last_date" => isset($row['modified']) ? $row['modified']->i18nFormat('yyyy-MM-dd HH:ss') : '',
                'credit' => isset($row['credit']) ? 1 : 0,
                'state' => $row['State']['name'],
                'city' => $row['city'],
            );
            $response_array[] = $add_array;
        }

        $this->Response->success();
        $this->Response->set('data', $response_array);
        $this->Response->set('total', $arrUsersCount);
    }

    public function show_inmap(){
        $this->loadModel('SysUsers');
        $ent_user = $this->SysUsers->find()
        ->where(['SysUsers.uid' => get('user_uid',''),'SysUsers.deleted' => 0])->first();

        $show = intval(get('show', -1));
        if($show == -1){
            $this->Response->add_errors('Invalid param.');
            return;
        }

        if(empty($ent_user)){
            $this->Response->add_errors('Invalid user.');
            return;
        }

        $this->SysUsers->updateAll(
            ['show_in_map' => $show,],
            ['id' => $ent_user->id]
        );
        $this->Response->success();
    }

    public function get_info_cert(){
        $this->loadModel('SpaLiveV1.CatTreatments');
        $this->loadModel('SpaLiveV1.CatQuestions');
        $ent_treatments = $this->CatTreatments->find()->where(['CatTreatments.deleted' => 0])->order(['CatTreatments.type_trmt','CatTreatments.id'])->all();
        $treatments = array();
        foreach ($ent_treatments as $row) {
            $treatments[] = array(
                'id' => $row['id'],
                'parent_id' => $row['parent_id'],
                'name' => $row['name'],
                'details' => $row['details'],
                'haschild' => $row['haschild'],
            );
        }

        $ent_questions = $this->CatQuestions->find()->where(['CatQuestions.deleted' => 0])->all();
        $quest = array();
        foreach ($ent_questions as $row) {
            $quest[] = array(
                'id' => $row['id'],
                'name' => $row['name'],
            );
            
        }

        $this->Response->set('treatments', $treatments);
        $this->Response->set('questions', $quest);
        $this->Response->success();
    }

    public function save_certificate(){
        $this->loadModel('DataConsultation');
        $this->loadModel('DataConsultationPlan');
        $this->loadModel('DataCertificates');
        $this->loadModel('CatTreatments');
        $this->loadModel('DataPayment');
        $this->loadModel('SysUsers');

        $patient = $this->SysUsers->find()->where(['SysUsers.uid' => get('user_uid', '')])->first();
        if(empty($patient)){
            $this->message('Invalid patient.');
            return;
        }

        $consultation_uid = Text::uuid();
        $arrTreatments = json_decode(get('treatments', '[]'), true);
        if(empty($arrTreatments)){
            $this->Response->add_messages('Invalid treatments.');
            return;
        }



        $str_treatments = implode(',', $arrTreatments);

        // $actual_treatments = $this->DataConsultationPlan->find()->join([
        //     'Consultation' => ['table' => 'data_consultation','type' => 'INNER','conditions' => 'Consultation.id = DataConsultationPlan.consultation_id'],
        // ])->where(['DataConsultationPlan.treatment_id IN ('.$str_treatments.')', 'DataConsultationPlan.proceed' => 1,'Consultation.patient_id' => $patient->id])->order('Consultation.created')->all();

        $actual_treatments = $this->DataConsultation->find()->join([
            'DataConsultationPlan' => ['table' => 'data_consultation_plan','type' => 'INNER','conditions' => 'DataConsultationPlan.consultation_id = DataConsultation.id AND DataConsultationPlan.proceed = 1'],
            'CatTreatments' => ['table' => 'cat_treatments','type' => 'INNER','conditions' => 'CatTreatments.id = DataConsultationPlan.treatment_id'],
        ])->where(['CatTreatments.parent_id IN ('.$str_treatments.')','DataConsultation.patient_id' => $patient->id])->group(['CatTreatments.id'])->all();


        $this->Response->set('need_confirm', false);
        if (count($actual_treatments) == count($arrTreatments) && get('force',0) == 0) {
            $this->Response->add_messages('The patient have a certificatee with one or more of the selected treatments.');
            $this->Response->set('need_confirm', true);
            return;
        }

        $str_treatments = implode(',', $arrTreatments);
        $array_save = array(
            'uid' => $consultation_uid,
            'patient_id' => $patient->id,
            'assistance_id' => -1,
            'treatments' => $str_treatments,
            'treatments_requested' => $str_treatments,
            'payment' => '-1',
            'meeting' => '',
            'meeting_pass' => '',
            'schedule_date' => date('Y-m-d H:i:s'),
            'status' => "CERTIFICATE",
            'schedule_by' => 0,
            'deleted' => 0,
            'participants' => 0,
            'createdby' => -1,
            'clinic_patient_id' => $patient->id,
            'payment_method' => '',
        );

        $entity_save = $this->DataConsultation->newEntity($array_save);
        $ent_consultation = $this->DataConsultation->save($entity_save);
        if($ent_consultation === false){
            $this->Response->add_messages('Error saving the consultation.');
            return;
        }

        $ent_consultation = $this->DataConsultation->find()->where(['DataConsultation.uid' => $consultation_uid])->first();
        if (empty($ent_consultation)) {
            $this->message('Invalid consultation.');
            return;
        }

        $consultation_id = $ent_consultation->id;
        $str_new_treatments = $str_treatments;
        $sep = '';

        if (count($arrTreatments) > 8) {
            $this->message('More than 8 tratments.');
            return;
        }

        $treatments = $this->CatTreatments->find()->select(['CatTreatments.id'])
                ->where([ 'CatTreatments.parent_id IN ('.$ent_consultation->treatments.')', 'CatTreatments.deleted' => 0 ])->toArray();

        foreach ($treatments as $row) {
            $array_save_a = array(
                'uid' => Text::uuid(),
                'consultation_id' => $consultation_id,
                'detail' => '',
                'treatment_id' => $row->id,
                'plan' => ' ',
                'proceed' => 1,
                'deleted' => 0,
            );
            
            $cp_entity = $this->DataConsultationPlan->newEntity($array_save_a);
            if(!$cp_entity->hasErrors())
                $this->DataConsultationPlan->save($cp_entity);
        }

        $oneYearOn = date('Y-m-d',strtotime(date("Y-m-d", time()) . " + 365 day"));

        $cert_uid = Text::uuid();

        $array_save_c = array(
            'uid' => $cert_uid,
            'consultation_id' => $consultation_id,
            'date_start' => Date('Y-m-d'),
            'date_expiration' => $oneYearOn,
            'deleted' => 0,
        );
    
        $cpc_entity = $this->DataCertificates->newEntity($array_save_c);
        if(!$cpc_entity->hasErrors()){
            $this->DataCertificates->save($cpc_entity);
        }

        $array_save_pay = array(
            'id_from' => $patient->id,
            'id_to' => 0,
            'uid' => Text::uuid(),
            'type' => 'GFE', //'CI REGISTER','PURCHASE','GFE','TREATMENT','COMISSION','REFUND'
            'intent' => '-1',
            'payment' => '-1',
            'receipt' => '',
            'discount_credits' => 0,
            'promo_discount' => '',
            'promo_code' =>  '',
            'subtotal' => 0,
            'total' => 0,
            'prod' => 1,
            'is_visible' => 1,
            'comission_payed' => 1,
            'comission_generated' => 0,
            'service_uid' => $consultation_uid,
            'prepaid' => 1,
            'created' => date('Y-m-d H:i:s'),
            'createdby' => -1,
        );

        $c_entity = $this->DataPayment->newEntity($array_save_pay);
        if(!$c_entity->hasErrors()) {
            $this->DataPayment->save($c_entity); 
        } else {

        }
        
        // if ($ent_consultation->payment != "") {
        //     $html_content = 'You can download your new certificate by click <a href="' . $this->URL_ROOT . 'panel/pdf_login/?uid=' .  $cert_uid . '" link style="color:#60537A;text-decoration:underline"><strong>here</strong></a>';

        //     // https://dev.spalivemd.com/panel/pdf_login/?uid=b1c646bb-839c-40fb-90eb-491a1cd33b20

        //     $arr_ddd = array(intval($ent_consultation->patient_id));
        //     if ($ent_consultation->patient_id != $ent_consultation->createdby) {
        //         $arr_ddd = array(intval($ent_consultation->patient_id),intval($ent_consultation->createdby));
        //     }
        //     $this->notify_devices('NEW_CERTIFICATE',$arr_ddd,true,true,true,array(),$html_content);

        // }

        $this->Response->success();

    }

    public function update_training_notes() {
        
        $trainig_notes = get('notes','');

        $notes = get('notes','');

        $status_rep_sales = get('status','');

        $this->loadModel('SysUsers');
        $ent_user = $this->SysUsers->find()
        ->where(['SysUsers.uid' => get('user_uid',''),'SysUsers.deleted' => 0])->first();

        if (!empty($ent_user)) {
            $this->loadModel('DataUsersTrainingNotes');
            $ent_notes = $this->DataUsersTrainingNotes->find()->where(['DataUsersTrainingNotes.user_id' => $ent_user->id])->first();
            $n_id = 0;
            if (!empty($ent_notes)) {
                $n_id = $ent_notes->id;
            }

            $save_array = array(
                'id' => $n_id,
                'notes' => $notes,
                'user_id' => $ent_user->id,
            );

            $this->SysUsers->updateAll(
                ['sales_rep_status' => $status_rep_sales], 
                ['SysUsers.id' => $ent_user->id]
            );

            $c_entity = $this->DataUsersTrainingNotes->newEntity($save_array);
            if(!$c_entity->hasErrors()) {
                if ($this->DataUsersTrainingNotes->save($c_entity)) {
                    $this->Response->success();
                }
            }

        }

    }

    public function get_csv_inactive(){
        $this->loadModel('SysUsers');

        $arr_conditions = array(['SysUsers.deleted' => 0, 'SysUsers.active' => 0, 'SysUsers.type' => 'injector', 'SysUsers.is_test' => 0]);
        $arr_result = array();

        $ent_preregister = $this->SysUsers->find()
        ->select([
            'SysUsers.uid',
            'SysUsers.email',
            'SysUsers.name',
            'SysUsers.mname',
            'SysUsers.lname',
        ])
        ->where($arr_conditions)
        ->group(['SysUsers.email'])
        ->order(['SysUsers.created' => 'DESC'])->toArray();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getCell('A1')->setValue('Name'); 
        $sheet->getStyle('A1')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 16,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('B1')->setValue('Emails');
        $sheet->getStyle('B1')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 16,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));

        $initIndex = 2;
        foreach ($ent_preregister as $item) {
            $sheet->getCell('A'.$initIndex)->setValue($item['name'] . ' ' . $item['lname']);
            $sheet->getCell('B'.$initIndex)->setValue($item['email']);
            $initIndex = $initIndex+1;
        }
        
        $writer = new Csv($spreadsheet);
        $writer->save(TMP . 'reports' . DS . "inactive_injectors.csv");
        if(isset($_SESSION['_User'])){
            $General = new GeneralController();            
            $General->email_ashlna_download_csv('inactive_injectors.csv' );
        } 
        $this->Files->output_file(TMP . 'reports' . DS . "inactive_injectors.csv");
        exit;
    }

    public function get_csv_active(){
        $this->loadModel('SysUsers');

        // $status_array = array(
        //     'PAYMENT' => 'PAYMENT PENDING',
        //     'APPROVE' => 'WAITING FOR APPROVAL',
        //     'REJECT' => 'REJECTED REGISTRATION',
        //     'W9' => 'W9 PENDING',
        //     'CHANGEPASSWORD' => 'UPDATE PASSWORD PENDING',
        //     'READY' => 'READY',
        //     '' => '',
        // );

        $arr_conditions = array(['SysUsers.deleted' => 0, 'SysUsers.active' => 1, 'SysUsers.type' => 'injector', 'SysUsers.is_test' => 0]);
        $arr_result = array();

        $fields = ['SysUsers.uid','SysUsers.id','SysUsers.short_uid','SysUsers.active','SysUsers.name','SysUsers.mname','SysUsers.lname','SysUsers.email','SysUsers.bname','SysUsers.score','SysUsers.phone','SysUsers.created','SysUsers.modified','SysUsers.steps',
        'SysUsers.login_status','SysUsers.latitude','SysUsers.longitude','SysUsers.stripe_account','SysUsers.stripe_account_confirm', 'SysUsers.photo_id','SysUsers.city','State.name','SysUsers.dob','SysUsers.street','SysUsers.zip','State.name'];

        $fields['subscriptions'] = "(SELECT COUNT(DS.id) FROM data_subscriptions DS WHERE DS.user_id = SysUsers.id AND DS.deleted = 0 AND DS.status = 'ACTIVE')";

        $fields['subscriptions_cancel'] = "(SELECT COUNT(DS.id) FROM data_subscriptions DS WHERE DS.user_id = SysUsers.id AND DS.deleted = 0 AND DS.status = 'CANCELLED')";

        $ent_preregister = $this->SysUsers->find()
        ->select(
        //     [
        //     'SysUsers.uid',
        //     'SysUsers.email',
        //     'SysUsers.name',
        //     'SysUsers.mname',
        //     'SysUsers.lname',
        //     'SysUsers.phone',
        //     'SysUsers.dob',
        //     'SysUsers.street',
        //     'SysUsers.city',
        //     'SysUsers.zip',
        //     'State.name',
        //     'SysUsers.login_status',
        //     '(SELECT COUNT(DS.id) FROM data_subscriptions DS WHERE DS.user_id = SysUsers.id AND DS.deleted = 0 AND DS.status = "ACTIVE") as subscriptions',
        //     '(SELECT COUNT(DS.id) FROM data_subscriptions DS WHERE DS.user_id = SysUsers.id AND DS.deleted = 0 AND DS.status = "CANCELLED") as subscriptions_cancel',
        // ]
        $fields
        )
        ->join([
           'State' => ['table' => 'cat_states', 'type' => 'INNER', 'conditions' => 'State.id = SysUsers.state'],
            ])
        ->where($arr_conditions)
        ->group(['SysUsers.email'])
        ->order(['SysUsers.created' => 'DESC'])->all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getCell('A1')->setValue('Name'); 
        $sheet->getStyle('A1')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 16,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('B1')->setValue('Emails');
        $sheet->getStyle('B1')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 16,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('C1')->setValue('Phone');
        $sheet->getStyle('C1')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 16,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('D1')->setValue('Dob');
        $sheet->getStyle('D1')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 16,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('E1')->setValue('Address');
        $sheet->getStyle('E1')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 16,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('F1')->setValue('Status');
        $sheet->getStyle('F1')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 16,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));

        $initIndex = 2;
        foreach ($ent_preregister as $item) {

            if ($item['steps'] == 'CODEVERIFICATION') {
                $item['steps'] = 'Verification code pending';
            } else if ($item['steps'] == 'PAYMENTMETHOD') {
                $item['steps'] = 'First payment method pending';
            } else if ($item['steps'] == 'HOWITWORKS') {
                $item['steps'] = 'Reading how it works';
            } else if ($item['steps'] == 'DENIED' || $item['steps'] == 'STATENOTAVAILABLE') {
                $item['steps'] = 'Account rejected';
            } else if ($item['steps'] == 'TRACERS') {
                $item['steps'] = 'Criminal records found';
            } else if ($item['steps'] == 'BASICCOURSE') {
                $item['steps'] = 'First training payment PENDING';
            } else if (($item['steps'] == 'ADVANCEDCOURSE' || $item['steps'] == 'SELECTADVANCEDCOURSE')) {
                if($item['subscriptions_cancel'] > 0){
                    if($item['subscriptions'] == 0){
                        $item['steps'] = 'MSL Subscription PENDING';
                    }else{
                        $item['steps'] = 'Fully Active Provider';
                    }                            
                } else if($item['subscriptions_cancel'] == 0){
                    $item['steps'] = 'Preparing for advanced';
                }
            } else if ($item['steps'] == 'MSLSUBSCRIPTION') {
                $item['steps'] = 'MSL Subscription PENDING';
            } else if ($item['steps'] == 'HOME' && $item['subscriptions_cancel'] > 0) {
                if($item['subscriptions'] == 0){
                    $item['steps'] = 'MSL Subscription PENDING';
                }else{
                    $item['steps'] = 'Fully Active Provider';
                }
            } else if ($item['steps'] == 'TREATMENTSETTINGS' && $item['subscriptions_cancel'] > 0) {
                if($item['subscriptions'] == 0){
                    $item['steps'] = 'MSL Subscription PENDING';
                }else{
                    $item['steps'] = 'Fully Active Provider';
                }
            } else if ($item['steps'] == 'WAITINGSCHOOLAPPROVAL') {
                $item['steps'] = 'Waiting for school';
            } else if ($item['steps'] == 'SELECTREFERRED') {
                $item['steps'] = 'Referal screen pending';
            } else if ($item['steps'] == 'SELECTBASICCOURSE') {
                $item['steps'] = 'Choosing training date';
            } 
            else if ($item['steps'] == 'MATERIALS') {
                $item['steps'] = 'Studying';
            }
            else if ($item['steps'] == 'SUBSCRIPTIONPENDING') {
                $item['steps'] = 'Subscription pending';            
            } else if ($item['steps'] == 'MDSUBSCRIPTION') {
                $item['steps'] = 'MD Subscription PENDING';
            }  else if ($item['steps'] == 'HOME' && $item['subscriptions_cancel'] == 0) {
                if ($item['subscriptions_hold'] >= 0 && $item['training_attended'] != "" && $item['subscriptions'] < 2){ 
                    $item['steps'] = 'Subscription pending';                                
                }else{
                    $item['steps'] = 'Fully Active Provider';
                }                            
            } else if ($item['steps'] == 'W9') {
                $item['steps'] = 'W9';
            } else if ($item['steps'] == 'CPR') {
                $item['steps'] = 'CPR';
            } else if ($item['steps'] == 'TREATMENTSETTINGS' && $item['subscriptions_cancel'] == 0) {
                $item['steps'] = 'Needs Treatment Settings';
            } else if ($item['steps'] == 'STRIPEACCOUNT') {
                $item['steps'] = 'Needs Stripe';
            } else {
                $item['steps'] = $item['login_status'];
            }

            // if($item['id'] == 7508){
            //     print_r($item); exit;
            // }

            $sheet->getCell('A'.$initIndex)->setValue($item['name'] . ' ' . $item['lname']);
            $sheet->getCell('B'.$initIndex)->setValue($item['email']);
            $sheet->getCell('C'.$initIndex)->setValue($item['phone']);
            $sheet->getCell('D'.$initIndex)->setValue($item['dob']->i18nFormat('MM/dd/yyyy'));
            $sheet->getCell('E'.$initIndex)->setValue($item['street'] . ', ' . $item['city'] . ', ' . $item['State']['name'] . ' ' . $item['zip'] );
            // $sheet->getCell('F'.$initIndex)->setValue($status_array[strtoupper($item['login_status'])]);
            $sheet->getCell('F'.$initIndex)->setValue(($item['steps']));
            $initIndex = $initIndex+1;
        }
        

        $writer = new Csv($spreadsheet);
        $writer->save(TMP . 'reports' . DS . "active_injectors.csv");
        if(isset($_SESSION['_User'])){
            $General = new GeneralController();            
            $General->email_ashlna_download_csv('active_injectors.csv' );                           
        }        
        $this->Files->output_file(TMP . 'reports' . DS . "active_injectors.csv");
        exit;
    }

    public function get_icons_user(){
        $this->loadModel('DataUserIcon');

        $user = $this->SysUsers->find()->select(['SysUsers.id'])->where(['SysUsers.uid' => get('uid', '')])->first();
        if(empty($user)){
            $this->Response->message('Invalid user.');
            return;
        }
        $user_id = $user->id;

        $str_query = "
            SELECT CatIcon.uid, CatIcon.name, CatIcon.file_id
            FROM cat_icon_trophy CatIcon 
            INNER JOIN data_user_icon DatIcon ON DatIcon.icon_id = CatIcon.id AND DatIcon.user_id = {$user_id}
            WHERE CatIcon.deleted = 0
        ";

        $result = $this->DataUserIcon->getConnection()->execute($str_query)->fetchAll('assoc');

        $this->Response->set('data', $result);
        $this->Response->success();
    }

    public function add_icon_user(){
        $this->loadModel('DataUserIcon');
        $this->loadModel('CatIconTrophy');

        $user = $this->SysUsers->find()->select(['SysUsers.id'])->where(['SysUsers.uid' => get('uid', '')])->first();
        if(empty($user)){
            $this->Response->message('Invalid user.');
            return;
        }
        $user_id = $user->id;

        $icon_id = $this->CatIconTrophy->uid_to_id(get('icon_uid', ''));
        if($icon_id <= 0){
            $this->Response->message('Invalid icon.');
            return;
        }

        $str_query = "DELETE FROM data_user_icon WHERE user_id = {$user_id} AND icon_id = {$icon_id}";
        $this->DataUserIcon->getConnection()->execute($str_query);

        $arrSave = [
            'user_id' => $user_id,
            'icon_id' => $icon_id,
        ];

        $enty_icon = $this->DataUserIcon->newEntity($arrSave);
        if(!$enty_icon->hasErrors()){
            $this->DataUserIcon->save($enty_icon);
            $this->Response->success();
        }else{
            $this->Response->message('Error adding icon to user.');
            return;
        }
    }

    public function remove_icon(){
        $this->loadModel('DataUserIcon');
        $this->loadModel('CatIconTrophy');

        $user = $this->SysUsers->find()->select(['SysUsers.id'])->where(['SysUsers.uid' => get('uid', '')])->first();
        if(empty($user)){
            $this->Response->message('Invalid user.');
            return;
        }
        $user_id = $user->id;

        $icon_id = $this->CatIconTrophy->uid_to_id(get('icon_uid', ''));
        if($icon_id <= 0){
            $this->Response->message('Invalid icon.');
            return;
        }

        $str_query = "DELETE FROM data_user_icon WHERE user_id = {$user_id} AND icon_id = {$icon_id}";
        $this->DataUserIcon->getConnection()->execute($str_query);
        $this->Response->success();
    }

    public function remove_criminal_records(){

        $ent_user = $this->SysUsers->find()->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();
        if(empty($ent_user)){
            return;
        }

        $ent_user->tracers = '{"criminalRecords":[],"criminalRecordCounts":0,"pagination":{"currentPageNumber":0,"resultsPerPage":10,"totalPages":0},"searchCriteria":[],"totalRequestExecutionTimeMs":99,"requestId":"dd536eaf-8cc9-4096-9a66-2fa000700b64","requestType":"Search","requestTime":"2021-06-02T07:03:34.4893319-07:00","isError":false}';

        $this->SysUsers->save($ent_user);
        if(!$ent_user->hasErrors()){
            $this->Response->set('data', '{"criminalRecords":[],"criminalRecordCounts":0,"pagination":{"currentPageNumber":0,"resultsPerPage":10,"totalPages":0},"searchCriteria":[],"totalRequestExecutionTimeMs":99,"requestId":"dd536eaf-8cc9-4096-9a66-2fa000700b64","requestType":"Search","requestTime":"2021-06-02T07:03:34.4893319-07:00","isError":false}');
            $this->Response->success();
        }else{
            $this->Response->add_errors('Internal Error.');
        }
    }

    private function getLicensesInjector($injector_id){
        $this->loadModel('SysLicence');
        $licenses = $this->SysLicence->find()->select(['SysLicence.type','SysLicence.number','SysLicence.start_date','SysLicence.exp_date'])->where(['SysLicence.user_id' => $injector_id])->toArray();
        return $licenses;
    }

    public function agreements() {

        
        $ent_user = $this->SysUsers->find()->where(['SysUsers.uid' => get('patient_uid',''),'SysUsers.deleted' => 0])->first();
        if(empty($ent_user)){
            return;
        }

        $this->loadModel('DataAgreements');
        $this->loadModel('DataSubscriptionCancelled');

        $ent = $this->DataAgreements->find()->select(['CatAgreement.agreement_title','CatAgreement.deleted','State.name','DataAgreements.created','CatAgreement.uid', 'DataSubscriptions.id'])
        ->join([
            'CatAgreement' => ['table' => 'cat_agreements', 'type' => 'INNER', 'conditions' => 'CatAgreement.uid = DataAgreements.agreement_uid'],
            'State' => ['table' => 'cat_states', 'type' => 'LEFT', 'conditions' => 'State.id = CatAgreement.state_id'],
            'DataSubscriptions' => ['table' => 'data_subscriptions', 'type' => 'LEFT', 'conditions' => 'DataSubscriptions.user_id='. $ent_user->id.' AND DataSubscriptions.deleted = 0 AND DataSubscriptions.status = "CANCELLED" AND CatAgreement.agreement_type = DataSubscriptions.subscription_type'],
            ])
        ->where([
            'DataAgreements.user_id' => $ent_user->id,
            'DataAgreements.deleted' => 0,
            'CatAgreement.id > ' => 24
            ])
            ->all();
        
        $result = array();
        foreach ($ent as $row) {
            $result[] = array(
                'state' => $row['State']['name'],
                'created' => $row['created'],
                'agreement_title' => $row['CatAgreement']['agreement_title'],
                'uid' => $row['CatAgreement']['uid'],
                'type' => 'agreement',
                'patient_uid' => get('patient_uid','')
            );
            
        }

        $this->loadModel('SpaLiveV1.DataModelPatientDocs');            
        $docs = $this->DataModelPatientDocs->find()->where(['DataModelPatientDocs.user_id' => $ent_user->id, 'DataModelPatientDocs.deleted' => 0])->toArray();
        foreach ($docs as $doc) {
            if($doc['type'] == 'INFO'){
                $result[] = array(
                    'agreement_title' => 'What is a patient model?', 
                    'created' => $doc['created']->format('m-d-Y'),
                    'state' => '',
                    'url' => $this->URL_API . '?action=PatientModel____generate_pdf_what_is_pm&key=225sadfgasd123fgkhijjdsadfg16578g12gg3gh&id=' . $ent_user->id,
                    'user_id' => $doc['user_id'],
                    'type' => 'info',
                    'patient_uid' => get('patient_uid','')
                );
            }
            else if($doc['type'] == 'GFE'){
                $result[] = array(
                    'agreement_title' => 'GFE payment confirmation', 
                    'created' => $doc['created']->format('m-d-Y'),
                    'state' => '',
                    'url' => $this->URL_API . '?action=PatientModel____generate_pdf_gfe_payment&key=225sadfgasd123fgkhijjdsadfg16578g12gg3gh&id=' . $ent_user->id,
                    'user_id' => $doc['user_id'],
                    'type' => 'gfe',
                    'patient_uid' => get('patient_uid','')
                );
            }
        }

        $this->Response->set('data', $result);
        $this->Response->success();

    }

    public function get_referred() {
        $this->loadModel('Admin.DataSalesRepresentative');
        $this->loadModel('Admin.DataSalesRepresentativeRegister');

        $ent_user = $this->SysUsers->find()->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();
        if(empty($ent_user)){
            return;
        }

        $add_array = array(
            "name" => $ent_user->name . ' ' . $ent_user->lname,
        );

        $this->Response->set('data', $add_array);
        $this->Response->success();
    }

    public function get_referred_list() {

        $this->loadModel('Admin.DataSalesRepresentative');
        $this->loadModel('Admin.DataSalesRepresentativeRegister');

        $ent_representatives = $this->DataSalesRepresentative->find()->select(['SU.name', 'SU.lname', 'DataSalesRepresentative.user_id'])
        ->join([
            'SU' => ['table' => 'sys_users' , 'type' => 'INNER', 'conditions' => 'SU.id = DataSalesRepresentative.user_id']
        ])->where(['DataSalesRepresentative.deleted' => 0,'DataSalesRepresentative.sales_person' => 1])->all();
        
        $data = [];
        foreach($ent_representatives as $row){
            array_push($data, array(
                'name' => $row['SU']['name'] . ' ' . $row['SU']['lname'],
                'id' => $row->user_id
            ));
        }

        //$this->Response->set('name', 'Test');
        //$this->Response->set('referred_by', $ent_representative['SU']['name'] . ' ' . $ent_representative['SU']['lname']);
        $this->Response->set('data', $data);
        $this->Response->success();
    }

    public function get_injector_payment_list() {

        $this->loadModel('Admin.DataSalesRepresentative');
        $this->loadModel('Admin.DataSalesRepresentativeRegister');

        $ent_representatives = $this->SysUsers->find()->select(['SysUsers.name', 'SysUsers.lname', 'SysUsers.id'])
        ->where(['SysUsers.deleted' => 0,'SysUsers.active' => 1, 'SysUsers.type IN' => array('injector', 'gfe+ci')/* , 'SysUsers.steps' => 'HOME' */])->all();
        
        $data = [];
        foreach($ent_representatives as $row){
            array_push($data, array(
                'name' => $row['name'] . ' ' . $row['lname'],
                'id' => $row->id
            ));
        }

        $this->Response->set('data', $data);
        $this->Response->success();
    }

    public function save_referred() {

        $this->loadModel('Admin.DataSalesRepresentative');
        $this->loadModel('Admin.DataSalesRepresentativeRegister');

        $ent_user = $this->SysUsers->find()->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();
        $ent_repre = $this->DataSalesRepresentativeRegister->find()->where(['DataSalesRepresentativeRegister.user_id' => $ent_user->id])->first();
        if(!empty($ent_repre)){
            $this->DataSalesRepresentativeRegister->updateAll(
                ['representative_id' => get('referred_by', 0)],
                ['user_id' => $ent_user->id]
            );
            $this->Response->success();
        }else{
            $save_array = array(
                'user_id' => $ent_user->id,
                'representative_id' => get('referred_by',''),
                'deleted' => 0,
                'created' => date('Y-m-d H:i:s')
            );

            $c_entity = $this->DataSalesRepresentativeRegister->newEntity($save_array);
            if(!$c_entity->hasErrors()) {
                if ($this->DataSalesRepresentativeRegister->save($c_entity)) {
                    $this->Response->success();
                }
            }
        }
    }

    
    public function save_assigned() {
        if (!MASTER) return;
        $this->loadModel('Admin.DataSalesRepresentative');
        $this->loadModel('Admin.DataAssignedToRegister');

        $ent_user = $this->SysUsers->find()->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();
        $ent_repre = $this->DataAssignedToRegister->find()->where(['DataAssignedToRegister.user_id' => $ent_user->id])->first();
        if(!empty($ent_repre)){
            $this->DataAssignedToRegister->updateAll(
                ['representative_id' => get('referred_by', 0)],
                ['user_id' => $ent_user->id]
            );
            $this->Response->success();
        }else{
            $save_array = array(
                'user_id' => $ent_user->id,
                'representative_id' => get('referred_by',''),
                'deleted' => 0,
                'created' => date('Y-m-d H:i:s')
            );

            $c_entity = $this->DataAssignedToRegister->newEntity($save_array);
            if(!$c_entity->hasErrors()) {
                if ($this->DataAssignedToRegister->save($c_entity)) {
                    $this->Response->success();
                }
            }
        }
    }

    public function save_sales_patient() {
        if (!MASTER) return;
        $this->loadModel('SpaLiveV1.DataSalesTeamPatients');

        $ent_user = $this->SysUsers->find()->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();

        $save_array = array(
            'sales_team_id' => get('sales_rep',''),
            'patient_id' => $ent_user->id,
            'created' => date('Y-m-d H:i:s'),
            'deleted' => 0,
        );

        $sales = $this->DataSalesTeamPatients->find()->where(['DataSalesTeamPatients.patient_id' => $ent_user->id, 'DataSalesTeamPatients.deleted' => 0])->first();

        if(!empty($sales)){
            $this->Response->message('Your choice cannot be changed anymore.');
            return;
        }

        $c_entity = $this->DataSalesTeamPatients->newEntity($save_array);
        if(!$c_entity->hasErrors()) {
            if ($this->DataSalesTeamPatients->save($c_entity)) {
                $this->Response->success();
            }
        }
    }


    public function get_injectors_csv (){
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $grid_o = $this->grid(false, "injector");
        
        
        $sheet->mergeCells('A1:H1');
        $sheet->getCell('A1')->setValue('MySpaLive'); 
        $sheet->getStyle('A1')->getFont()->getColor()->setARGB('1D6782');
        $sheet->getStyle('A1')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 30,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('A2')->setValue('Name');
        $sheet->getStyle('A2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_LEFT,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('B2')->setValue('Registration date');
        $sheet->getStyle('B2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('C2')->setValue('Status');
        $sheet->getStyle('C2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('D2')->setValue('Invited by');
        $sheet->getStyle('D2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('E2')->setValue('Referred by');
        $sheet->getStyle('E2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('F2')->setValue('Last status change');
        $sheet->getStyle('F2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('G2')->setValue('Comments');
        $sheet->getStyle('G2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('H2')->setValue('Last training');
        $sheet->getStyle('H2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('I2')->setValue('Scheduled training');
        $sheet->getStyle('I2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('J2')->setValue('Background');
        $sheet->getStyle('J2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('K2')->setValue('Phone');
        $sheet->getStyle('K2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('L2')->setValue('User');
        $sheet->getStyle('L2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('M2')->setValue('City');
        $sheet->getStyle('M2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('N2')->setValue('State');
        $sheet->getStyle('N2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('O2')->setValue('Rate');
        $sheet->getStyle('O2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('P2')->setValue('Stripe registration');
        $sheet->getStyle('P2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('Q2')->setValue('Last purchase');
        $sheet->getStyle('Q2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('R2')->setValue('# Purchases');
        $sheet->getStyle('R2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('S2')->setValue('$ Purchases');
        $sheet->getStyle('S2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('T2')->setValue('Last treatment');
        $sheet->getStyle('T2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('U2')->setValue('# Treatments');
        $sheet->getStyle('U2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('V2')->setValue('$ Treatments');
        $sheet->getStyle('V2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('W2')->setValue('Total paid<br>from us');
        $sheet->getStyle('W2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('X2')->setValue('App Version');
        $sheet->getStyle('X2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));


        $initIndex = 3;
        foreach($grid_o as $row) {
            // pr($row['name']);
            $sheet->getCell('A'.$initIndex)->setValue($row['name']);
            $sheet->getCell('B'.$initIndex)->setValue($row['registration_date']);
            $sheet->getCell('C'.$initIndex)->setValue($row['login_status']);
            $sheet->getCell('D'.$initIndex)->setValue($row['invited_by']);
            $sheet->getCell('E'.$initIndex)->setValue($row['referred_by']);
            $sheet->getCell('F'.$initIndex)->setValue($row['last_status_change']);
            $sheet->getCell('G'.$initIndex)->setValue($row['comments']);
            $sheet->getCell('H'.$initIndex)->setValue($row['last_training']);
            $sheet->getCell('I'.$initIndex)->setValue($row['training_notes']);
            $sheet->getCell('J'.$initIndex)->setValue($row['background']);
            $sheet->getCell('K'.$initIndex)->setValue($row['phone']);
            $sheet->getCell('L'.$initIndex)->setValue($row['user']);
            $sheet->getCell('M'.$initIndex)->setValue($row['city']);
            $sheet->getCell('N'.$initIndex)->setValue($row['state']);
            $sheet->getCell('O'.$initIndex)->setValue($row['score']);
            $sheet->getCell('P'.$initIndex)->setValue($row['stripe']);
            $sheet->getCell('Q'.$initIndex)->setValue($row['last_purchase']);
            $sheet->getCell('R'.$initIndex)->setValue($row['n_purchases']);
            $sheet->getCell('S'.$initIndex)->setValue($row['a_purchases']);
            $sheet->getCell('T'.$initIndex)->setValue($row['last_treatment']);
            $sheet->getCell('U'.$initIndex)->setValue($row['n_treatments']);
            $sheet->getCell('V'.$initIndex)->setValue($row['a_treatments']);
            $sheet->getCell('W'.$initIndex)->setValue($row['paid_from']);
            $sheet->getCell('X'.$initIndex)->setValue($row['version']);

            $initIndex = $initIndex+1;
        }

        // $writer = new Csv($spreadsheet);
        // $writer->save(TMP . 'reports' . DS . "active_injectors.csv");
        // $this->Files->output_file(TMP . 'reports' . DS . "active_injectors.csv");

        
        




        for ($i = 'A'; 
            $i <=  $spreadsheet->getActiveSheet()->getHighestColumn(); 
            $i++) {
            $spreadsheet->getActiveSheet()->getColumnDimension($i)->setAutoSize(TRUE);
        }
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, "Xlsx");
    
        // $writer = new Csv($spreadsheet);
        $writer->save(TMP . 'reports' . DS . "ALL Injectors.xlsx");
        $this->Files->output_file(TMP . 'reports' . DS . "ALL Injectors.xlsx");
        exit;

    }


    public function injectors_on_training() {

        $page = intval(get('page', 1));
        $limit = get('limit', 300);

        $_where = ['SysUsers.deleted' => 0, 'SysUsers.type' => 'injector'];

        if (get('filter','')) {
            $arr_filter = json_decode(get('filter'),true);
            if ($arr_filter[0]['property'] == "query") {
                $search = $arr_filter[0]['value'];
                $_where['OR'] = [['SysUsers.name LIKE' => "%$search%"], ['SysUsers.lname LIKE' => "%$search%"],['SysUsers.short_uid LIKE' => "%$search%"], ['SysUsers.email LIKE' => "%$search%"],['SysUsers.phone LIKE' => "%$search%"]];
            }
        }

        $str_now = date('Y-m-d 07:00:00');
        $_having = ['training_date IS NOT NULL'];
        $fields = ['SysUsers.uid','SysUsers.email', 'SysUsers.name','SysUsers.lname'];
        
        $fields['training_date'] = "(SELECT CT.scheduled FROM data_trainings DT JOIN cat_trainings CT ON CT.id = DT.training_id WHERE CT.scheduled >= '" . $str_now . "' AND DT.user_id = SysUsers.id LIMIT 1)";
        
        $entUsers = $this->SysUsers->find()->select($fields)->where($_where)->having($_having)->limit($limit)->page($page)->all();
        $count = $this->SysUsers->find()->select($fields)->where($_where)->having($_having)->count();

        $data = array();
        foreach($entUsers as $row) {
            $data[] = array(
                'uid' => $row['uid'],
                'training_date' => $row['training_date'],
                'user' => $row['email'],
                'name' => $row['name'] . ' ' . $row['lname'],
            );
        }   

        $this->Response->success();
        $this->Response->set('data', $data);
        $this->Response->set('total', $count);
    }

    public function save_credits() {

        $this->loadModel('Admin.SysUsers');

        $entUser = $this->SysUsers->find()->where(['SysUsers.uid' => get('user_uid','')])->first();

        if (!empty($entUser)) {
            $uid = Text::uuid();
            $arrSave = array(
                'uid' => $uid,
                'id_from' => $entUser->id,
                'id_to' => 0,
                'service_uid' => '',
                'type' => 'GFE',
                'intent' => 'panel_' . $uid,
                'payment' => 'panel_' . $uid,
                'receipt' => 'panel_' . $uid,
                'subtotal' => 0,
                'total' => 0,
                'prepaid' => 1,
                'is_visible' => 1,
                'comission_payed' => 1,
                'comission_generated' => 0,
                'created' => date('Y-m-d H:i:s'),
                'createdby' => $entUser->id,
                'refund_id' => 0,
                'transfer' => ''
            );

            $this->loadModel('Admin.DataPayment');
            $c_entity = $this->DataPayment->newEntity($arrSave);
            if(!$c_entity->hasErrors()) {
                if ($this->DataPayment->save($c_entity)) {
                       $this->Response->success();
                } else {
                    $this->Response->set('err', 'Internal error');
                }
            } else {
                $this->Response->set('err2', 'Internal error');
            }
        }

    }

    public function get_patients_csv (){
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $grid_o = $this->grid(false, "patient");
        
        
        $sheet->mergeCells('A1:H1');
        $sheet->getCell('A1')->setValue('MySpaLive - Patients'); 
        $sheet->getStyle('A1')->getFont()->getColor()->setARGB('1D6782');
        $sheet->getStyle('A1')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 30,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('A2')->setValue('Name');
        $sheet->getStyle('A2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_LEFT,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('B2')->setValue('Registration date');
        //$sheet->getStyle('B2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('C2')->setValue('Comments');
        //$sheet->getStyle('C2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('D2')->setValue('Phone');
        //$sheet->getStyle('D2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('E2')->setValue('Email');
        //$sheet->getStyle('E2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('F2')->setValue('Last Exam');
        //$sheet->getStyle('F2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('G2')->setValue('First treatment');
        //$sheet->getStyle('G2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));
        $sheet->getCell('H2')->setValue('Last treatment');
        //$sheet->getStyle('H2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));

        $sheet->getStyle('B2:H2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));

        // $sheet->getCell('I2')->setValue('App Version');
        // $sheet->getStyle('I2')->applyFromArray(array('font' => array('name' => 'Arial','bold' => true,'size' => 14,),'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,)));


        $initIndex = 3;
        foreach($grid_o as $row) {
            // pr($row['name']);
            $sheet->getCell('A'.$initIndex)->setValue($row['name']);
            $sheet->getCell('B'.$initIndex)->setValue($row['registration_date'] ? date('m/d/Y H:i', strtotime($row['registration_date'])) : '');
            $sheet->getCell('C'.$initIndex)->setValue($row['comments']);
            $sheet->getCell('D'.$initIndex)->setValue($row['phone']);
            $sheet->getCell('E'.$initIndex)->setValue($row['user']);
            $sheet->getCell('F'.$initIndex)->setValue($row['last_exam'] ? date('m/d/Y H:i', strtotime($row['last_exam'])) : '');
            // $sheet->getCell('G'.$initIndex)->setValue($row['n_treatments']);
            $sheet->getCell('H'.$initIndex)->setValue($row['last_treatment'] ? date('m/d/Y H:i', strtotime($row['last_treatment'])) : '');
            // $sheet->getCell('I'.$initIndex)->setValue($row['version']);

            $initIndex++; // = $initIndex+1;
        }

        // $writer = new Csv($spreadsheet);
        // $writer->save(TMP . 'reports' . DS . "active_injectors.csv");
        // $this->Files->output_file(TMP . 'reports' . DS . "active_injectors.csv");

        for ($i = 'A'; 
            $i <=  $spreadsheet->getActiveSheet()->getHighestColumn(); 
            $i++) {
            $spreadsheet->getActiveSheet()->getColumnDimension($i)->setAutoSize(TRUE);
        }
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, "Xlsx");
    
        // $writer = new Csv($spreadsheet);
        $writer->save(TMP . 'reports' . DS . "Patients.xlsx");
        if(isset($_SESSION['_User'])){            
            $General = new GeneralController();            
            $General->email_ashlna_download_csv('Patients.xlsx' );
        }
        $this->Files->output_file(TMP . 'reports' . DS . "Patients.xlsx");
        exit;

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

    private function getSummaryUsersByStatusGFI($userType, $join = ""){
        $condition = empty($userType) ? "" : " AND type = '{$userType}'";
        
        $gfe_colums = empty($join) ? "" : "
        ,(SELECT COUNT(U.id) FROM sys_users U ".$join." WHERE DRG.status = 'INIT' AND deleted = 0 AND active = 1) as total_pend_gfeci,        
        (SELECT COUNT(U.id) FROM sys_users U ".$join." WHERE DRG.status = 'READY' AND deleted = 0 AND active = 1) as total_ready_gfeci";                        
        $sql = "
        SELECT 
            (SELECT COUNT(U.id) FROM sys_users U ".$join." WHERE U.deleted = 0 AND U.active = 1 ".$condition.") as total,            
            (SELECT COUNT(U.id) FROM sys_users U ".$join." WHERE U.active = 0 AND U.deleted = 0 ".$condition.") as total_inactive
            {$gfe_colums}
            
        ";
    
        return $this->SysUsers->getConnection()->execute($sql)->fetchAll('assoc')[0];
    }

    public function email_ashlna_download_csv($CSVname ){
        
        $user = $_SESSION['_User'];        
        if(isset($user['uid'])){
            $uid = $user['uid'];
            $this->loadModel('SysUsersAdmin');
            $find = $SysUserAdmin = $this->SysUsersAdmin->find()->where(['SysUsersAdmin.uid' => $uid])->first();
            //    'all',array('conditions'=>array('uid'=>$uid)));
            if(empty($find)){                
                return;
            }            
        }else{
            return;
        }
        //exit;
        $type = get('type','EMAIL');        
        $str_email = get('email','ashlan@myspalive.com');  
        $is_dev = env('IS_DEV', false);
        if($is_dev){
            $str_email = 'jorgelara@advantedigital.com';
        }else{
            $str_email = 'ashlan@myspalive.com';
        }
        if($type == 'EMAIL'){
            $str_message = '
                <!doctype html>
                    <html>
                      <head>
                        <meta name="viewport" content="width=device-width">
                        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                        <title>MySpaLive Message</title>
                        <style>
                        @media only screen and (max-width: 620px) {
                          table[class=body] h1 {
                            font-size: 28px !important;
                            margin-bottom: 10px !important;
                          }
                          table[class=body] p,
                                table[class=body] ul,
                                table[class=body] ol,
                                table[class=body] td,
                                table[class=body] span,
                                table[class=body] a {
                            font-size: 16px !important;
                          }
                          table[class=body] .wrapper,
                                table[class=body] .article {
                            padding: 10px !important;
                          }
                          table[class=body] .content {
                            padding: 0 !important;
                          }
                          table[class=body] .container {
                            padding: 0 !important;
                            width: 100% !important;
                          }
                          table[class=body] .main {
                            border-left-width: 0 !important;
                            border-radius: 0 !important;
                            border-right-width: 0 !important;
                          }
                          table[class=body] .btn table {
                            width: 100% !important;
                          }
                          table[class=body] .btn a {
                            width: 100% !important;
                          }
                          table[class=body] .img-responsive {
                            height: auto !important;
                            max-width: 100% !important;
                            width: auto !important;
                          }
                        }
  
                        /* -------------------------------------
                            PRESERVE THESE STYLES IN THE HEAD
                        ------------------------------------- */
                        @media all {
                          .ExternalClass {
                            width: 100%;
                          }
                          .ExternalClass,
                                .ExternalClass p,
                                .ExternalClass span,
                                .ExternalClass font,
                                .ExternalClass td,
                                .ExternalClass div {
                            line-height: 100%;
                          }
                          .apple-link a {
                            color: inherit !important;
                            font-family: inherit !important;
                            font-size: inherit !important;
                            font-weight: inherit !important;
                            line-height: inherit !important;
                            text-decoration: none !important;
                          }
                          #MessageViewBody a {
                            color: inherit;
                            text-decoration: none;
                            font-size: inherit;
                            font-family: inherit;
                            font-weight: inherit;
                            line-height: inherit;
                          }
                          .btn-primary table td:hover {
                            background-color: #34495e !important;
                          }
                          .btn-primary a:hover {
                            background-color: #34495e !important;
                            border-color: #34495e !important;
                          }
                        }
                        </style>
                      </head>
                      <body class="" style="background-color: #f6f6f6; font-family: sans-serif; -webkit-font-smoothing: antialiased; font-size: 14px; line-height: 1.4; margin: 0; padding: 0; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%;">
                        <span class="preheader" style="color: transparent; display: none; height: 0; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; mso-hide: all; visibility: hidden; width: 0;">SpaLiveMD Message.</span>
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%; background-color: #f6f6f6;">
                          <tr>
                            <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;">&nbsp;</td>
                            <td class="container" style="font-family: sans-serif; font-size: 14px; vertical-align: top; display: block; Margin: 0 auto; max-width: 580px; padding: 10px; width: 580px;">
                              <div class="content" style="box-sizing: border-box; display: block; Margin: 0 auto; max-width: 580px; padding: 10px;">
                              <img src="https://panel.myspalive.com/img/logo_colored2.png" width="100px"/>
                                <!-- START CENTERED WHITE CONTAINER -->
                                <table role="presentation" class="main" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%; background: #ffffff; border-radius: 3px;">
                                  <!-- START MAIN CONTENT AREA -->
                                  <tr>
                                    <td class="wrapper" style="font-family: sans-serif; font-size: 14px; vertical-align: top; box-sizing: border-box; padding: 20px;">
                                      <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%;">
                                        <tr>
                                          <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;">
                                            <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; Margin-bottom: 15px;">
                                            <bold>Username:</bold> '.$find->name.' <br>
                                            <bold>Date:</bold> '. date('Y-m-d') .' <br>
                                            <bold>Time:</bold> '. date('H:i:s') .' <br>
                                            <bold>CSV name:</bold> '.$CSVname.' <br>
                                            </p>
                                            
                                        </tr>
                                      </table>
                                    </td>
                                  </tr>
  
                                <!-- END MAIN CONTENT AREA -->
                                </table>
  
                                <!-- START FOOTER -->
                                <div class="footer" style="clear: both; Margin-top: 10px; text-align: center; width: 100%;">
                                  <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%;">
                                    <tr>
                                      <td class="content-block" style="font-family: sans-serif; vertical-align: top; padding-bottom: 10px; padding-top: 10px; font-size: 12px; color: #999999; text-align: center;">
                                        <span class="apple-link" style="color: #999999; font-size: 12px; text-align: center;">Visit us at <a href="https://myspalive.com/">MySpaLive</a></span>
                                      </td>
                                  </table>
                                </div>
                                <!-- END FOOTER -->
  
                              <!-- END CENTERED WHITE CONTAINER -->
                              </div>
                            </td>
                            <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;">&nbsp;</td>
                          </tr>
                        </table>
                      </body>
                    </html>
  
            ';
  
            $data = array(
                'from'    => 'MySpaLive <noreply@mg.myspalive.com>',
                'to'    => $str_email,
                'subject' => 'An administrator user downloaded a CSV',
                'html'    => $str_message,
            );
            $this->log(__LINE__ . ' ' .$str_message);

            $mailgunKey = $this->getMailgunKey();
  
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, 'https://api.mailgun.net/v3/mg.myspalive.com/messages');
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_USERPWD, 'api:' . $mailgunKey);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_POST, true); 
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
  
            $result = curl_exec($curl);
  
            curl_close($curl);
        }
        $this->Response->success();     
    }
    
    public function load_welcome_call() {
        $html = '';

        $this->loadModel('SysUsers');
        $ent_user = $this->SysUsers->find()
        ->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();

        if (!empty($ent_user)) {
            $this->loadModel('DataUsersWelcomeCall');
            $ent_notes = $this->DataUsersWelcomeCall->find()->where(['DataUsersWelcomeCall.user_id' => $ent_user->id])->first();
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

    public function save_welcome_call() {
        $notes = get('notes','');

        $this->loadModel('SysUsers');
        $ent_user = $this->SysUsers->find()
        ->where(['SysUsers.uid' => get('uid',''),'SysUsers.deleted' => 0])->first();

        if (!empty($ent_user)) {
            $this->loadModel('DataUsersWelcomeCall');
            $ent_notes = $this->DataUsersWelcomeCall->find()->where(['DataUsersWelcomeCall.user_id' => $ent_user->id])->first();
            $n_id = 0;
            if (!empty($ent_notes)) {
                $n_id = $ent_notes->id;
            }

            $save_array = array(
                'id' => $n_id,
                'notes' => $notes,
                'user_id' => $ent_user->id,
                'created_by' => USER_ID,
                'created' => date('Y-m-d H:i:s')
            );

            $c_entity = $this->DataUsersWelcomeCall->newEntity($save_array);
            if(!$c_entity->hasErrors()) {
                if ($this->DataUsersWelcomeCall->save($c_entity)) {
                    $this->Response->success();
                }
            }
        }
    }
}  

