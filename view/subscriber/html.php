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
class OSMembershipViewSubscriberHtml extends MPFViewItem
{
	protected $hideButtons = array('save2new', 'save2copy');

	protected function prepareView()
	{
		parent::prepareView();

		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);
		$item   = $this->item;
		$lists  = &$this->lists;
		$config = OSMembershipHelper::getConfig();

		if (!$item->id)
		{
			$item->plan_id = $this->input->getInt('plan_id', 0);
		}

		$query->select('id, title')
			->from('#__osmembership_plans')
			->where('published = 1')
			->order('ordering');

		$db->setQuery($query);
		$options          = array();
		$options[]        = JHtml::_('select.option', '', JText::_('OSM_SELECT_PLAN'), 'id', 'title');
		$options          = array_merge($options, $db->loadObjectList());
		$lists['plan_id'] = JHtml::_('select.genericlist', $options, 'plan_id', ' class="validate[required]" ', 'id', 'title', $item->plan_id);

		//Subscription status
		$options            = array();		
		$options[]          = JHtml::_('select.option', 0, JText::_('OSM_PENDING'));
		$options[]          = JHtml::_('select.option', 1, JText::_('OSM_ACTIVE'));
		$options[]          = JHtml::_('select.option', 2, JText::_('OSM_EXPIRED'));
		$options[]          = JHtml::_('select.option', 3, JText::_('OSM_CANCELLED_PENDING'));
		$options[]          = JHtml::_('select.option', 4, JText::_('OSM_CANCELLED_REFUNDED'));
		$lists['published'] = JHtml::_('select.genericlist', $options, 'published', ' class="inputbox" ', 'value', 'text', $item->published);

		//Get list of payment methods
		$query->clear();
		$query->select('name, title')
			->from('#__osmembership_plugins')
			->where('published = 1')
			->order('ordering');

		$db->setQuery($query);
		$options                 = array();
		$options[]               = JHtml::_('select.option', '', JText::_('OSM_PAYMENT_METHOD'), 'name', 'title');
		$options                 = array_merge($options, $db->loadObjectList());
		$lists['payment_method'] = JHtml::_('select.genericlist', $options, 'payment_method', ' class="inputbox" ', 'name', 'title',
			$item->payment_method);
		$rowFields               = OSMembershipHelper::getProfileFields($item->plan_id, true, $item->language);
		$data                    = array();
		if ($item->id)
		{
			$data       = OSMembershipHelper::getProfileData($item, $item->plan_id, $rowFields);
			$setDefault = false;
		}
		else
		{
			$setDefault = true;
		}
		if (!isset($data['country']) || !$data['country'])
		{
			$data['country'] = $config->default_country;
		}
		$form = new MPFForm($rowFields);
		$form->setData($data)->bindData($setDefault);
		$form->buildFieldsDependency();

		//Custom fields processing goes here
		if ($item->plan_id)
		{
			$query->clear();
			$query->select('lifetime_membership')
				->from('#__osmembership_plans')
				->where('id = ' . (int) $item->plan_id);
			$db->setQuery($query);
			$item->lifetime_membership = (int) $db->loadResult();
		}
		else
		{
			$item->lifetime_membership = 0;
		}

		// Convert dates from UTC to user timezone
		if ($item->id)
		{
			$item->created_date = JHtml::_('date', $item->created_date, 'Y-m-d H:i:s');
			$item->from_date    = JHtml::_('date', $item->from_date, 'Y-m-d H:i:s');
			$item->to_date      = JHtml::_('date', $item->to_date, 'Y-m-d H:i:s');
		}

		OSMembershipHelper::addLangLinkForAjax($item->language);

		$this->config = $config;
		$this->form   = $form;

		$this->addToolbar();
	}

	/**
	 * Add Toolbar
	 */
	protected function addToolbar()
	{
		JLoader::register('JToolbarHelper', JPATH_ADMINISTRATOR . '/includes/toolbar.php');

		parent::addToolbar();
	}
}
