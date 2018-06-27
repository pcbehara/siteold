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
use Joomla\Utilities\ArrayHelper;
use Joomla\String\StringHelper;

class OSMembershipHelper
{
	/**
	 * Get configuration data and store in config object
	 *
	 * @return MPFConfig
	 */
	public static function getConfig()
	{
		static $config;

		if ($config === null)
		{
			$config = new MPFConfig('#__osmembership_configs');
		}

		return $config;
	}

	/**
	 * Check if a method is overrided in a child class
	 *
	 * @param $class
	 * @param $method
	 *
	 * @return bool
	 */
	public static function isMethodOverridden($class, $method)
	{
		if (class_exists($class) && method_exists($class, $method))
		{
			$reflectionMethod = new ReflectionMethod($class, $method);

			if ($reflectionMethod->getDeclaringClass()->getName() == $class)
			{
				return true;
			}
		}

		return false;
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

		// Fix PayPal IPN sending to wrong URL
		if (!empty($_POST['txn_type']) && empty($_REQUEST['task']) && empty($_REQUEST['view']))
		{
			$_REQUEST['payment_method'] = 'os_paypal';

			if (!empty($_POST['subscr_id']) || strpos($_POST['txn_type'], 'subscr_'))
			{
				$_REQUEST['task'] = 'recurring_payment_confirm';
			}
			else
			{
				$_REQUEST['task'] = 'payment_confirm';
			}
		}
	}

	/**
	 * Get page params of the given view
	 *
	 * @param $active
	 * @param $views
	 *
	 * @return Registry
	 */
	public static function getViewParams($active, $views)
	{
		if ($active && isset($active->query['view']) && in_array($active->query['view'], $views))
		{
			return $active->params;
		}

		return new Registry();
	}

	/**
	 * Get sef of current language
	 *
	 * @param string $tag
	 *
	 * @return void
	 */
	public static function addLangLinkForAjax($tag = '')
	{
		$langLink = '';

		if (JLanguageMultilang::isEnabled())
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);

			if (empty($tag) || $tag == '*')
			{
				$tag = JFactory::getLanguage()->getTag();
			}

			$query->select('`sef`')
				->from('#__languages')
				->where('published = 1')
				->where('lang_code = ' . $db->quote($tag));
			$db->setQuery($query, 0, 1);
			$langLink = '&lang=' . $db->loadResult();
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
		$user   = JFactory::getUser();
		$userId = $user->id;

		if ($row
			&& (($row->user_id == $userId && $userId) || $user->authorise('core.admin', 'com_osmembership'))
			&& !$row->recurring_subscription_cancelled)
		{
			return true;
		}

