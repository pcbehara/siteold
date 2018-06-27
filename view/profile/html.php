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

class OSMembershipViewProfileHtml extends MPFViewHtml
{
	public function display()
	{
		$user = JFactory::getUser();
		if (!$user->id)
		{
			$return = 'index.php?option=com_osmembership&view=profile&Itemid=' . $this->Itemid;
			JFactory::getApplication()->redirect('index.php?option=com_users&view=login&return=' . base64_encode($return), JText::_('OSM_LOGIN_TO_EDIT_PROFILE'));
		}

		$config = OSMembershipHelper::getConfig();
		$item = OSMembershipHelperSubscription::getMembershipProfile($user->id);
		if (!$item)
		{
			if (OSMembershipHelperSubscription::fixProfileId($user->id))
			{
				// Redirect to current page after fixing the data
				JFactory::getApplication()->redirect(JUri::getInstance()->toString());
			}
			else
			{
				$redirectURL = OSMembershipHelper::getViewUrl(array('categories', 'plans', 'plan', 'register'));
				if (!$redirectURL)
				{
					$redirectURL = 'index.php';
				}
				JFactory::getApplication()->redirect($redirectURL, JText::_('OSM_DONOT_HAVE_SUBSCRIPTION_RECORD'));
			}
		}

		// Fix wrong data for profile record
		if ($item->id != $item->profile_id)
		{
			$db               = JFactory::getDbo();
			$query            = $db->getQuery(true);
			$item->profile_id = $item->id;
			$query->update('#__osmembership_subscribers')
				->set('profile_id = ' . $item->id)
				->where('id = ' . $item->id);
			$db->setQuery($query);
			$db->execute();
		}

		// Get subscriptions history
		require_once JPATH_ROOT . '/components/com_osmembership/model/subscriptions.php';
		$model = JModelLegacy::getInstance('Subscriptions', 'OSMembershipModel');
		$items = $model->getData();

		if (OSMembershipHelper::isUniquePlan($item->user_id))
		{
			$planId = $item->plan_id;
		}
		else
		{
			$planId = 0;
		}

		// Form
		$rowFields = OSMembershipHelper::getProfileFields($planId);
		$data      = OSMembershipHelper::getProfileData($item, $planId, $rowFields);
		$form      = new MPFForm($rowFields);
		$form->setData($data)->bindData();
		$form->buildFieldsDependency();

		// Trigger third party add-on
		JPluginHelper::importPlugin('osmembership');
		$dispatcher = JEventDispatcher::getInstance();
		$results    = $dispatcher->trigger('onProfileDisplay', array($item));

		if ($item->group_admin_id == 0)
		{
			list($planIds, $renewOptions) = OSMembershipHelperSubscription::getRenewOptions($user->id);

			$this->upgradeRules = OSMembershipHelperSubscription::getUpgradeRules($item->user_id);
			$this->planIds      = $planIds;
			$this->renewOptions = $renewOptions;
			$this->plans        = OSMembershipHelperDatabase::getAllPlans('id');
		}

		// Load js file to support state field dropdown
		OSMembershipHelper::addLangLinkForAjax();
		JFactory::getDocument()->addScript(JUri::base(true) . '/media/com_osmembership/assets/js/paymentmethods.min.js');

		// Need to get subscriptions information of the user
		$this->item            = $item;
		$this->config          = $config;
		$this->items           = $items;
		$this->form            = $form;
		$this->plugins         = $results;
		$this->subscriptions   = OSMembershipHelper::getSubscriptions($item->profile_id);
		$this->bootstrapHelper = new OSMembershipHelperBootstrap($config->twitter_bootstrap_version);

		parent::display();
	}
}
