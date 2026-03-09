<?php

declare(strict_types=1);

namespace App\Controller;

use Admin\Controller\AppController;
use Cake\Utility\Security;
use Cake\Utility\Hash;

class PaymentsmdController extends AppController
{
	public function initialize(): void
	{
		parent::initialize();
		$this->Session = $this->getRequest()->getSession();
		$this->loadModel('Admin.SysUsuario');
	}

	public function gridDoctorsPayments()
	{
		$this->loadModel('DataTreatment');
		$this->loadModel('DataPayment');
		$this->loadModel('DataSubscriptions');

		$arr_return = [];

		// Obtener el mes actual para determinar qué lógica usar
		$current_month = date('Y-m');
		
		// LÓGICA ANTERIOR para meses pasados (mantener números exactos)
		$query2 = "SELECT DATE_FORMAT(DSP.created,'%Y-%m') mes, SUM(DSP.total) total_mes FROM data_subscription_payments DSP
				  JOIN data_subscriptions DS ON DS.id = DSP.subscription_id AND DS.subscription_type LIKE 'SUBSCRIPTIONMD%'
				  WHERE DSP.deleted = 0 AND DSP.status = 'DONE' AND DSP.payment_id <> '' AND DSP.md_id > 0 
				  and DSP.payment_details like '%NEUROTOXINS%' AND DSP.payment_details not like '%IV THERAPY%'
				  AND DATE_FORMAT(DSP.created,'%Y-%m') < '$current_month'
				  GROUP BY DATE_FORMAT(DSP.created,'%Y-%m') ORDER BY DSP.created DESC;";

		$ent_query2 = $this->DataSubscriptions->getConnection()->execute($query2)->fetchAll('assoc');

		foreach ($ent_query2 as $key2 => $value2) {
			$mes = $value2['mes'];

			$md = ' ';
			$query2_md = "SELECT DATE_FORMAT(DSP.created,'%Y-%m') mes, SUM(DSP.total) total_mes,a.name FROM data_subscription_payments DSP
				JOIN data_subscriptions DS ON DS.id = DSP.subscription_id AND DS.subscription_type LIKE 'SUBSCRIPTIONMD%'
				LEFT JOIN sys_users u ON u.id = DS.user_id
				LEFT JOIN sys_users_admin a ON a.id = DSP.md_id
				WHERE DSP.deleted = 0 AND DSP.status = 'DONE' AND DSP.payment_id <> '' and DATE_FORMAT(DSP.created,'%Y-%m') = '$mes' AND DSP.md_id > 0
				and ((DSP.payment_details like '%NEUROTOXINS%'       AND DSP.payment_details not like '%IV THERAPY%'      )      OR (DSP.payment_details like '%NEUROTOXINS%' AND DSP.payment_details like '%FILLERS%')    )
				" . $this->getUserConditionForDate() . "
				GROUP BY DATE_FORMAT(DSP.created,'%Y-%m'),DSP.md_id ORDER BY DSP.created DESC;";
			$md = ' ';

			$ent_query2_md = $this->DataSubscriptions->getConnection()->execute($query2_md)->fetchAll('assoc');
			
			foreach ($ent_query2_md as $key_md => $value_md) {
				$arr_return[] = [
					'Doctor_Uid'			=> '',
					'Treatment_YearMonth'	=> $value_md['mes'],
					'Treatment_Year'		=> '',
					'Treatment_Month'		=> '',
					'Treatment_Doctor'		=> isset($value_md['name'])  ? $value_md['name'] : $md,
					'Doctor_TotalTreat'		=> 'MD Subscription',
					'Doctor_TotalAmount'	=> doubleval(($value_md['total_mes'] / 100) * 0.21),
					'SortId' => 2
				];
			}
		}

		// subscriptions SUBSCRIPTIONMDIVT - meses pasados
		$query2 = "SELECT DATE_FORMAT(DSP.created,'%Y-%m') mes, SUM(DSP.total) total_mes FROM data_subscription_payments DSP
		JOIN data_subscriptions DS ON DS.id = DSP.subscription_id AND DS.subscription_type  LIKE 'SUBSCRIPTIONMD%'
		WHERE DSP.deleted = 0 AND DSP.status = 'DONE' AND DSP.payment_id <> '' AND DSP.md_id > 0  
		AND DSP.payment_details not like '%NEUROTOXINS%' AND DSP.payment_details like '%IV THERAPY%' AND DSP.payment_details not like '%FILLERS%'
		AND DATE_FORMAT(DSP.created,'%Y-%m') < '$current_month'
		GROUP BY DATE_FORMAT(DSP.created,'%Y-%m') ORDER BY DSP.created DESC;";

		$ent_query2_MDIVT = $this->DataSubscriptions->getConnection()->execute($query2)->fetchAll('assoc');

		foreach ($ent_query2_MDIVT as $key2 => $value2) {
			$mes = $value2['mes'];

			$md = ' ';
			$query2_md = "SELECT DATE_FORMAT(DSP.created,'%Y-%m') mes, SUM(DSP.total) total_mes,a.name FROM data_subscription_payments DSP
			JOIN data_subscriptions DS ON DS.id = DSP.subscription_id AND DS.subscription_type  LIKE 'SUBSCRIPTIONMD%'
			LEFT JOIN sys_users u ON u.id = DS.user_id
			LEFT JOIN sys_users_admin a ON a.id = DSP.md_id
			WHERE DSP.deleted = 0 AND DSP.status = 'DONE' AND DSP.payment_id <> '' and DATE_FORMAT(DSP.created,'%Y-%m') = '$mes' AND DSP.md_id > 0  
			AND DSP.payment_details not like '%NEUROTOXINS%' AND DSP.payment_details like '%IV THERAPY%' AND DSP.payment_details not like '%FILLERS%'
			" . $this->getUserConditionForDate() . "
			GROUP BY DATE_FORMAT(DSP.created,'%Y-%m'),DSP.md_id ORDER BY DSP.created DESC;";
			$md = ' ';

			$ent_query2_md = $this->DataSubscriptions->getConnection()->execute($query2_md)->fetchAll('assoc');
			
			foreach ($ent_query2_md as $key_md => $value_md) {
				$arr_return[] = [
					'Doctor_Uid'			=> '',
					'Treatment_YearMonth'	=> $value_md['mes'],
					'Treatment_Year'		=> '',
					'Treatment_Month'		=> '',
					'Treatment_Doctor'		=> isset($value_md['name'])  ? $value_md['name'] : $md,
					'Doctor_TotalTreat'		=> 'IVT Subscription',
					'Doctor_TotalAmount'	=> doubleval(($value_md['total_mes'] / 100) * 0.21),
					'SortId' => 2
				];
			}
		}

		// subscriptions SUBSCRIPTIONMD+IVT - meses pasados
		$query2 = "SELECT DATE_FORMAT(DSP.created,'%Y-%m') mes, SUM(DSP.total) total_mes FROM data_subscription_payments DSP
		JOIN data_subscriptions DS ON DS.id = DSP.subscription_id AND DS.subscription_type LIKE 'SUBSCRIPTIONMD%'
		WHERE DSP.deleted = 0 AND DSP.status = 'DONE' AND DSP.payment_id <> '' AND DSP.md_id > 0 
		AND DSP.payment_details like '%NEUROTOXINS%' AND DSP.payment_details like '%IV THERAPY%'  AND DSP.payment_details not like '%FILLERS%'
		AND DATE_FORMAT(DSP.created,'%Y-%m') < '$current_month'
		GROUP BY DATE_FORMAT(DSP.created,'%Y-%m') ORDER BY DSP.created DESC;";

		$ent_query2_md_ivt = $this->DataSubscriptions->getConnection()->execute($query2)->fetchAll('assoc');

		foreach ($ent_query2_md_ivt as $key2 => $value2) {
			$mes = $value2['mes'];

			$md = ' ';
			$query2_md = "SELECT DATE_FORMAT(DSP.created,'%Y-%m') mes, SUM(DSP.total) total_mes,a.name FROM data_subscription_payments DSP
			JOIN data_subscriptions DS ON DS.id = DSP.subscription_id AND DS.subscription_type LIKE 'SUBSCRIPTIONMD%'
			LEFT JOIN sys_users u ON u.id = DS.user_id
			LEFT JOIN sys_users_admin a ON a.id = DSP.md_id
			WHERE DSP.deleted = 0 AND DSP.status = 'DONE' AND DSP.payment_id <> '' and DATE_FORMAT(DSP.created,'%Y-%m') = '$mes' AND DSP.md_id > 0 
			AND DSP.payment_details like '%NEUROTOXINS%' AND DSP.payment_details like '%IV THERAPY%' AND DSP.payment_details not like '%FILLERS%'
			" . $this->getUserConditionForDate() . "
			GROUP BY DATE_FORMAT(DSP.created,'%Y-%m'),DSP.md_id ORDER BY DSP.created DESC;";
			$md = ' ';

			$ent_query2_md = $this->DataSubscriptions->getConnection()->execute($query2_md)->fetchAll('assoc');
		
			foreach ($ent_query2_md as $key_md => $value_md) {
				$arr_return[] = [
					'Doctor_Uid'			=> '',
					'Treatment_YearMonth'	=> $value_md['mes'],
					'Treatment_Year'		=> '',
					'Treatment_Month'		=> '',
					'Treatment_Doctor'		=> isset($value_md['name'])  ? $value_md['name'] : $md,
					'Doctor_TotalTreat'		=> 'MD+IVT Subscription',
					'Doctor_TotalAmount'	=> doubleval(($value_md['total_mes'] / 100) * 0.21),
					'SortId' => 2
				];
			}
		}

		// NUEVA LÓGICA para el mes actual en adelante (con comisiones variables)
		$query2_new = "SELECT 
			DATE_FORMAT(DSP.created,'%Y-%m') as mes, 
			DSP.total, 
			DSP.payment_details,
			DSP.md_id,
			a.name as admin_name
		FROM data_subscription_payments DSP
		JOIN data_subscriptions DS ON DS.id = DSP.subscription_id AND DS.subscription_type LIKE '%MD%'
		LEFT JOIN sys_users_admin a ON a.id = DSP.md_id
		WHERE DSP.deleted = 0 AND DSP.status = 'DONE' AND DSP.payment_id <> '' AND DSP.md_id > 0
		AND DSP.payment_details IS NOT NULL AND DSP.payment_details != ''
		AND DATE_FORMAT(DSP.created,'%Y-%m') >= '$current_month'
		" . $this->getUserConditionForDate('DSP.created') . "
		ORDER BY DSP.created DESC;";

		// Procesar todos los pagos de suscripciones con agrupación para meses actuales
		try {
			$ent_query2_new = $this->DataSubscriptions->getConnection()->execute($query2_new)->fetchAll('assoc');
			
			// Array para agrupar por mes, doctor y servicio
			$grouped_payments = [];
			
			foreach ($ent_query2_new as $payment) {
				$mes = $payment['mes'];
				$md_id = $payment['md_id'];
				$admin_name = $payment['admin_name'];
				$total_amount = $payment['total'];
				$payment_details = $payment['payment_details'];
				
				// Determinar nombre del doctor - omitir si no tiene nombre
				if (empty($admin_name)) {
					continue; // Saltar este pago si no tiene nombre de medical director
				}
				$doctor_name = $admin_name;
				
				// Decodificar JSON de payment_details
				$services = json_decode($payment_details, true);
				if (json_last_error() !== JSON_ERROR_NONE) {
					continue;
				}
				
				// Si el JSON es un array, tomar el primer elemento
				if (is_array($services) && isset($services[0])) {
					$services = $services[0];
				}
				
				// Procesar cada servicio en el payment_details
				if (is_array($services)) {
					foreach ($services as $service_name => $service_amount) {
						// Convertir el nombre del servicio a un formato más legible
						$service_display_name = $this->formatServiceName($service_name);
						
						// Crear clave única para agrupar: mes + doctor + servicio
						$group_key = $mes . '|' . $doctor_name . '|' . $service_display_name;
						
						// Si ya existe esta combinación, sumar el monto
						if (isset($grouped_payments[$group_key])) {
							$grouped_payments[$group_key]['total_amount'] += $service_amount;
						} else {
							// Crear nueva entrada
							$grouped_payments[$group_key] = [
								'mes' => $mes,
								'doctor_name' => $doctor_name,
								'service_name' => $service_display_name,
								'total_amount' => $service_amount
							];
						}
					}
				}
			}
			
			// Convertir datos agrupados a formato de salida
			foreach ($grouped_payments as $group) {
				// Determinar la comisión según la fecha
				$payment_date = $group['mes'] . '-01'; // Asumir primer día del mes
				$cutoff_date = '2025-09-01'; // Fecha fija de corte: 01 de septiembre de 2025
				$commission_rate = ($payment_date < $cutoff_date) ? 0.21 : 0.31; // 21% antes del 22 sep 2025, 31% a partir del 22 sep 2025
				
				// Calcular el monto con la comisión correspondiente
				$commission_amount = doubleval(($group['total_amount'] / 100) * $commission_rate);
				
				$arr_return[] = [
					'Doctor_Uid'			=> '',
					'Treatment_YearMonth'	=> $group['mes'],
					'Treatment_Year'		=> '',
					'Treatment_Month'		=> '',
					'Treatment_Doctor'		=> $group['doctor_name'],
					'Doctor_TotalTreat'		=> $group['service_name'],
					'Doctor_TotalAmount'	=> $commission_amount,
					'SortId' => 2
				];
			}
			
		} catch (Exception $e) {
			error_log("Error en gridDoctorsPayments: " . $e->getMessage());
			$this->Response->success(false);
			$this->Response->message('Error en consulta de suscripciones: ' . $e->getMessage());
			return;
		}

		// subscriptions 3 months SAKOMOTO DAYS
		$query3month = "SELECT DATE_FORMAT(DS.created,'%Y-%m') mes, COUNT(DS.id) total_mes FROM data_subscriptions DS 
					JOIN sys_users SU ON SU.id = DS.user_id
					WHERE DS.subscription_type LIKE '%MD%' AND DS.monthly = '3' AND DS.deleted = 0 AND DS.status = 'ACTIVE' AND SU.name NOT LIKE '%test%' AND SU.lname NOT LIKE '%test%'
					GROUP BY DATE_FORMAT(DS.created,'%Y-%m') ORDER BY DS.created DESC;";

		$ent_query_3_month = $this->DataSubscriptions->getConnection()->execute($query3month)->fetchAll('assoc');

		foreach ($ent_query_3_month as $key2 => $value3m) {
			
			$total = $value3m['total_mes'] / 2;
			$arr_return[] = [
				'Doctor_Uid'			=> '',
				'Treatment_YearMonth'	=> $value3m['mes'],
				'Treatment_Year'		=> '',
				'Treatment_Month'		=> '',
				'Treatment_Doctor'		=> 'Karen Reid-Renner',
				'Doctor_TotalTreat'		=> 'MD Subscription 3 month',
				'Doctor_TotalAmount'	=> doubleval(($total) * 21),
				'SortId' => 2
			];

			$arr_return[] = [
				'Doctor_Uid'			=> '',
				'Treatment_YearMonth'	=> $value3m['mes'],
				'Treatment_Year'		=> '',
				'Treatment_Month'		=> '',
				'Treatment_Doctor'		=> 'Marie',
				'Doctor_TotalTreat'		=> 'MD Subscription 3 month',
				'Doctor_TotalAmount'	=> doubleval(($total) * 21),
				'SortId' => 2
			];
		}

		$uniqueDates = array_unique(array_column($arr_return, 'Treatment_YearMonth'));
		foreach($uniqueDates as $key=>$value){			
			$sumTotal = $this->calculateTotalForYearMonth($arr_return, $value);
			$arr_return[] = [
				'Doctor_Uid'			=> '',
				'Treatment_YearMonth'	=> $value,
				'Treatment_Year'		=> '',
				'Treatment_Month'		=> '',
				'Treatment_Doctor'		=> 'TOTAL',
				'Doctor_TotalTreat'		=> '',
				'Doctor_TotalAmount'	=> doubleval($sumTotal ),
				'SortId' => 3
			];
		}					

		$mmes = array();
		foreach ($arr_return as $key => $row) {
			$mmes['Treatment_YearMonth'][$key] = $row['Treatment_YearMonth'];
			$mmes['SortId'][$key] = $row['SortId'];
		}
		array_multisort($mmes['Treatment_YearMonth'], SORT_DESC, $mmes['SortId'], SORT_ASC, $arr_return);

		$this->Response->success(true);
		$this->Response->set('data', $arr_return);
	}

