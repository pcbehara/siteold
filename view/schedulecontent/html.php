<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die;

class OSMembershipViewSchedulecontentHtml extends MPFViewHtml
{
	public function display()
	{
		$app = JFactory::getApplication();

		if (!JPluginHelper::isEnabled('system', 'schedulecontent'))
		{
			$app->enqueueMessage(JText::_('Schedule Content feature is not enabled. Please contact super administrator'));

			return;
		}

		if (!JFactory::getUser()->get('id'))
		{
			$returnUrl = JRoute::_('index.php?option=com_osmembership&view=schedulecontent&Itemid=' . $this->Itemid);
			$url       = JRoute::_('index.php?option=com_users&view=login&return=' . base64_encode($returnUrl), false);
			$app->redirect($url, JText::_('OSM_PLEASE_LOGIN'));
		}

		/* @var $model OSmembershipModelSchedulecontent */
		$model               = $this->getModel();
		$this->items         = $model->getData();
		$this->config        = OSMembershipHelper::getConfig();
		$this->pagination    = $model->getPagination();
		$this->subscriptions = OSMembershipHelperSubscription::getUserSubscriptionsInfo();

		parent::display();
	}
}
