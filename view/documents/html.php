<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */


defined('_JEXEC') or die;

class OSMembershipViewDocumentsHtml extends MPFViewHtml
{
	public function display()
	{
		$app = JFactory::getApplication();

		if (!JPluginHelper::isEnabled('osmembership', 'documents'))
		{
			$app->enqueueMessage(JText::_('Memebership Pro Documents plugin is not enabled. Please contact super administrator'));

			return;
		}

		// Make sure users are logged in before allow them to access
		$this->requestLogin();

		/* @var $model OSmembershipModelDocuments */
		$model               = $this->getModel();
		$this->items         = $model->getData();
		$this->pagination    = $model->getPagination();
		$this->documentsPath = OSMembershipHelper::getDocumentsPath();

		parent::display();
	}
}
