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

class OSMembershipViewRenewmembershipHtml extends MPFViewHtml
{
	public $hasModel = false;

	public function display()
	{
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		if (!$user->id)
		{
			$return = 'index.php?option=com_osmembership&view=renewmembership&Itemid=' . $this->Itemid;
			$app->redirect('index.php?option=com_users&view=login&return=' . base64_encode($return), JText::_('OSM_LOGIN_TO_RENEW_MEMBERSHIP'));
		}

		$config = OSMembershipHelper::getConfig();
		$item   = OSMembershipHelperSubscription::getMembershipProfile($user->id);
		if (!$item)
		{
			// Try to fix the profile id field
			if (OSMembershipHelperSubscription::fixProfileId($user->id))
			{
				$app->redirect(JUri::getInstance()->toString());
			}
			else
			{
				$app->enqueueMessage(JText::_('OSM_DONOT_HAVE_SUBSCRIPTION_RECORD_TO_RENEW'));

				$return;
			}
		}

		if ($item->id != $item->profile_id)
		{
			$db               = JFactory::getDbo();
			$query            = $db->getQuery(true);
			$item->profile_id = $item->id;
			$query->clear()
				->update('#__osmembership_subscribers')
				->set('profile_id = ' . $item->id)
				->where('id = ' . $item->id);
			$db->setQuery($query);
			$db->execute();
		}

		if ($item->group_admin_id > 0)
		{
			$app->enqueueMessage(JText::_('OSM_ONLY_GROUP_ADMIN_CAN_RENEW_MEMBERSHIP'));

			return;
		}

		list($planIds, $renewOptions) = OSMembershipHelperSubscription::getRenewOptions($user->id);

		if (empty($planIds))
		{
			$app->enqueueMessage(JText::_('OSM_NO_RENEW_OPTIONS_AVAILABLE'));

			return;
		}

		// Load js file to support state field dropdown
		OSMembershipHelper::addLangLinkForAjax();
		JFactory::getDocument()->addScript(JUri::base(true) . '/media/com_osmembership/assets/js/paymentmethods.min.js');

		// Need to get subscriptions information of the user
		$this->planIds         = $planIds;
		$this->renewOptions    = $renewOptions;
		$this->plans           = OSMembershipHelperDatabase::getAllPlans('id');
		$this->config          = $config;
		$this->bootstrapHelper = new OSMembershipHelperBootstrap($config->twitter_bootstrap_version);

		parent::display();
	}
}
