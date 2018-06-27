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

class OSMembershipHelperMail
{
	/**
	 * Send email to super administrator and user
	 *
	 * @param OSMembershipTableSubscriber $row
	 * @param object                      $config
	 */
	public static function sendEmails($row, $config)
	{
		$db          = JFactory::getDbo();
		$query       = $db->getQuery(true);
		$fieldSuffix = OSMembershipHelper::getFieldSuffix($row->language);

		$query->select('*')
			->from('#__osmembership_plans')
			->where('id = ' . $row->plan_id);

		if ($fieldSuffix)
		{
			OSMembershipHelperDatabase::getMultilingualFields($query, array('title'), $fieldSuffix);
		}

		$db->setQuery($query);
		$plan = $db->loadObject();

		if ($plan->notification_emails)
		{
			$config->notification_emails = $plan->notification_emails;
		}

		$mailer = static::getMailer($config);

		$message = OSMembershipHelper::getMessages();

		if ($row->act == 'upgrade')
		{
			static::sendMembershipUpgradeEmails($mailer, $row, $plan, $config, $message, $fieldSuffix);

			return;
		}

		if ($row->act == 'renew')
		{
			static::sendMembershipRenewalEmails($mailer, $row, $plan, $config, $message, $fieldSuffix);

			return;
		}

		$rowFields              = OSMembershipHelper::getProfileFields($row->plan_id);
		$emailContent           = OSMembershipHelper::getEmailContent($config, $row);
		$replaces               = OSMembershipHelper::buildTags($row, $config);
		$replaces['plan_title'] = $plan->title;

		// Get the message from the plan if needed
		if ($plan->{'user_email_subject' . $fieldSuffix})
		{
			$message->{'user_email_subject' . $fieldSuffix} = $plan->{'user_email_subject' . $fieldSuffix};
		}

		if (strlen(strip_tags($plan->{'user_email_body' . $fieldSuffix})))
		{
			$message->{'user_email_body' . $fieldSuffix} = $plan->{'user_email_body' . $fieldSuffix};
		}

		if (strlen(strip_tags($plan->{'user_email_body_offline' . $fieldSuffix})))
		{
			$message->{'user_email_body_offline' . $fieldSuffix} = $plan->{'user_email_body_offline' . $fieldSuffix};
		}

		if (strlen($message->{'user_email_subject' . $fieldSuffix}))
		{
			$subject = $message->{'user_email_subject' . $fieldSuffix};
		}
		else
		{
			$subject = $message->user_email_subject;
		}

		if ($row->payment_method == 'os_offline' && $row->published == 0)
		{
			if (strlen(strip_tags($message->{'user_email_body_offline' . $fieldSuffix})))
			{
				$body = $message->{'user_email_body_offline' . $fieldSuffix};
			}
			else
			{
				$body = $message->user_email_body_offline;
			}
		}
		else
		{
			if (strlen(strip_tags($message->{'user_email_body' . $fieldSuffix})))
			{
				$body = $message->{'user_email_body' . $fieldSuffix};
			}
			else
			{
				$body = $message->user_email_body;
			}
		}

		$subject = str_replace('[PLAN_TITLE]', $plan->title, $subject);
		$body    = str_replace('[SUBSCRIPTION_DETAIL]', $emailContent, $body);
		$body    = str_replace('[SUBSCRIPTION_DETAIL_NEW]', $emailContent, $body);


		foreach ($replaces as $key => $value)
		{
			$key     = strtoupper($key);
			$subject = str_ireplace("[$key]", $value, $subject);
			$body    = str_ireplace("[$key]", $value, $body);
		}

		if ($config->activate_invoice_feature && $config->send_invoice_to_customer && OSMembershipHelper::needToCreateInvoice($row))
		{
			if (!$row->invoice_number)
			{
				$row->invoice_number = OSMembershipHelper::getInvoiceNumber($row);
				$row->store();
			}
			OSMembershipHelper::generateInvoicePDF($row);
			$mailer->addAttachment(JPATH_ROOT . '/media/com_osmembership/invoices/' . OSMembershipHelper::formatInvoiceNumber($row, $config) . '.pdf');
		}

		if (JMailHelper::isEmailAddress($row->email))
		{
			static::send($mailer, array($row->email), $subject, $body);
			$mailer->clearAllRecipients();
		}

		if (!$config->send_invoice_to_admin)
		{
			$mailer->clearAttachments();
		}

		if (!$config->disable_notification_to_admin)
		{
			$emails = explode(',', $config->notification_emails);

			if (strlen($message->{'admin_email_subject' . $fieldSuffix}))
			{
				$subject = $message->{'admin_email_subject' . $fieldSuffix};
			}
			else
			{
				$subject = $message->admin_email_subject;
			}
			$subject = str_replace('[PLAN_TITLE]', $plan->title, $subject);

			if (strlen(strip_tags($message->{'admin_email_body' . $fieldSuffix})))
			{
				$body = $message->{'admin_email_body' . $fieldSuffix};
			}
			else
			{
				$body = $message->admin_email_body;
			}

			$emailContent = OSMembershipHelper::getEmailContent($config, $row, true);

			$body = str_replace('[SUBSCRIPTION_DETAIL]', $emailContent, $body);
			$body = str_replace('[SUBSCRIPTION_DETAIL_NEW]', $emailContent, $body);

			foreach ($replaces as $key => $value)
			{
				$key     = strtoupper($key);
				$subject = str_ireplace("[$key]", $value, $subject);
				$body    = str_ireplace("[$key]", $value, $body);
			}

			if ($config->send_attachments_to_admin)
			{
				self::addAttachments($mailer, $rowFields, $replaces);
			}

			static::send($mailer, $emails, $subject, $body);
		}

		//After sending email, we can empty the user_password of subscription was activated
		if ($row->published == 1 && $row->user_password)
		{
			$query->clear()
				->update('#__osmembership_subscribers')
				->set('user_password = ""')
				->where('id = ' . $row->id);
			$db->setQuery($query);
			$db->execute();
		}
	}

