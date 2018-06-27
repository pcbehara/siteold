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
 * HTML View class for OS Membership component
 *
 * @static
 * @package        Joomla
 * @subpackage     Membership Pro
 */
class OSMembershipViewPlanHtml extends MPFViewHtml
{
	public function display()
	{
		$app  = JFactory::getApplication();
		$item = $this->getModel()->getData();

		if (!$item->id)
		{
			$app->enqueueMessage(JText::_('OSM_INVALID_SUBSCRIPTION_PLAN'));
			$app->redirect(JUri::root(), 404);
		}

		if (!in_array($item->access, JFactory::getUser()->getAuthorisedViewLevels()))
		{
			$app->enqueueMessage(JText::_('OSM_NOT_ALLOWED_PLAN'));
			$app->redirect(JUri::root(), 403);
		}

		$taxRate = OSMembershipHelper::calculateTaxRate($item->id);
		$config  = OSMembershipHelper::getConfig();

		if ($config->show_price_including_tax && $taxRate > 0)
		{
			$item->price        = $item->price * (1 + $taxRate / 100);
			$item->trial_amount = $item->trial_amount * (1 + $taxRate / 100);
			$item->setup_fee    = $item->setup_fee * (1 + $taxRate / 100);
		}
		$item->short_description = JHtml::_('content.prepare', $item->short_description);
		$item->description       = JHtml::_('content.prepare', $item->description);

		// Process page title and meta data
		$active = $app->getMenu()->getActive();
		$params = OSMembershipHelper::getViewParams($active, array('plan'));
		$params->def('page_heading', $item->page_heading ?: $item->title);
		$params->def('page_title', $item->page_title ?: $item->title);
		$params->def('menu-meta_keywords', $item->meta_keywords);
		$params->def('menu-meta_description', $item->meta_description);

		if ($active)
		{
			$this->setDocumentMetadata($params);
		}

		$this->item            = $item;
		$this->config          = $config;
		$this->bootstrapHelper = new OSMembershipHelperBootstrap($config->twitter_bootstrap_version);
		$this->params          = $params;
		$this->setLayout('default');

		parent::display();
	}
}
