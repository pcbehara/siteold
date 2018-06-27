<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */


defined('_JEXEC') or die;

class OSMembershipViewUpgradeMembershipHtml extends MPFViewHtml
{
	public $hasModel = false;

	public function display()
	{
		$this->requestLogin('OSM_LOGIN_TO_UPGRADE_MEMBERSHIP');

		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$config = OSMembershipHelper::getConfig();
		$item   = OSMembershipHelperSubscription::getMembershipProfile($user->id);

		if (!$item)
		{
			// Fix Profile ID
			if (OSMembershipHelperSubscription::fixProfileId($user->id))
			{
				$app->redirect(JUri::getInstance()->toString());
			}
			else
			{
				$app->enqueueMessage(JText::_('OSM_DONOT_HAVE_SUBSCRIPTION_RECORD_TO_UPGRADE'));

				return;
			}
		}

		if ($item->id != $item->profile_id)
		{
			$item->profile_id = $item->id;
			$db               = JFactory::getDbo();
			$query            = $db->getQuery(true);
			$query->update('#__osmembership_subscribers')
				->set('profile_id = ' . $item->id)
				->where('id = ' . $item->id);
			$db->setQuery($query);
			$db->execute();
		}

		if ($item->group_admin_id > 0)
		{
			$app->enqueueMessage(JText::_('OSM_ONLY_GROUP_ADMIN_CAN_UPGRADE_MEMBERSHIP'));

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
		$toPlanId     = $this->input->getInt('to_plan_id');
		$upgradeRules = OSMembershipHelperSubscription::getUpgradeRules($item->user_id);
		$n            = count($upgradeRules);

		if ($toPlanId > 0)
		{
			for ($i = 0; $i < $n; $i++)
			{
				$rule = $upgradeRules[$i];
				if ($rule->to_plan_id != $toPlanId)
				{
					unset($upgradeRules[$i]);
				}
			}

			$upgradeRules = array_values($upgradeRules);
		}

		$this->upgradeRules    = $upgradeRules;
		$this->config          = $config;
		$this->plans           = OSMembershipHelperDatabase::getAllPlans('id');
		$this->bootstrapHelper = new OSMembershipHelperBootstrap($config->twitter_bootstrap_version);

		$this->setLayout('default');

		parent::display();
	}
}
