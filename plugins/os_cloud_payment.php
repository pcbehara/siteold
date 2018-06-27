<?php
/**
 * @package        Joomla
 * @subpackage     Cloud Payment
 * @author         Lee L.
 * @copyright      Copyright (C) 2016 jumazi Team
 * @license        GNU/GPL, see LICENSE.php
 */
// no direct access
defined('_JEXEC') or die;



class os_cloud_payment extends MPFPaymentOmnipay
{
	
	
	public $login = null;

	public $url_api_stop_payment = null;

	public $url_api_change_email = null;

	public $url = null;

	public $params = array();

	public $success = false;

	public $error = true;

	public $postfields;

	public $response;

	public $resultCode;

	public $code;

	public $text;

	public $subscrId;

	public $data = array();

	
	public function __construct($params, $config = array())
	{
		$this->login                = $params->get('cp_api_id_login');
		
		$this->url                  = $params->get('cp_url_api_new_payment');
		
		$this->url_api_stop_payment = $params->get('cp_url_api_stop_payment');
		
		$this->url_api_change_email = $params->get('cp_url_api_change_email');
	}

	public function processRecurringPayment($row, $data)
	{

		$app    = JFactory::getApplication();

		$Itemid = $app->input->getInt('Itemid', 0);

		$db     = JFactory::getDbo();

		$query  = $db->getQuery(true);

		/* Lee comment: get Plan Detail */		
 
		$query->select('*')

			->from('#__osmembership_plans')

			->where('id = ' . $row->plan_id);

		$db->setQuery($query);

		$rowPlan   = $db->loadObject();

		$frequency = $rowPlan->subscription_length_unit;

		$length    = $rowPlan->subscription_length;

		switch ($frequency)

		{

			case 'D':

				$unit = 'days';

				break;

			case 'W':

				$unit = 'days';

				break;

			case 'M':

				$unit = 'months';

				break;

			case 'Y':

				$length = 12;

				$unit   = 'months';

				break;

		}

		$this->setParameter('userID', $row->user_id);

		$this->setParameter('userEmail', $row->email);

		$this->setParameter('refID', $row->id . '-' . JHtml::_('date', 'now', 'Y-m-d'));

		$this->setParameter('subscrName', $row->first_name . ' ' . $row->last_name);

		$this->setParameter('interval_length', $length);

		$this->setParameter('interval_unit', $unit);

		// Note: get expirationDate
		$this->setParameter('expirationDate', str_pad($data['exp_month'], 2, '0', STR_PAD_LEFT) . '/' . substr($data['exp_year'], 2, 2));

		$this->setParameter('expirationDateYear', substr($data['exp_year'], 2, 2));

		$this->setParameter('expirationDateMonth', str_pad($data['exp_month'], 2, '0', STR_PAD_LEFT));		

		$this->setParameter('cardNumber', $data['cp_card_number']);

		$this->setParameter('phoneNumber', $data['cp_phone_number']);

		$this->setParameter('cp_firstName', $data['cp_first_name']);

		$this->setParameter('cp_lastName', $data['cp_last_name']);

		$this->setParameter('tkn', $data['tkn']);

		$this->setParameter('firstName', $row->first_name);

		$this->setParameter('lastName', $row->last_name);

		$this->setParameter('address', $row->address);

		$this->setParameter('city', $row->city);

		$this->setParameter('state', $row->state);

		$this->setParameter('zip', $row->zip);

		$this->setParameter('amount', round($data['regular_price'], 2));

		if ($rowPlan->number_payments >= 2)
		{
			$totalOccurrences = $rowPlan->number_payments;
		}
		else
		{
			$totalOccurrences = 9999;
		}


		$this->setParameter('totalOccurrences', $totalOccurrences);

		if ($data['trial_duration'])
		{

			$this->setParameter('trialAmount', $data['trial_amount']);

			$this->setParameter('trialOccurrences', $data['trial_duration']);
		}

		/*		
		echo "<pre>";
		print_r($this);
		echo "</pre>";

		echo "<pre>";
		print_r($row);
		echo "</pre>";

		echo "<pre>";
		print_r($data);
		echo "</pre>";		

		exit();
		*/
	
		$this->createAccount();

		if ($this->success)
		{

			$config               = OSMembershipHelper::getConfig();

			$row->subscription_id = $this->getSubscriberID();

			$row->payment_date    = date('Y-m-d H:i:s');

			$row->payment_made    = 1;

			$row->published       = true;

			$row->store();

			JPluginHelper::importPlugin('osmembership');

			$dispatcher = JEventDispatcher::getInstance();

			$dispatcher->trigger('onMembershipActive', array($row));

			OSMembershipHelper::sendEmails($row, $config);

			$app->redirect(JRoute::_('index.php?option=com_osmembership&view=complete&Itemid=' . $Itemid, false, false));

			return true;
		}

		else
		{

			$session = JFactory::getSession();

			$session->set('omnipay_payment_error_reason', $this->text);

			$app->redirect(JRoute::_('index.php?option=com_osmembership&view=failure&id=' . $row->id . '&Itemid=' . $Itemid, false));

			return false;
		}

	}