	/**
	 * Send email when subscriber upgrade their membership
	 *
	 * @param JMail                       $mailer
	 * @param OSMembershipTableSubscriber $row
	 * @param stdClass                    $plan
	 * @param stdClass                    $config
	 * @param stdClass                    $message
	 * @param string                      $fieldSuffix
	 */
	public static function sendMembershipRenewalEmails($mailer, $row, $plan, $config, $message, $fieldSuffix)
	{
		if ($row->renew_option_id == OSM_DEFAULT_RENEW_OPTION_ID)
		{
			$numberDays = $plan->subscription_length;
		}
		else
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('number_days')
				->from('#__osmembership_renewrates')
				->where('id = ' . $row->renew_option_id);
			$db->setQuery($query);
			$numberDays = $db->loadResult();
		}

		// Get list of fields
		$rowFields = OSMembershipHelper::getProfileFields($row->plan_id);

		$emailContent            = OSMembershipHelper::getEmailContent($config, $row);
		$replaces                = OSMembershipHelper::buildTags($row, $config);
		$replaces['plan_title']  = $plan->title;
		$replaces['number_days'] = $numberDays;

		// Use plan messages if needed
		if (strlen($plan->{'user_renew_email_subject' . $fieldSuffix}))
		{
			$message->{'user_renew_email_subject' . $fieldSuffix} = $plan->{'user_renew_email_subject' . $fieldSuffix};
		}

		if (strlen(strip_tags($plan->{'user_renew_email_body' . $fieldSuffix})))
		{
			$message->{'user_renew_email_body' . $fieldSuffix} = $plan->{'user_renew_email_body' . $fieldSuffix};
		}

		if (strlen($message->{'user_renew_email_subject' . $fieldSuffix}))
		{
			$subject = $message->{'user_renew_email_subject' . $fieldSuffix};
		}
		else
		{
			$subject = $message->user_renew_email_subject;
		}

		$subject = str_replace('[PLAN_TITLE]', $plan->title, $subject);

