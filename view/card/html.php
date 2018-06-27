<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

class OSMembershipViewCardHtml extends MPFViewHtml
{
	public $hasModel = false;

	public function display()
	{
		// Add necessary javascript files
		OSMembershipHelper::addLangLinkForAjax();
		$document = JFactory::getDocument();
		$rootUri  = JUri::root(true);
		$document->addScript($rootUri . '/media/com_osmembership/assets/js/paymentmethods.min.js');

		$customJSFile = JPATH_ROOT . '/media/com_osmembership/assets/js/custom.js';

		if (file_exists($customJSFile) && filesize($customJSFile) > 0)
		{
			$document->addScript($rootUri . '/media/com_osmembership/assets/js/custom.js');
		}

		$config         = OSMembershipHelper::getConfig();
		$subscriptionId = $this->input->getString('subscription_id');
		$subscription   = OSMembershipHelperSubscription::getSubscription($subscriptionId);

		if (!$subscription)
		{
			throw new Exception(JText::sprintf('Subscription ID %s not found', $subscriptionId));
		}

		if ($subscription->payment_method)
		{
			$method = OSMembershipHelper::loadPaymentMethod($subscription->payment_method);
		}

		// Payment Methods parameters
		$lists['exp_month'] = JHtml::_('select.integerlist', 1, 12, 1, 'exp_month', ' id="exp_month" class="input-small" ', $this->input->get('exp_month', date('m'), 'none'), '%02d');
		$currentYear        = date('Y');
		$lists['exp_year']  = JHtml::_('select.integerlist', $currentYear, $currentYear + 10, 1, 'exp_year', ' id="exp_year" class="input-small" ', $this->input->get('exp_year', date('Y'), 'none'));

		$this->bootstrapHelper = new OSMembershipHelperBootstrap($config->twitter_bootstrap_version);
		$this->lists           = $lists;
		$this->subscription    = $subscription;
		$this->config          = $config;

		parent::display();
	}
}
