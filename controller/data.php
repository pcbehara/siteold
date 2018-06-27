<?php
/**
 * @package        Joomla
 * @subpackage     OSMembership
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

trait OSMembershipControllerData
{
	/**
	 * Get profile data of the subscriber, using for json format
	 */
	public function get_profile_data()
	{
		$app    = JFactory::getApplication();
		$config = OSMembershipHelper::getConfig();
		$input  = $app->input;
		$userId = $input->getInt('user_id', 0);
		$planId = $input->getInt('plan_id');
		$data   = array();

		if (OSMembershipHelper::canBrowseUsersList() && $userId && $planId)
		{
			$rowFields = OSMembershipHelper::getProfileFields($planId, true);
			$db        = JFactory::getDbo();
			$query     = $db->getQuery(true);

			$query->select('*')
				->from('#__osmembership_subscribers')
				->where('user_id = ' . $userId)
				->where('plan_id = ' . $planId);
			$db->setQuery($query);
			$rowProfile = $db->loadObject();

			if (!$rowProfile)
			{
				$query->clear()
					->select('*')
					->from('#__osmembership_subscribers')
					->where('user_id=' . $userId . ' AND is_profile=1');
				$db->setQuery($query);
				$rowProfile = $db->loadObject();
			}

			if (!$rowProfile)
			{
				$query->clear()
					->select('*')
					->from('#__osmembership_subscribers')
					->where('user_id = ' . $userId)
					->order('id DESC');
				$db->setQuery($query);
				$rowProfile = $db->loadObject();
			}

			if ($rowProfile)
			{
				$data = OSMembershipHelper::getProfileData($rowProfile, $planId, $rowFields);
			}
			else
			{
				// Trigger plugin to get data
				$mappings = array();

				foreach ($rowFields as $rowField)
				{
					if ($rowField->field_mapping)
					{
						$mappings[$rowField->name] = $rowField->field_mapping;
					}
				}

				JPluginHelper::importPlugin('osmembership');
				$results = JFactory::getApplication()->triggerEvent('onGetProfileData', array($userId, $mappings));

				if (count($results))
				{
					foreach ($results as $res)
					{
						if (is_array($res) && count($res))
						{
							$data = $res;
							break;
						}
					}
				}
			}

			if (!count($data) && JPluginHelper::isEnabled('user', 'profile') && !$config->cb_integration)
			{
				$synchronizer = new MPFSynchronizerJoomla();
				$mappings     = array();

				foreach ($rowFields as $rowField)
				{
					if ($rowField->profile_field_mapping)
					{
						$mappings[$rowField->name] = $rowField->profile_field_mapping;
					}
				}

				$data = $synchronizer->getData($userId, $mappings);
			}
		}

		if ($userId && !isset($data['first_name']))
		{
			//Load the name from Joomla default name
			$user = JFactory::getUser($userId);
			$name = $user->name;

			if ($name)
			{
				$pos = strpos($name, ' ');

				if ($pos !== false)
				{
					$data['first_name'] = substr($name, 0, $pos);
					$data['last_name']  = substr($name, $pos + 1);
				}
				else
				{
					$data['first_name'] = $name;
					$data['last_name']  = '';
				}
			}
		}

		if ($userId && !isset($data['email']))
		{
			$user          = JFactory::getUser($userId);
			$data['email'] = $user->email;
		}

		echo json_encode($data);

		$app->close();
	}
}
