<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
// no direct access
defined('_JEXEC') or die;

class OsMembershipViewCompleteHtml extends MPFViewHtml
{
	public $hasModel = false;

	public function display()
	{
		$app     = JFactory::getApplication();
		$session = JFactory::getSession();

		$db             = JFactory::getDbo();
		$query          = $db->getQuery(true);
		$subscriptionId = $session->get('mp_subscription_id');

		// Get subscriber information
		$query->select('*')
			->from('#__osmembership_subscribers')
			->where('id = ' . (int) $subscriptionId);
		$db->setQuery($query);
		$rowSubscriber = $db->loadObject();

		if (!$rowSubscriber)
		{
			JFactory::getApplication()->redirect('index.php', JText::_('Invalid subscription code'));
		}

		$config      = OSMembershipHelper::getConfig();
		$messageObj  = OSMembershipHelper::getMessages();
		$fieldSuffix = OSMembershipHelper::getFieldSuffix();

		//Get plan information
		$query->clear();
		$query->select('a.*, a.title' . $fieldSuffix . ' AS title')
			->from('#__osmembership_plans AS a')
			->where('id = ' . $rowSubscriber->plan_id);
		$db->setQuery($query);
		$rowPlan = $db->loadObject();

		// Auto Login and auto reload Joomla groups
		if ($rowSubscriber->user_id && $rowSubscriber->published == 1)
		{
			$user = JFactory::getUser();
			if ((!$user->id && $config->auto_login) || ($user->id && $config->auto_reload_user))
			{
				$session->set('user', new JUser($rowSubscriber->user_id));
			}
		}

		// Check and redirect subscriber back to restricted page if needed
		$user = JFactory::getUser();
		if ($user->id)
		{
			$session                = JFactory::getSession();
			$sessionReturnUrl       = $session->get('osm_return_url');
			$sessionRequiredPlanIds = $session->get('required_plan_ids');

			if (!empty($sessionReturnUrl) && is_array($sessionRequiredPlanIds) && in_array($rowSubscriber->plan_id, $sessionRequiredPlanIds))
			{

				// Clear the old session data
				$session->clear('osm_return_url');
				$session->clear('required_plan_ids');
				$app->redirect($sessionReturnUrl);
			}
		}

		// If a custom URL is setup for this plan, we need to redirect to that custom URL
		if ($rowPlan->subscription_complete_url)
		{
			$app->redirect($rowPlan->subscription_complete_url);
		}

		switch ($rowSubscriber->act)
		{
			case 'renew':
				if (strlen(strip_tags($messageObj->{'renew_thanks_message' . $fieldSuffix})))
				{
					$message = $messageObj->{'renew_thanks_message' . $fieldSuffix};
				}
				else
				{
					$message = $messageObj->renew_thanks_message;
				}

				// Use offline payment thank you message if available
				if ($rowSubscriber->payment_method == 'os_offline' && $rowSubscriber->published == 0)
				{
					if (strlen(strip_tags($messageObj->{'renew_thanks_message_offline' . $fieldSuffix})))
					{
						$message = $messageObj->{'renew_thanks_message_offline' . $fieldSuffix};
					}
					elseif (strlen(strip_tags($messageObj->renew_thanks_message_offline)))
					{
						$message = $messageObj->renew_thanks_message_offline;
					}
				}

				if ($rowSubscriber->to_date)
				{
					$toDate = JHtml::_('date', $rowSubscriber->to_date, $config->date_format);
				}
				else
				{
					$toDate = '';
				}
				$message = str_replace('[END_DATE]', $toDate, $message);
				$message = str_replace('[PLAN_TITLE]', $rowPlan->title, $message);
				break;
			case 'upgrade':
				if (strlen(strip_tags($messageObj->{'renew_thanks_message' . $fieldSuffix})))
				{
					$message = $messageObj->{'upgrade_thanks_message' . $fieldSuffix};
				}
				else
				{
					$message = $messageObj->upgrade_thanks_message;
				}
				$query->clear();
				$query->select('c.title')
					->from('#__osmembership_subscribers AS a')
					->innerJoin('#__osmembership_upgraderules AS b ON a.upgrade_option_id=b.id')
					->innerJoin('#__osmembership_plans AS c ON b.from_plan_id = c.id')
					->where('a.id = ' . $rowSubscriber->id);
				$db->setQuery($query);
				$fromPlan = $db->loadResult();
				$message  = str_replace('[PLAN_TITLE]', $fromPlan, $message);
				$message  = str_replace('[TO_PLAN_TITLE]', $rowPlan->title, $message);
				break;
			default:
				if ($rowSubscriber->payment_method == 'os_offline' && $rowSubscriber->published == 0)
				{
					if (strlen(strip_tags($rowPlan->{'thanks_message_offline' . $fieldSuffix})))
					{
						$message = $rowPlan->{'thanks_message_offline' . $fieldSuffix};
					}
					elseif (strlen(strip_tags($messageObj->{'thanks_message_offline' . $fieldSuffix})))
					{
						$message = $messageObj->{'thanks_message_offline' . $fieldSuffix};
					}
					else
					{
						$message = $messageObj->thanks_message_offline;
					}
				}
				else
				{
					if (strlen(strip_tags($rowPlan->{'thanks_message' . $fieldSuffix})))
					{
						$message = $rowPlan->{'thanks_message' . $fieldSuffix};
					}
					elseif (strlen(strip_tags($messageObj->{'thanks_message' . $fieldSuffix})))
					{
						$message = $messageObj->{'thanks_message' . $fieldSuffix};
					}
					else
					{
						$message = $messageObj->thanks_message;
					}
				}
				$message = str_replace('[PLAN_TITLE]', $rowPlan->title, $message);
				break;
		}

		$subscriptionDetail = OSMembershipHelper::getEmailContent($config, $rowSubscriber);
		$message            = str_replace('[SUBSCRIPTION_DETAIL]', $subscriptionDetail, $message);
		$replaces           = OSMembershipHelper::buildTags($rowSubscriber, $config);

		$replaces['plan_title'] = $rowPlan->title;
		foreach ($replaces as $key => $value)
		{
			$key     = strtoupper($key);
			$message = str_replace("[$key]", $value, $message);
		}
		$this->message                = $message;
		$this->conversionTrackingCode = $config->conversion_tracking_code;

		// Lee custom
		if($rowSubscriber->plan_id == 8)
		{
			$this->message_new = JText::_('OSM_SUBSCRIPTION_OVERRIDE_REGISTRATION_COMPLETE');
		}
		elseif($rowSubscriber->plan_id == 7)
		{
			$this->message_new = JText::_('OSM_SUBSCRIPTION_OVERRIDE_CLOUDPAYMENT_COMPLETE');	
		}
		else
		{
			$this->message_new = JText::_('OSM_SUBSCRIPTION_OVERRIDE_BANKTRANSFERS_COMPLETE');	
		}
		// End	


		$this->setLayout('default');

		parent::display();
	}
}
