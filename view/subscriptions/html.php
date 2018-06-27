<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */


defined('_JEXEC') or die;

class OSMembershipViewSubscriptionsHtml extends MPFViewHtml
{
	public function display()
	{
		$this->requestLogin();

		/* @var OSMembershipModelSubscriptions $model */
		$model            = $this->getModel();
		$this->items      = $model->getData();
		$this->config     = OSMembershipHelper::getConfig();
		$this->pagination = $model->getPagination();

		parent::display();
	}
}