	/**
	 * Genera la condición de usuario basada en la fecha
	 * Para pagos de junio 2024 hacia atrás: aplica restricción de usuario
	 * Para pagos de julio 2024 en adelante: todos los usuarios ven todos los pagos
	 */
	private function getUserConditionForDate($dateField = 'DSP.created')
	{
		// Si es usuario master, no hay restricciones
		if (USER_ID == 1) {
			return '';
		}
		
		$user_condition_base = "AND a.id = " . USER_ID;
		
		// Para usuarios normales: restricción solo para junio 2024 hacia atrás
		return "AND (DATE_FORMAT($dateField,'%Y-%m') <= '2025-06' $user_condition_base OR DATE_FORMAT($dateField,'%Y-%m') > '2025-06')";
	}

	function calculateTotalForYearMonth($data, $yearmonth) {
		$total = 0;
		
		foreach ($data as $entry) {
			$entryYearMonth = date('Y-m', strtotime($entry['Treatment_YearMonth']));
			
			if ($entryYearMonth === "$yearmonth") {
				$total += $entry['Doctor_TotalAmount'];
			}
		}
		
		return $total;
	}

	/**
	 * Formatea el nombre del servicio para mostrarlo de manera más legible
	 */
	private function formatServiceName($service_name) {
		$service_mapping = [
			'NEUROTOXINS' => 'Neurotoxin Subscription',
			'DERMAPLANING' => 'Dermaplaning Subscription',
			'MICRONEEDLING' => 'Microneedling Subscription',
			'CHEMICAL_PEELS' => 'Chemical Peels Subscription',
			'IV THERAPY' => 'IVT Subscription',
			'ARCANE' => 'Arcane Subscription',
			'FILLERS' => 'Fillers Subscription'
		];
		
		return isset($service_mapping[$service_name]) ? $service_mapping[$service_name] : $service_name . ' Subscription';
	}


