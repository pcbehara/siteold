<?php
/**
 * @package        Joomla
 * @subpackage     OSMembership
 * @author         Lee L - www.jumazi.com
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

class OSMembershipModelBuymembership extends MPFModel
{
	/**
	 * Get Buymembership data of the users
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
	 * Buymembership profile of the user
	 *
	 * @param array    $data
	 * @param MPFInput $input
	 *
	 * @return boolean
	 */
	public function BuymembershipBuymembership($data, $input)
	{
		$db  = $this->getDbo();
		$row = $this->getTable('Subscriber');
		$row->load($data['id']);
		$query    = $db->getQuery(true);
	
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
		//Trigger event	onProfileBuymembership event
		JPluginHelper::importPlugin('osmembership');
		$dispatcher = JEventDispatcher::getInstance();
		$dispatcher->trigger('onProfileBuymembership', array($row));

		OSMembershipHelper::sendProfileBuymembershipEmail($row, $config);

		return true;
	}

}
