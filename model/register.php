<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

class OSMembershipModelRegister extends MPFModel
{
	use OSMembershipModelSubscriptiontrait, OSMembershipModelValidationtrait;

	/**
	 * Process Subscription
	 *
	 * @param array    $data
	 * @param MPFInput $input
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function processSubscription($data, $input)
	{
		jimport('joomla.user.helper');

		$config = OSMembershipHelper::getConfig();
		$user   = JFactory::getUser();
		$userId = $user->get('id');

		/* @var $row OSMembershipTableSubscriber */
		$row = $this->getTable('Subscriber');

		// Create user account
		if (!$userId && $config->registration_integration)
		{
			if ($config->create_account_when_membership_active !== '1')
			{
				$userId = OSMembershipHelper::saveRegistration($data);
			}
			else
			{
				//Encrypt the password and store into  #__osmembership_subscribers table and create the account layout
				$privateKey            = md5(JFactory::getConfig()->get('secret'));
				$key                   = new JCryptKey('simple', $privateKey, $privateKey);
				$crypt                 = new JCrypt(new JCryptCipherSimple, $key);
				$data['user_password'] = $crypt->encrypt($data['password1']);
			}
		}

		// Uploading avatar
		$avatar = $input->files->get('profile_avatar');

		if ($avatar['name'])
		{
			$this->uploadAvatar($avatar, $row);
		}


		$row->bind($data);

		// Set subscription data which is not available from request
		$row->agree_privacy_policy = 1;

		if ($config->show_subscribe_newsletter_checkbox)
		{
			$row->subscribe_newsletter = empty($data['subscribe_to_newsletter']) ? 0 : 1;
		}
		else
		{
			$row->subscribe_newsletter = 1;
		}

		$row->id             = 0;
		$row->plan_id        = (int) $row->plan_id;
		$row->user_id        = (int) $userId;
		$row->transaction_id = strtoupper(JUserHelper::genRandomPassword(16));
		$row->created_date   = JFactory::getDate()->toSql();
		$row->language       = JFactory::getLanguage()->getTag();
		$row->published      = 0;

		$rowPlan = OSMembershipHelperDatabase::getPlan($row->plan_id);

		// Disable free trial if he subscribed before
		OSMembershipHelperSubscription::disableFreeTrialForPlan($rowPlan);

		// Get subscription related custom fields and build the form object
		list($rowFields, $formFields) = $this->getFields($row->plan_id, true, null, $row->act);

		$form = new MPFForm($formFields);
		$form->setData($data)
			->bindData();

		// Calculate and set subscription start date, end date
		$fromDate = $this->calculateSubscriptionFromDate($row, $rowPlan);

		// Calculate subscription end date
		$this->calculateSubscriptionEndDate($row, $rowPlan, $fromDate, $rowFields, $data);

		// Calculate and store subscription fees
		if (is_callable('OSMembershipHelperOverrideHelper::calculateSubscriptionFee'))
		{
			$fees = OSMembershipHelperOverrideHelper::calculateSubscriptionFee($rowPlan, $form, $data, $config, $row->payment_method);
		}
		else
		{
			$fees = OSMembershipHelper::calculateSubscriptionFee($rowPlan, $form, $data, $config, $row->payment_method);
		}

		// Store payment amounts data for subscription
		$this->setSubscriptionAmounts($row, $fees, (bool) $rowPlan->recurring_subscription);

		// Update coupon usage if there is coupon uses for the subscription
		$couponCode = $input->getString('coupon_code');

		if ($couponCode && $fees['coupon_valid'])
		{
			$this->updateAndStoreCouponUsage($row, $fees, $couponCode);
		}

		// Mark subscription as free trial if needed to make it easier for payment processing
		if ($rowPlan->recurring_subscription && $fees['trial_duration'] > 0 && $fees['trial_gross_amount'] == 0)
		{
			$row->is_free_trial = 1;
		}
		else
		{
			$row->is_free_trial = 0;
		}

		$row->store();

		//Store custom fields data
		$form->storeFormData($row->id, $data);

		//Synchronize profile data for other records
		if ($config->synchronize_data !== '0')
		{
			OSMembershipHelperSubscription::synchronizeProfileData($row, $rowFields);
		}

		// Trigger onAfterStoreSubscription event
		JPluginHelper::importPlugin('osmembership');
		$app = JFactory::getApplication();
		$app->triggerEvent('onAfterStoreSubscription', array($row));

		// Store subscription code into session so that we won't have to pass it in URL, support Paypal auto return
		JFactory::getSession()->set('mp_subscription_id', $row->id);

