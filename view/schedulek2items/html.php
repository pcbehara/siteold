<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */


defined('_JEXEC') or die;

class OSMembershipViewScheduleK2itemsHtml extends MPFViewHtml
{
	public function display()
	{

		if (!JPluginHelper::isEnabled('system', 'schedulek2items'))
		{
			JFactory::getApplication()->enqueueMessage(JText::_('Schedule K2 Items feature is not enabled. Please contact super administrator'));

			return;
		}

		$this->requestLogin();

		/* @var $model OSMembershipModelScheduleK2items */
		$model               = $this->getModel();
		$this->items         = $model->getData();
		$this->pagination    = $model->getPagination();
		$this->config        = OSMembershipHelper::getConfig();
		$this->subscriptions = OSMembershipHelperSubscription::getUserSubscriptionsInfo();

		parent::display();
	}
}
