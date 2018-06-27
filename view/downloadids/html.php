<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

/**
 * Class OSMembershipViewDownloadidsHtml
 *
 * @property OSMembershipModelDownloadids $model
 */
class OSMembershipViewDownloadidsHtml extends MPFViewList
{
	public function display()
	{
		$user  = JFactory::getUser();
		$db    = $this->getModel()->getDbo();
		$query = $db->getQuery(true);

		// Check to see whether the current user has any valid order, if not, just display a warning
		$query->select('COUNT(*)')
			->from('#__osmembership_subscribers')
			->where('published IN (1, 2)')
			->where('(user_id = ' . $user->id . ' OR email = ' . $db->quote($user->email) . ')');
		$db->setQuery($query);
		$total = (int) $db->loadResult();

		if (!$total)
		{
			echo '<p class="text-info">' . JText::_('OSM_NO_DOWNLOAD_IDS') . '</p>';

			return;
		}

		// If current user does not have any Download IDs yet, generate one for him
		$query->clear()
			->select('COUNT(*)')
			->from('#__osmembership_downloadids')
			->where('user_id = ' . $user->id);
		$db->setQuery($query);
		$total = $db->loadResult();

		if (!$total)
		{
			$this->model->generateDownloadIds();
		}

		$this->config  = OSMembershipHelper::getConfig();
		$this->message = OSMembershipHelper::getMessages();

		parent::display();
	}
}