		if (strlen(strip_tags($message->{'user_renew_email_body' . $fieldSuffix})))
		{
			$body = $message->{'user_renew_email_body' . $fieldSuffix};
		}
		else
		{
			$body = $message->user_renew_email_body;
		}

		// Use offline payment email message if available
		if ($row->payment_method == 'os_offline' && $row->published == 0)
		{
			if (strlen(strip_tags($message->{'renew_thanks_message_offline' . $fieldSuffix})))
			{
				$body = $message->{'renew_thanks_message_offline' . $fieldSuffix};
			}
			elseif (strlen(strip_tags($message->renew_thanks_message_offline)))
			{
				$body = $message->renew_thanks_message_offline;
			}
		}

		$body = str_replace('[SUBSCRIPTION_DETAIL]', $emailContent, $body);
		$body    = str_replace('[SUBSCRIPTION_DETAIL_NEW]', $emailContent, $body);

		foreach ($replaces as $key => $value)
		{
			$key     = strtoupper($key);
			$subject = str_ireplace("[$key]", $value, $subject);
			$body    = str_ireplace("[$key]", $value, $body);
		}

		if ($config->activate_invoice_feature && $config->send_invoice_to_customer && OSMembershipHelper::needToCreateInvoice($row))
		{
			if (!$row->invoice_number)
			{
				$row->invoice_number = OSMembershipHelper::getInvoiceNumber($row);
				$row->store();
			}
			OSMembershipHelper::generateInvoicePDF($row);
			$mailer->addAttachment(JPATH_ROOT . '/media/com_osmembership/invoices/' . OSMembershipHelper::formatInvoiceNumber($row, $config) . '.pdf');
		}

		if (JMailHelper::isEmailAddress($row->email))
		{
			static::send($mailer, array($row->email), $subject, $body);
			$mailer->clearAllRecipients();
		}

		if (!$config->send_invoice_to_admin)
		{
			$mailer->clearAttachments();
		}

