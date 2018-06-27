<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die;

use Joomla\String\StringHelper;

trait OSMembershipModelSubscriptiontrait
{
	/**
	 * Method to create user account based on given data. Account will be enabled automatically
	 *
	 * This is called while creating subscription record from administrator area (import subscription or creating
	 * new subscription record)
	 *
	 * @param $data
	 *
	 * @return int
	 *
	 * @throws Exception
	 */
	protected function createUserAccount($data)
	{
		//Store this account into the system and get the username
		jimport('joomla.user.helper');
		$params      = JComponentHelper::getParams('com_users');
		$newUserType = $params->get('new_usertype', 2);

		$data['groups']    = [];
		$data['groups'][]  = $newUserType;
		$data['block']     = 0;
		$data['name']      = rtrim($data['first_name'] . ' ' . $data['last_name']);
		$data['password1'] = $data['password2'] = $data['password'];
		$data['email1']    = $data['email2'] = $data['email'];
		$user              = new JUser;
		$user->bind($data);

		if (!$user->save())
		{
			throw new Exception($user->getError());
		}

		return $user->id;
	}

	/**
	 * Process upload avatar
	 *
	 * @param array                       $avatar
	 * @param OSMembershipTableSubscriber $row
	 *
	 * @return void
	 */
	protected function uploadAvatar($avatar, $row)
	{
		$config   = OSMembershipHelper::getConfig();
		$fileName = JFile::makeSafe($avatar['name']);
		$fileExt  = StringHelper::strtoupper(JFile::getExt($fileName));

		if (JFile::exists(JPATH_ROOT . '/media/com_osmembership/avatars/' . $fileName) && $fileName != $row->avatar)
		{
			$fileName = uniqid('avatar_') . $fileName;
		}

		$avatarPath = JPATH_ROOT . '/media/com_osmembership/avatars/' . $fileName;

		if ($fileExt == 'PNG')
		{
			$imageType = IMAGETYPE_PNG;
		}
		elseif ($fileExt == 'GIF')
		{
			$imageType = IMAGETYPE_GIF;
		}
		elseif (in_array($fileExt, ['JPG', 'JPEG']))
		{
			$imageType = IMAGETYPE_JPEG;
		}
		else
		{
			$imageType = '';
		}

		$image  = new JImage($avatar['tmp_name']);
		$width  = $config->avatar_width ? $config->avatar_width : 80;
		$height = $config->avatar_height ? $config->avatar_height : 80;
		$image->cropResize($width, $height, false)
			->toFile($avatarPath, $imageType);

		// Update avatar of existing subscription records from this user
		if ($row->user_id > 0)
		{
			/* @var JDatabaseDriver $db */
			$db    = $this->getDbo();
			$query = $db->getQuery(true);
			$query->update('#__osmembership_subscribers')
				->set('avatar = ' . $db->quote($fileName))
				->where('user_id = ' . $row->user_id);
			$db->setQuery($query);
			$db->execute();
		}

		$row->avatar = $fileName;
	}

	/**
	 * Get custom fields for the subscription
	 *
	 * @param int    $planId
	 * @param bool   $loadCoreFields
	 * @param string $language
	 * @param string $action
	 *
	 * @return array
	 */
	protected function getFields($planId, $loadCoreFields = true, $language = null, $action = null)
	{
		$rowFields  = OSMembershipHelper::getProfileFields($planId, $loadCoreFields, $language, $action);
		$formFields = [];

		// Remove message and heating custom fields type as it is not needed for calculation and storing data
		for ($i = 0, $n = count($rowFields); $i < $n; $i++)
		{
			$rowField = $rowFields[$i];

			if (in_array($rowField->fieldtype, ['Heading', 'Message']))
			{
				unset($rowFields[$i]);

				continue;
			}

			if (!$rowField->is_core)
			{
				$formFields[] = $rowField;
			}
		}

		reset($rowFields);

		return [$rowFields, $formFields];
	}

