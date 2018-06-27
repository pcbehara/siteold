<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
// no direct access
defined('_JEXEC') or die;

class OSMembershipHelper
{
	/**
	 * Get configuration data and store in config object
	 *
	 * @return object
	 */
	public static function getConfig()
	{
		static $config;
		if (!$config)
		{
			$db     = JFactory::getDbo();
			$query  = $db->getQuery(true);
			$config = new stdClass();
			$query->select('*')
				->from('#__osmembership_configs');
			$db->setQuery($query);
			$rows = $db->loadObjectList();
			for ($i = 0, $n = count($rows); $i < $n; $i++)
			{
				$row          = $rows[$i];
				$key          = $row->config_key;
				$value        = stripslashes($row->config_value);
				$config->$key = $value;
			}
		}

		return $config;
	}

	/**
	 * Get specify config value
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public static function getConfigValue($key, $default = null)
	{
		$config = static::getConfig();

		if (isset($config->{$key}))
		{
			return $config->{$key};
		}

		return $default;
	}

	/**
	 * Apply some fixes for request data
	 *
	 * @return void
	 */
	public static function prepareRequestData()
	{
		//Remove cookie vars from request data
		$cookieVars = array_keys($_COOKIE);
		if (count($cookieVars))
		{
			foreach ($cookieVars as $key)
			{
				if (!isset($_POST[$key]) && !isset($_GET[$key]))
				{
					unset($_REQUEST[$key]);
				}
			}
		}
		if (isset($_REQUEST['start']) && !isset($_REQUEST['limitstart']))
		{
			$_REQUEST['limitstart'] = $_REQUEST['start'];
		}
		if (!isset($_REQUEST['limitstart']))
		{
			$_REQUEST['limitstart'] = 0;
		}
	}

	/**
	 * Get page params of the given view
	 *
	 * @param $active
	 * @param $views
	 *
	 * @return JRegistry
	 */
	public static function getViewParams($active, $views)
	{
		if ($active && isset($active->query['view']) && in_array($active->query['view'], $views))
		{
			return $active->params;
		}

		return new JRegistry();
	}