	public function password()
	{
		$this->loadModel('Admin.SysTempPassword');

		$str_passwd = get('password', '');
		$str_new_passwd = get('password_new', '');
		$str_confirm_passwd = get('password_confirm', '');

		if (empty($str_passwd)) {
			$this->Response->add_errors('Password cant be empty.');
			return;
		}

		$where = ['SysUsuario.id' => USER_ID];

		$ent_reg = $this->SysUsuario->find()->where($where)->first();

		$shapassword = hash_hmac('sha256', $str_passwd, Security::getSalt());

		if (empty($ent_reg)) {
			$this->Response->add_errors('Wrong user.');
			return;
		}

		if ($ent_reg->password != $shapassword) {
			$this->Response->add_errors('Wrong password.');
			return;
		}

		if ($str_new_passwd != $str_confirm_passwd) {
			$this->Response->add_errors('Password and confirmation are not equal.');
			return;
		}


		// if(strlen($str_passwd) < 7){
		//     $this->Response->add_errors('La contraseña de ser de al menos 6 caracteres.');
		//     return;
		// }

		// $this->SysTempPassword->query()->update()->set(['deleted' => 1])->where(['user_id' => $int_modelo_id])->execute();

		$ent_reg->password = hash_hmac('sha256', $str_new_passwd, Security::getSalt());
		if ($this->SysUsuario->save($ent_reg)) {
			$this->Response->success();
		}
	}
}
