<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die;

class os_paypal extends MPFPayment
{
	/**
	 * Constructor
	 *
	 * @param JRegistry $params
	 * @param array     $config
	 */
	public function __construct($params, $config = array())
	{
		parent::__construct($params, $config);

		$this->mode = $params->get('paypal_mode', 0);

		if ($this->mode)
		{
			$this->url = 'https://www.paypal.com/cgi-bin/webscr';
		}
		else
		{
			$this->url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
		}

		$this->setParameter('business', $params->get('paypal_id'));
		$this->setParameter('rm', 2);
		$this->setParameter('cmd', '_xclick');
		$this->setParameter('no_shipping', 1);
		$this->setParameter('no_note', 1);

		$locale = $params->get('paypal_locale');

		if (empty($locale))
		{
			if (JLanguageMultilang::isEnabled())
			{
				$locale = JFactory::getLanguage()->getTag();
			}
			else
			{
				$locale = 'en_US';
			}
		}

		$this->setParameter('lc', $locale);
		$this->setParameter('charset', 'utf-8');

		// Disable tax calculation if it is setup in the owner Paypal account
		$this->setParameter('tax', 0);
	}

	/**
	 * Process onetime subscription payment
	 *
	 * @param OSMembershipTableSubscriber $row
	 * @param array                       $data
	 */
	public function processPayment($row, $data)
	{
		$app    = JFactory::getApplication();
		$Itemid = $app->input->getInt('Itemid', 0);

		$siteUrl = JUri::base();

		$this->setParameter('currency_code', $data['currency']);
		$this->setParameter('item_name', $data['item_name']);
		$this->setParameter('amount', round($data['amount'], 2));
		$this->setParameter('custom', $row->id);

		$rowPlan = OSMembershipHelperDatabase::getPlan($row->plan_id);

		// Override PayPal email
		if ($rowPlan->paypal_email)
		{
			$this->setParameter('business', $rowPlan->paypal_email);
		}

		$this->setParameter('return', $this->getReturnUrl($Itemid));
		$this->setParameter('cancel_return', $siteUrl . 'index.php?option=com_osmembership&view=cancel&id=' . $row->id . '&Itemid=' . $Itemid);
		$this->setParameter('notify_url', $siteUrl . 'index.php?option=com_osmembership&task=payment_confirm&payment_method=os_paypal');
		$this->setParameter('address1', $row->address);
		$this->setParameter('address2', $row->address2);
		$this->setParameter('city', $row->city);
		$this->setParameter('country', $data['country']);
		$this->setParameter('first_name', $row->first_name);
		$this->setParameter('last_name', $row->last_name);
		$this->setParameter('state', $row->state);
		$this->setParameter('zip', $row->zip);
		$this->setParameter('email', $row->email);

		// Store receiver PayPal email before redirecting to PayPal
		$row->receiver_email = $this->getParameter('business');
		$row->store();

		$this->renderRedirectForm();
	}

	/**
	 * Verify onetime subscription payment
	 *
	 * @return bool
	 */
	public function verifyPayment()
	{
		// First, validate and make sure the IPN message is valid
		if (!$this->validate())
		{
			return false;
		}

		/* @var OSMembershipTableSubscriber $row */
		$row           = JTable::getInstance('OsMembership', 'Subscriber');
		$id            = $this->notificationData['custom'];
		$transactionId = $this->notificationData['txn_id'];

		// Make sure each transaction is only processed once
		if ($transactionId && OSMembershipHelper::isTransactionProcessed($transactionId))
		{
			return false;
		}

		$amount = floatval($this->notificationData['mc_gross']);

		if ($amount < 0)
		{
			return false;
		}

		if (!$row->load($id))
		{
			return false;
		}

		if ($row->published)
		{
			return false;
		}

		// Accept 0.05$ difference to avoid bug causes by rounding
		if (($row->payment_amount - $amount) > 0.05)
		{
			return false;
		}

		// Validate receiver
		if (!$this->validateReceiver($row))
		{
			return false;
		}

		// Validate currency
		if (!$this->validateCurrency($row))
		{
			return false;
		}

		// Validate payment status (only on live mode because PayPal sandbox sometime doesn't work very well)
		if ($this->mode && ($this->notificationData['payment_status'] != 'Completed'))
		{
			return false;
		}

		$this->onPaymentSuccess($row, $transactionId);
	}