	/**
	* Perform a recurring payment subscription
	*/

	protected function createAccount()
	{

		$jp_aid    = $this->login;
		$jp_cod    = date('ymdHis').'_'.$this->params['userID'];

		// For checking (remove later)
		//$jp_cod    = '160829031822_997';

		$chocdn    = $this->params['cardNumber'];
		$chocdxy   = $this->params['expirationDateYear'];
		$chocdxm   = $this->params['expirationDateMonth'];
		$chocds1   = $this->params['cp_firstName'];
		$chocds2   = $this->params['cp_lastName'];
		$chotkn    = $this->params['tkn'];
		$chocemail = $this->params['userEmail'];
		$chocdt    = $this->params['phoneNumber'];
		$jp_iid    = '002';
		
//		$this->postfields = 'aid='.$jp_aid.'&rt=0&cod='.$jp_cod.'&cn='.urlencode($chocdn).'&ed='.$chocdxy.$chocdxm.'&fn='.urlencode($chocds1).'&ln='.urlencode($chocds2).'&em='.urlencode($chocemail).'&pn='.urlencode($chocdt).'&iid='.$jp_iid;
		// 20171110 Miki for Token
		$this->postfields = 'aid='.$jp_aid.'&rt=0&cod='.$jp_cod.'&tkn='.urlencode($chotkn).'&em='.urlencode($chocemail).'&pn='.urlencode($chocdt).'&iid='.$jp_iid;

		$this->process();

	}


	protected function process($retries = 1)
	{

		$count = 0;

		while ($count < $retries)

		{

			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $this->url);

			// curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			// curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
			curl_setopt($ch, CURLOPT_HTTPHEADER, false);

			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

			curl_setopt($ch, CURLOPT_POST, true);

			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->postfields);

			// $this->resultCode = 'OK';

			$jp_result = curl_exec($ch); 

			curl_close($ch);

			$jp_result = trim($jp_result);

			$this->resultCode =  $jp_result;

			if ($this->resultCode === "OK")

			{

				$this->success = true;

				$this->error   = false;

				break;

			}

			else

			{

				$this->success = false;

				$this->error   = true;

				break;

			}

