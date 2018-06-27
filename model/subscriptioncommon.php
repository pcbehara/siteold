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

trait OSMembershipModelSubscriptioncommon
{
	/**
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
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->clear()
				->update('#__osmembership_subscribers')
				->set('avatar = ' . $db->quote($fileName))
				->where('user_id = ' . $row->user_id);
			$db->setQuery($query);
			$db->execute();
		}

		$row->avatar = $fileName;
	}
}