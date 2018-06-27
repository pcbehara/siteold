<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

class os_authnet extends MPFPaymentOmnipay
{
	/**
	 * Omnipay package
	 *
	 * @var string
	 */
	protected $omnipayPackage = 'AuthorizeNet_AIM';

	/**
	 * The parameters which will be passed to payment gateway for processing payment
	 *
	 * @var array
	 */
	protected $parameters = array();

	/**
	 * Success or not
	 *
	 * @var boolean
	 */
	protected $success = false;

	/**
	 * Result code of the operation
	 *
	 * @var string
	 */
	protected $resultCode;

	/**
	 * Subscription ID
	 *
	 * @var string
	 */
	protected $subscriptionId;

	/**
	 * Return code of the operation
	 *
	 * @var string
	 */
	protected $code;

	/**
	 * Result text of the operation
	 *
	 * @var string
	 */
	protected $text;

	/**
	 * Post data sent from Authorize.net (via Slient Post)
	 *
	 * @var array
	 */
	protected $notificationData = [];

	/**
	 * Constructor
	 *
	 * @param JRegistry $params
	 * @param array     $config
	 */
	public function __construct($params, $config = array('type' => 1))
	{
		$config['params_map'] = array(
			'apiLoginId'     => 'x_login',
			'transactionKey' => 'x_tran_key',
			'developerMode'  => 'authnet_mode',
		);

		parent::__construct($params, $config);
	}


	/**
	 * Process recurring payment
	 *
	 * @param OSMembershipTableSubscriber $row
	 * @param array                       $data
	 *
	 * @return void
	 */
	public function processRecurringPayment($row, $data)
	{
		$app    = JFactory::getApplication();
		$Itemid = $app->input->getInt('Itemid', 0);

		$rowPlan   = OSMembershipHelperDatabase::getPlan($row->plan_id);
		$frequency = $rowPlan->subscription_length_unit;
		$length    = $rowPlan->subscription_length;

		// Initialize some recurring parameters
		$this->parameters['startDate']        = date("Y-m-d");
		$this->parameters['trialOccurrences'] = 0;
		$this->parameters['trialAmount']      = 0.00;

		// Process first payment if this is not free trial, this is to make sure the provided credit card number is valid
		$transactionId = '';

		if (!$row->is_free_trial)
		{
			$transactionId = $this->processFirstPayment($rowPlan, $data);

			if ($transactionId === false)
			{
				$app->redirect(JRoute::_('index.php?option=com_osmembership&view=failure&id=' . $row->id . '&Itemid=' . $Itemid, false));

				return;
			}

			$row->payment_made = 1;
		}

		switch ($frequency)
		{
			case 'D':
				$unit = 'days';
				break;
			case 'W':
				$length *= 7;
				$unit   = 'days';
				break;
			case 'M':
				$unit = 'months';
				break;
			case 'Y':
				$length *= 12;
				$unit   = 'months';
				break;
			default:
				$unit = 'days';
				break;
		}

		$this->setParameter('refID', $row->id . '-' . JHtml::_('date', 'now', 'Y-m-d'));
		$this->setParameter('subscrName', $row->first_name . ' ' . $row->last_name);
		$this->setParameter('interval_length', $length);
		$this->setParameter('interval_unit', $unit);
		$this->setParameter('expirationDate', str_pad($data['exp_month'], 2, '0', STR_PAD_LEFT) . '/' . substr($data['exp_year'], 2, 2));
		$this->setParameter('cardNumber', $data['x_card_num']);
		$this->setParameter('firstName', $row->first_name);
		$this->setParameter('lastName', $row->last_name);
		$this->setParameter('address', $row->address);
		$this->setParameter('city', $row->city);
		$this->setParameter('state', $row->state);
		$this->setParameter('zip', $row->zip);
		$this->setParameter('amount', round($data['regular_price'], 2));

		if ($rowPlan->number_payments >= 2)
		{
			if (!$row->is_free_trial)
			{
				$totalOccurrences = $rowPlan->number_payments - 1;
			}
			else
			{
				$totalOccurrences = $rowPlan->number_payments;
			}
		}
		else
		{
			$totalOccurrences = 9999;
		}

		$this->setParameter('totalOccurrences', $totalOccurrences);

		// Call authorize.net API for creating recurring subscription
		$this->createAccount();

		if ($this->success)
		{
			$row->subscription_id = $this->subscriptionId;
			$this->onPaymentSuccess($row, $transactionId);
			$app->redirect(JRoute::_('index.php?option=com_osmembership&view=complete&Itemid=' . $Itemid, false, false));
		}
		else
		{
			JFactory::getSession()->set('omnipay_payment_error_reason', $this->text);
			$app->redirect(JRoute::_('index.php?option=com_osmembership&view=failure&id=' . $row->id . '&Itemid=' . $Itemid, false));
		}
	}

