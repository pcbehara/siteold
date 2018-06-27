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
class OSMembershipViewMemberHtml extends MPFViewHtml
{
	public function display()
	{
		if (!JFactory::getUser()->authorise('core.viewmembers', 'com_osmembership'))
		{
			$app = JFactory::getApplication();
			$app->enqueueMessage(JText::_('OSM_NOT_ALLOW_TO_VIEW_MEMBERS'));
			$app->redirect(JUri::root(), 403);
		}

		/* @var OSMembershipModelMember $model */
		$model = $this->getModel();
		$item  = $model->getData();
		$state = $model->getState();

		if (!$item)
		{
			throw new Exception(sprintf('Member ID %d does not exist in the system', $state->get('id')));
		}

		$fields = OSMembershipHelper::getProfileFields($item->plan_id, true);

		for ($i = 0, $n = count($fields); $i < $n; $i++)
		{
			if (!$fields[$i]->show_on_profile || in_array($fields[$i]->name, ['first_name', 'last_name']))
			{
				unset($fields[$i]);
			}
		}

		$fields = array_values($fields);

		$this->item   = $item;
		$this->state  = $state;
		$this->fields = $fields;
		$this->data   = OSMembershipHelper::getProfileData($item, $item->plan_id, $fields);
		$this->config = OSMembershipHelper::getConfig();
		$this->params = JFactory::getApplication()->getParams();

		// Force to use default layout
		$this->setLayout('default');

		parent::display();
	}
}