			$count++;
		}
	}

	

	/**
	 * Verify recurring payment
	*/

	public function verifyRecurringPayment()
	{
		
		/*
		$ret = $this->validate();

		if ($ret)

		{

			// Find the ID of the subscription record

			$subscriptionId = $this->data['x_subscription_id'];

			$db             = JFactory::getDbo();

			$query          = $db->getQuery(true);

			$query->select('id')

				->from('#__osmembership_subscribers')

				->where('subscription_id = ' . $db->quote($subscriptionId));

			$db->setQuery($query);

			$id = (int) $db->loadResult();

			if ($id)

			{

				OSMembershipHelper::extendRecurringSubscription($id, $this->data['x_trans_id']);

			}
		}
		*/
	}


	protected function validate()
	{

		/*
		$this->data = $_POST;

		$this->log_ipn_results();

		if (!empty($this->data['x_subscription_id']) && @$this->data['x_response_code'] == 1 && ($this->data['x_subscription_paynum'] > 1))

		{

			return true;

		}

		else

		{

			return false;

		}
		*/
	}



	public function cancelSubscription($row)
	{
		
		JLoader::import('leehelper', JPATH_SITE . '/chocolatlib');
		$leehelper = new Leehelper;

		$user    = JFactory::getUser();
		$user_id = $user->id;
		
		// get acid value
		$db      = JFactory::getDbo();

		$query   = $db->getQuery(true);
		$query->select('acid')
			  ->from('#__osmembership_users_acid')
			  ->where('user_id = ' . $db->quote($user_id));
		$db->setQuery($query);
		$acid = (int) $db->loadResult();

		// Update Membership Pro

		// 1.1 get subscriber_id
		$sql2="SELECT id FROM `#__osmembership_subscribers` WHERE user_id=" . $user_id . " AND plan_id=7 ORDER BY id DESC LIMIT 1; ";
		$db->setQuery($sql2);
		$db->query();
		$subscriber_id = $db->loadResult();


		/*
		$check_membership_expired = $leehelper->checkMembershipexpires($subscriber_id);

		if($check_membership_expired)
		{
			// 1.3 Delete Joomla User Group (group_id = 10 - Membership)
			$sql4="SELECT COUNT(*) FROM `#__user_usergroup_map` WHERE user_id=" . $user_id . " AND group_id=10; ";

			$db->setQuery($sql4);
			$db->query();
			$result_group = $db->loadResult();
			if($result_group > 0)
			{
				$sql5="DELETE FROM `#__user_usergroup_map` WHERE user_id=" . $user_id . " AND group_id=10; ";
				$db->setQuery($sql5);
				$db->query();
			}	
		}
		*/

		// 1.3 Delete Joomla User Group (group_id = 10 - Membership)
		$sql4="SELECT COUNT(*) FROM `#__user_usergroup_map` WHERE user_id=" . $user_id . " AND group_id=10; ";

		$db->setQuery($sql4);
		$db->query();
		$result_group = $db->loadResult();
		if($result_group > 0)
		{
			$sql5="DELETE FROM `#__user_usergroup_map` WHERE user_id=" . $user_id . " AND group_id=10; ";
			$db->setQuery($sql5);
			$db->query();
		}	

		


		// 1.2 Update subscriber status (Published = 2 - Cancelled-Refunded)
		$today = date("Y-m-d H:i:s"); 
		$sql3="UPDATE `#__osmembership_subscribers` SET to_date='" .$today. "' , published=2 WHERE id=" . $subscriber_id . ";"; 
		$db->setQuery($sql3);
		$db->query();


		// Update API for function Cancel
		$ch_x = curl_init();
		curl_setopt($ch_x, CURLOPT_URL, $this->url_api_stop_payment.'?aid='.$this->login.'&cmd=1&acid='.$acid);
		// curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch_x, CURLOPT_RETURNTRANSFER, true);
		// curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
		curl_setopt($ch_x, CURLOPT_HTTPHEADER, false);
		curl_setopt($ch_x, CURLOPT_CONNECTTIMEOUT, 15);
		$jp_result_x = curl_exec($ch_x); 

		// get from_name & from_email
		$sql6="SELECT * FROM `#__osmembership_configs` ; ";
		$db->setQuery($sql6);
		$db->query();
		$osm_configs = $db->loadObjectList();

		if(count($osm_configs) > 0)
		{
			foreach ($osm_configs as $key => $osm_config_item) {
				if($osm_config_item->config_key == 'from_name')
				{
					$result_configs['from_name_value'] = $osm_config_item->config_value;
				}
				if($osm_config_item->config_key == 'from_email'){
					$result_configs['from_email_value'] = $osm_config_item->config_value;	
				}	
			}
		}
		if($result_configs['from_name_value'] == '')	
		{
			$result_configs['from_name_value'] = 'Chocolat';
		}
		if($result_configs['from_email_value'] == '')
		{
			$result_configs['from_email_value'] = 'info@chocolat-staff.com'	;
		}

		// Send a notice mail to Admin

		$subject = JText::_('OSM_OVERRIDE_CANCEL_SUBSCRIPTION_MAIL_ADMIN_SUBJECT');
		$body  = "Chocolat システムメッセージ<br><br>";
		$body .= "ID: $user_id<br>";
		$body .= "さんが登録リスナーに戻りました。<br>";

		$to = $result_configs['from_email_value'];
		$from = array($result_configs['from_email_value'], $result_configs['from_name_value']);

		$mailer = JFactory::getMailer();
		$mailer->setSender($from);
		$mailer->addRecipient($to);
		$mailer->setSubject($subject);
		$mailer->setBody($body);
		$mailer->isHTML();
		$mailer->send();

		// Send a notice mail to user
		$subject = JText::_('OSM_OVERRIDE_CANCEL_SUBSCRIPTION_MAIL_USER_SUBJECT');
		$body = JText::_('OSM_OVERRIDE_CANCEL_SUBSCRIPTION_MAIL_USER_BODY');

		$to = $user->email;
		$from = array($result_configs['from_email_value'], $result_configs['from_name_value']);

		$mailer = JFactory::getMailer();
		$mailer->setSender($from);
		$mailer->addRecipient($to);
		$mailer->setSubject($subject);
		$mailer->setBody($body);
		$mailer->isHTML();
		$mailer->send();
	
		return true;
	}


	protected function setParameter($field = "", $value = null)
	{

		$field                = (is_string($field)) ? trim($field) : $field;

		$value                = (is_string($value)) ? trim($value) : $value;

		$this->params[$field] = $value;
	}


	protected function getSubscriberID()
	{
		return $this->subscrId;
	}

	protected function isSuccessful()
	{
		return $this->success;
	}
	

	protected function isError()
	{
		return $this->error;
	}

}