	/**
	 * Get sef of current language
	 *
	 * @return mixed
	 */
	public static function addLangLinkForAjax()
	{
		if (JLanguageMultilang::isEnabled())
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);
			$tag   = JFactory::getLanguage()->getTag();
			$query->select('`sef`')
				->from('#__languages')
				->where('published = 1')
				->where('lang_code=' . $db->quote($tag));
			$db->setQuery($query, 0, 1);
			$langLink = '&lang=' . $db->loadResult();
		}
		else
		{
			$langLink = '';
		}

		JFactory::getDocument()->addScriptDeclaration(
			'var langLinkForAjax="' . $langLink . '";'
		);
	}

	/**
	 * Check to see if the given user only has unique subscription plan
	 *
	 * @param $userId
	 *
	 * @return bool
	 */
	public static function isUniquePlan($userId)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('DISTINCT plan_id')
			->from('#__osmembership_subscribers')
			->where('user_id = ' . $userId)
			->where('published <= 2');
		$db->setQuery($query);
		$planIds = $db->loadColumn();

		if (count($planIds) == 1)
		{
			return true;
		}

		return false;
	}

	/**
	 * Helper method to check to see where the subscription can be cancelled
	 *
	 * @param $row
	 *
	 * @return bool
	 */
	public static function canCancelSubscription($row)
	{
		$userId = JFactory::getUser()->id;
		if ($row && $row->user_id == $userId && $userId && !$row->recurring_subscription_cancelled)
		{
			return true;
		}

		return false;
	}

	/**
	 * Check if transaction ID processed before
	 *
	 * @param $transactionId
	 *
	 * @return bool
	 */

	public static function isTransactionProcessed($transactionId)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('COUNT(*)')
			->from('#__osmembership_subscribers')
			->where('transaction_id = ' . $db->quote($transactionId));
		$db->setQuery($query);
		$total = (int) $db->loadResult();
		if ($total > 0)
		{
			return true;
		}

		return false;
	}

	/**
	 * Helper function to extend subscription of a user when a recurring payment happens
	 *
	 * @param $id
	 *
	 * @return bool
	 */
	public static function extendRecurringSubscription($id, $transactionId = null)
	{
		require_once JPATH_ADMINISTRATOR . '/components/com_osmembership/table/osmembership.php';
		$row = JTable::getInstance('OsMembership', 'Subscriber');
		$row->load($id);
		if (!$row->id)
		{
			return false;
		}
		$config = OSMembershipHelper::getConfig();
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);
		$query->select('*')
			->from('#__osmembership_plans')
			->where('id = ' . (int) $row->plan_id);
		$db->setQuery($query);
		$rowPlan           = $db->loadObject();
		$row->payment_made = $row->payment_made + 1;
		$row->store();

		$process = false;

		if (($rowPlan->trial_duration && $rowPlan->trial_amount == 0) || ($row->payment_made > 1) || ($row->payment_method == 'os_stripe'))
		{
			$process = true;
		}

		if ($process)
		{
			$row->id             = 0;
			$row->created_date   = JFactory::getDate()->toSql();
			$row->invoice_number = 0;
			$maxDate             = null;
			if ($row->user_id > 0)
			{
				$query->clear();
				$query->select('MAX(to_date)')
					->from('#__osmembership_subscribers')
					->where('published = 1')
					->where('user_id = ' . $row->user_id)
					->where('plan_id =' . $row->plan_id);
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
			$row->to_date = $date->add(new DateInterval('P' . $rowPlan->subscription_length . $rowPlan->subscription_length_unit))->toSql();
			$row->act     = 'renew';

			$params = new JRegistry($row->params);
			if ($params->get('regular_amount') > 0)
			{
				// From version 1.7.0, we don't need to re-calculate the amount, used the amount stored in database already
				$row->amount                 = $params->get('regular_amount');
				$row->discount_amount        = $params->get('regular_discount_amount');
				$row->tax_amount             = $params->get('regular_tax_amount');
				$row->payment_processing_fee = $params->get('regular_payment_processing_fee');
				$row->gross_amount           = $params->get('regular_gross_amount');
			}
			else
			{
				$row->amount = $rowPlan->price;
				//Calculate coupon discount
				if ($row->coupon_id)
				{
					$query->clear();
					$query->select('*')
						->from('#__osmembership_coupons')
						->where('id = ' . (int) $row->coupon_id);
					$db->setQuery($query);
					$coupon = $db->loadObject();
					if ($coupon)
					{
						if ($coupon->coupon_type == 0)
						{
							$row->discount_amount = $row->amount * $coupon->discount / 100;
						}
						else
						{
							$row->discount_amount = min($coupon->discount, $row->amount);
						}
					}
				}
				else
				{
					$row->discount_amount = 0;
				}

				// Calculate tax rate
				$taxRate = OSMembershipHelper::calculateTaxRate($rowPlan->id);
				if ($taxRate > 0)
				{
					$row->tax_amount = round(($row->amount - $row->discount_amount) * $taxRate / 100, 2);
				}
				else
				{
					$row->tax_amount = 0;
				}
				$row->gross_amount = $row->amount - $row->discount_amount + $row->tax_amount;
			}
			$row->published       = 1;
			$row->is_profile      = 0;
			$row->invoice_number  = 0;
			$row->subscription_id = '';
			$row->transaction_id  = $transactionId;
			$row->store();

			$sql = "INSERT INTO #__osmembership_field_value(field_id, field_value, subscriber_id)"
				. " SELECT  field_id,field_value, $row->id"
				. " FROM #__osmembership_field_value WHERE subscriber_id=$id";
			$db->setQuery($sql);
			$db->execute();

			JPluginHelper::importPlugin('osmembership');
			$dispatcher = JEventDispatcher::getInstance();
			$dispatcher->trigger('onMembershipActive', array($row));
			OSMembershipHelper::sendEmails($row, $config);
		}
	}

	/**
	 * Get total plans of a category (and it's sub-categories)
	 *
	 * @param $categoryId
	 *
	 * @return int
	 */
	public static function countPlans($categoryId)
	{
		$user  = JFactory::getUser();
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('id, parent_id')
			->from('#__osmembership_categories')
			->where('published = 1')
			->where('`access` IN (' . implode(',', $user->getAuthorisedViewLevels()) . ')');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		$children = array();

		// first pass - collect children
		if (count($rows))
		{
			foreach ($rows as $v)
			{
				$pt   = $v->parent_id;
				$list = @$children[$pt] ? $children[$pt] : array();
				array_push($list, $v->id);
				$children[$pt] = $list;
			}
		}

		$queues        = array($categoryId);
		$allCategories = array($categoryId);

		while (count($queues))
		{
			$id = array_pop($queues);
			if (isset($children[$id]))
			{
				$allCategories = array_merge($allCategories, $children[$id]);
				$queues        = array_merge($queues, $children[$id]);
			}
		}

		$query->clear();
		$query->select('COUNT(*)')
			->from('#__osmembership_plans')
			->where('published = 1')
			->where('`access` IN (' . implode(',', $user->getAuthorisedViewLevels()) . ')')
			->where('category_id IN (' . implode(',', $allCategories) . ')');
		$db->setQuery($query);

		return (int) $db->loadResult();
	}

	/**
	 * Calculate to see the sign up button should be displayed or not
	 *
	 * @param object $row
	 *
	 * @return bool
	 */
	public static function canSubscribe($row)
	{
		$user = JFactory::getUser();
		if ($user->id)
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);
			if (!$row->enable_renewal)
			{
				$query->clear();
				$query->select('COUNT(*)')
					->from('#__osmembership_subscribers')
					->where('(email=' . $db->quote($user->email) . ' OR user_id=' . (int) $user->id . ')')
					->where('plan_id=' . $row->id)
					->where('published != 0');
				$db->setQuery($query);
				$total = (int) $db->loadResult();
				if ($total)
				{
					return false;
				}
			}
			$numberDaysBeforeRenewal = (int) self::getConfigValue('number_days_before_renewal');
			if ($numberDaysBeforeRenewal)
			{
				//Get max date
				$query->clear();
				$query->select('MAX(to_date)')
					->from('#__osmembership_subscribers')
					->where('user_id=' . (int) $user->id . ' AND plan_id=' . $row->id . ' AND (published=1 OR (published = 0 AND payment_method LIKE "os_offline%"))');
				$db->setQuery($query);
				$maxDate = $db->loadResult();
				if ($maxDate)
				{
					$expiredDate = JFactory::getDate($maxDate);
					$todayDate   = JFactory::getDate();
					$diff        = $expiredDate->diff($todayDate);
					$numberDays  = $diff->days;
					if ($numberDays > $numberDaysBeforeRenewal)
					{
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Get manage group members permission
	 *
	 * @param array $addNewMemberPlanIds
	 *
	 * @return int
	 */
	public static function getManageGroupMemberPermission(&$addNewMemberPlanIds = array())
	{
		if (!JPluginHelper::isEnabled('osmembership', 'groupmembership'))
		{
			return 0;
		}

		$userId = JFactory::getUser()->id;
		if (!$userId)
		{
			return 0;
		}

		JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_osmembership/table');
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		// Check if this user is a group members
		$query->select('COUNT(*)')
			->from('#__osmembership_subscribers')
			->where('user_id = ' . $userId)
			->where('group_admin_id > 0');
		$db->setQuery($query);
		$total = $db->loadResult();
		if ($total)
		{
			return 0;
		}

		$rowPlan       = JTable::getInstance('Osmembership', 'Plan');
		$planIds       = self::getActiveMembershipPlans($userId);
		$managePlanIds = array();

		for ($i = 1, $n = count($planIds); $i < $n; $i++)
		{
			$planId = $planIds[$i];
			$rowPlan->load($planId);
			$numberGroupMembers = $rowPlan->number_group_members;
			if ($numberGroupMembers > 0)
			{
				$managePlanIds[] = $planId;
				$query->clear();
				$query->select('COUNT(*)')
					->from('#__osmembership_subscribers')
					->where('group_admin_id = ' . $userId);
				$db->setQuery($query);
				$totalGroupMembers = (int) $db->loadResult();
				if ($totalGroupMembers < $numberGroupMembers)
				{
					$addNewMemberPlanIds[] = $planId;
				}
			}
		}

		if (count($addNewMemberPlanIds) > 0)
		{
			return 2;
		}
		elseif (count($managePlanIds) > 0)
		{
			return 1;
		}
		else
		{
			return 0;
		}
	}

	/**
	 * Try to fix ProfileID for user if it was lost for some reasons - for example, admin delete
	 *
	 * @param $userId
	 *
	 * @return bool
	 */
	public static function fixProfileId($userId)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('id')
			->from('#__osmembership_subscribers')
			->where('user_id = ' . (int) $userId)
			->order('id DESC');
		$db->setQuery($query);
		$id = (int) $db->loadResult();
		if ($id)
		{
			// Make this record as profile ID
			$query->clear();
			$query->update('#__osmembership_subscribers')
				->set('is_profile = 1')
				->set('profile_id =' . $id)
				->where('id = ' . $id);
			$db->setQuery($query);
			$db->execute();

			// Mark all other records of this user has profile_id = ID of this record
			$query->clear();
			$query->update('#__osmembership_subscribers')
				->set('profile_id = ' . $id)
				->where('user_id = ' . $userId)
				->where('id != ' . $id);
			$db->setQuery($query);
			$db->execute();

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * This function is used to check to see whether we need to update the database to support multilingual or not
	 *
	 * @return boolean
	 */
	public static function isSyncronized()
	{
		$db             = JFactory::getDbo();
		$fields         = array_keys($db->getTableColumns('#__osmembership_plans'));
		$extraLanguages = self::getLanguages();
		if (count($extraLanguages))
		{
			foreach ($extraLanguages as $extraLanguage)
			{
				$prefix = $extraLanguage->sef;
				if (!in_array('alias_' . $prefix, $fields) || !in_array('user_renew_email_subject_' . $prefix, $fields))
				{
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Check to see whether the system need to create invoice for this subscription record or not
	 *
	 *
	 * @param $row
	 *
	 * @return bool
	 */
	public static function needToCreateInvoice($row)
	{
		if ($row->amount > 0 || $row->gross_amount > 0)
		{
			return true;
		}

		return false;
	}

	/**
	 * Convert payment amount to USD currency in case the currency is not supported by the payment gateway
	 *
	 * @param $amount
	 * @param $currency
	 *
	 * @return float
	 */
	public static function convertAmountToUSD($amount, $currency)
	{
		static $rate = null;

		if ($rate === null)
		{
			$http     = JHttpFactory::getHttp();
			$url      = 'http://download.finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=USD' . $currency . '=X';
			$response = $http->get($url);
			if ($response->code == 200)
			{
				$currencyData = explode(',', $response->body);
				$rate         = floatval($currencyData[1]);
			}
		}

		if ($rate > 0)
		{
			$amount = $amount / $rate;
		}

		return round($amount, 2);
	}

	/**
	 * Calculate subscription fees based on input parameter
	 *
	 * @param JTable  $rowPlan the object which contains information about the plan
	 * @param MPFForm $form    The form object which is used to calculate extra fee
	 * @param array   $data    The post data
	 * @param Object  $config
	 * @param string  $paymentMethod
	 *
	 * @return array
	 */
	public static function calculateSubscriptionFee($rowPlan, $form, $data, $config, $paymentMethod = null)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$fees           = array();
		$feeAmount      = $form->calculateFee();
		$couponValid    = 1;
		$vatNumberValid = 1;
		$vatNumber      = '';
		$country        = isset($data['country']) ? $data['country'] : $config->default_country;
		$state          = isset($data['state']) ? $data['state'] : '';
		$countryCode    = self::getCountryCode($country);
		if ($countryCode == 'GR')
		{
			$countryCode = 'EL';
		}

		$paymentFeeAmount  = 0;
		$paymentFeePercent = 0;
		if ($paymentMethod)
		{
			$method            = os_payments::loadPaymentMethod($paymentMethod);
			$params            = new JRegistry($method->params);
			$paymentFeeAmount  = (float) $params->get('payment_fee_amount');
			$paymentFeePercent = (float) $params->get('payment_fee_percent');
		}

		$couponCode = isset($data['coupon_code']) ? $data['coupon_code'] : '';
		if ($couponCode)
		{
			$planId   = $rowPlan->id;
			$nullDate = $db->getNullDate();
			$query->clear()
				->select('*')
				->from('#__osmembership_coupons')
				->where('published=1')
				->where('code="' . $couponCode . '"')
				->where('(valid_from="' . $nullDate . '" OR DATE(valid_from) <= CURDATE())')
				->where('(valid_to="' . $nullDate . '" OR DATE(valid_to) >= CURDATE())')
				->where('(times = 0 OR times > used)')
				->where('(plan_id=0 OR plan_id=' . $planId . ')');
			$db->setQuery($query);
			$coupon = $db->loadObject();
			if (!$coupon)
			{
				$couponValid = 0;
			}
		}

		// Calculate tax
		if (!empty($config->eu_vat_number_field) && isset($data[$config->eu_vat_number_field]))
		{
			$vatNumber = $data[$config->eu_vat_number_field];
			if ($vatNumber)
			{
				// If users doesn't enter the country code into the VAT Number, append the code
				$firstTwoCharacters = substr($vatNumber, 0, 2);
				if (strtoupper($firstTwoCharacters) != $countryCode)
				{
					$vatNumber = $countryCode . $vatNumber;
				}
			}
		}

		if ($vatNumber)
		{
			$valid = OSMembershipHelperEuvat::validateEUVATNumber($vatNumber);
			if ($valid)
			{
				$taxRate = self::calculateTaxRate($rowPlan->id, $country, $state, 1);
			}
			else
			{
				$vatNumberValid = 0;
				$taxRate        = self::calculateTaxRate($rowPlan->id, $country, $state, 0);
			}
		}
		else
		{
			$taxRate = self::calculateTaxRate($rowPlan->id, $country, $state, 0);
		}

		if (!$rowPlan->recurring_subscription)
		{
			$action         = $data['act'];
			$discountAmount = 0;
			$taxAmount      = 0;

			if ($action == 'renew')
			{
				$renewOptionId = (int) $data['renew_option_id'];
				if ($renewOptionId == OSM_DEFAULT_RENEW_OPTION_ID)
				{
					$amount = $rowPlan->price;
				}
				else
				{
					$query->clear()
						->select('price')
						->from('#__osmembership_renewrates')
						->where('id = ' . $renewOptionId);
					$db->setQuery($query);
					$amount = $db->loadResult();
				}
			}
			elseif ($action == 'upgrade')
			{
				$query->clear()
					->select('price')
					->from('#__osmembership_upgraderules')
					->where('id = ' . (int) $data['upgrade_option_id']);
				$db->setQuery($query);
				$amount = $db->loadResult();
			}
			else
			{
				if ($rowPlan->recurring_subscription && $rowPlan->trial_duration)
				{
					$amount = $rowPlan->trial_amount;
				}
				else
				{
					$amount = $rowPlan->price;
				}
			}

			$amount += $feeAmount;

			if (!empty($coupon))
			{
				if ($coupon->coupon_type == 0)
				{
					$discountAmount = $amount * $coupon->discount / 100;
				}
				else
				{
					$discountAmount = min($coupon->discount, $amount);
				}
			}

			if ($taxRate > 0)
			{
				$taxAmount = round(($amount - $discountAmount) * $taxRate / 100, 2);
			}

			$grossAmount = $amount - $discountAmount + $taxAmount;
			$fees['payment_processing_fee'] = 0;
			if ($paymentFeeAmount > 0 || $paymentFeePercent > 0)
			{
				if ($grossAmount > 0)
				{
					$fees['payment_processing_fee'] = round($paymentFeeAmount + $grossAmount * $paymentFeePercent / 100, 2);
					$grossAmount += $fees['payment_processing_fee'];
				}
			}

			$fees['amount']          = $amount;
			$fees['discount_amount'] = $discountAmount;
			$fees['tax_amount']      = $taxAmount;
			$fees['gross_amount']    = $grossAmount;

			if ($fees['gross_amount'] > 0)
			{
				$fees['show_payment_information'] = 1;
			}
			else
			{
				$fees['show_payment_information'] = 0;
			}

			$fees['payment_terms'] = '';
		}
		else
		{
			$regularAmount         = $rowPlan->price + $feeAmount;
			$regularDiscountAmount = 0;
			$regularTaxAmount      = 0;
			$trialDiscountAmount   = 0;
			$trialTaxAmount        = 0;
			$trialAmount           = 0;
			$trialDuration         = 0;
			$trialDurationUnit     = '';

			if ($rowPlan->trial_duration || (!empty($coupon) && $coupon->apply_for == 1))
			{
				// There will be trial duration
				if ($rowPlan->trial_duration)
				{
					$trialAmount = $rowPlan->trial_amount + $feeAmount;

					$trialDuration     = $rowPlan->trial_duration;
					$trialDurationUnit = $rowPlan->trial_duration_unit;
				}
				else
				{
					$trialAmount = $regularAmount;

					$trialDuration     = $rowPlan->subscription_length;
					$trialDurationUnit = $rowPlan->subscription_length_unit;
				}
			}

			if (!empty($coupon))
			{
				if ($coupon->coupon_type == 0)
				{
					$trialDiscountAmount = $trialAmount * $coupon->discount / 100;
					if ($coupon->apply_for == 0)
					{
						$regularDiscountAmount = $regularAmount * $coupon->discount / 100;
					}
				}
				else
				{
					$trialDiscountAmount = min($coupon->discount, $trialAmount);
					if ($coupon->apply_for == 0)
					{
						$regularDiscountAmount = min($coupon->discount, $regularAmount);
					}
				}
			}

			if ($taxRate > 0)
			{
				$trialTaxAmount   = round(($trialAmount - $trialDiscountAmount) * $taxRate / 100, 2);
				$regularTaxAmount = round(($regularAmount - $regularDiscountAmount) * $taxRate / 100, 2);
			}

			$trialGrossAmount   = $trialAmount - $trialDiscountAmount + $trialTaxAmount;
			$regularGrossAmount = $regularAmount - $regularDiscountAmount + $regularTaxAmount;

			if ($paymentFeeAmount > 0 || $paymentFeePercent > 0)
			{
				if ($trialGrossAmount > 0)
				{
					$fees['trial_payment_processing_fee'] = round($paymentFeeAmount + $trialGrossAmount * $paymentFeePercent / 100, 2);
				}
				else
				{
					$fees['trial_payment_processing_fee'] = 0;
				}

				if ($regularGrossAmount > 0)
				{
					$fees['regular_payment_processing_fee'] = round($paymentFeeAmount + $regularGrossAmount * $paymentFeePercent / 100, 2);
				}
				else
				{
					$fees['regular_payment_processing_fee'] = 0;
				}

				$trialGrossAmount += $fees['trial_payment_processing_fee'];
				$regularGrossAmount += $fees['regular_payment_processing_fee'];
			}
			else
			{
				$fees['trial_payment_processing_fee']   = 0;
				$fees['regular_payment_processing_fee'] = 0;
			}

			$fees['trial_amount']            = $trialAmount;
			$fees['trial_discount_amount']   = $trialDiscountAmount;
			$fees['trial_tax_amount']        = $trialTaxAmount;
			$fees['trial_gross_amount']      = $trialGrossAmount;
			$fees['regular_amount']          = $regularAmount;
			$fees['regular_discount_amount'] = $regularDiscountAmount;
			$fees['regular_tax_amount']      = $regularTaxAmount;
			$fees['regular_gross_amount']    = $regularGrossAmount;
			$fees['trial_duration']          = $trialDuration;
			$fees['trial_duration_unit']     = $trialDurationUnit;

			if ($fees['regular_gross_amount'] > 0)
			{
				$fees['show_payment_information'] = 1;
			}
			else
			{
				$fees['show_payment_information'] = 0;
			}

			$replaces = array();
			switch ($rowPlan->subscription_length_unit)
			{
				case 'D':
					$regularDuration = $rowPlan->subscription_length . ' ' . ($rowPlan->subscription_length > 1 ? JText::_('OSM_DAYS') : JText::_('OSM_DAY'));
					break;
				case 'W':
					$regularDuration = $rowPlan->subscription_length . ' ' . ($rowPlan->subscription_length > 1 ? JText::_('OSM_WEEKS') : JText::_('OSM_WEEK'));
					break;
				case 'M':
					$regularDuration = $rowPlan->subscription_length . ' ' . ($rowPlan->subscription_length > 1 ? JText::_('OSM_MONTHS') : JText::_('OSM_MONTH'));
					break;
				case 'Y':
					$regularDuration = $rowPlan->subscription_length . ' ' . ($rowPlan->subscription_length > 1 ? JText::_('OSM_YEARS') : JText::_('OSM_YEAR'));
					break;
				default:
					$regularDuration = $rowPlan->subscription_length . ' ' . ($rowPlan->subscription_length > 1 ? JText::_('OSM_DAYS') : JText::_('OSM_DAY'));
					break;
			}

			$replaces['[REGULAR_AMOUNT]']   = OSMembershipHelper::formatCurrency($fees['regular_gross_amount'], $config);
			$replaces['[REGULAR_DURATION]'] = $regularDuration;
			$replaces['[NUMBER_PAYMENTS]']  = $rowPlan->number_payments;
			if ($trialDuration > 0)
			{
				switch ($trialDurationUnit)
				{
					case 'D':
						$trialDurationText = $trialDuration . ' ' . ($trialDuration > 1 ? JText::_('OSM_DAYS') : JText::_('OSM_DAY'));
						break;
					case 'W':
						$trialDurationText = $trialDuration . ' ' . ($trialDuration > 1 ? JText::_('OSM_WEEKS') : JText::_('OSM_WEEK'));
						break;
					case 'M':
						$trialDurationText = $trialDuration . ' ' . ($trialDuration > 1 ? JText::_('OSM_MONTHS') : JText::_('OSM_MONTH'));
						break;
					case 'Y':
						$trialDurationText = $trialDuration . ' ' . ($trialDuration > 1 ? JText::_('OSM_YEARS') : JText::_('OSM_YEAR'));
						break;
					default:
						$trialDurationText = $trialDuration . ' ' . ($trialDuration > 1 ? JText::_('OSM_DAYS') : JText::_('OSM_DAY'));
						break;
				}

				$replaces['[TRIAL_DURATION]'] = $trialDurationText;

				if ($fees['trial_gross_amount'] > 0)
				{
					$replaces['[TRIAL_AMOUNT]'] = OSMembershipHelper::formatCurrency($fees['trial_gross_amount'], $config);
					if ($rowPlan->number_payments > 0)
					{
						$paymentTerms = JText::_('OSM_TERMS_TRIAL_AMOUNT_NUMBER_PAYMENTS');
					}
					else
					{
						$paymentTerms = JText::_('OSM_TERMS_TRIAL_AMOUNT');
					}
				}
				else
				{
					if ($rowPlan->number_payments > 0)
					{
						$paymentTerms = JText::_('OSM_TERMS_FREE_TRIAL_NUMBER_PAYMENTS');
					}
					else
					{
						$paymentTerms = JText::_('OSM_TERMS_FREE_TRIAL');
					}
				}
			}
			else
			{
				if ($rowPlan->number_payments > 0)
				{
					$paymentTerms = JText::_('OSM_TERMS_EACH_DURATION_NUMBER_PAYMENTS');
				}
				else
				{
					$paymentTerms = JText::_('OSM_TERMS_EACH_DURATION');
				}
			}

			foreach ($replaces as $key => $value)
			{
				$paymentTerms = str_replace($key, $value, $paymentTerms);
			}

			$fees['payment_terms'] = $paymentTerms;
		}

		$fees['coupon_valid']    = $couponValid;
		$fees['vatnumber_valid'] = $vatNumberValid;
		$fees['country_code']    = $countryCode;

		if (OSMembershipHelperEuvat::isEUCountry($countryCode))
		{
			$fees['show_vat_number_field'] = 1;
		}
		else
		{
			$fees['show_vat_number_field'] = 0;
		}

		return $fees;
	}

	/**
	 * Helper function to determine tax rate is based on country or not
	 *
	 * @return bool
	 */

	public static function isCountryBaseTax()
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('DISTINCT(country)')
			->from('#__osmembership_taxes')
			->where('published = 1');
		$db->setQuery($query);
		$countries       = $db->loadColumn();
		$numberCountries = count($countries);
		if ($numberCountries > 1)
		{
			return true;
		}
		elseif ($numberCountries == 1 && strlen($countries[0]))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Get list of countries which has tax based on state
	 *
	 * @return string
	 */
	public static function getTaxStateCountries()
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('DISTINCT(country)')
			->from('#__osmembership_taxes')
			->where('`state` != ""')
			->where('published = 1');
		$db->setQuery($query);

		return implode(',', $db->loadColumn());
	}

	/**
	 * Calculate tax rate for the plan
	 *
	 * @param int    $planId
	 * @param string $country
	 * @param string $state
	 * @param int    $vies
	 *
	 * @return int
	 */
	public static function calculateTaxRate($planId, $country = '', $state = '', $vies = 2)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		if (empty($country))
		{
			$country = self::getConfigValue('default_country');
		}
		$query->select('rate')
			->from('#__osmembership_taxes')
			->where('published = 1')
			->where('plan_id = ' . $planId)
			->where('(country = "" OR country = ' . $db->quote($country) . ')');

		if ($state)
		{
			$query->where('(state = "" OR state = ' . $db->quote($state) . ')')
				->order('`state` DESC');
		}
		else
		{
			$query->where('state = ""');
		}

		$query->order('country DESC');

		if ($vies != 2)
		{
			$query->where('vies = ' . (int) $vies);
		}
		$db->setQuery($query);
		$rowRate = $db->loadObject();
		if ($rowRate)
		{
			return $rowRate->rate;
		}
		else
		{
			// Try to find a record with all plans
			$query->clear();
			$query->select('rate')
				->from('#__osmembership_taxes')
				->where('published = 1')
				->where('plan_id = 0')
				->where('(country = "" OR country = ' . $db->quote($country) . ')');

			if ($state)
			{
				$query->where('(state = "" OR state = ' . $db->quote($state) . ')')
					->order('`state` DESC');
			}
			else
			{
				$query->where('state = ""');
			}

			$query->order('country DESC');

			if ($vies != 2)
			{
				$query->where('vies = ' . (int) $vies);
			}
			$db->setQuery($query);
			$rowRate = $db->loadObject();
			if ($rowRate)
			{
				return $rowRate->rate;
			}
		}

		// If no tax rule found, return 0
		return 0;
	}

	/**
	 * Calculate max taxrate for the plan
	 *
	 * @param int    $planId
	 * @param string $country
	 * @param string $state
	 * @param int    $vies
	 *
	 * @return int
	 */
	public static function calculateMaxTaxRate($planId, $country = '', $state = '', $vies = 2)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		if (empty($country))
		{
			$country = self::getConfigValue('default_country');
		}
		$query->select('rate')
			->from('#__osmembership_taxes')
			->where('published = 1')
			->where('plan_id = ' . $planId)
			->where('(country = "" OR country = ' . $db->quote($country) . ')')
			->order('`rate` DESC');

		if ($state)
		{
			$query->where('(state = "" OR state = ' . $db->quote($state) . ')')
				->order('`state` DESC');
		}

		$query->order('country DESC');

		if ($vies != 2)
		{
			$query->where('vies = ' . (int) $vies);
		}
		$db->setQuery($query);
		$rowRate = $db->loadObject();
		if ($rowRate)
		{
			return $rowRate->rate;
		}
		else
		{
			// Try to find a record with all plans
			$query->clear();
			$query->select('rate')
				->from('#__osmembership_taxes')
				->where('published = 1')
				->where('plan_id = 0')
				->where('(country = "" OR country = ' . $db->quote($country) . ')')
				->order('`rate` DESC');

			if ($state)
			{
				$query->where('(state = "" OR state = ' . $db->quote($state) . ')')
					->order('`state` DESC');
			}

			$query->order('country DESC');

			if ($vies != 2)
			{
				$query->where('vies = ' . (int) $vies);
			}
			$db->setQuery($query);
			$rowRate = $db->loadObject();
			if ($rowRate)
			{
				return $rowRate->rate;
			}
		}

		// If no tax rule found, return 0
		return 0;
	}

	/**
	 * Get list of fields used to display on subscription form for a plan
	 *
	 * @param      $planId
	 * @param bool $loadCoreFields
	 * @param null $language
	 *
	 * @return mixed
	 */
	public static function getProfileFields($planId, $loadCoreFields = true, $language = null, $action = null)
	{
		$planId      = (int) $planId;
		$db          = JFactory::getDbo();
		$query       = $db->getQuery(true);
		$fieldSuffix = self::getFieldSuffix($language);
		$query->select('*')
			->from('#__osmembership_fields')
			->where('published = 1')
			->where('`access` IN (' . implode(',', JFactory::getUser()->getAuthorisedViewLevels()) . ')')
			->where('(plan_id=0 OR id IN (SELECT field_id FROM #__osmembership_field_plan WHERE plan_id=' . $planId . '))');

		if ($fieldSuffix)
		{
			OSMembershipHelperDatabase::getMultilingualFields(
				$query,
				array('title', 'description', 'values', 'default_values', 'depend_on_options'),
				$fieldSuffix
			);
		}

		if (!$loadCoreFields)
		{
			$query->where('is_core = 0');
		}

		// Hide the fields which are setup to be hided on membership renewal
		if ($action == 'renew')
		{
			$query->where('hide_on_membership_renewal = 0');
		}

		$query->order('ordering');
		$db->setQuery($query);

		return $db->loadObjectList();
	}

	/**
	 * Get Login redirect url for the subscriber
	 *
	 * @return string
	 */
	public static function getLoginRedirectUrl()
	{
		$redirectUrl = '';
		$activePlans = OSMembershipHelper::getActiveMembershipPlans();
		if (count($activePlans) > 1)
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('login_redirect_menu_id')
				->from('#__osmembership_plans')
				->where('id IN (' . implode(',', $activePlans) . ')')
				->where('login_redirect_menu_id > 0')
				->order('price DESC');
			$db->setQuery($query);
			$loginRedirectMenuId = $db->loadResult();
			if ($loginRedirectMenuId)
			{
				$redirectUrl = 'index.php?Itemid=' . $loginRedirectMenuId;
			}
		}

		return $redirectUrl;
	}

	/**
	 * Get profile data of one user
	 *
	 * @param object $rowProfile
	 * @param array  $rowFields
	 *
	 * @return array
	 */
	public static function getProfileData($rowProfile, $planId, $rowFields)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$data  = array();
		$query->select('a.name, b.field_value')
			->from('#__osmembership_fields AS a')
			->innerJoin('#__osmembership_field_value AS b ON a.id = b.field_id')
			->where('b.subscriber_id = ' . $rowProfile->id);
		$db->setQuery($query);
		$fieldValues = $db->loadObjectList('name');
		for ($i = 0, $n = count($rowFields); $i < $n; $i++)
		{
			$rowField = $rowFields[$i];
			if ($rowField->is_core)
			{
				$data[$rowField->name] = $rowProfile->{$rowField->name};
			}
			else
			{
				if (isset($fieldValues[$rowField->name]))
				{
					$data[$rowField->name] = $fieldValues[$rowField->name]->field_value;
				}
			}
		}

		return $data;
	}

	/**
	 * Synchronize data for hidden fields on membership renewal
	 *
	 * @param $row
	 * @param $data
	 *
	 * @return bool
	 */
	public static function synchronizeHiddenFieldsData($row, &$data)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__osmembership_subscribers')
			->where('profile_id = ' . $row->profile_id)
			->where('plan_id = ' . $row->plan_id)
			->where('id != ' . $row->id)
			->where('(published >= 1 OR payment_method="os_offline")')
			->where('act != "renew"')
			->order('id');
		$db->setQuery($query);
		$rowProfile = $db->loadObject();

		if ($rowProfile)
		{
			// Get the fields which are hided
			$query->clear();
			$query->select('*')
				->from('#__osmembership_fields')
				->where('published = 1')
				->where('hide_on_membership_renewal = 1')
				->where('`access` IN (' . implode(',', JFactory::getUser()->getAuthorisedViewLevels()) . ')')
				->where('(plan_id=0 OR id IN (SELECT field_id FROM #__osmembership_field_plan WHERE plan_id=' . $row->plan_id . '))');
			$db->setQuery($query);
			$hidedFields = $db->loadObjectList();

			$hideFieldsData = OSMembershipHelper::getProfileData($rowProfile, 0, $hidedFields);
			if (count(($hideFieldsData)))
			{
				$data = array_merge($data, $hideFieldsData);
				foreach ($hidedFields as $field)
				{
					$fieldName = $field->name;

					if ($field->is_core && isset($data[$fieldName]))
					{
						$row->{$fieldName} = $rowProfile->{$fieldName};
					}
				}

				$row->store();
			}
		}

		return true;
	}

	public static function syncronizeProfileData($row, $data)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->select('id')
			->from('#__osmembership_subscribers')
			->where('profile_id=' . (int) $row->profile_id)
			->where('id !=' . (int) $row->id);
		$db->setQuery($query);
		$subscriptionIds = $db->loadColumn();
		if (count($subscriptionIds))
		{
			if ($row->user_id && OSMembershipHelper::isUniquePlan($row->user_id))
			{
				$planId = $row->plan_id;
			}
			else
			{
				$planId = 0;
			}

			$rowFields = OSMembershipHelper::getProfileFields($planId, false);
			$form      = new MPFForm($rowFields);
			$form->storeData($row->id, $data);

			$query->clear();
			$query->select('name')
				->from('#__osmembership_fields')
				->where('is_core=1 AND published = 1');
			$db->setQuery($query);
			$coreFields    = $db->loadColumn();
			$coreFieldData = array();

			foreach ($coreFields as $field)
			{
				if (isset($data[$field]))
				{
					$coreFieldData[$field] = $data[$field];
				}
				else
				{
					$coreFieldData[$field] = '';
				}
			}

			foreach ($subscriptionIds as $subscriptionId)
			{
				$rowSubscription = JTable::getInstance('OsMembership', 'Subscriber');
				$rowSubscription->load($subscriptionId);
				$rowSubscription->bind($coreFieldData);
				$rowSubscription->store();
				$form->storeData($subscriptionId, $data);
			}
		}
	}

	/**
	 * Get information about subscription plans of a user
	 *
	 * @param int $profileId
	 *
	 * @return array
	 */
	public static function getSubscriptions($profileId)
	{
		JLoader::register('OSMembershipHelperSubscription', JPATH_ROOT . '/components/com_osmembership/helper/subscription.php');

		return OSMembershipHelperSubscription::getSubscriptions($profileId);
	}

	/**
	 * Get the email messages used for sending emails
	 *
	 * @return stdClass
	 */
	public static function getMessages()
	{
		static $message;
		if (!$message)
		{
			$message = new stdClass();
			$db      = JFactory::getDbo();
			$query   = $db->getQuery(true);
			$query->select('*')->from('#__osmembership_messages');
			$db->setQuery($query);
			$rows = $db->loadObjectList();
			for ($i = 0, $n = count($rows); $i < $n; $i++)
			{
				$row           = $rows[$i];
				$key           = $row->message_key;
				$value         = stripslashes($row->message);
				$message->$key = $value;
			}
		}

		return $message;
	}

	/**
	 * Get field suffix used in sql query
	 *
	 * @return string
	 */
	public static function getFieldSuffix($activeLanguage = null)
	{
		$prefix = '';
		if (JLanguageMultilang::isEnabled())
		{
			if (!$activeLanguage)
			{
				$activeLanguage = JFactory::getLanguage()->getTag();
			}
			if ($activeLanguage != self::getDefaultLanguage())
			{
				$db    = JFactory::getDbo();
				$query = $db->getQuery(true);
				$query->select('`sef`')
					->from('#__languages')
					->where('lang_code = ' . $db->quote($activeLanguage))
					->where('published = 1');
				$db->setQuery($query);
				$sef = $db->loadResult();
				if ($sef)
				{
					$prefix = '_' . $sef;
				}
			}
		}

		return $prefix;
	}

	/**
	 * Function to get all available languages except the default language
	 * @return languages object list
	 */
	public static function getLanguages()
	{
		$db      = JFactory::getDbo();
		$query   = $db->getQuery(true);
		$default = self::getDefaultLanguage();
		$query->select('lang_id, lang_code, title, `sef`')
			->from('#__languages')
			->where('published = 1')
			->where('lang_code != "' . $default . '"')
			->order('ordering');
		$db->setQuery($query);
		$languages = $db->loadObjectList();

		return $languages;
	}

	/**
	 * Get front-end default language
	 * @return string
	 */
	public static function getDefaultLanguage()
	{
		$params = JComponentHelper::getParams('com_languages');

		return $params->get('site', 'en-GB');
	}

	/**
	 * Synchronize Membership Pro database to support multilingual
	 */
	public static function setupMultilingual()
	{
		$languages = self::getLanguages();
		if (count($languages))
		{
			$db                  = JFactory::getDbo();
			$categoryTableFields = array_keys($db->getTableColumns('#__osmembership_categories'));
			$planTableFields     = array_keys($db->getTableColumns('#__osmembership_plans'));
			$fieldTableFields    = array_keys($db->getTableColumns('#__osmembership_fields'));
			foreach ($languages as $language)
			{
				#Process for #__osmembership_categories table
				$prefix    = $language->sef;
				$fieldName = 'alias_' . $prefix;
				if (!in_array($fieldName, $categoryTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_categories` ADD  `$fieldName` VARCHAR( 255 );";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'title_' . $prefix;
				if (!in_array($fieldName, $categoryTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_categories` ADD  `$fieldName` VARCHAR( 255 );";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'description_' . $prefix;
				if (!in_array($fieldName, $categoryTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_categories` ADD  `$fieldName` TEXT NULL;";
					$db->setQuery($sql);
					$db->execute();
				}

				#Process for #__osmembership_plans table
				$fieldName = 'alias_' . $prefix;
				if (!in_array($fieldName, $planTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` VARCHAR( 255 );";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'title_' . $prefix;
				if (!in_array($fieldName, $planTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` VARCHAR( 255 );";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'short_description_' . $prefix;
				if (!in_array($fieldName, $planTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` TEXT NULL;";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'description_' . $prefix;
				if (!in_array($fieldName, $planTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` TEXT NULL;";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'subscription_form_message_' . $prefix;
				if (!in_array($fieldName, $planTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` TEXT NULL;";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'user_email_subject_' . $prefix;
				if (!in_array($fieldName, $planTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` VARCHAR( 255 );";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'user_email_body_' . $prefix;
				if (!in_array($fieldName, $planTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` TEXT NULL;";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'user_email_body_offline_' . $prefix;
				if (!in_array($fieldName, $planTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` TEXT NULL;";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'subscription_approved_email_subject_' . $prefix;
				if (!in_array($fieldName, $planTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` VARCHAR( 255 );";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'subscription_approved_email_body_' . $prefix;
				if (!in_array($fieldName, $planTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` TEXT NULL;";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'thanks_message_' . $prefix;
				if (!in_array($fieldName, $planTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` TEXT NULL;";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'thanks_message_offline_' . $prefix;
				if (!in_array($fieldName, $planTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` TEXT NULL;";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'user_renew_email_subject_' . $prefix;
				if (!in_array($fieldName, $planTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` VARCHAR( 255 );";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'user_renew_email_body_' . $prefix;
				if (!in_array($fieldName, $planTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` TEXT NULL;";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'title_' . $prefix;
				if (!in_array($fieldName, $fieldTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_fields` ADD  `$fieldName` VARCHAR( 255 );";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'description_' . $prefix;
				if (!in_array($fieldName, $fieldTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_fields` ADD  `$fieldName` TEXT NULL;";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'values_' . $prefix;
				if (!in_array($fieldName, $fieldTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_fields` ADD  `$fieldName` TEXT NULL;";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'default_values_' . $prefix;
				if (!in_array($fieldName, $fieldTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_fields` ADD  `$fieldName` TEXT NULL;";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'fee_values_' . $prefix;
				if (!in_array($fieldName, $fieldTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_fields` ADD  `$fieldName` TEXT NULL;";
					$db->setQuery($sql);
					$db->execute();
				}

				$fieldName = 'depend_on_options_' . $prefix;
				if (!in_array($fieldName, $fieldTableFields))
				{
					$sql = "ALTER TABLE  `#__osmembership_fields` ADD  `$fieldName` TEXT NULL;";
					$db->setQuery($sql);
					$db->execute();
				}
			}
		}
	}

	/**
	 * Load jquery library
	 */
	public static function loadJQuery()
	{
		JHtml::_('jquery.framework');
	}

	/**
	 * Load bootstrap lib
	 */
	public static function loadBootstrap($loadJs = true)
	{
		$config = self::getConfig();
		if ($loadJs)
		{
			JHtml::_('bootstrap.framework');
		}
		if (JFactory::getApplication()->isAdmin() || $config->load_twitter_bootstrap_in_frontend !== '0')
		{
			JHtml::_('bootstrap.loadCss');
		}
	}

	/**
	 * Get Itemid of OS Membership Componnent
	 *
	 * @return int
	 */
	public static function getItemid()
	{
		$app   = JFactory::getApplication();
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$user  = JFactory::getUser();
		$query->select('id')
			->from('#__menu AS a')
			->where('a.link LIKE "%index.php?option=com_osmembership%"')
			->where('a.published=1')
			->where('a.access IN (' . implode(',', $user->getAuthorisedViewLevels()) . ')');
		if ($app->isSite() && $app->getLanguageFilter())
		{
			$query->where('a.language IN (' . $db->quote(JFactory::getLanguage()->getTag()) . ',' . $db->quote('*') . ')');
		}
		$query->order('a.access');
		$db->setQuery($query);
		$itemId = $db->loadResult();
		if (!$itemId)
		{
			$Itemid = $app->input->getInt('Itemid', 0);
			if ($Itemid == 1)
			{
				$itemId = 999999;
			}
			else
			{
				$itemId = $Itemid;
			}
		}

		return $itemId;
	}

	/**
	 * This function is used to find the link to possible views in the component
	 *
	 * @param array $views
	 *
	 * @return string|NULL
	 */
	public static function getViewUrl($views = array())
	{
		$app       = JFactory::getApplication();
		$menus     = $app->getMenu('site');
		$component = JComponentHelper::getComponent('com_osmembership');
		$items     = $menus->getItems('component_id', $component->id);
		foreach ($views as $view)
		{
			$viewUrl = 'index.php?option=com_osmembership&view=' . $view;
			foreach ($items as $item)
			{
				if (strpos($item->link, $viewUrl) !== false)
				{
					if (strpos($item->link, 'Itemid=') === false)
					{
						return JRoute::_($item->link . '&Itemid=' . $item->id);
					}
					else
					{
						return JRoute::_($item->link);
					}
				}
			}
		}

		return;
	}

	/**
	 * Get country code
	 *
	 * @param string $countryName
	 *
	 * @return string
	 */
	public static function getCountryCode($countryName)
	{
		$db  = JFactory::getDbo();
		$sql = 'SELECT country_2_code FROM #__osmembership_countries WHERE LOWER(name)="' . JString::strtolower($countryName) . '"';
		$db->setQuery($sql);
		$countryCode = $db->loadResult();
		if (!$countryCode)
			$countryCode = 'US';

		return $countryCode;
	}

	/***
	 * Get state full name
	 *
	 * @param $country
	 * @param $stateCode
	 *
	 * @return string
	 */
	public static function getStateName($country, $stateCode)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		if (!$country)
		{
			$config  = self::getConfig();
			$country = $config->default_country;
		}

		$query->select('a.state_name')
			->from('#__osmembership_states AS a')
			->innerJoin('#__osmembership_countries AS b ON a.country_id = b.id')
			->where('b.name = ' . $db->quote($country))
			->where('a.state_2_code = ' . $db->quote($stateCode));

		$db->setQuery($query);
		$state = $db->loadResult();

		return $state ? $state : $stateCode;
	}

	/**
	 * Load language from main component
	 */
	public static function loadLanguage()
	{
		static $loaded;
		if (!$loaded)
		{
			$lang = JFactory::getLanguage();
			$tag  = $lang->getTag();
			if (!$tag)
			{
				$tag = 'en-GB';
			}
			$lang->load('com_osmembership', JPATH_ROOT, $tag);
			$loaded = true;
		}
	}

	/**
	 * Display copy right information
	 */
	public static function displayCopyRight()
	{
		JLoader::import('leehelper', JPATH_SITE . '/chocolatlib');
		$leehelper = new Leehelper;
		$version_t = $leehelper->getLastestVerison(); 

		echo '<div class="copyright" style="text-align:center;margin-top: 5px;"><a href="http://joomdonation.com/joomla-extensions/membership-pro-joomla-membership-subscription.html" target="_blank"><strong>Membership Pro</strong></a> version '.$version_t.' , Copyright (C) 2012-' . date('Y') . ' <a href="http://joomdonation.com" target="_blank"><strong>Ossolution Team</strong></a></div>';
	}

	public static function validateEngine()
	{
		$config     = self::getConfig();
		$dateFormat = $config->date_field_format ? $config->date_field_format : '%Y-%m-%d';
		$dateFormat = str_replace('%', '', $dateFormat);
		$dateNow    = JHtml::_('date', JFactory::getDate(), $dateFormat);
		//validate[required,custom[integer],min[-5]] text-input
		$validClass = array(
			"validate[required]",
			"validate[required,custom[integer]]",
			"validate[required,custom[number]]",
			"validate[required,custom[email]]",
			"validate[required,custom[url]]",
			"validate[required,custom[phone]]",
			"validate[custom[date],past[$dateNow]]",
			"validate[required,custom[ipv4]]",
			"validate[required,minSize[6]]",
			"validate[required,maxSize[12]]",
			"validate[required,custom[integer],min[-5]]",
			"validate[required,custom[integer],max[50]]",
		);

		return json_encode($validClass);
	}

	/**
	 * Get exclude group ids of group members
	 *
	 * @return array
	 */
	public static function getGroupMemberExcludeGroupIds()
	{
		$plugin          = JPluginHelper::getPlugin('osmembership', 'groupmembership');
		$params          = new JRegistry($plugin->params);
		$excludeGroupIds = $params->get('exclude_group_ids', '7,8');
		$excludeGroupIds = explode(',', $excludeGroupIds);
		JArrayHelper::toInteger($excludeGroupIds);

		return $excludeGroupIds;
	}

	/**
	 * Get active membership plans
	 */
	public static function getActiveMembershipPlans($userId = 0, $excludes = array())
	{
		JLoader::register('OSMembershipHelperSubscription', JPATH_ROOT . '/components/com_osmembership/helper/subscription.php');

		return OSMembershipHelperSubscription::getActiveMembershipPlans($userId, $excludes);
	}

	/**
	 * Get total subscriptions based on status
	 *
	 * @param int $planId
	 * @param int $status
	 *
	 * @return int
	 */
	public static function countSubscribers($planId = 0, $status = -1)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('COUNT(*)')
			->from('#__osmembership_subscribers');

		if ($planId)
		{
			$query->where('plan_id = ' . $planId);
		}

		if ($status != -1)
		{
			$query->where('published = ' . $status);
		}

		$db->setQuery($query);

		return (int) $db->loadResult();
	}

	/**
	 * Check to see whether the current user can renew his membership using the given option
	 *
	 * @param int $renewOptionId
	 *
	 * @return boolean
	 */
	public static function canRenewMembership($renewOptionId, $fromSubscriptionId)
	{
		return true;
	}

	/**
	 * Check to see whether the current user can upgrade his membership using the upgraded option
	 *
	 * @param int $upgradeOptionId
	 *
	 * @return boolean
	 */
	public static function canUpgradeMembership($upgradeOptionId, $fromSubscriptionId)
	{
		return true;
	}

	/**
	 * Upgrade a membership
	 *
	 * @param OSMembershipTableSubscriber $row
	 */
	public static function processUpgradeMembership($row)
	{
		JLoader::register('OSMembershipHelperSubscription', JPATH_ROOT . '/components/com_osmembership/helper/subscription.php');

		OSMembershipHelperSubscription::processUpgradeMembership($row);
	}

	/**
	 * Get next membership id for this subscriber
	 */
	public static function getMembershipId()
	{
		$config = OSMembershipHelper::getConfig();
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);
		$query->select('MAX(membership_id)')
			->from('#__osmembership_subscribers');

		if ($config->reset_membership_id)
		{
			$currentYear = date('Y');
			$query->where('YEAR(created_date) = ' . $currentYear)
				->where('is_profile = 1');
		}
		$db->setQuery($query);

		$membershipId = (int) $db->loadResult();
		$membershipId++;

		return max($membershipId, (int) $config->membership_id_start_number);
	}

	/**
	 * Get the invoice number for this subscription record
	 */
	public static function getInvoiceNumber($row)
	{
		$config = self::getConfig();
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);
		$query->select('MAX(invoice_number)')
			->from('#__osmembership_subscribers');
		if ($config->reset_invoice_number)
		{
			$currentYear = date('Y');
			$query->where('invoice_year = ' . $currentYear);
			$row->invoice_year = $currentYear;
		}
		$db->setQuery($query);
		$invoiceNumber = (int) $db->loadResult();
		if (!$invoiceNumber)
		{
			$invoiceNumber = (int) $config->invoice_start_number;
		}
		else
		{
			$invoiceNumber++;
		}

		return $invoiceNumber;
	}

	/**
	 * Format invoice number
	 *
	 * @param $row
	 * @param $config
	 *
	 * @return mixed|string
	 */
	public static function formatInvoiceNumber($row, $config)
	{
		$invoicePrefix = str_replace('[YEAR]', $row->invoice_year, $config->invoice_prefix);

		return $invoicePrefix . str_pad($row->invoice_number, $config->invoice_number_length ? $config->invoice_number_length : 4, '0', STR_PAD_LEFT);
	}

	/**
	 * Format Membership Id
	 *
	 * @param $row
	 * @param $config
	 *
	 * @return string
	 */
	public static function formatMembershipId($row, $config)
	{
		if (!$row->is_profile)
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('YEAR(created_date)')
				->from('#__osmembership_subscribers')
				->where('id = ' . (int) $row->profile_id);
			$db->setQuery($query);
			$year = (int) $db->loadResult();
		}
		else
		{
			$year = JHtml::_('date', $row->created_date, 'Y');
		}

		$idPrefix = str_replace('[YEAR]', $year, $config->membership_id_prefix);

		return $idPrefix . $row->membership_id;
	}

	/**
	 * Generate invoice PDF
	 *
	 * @param object $row
	 */
	public static function generateInvoicePDF($row)
	{
		self::loadLanguage();

		require_once JPATH_ROOT . '/components/com_osmembership/tcpdf/tcpdf.php';
		require_once JPATH_ROOT . '/components/com_osmembership/tcpdf/config/lang/eng.php';

		$db       = JFactory::getDbo();
		$query    = $db->getQuery(true);
		$config   = self::getConfig();
		$sitename = JFactory::getConfig()->get("sitename");

		$query->select('*')
			->from('#__osmembership_plans')
			->where('id = ' . $row->plan_id);
		$db->setQuery($query);
		$rowPlan = $db->loadObject();

		$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
		$pdf->SetCreator(PDF_CREATOR);
		$pdf->SetAuthor($sitename);
		$pdf->SetTitle('Invoice');
		$pdf->SetSubject('Invoice');
		$pdf->SetKeywords('Invoice');
		$pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		$pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->SetMargins(PDF_MARGIN_LEFT, 0, PDF_MARGIN_RIGHT);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		//set auto page breaks
		$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
		//set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

		$font = empty($config->pdf_font) ? 'times' : $config->pdf_font;

		$pdf->SetFont($font, '', 8);
		$pdf->AddPage();
		$invoiceOutput = $config->invoice_format;

		$replaces                      = array();
		$replaces['name']              = $row->first_name . ' ' . $row->last_name;
		$replaces['email']             = $row->email;
		$replaces['user_id']           = $row->user_id;
		$replaces['organization']      = $row->organization;
		$replaces['address']           = $row->address;
		$replaces['address2']          = $row->address2;
		$replaces['city']              = $row->city;
		$replaces['state']             = self::getStateName($row->country, $row->state);
		$replaces['zip']               = $row->zip;
		$replaces['country']           = $row->country;
		$replaces['country_code']      = self::getCountryCode($row->country);
		$replaces['phone']             = $row->phone;
		$replaces['fax']               = $row->fax;
		$replaces['invoice_number']    = self::formatInvoiceNumber($row, $config);
		$replaces['invoice_date']      = JHtml::_('date', $row->created_date, $config->date_format);
		$replaces['from_date']         = JHtml::_('date', $row->from_date, $config->date_format);
		$replaces['to_date']           = JHtml::_('date', $row->to_date, $config->date_format);
		$replaces['created_date']      = JHtml::_('date', $row->created_date, $config->date_format);
		$replaces['date']              = JHtml::_('date', 'Now', $config->date_format);
		$replaces['plan_title']        = $rowPlan->title;
		$replaces['short_description'] = $rowPlan->short_description;
		$replaces['description']       = $rowPlan->description;
		$replaces['transaction_id']    = $row->transaction_id;
		$replaces['membership_id']     = self::formatMembershipId($row, $config);
		$replaces['end_date']          = $replaces['to_date'];
		$replaces['payment_method']    = '';
		if ($row->payment_method)
		{
			$method = os_payments::loadPaymentMethod($row->payment_method);
			if ($method)
			{
				$replaces['payment_method'] = JText::_($method->title);
			}
		}

		$query->clear();
		// Support for name of custom field in tags
		$query->select('field_id, field_value')
			->from('#__osmembership_field_value')
			->where('subscriber_id = ' . $row->id);
		$db->setQuery($query);
		$rowValues = $db->loadObjectList('field_id');

		$query->clear();
		$query->select('id, name, fieldtype')
			->from('#__osmembership_fields AS a')
			->where('a.published = 1')
			->where('a.is_core = 0');
		$db->setQuery($query);
		$rowFields = $db->loadObjectList();

		for ($i = 0, $n = count($rowFields); $i < $n; $i++)
		{
			$rowField = $rowFields[$i];
			if (isset($rowValues[$rowField->id]))
			{
				$fieldValue = $rowValues[$rowField->id]->field_value;
				if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
				{
					$fieldValue = implode(', ', json_decode($fieldValue));
				}
				if ($fieldValue && $rowField->fieldtype == 'Date')
				{
					try
					{
						$replaces[$rowField->name] = JHtml::_('date', $fieldValue, $config->date_format, null);
					}
					catch (Exception $e)
					{
						$replaces[$rowField->name] = $fieldValue;
					}
				}
				else
				{
					$replaces[$rowField->name] = $fieldValue;
				}
			}
			else
			{
				$replaces[$rowField->name] = '';
			}
		}

		if ($row->published == 0)
		{
			$invoiceStatus = JText::_('OSM_INVOICE_STATUS_PENDING');
		}
		elseif ($row->published == 1)
		{
			$invoiceStatus = JText::_('OSM_INVOICE_STATUS_PAID');
		}
		else
		{
			$invoiceStatus = JText::_('');
		}
		$replaces['INVOICE_STATUS']         = $invoiceStatus;
		$replaces['ITEM_QUANTITY']          = 1;
		$replaces['ITEM_AMOUNT']            = $replaces['ITEM_SUB_TOTAL'] = self::formatCurrency($row->amount, $config);
		$replaces['DISCOUNT_AMOUNT']        = self::formatCurrency($row->discount_amount, $config);
		$replaces['SUB_TOTAL']              = self::formatCurrency($row->amount - $row->discount_amount, $config);
		$replaces['TAX_AMOUNT']             = self::formatCurrency($row->tax_amount, $config);
		$replaces['payment_processing_fee'] = self::formatCurrency($row->payment_processing_fee, $config);
		$replaces['TOTAL_AMOUNT']           = self::formatCurrency($row->gross_amount, $config);

		switch ($row->act)
		{
			case 'renew':
				$itemName = JText::_('OSM_PAYMENT_FOR_RENEW_SUBSCRIPTION');
				$itemName = str_replace('[PLAN_TITLE]', $rowPlan->title, $itemName);
				break;
			case 'upgrade':
				$itemName = JText::_('OSM_PAYMENT_FOR_UPGRADE_SUBSCRIPTION');
				$itemName = str_replace('[PLAN_TITLE]', $rowPlan->title, $itemName);
				$sql      = 'SELECT a.title FROM #__osmembership_plans AS a '
					. 'INNER JOIN #__osmembership_upgraderules AS b '
					. 'ON a.id=b.from_plan_id '
					. 'WHERE b.id=' . $row->upgrade_option_id;
				$db->setQuery($sql);
				$fromPlanTitle = $db->loadResult();
				$itemName      = str_replace('[FROM_PLAN_TITLE]', $fromPlanTitle, $itemName);
				break;
			default:
				$itemName = JText::_('OSM_PAYMENT_FOR_SUBSCRIPTION');
				$itemName = str_replace('[PLAN_TITLE]', $rowPlan->title, $itemName);
				break;
		}
		$replaces['ITEM_NAME'] = $itemName;
		foreach ($replaces as $key => $value)
		{
			$key           = strtoupper($key);
			$invoiceOutput = str_ireplace("[$key]", $value, $invoiceOutput);
		}

		$v = $pdf->writeHTML($invoiceOutput, true, false, false, false, '');
		//Filename
		$filePath = JPATH_ROOT . '/media/com_osmembership/invoices/' . $replaces['invoice_number'] . '.pdf';
		$pdf->Output($filePath, 'F');
	}

	/**
	 * Download invoice of a subscription record
	 *
	 * @param int $id
	 */
	public static function downloadInvoice($id)
	{
		JTable::addIncludePath(JPATH_ROOT . '/administrator/components/com_osmembership/table');
		$config = self::getConfig();
		$row    = JTable::getInstance('osmembership', 'Subscriber');
		$row->load($id);
		$invoiceStorePath = JPATH_ROOT . '/media/com_osmembership/invoices/';
		if ($row)
		{
			if (!$row->invoice_number)
			{
				$row->invoice_number = self::getInvoiceNumber($row);
				$row->store();
			}
			$invoiceNumber = self::formatInvoiceNumber($row, $config);
			self::generateInvoicePDF($row);
			$invoicePath = $invoiceStorePath . $invoiceNumber . '.pdf';
			$fileName    = $invoiceNumber . '.pdf';
			while (@ob_end_clean()) ;
			self::processDownload($invoicePath, $fileName);
		}
	}

	/**
	 * Get the original filename, without the timestamp prefix at the beginning
	 *
	 * @param string $filename
	 *
	 * @return string
	 */
	public static function getOriginalFilename($filename)
	{
		$pos = strpos($filename, '_');
		if ($pos !== false)
		{
			$timeInFilename = (int) substr($filename, 0, $pos);
			if ($timeInFilename > 5000)
			{
				$filename = substr($filename, $pos + 1);
			}
		}

		return $filename;
	}

	/**
	 * Process download a file
	 *
	 * @param string $file : Full path to the file which will be downloaded
	 */
	public static function processDownload($filePath, $filename, $detectFilename = false)
	{
		jimport('joomla.filesystem.file');
		$fsize    = @filesize($filePath);
		$mod_date = date('r', filemtime($filePath));
		$cont_dis = 'attachment';
		if ($detectFilename)
		{
			$filename = self::getOriginalFilename($filename);
		}
		$ext  = JFile::getExt($filename);
		$mime = self::getMimeType($ext);
		// required for IE, otherwise Content-disposition is ignored
		if (ini_get('zlib.output_compression'))
		{
			ini_set('zlib.output_compression', 'Off');
		}
		header("Pragma: public");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Expires: 0");
		header("Content-Transfer-Encoding: binary");
		header(
			'Content-Disposition:' . $cont_dis . ';' . ' filename="' . $filename . '";' . ' modification-date="' . $mod_date . '";' . ' size=' . $fsize .
			';'); //RFC2183
		header("Content-Type: " . $mime); // MIME type
		header("Content-Length: " . $fsize);

		if (!ini_get('safe_mode'))
		{ // set_time_limit doesn't work in safe mode
			@set_time_limit(0);
		}

		self::readfile_chunked($filePath);
	}

	/**
	 * Get mimetype of a file
	 *
	 * @return string
	 */
	public static function getMimeType($ext)
	{
		require_once JPATH_ROOT . "/components/com_osmembership/helper/mime.mapping.php";
		foreach ($mime_extension_map as $key => $value)
		{
			if ($key == $ext)
			{
				return $value;
			}
		}

		return "";
	}

	/**
	 * Read file
	 *
	 * @param string $filename
	 * @param        $retbytes
	 *
	 * @return unknown
	 */
	public static function readfile_chunked($filename, $retbytes = true)
	{
		$chunksize = 1 * (1024 * 1024); // how many bytes per chunk
		$buffer    = '';
		$cnt       = 0;
		$handle    = fopen($filename, 'rb');
		if ($handle === false)
		{
			return false;
		}
		while (!feof($handle))
		{
			$buffer = fread($handle, $chunksize);
			echo $buffer;
			@ob_flush();
			flush();
			if ($retbytes)
			{
				$cnt += strlen($buffer);
			}
		}
		$status = fclose($handle);
		if ($retbytes && $status)
		{
			return $cnt; // return num. bytes delivered like readfile() does.
		}

		return $status;
	}

	/**
	 * Convert all img tags to use absolute URL
	 *
	 * @param string $html_content
	 */
	public static function convertImgTags($html_content)
	{
		$patterns     = array();
		$replacements = array();
		$i            = 0;
		$src_exp      = "/src=\"(.*?)\"/";
		$link_exp     = "[^http:\/\/www\.|^www\.|^https:\/\/|^http:\/\/]";
		$siteURL      = JUri::root();
		preg_match_all($src_exp, $html_content, $out, PREG_SET_ORDER);
		foreach ($out as $val)
		{
			$links = preg_match($link_exp, $val[1], $match, PREG_OFFSET_CAPTURE);
			if ($links == '0')
			{
				$patterns[$i]     = $val[1];
				$patterns[$i]     = "\"$val[1]";
				$replacements[$i] = $siteURL . $val[1];
				$replacements[$i] = "\"$replacements[$i]";
			}
			$i++;
		}
		$mod_html_content = str_replace($patterns, $replacements, $html_content);

		return $mod_html_content;
	}

	/**
	 * Build list of tags which will be used on emails & messages
	 *
	 * @param $row
	 * @param $config
	 *
	 * @return array
	 */
	public static function buildTags($row, $config)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$row->state                         = self::getStateName($row->country, $row->state);
		$replaces                           = array();
		$replaces['user_id']                = $row->first_name;
		$replaces['first_name']             = $row->first_name;
		$replaces['last_name']              = $row->last_name;
		$replaces['organization']           = $row->organization;
		$replaces['address']                = $row->address;
		$replaces['address2']               = $row->address2;
		$replaces['city']                   = $row->city;
		$replaces['state']                  = self::getStateName($row->country, $row->state);
		$replaces['zip']                    = $row->zip;
		$replaces['country']                = $row->country;
		$replaces['phone']                  = $row->phone;
		$replaces['fax']                    = $row->phone;
		$replaces['email']                  = $row->email;
		$replaces['comment']                = $row->comment;
		$replaces['amount']                 = self::formatAmount($row->amount, $config);
		$replaces['discount_amount']        = self::formatAmount($row->discount_amount, $config);
		$replaces['tax_amount']             = self::formatAmount($row->tax_amount, $config);
		$replaces['gross_amount']           = self::formatAmount($row->gross_amount, $config);
		$replaces['payment_processing_fee'] = self::formatAmount($row->payment_processing_fee, $config);
		$replaces['from_date']              = JHtml::_('date', $row->from_date, $config->date_format);
		$replaces['to_date']                = JHtml::_('date', $row->to_date, $config->date_format);
		$replaces['created_date']           = JHtml::_('date', $row->created_date, $config->date_format);
		$replaces['date']                   = JHtml::_('date', 'Now', $config->date_format);
		$replaces['end_date']               = $replaces['to_date'];
		$replaces['payment_method']         = '';
		if ($row->payment_method)
		{
			$method = os_payments::loadPaymentMethod($row->payment_method);
			if ($method)
			{
				$replaces['payment_method'] = JText::_($method->title);
			}
		}

		if ($row->username && $row->user_password)
		{
			$replaces['username'] = $row->username;
			//Password
			$privateKey           = md5(JFactory::getConfig()->get('secret'));
			$key                  = new JCryptKey('simple', $privateKey, $privateKey);
			$crypt                = new JCrypt(new JCryptCipherSimple, $key);
			$replaces['password'] = $crypt->decrypt($row->user_password);
		}
		elseif ($row->username)
		{
			$replaces['username'] = $row->username;
		}
		elseif ($row->user_id)
		{
			$query->select('username')
				->from('#__users')
				->where('id = ' . (int) $row->user_id);
			$db->setQuery($query);
			$replaces['username'] = $db->loadResult();
			$query->clear();
		}
		else
		{
			$replaces['username'] = '';
		}

		$replaces['transaction_id'] = $row->transaction_id;
		$replaces['membership_id']  = self::formatMembershipId($row, $config);
		$replaces['invoice_number'] = self::formatInvoiceNumber($row, $config);
		if ($row->payment_method)
		{
			$method = os_payments::loadPaymentMethod($row->payment_method);
			if ($method)
			{
				$replaces['payment_method'] = $method->title;
			}
			else
			{
				$replaces['payment_method'] = '';
			}
		}

		// Support for name of custom field in tags
		$query->select('field_id, field_value')
			->from('#__osmembership_field_value')
			->where('subscriber_id = ' . $row->id);
		$db->setQuery($query);
		$rowValues = $db->loadObjectList('field_id');

		$query->clear();
		$query->select('id, name, fieldtype')
			->from('#__osmembership_fields AS a')
			->where('a.published = 1')
			->where('a.is_core = 0')
			->where("(a.plan_id = 0 OR a.id IN (SELECT field_id FROM #__osmembership_field_plan WHERE plan_id = $row->plan_id))");
		$db->setQuery($query);
		$rowFields = $db->loadObjectList();

		for ($i = 0, $n = count($rowFields); $i < $n; $i++)
		{
			$rowField = $rowFields[$i];
			if (isset($rowValues[$rowField->id]))
			{
				$fieldValue = $rowValues[$rowField->id]->field_value;
				if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
				{
					$fieldValue = implode(', ', json_decode($fieldValue));
				}
				if ($fieldValue && $rowField->fieldtype == 'Date')
				{
					try
					{
						$replaces[$rowField->name] = JHtml::_('date', $fieldValue, $config->date_format, null);
					}
					catch (Exception $e)
					{
						$replaces[$rowField->name] = $fieldValue;
					}
				}
				else
				{
					$replaces[$rowField->name] = $fieldValue;
				}
			}
			else
			{
				$replaces[$rowField->name] = '';
			}
		}

		return $replaces;
	}

	/**
	 * Send email to super administrator and user
	 *
	 * @param object $row
	 * @param object $config
	 */
	public static function sendEmails($row, $config)
	{
		OSMembershipHelperMail::sendEmails($row, $config);
	}

	/**
	 * Send email to subscriber to inform them that their membership approved (and activated)
	 *
	 * @param object $row
	 */
	public static function sendMembershipApprovedEmail($row)
	{
		OSMembershipHelperMail::sendMembershipApprovedEmail($row);
	}

	/**
	 * Send confirmation email to subscriber and notification email to admin when a recurring subscription cancelled
	 *
	 * @param $row
	 * @param $config
	 */
	public static function sendSubscriptionCancelEmail($row, $config)
	{
		OSMembershipHelperMail::sendSubscriptionCancelEmail($row, $config);
	}

	/**
	 * Send notification emailt o admin when someone update his profile
	 *
	 * @param $row
	 * @param $config
	 */
	public static function sendProfileUpdateEmail($row, $config)
	{
		OSMembershipHelperMail::sendProfileUpdateEmail($row, $config);
	}

	/**
	 * Format currency based on config parametters
	 *
	 * @param Float  $amount
	 * @param Object $config
	 * @param string $currencySymbol
	 *
	 * @return string
	 */
	public static function formatCurrency($amount, $config, $currencySymbol = null)
	{
		$decimals      = isset($config->decimals) ? $config->decimals : 2;
		$dec_point     = isset($config->dec_point) ? $config->dec_point : '.';
		$thousands_sep = isset($config->thousands_sep) ? $config->thousands_sep : ',';
		$symbol        = $currencySymbol ? $currencySymbol : $config->currency_symbol;

		return $config->currency_position ? (number_format($amount, $decimals, $dec_point, $thousands_sep) . $symbol) : ($symbol .
			number_format($amount, $decimals, $dec_point, $thousands_sep));
	}

	public static function formatAmount($amount, $config)
	{
		$decimals      = isset($config->decimals) ? $config->decimals : 2;
		$dec_point     = isset($config->dec_point) ? $config->dec_point : '.';
		$thousands_sep = isset($config->thousands_sep) ? $config->thousands_sep : ',';

		return number_format($amount, $decimals, $dec_point, $thousands_sep);
	}

	/**
	 * Get detail information of the subscription
	 *
	 * @param object $config
	 * @param object $row
	 *
	 * @return string
	 */
	public static function getEmailContent($config, $row, $toAdmin = false)
	{
		$db          = JFactory::getDbo();
		$query       = $db->getQuery(true);
		$fieldSuffix = self::getFieldSuffix($row->language);
		$query->select('title' . $fieldSuffix . ' AS title')
			->select('lifetime_membership')
			->select('currency, currency_symbol')
			->from('#__osmembership_plans')
			->where('id = ' . $row->plan_id);
		$db->setQuery($query);
		$plan = $db->loadObject();

		$data                       = array();
		$data['planTitle']          = $plan->title;
		$data['lifetimeMembership'] = $plan->lifetime_membership;
		$data['config']             = $config;
		$data['row']                = $row;
		$data['toAdmin']            = $toAdmin;

		$data['currencySymbol'] = $plan->currency_symbol ? $plan->currency_symbol : $plan->currency;

		if ($row->payment_method == 'os_creditcard')
		{
			$cardNumber          = JRequest::getVar('x_card_num', '');
			$last4Digits         = substr($cardNumber, strlen($cardNumber) - 4);
			$data['last4Digits'] = $last4Digits;
		}
		if ($row->user_id)
		{
			$query->clear()
				->select('username')
				->from('#__users')
				->where('id = ' . $row->user_id);
			$db->setQuery($query);
			$username         = $db->loadResult();
			$data['username'] = $username;
		}

		if ($row->username && $row->user_password)
		{
			$data['username'] = $row->username;
			//Password
			$privateKey       = md5(JFactory::getConfig()->get('secret'));
			$key              = new JCryptKey('simple', $privateKey, $privateKey);
			$crypt            = new JCrypt(new JCryptCipherSimple, $key);
			$data['password'] = $crypt->decrypt($row->user_password);
		}
		$rowFields = OSMembershipHelper::getProfileFields($row->plan_id, true, $row->language);
		$formData  = OSMembershipHelper::getProfileData($row, $row->plan_id, $rowFields);
		$form      = new MPFForm($rowFields);
		$form->setData($formData)->bindData();
		$form->buildFieldsDependency(false);
		$data['form'] = $form;

		$params = JComponentHelper::getParams('com_users');
		if (!$params->get('sendpassword', 1) && isset($data['password']))
		{
			unset($data['password']);
		}

		return OSMembershipHelperHtml::loadCommonLayout('emailtemplates/tmpl/email.php', $data);
	}

	/**
	 * Get recurring frequency from subscription length
	 *
	 * @param int $subscriptionLength
	 *
	 * @return array
	 */
	public static function getRecurringSettingOfPlan($subscriptionLength)
	{
		if (($subscriptionLength >= 365) && ($subscriptionLength % 365 == 0))
		{
			$frequency = 'Y';
			$length    = $subscriptionLength / 365;
		}
		elseif (($subscriptionLength >= 30) && ($subscriptionLength % 30 == 0))
		{
			$frequency = 'M';
			$length    = $subscriptionLength / 30;
		}
		elseif (($subscriptionLength >= 7) && ($subscriptionLength % 7 == 0))
		{
			$frequency = 'W';
			$length    = $subscriptionLength / 7;
		}
		else
		{
			$frequency = 'D';
			$length    = $subscriptionLength;
		}

		return array($frequency, $length);
	}

	/**
	 * Create an user account based on the entered data
	 *
	 * @param $data
	 *
	 * @return int
	 * @throws Exception
	 */
	public static function saveRegistration($data)
	{
		$config = OSMembershipHelper::getConfig();
		if (!empty($config->use_cb_api))
		{
			return static::userRegistrationCB($data['first_name'], $data['last_name'], $data['email'], $data['username'], $data['password1']);
		}

		//Need to load com_users language file
		$lang = JFactory::getLanguage();
		$tag  = $lang->getTag();
		if (!$tag)
		{
			$tag = 'en-GB';
		}
		$lang->load('com_users', JPATH_ROOT, $tag);
		$userData             = array();
		$userData['username'] = $data['username'];
		$userData['name']     = trim($data['first_name'] . ' ' . $data['last_name']);
		$userData['password'] = $userData['password1'] = $userData['password2'] = $data['password1'];
		$userData['email']    = $userData['email1'] = $userData['email2'] = $data['email'];
		$sendActivationEmail  = OSMembershipHelper::getConfigValue('send_activation_email');
		if ($sendActivationEmail)
		{
			require_once JPATH_ROOT . '/components/com_users/models/registration.php';
			$model = new UsersModelRegistration();
			$model->register($userData);

			// User is successfully saved, we will return user id based on username
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('id')
				->from('#__users')
				->where('username=' . $db->quote($data['username']));

			$db->setQuery($query);

			$userId = (int) $db->loadResult();
			if (!$userId)
			{
				throw new Exception($model->getError());
			}

			return $userId;
		}
		else
		{
			$params         = JComponentHelper::getParams('com_users');
			$userActivation = $params->get('useractivation');
			if (($userActivation == 1) || ($userActivation == 2))
			{
				$userData['activation'] = JApplication::getHash(JUserHelper::genRandomPassword());
				$userData['block']      = 1;
			}
			$userData['groups']   = array();
			$userData['groups'][] = $params->get('new_usertype', 2);
			$user                 = new JUser();
			if (!$user->bind($userData))
			{
				throw new Exception(JText::sprintf('COM_USERS_REGISTRATION_BIND_FAILED', $user->getError()));
			}
			// Store the data.
			if (!$user->save())
			{
				throw new Exception(JText::sprintf('COM_USERS_REGISTRATION_SAVE_FAILED', $user->getError()));
			}

			return $user->get('id');
		}
	}

	/**
	 * Use CB API for saving user account
	 *
	 * @param       $firstName
	 * @param       $lastName
	 * @param       $email
	 * @param       $username
	 * @param       $password
	 *
	 * @return int
	 */
	public static function userRegistrationCB($firstName, $lastName, $email, $username, $password)
	{
		global $_CB_framework, $_PLUGINS, $ueConfig;

		include_once JPATH_ADMINISTRATOR . '/components/com_comprofiler/plugin.foundation.php';
		cbimport('cb.html');
		cbimport('cb.plugins');

		$approval     = $ueConfig['reg_admin_approval'];
		$confirmation = ($ueConfig['reg_confirmation']);
		$user         = new \CB\Database\Table\UserTable();

		$user->set('username', $username);
		$user->set('email', $email);
		$user->set('name', trim($firstName . ' ' . $lastName));
		$user->set('gids', array((int) $_CB_framework->getCfg('new_usertype')));
		$user->set('sendEmail', 0);
		$user->set('registerDate', $_CB_framework->getUTCDate());
		$user->set('password', $user->hashAndSaltPassword($password));
		$user->set('registeripaddr', cbGetIPlist());

		if ($approval == 0)
		{
			$user->set('approved', 1);
		}
		else
		{
			$user->set('approved', 0);
		}

		if ($confirmation == 0)
		{
			$user->set('confirmed', 1);
		}
		else
		{
			$user->set('confirmed', 0);
		}

		if (($user->get('confirmed') == 1) && ($user->get('approved') == 1))
		{
			$user->set('block', 0);
		}
		else
		{
			$user->set('block', 1);
		}

		$_PLUGINS->trigger('onBeforeUserRegistration', array(&$user, &$user));

		if ($user->store())
		{
			if ($user->get('confirmed') == 0)
			{
				$user->store();
			}

			$messagesToUser = activateUser($user, 1, 'UserRegistration');

			$_PLUGINS->trigger('onAfterUserRegistration', array(&$user, &$user, true));

			return $user->get('id');
		}

		return 0;
	}

	/**
	 * Get base URL of the site
	 *
	 * @return mixed|string
	 * @throws Exception
	 */
	public static function getSiteUrl()
	{
		$uri  = JUri::getInstance();
		$base = $uri->toString(array('scheme', 'host', 'port'));
		if (strpos(php_sapi_name(), 'cgi') !== false && !ini_get('cgi.fix_pathinfo') && !empty($_SERVER['REQUEST_URI']))
		{
			$script_name = $_SERVER['PHP_SELF'];
		}
		else
		{
			$script_name = $_SERVER['SCRIPT_NAME'];
		}

		$path = rtrim(dirname($script_name), '/\\');

		if ($path)
		{
			$siteUrl = $base . $path . '/';
		}
		else
		{
			$siteUrl = $base . '/';
		}

		if (JFactory::getApplication()->isAdmin())
		{
			$adminPos = strrpos($siteUrl, 'administrator/');
			$siteUrl  = substr_replace($siteUrl, '', $adminPos, 14);
		}

		$config = self::getConfig();
		if ($config->use_https)
		{
			$siteUrl = str_replace('http://', 'https://', $siteUrl);
		}

		return $siteUrl;
	}

	/**
	 * Try to determine the best match url which users should be redirected to when they access to restricted resource
	 *
	 * @param $planIds
	 *
	 * @return string
	 */
	public static function getRestrictionRedirectUrl($planIds)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		// Get category of the first plan
		$query->select('category_id')
			->from('#__osmembership_plans')
			->where('id = ' . (int) $planIds[0]);
		$db->setQuery($query);
		$categoryId = (int) $db->loadResult();

		$needles = array();
		if (count($planIds) == 1)
		{
			$planId                = $planIds[0];
			$needles['register']   = array($planId);
			$needles['plan']       = array($planId);
			$needles['plans']      = array($categoryId);
			$needles['categories'] = array($categoryId);
		}
		elseif ($categoryId > 0)
		{
			// If the category contains all the plans here, we will find menu item linked to that category
			$query->clear();
			$query->select('id')
				->from('#__osmembership_plans')
				->where('category_id = ' . $categoryId)
				->where('published = 1');
			$db->setQuery($query);
			$categoryPlanIds = $db->loadColumn();

			if (count(array_diff($planIds, $categoryPlanIds)) == 0)
			{
				$needles['plans']      = array($categoryId);
				$needles['categories'] = array($categoryId);
			}
		}

		if (count($needles))
		{
			require_once JPATH_ROOT . '/components/com_osmembership/helper/route.php';

			$menuItemId = OSMembershipHelperRoute::findItem($needles);

			if ($menuItemId)
			{
				return JRoute::_('index.php?Itemid=' . $menuItemId);
			}
		}

		return;
	}

	/**
	 * Generate User Input Select
	 *
	 * @param int $userId
	 * @param int $subscriberId
	 *
	 * @return string
	 */
	public static function getUserInput($userId, $subscriberId)
	{
		$field = JFormHelper::loadFieldType('User');

		$element = new SimpleXMLElement('<field />');
		$element->addAttribute('name', 'user_id');
		$element->addAttribute('class', 'readonly');

		if (!$subscriberId)
		{
			$element->addAttribute('onchange', 'populateSubscriberData();');
		}

		$field->setup($element, $userId);

		return $field->input;
	}

	/**
	 * Get current version of Membership Pro installed on the site
	 *
	 * @return string
	 */
	public static function getInstalledVersion()
	{
		return '2.6.1';
	}
}