		// Prepare payment amounts and pass it to payment plugin for payment processing
		$data['amount'] = $row->gross_amount;

		if ($rowPlan->recurring_subscription)
		{
			$data['regular_price']       = $fees['regular_gross_amount'];
			$data['trial_amount']        = $fees['trial_gross_amount'];
			$data['trial_duration']      = $fees['trial_duration'];
			$data['trial_duration_unit'] = $fees['trial_duration_unit'];
		}
		else
		{
			$data['regular_price']       = 0;
			$data['trial_amount']        = 0;
			$data['trial_duration']      = 0;
			$data['trial_duration_unit'] = '';
		}

		if ($data['amount'] > 0 || ($rowPlan->recurring_subscription && $data['regular_price'] > 0))
		{
			$this->processPayment($row, $rowPlan, $data);
		}
		else
		{
			$this->completeFreeSubscription($row, $rowPlan);

			$app->redirect(JRoute::_('index.php?option=com_osmembership&view=complete&Itemid=' . $input->getInt('Itemid', 0), false));
		}
	}

	/**
	 * Method to cancel a recurring subscription
	 *
	 * @param OSMembershipTableSubscriber $row
	 *
	 * @return bool
	 *
	 * @throws Exception
	 */
	public function cancelSubscription($row)
	{
		$method = OSMembershipHelper::loadPaymentMethod($row->payment_method);

		/* @var os_authnet $method */
		$ret = $method->cancelSubscription($row);

		if ($ret)
		{
			OSMembershipHelperSubscription::cancelRecurringSubscription($row->id);
		}

		return $ret;
	}

	/**
	 * Verify payment
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function paymentConfirm($paymentMethod)
	{
		/* @var os_paypal $method */
		$method = OSMembershipHelper::loadPaymentMethod($paymentMethod);

		$method->verifyPayment();
	}

	/**
	 * Verify recurring payment
	 *
	 * @param $paymentMethod
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function recurringPaymentConfirm($paymentMethod)
	{
		/* @var os_paypal $method */
		$method = OSMembershipHelper::loadPaymentMethod($paymentMethod);

		$method->verifyRecurringPayment();
	}

	/**
	 * Finish subscription process for free subscription
	 *
	 * @param OSMembershipTableSubscriber $row
	 * @param OSMembershipTablePlan       $rowPlan
	 */
	protected function completeFreeSubscription($row, $rowPlan)
	{
		$config = OSMembershipHelper::getConfig();

		$row->published = 1;

		if ($rowPlan->price == 0
			&& isset($config->free_plans_subscription_status)
			&& $config->free_plans_subscription_status === '0')
		{
			$row->published = 0;
		}

		$row->store();

		if ($row->act == 'upgrade')
		{
			OSMembershipHelperSubscription::processUpgradeMembership($row);
		}

		if ($row->published == 1)
		{
			JPluginHelper::importPlugin('osmembership');
			JFactory::getApplication()->triggerEvent('onMembershipActive', array($row));
		}

		OSMembershipHelper::sendEmails($row, $config);
	}

	/**
	 * Form form some basic validation to make sure the data is valid
	 *
	 * @param MPFInput $input
	 *
	 * @return array
	 */
	public function validate($input)
	{
		$data              = $input->post->getData();
		$db                = $this->getDbo();
		$query             = $db->getQuery(true);
		$config            = OSMembershipHelper::getConfig();
		$rowFields         = OSMembershipHelper::getProfileFields((int) $data['plan_id'], true, null, $input->getCmd('act'));
		$userId            = JFactory::getUser()->id;
		$filterInput       = JFilterInput::getInstance();
		$createUserAccount = $config->registration_integration && !$userId;
		$errors            = [];

		// Validate username and password
		if ($createUserAccount)
		{
			$username = isset($data['username']) ? $data['username'] : '';
			$password = isset($data['password1']) ? $data['password1'] : '';

			$errors = array_merge($errors, $this->validateUsername($username));
			$errors = array_merge($errors, $this->validatePassword($password));
		}

		// Validate email
		$email  = isset($data['email']) ? $data['email'] : '';
		$errors = array_merge($errors, $this->validateEmail($email, $createUserAccount));

		// Validate avatar
		$avatar = $input->files->get('profile_avatar');

		if ($avatar['name'])
		{
			$fileExt        = StringHelper::strtoupper(JFile::getExt($avatar['name']));
			$supportedTypes = array('JPG', 'PNG', 'GIF');

			if (!in_array($fileExt, $supportedTypes))
			{
				$errors[] = JText::_('OSM_INVALID_AVATAR');
			}

			$imageSizeData = getimagesize($avatar['tmp_name']);

			if ($imageSizeData === false)
			{
				$errors[] = JText::_('OSM_INVALID_AVATAR');
			}
		}

		// Validate name
		$name = trim($data['first_name'] . ' ' . $data['last_name']);

		if ($filterInput->clean($name, 'TRIM') == '')
		{
			$errors[] = JText::_('JLIB_DATABASE_ERROR_PLEASE_ENTER_YOUR_NAME');
		}

		// Validate form fields
		$form = new MPFForm($rowFields);
		$form->setData($data)->bindData();
		$form->buildFieldsDependency(false);

		// If there is error message, use it
		if ($formErrors = $form->validate())
		{
			$errors = array_merge($errors, $formErrors);
		}

		// Validate privacy policy
		if ($config->show_privacy_policy_checkbox && empty($data['agree_privacy_policy']))
		{
			$errors[] = JText::_('OSM_AGREE_PRIVACY_POLICY_ERROR');
		}

		// Validate renew subscription using offline payment multiple times
		$paymentMethod = isset($data['payment_method']) ? $data['payment_method'] : '';
		$plan          = OSMembershipHelperDatabase::getPlan((int) $data['plan_id']);

		if ($plan->price > 0 && $userId > 0 && strpos($paymentMethod, 'os_offline') !== false)
		{
			$query->clear()
				->select('COUNT(*)')
				->from('#__osmembership_subscribers')
				->where('user_id = ' . (int) $userId)
				->where('plan_id = ' . (int) $data['plan_id'])
				->where('published = 0')
				->where('payment_method LIKE "os_offline%"')
				->where('to_date > ' . $db->quote(JFactory::getDate()->toSql()));
			$db->setQuery($query);
			$total = $db->loadResult();

			if ($total)
			{
				// This user has an offline payment renewal has not paid yet, disable renewal using offline payment again
				$errors[] = JText::_('OSM_HAD_OFFLINE_PAYMENT_RENEWAL_ALREADY');
			}
		}

		return $errors;
	}

	/**
	 * Method to set fees data for the subscription record
	 *
	 * @param OSMembershipTableSubscriber $row
	 * @param array                       $fees
	 * @param bool                        $isRecurringSubscription
	 *
	 * return void
	 */
	protected function setSubscriptionAmounts($row, $fees, $isRecurringSubscription)
	{
		$row->setup_fee = $fees['setup_fee'];
		$row->tax_rate  = $fees['tax_rate'];

		if ($isRecurringSubscription)
		{
			if ($fees['trial_duration'] > 0)
			{
				$row->amount                 = $fees['trial_amount'] - $row->setup_fee;
				$row->discount_amount        = $fees['trial_discount_amount'];
				$row->tax_amount             = $fees['trial_tax_amount'];
				$row->payment_processing_fee = $fees['trial_payment_processing_fee'];
				$row->gross_amount           = $fees['trial_gross_amount'];
			}
			else
			{
				$row->amount                 = $fees['regular_amount'];
				$row->discount_amount        = $fees['regular_discount_amount'];
				$row->tax_amount             = $fees['regular_tax_amount'];
				$row->payment_processing_fee = $fees['regular_payment_processing_fee'];
				$row->gross_amount           = $fees['regular_gross_amount'];
			}
		}
		else
		{
			$row->amount                 = $fees['amount'];
			$row->discount_amount        = $fees['discount_amount'];
			$row->tax_amount             = $fees['tax_amount'];
			$row->payment_processing_fee = $fees['payment_processing_fee'];
			$row->gross_amount           = $fees['gross_amount'];
		}

		// Store regular payment amount for recurring subscriptions
		if ($isRecurringSubscription)
		{
			$params = new Registry($row->params);
			$params->set('regular_amount', $fees['regular_amount']);
			$params->set('regular_discount_amount', $fees['regular_discount_amount']);
			$params->set('regular_tax_amount', $fees['regular_tax_amount']);
			$params->set('regular_payment_processing_fee', $fees['regular_payment_processing_fee']);
			$params->set('regular_gross_amount', $fees['regular_gross_amount']);
			$row->params = $params->toString();

			// In case the coupon discount is 100%, we treat this as lifetime membership
			if ($fees['regular_gross_amount'] == 0)
			{
				$row->to_date = '2099-12-31 23:59:59';
			}
		}
	}

	/**
	 * Update and store coupon usage
	 *
	 * @param OSMembershipTableSubscriber $row
	 * @param array                       $fees
	 * @param string                      $couponCode
	 *
	 * @return void
	 */
	protected function updateAndStoreCouponUsage($row, $fees, $couponCode)
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		// We need this check to make it backward compatible with existing override
		if (!empty($fees['coupon_id']))
		{
			$couponId = (int) $fees['coupon_id'];
		}
		else
		{
			$query->select('id')
				->from('#__osmembership_coupons')
				->where('code = ' . $db->quote($couponCode));
			$db->setQuery($query);
			$couponId = (int) $db->loadResult();
		}

		$query->clear()
			->update('#__osmembership_coupons')
			->set('used = used + 1')
			->where('id = ' . $couponId);
		$db->setQuery($query);
		$db->execute();

		$row->coupon_id = $couponId;
	}

	/**
	 * Process payment for subscripotion via selected payment gateway
	 *
	 * @param OSMembershipTableSubscriber $row
	 * @param OSMembershipTablePlan       $rowPlan
	 * @param array                       $data
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function processPayment($row, $rowPlan, $data)
	{
		$config = OSMembershipHelper::getConfig();
		$db     = $this->getDbo();
		$query  = $db->getQuery(true);

		switch ($row->act)
		{
			case 'renew':
				$itemName = JText::_('OSM_PAYMENT_FOR_RENEW_SUBSCRIPTION');
				$itemName = str_replace('[PLAN_TITLE]', $rowPlan->title, $itemName);
				break;
			case 'upgrade':
				$itemName = JText::_('OSM_PAYMENT_FOR_UPGRADE_SUBSCRIPTION');
				$itemName = str_replace('[PLAN_TITLE]', $rowPlan->title, $itemName);

				//Get from Plan Title
				$query->select('a.title')
					->from('#__osmembership_plans AS a')
					->innerJoin('#__osmembership_upgraderules AS b ON a.id = b.from_plan_id')
					->where('b.id = ' . $row->upgrade_option_id);
				$db->setQuery($query);
				$fromPlanTitle = $db->loadResult();
				$itemName      = str_replace('[FROM_PLAN_TITLE]', $fromPlanTitle, $itemName);
				break;
			default:
				$itemName = JText::_('OSM_PAYMENT_FOR_SUBSCRIPTION');
				$itemName = str_replace('[PLAN_TITLE]', $rowPlan->title, $itemName);
				break;
		}

		$data['item_name'] = $itemName;

		$paymentMethod = $data['payment_method'];
		$paymentClass  = OSMembershipHelper::loadPaymentMethod($paymentMethod);

		// Convert payment amount to USD if the currency is not supported by payment gateway
		$currency = $rowPlan->currency ? $rowPlan->currency : $config->currency_code;

		if (method_exists($paymentClass, 'getSupportedCurrencies'))
		{
			$currencies = $paymentClass->getSupportedCurrencies();

			if (!in_array($currency, $currencies))
			{
				if ($data['amount'] > 0)
				{
					$data['amount'] = OSMembershipHelper::convertAmountToUSD($data['amount'], $currency);
				}

				if ($data['regular_price'] > 0)
				{
					$data['regular_price'] = OSMembershipHelper::convertAmountToUSD($data['regular_price'], $currency);
				}

				if ($data['trial_amount'] > 0)
				{
					$data['trial_amount'] = OSMembershipHelper::convertAmountToUSD($data['trial_amount'], $currency);
				}

				$currency = 'USD';
			}
		}

		$data['currency'] = $currency;

		if (!empty($data['x_card_num']))
		{
			if (empty($data['card_type']))
			{
				$data['card_type'] = OSMembershipHelperCreditcard::getCardType($data['x_card_num']);
			}
		}

		$country         = empty($data['country']) ? $config->default_country : $data['country'];
		$data['country'] = OSMembershipHelper::getCountryCode($country);

		// Round payment amount before passing to payment gateway
		if ($data['amount'] > 0)
		{
			$data['amount'] = round($data['amount'], 2);
		}

		if ($data['regular_price'] > 0)
		{
			$data['regular_price'] = round($data['regular_price'], 2);
		}

		if ($data['trial_amount'] > 0)
		{
			$data['trial_amount'] = round($data['trial_amount'], 2);
		}

		// Store payment currency and payment amount for future validation
		$row->payment_currency = $currency;

		if ($rowPlan->recurring_subscription)
		{
			$row->trial_payment_amount = $data['trial_amount'];
			$row->payment_amount       = $data['regular_price'];
		}
		else
		{
			$row->payment_amount = $data['amount'];
		}

		$row->store();

		if ($rowPlan->recurring_subscription && method_exists($paymentClass, 'processRecurringPayment'))
		{
			$paymentClass->processRecurringPayment($row, $data);
		}
		else
		{
			$paymentClass->processPayment($row, $data);
		}
	}
}
