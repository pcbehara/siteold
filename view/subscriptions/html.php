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

class OSMembershipViewSubscriptionsHtml extends MPFViewHtml
{
	public function display()
	{
		if (!JFactory::getUser()->get('id'))
		{
			$returnUrl = JRoute::_('index.php?option=com_osmembership&view=subscriptions&Itemid=' . $this->Itemid);
			$url       = JRoute::_('index.php?option=com_users&view=login&return=' . base64_encode($returnUrl), false);
			JFactory::getApplication()->redirect($url, JText::_('OSM_PLEASE_LOGIN'));
		}

		$model            = $this->getModel();
		$this->items      = $model->getData();
		$this->config     = OSMembershipHelper::getConfig();
		$this->pagination = $model->getPagination();

		parent::display();
	}
}
