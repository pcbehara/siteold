<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die;

class OSMembershipModelProfile extends MPFModel
{
	/**
	 * Get profile data of the users
	 */
	public function getData()
	{
		$user  = JFactory::getUser();
		$db    = $this->getDbo();
		$query = $db->getQuery(true);
		$query->select('a.*, b.username')
			->from('#__osmembership_subscribers AS a ')
			->leftJoin('#__users AS b ON a.user_id=b.id')
			->where('is_profile=1')
			->where("(a.email='$user->email' OR user_id=$user->id)")
			->order('id DESC');

		$db->setQuery($query);

		return $db->loadObject();
	}

	/**
	 * Update profile of the user
	 *
	 * @param array    $data
	 * @param MPFInput $input
	 *
	 * @return boolean
	 */
	public function updateProfile($data, $input)
	{
		$db  = $this->getDbo();
		$row = $this->getTable('Subscriber');
		$row->load($data['id']);
		$query    = $db->getQuery(true);
		$userData = array();
		$query->select('COUNT(*)')
			->from('#__users')
			->where('email=' . $db->quote($data['email']))
			->where('id!=' . $row->user_id);
		$db->setQuery($query);
		$total = $db->loadResult();

		// Upload avatar image
		$avatar = $input->files->get('profile_avatar');
		if ($avatar['name'])
		{
			$fileExt        = JString::strtoupper(JFile::getExt($avatar['name']));
			$supportedTypes = array('JPG', 'PNG', 'GIF');
			if (!in_array($fileExt, $supportedTypes))
			{
				throw new \RuntimeException(JText::_('OSM_INVALID_AVATAR'));
			}

			$imageSizeData = getimagesize($avatar['tmp_name']);
			if ($imageSizeData === false)
			{
				throw new \RuntimeException(JText::_('OSM_INVALID_AVATAR'));
			}

			// Avatar is valid, upload and resize
			$fileName   = $row->user_id . '.' . JString::strtoupper(JFile::getExt($avatar['name']));
			$avatarPath = JPATH_ROOT . '/media/com_osmembership/avatars/' . $fileName;
			JFile::upload($avatar['tmp_name'], $avatarPath);

			$config = OSMembershipHelper::getConfig();
			$image = new JImage($avatarPath);
			$width  = $config->avatar_width ? $config->avatar_width : 80;
			$height = $config->avatar_height ? $config->avatar_height: 80;
			$image->cropResize($width, $height, false)
				->toFile($avatarPath);

			$data['avatar'] = $fileName;
		}

		if (!$total)
		{
			$userData['email'] = $data['email'];
		}
		if ($data['password'])
		{
			$userData['password2'] = $userData['password'] = $data['password'];
		}
		if (count($userData))
		{
			$user = JFactory::getUser($row->user_id);
			$user->bind($userData);
			$user->save(true);
		}

		if (!$row->bind($data))
		{
			$this->setError($db->getErrorMsg());

			return false;
		}
		if (!$row->check())
		{
			$this->setError($db->getErrorMsg());

			return false;
		}
		if (!$row->store())
		{
			$this->setError($db->getErrorMsg());

			return false;
		}

		//Store custom field data for this profile record

		if (OSMembershipHelper::isUniquePlan($user->id))
		{
			$planId = $row->plan_id;
		}
		else
		{
			$planId = 0;
		}

		$rowFields = OSMembershipHelper::getProfileFields($planId, false);
		$form      = new MPFForm($rowFields);
		$form->storeData($row->id, $data, true);

		//Synchronize profile data of other subscription records from this subscriber
		$config = OSMembershipHelper::getConfig();
		if ($config->synchronize_data !== '0')
		{
			OSMembershipHelper::syncronizeProfileData($row, $data);
		}
		//Trigger event	onProfileUpdate event
		JPluginHelper::importPlugin('osmembership');
		$dispatcher = JEventDispatcher::getInstance();
		$dispatcher->trigger('onProfileUpdate', array($row));

		OSMembershipHelper::sendProfileUpdateEmail($row, $config);

		return true;
	}

}
