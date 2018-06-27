<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

class OSMembershipHelperSubscription
{
	/**
	 * Get membership profile record of the given user
	 *
	 * @param int $userId
	 *
	 * @return object
	 */
	public static function getMembershipProfile($userId)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('a.*, b.username')
			->from('#__osmembership_subscribers AS a ')
			->leftJoin('#__users AS b ON a.user_id = b.id')
			->where('is_profile = 1')
			->where('user_id = ' . (int) $userId)
			->order('a.id DESC');
		$db->setQuery($query);

		return $db->loadObject();
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

		$userId = (int) $userId;
		$query->select('id')
			->from('#__osmembership_subscribers')
			->where('user_id = ' . $userId)
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
	 * Get active subscription plan ids of the given user
	 *
	 * @param int   $userId
	 * @param array $excludes
	 *
	 * @return array
	 */
	public static function getActiveMembershipPlans($userId = 0, $excludes = array())
	{
		$activePlans = array(0);

		if (!$userId)
		{
			$userId = (int) JFactory::getUser()->get('id');
		}

		if ($userId > 0)
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);
			$now   = $db->quote(JFactory::getDate('now')->format('Y-m-d'));
			$query->select('a.id')
				->from('#__osmembership_plans AS a')
				->innerJoin('#__osmembership_subscribers AS b ON a.id = b.plan_id')
				->where('b.user_id = ' . $userId)
				->where('b.published = 1')
				->where('(a.lifetime_membership = 1 OR (DATEDIFF(' . $now . ', from_date) >= -1 AND DATE(to_date) >= ' . $now . '))');

			if (count($excludes))
			{
				$query->where('b.id NOT IN (' . implode(',', $excludes) . ')');
			}

			$db->setQuery($query);

