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
class OSMembershipViewMembersHtml extends MPFViewHtml
{
	public function display()
	{
		if (!JFactory::getUser()->authorise('core.viewmembers', 'com_osmembership'))
		{
			$app = JFactory::getApplication();
			$app->enqueueMessage(JText::_('OSM_NOT_ALLOW_TO_VIEW_MEMBERS'));
			$app->redirect(JUri::root(), 403);
		}

		/* @var OSMembershipModelMembers $model */
		$model  = $this->getModel();
		$state  = $model->getState();
		$fields = OSMembershipHelper::getProfileFields($state->id, true);

		for ($i = 0, $n = count($fields); $i < $n; $i++)
		{
			if (!$fields[$i]->show_on_members_list)
			{
				unset($fields[$i]);
			}
		}

		$fields = array_values($fields);

		$this->fields     = $fields;
		$this->state      = $state;
		$this->items      = $model->getData();
		$this->pagination = $model->getPagination();
		$this->fieldsData = $model->getFieldsData();
		$this->config     = OSMembershipHelper::getConfig();
		$this->params     = JFactory::getApplication()->getParams();

		parent::display();
	}
}