		return false;
	}

	/**
	 * Get list of custom fields belong to com_users
	 *
	 * @return array
	 */
	public static function getUserFields()
	{
		if (version_compare(JVERSION, '3.7.0', 'ge'))
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('id, name')
				->from('#__fields')
				->where($db->quoteName('context') . '=' . $db->quote('com_users.user'))
				->where($db->quoteName('state') . ' = 1');
			$db->setQuery($query);

			return $db->loadObjectList('name');
		}

		return [];
	}

	/**
	 * Load payment method object
	 *
	 * @param string $name
	 *
	 * @return MPFPayment
	 * @throws Exception
	 */
	public static function loadPaymentMethod($name)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__osmembership_plugins')
			->where('published = 1')
			->where('name = ' . $db->quote($name));
		$db->setQuery($query);
		$row = $db->loadObject();

		if ($row && file_exists(JPATH_ROOT . '/components/com_osmembership/plugins/' . $row->name . '.php'))
		{
			require_once JPATH_ROOT . '/components/com_osmembership/plugins/' . $name . '.php';

			$params = new Registry($row->params);

			/* @var MPFPayment $method */
			$method = new $name($params);
			$method->setTitle($row->title);

			return $method;
		}

		throw new Exception(sprintf('Payment method %s not found', $name));
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

		return $total > 0;
	}

	/**
	 * Helper function to extend subscription of a user when a recurring payment happens
	 *
	 * @param int    $id
	 * @param string $transactionId
	 * @param string $subscriptionId
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public static function extendRecurringSubscription($id, $transactionId = null, $subscriptionId = null)
	{
		/* @var OSMembershipModelApi $model */
		$model = MPFModel::getInstance('Api', 'OSMembershipModel', ['ignore_request' => true]);
		$model->renewRecurringSubscription($id, $subscriptionId, $transactionId);
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

		$query->clear()
			->select('COUNT(*)')
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
				$query->clear()
					->select('COUNT(*)')
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

			$config = OSMembershipHelper::getConfig();

			$numberDaysBeforeRenewal = (int) $config->number_days_before_renewal;

			if ($numberDaysBeforeRenewal)
			{
				//Get max date
				$query->clear()
					->select('MAX(to_date)')
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

	/*
	 * Check to see whether the current user can browse users list
	 */
	public static function canBrowseUsersList()
	{
		$user = JFactory::getUser();

		if ($user->authorise('membershippro.subscriptions', 'com_osmembership'))
		{
			return true;
		}

		$config = OSMembershipHelper::getConfig();

		if (!$config->enable_select_existing_users)
		{
			return false;
		}

		$canManage = OSMembershipHelper::getManageGroupMemberPermission();

		return $canManage > 0;
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
			JFactory::getApplication()->enqueueMessage('Please enable plugin Membership Pro - Group Membership Plugin to use this feature', 'notice');

			return 0;
		}

		$userId = JFactory::getUser()->id;

		if (!$userId)
		{
			return 0;
		}

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
				$query->clear()
					->select('COUNT(*)')
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
	 * Method to check to see whether the current user can access to the current view
	 *
	 * @param string $view
	 *
	 * @return bool
	 */
	public static function canAccessThisView($view)
	{
		$user   = JFactory::getUser();
		$access = true;

		switch ($view)
		{
			case 'categories':
			case 'category':
				$access = $user->authorise('membershippro.categories', 'com_osmembership');
				break;
			case 'plans':
			case 'plan':
				$access = $user->authorise('membershippro.plans', 'com_osmembership');
				break;
			case 'subscriptions':
			case 'subscription':
			case 'reports':
			case 'subscribers':
			case 'subscriber':
			case 'groupmembers':
			case 'groupmember':
			case 'import':
				$access = $user->authorise('membershippro.subscriptions', 'com_osmembership');
				break;
			case 'configuration':
			case 'plugins':
			case 'plugin':
			case 'taxes':
			case 'tax':
			case 'countries':
			case 'country':
			case 'states':
			case 'state':
			case 'message':
				$access = $user->authorise('core.admin', 'com_osmembership');
				break;
			case 'fields':
			case 'field':
				$access = $user->authorise('membershippro.fields', 'com_osmembership');
				break;
			case 'coupons':
			case 'coupon':
				$access = $user->authorise('membershippro.coupons', 'com_osmembership');
				break;
		}

		return $access;
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
			$query->clear()
				->update('#__osmembership_subscribers')
				->set('is_profile = 1')
				->set('profile_id =' . $id)
				->where('id = ' . $id);
			$db->setQuery($query);
			$db->execute();

			// Mark all other records of this user has profile_id = ID of this record
			$query->clear()
				->update('#__osmembership_subscribers')
				->set('profile_id = ' . $id)
				->where('user_id = ' . $userId)
				->where('id != ' . $id);
			$db->setQuery($query);
			$db->execute();

			return true;
		}

		return false;
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
		if (OSMembershipHelper::isMethodOverridden('OSMembershipHelperOverrideHelper', 'needToCreateInvoice'))
		{
			return OSMembershipHelperOverrideHelper::needToCreateInvoice($row);
		}

		$config    = OSMembershipHelper::getConfig();
		$published = (int) $row->published;

		if (($row->amount > 0 || $row->gross_amount > 0)
			&& ($published === 1 || !$config->generated_invoice_for_paid_subscription_only))
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
		if (OSMembershipHelper::isMethodOverridden('OSMembershipHelperOverrideHelper', 'convertAmountToUSD'))
		{
			return OSMembershipHelperOverrideHelper::convertAmountToUSD($amount, $currency);
		}

		static $rate = null;

		if ($rate === null)
		{
			$url = sprintf('https://www.google.com/search?q=1+%s+to+%s', 'USD', $currency);

			$headers = [
				'Accept'     => 'text/html',
				'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:21.0) Gecko/20100101 Firefox/21.0',
			];

			$http     = JHttpFactory::getHttp();
			$response = $http->get($url, $headers);

			if (302 == $response->code && isset($response->headers['Location']))
			{
				$response = $http->get($response->headers['Location'], $headers);
			}

			$body = $response->body;

			try
			{
				$rate = static::buildExchangeRate($body);
			}
			catch (Exception $e)
			{

			}
		}

		if ($rate > 0)
		{
			$amount = $amount / $rate;
		}

		return round($amount, 2);
	}

	/**
	 * Builds an exchange rate from the response content.
	 *
	 * @param string $content
	 *
	 * @return float
	 *
	 * @throws \Exception
	 */
	protected static function buildExchangeRate($content)
	{
		$document = new \DOMDocument();

		if (false === @$document->loadHTML('<?xml encoding="utf-8" ?>' . $content))
		{
			throw new Exception('The page content is not loadable');
		}

		$xpath = new \DOMXPath($document);
		$nodes = $xpath->query('//span[@id="knowledge-currency__tgt-amount"]');

		if (1 !== $nodes->length)
		{
			$nodes = $xpath->query('//div[@class="vk_ans vk_bk" or @class="dDoNo vk_bk"]');
		}

		if (1 !== $nodes->length)
		{
			throw new Exception('The currency is not supported or Google changed the response format');
		}

		$nodeContent = $nodes->item(0)->textContent;

		// Beware of "3 417.36111 Colombian pesos", with a non breaking space
		$bid = strtr($nodeContent, ["\xc2\xa0" => '']);

		if (false !== strpos($bid, ' '))
		{
			$bid = strstr($bid, ' ', true);
		}
		// Does it have thousands separator?
		if (strpos($bid, ',') && strpos($bid, '.'))
		{
			$bid = str_replace(',', '', $bid);
		}

		if (!is_numeric($bid))
		{
			throw new Exception('The currency is not supported or Google changed the response format');
		}

		return $bid;
	}

	/**
	 * Check to see whether the return value is a valid date format
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	public static function isValidDate($value)
	{
		// basic date format yyyy-mm-dd
		$expr = '/^([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})$/D';

		return preg_match($expr, $value, $match) && checkdate($match[2], $match[3], $match[1]);
	}

	/**
	 * Calculate subscription fees based on input parameter
	 *
	 * @param OSMembershipTablePlan $rowPlan the object which contains information about the plan
	 * @param MPFForm               $form    The form object which is used to calculate extra fee
	 * @param array                 $data    The post data
	 * @param MPFConfig             $config
	 * @param string                $paymentMethod
	 *
	 * @return array
	 */
	public static function calculateSubscriptionFee($rowPlan, $form, $data, $config, $paymentMethod = null)
	{
		$db       = JFactory::getDbo();
		$query    = $db->getQuery(true);
		$nullDate = $db->getNullDate();

		$fees           = array();
		$feeAmount      = $form->calculateFee(array('PLAN_PRICE' => $rowPlan->price));
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
			$params            = new Registry($method->params);
			$paymentFeeAmount  = (float) $params->get('payment_fee_amount');
			$paymentFeePercent = (float) $params->get('payment_fee_percent');
		}

		$couponCode = isset($data['coupon_code']) ? $data['coupon_code'] : '';

		if ($couponCode)
		{
			$planId = $rowPlan->id;

			$query->clear()
				->select('*')
				->from('#__osmembership_coupons')
				->where('published = 1')
				->where($db->quoteName('access') . ' IN (' . implode(',', JFactory::getUser()->getAuthorisedViewLevels()) . ')')
				->where('code = ' . $db->quote($couponCode))
				->where('(valid_from = ' . $db->quote($nullDate) . ' OR DATE(valid_from) <= CURDATE())')
				->where('(valid_to = ' . $db->quote($nullDate) . ' OR DATE(valid_to) >= CURDATE())')
				->where('(times = 0 OR times > used)')
				->where('(plan_id = 0 OR id IN (SELECT coupon_id FROM #__osmembership_coupon_plans WHERE plan_id = ' . $planId . '))');
			$db->setQuery($query);
			$coupon = $db->loadObject();

			if (!$coupon)
			{
				$couponValid = 0;
			}
			else
			{
				$fees['coupon_id'] = $coupon->id;
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

		$action = $data['act'];

		if ($action != 'renew')
		{
			$setupFee = $rowPlan->setup_fee;
		}
		else
		{
			$setupFee = 0;
		}

		$fees['setup_fee'] = $setupFee;

		if (!$rowPlan->recurring_subscription)
		{
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

				// Get renewal discount
				$renewalDiscount = OSMembershipHelperSubscription::getRenewalDiscount((int) JFactory::getUser()->id, $rowPlan->id);

				if ($renewalDiscount)
				{
					if ($renewalDiscount->discount_type == 0)
					{
						$amount = round($amount * (1 - $renewalDiscount->discount_amount / 100), 2);
					}
					else
					{
						$amount = $amount - $renewalDiscount->discount_amount;
					}
				}
			}
			elseif ($action == 'upgrade')
			{
				$query->clear()
					->select('*')
					->from('#__osmembership_upgraderules')
					->where('id = ' . (int) $data['upgrade_option_id']);
				$db->setQuery($query);
				$upgradeOption = $db->loadObject();
				$amount        = $upgradeOption->price;

				if ($upgradeOption->upgrade_prorated == 2)
				{
					$amount -= OSMembershipHelperSubscription::calculateProratedUpgradePrice($upgradeOption, (int) JFactory::getUser()->id);
				}
			}
			else
			{
				$amount = $rowPlan->price;

				if ($rowPlan->expired_date && $rowPlan->expired_date != $nullDate && $rowPlan->prorated_signup_cost)
				{
					$expiredDate = JFactory::getDate($rowPlan->expired_date, JFactory::getConfig()->get('offset'));
					$date        = JFactory::getDate('now', JFactory::getConfig()->get('offset'));
					$expiredDate->setTime(23, 59, 59);
					$date->setTime(23, 59, 59);

					if ($rowPlan->subscription_length_unit == 'Y')
					{
						$subscriptionLengthYears = $rowPlan->subscription_length;
					}
					else
					{
						$subscriptionLengthYears = 1;
					}

					$expiredDate->setDate($date->year, $expiredDate->month, $expiredDate->day);

					if ($date > $expiredDate)
					{
						$expiredDate->modify("+ $subscriptionLengthYears years");
					}
					else
					{
						$expiredDate->modify("+ " . ($subscriptionLengthYears - 1) . " years");
					}

					$diff = $expiredDate->diff($date, true);

					if ($rowPlan->prorated_signup_cost == 1)
					{
						$numberDays = $subscriptionLengthYears * 365;
						$amount     = $amount * ($diff->days + 1) / $numberDays;
					}
					elseif ($rowPlan->prorated_signup_cost == 2)
					{
						$numberMonths = $subscriptionLengthYears * 12;
						$amount       = $amount * ($diff->y * 12 + $diff->m + 1) / $numberMonths;
					}
				}
			}

			$amount += $feeAmount;

			if (!empty($coupon))
			{
				if ($coupon->coupon_type == 0)
				{
					$discountAmount = ($amount + $setupFee) * $coupon->discount / 100;
				}
				else
				{
					$discountAmount = min($coupon->discount, $amount + $setupFee);
				}
			}

			if ($taxRate > 0)
			{
				$taxAmount = round(($amount + $setupFee - $discountAmount) * $taxRate / 100, 2);
			}

			$grossAmount                    = $setupFee + $amount - $discountAmount + $taxAmount;
			$fees['payment_processing_fee'] = 0;

			if ($paymentFeeAmount > 0 || $paymentFeePercent > 0)
			{
				if ($grossAmount > 0)
				{
					$fees['payment_processing_fee'] = round($paymentFeeAmount + $grossAmount * $paymentFeePercent / 100, 2);
					$grossAmount                    += $fees['payment_processing_fee'];
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
			if ($action == 'upgrade')
			{
				$query->clear()
					->select('*')
					->from('#__osmembership_upgraderules')
					->where('id = ' . (int) $data['upgrade_option_id']);
				$db->setQuery($query);
				$upgradeOption = $db->loadObject();
				$regularAmount = $upgradeOption->price + $feeAmount;

				if ($upgradeOption->upgrade_prorated == 2)
				{
					$regularAmount -= OSMembershipHelperSubscription::calculateProratedUpgradePrice($upgradeOption, (int) JFactory::getUser()->id);
				}
			}
			else
			{
				$regularAmount = $rowPlan->price + $feeAmount;
			}

			$regularDiscountAmount = 0;
			$regularTaxAmount      = 0;
			$trialDiscountAmount   = 0;
			$trialTaxAmount        = 0;
			$trialAmount           = 0;
			$trialDuration         = 0;
			$trialDurationUnit     = '';

			if ($rowPlan->trial_duration || $setupFee > 0 || (!empty($coupon) && $coupon->apply_for == 1))
			{
				// There will be trial duration
				if ($rowPlan->trial_duration)
				{
					$trialAmount       = $rowPlan->trial_amount + $feeAmount + $setupFee;
					$trialDuration     = $rowPlan->trial_duration;
					$trialDurationUnit = $rowPlan->trial_duration_unit;
				}
				elseif ($setupFee > 0)
				{
					$trialAmount       = $regularAmount + $setupFee;
					$trialDuration     = $rowPlan->subscription_length;
					$trialDurationUnit = $rowPlan->subscription_length_unit;
				}
				else
				{
					$trialAmount       = $regularAmount + $setupFee;
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

				$trialGrossAmount   += $fees['trial_payment_processing_fee'];
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

		$fees['tax_rate'] = $taxRate;

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
			$query->clear()
				->select('rate')
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
	 * @param bool   $useDefaultCountryIfEmpty
	 *
	 * @return int
	 */
	public static function calculateMaxTaxRate($planId, $country = '', $state = '', $vies = 2, $useDefaultCountryIfEmpty = true)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		if (empty($country) && $useDefaultCountryIfEmpty)
		{
			$country = self::getConfigValue('default_country');
		}

		$query->select('rate')
			->from('#__osmembership_taxes')
			->where('published = 1')
			->where('plan_id = ' . $planId)
			->order('`rate` DESC');

		if ($country)
		{
			$query->where('(country = "" OR country = ' . $db->quote($country) . ')')
				->order('country DESC');
		}

		if ($state)
		{
			$query->where('(state = "" OR state = ' . $db->quote($state) . ')')
				->order('`state` DESC');
		}

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
			$query->clear()
				->select('rate')
				->from('#__osmembership_taxes')
				->where('published = 1')
				->where('plan_id = 0')
				->order('`rate` DESC');

			if ($country)
			{
				$query->where('(country = "" OR country = ' . $db->quote($country) . ')')
					->order('country DESC');
			}

			if ($state)
			{
				$query->where('(state = "" OR state = ' . $db->quote($state) . ')')
					->order('`state` DESC');
			}

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
		$user        = JFactory::getUser();
		$db          = JFactory::getDbo();
		$query       = $db->getQuery(true);
		$planId      = (int) $planId;
		$fieldSuffix = self::getFieldSuffix($language);
		$query->select('*')
			->from('#__osmembership_fields')
			->where('published = 1')
			->where('(plan_id=0 OR id IN (SELECT field_id FROM #__osmembership_field_plan WHERE plan_id=' . $planId . '))');

		if (!$user->authorise('core.admin', 'com_osmembership'))
		{
			$query->where('`access` IN (' . implode(',', $user->getAuthorisedViewLevels()) . ')');
		}

		if ($fieldSuffix)
		{
			require_once JPATH_ROOT . '/components/com_osmembership/helper/database.php';

			OSMembershipHelperDatabase::getMultilingualFields(
				$query,
				array('title', 'description', 'values', 'default_values', 'depend_on_options', 'place_holder',),
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
		$data  = [];
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
				$data[$rowField->name] = $rowProfile->{
				$rowField->name};
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
			$query->clear()
				->select('*')
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

			$query->clear()
				->select('name')
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
	 * @return MPFConfig
	 */
	public static function getMessages()
	{
		static $message;

		if ($message === null)
		{
			$message = new MPFConfig('#__osmembership_messages', 'message_key', 'message');
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
	 * @return array languages object list
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
	 *
	 * @return void
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
				$prefix = $language->sef;

				#Process for #__osmembership_categories table
				$varcharFields = array(
					'alias',
					'title',
				);

				foreach ($varcharFields as $varcharField)
				{
					$fieldName = $varcharField . '_' . $prefix;

					if (!in_array($fieldName, $categoryTableFields))
					{
						$sql = "ALTER TABLE  `#__osmembership_categories` ADD  `$fieldName` VARCHAR( 255 );";
						$db->setQuery($sql);
						$db->execute();
					}
				}

				$textFields = array(
					'description',
				);

				foreach ($textFields as $textField)
				{
					$fieldName = $textField . '_' . $prefix;

					if (!in_array($fieldName, $categoryTableFields))
					{
						$sql = "ALTER TABLE  `#__osmembership_categories` ADD  `$fieldName` TEXT NULL;";
						$db->setQuery($sql);
						$db->execute();
					}
				}

				#Process for #__osmembership_plans table
				$varcharFields = array(
					'alias',
					'title',
					'page_title',
					'page_heading',
					'meta_keywords',
					'meta_description',
					'user_email_subject',
					'subscription_approved_email_subject',
					'user_renew_email_subject',
				);

				foreach ($varcharFields as $varcharField)
				{
					$fieldName = $varcharField . '_' . $prefix;

					if (!in_array($fieldName, $planTableFields))
					{
						$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` VARCHAR( 255 );";
						$db->setQuery($sql);
						$db->execute();
					}
				}

				$textFields = array(
					'short_description',
					'description',
					'subscription_form_message',
					'user_email_body',
					'user_email_body_offline',
					'subscription_approved_email_body',
					'thanks_message',
					'thanks_message_offline',
					'user_renew_email_body'
				);

				foreach ($textFields as $textField)
				{
					$fieldName = $textField . '_' . $prefix;

					if (!in_array($fieldName, $planTableFields))
					{
						$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `$fieldName` TEXT NULL;";
						$db->setQuery($sql);
						$db->execute();
					}
				}

				#Process for #__osmembership_fields table
				$varcharFields = array(
					'title',
					'place_holder',
				);

				foreach ($varcharFields as $varcharField)
				{
					$fieldName = $varcharField . '_' . $prefix;

					if (!in_array($fieldName, $fieldTableFields))
					{
						$sql = "ALTER TABLE  `#__osmembership_fields` ADD  `$fieldName` VARCHAR( 255 );";
						$db->setQuery($sql);
						$db->execute();
					}
				}

				$textFields = array(
					'description',
					'values',
					'default_values',
					'fee_values',
					'depend_on_options',
				);

				foreach ($textFields as $textField)
				{
					$fieldName = $textField . '_' . $prefix;

					if (!in_array($fieldName, $fieldTableFields))
					{
						$sql = "ALTER TABLE  `#__osmembership_fields` ADD  `$fieldName` TEXT NULL;";
						$db->setQuery($sql);
						$db->execute();
					}
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
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('country_2_code')
			->from('#__osmembership_countries')
			->where('LOWER(name) = ' . $db->quote(StringHelper::strtolower($countryName)));
		$db->setQuery($query);
		$countryCode = $db->loadResult();

		if (!$countryCode)
		{
			$countryCode = 'US';
		}

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
		echo '<div class="copyright" style="text-align: center;margin-top: 5px;"><a href="http://joomdonation.com/joomla-extensions/membership-pro-joomla-membership-subscription.html" target="_blank"><strong>Membership Pro</strong></a> version ' . self::getInstalledVersion() . ', Copyright (C) 2012-' . date('Y') . ' <a href="http://joomdonation.com" target="_blank"><strong>Ossolution Team</strong></a></div>';
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
		$params          = new Registry($plugin->params);
		$excludeGroupIds = $params->get('exclude_group_ids', '7,8');
		$excludeGroupIds = explode(',', $excludeGroupIds);
		$excludeGroupIds = ArrayHelper::toInteger($excludeGroupIds);

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
	 * @param OSMembershipTableSubscriber $row
	 * @param                             $config
	 *
	 * @return string
	 */
	public static function formatMembershipId($row, $config)
	{
		if (!$row->is_profile)
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('created_date')
				->from('#__osmembership_subscribers')
				->where('id = ' . (int) $row->profile_id);
			$db->setQuery($query);
			$createdDate = $db->loadResult();
		}
		else
		{
			$createdDate = $row->created_date;
		}

		$idPrefix = str_replace('[YEAR]', JHtml::_('date', $createdDate, 'Y'), $config->membership_id_prefix);
		$idPrefix = str_replace('[MONTH]', JHtml::_('date', $createdDate, 'm'), $idPrefix);

		if ($config->membership_id_length)
		{
			return $idPrefix . str_pad($row->membership_id, (int) $config->membership_id_length, '0', STR_PAD_LEFT);
		}
		else
		{
			return $idPrefix . $row->membership_id;
		}
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

		if (OSMembershipHelper::isMethodOverridden('OSMembershipHelperOverrideHelper', 'generateInvoicePDF'))
		{
			OSMembershipHelperOverrideHelper::generateInvoicePDF($row);

			return;
		}

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
		$pdf->setHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->setFooterMargin(PDF_MARGIN_FOOTER);
		//set auto page breaks
		$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
		//set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

		$font = empty($config->pdf_font) ? 'times' : $config->pdf_font;

		$pdf->SetFont($font, '', 8);
		$pdf->AddPage();

		$fieldSuffix = OSMembershipHelper::getFieldSuffix($row->language);

		if ($fieldSuffix && strlen(strip_tags($config->{'invoice_format' . $fieldSuffix})) > 100)
		{
			$invoiceOutput = $config->{'invoice_format' . $fieldSuffix};
		}
		else
		{
			$invoiceOutput = $config->invoice_format;
		}

		$replaces                      = array();
		$replaces['first_name']        = $row->first_name;
		$replaces['last_name']         = $row->last_name;
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
		$replaces['comment']           = $row->comment;
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
		$replaces['profile_id']        = $row->profile_id;

		if ($row->payment_method)
		{
			$method = os_payments::loadPaymentMethod($row->payment_method);
			if ($method)
			{
				$replaces['payment_method'] = JText::_($method->title);
			}
		}

		// Support for name of custom field in tags
		$query->clear()
			->select('field_id, field_value')
			->from('#__osmembership_field_value')
			->where('subscriber_id = ' . $row->id);
		$db->setQuery($query);
		$rowValues = $db->loadObjectList('field_id');

		$query->clear()
			->select('id, name, fieldtype')
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
		elseif ($row->published == 3)
		{
			$invoiceStatus = JText::_('OSM_INVOICE_STATUS_CANCELLED_PENDING');
		}
		elseif ($row->published == 4)
		{
			$invoiceStatus = JText::_('OSM_INVOICE_STATUS_CANCELLED_REFUNDED');
		}
		else
		{
			$invoiceStatus = JText::_('');
		}

		$replaces['SETUP_FEE']              = self::formatCurrency($row->setup_fee, $config, $rowPlan->currency_symbol);
		$replaces['INVOICE_STATUS']         = $invoiceStatus;
		$replaces['ITEM_QUANTITY']          = 1;
		$replaces['ITEM_AMOUNT']            = $replaces['ITEM_SUB_TOTAL'] = self::formatCurrency($row->amount, $config, $rowPlan->currency_symbol);
		$replaces['DISCOUNT_AMOUNT']        = self::formatCurrency($row->discount_amount, $config, $rowPlan->currency_symbol);
		$replaces['SUB_TOTAL']              = self::formatCurrency($row->amount + $row->setup_fee - $row->discount_amount, $config, $rowPlan->currency_symbol);
		$replaces['TAX_AMOUNT']             = self::formatCurrency($row->tax_amount, $config, $rowPlan->currency_symbol);
		$replaces['payment_processing_fee'] = self::formatCurrency($row->payment_processing_fee, $config, $rowPlan->currency_symbol);
		$replaces['TOTAL_AMOUNT']           = self::formatCurrency($row->gross_amount, $config, $rowPlan->currency_symbol);
		$replaces['TAX_RATE']               = self::formatAmount($row->tax_rate, $config, $rowPlan->currency_symbol);

		switch ($row->act)
		{
			case 'renew':
				$itemName = JText::_('OSM_PAYMENT_FOR_RENEW_SUBSCRIPTION');
				$itemName = str_replace('[PLAN_TITLE]', $rowPlan->title, $itemName);
				break;
			case 'upgrade':
				$itemName = JText::_('OSM_PAYMENT_FOR_UPGRADE_SUBSCRIPTION');
				$itemName = str_replace('[PLAN_TITLE]', $rowPlan->title, $itemName);
				$query->clear()
					->select('a.title')
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

		$replaces['ITEM_NAME']              = $itemName;
		$replaces['PLAN_SHORT_DESCRIPTION'] = $rowPlan->short_description;
		$replaces['PLAN_DESCRIPTION']       = $rowPlan->description;
		$replaces['PLAN_ID']                = $rowPlan->id;

		foreach ($replaces as $key => $value)
		{
			$key           = strtoupper($key);
			$invoiceOutput = str_ireplace("[$key]", $value, $invoiceOutput);
		}


		$pdf->writeHTML($invoiceOutput, true, false, false, false, '');

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
	 * @param string $text
	 *
	 * @return string
	 */
	public static function convertImgTags($text)
	{
		$app = JFactory::getApplication();

		$siteUrl    = JUri::root();
		$rootURL    = rtrim(JUri::root(), '/');
		$subpathURL = JUri::root(true);

		if (!empty($subpathURL) && ($subpathURL != '/'))
		{
			$rootURL = substr($rootURL, 0, -1 * strlen($subpathURL));
		}

		// Replace index.php URI by SEF URI.
		if (strpos($text, 'href="index.php?') !== false)
		{
			preg_match_all('#href="index.php\?([^"]+)"#m', $text, $matches);

			foreach ($matches[1] as $urlQueryString)
			{

				if ($app->isSite())
				{
					$text = str_replace(
						'href="index.php?' . $urlQueryString . '"',
						'href="' . $rootURL . JRoute::_('index.php?' . $urlQueryString) . '"',
						$text
					);
				}
				else
				{
					$text = str_replace(
						'href="index.php?' . $urlQueryString . '"',
						'href="' . $siteUrl . 'index.php?' . $urlQueryString . '"',
						$text
					);
				}
			}
		}

		$patterns     = array();
		$replacements = array();
		$i            = 0;
		$src_exp      = "/src=\"(.*?)\"/";
		$link_exp     = "[^http:\/\/www\.|^www\.|^https:\/\/|^http:\/\/]";

		preg_match_all($src_exp, $text, $out, PREG_SET_ORDER);

		foreach ($out as $val)
		{
			$links = preg_match($link_exp, $val[1], $match, PREG_OFFSET_CAPTURE);

			if ($links == '0')
			{
				$patterns[$i]     = $val[1];
				$patterns[$i]     = "\"$val[1]";
				$replacements[$i] = $siteUrl . $val[1];
				$replacements[$i] = "\"$replacements[$i]";
			}

			$i++;
		}

		$text = str_replace($patterns, $replacements, $text);

		return $text;
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
		$replaces['user_id']                = $row->user_id;
		$replaces['profile_id']             = $row->profile_id;
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
		$replaces['fax']                    = $row->fax;
		$replaces['email']                  = $row->email;
		$replaces['comment']                = $row->comment;
		$replaces['amount']                 = self::formatAmount($row->amount, $config);
		$replaces['discount_amount']        = self::formatAmount($row->discount_amount, $config);
		$replaces['tax_amount']             = self::formatAmount($row->tax_amount, $config);
		$replaces['gross_amount']           = self::formatAmount($row->gross_amount, $config);
		$replaces['payment_processing_fee'] = self::formatAmount($row->payment_processing_fee, $config);
		$replaces['tax_rate']               = self::formatAmount($row->tax_rate, $config);
		$replaces['from_date']              = JHtml::_('date', $row->from_date, $config->date_format);
		$replaces['to_date']                = JHtml::_('date', $row->to_date, $config->date_format);
		$replaces['created_date']           = JHtml::_('date', $row->created_date, $config->date_format);
		$replaces['date']                   = JHtml::_('date', 'Now', $config->date_format);
		$replaces['end_date']               = $replaces['to_date'];
		$replaces['payment_method']         = '';

		// Support avatar tags
		if ($row->avatar && file_exists(JPATH_ROOT . '/media/com_osmembership/avatars/' . $row->avatar))
		{
			$replaces['avatar'] = '<img class="oms-avatar" src="media/com_osmembership/avatars/' . $row->avatar . '"/>';
		}
		else
		{
			$replaces['avatar'] = '';
		}

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

		switch ($row->published)
		{
			case 0 :
				$replaces['subscription_status'] = JText::_('OSM_PENDING');
				break;
			case 1 :
				$replaces['subscription_status'] = JText::_('OSM_ACTIVE');
				break;
			case 2 :
				$replaces['subscription_status'] = JText::_('OSM_EXPIRED');
				break;
			case 3 :
				$replaces['subscription_status'] = JText::_('OSM_CANCELLED_PENDING');
				break;
			case 4 :
				$replaces['subscription_status'] = JText::_('OSM_CANCELLED_REFUNDED');
				break;
			default:
				$replaces['subscription_status'] = 'Unknown';
		}

		// Support for name of custom field in tags
		$query->select('field_id, field_value')
			->from('#__osmembership_field_value')
			->where('subscriber_id = ' . $row->id);
		$db->setQuery($query);
		$rowValues = $db->loadObjectList('field_id');

		$query->clear()
			->select('id, name, fieldtype')
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

		// Build plan replaced tags
		$rowPlan = OSMembershipHelperDatabase::getPlan($row->plan_id);

		$replaces['plan_short_description'] = $rowPlan->short_description;
		$replaces['plan_description']       = $rowPlan->description;
		$replaces['plan_id']                = $rowPlan->id;
		$replaces['plan_title']             = $rowPlan->title;


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
			$cardNumber          = JFactory::getApplication()->input->getString('x_card_num');
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

			if (JLanguageMultilang::isEnabled())
			{
				JForm::addFormPath(JPATH_ROOT . '/components/com_users/models/forms');
				JForm::addFieldPath(JPATH_ROOT . '/components/com_users/models/fields');
			}

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
				$userData['activation'] = JApplicationHelper::getHash(JUserHelper::genRandomPassword());
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
		if (OSMembershipHelper::isMethodOverridden('OSMembershipHelperOverrideHelper', 'getRestrictionRedirectUrl'))
		{
			return OSMembershipHelperOverrideHelper::getRestrictionRedirectUrl($planIds);
		}

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
			$planId = $planIds[0];

			$Itemid = OSMembershipHelperRoute::getPlanMenuId($planId, $categoryId, OSMembershipHelper::getItemid());

			return JRoute::_('index.php?option=com_osmembership&view=plan' . ($categoryId > 0 ? '&catid=' . $categoryId : '') . '&id=' . $planId . '&Itemid=' . $Itemid);
		}
		elseif ($categoryId > 0)
		{
			// If the category contains all the plans here, we will find menu item linked to that category
			$query->clear()
				->select('id')
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
		if (JFactory::getApplication()->isSite())
		{
			// Initialize variables.
			$html = array();
			$link = 'index.php?option=com_osmembership&amp;view=users&amp;layout=modal&amp;tmpl=component&amp;field=user_id';
			// Initialize some field attributes.
			$attr = ' class="inputbox"';
			// Load the modal behavior script.
			JHtml::_('behavior.modal', 'a.modal_user_id');
			// Build the script.
			$script   = array();
			$script[] = '	function jSelectUser_user_id(id, title) {';
			$script[] = '			document.getElementById("jform_user_id").value = title; ';
			$script[] = '			document.getElementById("user_id_id").value = id; ';
			if (!$subscriberId)
			{
				$script[] = 'populateSubscriberData()';
			}
			$script[] = '		SqueezeBox.close();';
			$script[] = '	}';
			// Add the script to the document head.
			JFactory::getDocument()->addScriptDeclaration(implode("\n", $script));
			// Load the current username if available.
			$table = JTable::getInstance('user');
			if ($userId)
			{
				$table->load($userId);
			}
			else
			{
				$table->name = '';
			}
			// Create a dummy text field with the user name.
			$html[] = '<div class="input-append">';
			$html[] = '	<input type="text" readonly="" name="jform[user_id]" id="jform_user_id"' . ' value="' . $table->name . '"' . $attr . ' />';
			$html[] = '	<input type="hidden" name="user_id" id="user_id_id"' . ' value="' . $userId . '"' . $attr . ' />';
			// Create the user select button.
			$html[] = '<a class="btn btn-primary button-select modal_user_id" title="' . JText::_('JLIB_FORM_CHANGE_USER') . '"' . ' href="' . $link . '"' .
				' rel="{handler: \'iframe\', size: {x: 800, y: 500}}">';
			$html[] = ' <span class="icon-user"></span></a>';
			$html[] = '</div>';

			return implode("\n", $html);
		}
		else
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
	}

	/**
	 * Check if the given message entered via HTML editor has actual data
	 *
	 * @param $string
	 *
	 * @return bool
	 */
	public static function isValidMessage($string)
	{
		$string = strip_tags($string, '<img>');

		// Remove none printable characters
		$string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', $string);

		$string = trim($string);

		if (strlen($string))
		{
			return true;
		}

		return false;
	}

	/**
	 * Get documents path
	 *
	 * @return string
	 */
	public static function getDocumentsPath()
	{
		$documentsPath = JPATH_ROOT . '/media/com_osmembership/documents';

		$plugin = JPluginHelper::getPlugin('osmembership', 'documents');

		if (is_string($plugin->params))
		{
			$params = new Registry($plugin->params);
		}
		elseif ($plugin->params instanceof Registry)
		{
			$params = $plugin->params;
		}
		else
		{
			$params = new Registry;
		}

		$path = $params->get('documents_path', 'media/com_osmembership/documents');

		if (JFolder::exists(JPATH_ROOT . '/' . $path))
		{
			$documentsPath = JPATH_ROOT . '/' . $path;
		}
		elseif (JFolder::exists($path))
		{
			$documentsPath = $path;
		}

		return $documentsPath;
	}

	/**
	 * Get all dependencies custom fields of a given field
	 *
	 * @param $id
	 *
	 * @return array
	 */
	public static function getAllDependencyFields($id)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$queue  = array($id);
		$fields = array($id);

		while (count($queue))
		{
			$masterFieldId = array_pop($queue);

			//Get list of dependency fields of this master field
			$query->clear()
				->select('id')
				->from('#__osmembership_fields')
				->where('depend_on_field_id = ' . $masterFieldId);
			$db->setQuery($query);
			$rows = $db->loadObjectList();

			if (count($rows))
			{
				foreach ($rows as $row)
				{
					$queue[]  = $row->id;
					$fields[] = $row->id;
				}
			}
		}

		return $fields;
	}

	/**
	 * Get current version of Membership Pro installed on the site
	 *
	 * @return string
	 */
	public static function getInstalledVersion()
	{
		return '2.14.1';
	}
}