	/**
	 * Method to calculate subscription from date
	 *
	 * @param   OSMembershipTableSubscriber $row
	 * @param   OSMembershipTablePlan       $rowPlan
	 *
	 * @return  \Joomla\CMS\Date\Date
	 */
	protected function calculateSubscriptionFromDate($row, $rowPlan)
	{
		$maxDate = null;

		if ($row->user_id > 0 && !$rowPlan->lifetime_membership)
		{
			/* @var JDatabaseDriver $db */
			$db    = $this->getDbo();
			$query = $db->getQuery(true);
			$query->select('MAX(to_date)')
				->from('#__osmembership_subscribers')
				->where('user_id = ' . $row->user_id)
				->where('plan_id = ' . $row->plan_id)
				->where('published = 1');
			$db->setQuery($query);
			$maxDate = $db->loadResult();
		}

		if ($maxDate)
		{
			$date = JFactory::getDate($maxDate);
			$date->add(new DateInterval('PT1S'));
			$row->from_date = $date->toSql();
		}
		else
		{
			$date           = JFactory::getDate();
			$row->from_date = $date->toSql();
		}

		return $date;
	}

	/**
	 * Method to calculate subscription from date
	 *
	 * @param   OSMembershipTableSubscriber $row
	 * @param   OSMembershipTablePlan       $rowPlan
	 * @param   \Joomla\CMS\Date\Date       $date
	 * @param   array                       $rowFields
	 * @param   array                       $data
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	protected function calculateSubscriptionEndDate($row, $rowPlan, $date, $rowFields, $data)
	{
		/* @var JDatabaseDriver $db */
		$db = $this->getDbo();

		// In case plan is a lifetime membership, the subscription will be lifetime subscription
		if ($rowPlan->lifetime_membership)
		{
			$row->to_date = '2099-12-31 23:59:59';

			return;
		}

		// In case plan has fixed expiration date, call a separate method to calculate the date
		if ($rowPlan->expired_date && $rowPlan->expired_date != $db->getNullDate())
		{
			$this->calculateSubscriptionFixedExpirationDate($row, $rowPlan, $date);

			return;
		}

		// Handle the case the upgrade rule requires keep original subscription duration
		if ($row->act == 'upgrade')
		{
			$upgradeRule = OSMembershipHelperDatabase::getUpgradeRule($row->upgrade_option_id);

			if ($upgradeRule->upgrade_prorated == 3)
			{
				$query = $db->getQuery(true);
				$query->select('*')
					->from('#__osmembership_subscribers')
					->where('user_id = ' . $row->user_id)
					->where('plan_id = ' . $upgradeRule->from_plan_id)
					->where('published = 1')
					->order('to_date DESC');
				$db->setQuery($query);
				$fromSubscription = $db->loadObject();

				if ($fromSubscription)
				{
					$row->from_date = $fromSubscription->from_date;
					$row->to_date   = $fromSubscription->to_date;

					return;
				}
			}
		}


		list($dateIntervalSpec, $upgradeProratedInterval) = $this->calculateDateModify($row, $rowPlan);

		$date->add(new DateInterval($dateIntervalSpec));

		if (!empty($upgradeProratedInterval))
		{
			$date->add($upgradeProratedInterval);
			$date->modify('+1 day');
		}

		$this->modifySubscriptionDuration($date, $rowFields, $data);