		if (!$config->disable_notification_to_admin)
		{
			$emails = explode(',', $config->notification_emails);

			if (strlen($message->{'admin_renw_email_subject' . $fieldSuffix}))
			{
				$subject = $message->{'admin_renw_email_subject' . $fieldSuffix};
			}
			else
			{
				$subject = $message->admin_renw_email_subject;
			}
			$subject = str_replace('[PLAN_TITLE]', $plan->title, $subject);

			if (strlen(strip_tags($message->{'admin_renew_email_body' . $fieldSuffix})))
			{
				$body = $message->{'admin_renew_email_body' . $fieldSuffix};
			}
			else
			{
				$body = $message->admin_renew_email_body;
			}

			if ($row->payment_method == 'os_creditcard')
			{
				$emailContent = OSMembershipHelper::getEmailContent($config, $row, true);
			}
			$body = str_replace('[SUBSCRIPTION_DETAIL]', $emailContent, $body);
			$body    = str_replace('[SUBSCRIPTION_DETAIL_NEW]', $emailContent, $body);
			foreach ($replaces as $key => $value)
			{
				$key     = strtoupper($key);
				$subject = str_ireplace("[$key]", $value, $subject);
				$body    = str_ireplace("[$key]", $value, $body);
			}

			//We will need to get attachment data here
			if ($config->send_attachments_to_admin)
			{
				static::addAttachments($mailer, $rowFields, $replaces);
			}

			static::send($mailer, $emails, $subject, $body);
		}
	}

	/**
	 * Send email when someone upgrade their membership
	 *
	 * @param JMail                       $mailer
	 * @param OSMembershipTableSubscriber $row
	 * @param stdClass                    $plan
	 * @param stdClass                    $config
	 * @param stdClass                    $message
	 * @param string                      $fieldSuffix
	 */
	public static function sendMembershipUpgradeEmails($mailer, $row, $plan, $config, $message, $fieldSuffix)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		//Get from plan title
		$query->select('b.title' . $fieldSuffix . ' AS title')
			->from('#__osmembership_upgraderules AS a')
			->innerJoin('#__osmembership_plans AS b ON a.from_plan_id = b.id')
			->where('a.id = ' . $row->upgrade_option_id);
		$db->setQuery($query);
		$planTitle = $db->loadResult();

		$rowFields = OSMembershipHelper::getProfileFields($row->plan_id);

		$emailContent              = OSMembershipHelper::getEmailContent($config, $row);
		$replaces                  = OSMembershipHelper::buildTags($row, $config);
		$replaces['plan_title']    = $planTitle;
		$replaces['to_plan_title'] = $plan->title;

		if (strlen($message->{'user_upgrade_email_subject' . $fieldSuffix}))
		{
			$subject = $message->{'user_upgrade_email_subject' . $fieldSuffix};
		}
		else
		{
			$subject = $message->user_upgrade_email_subject;
		}
		$subject = str_replace('[TO_PLAN_TITLE]', $plan->title, $subject);
		$subject = str_replace('[PLAN_TITLE]', $planTitle, $subject);
		if (strlen(strip_tags($message->{'user_upgrade_email_body' . $fieldSuffix})))
		{
			$body = $message->{'user_upgrade_email_body' . $fieldSuffix};
		}
		else
		{
			$body = $message->user_upgrade_email_body;
		}
		$body = str_replace('[SUBSCRIPTION_DETAIL]', $emailContent, $body);
		$body    = str_replace('[SUBSCRIPTION_DETAIL_NEW]', $emailContent, $body);
		foreach ($replaces as $key => $value)
		{
			$key     = strtoupper($key);
			$subject = str_ireplace("[$key]", $value, $subject);
			$body    = str_ireplace("[$key]", $value, $body);
		}
		$attachment = null;
		if ($config->activate_invoice_feature && $config->send_invoice_to_customer && OSMembershipHelper::needToCreateInvoice($row))
		{
			if (!$row->invoice_number)
			{
				$row->invoice_number = OSMembershipHelper::getInvoiceNumber($row);
				$row->store();
			}
			OSMembershipHelper::generateInvoicePDF($row);
			$mailer->addAttachment(JPATH_ROOT . '/media/com_osmembership/invoices/' . OSMembershipHelper::formatInvoiceNumber($row, $config) . '.pdf');
		}

		if (JMailHelper::isEmailAddress($row->email))
		{
			static::send($mailer, array($row->email), $subject, $body);
			$mailer->clearAllRecipients();
		}

		if (!$config->send_invoice_to_admin)
		{
			$mailer->clearAttachments();
		}

		//Send emails to notification emails
		if (!$config->disable_notification_to_admin)
		{
			$emails = explode(',', $config->notification_emails);

			if (strlen($message->{'admin_upgrade_email_subject' . $fieldSuffix}))
			{
				$subject = $message->{'admin_upgrade_email_subject' . $fieldSuffix};
			}
			else
			{
				$subject = $message->admin_upgrade_email_subject;
			}
			$subject = str_replace('[TO_PLAN_TITLE]', $plan->title, $subject);
			$subject = str_replace('[PLAN_TITLE]', $planTitle, $subject);
			if (strlen(strip_tags($message->{'admin_upgrade_email_body' . $fieldSuffix})))
			{
				$body = $message->{'admin_upgrade_email_body' . $fieldSuffix};
			}
			else
			{
				$body = $message->admin_upgrade_email_body;
			}
			if ($row->payment_method == 'os_creditcard')
			{
				$emailContent = OSMembershipHelper::getEmailContent($config, $row, true);
			}
			$body = str_replace('[SUBSCRIPTION_DETAIL]', $emailContent, $body);
			$body    = str_replace('[SUBSCRIPTION_DETAIL_NEW]', $emailContent, $body);
			foreach ($replaces as $key => $value)
			{
				$key     = strtoupper($key);
				$subject = str_ireplace("[$key]", $value, $subject);
				$body    = str_ireplace("[$key]", $value, $body);
			}

			// Add attachments which subscriber upload to notification emails
			if ($config->send_attachments_to_admin)
			{
				static::addAttachments($mailer, $rowFields, $replaces);
			}

			static::send($mailer, $emails, $subject, $body);
		}
	}

	/**
	 * Send email to subscriber to inform them that their membership approved (and activated)
	 *
	 * @param object $row
	 */
	public static function sendMembershipApprovedEmail($row)
	{
		// Load frontend language file
		if ($row->language && $row->language != '*')
		{
			$lang = JFactory::getLanguage();
			$lang->load('com_osmembership', JPATH_ROOT, $row->language);
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$config = OSMembershipHelper::getConfig();

		$mailer = static::getMailer($config);

		$message     = OSMembershipHelper::getMessages();
		$fieldSuffix = OSMembershipHelper::getFieldSuffix($row->language);

		$query->select('*')
			->from('#__osmembership_plans')
			->where('id = ' . $row->plan_id);

		if ($fieldSuffix)
		{
			OSMembershipHelperDatabase::getMultilingualFields($query, array('title'), $fieldSuffix);
		}

		$db->setQuery($query);
		$plan = $db->loadObject();

		$emailContent           = OSMembershipHelper::getEmailContent($config, $row);
		$replaces               = OSMembershipHelper::buildTags($row, $config);
		$replaces['plan_title'] = $plan->title;

		// Override messages from plan settings if needed
		if (strlen($plan->{'subscription_approved_email_subject' . $fieldSuffix}))
		{
			$message->{'subscription_approved_email_subject' . $fieldSuffix} = $plan->{'subscription_approved_email_subject' . $fieldSuffix};
		}

		if (strlen(strip_tags($plan->{'subscription_approved_email_body' . $fieldSuffix})))
		{
			$message->{'subscription_approved_email_body' . $fieldSuffix} = $plan->{'subscription_approved_email_body' . $fieldSuffix};
		}

		if (strlen($message->{'subscription_approved_email_subject' . $fieldSuffix}))
		{
			$subject = $message->{'subscription_approved_email_subject' . $fieldSuffix};
		}
		else
		{
			$subject = $message->subscription_approved_email_subject;
		}

		$subject = str_replace('[PLAN_TITLE]', $plan->title, $subject);
		if (strlen(strip_tags($message->{'subscription_approved_email_body' . $fieldSuffix})))
		{
			$body = $message->{'subscription_approved_email_body' . $fieldSuffix};
		}
		else
		{
			$body = $message->subscription_approved_email_body;
		}
		$body = str_replace('[SUBSCRIPTION_DETAIL]', $emailContent, $body);
		$body    = str_replace('[SUBSCRIPTION_DETAIL_NEW]', $emailContent, $body);
		foreach ($replaces as $key => $value)
		{
			$key     = strtoupper($key);
			$subject = str_ireplace("[$key]", $value, $subject);
			$body    = str_ireplace("[$key]", $value, $body);
		}

		if (JMailHelper::isEmailAddress($row->email))
		{
			static::send($mailer, array($row->email), $subject, $body);
		}

		if ($row->published == 1 && $row->user_password)
		{
			$query->clear()
				->update('#__osmembership_subscribers')
				->set('user_password = ""')
				->where('id = ' . $row->id);
			$db->setQuery($query);
			$db->execute();
		}
	}

	/**
	 * Send confirmation email to subscriber and notification email to admin when a recurring subscription cancelled
	 *
	 * @param $row
	 * @param $config
	 */
	public static function sendSubscriptionCancelEmail($row, $config)
	{
		// Load the frontend language file with subscription record language
		$lang = JFactory::getLanguage();
		$tag  = $row->language;
		if (!$tag)
		{
			$tag = 'en-GB';
		}
		$lang->load('com_osmembership', JPATH_ROOT, $tag);

		$message     = OSMembershipHelper::getMessages();
		$fieldSuffix = OSMembershipHelper::getFieldSuffix($row->language);

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->select('*')
			->from('#__osmembership_plans')
			->where('id = ' . $row->plan_id);

		if ($fieldSuffix)
		{
			OSMembershipHelperDatabase::getMultilingualFields($query, array('title'), $fieldSuffix);
		}

		$db->setQuery($query);
		$plan = $db->loadObject();

		if ($plan->notification_emails)
		{
			$config->notification_emails = $plan->notification_emails;
		}

		$mailer = static::getMailer($config);

		$replaces['plan_title'] = $plan->title;
		$replaces['first_name'] = $row->first_name;
		$replaces['last_name']  = $row->last_name;
		$replaces['email']      = $row->email;

		// Get latest subscription end date
		$query->clear();
		$query->select('MAX(to_date)')
			->from('#__osmembership_subscribers')
			->where('user_id = ' . $row->user_id)
			->where('plan_id = ' . $row->plan_id);
		$db->setQuery($query);
		$subscriptionEndDate = $db->loadResult();
		if (!$subscriptionEndDate)
		{
			$subscriptionEndDate = date($config->date_format);
		}
		$replaces['SUBSCRIPTION_END_DATE'] = $subscriptionEndDate;

		// Send confirmation email to subscribers
		if (strlen($message->{'user_recurring_subscription_cancel_subject' . $fieldSuffix}))
		{
			$subject = $message->{'user_recurring_subscription_cancel_subject' . $fieldSuffix};
		}
		else
		{
			$subject = $message->user_recurring_subscription_cancel_subject;
		}

		if (strlen(strip_tags($message->{'user_recurring_subscription_cancel_body' . $fieldSuffix})))
		{
			$body = $message->{'user_recurring_subscription_cancel_body' . $fieldSuffix};
		}
		else
		{
			$body = $message->user_recurring_subscription_cancel_body;
		}

		$subject = str_replace('[PLAN_TITLE]', $plan->title, $subject);
		foreach ($replaces as $key => $value)
		{
			$key     = strtoupper($key);
			$subject = str_ireplace("[$key]", $value, $subject);
			$body    = str_ireplace("[$key]", $value, $body);
		}

		if (JMailHelper::isEmailAddress($row->email))
		{
			static::send($mailer, array($row->email), $subject, $body);
			$mailer->clearAllRecipients();
		}

		//Send notification email to administrators
		$emails = explode(',', $config->notification_emails);
		if (strlen($message->{'admin_recurring_subscription_cancel_subject' . $fieldSuffix}))
		{
			$subject = $message->{'admin_recurring_subscription_cancel_subject' . $fieldSuffix};
		}
		else
		{
			$subject = $message->admin_recurring_subscription_cancel_subject;
		}
		$subject = str_replace('[PLAN_TITLE]', $plan->title, $subject);

		if (strlen(strip_tags($message->{'admin_recurring_subscription_cancel_body' . $fieldSuffix})))
		{
			$body = $message->{'admin_recurring_subscription_cancel_body' . $fieldSuffix};
		}
		else
		{
			$body = $message->admin_recurring_subscription_cancel_body;
		}

		foreach ($replaces as $key => $value)
		{
			$key     = strtoupper($key);
			$subject = str_ireplace("[$key]", $value, $subject);
			$body    = str_ireplace("[$key]", $value, $body);
		}

		static::send($mailer, $emails, $subject, $body);
	}

	/**
	 * Send notification email to admin when someone update his profile
	 *
	 * @param $row
	 * @param $config
	 */
	public static function sendProfileUpdateEmail($row, $config)
	{
		$message     = OSMembershipHelper::getMessages();
		$fieldSuffix = OSMembershipHelper::getFieldSuffix();

		if (strlen($message->{'profile_update_email_subject' . $fieldSuffix}))
		{
			$subject = $message->{'profile_update_email_subject' . $fieldSuffix};
		}
		else
		{
			$subject = $message->profile_update_email_subject;
		}

		if (empty($subject))
		{
			return;
		}

		if (strlen(strip_tags($message->{'profile_update_email_body' . $fieldSuffix})))
		{
			$body = $message->{'profile_update_email_body' . $fieldSuffix};
		}
		else
		{
			$body = $message->profile_update_email_body;
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__osmembership_plans')
			->where('id = ' . $row->plan_id);

		if ($fieldSuffix)
		{
			OSMembershipHelperDatabase::getMultilingualFields($query, array('title'), $fieldSuffix);
		}

		$db->setQuery($query);
		$plan = $db->loadObject();

		if ($plan->notification_emails)
		{
			$config->notification_emails = $plan->notification_emails;
		}

		$mailer = static::getMailer($config);

		$replaces = OSMembershipHelper::buildTags($row, $config);

		// Get latest subscription end date
		$query->clear();
		$query->select('MAX(to_date)')
			->from('#__osmembership_subscribers')
			->where('user_id = ' . $row->user_id)
			->where('plan_id = ' . $row->plan_id);
		$db->setQuery($query);
		$subscriptionEndDate = $db->loadResult();
		if (!$subscriptionEndDate)
		{
			$subscriptionEndDate = date($config->date_format);
		}
		$replaces['SUBSCRIPTION_END_DATE'] = $subscriptionEndDate;
		$profileUrl                        = JUri::root() . 'administrator/index.php?option=com_osmembership&task=subscriber.edit&cid[]=' . $row->profile_id;
		$replaces['profile_link']          = '<a href="' . $profileUrl . '">' . $profileUrl . '</a>';
		foreach ($replaces as $key => $value)
		{
			$key     = strtoupper($key);
			$subject = str_ireplace("[$key]", $value, $subject);
			$body    = str_ireplace("[$key]", $value, $body);
		}

		$emails = explode(',', $config->notification_emails);

		static::send($mailer, $emails, $subject, $body);
	}

	/**
	 * Method for sending first, second and third reminder emails
	 *
	 * @param array  $rows
	 * @param string $bccEmail
	 * @param int    $time
	 */
	public static function sendReminderEmails($rows, $bccEmail, $time = 1)
	{
		$config = OSMembershipHelper::getConfig();
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);
		$mailer = static::getMailer($config);

		if (JMailHelper::isEmailAddress($bccEmail))
		{
			$mailer->addBcc($bccEmail);
		}

		$fieldSuffixes = array();

		switch ($time)
		{
			case 2:
				$fieldPrefix = 'second_reminder_';
				break;
			case 3:
				$fieldPrefix = 'third_reminder_';
				break;
			default:
				$fieldPrefix = 'first_reminder_';
				break;
		}

		$message  = OSMembershipHelper::getMessages();
		$timeSent = $db->quote(JFactory::getDate()->toSql());
		for ($i = 0, $n = count($rows); $i < $n; $i++)
		{
			$row = $rows[$i];

			if ($row->number_days < 0)
			{
				continue;
			}

			$query->clear()
				->select('COUNT(*)')
				->from('#__osmembership_subscribers')
				->where('plan_id = ' . $row->plan_id)
				->where('published = 1')
				->where('DATEDIFF(from_date, NOW()) >=0')
				->where('((user_id > 0 AND user_id = ' . (int) $row->user_id . ') OR email="' . $row->email . '")');
			$db->setQuery($query);
			$total = (int) $db->loadResult();
			if ($total)
			{
				$query->clear()
					->update('#__osmembership_subscribers')
					->set($db->quoteName($fieldPrefix . 'sent') . ' = 1 ')
					->where('id = ' . $row->id);
				$db->setQuery($query);
				$db->execute();
				continue;
			}

			$fieldSuffix = '';
			if ($row->language)
			{
				if (!isset($fieldSuffixes[$row->language]))
				{
					$fieldSuffixes[$row->language] = OSMembershipHelper::getFieldSuffix($row->language);
				}

				$fieldSuffix = $fieldSuffixes[$row->language];
			}

			$query->clear()
				->select('title' . $fieldSuffix . ' AS title')
				->from('#__osmembership_plans')
				->where('id = ' . $row->plan_id);

			$db->setQuery($query);
			$planTitle = $db->loadResult();

			$query->clear();
			$replaces                = array();
			$replaces['plan_title']  = $planTitle;
			$replaces['first_name']  = $row->first_name;
			$replaces['last_name']   = $row->last_name;
			$replaces['number_days'] = $row->number_days;
			$replaces['expire_date'] = JHtml::_('date', $row->to_date, $config->date_format);

			if (strlen($message->{$fieldPrefix . 'email_subject' . $fieldSuffix}))
			{
				$subject = $message->{$fieldPrefix . 'email_subject' . $fieldSuffix};
			}
			else
			{
				$subject = $message->{$fieldPrefix . 'email_subject'};
			}

			if (strlen(strip_tags($message->{$fieldPrefix . 'email_body' . $fieldSuffix})))
			{
				$body = $message->{$fieldPrefix . 'email_body' . $fieldSuffix};
			}
			else
			{
				$body = $message->{$fieldSuffix . 'email_body'};
			}

			foreach ($replaces as $key => $value)
			{
				$key     = strtoupper($key);
				$body    = str_ireplace("[$key]", $value, $body);
				$subject = str_ireplace("[$key]", $value, $subject);
			}

			if (JMailHelper::isEmailAddress($row->email))
			{
				static::send($mailer, array($row->email), $subject, $body);

				$mailer->clearAddresses();
			}

			$query->clear()
				->update('#__osmembership_subscribers')
				->set($fieldPrefix . 'sent = 1')
				->set($fieldPrefix . 'sent_at = ' . $timeSent)
				->where('id = ' . $row->id);
			$db->setQuery($query);
			$db->execute();
		}
	}

	/**
	 * Create and initialize mailer object from configuration data
	 *
	 * @param $config
	 *
	 * @return JMail
	 */
	private static function getMailer($config)
	{
		$mailer = JFactory::getMailer();

		if ($config->from_name)
		{
			$fromName = $config->from_name;
		}
		else
		{
			$fromName = JFactory::getConfig()->get('fromname');
		}

		if ($config->from_email)
		{
			$fromEmail = $config->from_email;
		}
		else
		{
			$fromEmail = JFactory::getConfig()->get('mailfrom');
		}

		$mailer->setSender(array($fromEmail, $fromName));
		$mailer->isHtml(true);

		if (empty($config->notification_emails))
		{
			$config->notification_emails = $fromEmail;
		}

		return $mailer;
	}

	/**
	 * Add file uploads to the mailer object
	 *
	 * @param JMail $mailer
	 * @param array $fields
	 * @param array $data
	 */
	private static function addAttachments($mailer, $fields, $data)
	{
		$attachmentsPath = JPATH_ROOT . '/media/com_osmembership/upload/';
		for ($i = 0, $n = count($fields); $i < $n; $i++)
		{
			$field = $fields[$i];
			if ($field->fieldtype == 'File' && isset($data[$field->name]))
			{
				$fileName = $data[$field->name];
				if ($fileName && file_exists($attachmentsPath . '/' . $fileName))
				{
					$pos = strpos($fileName, '_');
					if ($pos !== false)
					{
						$originalFilename = substr($fileName, $pos + 1);
					}
					else
					{
						$originalFilename = $fileName;
					}
					$mailer->addAttachment($attachmentsPath . '/' . $fileName, $originalFilename);
				}
			}
		}
	}

	/**
	 * Process sending after all the data has been initialized
	 *
	 * @param JMail  $mailer
	 * @param array  $emails
	 * @param string $subject
	 * @param string $body
	 */
	private static function send($mailer, $emails, $subject, $body)
	{
		if (empty($subject))
		{
			return;
		}

		$emails = array_map('trim', $emails);

		for ($i = 0, $n = count($emails); $i < $n; $i++)
		{
			if (!JMailHelper::isEmailAddress($emails[$i]))
			{
				unset($emails[$i]);
			}
		}

		if (count($emails) == 0)
		{
			return;
		}

		$mailer->addRecipient($emails[0]);

		if (count($emails) > 1)
		{
			unset($emails[0]);
			$mailer->addBcc($emails);
		}

		$mailer->setSubject($subject)
			->setBody($body)
			->Send();
	}
}
