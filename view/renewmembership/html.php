<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */


defined('_JEXEC') or die;

class OSMembershipViewRenewmembershipHtml extends MPFViewHtml
{
	public $hasModel = false;

	public function display()
	{
		$this->requestLogin('OSM_LOGIN_TO_RENEW_MEMBERSHIP');

		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
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

				return;
			}
		}

		if ($item->id != $item->profile_id)
		{
			$db               = $this->model->getDbo();
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
		$document = JFactory::getDocument();
		$rootUri  = JUri::root(true);
		$document->addScript($rootUri . '/media/com_osmembership/assets/js/paymentmethods.min.js');

		$customJSFile = JPATH_ROOT . '/media/com_osmembership/assets/js/custom.js';

		if (file_exists($customJSFile) && filesize($customJSFile) > 0)
		{
			$document->addScript($rootUri . '/media/com_osmembership/assets/js/custom.js');
		}

		// Need to get subscriptions information of the user
		$this->planIds         = $planIds;
		$this->renewOptions    = $renewOptions;
		$this->plans           = OSMembershipHelperDatabase::getAllPlans('id');
		$this->config          = $config;
		$this->bootstrapHelper = new OSMembershipHelperBootstrap($config->twitter_bootstrap_version);

		$this->setLayout('default');

		parent::display();
	}
}
