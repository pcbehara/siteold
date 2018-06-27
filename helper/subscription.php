<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
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

				if ($subscription->subscription_id && !$subscription->recurring_subscription_cancelled && in_array($subscription->payment_method, array('os_authnet', 'os_stripe', 'os_paypal_pro')))
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
	 * @return array
	 */
	public static function getUpgradeRules()
	{
		$user   = JFactory::getUser();
		$userId = (int) $user->get('id');

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
			->order('from_plan_id');

		if (count($activePlanIds) > 1)
		{
			$query->where('to_plan_id NOT IN (' . implode(',', $activePlanIds) . ')');
		}

		$db->setQuery($query);

		return $db->loadObjectList();
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

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

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

			$query->select('plan_id, MIN(from_date) AS active_from_date, MIN(DATEDIFF(' . $nowSql . ', from_date)) AS active_in_number_days')
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

		foreach ($subscriberIds as $subscriberId)
		{
			$rowSubscription->load($subscriberId);
			$rowSubscription->to_date   = date('Y-m-d H:i:s');
			$rowSubscription->published = 2;
			$rowSubscription->store();

			//Trigger plugins
			JPluginHelper::importPlugin('osmembership');
			$dispatcher = JEventDispatcher::getInstance();
			$dispatcher->trigger('onMembershipExpire', array($rowSubscription));
		}
	}
}