	/**
	 * Process recurring subscription payment
	 *
	 * @param OSMembershipTableSubscriber $row
	 * @param array                       $data
	 */
	public function processRecurringPayment($row, $data)
	{
		$app     = JFactory::getApplication();
		$siteUrl = JUri::base();
		$Itemid  = $app->input->getInt('Itemid', 0);

		$rowPlan = OSMembershipHelperDatabase::getPlan($row->plan_id);

		$this->setParameter('currency_code', $data['currency']);
		$this->setParameter('item_name', $data['item_name']);
		$this->setParameter('custom', $row->id);

		// Override Paypal email if needed
		if ($rowPlan->paypal_email)
		{
			$this->setParameter('business', $rowPlan->paypal_email);
		}

		$this->setParameter('return', $this->getReturnUrl($Itemid));
		$this->setParameter('cancel_return', $siteUrl . 'index.php?option=com_osmembership&view=cancel&id=' . $row->id . '&Itemid=' . $Itemid);
		$this->setParameter('notify_url', $siteUrl . 'index.php?option=com_osmembership&task=recurring_payment_confirm&payment_method=os_paypal');
		$this->setParameter('cmd', '_xclick-subscriptions');
		$this->setParameter('src', 1);
		$this->setParameter('sra', 1);
		$this->setParameter('a3', $data['regular_price']);
		$this->setParameter('address1', $row->address);
		$this->setParameter('address2', $row->address2);
		$this->setParameter('city', $row->city);
		$this->setParameter('country', $data['country']);
		$this->setParameter('first_name', $row->first_name);
		$this->setParameter('last_name', $row->last_name);
		$this->setParameter('state', $row->state);
		$this->setParameter('zip', $row->zip);
		$this->setParameter('p3', $rowPlan->subscription_length);
		$this->setParameter('t3', $rowPlan->subscription_length_unit);
		$this->setParameter('lc', 'US');

		if ($rowPlan->number_payments > 1)
		{
			$this->setParameter('srt', $rowPlan->number_payments);
		}

		if ($data['trial_duration'])
		{
			$this->setParameter('a1', $data['trial_amount']);
			$this->setParameter('p1', $data['trial_duration']);
			$this->setParameter('t1', $data['trial_duration_unit']);
		}

		// Store receiver PayPal email before redirecting to PayPal
		$row->receiver_email = $this->getParameter('business');
		$row->store();

		//Redirect users to PayPal for processing payment
		$this->renderRedirectForm();
	}

	/**
	 * Verify recurring payment and extend the subscription if needed
	 */
	public function verifyRecurringPayment()
	{
		// First, validate and
		if (!$this->validate())
		{
			return false;
		}

		$id             = $this->notificationData['custom'];
		$transactionId  = $this->notificationData['txn_id'];
		$subscriptionId = $this->notificationData['subscr_id'];
		$amount         = floatval($this->notificationData['mc_gross']);
		$txnType        = $this->notificationData['txn_type'];

		if ($subscriptionId)
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('id')
				->from('#__osmembership_subscribers')
				->where('subscription_id = ' . $db->quote($subscriptionId))
				->order('id');
			$db->setQuery($query);

			if ($recordId = $db->loadResult())
			{
				$id = $recordId;
			}
		}

		if ($amount < 0)
		{
			return false;
		}

		if ($transactionId && OSMembershipHelper::isTransactionProcessed($transactionId))
		{
			return false;
		}

		/* @var OSMembershipTableSubscriber $row */
		$row = JTable::getInstance('OsMembership', 'Subscriber');

		if (!$row->load($id))
		{
			return false;
		}

