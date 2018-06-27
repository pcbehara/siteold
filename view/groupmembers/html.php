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
 * HTML View class for Membership Pro component
 *
 * @static
 * @package        Joomla
 * @subpackage     Membership Pro
 */
class OSMembershipViewGroupmembersHtml extends MPFViewHtml
{
	public function display()
	{
		$canManage = OSMembershipHelper::getManageGroupMemberPermission();

		if (!$canManage)
		{
			$app = JFactory::getApplication();
			$app->enqueueMessage(JText::_('OSM_NOT_ALLOW_TO_MANAGE_GROUP_MEMBERS'));
			$app->redirect(JUri::root(), 403);

			return;
		}

		$fields = OSMembershipHelper::getProfileFields(0, true);

		for ($i = 0, $n = count($fields); $i < $n; $i++)
		{
			if (!$fields[$i]->show_on_members_list)
			{
				unset($fields[$i]);
			}
		}

		/* @var OSMembershipModelGroupmembers $model */
		$model = $this->getModel();

		$this->state      = $model->getState();
		$this->items      = $model->getData();
		$this->fieldsData = $model->getFieldsData();
		$this->config     = OSMembershipHelper::getConfig();
		$this->pagination = $model->getPagination();
		$this->canManage  = $canManage;
		$this->fields     = $fields;

		parent::display();
	}
}