		$row->to_date = $date->toSql();
	}

	/**
	 * Method to calculate subscription end date in case plan is a fixed expiration date plan
	 *
	 * @param   OSMembershipTableSubscriber $row
	 * @param   OSMembershipTablePlan       $rowPlan
	 * @param   \Joomla\CMS\Date\Date       $date
	 *
	 * @return  void
	 */
	protected function calculateSubscriptionFixedExpirationDate($row, $rowPlan, $date)
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

		if ($row->act == 'renew')
		{
			if ($row->renew_option_id == 0 || ($row->renew_option_id == OSM_DEFAULT_RENEW_OPTION_ID))
			{
				if ($rowPlan->subscription_length_unit == 'Y')
				{
					$numberYears = $rowPlan->subscription_length;
				}
			}
			else
			{
				/* @var JDatabaseDriver $db */
				$db    = $this->getDbo();
				$query = $db->getQuery(true);
				$query->select('*')
					->from('#__osmembership_renewrates')
					->where('id = ' . $row->renew_option_id);
				$db->setQuery($query);
				$renewOption = $db->loadObject();

				if ($renewOption->renew_option_length_unit == 'Y' && $renewOption->renew_option_length > 1)
				{
					$numberYears = $renewOption->renew_option_length;
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

		$expiredDate->setDate((int) $expiredDate->year + $numberYears - 1, $expiredDate->month, $expiredDate->day);

		$row->to_date = $expiredDate->toSql();
	}

	/**
	 * Calculate date modify string for subscription end date
	 *
	 * @param OSMembershipTableSubscriber $row
	 * @param OSMembershipTablePlan       $rowPlan
	 *
	 * @return array
	 */
	protected function calculateDateModify($row, $rowPlan)
	{
		/* @var JDatabaseDriver $db */
		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		$upgradeProratedInterval = '';

		if ($row->act == 'renew')
		{
			$renewOptionId = (int) $row->renew_option_id;

			if ($renewOptionId == 0 || $renewOptionId == OSM_DEFAULT_RENEW_OPTION_ID)
			{
				$dateIntervalSpec = 'P' . $rowPlan->subscription_length . $rowPlan->subscription_length_unit;
			}
			else
			{
				$query->select('*')
					->from('#__osmembership_renewrates')
					->where('id = ' . $renewOptionId);
				$db->setQuery($query);
				$renewOption      = $db->loadObject();
				$dateIntervalSpec = 'P' . $renewOption->renew_option_length . $renewOption->renew_option_length_unit;
			}
		}
		elseif ($row->act == 'upgrade')
		{
			$dateIntervalSpec = 'P' . $rowPlan->subscription_length . $rowPlan->subscription_length_unit;
			$query->select('*')
				->from('#__osmembership_upgraderules')
				->where('id = ' . $row->upgrade_option_id);
			$db->setQuery($query);
			$upgradeOption = $db->loadObject();

			if ($upgradeOption->upgrade_prorated == 1)
			{
				// Check to see how many days left from his current plan subscription
				$query->clear()
					->select('MAX(to_date)')
					->from('#__osmembership_subscribers')
					->where('published = 1')
					->where('plan_id = ' . $upgradeOption->from_plan_id)
					->where('user_id = ' . $row->user_id);
				$db->setQuery($query);
				$fromPlanSubscriptionEndDate = $db->loadResult();

				if ($fromPlanSubscriptionEndDate)
				{
					$fromPlanSubscriptionEndDate = JFactory::getDate($fromPlanSubscriptionEndDate);
					$todayDate                   = JFactory::getDate('now');

					if ($fromPlanSubscriptionEndDate > $todayDate)
					{
						$upgradeProratedInterval = $todayDate->diff($fromPlanSubscriptionEndDate);
					}
				}
			}
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

		return array($dateIntervalSpec, $upgradeProratedInterval);
	}

	/**
	 * Modify subscription duration based on the option which subscriber choose on form
	 *
	 * @param   \Joomla\CMS\Date\Date $date
	 * @param   array                 $rowFields
	 * @param   array                 $data
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	protected function modifySubscriptionDuration($date, $rowFields, $data)
	{
		// Check to see whether there are any fields which can modify subscription end date
		for ($i = 0, $n = count($rowFields); $i < $n; $i++)
		{
			$rowField = $rowFields[$i];

			if (empty($rowField->modify_subscription_duration) || empty($data[$rowField->name]))
			{
				continue;
			}

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