	/**
	 * Verify recurring payment
	 */
	public function verifyRecurringPayment()
	{
		$db             = JFactory::getDbo();
		$query          = $db->getQuery(true);
		$subscriptionId = $this->notificationData['x_subscription_id'];
		$query->select('id')
			->from('#__osmembership_subscribers')
			->where('subscription_id = ' . $db->quote($subscriptionId));
		$db->setQuery($query);
		$id = (int) $db->loadResult();

		if ($id && $this->validate())
		{
			// Valid payment, extend the recurring subscription
			/* @var OSMembershipModelApi $model */
			$model = MPFModel::getInstance('Api', 'OSMembershipModel', ['ignore_request' => true]);
			$model->renewRecurringSubscription($id, $subscriptionId, $this->notificationData['x_trans_id']);
		}
	}

	/**
	 * Cancel recurring subscription
	 *
	 * @param OSMembershipTableSubscriber $row
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function cancelSubscription($row)
	{
		$xml =
			"<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
			"<ARBCancelSubscriptionRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
			"<merchantAuthentication>" .
			"<name>" . $this->params->get('x_login') . "</name>" .
			"<transactionKey>" . $this->params->get('x_tran_key') . "</transactionKey>" .
			"</merchantAuthentication>" .
			"<subscriptionId>" . $row->subscription_id . "</subscriptionId>" .
			"</ARBCancelSubscriptionRequest>";

		$this->process($xml);

		if ($this->success)
		{
			return true;
		}

		JFactory::getApplication()->enqueueMessage($this->text, 'error');

		return false;
	}

	/**
	 * Perform a recurring payment subscription
	 */
	protected function createAccount()
	{
		$xml = "<?xml version='1.0' encoding='utf-8'?>
          <ARBCreateSubscriptionRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
              <merchantAuthentication>
                  <name>" . $this->params->get('x_login') . "</name>
                  <transactionKey>" . $this->params->get('x_tran_key') . "</transactionKey>
              </merchantAuthentication>
              <refId>" . $this->parameters['refID'] . "</refId>
              <subscription>
                  <name>" . $this->parameters['subscrName'] . "</name>
                  <paymentSchedule>
                      <interval>
                          <length>" . $this->parameters['interval_length'] . "</length>
                          <unit>" . $this->parameters['interval_unit'] . "</unit>
                      </interval>
                      <startDate>" . $this->parameters['startDate'] . "</startDate>
                      <totalOccurrences>" . $this->parameters['totalOccurrences'] . "</totalOccurrences>
                      <trialOccurrences>" . $this->parameters['trialOccurrences'] . "</trialOccurrences>
                  </paymentSchedule>
                  <amount>" . $this->parameters['amount'] . "</amount>
                  <trialAmount>" . $this->parameters['trialAmount'] . "</trialAmount>
                  <payment>
                      <creditCard>
                          <cardNumber>" . $this->parameters['cardNumber'] . "</cardNumber>
                          <expirationDate>" . $this->parameters['expirationDate'] . "</expirationDate>
                      </creditCard>
                  </payment>
                  <billTo>
                      <firstName>" . $this->parameters['firstName'] . "</firstName>
                      <lastName>" . $this->parameters['lastName'] . "</lastName>
                      <address>" . $this->parameters['address'] . "</address>
                      <city>" . $this->parameters['city'] . "</city>
                      <state>" . $this->parameters['state'] . "</state>
                      <zip>" . $this->parameters['zip'] . "</zip>
                  </billTo>
              </subscription>
          </ARBCreateSubscriptionRequest>";

		$this->process($xml);
	}


	/**
	 * Call authorize.net for processing payment
	 *
	 * @param string $xml
	 */
	protected function process($xml)
	{
		if ($this->params->get('authnet_mode'))
		{
			$url = "https://api.authorize.net/xml/v1/request.api";
		}
		else
		{
			$url = "https://apitest.authorize.net/xml/v1/request.api";
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_SSLVERSION, 6);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($ch);

		$this->parseResults($response);

		if ($this->resultCode === "Ok")
		{
			$this->success = true;
		}
		else
		{
			$this->success = false;
		}

		curl_close($ch);
	}