			$activePlans = array_merge($activePlans, $db->loadColumn());
		}

		return $activePlans;
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
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__osmembership_subscribers')
			->where('profile_id = ' . (int) $profileId)
			->order('to_date');
		$db->setQuery($query);
		$rows             = $db->loadObjectList();
		$rowSubscriptions = array();

		foreach ($rows as $row)
		{
			$rowSubscriptions[$row->plan_id][] = $row;
		}

		$planIds = array_keys($rowSubscriptions);

		if (count($planIds) == 0)
		{
			$planIds = array(0);
		}

		$query->clear()
			->select('*')
			->from('#__osmembership_plans')
			->where('id IN (' . implode(',', $planIds) . ')');
		$db->setQuery($query);
		$rowPlans = $db->loadObjectList();

		// Translate plan title
		$fieldSuffix = OSMembershipHelper::getFieldSuffix();

		if ($fieldSuffix)
		{
			OSMembershipHelperDatabase::getMultilingualFields($query, array('title', 'description'), $fieldSuffix);
		}


		foreach ($rowPlans as $rowPlan)
		{
			$isActive           = false;
			$isPending          = false;
			$isExpired          = false;
			$subscriptions      = $rowSubscriptions[$rowPlan->id];
			$lastActiveDate     = null;
			$subscriptionId     = null;
			$recurringCancelled = 0;

			foreach ($subscriptions as $subscription)
			{
				if ($subscription->published == 1)
				{
					$isActive       = true;
					$lastActiveDate = $subscription->to_date;
				}
				elseif ($subscription->published == 0)
				{
					$isPending = true;
				}
				elseif ($subscription->published == 2)
				{
					$isExpired = true;
				}

				if ($subscription->recurring_subscription_cancelled)
				{
					$recurringCancelled = 1;
				}

				if ($subscription->subscription_id && !$subscription->recurring_subscription_cancelled && in_array($subscription->payment_method, array('os_authnet', 'os_stripe', 'os_paypal_pro', 'os_ideal')))
				{
					$subscriptionId = $subscription->subscription_id;
				}

			}
			$rowPlan->subscriptions          = $subscriptions;
			$rowPlan->subscription_id        = $subscriptionId;
			$rowPlan->subscription_from_date = $subscriptions[0]->from_date;
			$rowPlan->subscription_to_date   = $subscriptions[count($subscriptions) - 1]->to_date;
			$rowPlan->recurring_cancelled    = $recurringCancelled;
			if ($isActive)
			{
				$rowPlan->subscription_status  = 1;
				$rowPlan->subscription_to_date = $lastActiveDate;
			}
			elseif ($isPending)
			{
				$rowPlan->subscription_status = 0;
			}
			elseif ($isExpired)
			{
				$rowPlan->subscription_status = 2;
			}
			else
			{
				$rowPlan->subscription_status = 3;
			}
		}

		return $rowPlans;
	}

	/**
	 * Get upgrade rules available for the current user
	 *
	 * @param int $userId
	 *
	 * @return array
	 */
	public static function getUpgradeRules($userId = 0)
	{
		if (OSMembershipHelper::isMethodOverridden('OSMembershipHelperOverrideSubscription', 'getUpgradeRules'))
		{
			return OSMembershipHelperOverrideSubscription::getUpgradeRules($userId);
		}

		$user = JFactory::getUser();

		if (empty($userId))
		{
			$userId = (int) $user->get('id');
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		// Get list of plans which users can upgrade from
		$query->select('DISTINCT plan_id')
			->from('#__osmembership_subscribers')
			->where('user_id = ' . $userId)
			->where('(published = 1 OR (published = 2 AND amount = 0))');
		$db->setQuery($query);
		$planIds = $db->loadColumn();

		if (!$planIds)
		{
			return array();
		}

		$activePlanIds = static::getActiveMembershipPlans($userId);

		$query->clear()
			->select('a.*')
			->from('#__osmembership_upgraderules AS a')
			->where('from_plan_id IN (' . implode(',', $planIds) . ')')
			->where('a.published = 1')
			->where('to_plan_id IN (SELECT id FROM #__osmembership_plans WHERE published = 1 AND access IN (' . implode(',', $user->getAuthorisedViewLevels()) . '))')
			->order('from_plan_id')
			->order('id');

		if (count($activePlanIds) > 1)
		{
			$query->where('to_plan_id NOT IN (' . implode(',', $activePlanIds) . ')');
		}

		$db->setQuery($query);
		$rows = $db->loadObjectList();

		foreach ($rows as $row)
		{
			// Adjust the upgrade price if price is pro-rated
			if ($row->upgrade_prorated == 2)
			{
				$row->price -= static::calculateProratedUpgradePrice($row, $userId);
			}
		}

		return $rows;
	}

	/**
	 * Get Ids of the plans which is renewable
	 *
	 * @param  int $userId *
	 *
	 * @return array
	 */
	public static function getRenewOptions($userId)
	{
		$config = OSMembershipHelper::getConfig();
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);

		$activePlanIds = static::getActiveMembershipPlans($userId);

		// Get list of plans which the user has upgraded from
		$query->select('from_plan_id')
			->from('#__osmembership_subscribers AS a')
			->where('a.user_id = ' . $userId)
			->where('a.published IN (1, 2)')
			->where('from_plan_id > 0');
		$db->setQuery($query);
		$upgradedFromPlanIds = $db->loadColumn();

		$query->clear()
			->select('DISTINCT plan_id')
			->from('#__osmembership_subscribers')
			->where('user_id = ' . $userId)
			->where('published IN (1, 2)')
			->where('plan_id > 0');

		if (count($upgradedFromPlanIds))
		{
			$query->where('plan_id NOT IN (' . implode(',', $upgradedFromPlanIds) . ')');
		}

		$db->setQuery($query);
		$planIds = $db->loadColumn();

		$todayDate = JFactory::getDate();

		for ($i = 0, $n = count($planIds); $i < $n; $i++)
		{
			$planId = $planIds[$i];

			$query->clear()
				->select('*')
				->from('#__osmembership_plans')
				->where('id = ' . $planId);
			$db->setQuery($query);
			$row = $db->loadObject();

			if (!$row->enable_renewal)
			{
				unset($planIds[$i]);

				continue;
			}

			// If this is a recurring plan and users still have active subscription, they can't renew
			if ($row->recurring_subscription && in_array($row->id, $activePlanIds))
			{
				unset($planIds[$i]);
				continue;
			}

			if ($config->number_days_before_renewal > 0)
			{
				//Get max date
				$query->clear()
					->select('MAX(to_date)')
					->from('#__osmembership_subscribers')
					->where('user_id=' . (int) $userId . ' AND plan_id=' . $row->id . ' AND (published=1 OR (published = 0 AND payment_method LIKE "os_offline%"))');
				$db->setQuery($query);
				$maxDate = $db->loadResult();

				if ($maxDate)
				{
					$expiredDate = JFactory::getDate($maxDate);
					$diff        = $expiredDate->diff($todayDate);

					if ($diff->days > $config->number_days_before_renewal)
					{
						unset($planIds[$i]);

						continue;
					}
				}
			}
		}

		if (count($planIds))
		{
			$query->clear()
				->select('*')
				->from('#__osmembership_renewrates')
				->where('plan_id IN (' . implode(',', $planIds) . ')')
				->order('plan_id')
				->order('id');
			$db->setQuery($query);
			$rows = $db->loadObjectList();

			$renewOptions = array();
			foreach ($rows as $row)
			{
				$renewalDiscountRule = static::getRenewalDiscount($userId, $row->plan_id);

				if ($renewalDiscountRule)
				{
					if ($renewalDiscountRule->discount_type == 0)
					{
						$row->price = round($row->price * (1 - $renewalDiscountRule->discount_amount / 100), 2);
					}
					else
					{
						$row->price = $row->price - $renewalDiscountRule->discount_amount;
					}

					if ($row->price < 0)
					{
						$row->price = 0;
					}
				}

				$renewOptions[$row->plan_id][] = $row;
			}

			return array(
				$planIds,
				$renewOptions,
			);
		}

		return array(
			array(),
			array(),
		);
	}

	/**
	 * Get max renewal discount rule
	 *
	 * @param $userId
	 * @param $planId
	 *
	 * @return stdClass
	 */
	public static function getRenewalDiscount($userId, $planId)
	{
		static $renewalDiscounts = [];

		if (!isset($renewalDiscounts[$planId]))
		{
			// Initial value
			$renewalDiscounts[$planId] = '';

			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);

			// Get max expiration date
			$query->select('MAX(to_date)')
				->from('#__osmembership_subscribers')
				->where('user_id = ' . $userId)
				->where('plan_id = ' . $planId)
				->where('published = 1');
			$db->setQuery($query);
			$maxDate = $db->loadResult();

			if ($maxDate)
			{
				$todayDate   = JFactory::getDate();
				$expiredDate = JFactory::getDate($maxDate);
				$diff        = $todayDate->diff($expiredDate);

				if ($diff->days > 0)
				{
					// Get the renewal discount object with max discount amount
					$query->clear()
						->select('*')
						->from('#__osmembership_renewaldiscounts')
						->where('plan_id = ' . $planId)
						->where('number_days <= ' . $diff->days)
						->order('discount_amount DESC');
					$db->setQuery($query, 0, 1);
					$renewalDiscounts[$planId] = $db->loadObject();
				}
			}
		}

		return $renewalDiscounts[$planId];
	}

	/**
	 * Get subscriptions information of the current user
	 *
	 * @return array
	 */
	public static function getUserSubscriptionsInfo()
	{
		static $subscriptions;

		if ($subscriptions === null)
		{
			$user = JFactory::getUser();

			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);

			$now    = JFactory::getDate();
			$nowSql = $db->quote($now->toSql());

			$query->select('plan_id, MIN(from_date) AS active_from_date, MAX(DATEDIFF(' . $nowSql . ', from_date)) AS active_in_number_days')
				->from('#__osmembership_subscribers AS a')
				->where('user_id = ' . (int) $user->id)
				->where('DATEDIFF(' . $nowSql . ', from_date) >= 0')
				->where('published IN (1, 2)')
				->group('plan_id');
			$db->setQuery($query);
			$rows = $db->loadObjectList();

			$subscriptions = array();

			foreach ($rows as $row)
			{
				$subscriptions[$row->plan_id] = $row;
			}
		}

		return $subscriptions;
	}

	/**
	 * Get subscription status for a plan of the given user
	 *
	 * @param int $profileId
	 * @param int $planId
	 *
	 * @return int
	 */
	public static function getPlanSubscriptionStatusForUser($profileId, $planId)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('published')
			->from('#__osmembership_subscribers')
			->where('profile_id = ' . $profileId)
			->where('plan_id = ' . $planId)
			->order('to_date');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		$isActive  = false;
		$isPending = false;
		$isExpired = false;

		foreach ($rows as $subscription)
		{

			if ($subscription->published == 1)
			{
				$isActive = true;
			}
			elseif ($subscription->published == 0)
			{
				$isPending = true;
			}
			elseif ($subscription->published == 2)
			{
				$isExpired = true;
			}
		}

		if ($isActive)
		{
			return 1;
		}
		elseif ($isPending)
		{
			return 0;
		}
		elseif ($isExpired)
		{
			return 2;
		}

		return 3;
	}

	/**
	 * Upgrade a membership
	 *
	 * @param OSMembershipTableSubscriber $row
	 */
	public static function processUpgradeMembership($row)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		/* @var OSMembershipTableSubscriber $rowSubscription */
		$rowSubscription = JTable::getInstance('OsMembership', 'Subscriber');

		$query->select('from_plan_id')
			->from('#__osmembership_upgraderules')
			->where('id = ' . $row->upgrade_option_id);
		$db->setQuery($query);
		$planId            = (int) $db->loadResult();
		$row->from_plan_id = $planId;
		$row->store();

		$query->clear()
			->select('id')
			->from('#__osmembership_subscribers')
			->where('plan_id = ' . $planId)
			->where('profile_id = ' . $row->profile_id)
			->where('published = 1');
		$db->setQuery($query);
		$subscriberIds = $db->loadColumn();

		$mainSubscription = null;

		foreach ($subscriberIds as $subscriberId)
		{
			$rowSubscription->load($subscriberId);
			$rowSubscription->to_date              = date('Y-m-d H:i:s');
			$rowSubscription->published            = 2;
			$rowSubscription->first_reminder_sent  = 1;
			$rowSubscription->second_reminder_sent = 1;
			$rowSubscription->third_reminder_sent  = 1;
			$rowSubscription->store();

			if ($rowSubscription->subscription_id && $rowSubscription->payment_method &&
				!$rowSubscription->recurring_subscription_cancelled)
			{
				$mainSubscription = $rowSubscription;
			}

			//Trigger plugins
			JPluginHelper::importPlugin('osmembership');
			JFactory::getApplication()->triggerEvent('onMembershipExpire', array($rowSubscription));
		}

		if ($mainSubscription)
		{
			try
			{
				JLoader::register('OSMembershipModelRegister', JPATH_ROOT . '/components/com_osmembership/model/register.php');

				/**@var OSMembershipModelRegister $model * */
				$model = new OSMembershipModelRegister;
				$model->cancelSubscription($mainSubscription);
			}
			catch (Exception $e)
			{
				// Ignore for now
			}
		}
	}

	/**
	 * Modify subscription duration based on the option which subscriber choose on form
	 *
	 * @param JDate $date
	 * @param array $rowFields
	 * @param array $data
	 */
	public static function modifySubscriptionDuration($date, $rowFields, $data)
	{
		// Check to see whether there are any fields which can modify subscription end date
		for ($i = 0, $n = count($rowFields); $i < $n; $i++)
		{
			$rowField = $rowFields[$i];

			if (!empty($rowField->modify_subscription_duration) && !empty($data[$rowField->name]))
			{
				$durationValues = explode("\r\n", $rowField->modify_subscription_duration);
				$values         = explode("\r\n", $rowField->values);
				$values         = array_map('trim', $values);
				$fieldValue     = $data[$rowField->name];

				$fieldValueIndex = array_search($fieldValue, $values);

				if ($fieldValueIndex !== false && !empty($durationValues[$fieldValueIndex]))
				{
					$modifyDurationString = $durationValues[$fieldValueIndex];

					if (!$date->modify($modifyDurationString))
					{
						JFactory::getApplication()->enqueueMessage(sprintf('Modify duration string %s is invalid', $modifyDurationString), 'warning');
					}
				}
			}
		}
	}

	/**
	 * Get plan which the given user has subscribed for
	 *
	 * @param int $userId
	 *
	 * @return array
	 */
	public static function getSubscribedPlans($userId = 0)
	{
		if ($userId == 0)
		{
			$userId = (int) JFactory::getUser()->get('id');
		}

		if ($userId > 0)
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);

			$query->select('DISTINCT plan_id')
				->from('#__osmembership_subscribers')
				->where('user_id = ' . $userId)
				->where('published IN (1, 2)');
			$db->setQuery($query);

			return $db->loadColumn();
		}

		return array();
	}

	/**
	 * Get subscription from ID
	 *
	 * @param string $subscriptionId
	 *
	 * @return OSMembershipTableSubscriber
	 */
	public static function getSubscription($subscriptionId)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__osmembership_subscribers')
			->where('subscription_id = ' . $db->quote($subscriptionId))
			->order('id');
		$db->setQuery($query);

		return $db->loadObject();
	}

	/**
	 * Calculate prorated upgrade price for an upgrade rule
	 *
	 * @param $row
	 * @param $userId
	 *
	 * @return float|int
	 */
	public static function calculateProratedUpgradePrice($row, $userId)
	{
		$db        = JFactory::getDbo();
		$query     = $db->getQuery(true);
		$todayDate = JFactory::getDate('now');

		$query->select('MAX(to_date)')
			->from('#__osmembership_subscribers')
			->where('published = 1')
			->where('plan_id = ' . (int) $row->from_plan_id)
			->where('user_id = ' . (int) $userId);
		$db->setQuery($query);
		$fromPlanSubscriptionEndDate = $db->loadResult();

		if ($fromPlanSubscriptionEndDate)
		{
			$fromPlanSubscriptionEndDate = JFactory::getDate($fromPlanSubscriptionEndDate);

			if ($fromPlanSubscriptionEndDate > $todayDate)
			{
				$diff = $todayDate->diff($fromPlanSubscriptionEndDate);

				// Get price of the original plan
				$query->clear()
					->select('*')
					->from('#__osmembership_plans')
					->where('id = ' . (int) $row->from_plan_id);
				$db->setQuery($query);
				$fromPlan      = $db->loadObject();
				$fromPlanPrice = $fromPlan->price;

				switch ($fromPlan->subscription_length_unit)
				{
					case 'W':
						$numberDays = $fromPlan->subscription_length * 7;
						break;
					case 'M':
						$numberDays = $fromPlan->subscription_length * 30;
						break;
					case 'Y':
						$numberDays = $fromPlan->subscription_length * 365;
						break;
					default:
						$numberDays = $fromPlan->subscription_length;
						break;
				}

				return $fromPlanPrice * ($diff->days + 1) / $numberDays;
			}
		}

		return 0;
	}

	/**
	 * Get Ids of the plans which current users is not allowed to subscribe because exclusive rule
	 *
	 * @return array
	 */
	public static function getExclusivePlanIds()
	{
		$activePlanIds = OSMembershipHelper::getActiveMembershipPlans();

		if (count($activePlanIds) > 1)
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('a.id')
				->from('#__osmembership_categories AS a')
				->innerJoin('#__osmembership_plans AS b ON a.id = b.category_id')
				->where('a.published = 1')
				->where('a.exclusive_plans = 1')
				->where('b.id IN (' . implode(',', $activePlanIds) . ')');
			$db->setQuery($query);
			$categoryIds = $db->loadColumn();

			if (count($categoryIds))
			{
				$query->clear()
					->select('id')
					->from('#__osmembership_plans')
					->where('category_id IN (' . implode(',', $categoryIds) . ')')
					->where('published = 1');
				$db->setQuery($query);

				return $db->loadColumn();
			}

		}

		return array();
	}

	/**
	 * Cancel recurring subscription
	 *
	 * @param int $id
	 */
	public static function cancelRecurringSubscription($id)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__osmembership_subscribers')
			->where('id = ' . (int) $id);
		$db->setQuery($query);
		$row = $db->loadObject();

		if ($row)
		{
			// The recurring subscription already cancelled before, no need to process it further
			if ($row->recurring_subscription_cancelled)
			{
				return;
			}

			$query->clear()
				->update('#__osmembership_subscribers')
				->set('recurring_subscription_cancelled = 1')
				->where('id = ' . $row->id);
			$db->setQuery($query);
			$db->execute();

			$config = OSMembershipHelper::getConfig();
			OSMembershipHelperMail::sendSubscriptionCancelEmail($row, $config);

			// Mark all reminder emails as sent so that the system won't re-send these emails
			if ($row->user_id > 0 && $row->plan_id > 0)
			{
				$query->clear()
					->update('#__osmembership_subscribers')
					->set('first_reminder_sent = 1')
					->set('second_reminder_sent = 1')
					->set('third_reminder_sent = 1')
					->where('plan_id = ' . (int) $row->plan_id)
					->where('user_id = ' . (int) $row->user_id);
				$db->setQuery($query);
				$db->execute();
			}
		}
	}

	/**
	 * Synchronize profile data for a subscriber
	 *
	 * @param OSMembershipTableSubscriber $row
	 * @param array                       $fields
	 */
	public static function synchronizeProfileData($row, $fields)
	{
		$db         = JFactory::getDbo();
		$query      = $db->getQuery(true);
		$data       = [];
		$fieldNames = [];

		foreach ($fields as $field)
		{
			$fieldNames[] = $field->name;
		}

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

			$rowFields = OSMembershipHelper::getProfileFields($planId);

			for ($i = 0, $n = count($rowFields); $i < $n; $i++)
			{
				$rowField = $rowFields[$i];

				if (!in_array($rowField->name, $fieldNames))
				{
					unset($rowFields[$i]);
					continue;
				}

				if ($rowField->is_core)
				{
					$data[$rowField->name] = $row->{$rowField->name};
					unset($rowFields[$i]);
				}
			}

			// Store core fields data
			foreach ($subscriptionIds as $subscriptionId)
			{
				$rowSubscription = JTable::getInstance('OsMembership', 'Subscriber');
				$rowSubscription->load($subscriptionId);
				$rowSubscription->bind($data);
				$rowSubscription->store();
			}


			reset($rowFields);

			if (count($rowFields))
			{
				$fieldIds = [];

				foreach ($rowFields as $rowField)
				{
					$fieldIds[] = $rowField->id;
				}

				// Delete old data
				$query->clear()
					->delete('#__osmembership_field_value')
					->where('subscriber_id IN (' . implode(',', $subscriptionIds) . ')')
					->where('field_id IN (' . implode(',', $fieldIds) . ')');
				$db->setQuery($query)
					->execute();

				foreach ($subscriptionIds as $subscriptionId)
				{
					$sql = " INSERT INTO #__osmembership_field_value(subscriber_id, field_id, field_value)"
						. " SELECT $subscriptionId, field_id, field_value FROM #__osmembership_field_value WHERE subscriber_id = $row->id AND field_id IN (" . implode(',', $fieldIds) . ")";
					$db->setQuery($sql)
						->execute();
				}
			}
		}
	}

	/**
	 * Method to check and disable free trial for recurring plan if needed
	 *
	 * @param OSMembershipTablePlan $plan
	 *
	 * @return void
	 */
	public static function disableFreeTrialForPlan($plan)
	{
		if (OSMembershipHelper::isMethodOverridden('OSMembershipHelperOverrideSubscription', 'disableFreeTrialForPlan'))
		{
			OSMembershipHelperOverrideSubscription::disableFreeTrialForPlan($plan);

			return;
		}
		// If this is a free trial plan and the current user subscribed for it before, we will disable free trial
		$user = JFactory::getUser();

		if ($user->id && $plan->recurring_subscription && $plan->trial_duration > 0 && $plan->trial_amount == 0)
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('COUNT(*)')
				->from('#__osmembership_subscribers')
				->where('user_id = ' . $user->id)
				->where('plan_id = ' . $plan->id)
				->where('published IN (1,2)');
			$db->setQuery($query);

			if ($count = $db->loadResult())
			{
				$plan->trial_duration = 0;
			}
		}
	}

	/**
	 * Generate member card for the given user
	 *
	 * @param OSMembershipTableSubscriber $item
	 * @param MPFConfig                   $config
	 *
	 * @return string
	 */
	public static function generateMemberCard($item, $config)
	{
		if (OSMembershipHelper::isMethodOverridden('OSMembershipHelperOverrideSubscription', 'generateMemberCard'))
		{
			OSMembershipHelperOverrideSubscription::generateMemberCard($item, $config);

			return;
		}

		require_once JPATH_ROOT . '/components/com_osmembership/tcpdf/tcpdf.php';
		require_once JPATH_ROOT . '/components/com_osmembership/tcpdf/config/lang/eng.php';

		$pdf = new TCPDF($config->get('card_page_orientation', PDF_PAGE_ORIENTATION), PDF_UNIT, $config->get('card_page_format', PDF_PAGE_FORMAT), true, 'UTF-8', false);
		$pdf->SetCreator('Events Booking');
		$pdf->SetAuthor(JFactory::getConfig()->get("sitename"));
		$pdf->SetTitle('Member card');
		$pdf->SetSubject('Member card');
		$pdf->SetKeywords('Member card');
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

		$backgroundImage  = $config->card_bg_image;
		$backgroundLeft   = $config->card_bg_left;
		$backgroundTop    = $config->card_bg_top;
		$backgroundWidth  = $config->card_bg_width;
		$backgroundHeight = $config->card_bg_height;

		$pdf->AddPage();

		if ($backgroundImage)
		{
			// Get current  break margin
			$breakMargin = $pdf->getBreakMargin();
			// get current auto-page-break mode
			$autoPageBreak = $pdf->getAutoPageBreak();
			// disable auto-page-break
			$pdf->SetAutoPageBreak(false, 0);
			// set background image
			$pdf->Image($backgroundImage, $backgroundLeft, $backgroundTop, $backgroundWidth, $backgroundHeight);
			// restore auto-page-break status
			$pdf->SetAutoPageBreak($autoPageBreak, $breakMargin);
			// set the starting point for the page content
			$pdf->setPageMark();
		}

		$replaces = OSMembershipHelper::buildTags($item, $config);

		$subscriptions = static::getSubscriptions($item->profile_id);

		$replaces['subscriptions'] = OSMembershipHelperHtml::loadCommonLayout('emailtemplates/tmpl/subscriptions.php', ['subscriptions' => $subscriptions, 'config' => $config]);
		$replaces['register_date'] = $replaces['created_date'];
		$replaces['name']          = trim($item->first_name . ' ' . $item->last_name);

		// Get latest subscription
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__osmembership_subscribers')
			->where('published IN (1,2)')
			->where('user_id = ' . (int) $item->user_id)
			->order('id DESC');
		$db->setQuery($query);
		$latestSubscription = $db->loadObject();

		if (!$latestSubscription)
		{
			$latestReplaces = $replaces;
		}
		else
		{
			$latestReplaces = OSMembershipHelper::buildTags($latestSubscription, $config);
		}

		$output = $config->card_layout;

		foreach ($replaces as $key => $value)
		{
			$key    = strtoupper($key);
			$output = str_ireplace("[$key]", $value, $output);
		}

		foreach ($latestReplaces as $key => $value)
		{
			$key    = strtoupper('latest_' . $key);
			$output = str_ireplace("[$key]", $value, $output);
		}

		$pdf->writeHTML($output, true, false, false, false, '');

		$filePath = JPATH_ROOT . '/media/com_osmembership/membercards/' . $item->username . '.pdf';

		$pdf->Output($filePath, 'F');

		return $filePath;
	}
}
