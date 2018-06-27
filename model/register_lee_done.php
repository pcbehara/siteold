<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die;

class OSMembershipModelRegister extends MPFModel
{
	/**
	 * Process Subscription
	 *
	 * @param array    $data
	 * @param MPFInput $input
	 */
	public function processSubscription($data, $input)
	{
		
		jimport('joomla.user.helper');
		$config      = OSMembershipHelper::getConfig();
		$fieldSuffix = OSMembershipHelper::getFieldSuffix();

		$db       = JFactory::getDbo();
		$query    = $db->getQuery(true);
		$nullDate = $db->getNullDate();

		$user   = JFactory::getUser();
		$userId = $user->get('id');

		/* @var $row OSMembershipTableSubscriber */
		$row = JTable::getInstance('OsMembership', 'Subscriber');

		/* Lee comment: Create an account for Joomla User */

		

		// For checking - Lee

		if (!$userId && $config->registration_integration)
		{
			//Store user account into Joomla users database
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
		
	
		$data['transaction_id'] = strtoupper(JUserHelper::genRandomPassword(16));

		// Uploading avatar
		$avatar = $input->files->get('profile_avatar');
		if ($avatar['name'])
		{
			$fileName   = $userId . '.' . JString::strtoupper(JFile::getExt($avatar['name']));
			$avatarPath = JPATH_ROOT . '/media/com_osmembership/avatars/' . $fileName;
			JFile::upload($avatar['tmp_name'], $avatarPath);

			$image  = new JImage($avatarPath);
			$width  = $config->avatar_width ? $config->avatar_width : 80;
			$height = $config->avatar_height ? $config->avatar_height : 80;
			$image->cropResize($width, $height, false)
				->toFile($avatarPath);

			$data['avatar'] = $fileName;

			$query->update('#__osmembership_subscribers')
				->set('avatar = ' . $db->quote($fileName))
				->where('user_id = ' . $userId)
				->where('user_id > 0');
			$db->setQuery($query);
			$db->execute();
		}

		/* Lee comment: Blind Subscriber Table */

		$row->bind($data);
		$row->published        = 0;
		$row->created_date     = JFactory::getDate()->toSql();
		$row->user_id          = $userId;
		$row->is_profile       = 1;
		$row->plan_main_record = 1;

		if ($userId > 0)
		{
			/* Lee comment: get subscriberID */
			$query->clear()
				->select('id')
				->from('#__osmembership_subscribers')
				->where('user_id = ' . $userId)
				->where('is_profile = 1');
			$db->setQuery($query);
			$profileId = $db->loadResult();

			if ($profileId)
			{
				$row->is_profile = 0;
				$row->profile_id = $profileId;
			}

			/* Lee comment: update StartDate */			
			$query->clear()
				->select('plan_subscription_from_date')
				->from('#__osmembership_subscribers')
				->where('plan_main_record = 1')
				->where('user_id = ' . $userId)
				->where('plan_id = ' . $row->plan_id);
			$db->setQuery($query);
			$planMainRecord = $db->loadObject();
			if ($planMainRecord)
			{
				$row->plan_main_record            = 0;
				$row->plan_subscription_from_date = $planMainRecord->plan_subscription_from_date;
			}
		}

		/* Lee comment: set Language */		

		$row->language = JFactory::getLanguage()->getTag();

		/* Lee comment: get Custom Fields */

		$query->clear()
			->select('*')
			->from('#__osmembership_plans')
			->where('id = ' . (int) $data['plan_id']);

		if ($fieldSuffix)
		{
			OSMembershipHelperDatabase::getMultilingualFields($query, array('title'), $fieldSuffix);
		}
		$db->setQuery($query);
		$rowPlan = $db->loadObject();

		$rowFields = OSMembershipHelper::getProfileFields($row->plan_id, false);
		$form      = new MPFForm($rowFields);
		$form->setData($data)->bindData(true);


		/* Lee comment: calculate Fee */
		$fees   = OSMembershipHelper::calculateSubscriptionFee($rowPlan, $form, $data, $config, $row->payment_method);


		/* Lee comment: get dateIntervalSpec - Ex:P1M */

		$action = $data['act'];

		if ($action == 'renew')
		{
			$renewOptionId = (int) $data['renew_option_id'];
			if ($renewOptionId == OSM_DEFAULT_RENEW_OPTION_ID)
			{
				$dateIntervalSpec = 'P' . $rowPlan->subscription_length . $rowPlan->subscription_length_unit;
			}
			else
			{
				$query->clear()
					->select('*')
					->from('#__osmembership_renewrates')
					->where('id = ' . $renewOptionId);
				$db->setQuery($query);
				$renewOption      = $db->loadObject();
				$dateIntervalSpec = 'P' . $renewOption->renew_option_length . $renewOption->renew_option_length_unit;
			}
		}
		elseif ($action == 'upgrade')
		{
			$dateIntervalSpec = 'P' . $rowPlan->subscription_length . $rowPlan->subscription_length_unit;
		}
		else
		{
			if ($rowPlan->recurring_subscription && $rowPlan->trial_duration)
			{
				$dateIntervalSpec = 'P' . $rowPlan->trial_duration . $rowPlan->trial_duration_unit;
			}
			else
			{
				$dateIntervalSpec = 'P' . $rowPlan->subscription_length . $rowPlan->subscription_length_unit;
			}
		}

		/* Lee comment: check $maxDate & calculate from_date again */

		$maxDate = null;
		if ($row->user_id > 0)
		{
			//Subscriber, user existed
			$query->clear()
				->select('MAX(to_date)')
				->from('#__osmembership_subscribers')
				->where('user_id = ' . $row->user_id)
				->where('plan_id = ' . $row->plan_id)
				->where('(published = 1 OR (published = 0 AND payment_method LIKE "os_offline%"))');
			$db->setQuery($query);
			$maxDate = $db->loadResult();
		}

		if ($maxDate)
		{
			$date           = JFactory::getDate($maxDate);
			$row->from_date = $date->add(new DateInterval('P1D'))->toSql();
		}
		else
		{
			$date           = JFactory::getDate();
			$row->from_date = $date->toSql();
		}
		
		/* Lee comment: calculate to_date again */

		if ($rowPlan->expired_date && $rowPlan->expired_date != $nullDate)
		{
			$expiredDate = JFactory::getDate($rowPlan->expired_date, JFactory::getConfig()->get('offset'));

			// Change year of expired date to current year
			if ($date->year > $expiredDate->year)
			{
				$expiredDate->setDate($date->year, $expiredDate->month, $expiredDate->day);
			}

			$expiredDate->setTime(23, 59, 59);
			$date->setTime(23, 59, 59);

			$numberYears = 1;
			if ($action == 'renew')
			{
				if ($renewOptionId == OSM_DEFAULT_RENEW_OPTION_ID)
				{
					if ($rowPlan->subscription_length_unit == 'Y')
					{
						$numberYears = $rowPlan->subscription_length;
					}
				}
				else
				{
					list($renewOptionFrequency, $renewOptionLength) = OSMembershipHelper::getRecurringSettingOfPlan($numberDays);
					if ($renewOptionFrequency == 'Y' && $renewOptionLength > 1)
					{
						$numberYears = $renewOptionLength;
					}
				}
			}
			else
			{
				if ($rowPlan->subscription_length_unit == 'Y')
				{
					$numberYears = $rowPlan->subscription_length;
				}
			}

			if ($date >= $expiredDate)
			{
				$numberYears++;
			}

			$expiredDate->setDate($expiredDate->year + $numberYears - 1, $expiredDate->month, $expiredDate->day);

			$row->to_date = $expiredDate->toSql();
		}
		else
		{
			if ($rowPlan->lifetime_membership)
			{
				$row->to_date = '2099-12-31 23:59:59';
			}
			else
			{
				$row->to_date = $date->add(new DateInterval($dateIntervalSpec))->toSql();
			}
		}

		/* Lee comment: Check couponCode */

		$couponCode = $input->getString('coupon_code');
		$couponId   = 0;
		if ($couponCode && $fees['coupon_valid'])
		{
			$query->clear()
				->select('id')
				->from('#__osmembership_coupons')
				->where('code = ' . $db->quote($couponCode));
			$db->setQuery($query);
			$couponId = (int) $db->loadResult();

			$query->clear()
				->update('#__osmembership_coupons')
				->set('used = used + 1')
				->where('id = ' . $couponId);
			$db->setQuery($query);
			$db->execute();
		}

		/* Lee comment: Check recurring subscription,after that Update: amount,discount_amount,tax_amount,payment_processing_fee,gross_amount */

		if ($rowPlan->recurring_subscription)
		{
			if ($fees['trial_duration'] > 0)
			{
				$row->amount                 = $fees['trial_amount'];
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
		if ($rowPlan->recurring_subscription)
		{
			$params = new JRegistry($row->params);
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
		$row->coupon_id = $couponId;

		if ($row->plan_main_record == 1)
		{
			$row->plan_subscription_status    = $row->published;
			$row->plan_subscription_from_date = $row->from_date;
			$row->plan_subscription_to_date   = $row->to_date;
		}



		// For checking - Lee
		
		$row->store();

		if (!$row->profile_id)
		{
			$row->profile_id = $row->id;
			$row->store();
		}

		$data['amount'] = $row->gross_amount;

		if ($action == 'renew')
		{
			OSMembershipHelper::synchronizeHiddenFieldsData($row, $data);
		}

		//Store custom field data
		$form->storeData($row->id, $data);

		//Synchronize profile data for other records
		if ($config->synchronize_data !== '0')
		{
			OSMembershipHelper::syncronizeProfileData($row, $data);
		}
		

		

		JPluginHelper::importPlugin('osmembership');
		$dispatcher = JEventDispatcher::getInstance();
		$dispatcher->trigger('onAfterStoreSubscription', array($row));

		// Store subscription code into session so that we won't have to pass it in URL, support Paypal auto return
		JFactory::getSession()->set('mp_subscription_id', $row->id);

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
			switch ($action)
			{
				case 'renew':
					$itemName = JText::_('OSM_PAYMENT_FOR_RENEW_SUBSCRIPTION');
					$itemName = str_replace('[PLAN_TITLE]', $rowPlan->title, $itemName);
					break;
				case 'upgrade':
					$itemName = JText::_('OSM_PAYMENT_FOR_UPGRADE_SUBSCRIPTION');
					$itemName = str_replace('[PLAN_TITLE]', $rowPlan->title, $itemName);
					//Get from Plan Title
					$query->clear();
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
			$paymentMethod     = $data['payment_method'];
			require_once JPATH_ROOT . '/components/com_osmembership/plugins/' . $paymentMethod . '.php';

			$query->clear()
				->select('params, support_recurring_subscription')
				->from('#__osmembership_plugins')
				->where('name = ' . $db->quote($paymentMethod));
			$db->setQuery($query);
			$plugin           = $db->loadObject();
			$params           = $plugin->params;
			$supportRecurring = $plugin->support_recurring_subscription;
			$params           = new JRegistry($params);
			$paymentClass     = new $paymentMethod($params);

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

			if ($rowPlan->recurring_subscription && $supportRecurring)
			{
				if ($paymentMethod == 'os_authnet')
				{
					$paymentMethod = 'os_authnet_arb';
					require_once JPATH_ROOT . '/components/com_osmembership/plugins/' . $paymentMethod . '.php';
					$paymentClass = new $paymentMethod($params);
				}

				$paymentClass->processRecurringPayment($row, $data);
			}
			else
			{
				$paymentClass->processPayment($row, $data);
			}
		}
		else
		{
			$row->published = 1;
			if ($rowPlan->price == 0 && isset($config->free_plans_subscription_status) && $config->free_plans_subscription_status === '0')
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
				$dispatcher = JEventDispatcher::getInstance();
				$dispatcher->trigger('onMembershipActive', array($row));
			}

			OSMembershipHelper::sendEmails($row, $config);
			JFactory::getApplication()->redirect(JRoute::_('index.php?option=com_osmembership&view=complete&Itemid=' . $input->getInt('Itemid', 0), false));
		}
	}

	/**
	 * Method to cancel a recurring subscription
	 *
	 * @param $row
	 *
	 * @return bool
	 */
	public function cancelSubscription($row)
	{
		

		$db            = JFactory::getDbo();
		$query         = $db->getQuery(true);
		$paymentMethod = $row->payment_method;
		$query->select('params')
			->from('#__osmembership_plugins')
			->where('name = ' . $db->quote($paymentMethod));
		$db->setQuery($query);
		$params = new JRegistry($db->loadResult());
		if ($paymentMethod == 'os_authnet')
		{
			$paymentMethod = 'os_authnet_arb';
		}
		require_once JPATH_ROOT . '/components/com_osmembership/plugins/' . $paymentMethod . '.php';
		$paymentClass = new $paymentMethod($params);
		$ret          = $paymentClass->cancelSubscription($row);
		if ($ret)
		{
			$query->clear();
			$query->update('#__osmembership_subscribers')
				->set('recurring_subscription_cancelled = 1')
				->where('id = ' . $row->id);
			$db->setQuery($query);
			$db->execute();
			$config = OSMembershipHelper::getConfig();
			OSMembershipHelper::sendSubscriptionCancelEmail($row, $config);
		}

		return $ret;
	}

	/**
	 * Verify payment
	 */
	public function paymentConfirm($paymentMethod)
	{
		$method = os_payments::getPaymentMethod($paymentMethod);
		if ($method)
		{
			$method->verifyPayment();
		}
	}

	/**
	 * Verify recurring payment
	 *
	 * @param $paymentMethod
	 */
	public function recurringPaymentConfirm($paymentMethod)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('params')
			->from('#__osmembership_plugins')
			->where('name=' . $db->quote($paymentMethod));
		$db->setQuery($query);
		$params = new JRegistry($db->loadResult());

		if ($paymentMethod == 'os_authnet')
		{
			$paymentMethod = 'os_authnet_arb';
		}

		require_once JPATH_ROOT . '/components/com_osmembership/plugins/' . $paymentMethod . '.php';

		$method = new $paymentMethod($params);

		if ($method)
		{
			$method->verifyRecurringPayment();
		}
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
		$data        = $input->post->getData();
		$db          = $this->getDbo();
		$query       = $db->getQuery(true);
		$config      = OSMembershipHelper::getConfig();
		$rowFields   = OSMembershipHelper::getProfileFields((int) $data['plan_id'], true, null, $input->getCmd('act'));
		$userId      = JFactory::getUser()->id;
		$filterInput = JFilterInput::getInstance();
		$errors      = array();

		// Validate username
		if ($config->registration_integration && !$userId)
		{
			// Validate username
			if (empty($data['username']))
			{
				$errors[] = JText::sprintf('OSM_FIELD_NAME_IS_REQUIRED', JText::_('OSM_USERNAME'));
			}
			$username = $data['username'];
			if ($filterInput->clean($username, 'TRIM') == '')
			{
				$errors[] = JText::_('JLIB_DATABASE_ERROR_PLEASE_ENTER_A_USER_NAME');
			}

			if (preg_match('#[<>"\'%;()&\\\\]|\\.\\./#', $username) || strlen(utf8_decode($username)) < 2
				|| $filterInput->clean($username, 'TRIM') !== $username
			)
			{
				$errors[] = JText::sprintf('JLIB_DATABASE_ERROR_VALID_AZ09', 2);
			}

			$query->select('COUNT(*)')
				->from('#__users')
				->where('username="' . $username . '"');
			$db->setQuery($query);
			$total = $db->loadResult();

			if ($total)
			{
				$errors[] = JText::_('OSM_INVALID_USERNAME');
			}
		}

		// Validate avatar
		$avatar = $input->files->get('profile_avatar');
		if ($avatar['name'])
		{
			$fileExt        = JString::strtoupper(JFile::getExt($avatar['name']));
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

		// Validate email
		if (empty($data['email']))
		{
			$errors[] = JText::sprintf('OSM_FIELD_NAME_IS_REQUIRED', JText::_('Email'));
		}

		$email = $data['email'];

		if (($filterInput->clean($email, 'TRIM') == "") || !JMailHelper::isEmailAddress($email))
		{
			$errors[] = JText::_('JLIB_DATABASE_ERROR_VALID_MAIL');
		}

		// Check and make sure the email is not used by someone else before
		if ($config->registration_integration && !$userId)
		{
			$query->clear()
				->select('COUNT(*)')
				->from('#__users')
				->where('email = ' . $db->quote($email));
			$db->setQuery($query);
			$total = $db->loadResult();
			if ($total)
			{
				$errors[] = JText::_('OSM_INVALID_EMAIL');
			}
		}

		// Validate required fields
		$form = new MPFForm($rowFields);
		$form->setData($data)->bindData();
		$form->buildFieldsDependency(false);

		$fields = $form->getFields();
		foreach ($fields as $field)
		{
			if ($field->type != 'File')
			{
				continue;
			}

			if (!$field->visible)
			{
				continue;
			}

			if ($field->required && empty($data[$field->name]))
			{
				$errors[] = JText::sprintf('OSM_FIELD_NAME_IS_REQUIRED', $field->title);
			}
		}

		return $errors;
	}
}