	/**
	 * Validate recurring payment
	 *
	 * @return bool
	 */
	protected function validate()
	{
		$this->notificationData = $_POST;
		$this->logGatewayData($this->notificationData);

		// Validate hash
		if ($this->params->get('hash_secret'))
		{
			// Validate hash
			$input         = JFactory::getApplication()->input;
			$hashPosted    = $input->post->get('x_MD5_Hash', null, 'raw');
			$transactionId = $input->post->get('x_trans_id', null, 'raw');
			$amount        = $input->post->get('x_amount', null, 'raw');

			$key = array(
				$this->params->get('hash_secret'),
				$this->params->get('x_login'),
				$transactionId,
				$amount,
			);

			$calculatedHash = md5(implode('', $key));

			if ($calculatedHash != $hashPosted)
			{
				return false;
			}
		}

		if (!empty($this->notificationData['x_subscription_id']) && @$this->notificationData['x_response_code'] == 1)
		{
			return true;
		}

		return false;
	}

	/**
	 * Process first payment for the subscription
	 *
	 * @param OSMembershipTablePlan $rowPlan
	 * @param array                 $data
	 *
	 * @return mixed false on failure, string contain transaction id on success
	 */
	protected function processFirstPayment($rowPlan, $data)
	{
		// Process the first payment
		if ($data['trial_duration'])
		{
			$paymentAmount       = $data['trial_amount'];
			$trialDurationUnit   = $data['trial_duration_unit'];
			$trialDurationLength = $data['trial_duration'];
		}
		else
		{
			$paymentAmount       = $data['regular_price'];
			$trialDurationUnit   = $rowPlan->subscription_length_unit;
			$trialDurationLength = $rowPlan->subscription_length;
		}

		switch ($trialDurationUnit)
		{
			case 'D':
				$this->parameters['startDate'] = date("Y-m-d", strtotime('+' . $trialDurationLength . ' days'));
				break;
			case 'W':
				$this->parameters['startDate'] = date("Y-m-d", strtotime('+' . $trialDurationLength . ' weeks'));
				break;
			case 'M':
				$this->parameters['startDate'] = date("Y-m-d", strtotime('+' . $trialDurationLength . ' months'));
				break;
			case 'Y':
				$this->parameters['startDate'] = date("Y-m-d", strtotime('+' . $trialDurationLength . ' years'));
				break;
		}

		/* @var \Omnipay\AuthorizeNet\AIMGateway $gateway */
		$gateway  = $this->getGateway();
		$cardData = $this->getOmnipayCard($data);

		/* @var $request \Omnipay\Common\Message\AbstractRequest */
		try
		{
			$request = $gateway->purchase(array('card' => $cardData));

			$request->setAmount($paymentAmount);
			$request->setCurrency($data['currency']);
			$request->setDescription($data['item_name']);

			/* @var $response \Omnipay\Common\Message\ResponseInterface */

			$response = $request->send();
		}
		catch (\Exception $e)
		{
			$session = JFactory::getSession();
			$session->set('omnipay_payment_error_reason', $e->getMessage());

			return false;
		}

		if ($response->isSuccessful())
		{
			return $response->getTransactionReference();
		}
		else
		{
			//Payment failure, display error message to users
			$session = JFactory::getSession();
			$session->set('omnipay_payment_error_reason', $response->getMessage());

			return false;
		}
	}

	/**
	 * Set data for a parameter
	 *
	 * @param string $name
	 * @param string $value
	 */
	protected function setParameter($name, $value)
	{
		$this->parameters[$name] = $value;
	}

	/**
	 * Get data for a parameter
	 *
	 * @param  string $name
	 * @param  mixed  $default
	 *
	 * @return null
	 */
	protected function getParameter($name, $default = null)
	{
		return isset($this->parameters[$name]) ? $this->parameters[$name] : $default;
	}

	/**
	 * Parse the xml to get the necessary information of the subscription
	 *
	 * @param string $response
	 *
	 * @return void
	 */
	protected function parseResults($response)
	{
		$this->resultCode     = self::substring_between($response, '<resultCode>', '</resultCode>');
		$this->code           = self::substring_between($response, '<code>', '</code>');
		$this->text           = self::substring_between($response, '<text>', '</text>');
		$this->subscriptionId = self::substring_between($response, '<subscriptionId>', '</subscriptionId>');
	}

	/**
	 * Get content between tags
	 *
	 * @param string $haystack
	 * @param string $start
	 * @param string $end
	 *
	 * @return bool|string
	 */
	protected static function substring_between($haystack, $start, $end)
	{
		if (strpos($haystack, $start) === false || strpos($haystack, $end) === false)
		{
			return false;
		}
		else
		{
			$start_position = strpos($haystack, $start) + strlen($start);
			$end_position   = strpos($haystack, $end);

			return substr($haystack, $start_position, $end_position - $start_position);
		}
	}
}
