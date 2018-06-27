<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

class os_offline extends MPFPayment
{
	/**
	 * Process payment
	 */
	public function processPayment($row, $data)
	{
		$app    = JFactory::getApplication();
		$Itemid = $app->input->getInt('Itemid');

		$subscriptionStatus = $this->params->get('subscription_status');

		if ($subscriptionStatus == 1)
		{
			$this->onPaymentSuccess($row, $row->transaction_id);
		}
		else
		{
			$config = OSMembershipHelper::getConfig();
			OSMembershipHelper::sendEmails($row, $config);
		}

		$app->redirect(JRoute::_('index.php?option=com_osmembership&view=complete&Itemid=' . $Itemid, false));
	}
}
