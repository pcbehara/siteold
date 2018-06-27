<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die;

class OSMembershipViewSubscriptioncancelHtml extends MPFViewHtml
{
	public $hasModel = false;

	public function display()
	{
		$this->setLayout('default');
		$db             = JFactory::getDbo();
		$query          = $db->getQuery(true);
		$subscriptionId = (int) JFactory::getSession()->get('mp_subscription_id');
		$query->select('*')
			->from('#__osmembership_subscribers')
			->where('id = ' . $subscriptionId);
		$db->setQuery($query);
		$rowSubscriber = $db->loadObject();

		if (!$rowSubscriber)
		{
			$app = JFactory::getApplication();
			$app->enqueueMessage(JText::_('Invalid subscription code'));
			$app->redirect(JUri::root(), 404);
		}

		$messageObj  = OSMembershipHelper::getMessages();
		$fieldSuffix = OSMembershipHelper::getFieldSuffix();
		if (strlen(strip_tags($messageObj->{'recurring_subscription_cancel_message' . $fieldSuffix})))
		{
			$message = $messageObj->{'recurring_subscription_cancel_message' . $fieldSuffix};
		}
		else
		{
			$message = $messageObj->recurring_subscription_cancel_message;
		}

		// Get plan title
		$query->clear();
		$query->select('a.*, a.title' . $fieldSuffix . ' AS title')
			->from('#__osmembership_plans AS a')
			->where('id = ' . $rowSubscriber->plan_id);
		$db->setQuery($query);
		$rowPlan = $db->loadObject();
		$message = str_replace('[PLAN_TITLE]', $rowPlan->title, $message);

		// Get latest subscription end date
		$query->clear();
		$query->select('MAX(to_date)')
			->from('#__osmembership_subscribers')
			->where('user_id = ' . $rowSubscriber->user_id)
			->where('plan_id = ' . $rowSubscriber->plan_id);
		$db->setQuery($query);
		$subscriptionEndDate = $db->loadResult();
		if (!$subscriptionEndDate)
		{
			$subscriptionEndDate = date(OSMembershipHelper::getConfigValue('date_format'));
		}
		$message       = str_replace('[SUBSCRIPTION_END_DATE]', $subscriptionEndDate, $message);
		$this->message = $message;

		parent::display();
	}
}