		switch ($txnType)
		{
			case 'subscr_signup':
				if ($row->published)
				{
					return false;
				}

				$row->subscription_id = $subscriptionId;

				if ($row->is_free_trial)
				{
					$row->transaction_id = '';
					$this->onPaymentSuccess($row, $transactionId);
				}
				else
				{
					$row->store();
				}
				break;
			case 'subscr_payment':
				// Validate payment amount and payment currency
				if ($row->payment_currency && !$this->validatePaymentAmountAndCurrency($row))
				{
					return false;
				}

				// First payment (for not free trial subscription), calling  onPaymentSuccess method to send email to subscribers
				if (!$row->is_free_trial && !$row->published && $row->payment_made == 0)
				{
					$row->payment_made    = 1;
					$row->subscription_id = $subscriptionId;
					$this->onPaymentSuccess($row, $transactionId);

					return true;
				}

				// Valid recurring payment, extend the subscription
				/* @var OSMembershipModelApi $model */
				$model = MPFModel::getInstance('Api', 'OSMembershipModel', ['ignore_request' => true]);
				$model->renewRecurringSubscription($id, $subscriptionId, $transactionId);
				break;
			case 'subscr_cancel':
				OSMembershipHelperSubscription::cancelRecurringSubscription($id);
				break;

		}
	}

	/**
	 * Get list of supported currencies
	 *
	 * @return array
	 */
	public function getSupportedCurrencies()
	{
		return array(
			'AUD',
			'BRL',
			'CAD',
			'CZK',
			'DKK',
			'EUR',
			'HKD',
			'HUF',
			'ILS',
			'JPY',
			'MYR',
			'MXN',
			'NOK',
			'NZD',
			'PHP',
			'PLN',
			'GBP',
			'RUB',
			'SGD',
			'SEK',
			'CHF',
			'TWD',
			'THB',
			'TRY',
			'USD',
		);
	}

	/**
	 * Validate the post data from PayPal to our server
	 *
	 * @return string
	 */
	protected function validate()
	{
		JLoader::register('PaypalIPN', JPATH_ROOT . '/components/com_osmembership/plugins/paypal/PayPalIPN.php');

		$ipn = new PaypalIPN;

		// Use sandbox URL if test mode is configured
		if (!$this->mode)
		{
			$ipn->useSandbox();
		}

		// Disable use custom certs
		$ipn->usePHPCerts();

		$this->notificationData = $_POST;

		try
		{
			$valid = $ipn->verifyIPN();
			$this->logGatewayData($ipn->getResponse());

			if (!$this->mode || $valid)
			{
				return true;
			}

			return false;
		}
		catch (Exception $e)
		{
			$this->logGatewayData($e->getMessage());

			return false;
		}
	}

	/**
	 * Validate and make sure the payment is sent to correct receiver
	 *
	 * @param OSMembershipTableSubscriber $row
	 *
	 * @return bool
	 */
	protected function validateReceiver($row)
	{
		$receiverEmail = strtoupper($this->notificationData['receiver_email']);
		$receiverId    = strtoupper($this->notificationData['receiver_id']);
		$business      = strtoupper($this->notificationData['business']);

		$validReceiver = strtoupper($row->receiver_email);

		if ($receiverEmail != $validReceiver
			&& $receiverId != $validReceiver
			&& $business != $validReceiver)
		{
			return false;
		}

		return true;
	}

	/**
	 * Validate and make sure the payment is received in valid currency
	 *
	 * @param OSMembershipTableSubscriber $row
	 *
	 * @return bool
	 */
	protected function validateCurrency($row)
	{
		$receivedCurrency = strtoupper($this->notificationData['mc_currency']);
		$validCurrency    = strtoupper($row->payment_currency);

		if ($receivedCurrency != $validCurrency)
		{
			return false;
		}

		return true;
	}

	/**
	 * Validate payment amount and currency of recurring payment
	 *
	 * @param OSMembershipTableSubscriber $row
	 *
	 * @return bool
	 */
	protected function validatePaymentAmountAndCurrency($row)
	{
		// Validate receiver account
		if (!$this->validateCurrency($row))
		{
			return false;
		}

		// Validate currency
		if (!$this->validateCurrency($row))
		{
			return false;
		}

		// Validate payment amount
		$amount = floatval($this->notificationData['mc_gross']);

		if ($row->payment_made == 0)
		{
			if ($row->trial_payment_amount > 0)
			{
				$expectedPaymentAmount = $row->trial_payment_amount;
			}
			else
			{
				$expectedPaymentAmount = $row->payment_amount;
			}
		}
		else
		{
			$expectedPaymentAmount = $row->payment_amount;
		}

		if (($expectedPaymentAmount - $amount) > 0.05)
		{
			return false;
		}

		return true;
	}

	/**
	 * Get SEF return URL after processing payment
	 *
	 * @param int $Itemid
	 *
	 * @return string
	 */
	protected function getReturnUrl($Itemid)
	{
		$rootURL    = rtrim(JUri::root(), '/');
		$subpathURL = JUri::root(true);

		if (!empty($subpathURL) && ($subpathURL != '/'))
		{
			$rootURL = substr($rootURL, 0, -1 * strlen($subpathURL));
		}

		return $rootURL . JRoute::_(OSMembershipHelperRoute::getViewRoute('complete', $Itemid), false);
	}
}
