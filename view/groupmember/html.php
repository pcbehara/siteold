<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 Ossolution Team
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
class OSMembershipViewGroupmemberHtml extends MPFViewHtml
{
	public function display()
	{
		// Check permission
		$addMemberPlanIds = array();
		$canManage        = OSMembershipHelper::getManageGroupMemberPermission($addMemberPlanIds);
		$item             = $this->model->getData();

		// Check add/edit group member permission
		$canAccess = false;

		if (($item->id && $canManage >= 1) || ($canManage == 2))
		{
			$canAccess = true;
		}

		if (!$canAccess)
		{
			$app = JFactory::getApplication();
			$app->enqueueMessage(JText::_('OSM_NOT_ALLOW_TO_MANAGE_GROUP_MEMBERS'));
			$app->redirect(JUri::root(), 403);
		}

		$db                      = JFactory::getDbo();
		$query                   = $db->getQuery(true);
		$config                  = OSMembershipHelper::getConfig();
		$this->showExistingUsers = false;

		if (count($addMemberPlanIds) == 1 || $item->id)
		{
			if ($item->id)
			{
				$planId = $item->plan_id;
			}
			else
			{
				$planId = (int) $addMemberPlanIds[0];
			}

			$query->select('id, title')
				->from('#__osmembership_plans')
				->where('id = ' . (int) $planId);
			$db->setQuery($query);
			$this->plan = $db->loadObject();
		}
		else
		{
			// List of existing plans
			$query->select('id, title')
				->from('#__osmembership_plans')
				->where('published = 1')
				->where('id  IN (' . implode(',', $addMemberPlanIds) . ')')
				->order('ordering');
			$db->setQuery($query);
			$options          = array();
			$options[]        = JHtml::_('select.option', '', JText::_('OSM_SELECT_PLAN'), 'id', 'title');
			$options          = array_merge($options, $db->loadObjectList());
			$lists['plan_id'] = JHtml::_('select.genericlist', $options, 'plan_id', ' class="inputbox validate[required]" ', 'id', 'title', $item->plan_id);

			$this->lists = $lists;
		}

		OSMembershipHelper::addLangLinkForAjax();
		$document = JFactory::getDocument();
		$rootUri  = JUri::root(true);
		$document->addScript($rootUri . '/media/com_osmembership/assets/js/paymentmethods.min.js');

		$customJSFile = JPATH_ROOT . '/media/com_osmembership/assets/js/custom.js';

		if (file_exists($customJSFile) && filesize($customJSFile) > 0)
		{
			$document->addScript($rootUri . '/media/com_osmembership/assets/js/custom.js');
		}

		if ($item->id)
		{
			$memberPlanId = $item->plan_id;
		}
		elseif (isset($planId))
		{
			$memberPlanId = $planId;
		}
		else
		{
			$memberPlanId = 0;
		}

		$rowFields = OSMembershipHelper::getProfileFields($memberPlanId, true);
		$data      = array();

		if ($item->id)
		{
			$data       = OSMembershipHelper::getProfileData($item, $item->plan_id, $rowFields);
			$setDefault = false;
		}
		else
		{
			$populateFields = array();

			foreach ($rowFields as $rowField)
			{
				if ($rowField->populate_from_group_admin)
				{
					$populateFields[] = $rowField;
				}
			}

			if (count($populateFields))
			{
				$userId = JFactory::getUser()->get('id');
				$planId = (int) $addMemberPlanIds[0];
				$query->clear()
					->select('*')
					->from('#__osmembership_subscribers')
					->where('user_id = ' . $userId)
					->where('plan_id = ' . $planId)
					->order('id');
				$db->setQuery($query);
				$groupAdminSubscription = $db->loadObject();

				$data = OSMembershipHelper::getProfileData($groupAdminSubscription, $planId, $populateFields);
			}

			$setDefault = true;
		}

		if (!isset($data['country']))
		{
			$data['country'] = $config->default_country;
		}

		// Form
		$form = new MPFForm($rowFields);
		$form->setData($data)->bindData($setDefault);

		$this->item            = $item;
		$this->form            = $form;
		$this->bootstrapHelper = new OSMembershipHelperBootstrap($config->twitter_bootstrap_version);
		$this->config          = $config;

		parent::display();
	}
}
