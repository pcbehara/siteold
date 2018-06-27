<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

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
		if (OSMembershipHelper::isMethodOverridden('OSMembershipHelperOverrideMail', 'sendEmails'))
		{
			OSMembershipHelperOverrideMail::sendEmails($row, $config);

			return;
		}

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

		if (!empty($config->log_email_types) && in_array('new_subscription_emails', explode(',', $config->log_email_types)))
		{
			$logEmails = true;
		}
		else
		{
			$logEmails = false;
		}

		$rowFields    = OSMembershipHelper::getProfileFields($row->plan_id);
		$emailContent = OSMembershipHelper::getEmailContent($config, $row);

		if (is_callable('OSMembershipHelperOverrideHelper::buildTags'))
		{
			$replaces = OSMembershipHelperOverrideHelper::buildTags($row, $config);
		}
		else
		{
			$replaces = OSMembershipHelper::buildTags($row, $config);
		}

		$replaces['plan_title'] = $plan->title;


		// New Subscription Email Subject
		if ($fieldSuffix && trim($plan->{'user_email_subject' . $fieldSuffix}))
		{
			$subject = $plan->{'user_email_subject' . $fieldSuffix};
		}
		elseif ($fieldSuffix && trim($message->{'user_email_subject' . $fieldSuffix}))
		{
			$subject = $message->{'user_email_subject' . $fieldSuffix};
		}
		elseif (trim($plan->user_email_subject))
		{
			$subject = $plan->user_email_subject;
		}
		else
		{
			$subject = $message->user_email_subject;
		}

		// New Subscription Email Body
		if ($row->payment_method == 'os_offline' && $row->published == 0)
		{
			if ($fieldSuffix && OSMembershipHelper::isValidMessage($plan->{'user_email_body_offline' . $fieldSuffix}))
			{
				$body = $plan->{'user_email_body_offline' . $fieldSuffix};
			}
			elseif ($fieldSuffix && OSMembershipHelper::isValidMessage($message->{'user_email_body_offline' . $fieldSuffix}))
			{
				$body = $message->{'user_email_body_offline' . $fieldSuffix};
			}
			elseif (OSMembershipHelper::isValidMessage($plan->user_email_body_offline))
			{
				$body = $plan->user_email_body_offline;
			}
			else
			{
				$body = $message->user_email_body_offline;
			}
		}
		else
		{
			if ($fieldSuffix && OSMembershipHelper::isValidMessage($plan->{'user_email_body' . $fieldSuffix}))
			{
				$body = $plan->{'user_email_body' . $fieldSuffix};
			}
			elseif ($fieldSuffix && OSMembershipHelper::isValidMessage($message->{'user_email_body' . $fieldSuffix}))
			{
				$body = $message->{'user_email_body' . $fieldSuffix};
			}
			elseif (OSMembershipHelper::isValidMessage($plan->user_email_body))
			{
				$body = $plan->user_email_body;
			}
			else
			{
				$body = $message->user_email_body;
			}
		}

		$subject = str_replace('[PLAN_TITLE]', $plan->title, $subject);
		$body    = str_replace('[SUBSCRIPTION_DETAIL]', $emailContent, $body);

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
			static::send($mailer, array($row->email), $subject, $body, $logEmails, 2, 'new_subscription_emails');
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

			static::send($mailer, $emails, $subject, $body, $logEmails, 1, 'new_subscription_emails');
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
		if (OSMembershipHelper::isMethodOverridden('OSMembershipHelperOverrideMail', 'sendMembershipRenewalEmails'))
		{
			OSMembershipHelperOverrideMail::sendMembershipRenewalEmails($mailer, $row, $plan, $config, $message, $fieldSuffix);

			return;
		}

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

		if (!empty($config->log_email_types) && in_array('subscription_renewal_emails', explode(',', $config->log_email_types)))
		{
			$logEmails = true;
		}
		else
		{
			$logEmails = false;
		}

		// Get list of fields
		$rowFields = OSMembershipHelper::getProfileFields($row->plan_id);

		$emailContent = OSMembershipHelper::getEmailContent($config, $row);

		if (is_callable('OSMembershipHelperOverrideHelper::buildTags'))
		{
			$replaces = OSMembershipHelperOverrideHelper::buildTags($row, $config);
		}
		else
		{
			$replaces = OSMembershipHelper::buildTags($row, $config);
		}

		$replaces['plan_title']  = $plan->title;
		$replaces['number_days'] = $numberDays;

		// Subscription Renewal Email Subject
		if ($fieldSuffix && trim($plan->{'user_renew_email_subject' . $fieldSuffix}))
		{
			$subject = $plan->{'user_renew_email_subject' . $fieldSuffix};
		}
		elseif ($fieldSuffix && trim($message->{'user_renew_email_subject' . $fieldSuffix}))
		{
			$subject = $message->{'user_renew_email_subject' . $fieldSuffix};
		}
		elseif (trim($plan->user_renew_email_subject))
		{
			$subject = $plan->user_renew_email_subject;
		}
		else
		{
			$subject = $message->user_renew_email_subject;
		}

		// Subscription Renewal Email Body
		if ($row->payment_method == 'os_offline' && $row->published == 0)
		{
			if ($fieldSuffix && OSMembershipHelper::isValidMessage($message->{'user_renew_email_body_offline' . $fieldSuffix}))
			{
				$body = $message->{'user_renew_email_body_offline' . $fieldSuffix};
			}
			elseif (OSMembershipHelper::isValidMessage($plan->user_renew_email_body_offline))
			{
				$body = $plan->user_renew_email_body_offline;
			}
			else
			{
				$body = $message->user_renew_email_body_offline;
			}
		}
		else
		{
			if ($fieldSuffix && OSMembershipHelper::isValidMessage($plan->{'user_renew_email_body' . $fieldSuffix}))
			{
				$body = $plan->{'user_renew_email_body' . $fieldSuffix};
			}
			elseif ($fieldSuffix && OSMembershipHelper::isValidMessage($message->{'user_renew_email_body' . $fieldSuffix}))
			{
				$body = $message->{'user_renew_email_body' . $fieldSuffix};
			}
			elseif (OSMembershipHelper::isValidMessage($plan->user_renew_email_body))
			{
				$body = $plan->user_renew_email_body;
			}
			else
			{
				$body = $message->user_renew_email_body;
			}
		}

		$subject = str_replace('[PLAN_TITLE]', $plan->title, $subject);

		$body = str_replace('[SUBSCRIPTION_DETAIL]', $emailContent, $body);

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
			static::send($mailer, array($row->email), $subject, $body, $logEmails, 2, 'subscription_renewal_emails');
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

			static::send($mailer, $emails, $subject, $body, $logEmails, 1, 'subscription_renewal_emails');
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
		if (OSMembershipHelper::isMethodOverridden('OSMembershipHelperOverrideMail', 'sendMembershipUpgradeEmails'))
		{
			OSMembershipHelperOverrideMail::sendMembershipUpgradeEmails($mailer, $row, $plan, $config, $message, $fieldSuffix);

			return;
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		//Get from plan title
		$query->select($db->quoteName('b.title' . $fieldSuffix, 'title'))
			->from('#__osmembership_upgraderules AS a')
			->innerJoin('#__osmembership_plans AS b ON a.from_plan_id = b.id')
			->where('a.id = ' . $row->upgrade_option_id);
		$db->setQuery($query);
		$planTitle = $db->loadResult();

		if (!empty($config->log_email_types) && in_array('subscription_upgrade_emails', explode(',', $config->log_email_types)))
		{
			$logEmails = true;
		}
		else
		{
			$logEmails = false;
		}

		$rowFields = OSMembershipHelper::getProfileFields($row->plan_id);

		$emailContent = OSMembershipHelper::getEmailContent($config, $row);

		if (is_callable('OSMembershipHelperOverrideHelper::buildTags'))
		{
			$replaces = OSMembershipHelperOverrideHelper::buildTags($row, $config);
		}
		else
		{
			$replaces = OSMembershipHelper::buildTags($row, $config);
		}

		$replaces['plan_title']    = $planTitle;
		$replaces['to_plan_title'] = $plan->title;

		// Subscription Upgrade Email Subject
		if ($fieldSuffix && $message->{'user_upgrade_email_subject' . $fieldSuffix})
		{
			$subject = $message->{'user_upgrade_email_subject' . $fieldSuffix};
		}
		else
		{
			$subject = $message->user_upgrade_email_subject;
		}

		// Subscription Renewal Email Body
		if ($row->payment_method == 'os_offline' && $row->published == 0)
		{
			if (OSMembershipHelper::isValidMessage($plan->user_upgrade_email_body_offline))
			{
				$body = $plan->user_upgrade_email_body_offline;
			}
			elseif (OSMembershipHelper::isValidMessage($message->user_upgrade_email_body_offline))
			{
				$body = $message->user_upgrade_email_body_offline;
			}
			// The conditions below is for keep backward compatible
			elseif ($fieldSuffix && OSMembershipHelper::isValidMessage($message->{'user_upgrade_email_body' . $fieldSuffix}))
			{
				$body = $message->{'user_upgrade_email_body' . $fieldSuffix};
			}
			elseif (OSMembershipHelper::isValidMessage($plan->user_upgrade_email_body))
			{
				$body = $plan->user_upgrade_email_body;
			}
			else
			{
				$body = $message->user_upgrade_email_body;
			}
		}
		else
		{
			if ($fieldSuffix && OSMembershipHelper::isValidMessage($message->{'user_upgrade_email_body' . $fieldSuffix}))
			{
				$body = $message->{'user_upgrade_email_body' . $fieldSuffix};
			}
			elseif (OSMembershipHelper::isValidMessage($plan->user_upgrade_email_body))
			{
				$body = $plan->user_upgrade_email_body;
			}
			else
			{
				$body = $message->user_upgrade_email_body;
			}
		}

		$subject = str_replace('[TO_PLAN_TITLE]', $plan->title, $subject);
		$subject = str_replace('[PLAN_TITLE]', $planTitle, $subject);
		$body    = str_replace('[SUBSCRIPTION_DETAIL]', $emailContent, $body);

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
			static::send($mailer, array($row->email), $subject, $body, $logEmails, 2, 'subscription_upgrade_emails');
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

			static::send($mailer, $emails, $subject, $body, $logEmails, 2, 'subscription_upgrade_emails');
		}
	}

	/**
	 * Send email to subscriber to inform them that their membership approved (and activated)
	 *
	 * @param object $row
	 */
	public static function sendMembershipApprovedEmail($row)
	{
		if (OSMembershipHelper::isMethodOverridden('OSMembershipHelperOverrideMail', 'sendMembershipApprovedEmail'))
		{
			OSMembershipHelperOverrideMail::sendMembershipApprovedEmail($row);

			return;
		}

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

		$emailContent = OSMembershipHelper::getEmailContent($config, $row);

		if (!empty($config->log_email_types) && in_array('subscription_approved_emails', explode(',', $config->log_email_types)))
		{
			$logEmails = true;
		}
		else
		{
			$logEmails = false;
		}

		if (is_callable('OSMembershipHelperOverrideHelper::buildTags'))
		{
			$replaces = OSMembershipHelperOverrideHelper::buildTags($row, $config);
		}
		else
		{
			$replaces = OSMembershipHelper::buildTags($row, $config);
		}

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

		foreach ($replaces as $key => $value)
		{
			$key     = strtoupper($key);
			$subject = str_ireplace("[$key]", $value, $subject);
			$body    = str_ireplace("[$key]", $value, $body);
		}

		if (JMailHelper::isEmailAddress($row->email))
		{
			// Generate paid invoice and send it to email
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

			static::send($mailer, array($row->email), $subject, $body, $logEmails, 2, 'subscription_approved_emails');
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
		if (OSMembershipHelper::isMethodOverridden('OSMembershipHelperOverrideMail', 'sendSubscriptionCancelEmail'))
		{
			OSMembershipHelperOverrideMail::sendSubscriptionCancelEmail($row, $config);

			return;
		}

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

		if (!empty($config->log_email_types) && in_array('subscription_cancel_emails', explode(',', $config->log_email_types)))
		{
			$logEmails = true;
		}
		else
		{
			$logEmails = false;
		}

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
			static::send($mailer, array($row->email), $subject, $body, $logEmails, 2, 'subscription_cancel_emails');
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

		static::send($mailer, $emails, $subject, $body, $logEmails, 1, 'subscription_cancel_emails');
	}

	/**
	 * Send notification email to admin when someone update his profile
	 *
	 * @param $row
	 * @param $config
	 */
	public static function sendProfileUpdateEmail($row, $config, $updateFields = [])
	{
		if (OSMembershipHelper::isMethodOverridden('OSMembershipHelperOverrideMail', 'sendProfileUpdateEmail'))
		{
			OSMembershipHelperOverrideMail::sendProfileUpdateEmail($row, $config);

			return;
		}

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

		if (!empty($config->log_email_types) && in_array('profile_updated_emails', explode(',', $config->log_email_types)))
		{
			$logEmails = true;
		}
		else
		{
			$logEmails = false;
		}

		if (is_callable('OSMembershipHelperOverrideHelper::buildTags'))
		{
			$replaces = OSMembershipHelperOverrideHelper::buildTags($row, $config);
		}
		else
		{
			$replaces = OSMembershipHelper::buildTags($row, $config);
		}

		if (!empty($updateFields))
		{
			$replaces['profile_updated_details'] = OSMembershipHelperHtml::loadCommonLayout('emailtemplates/tmpl/profile_updated.php', ['fields' => $updateFields]);
		}
		else
		{
			$replaces['profile_updated_details'] = '';
		}

		// Get latest subscription end date
		$query->clear()
			->select('MAX(to_date)')
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
		$replaces['SUBSCRIPTION_DETAIL']   = OSMembershipHelper::getEmailContent($config, $row);
		$profileUrl                        = JUri::root() . 'administrator/index.php?option=com_osmembership&task=subscriber.edit&cid[]=' . $row->profile_id;
		$replaces['profile_link']          = '<a href="' . $profileUrl . '">' . $profileUrl . '</a>';

		foreach ($replaces as $key => $value)
		{
			$key     = strtoupper($key);
			$subject = str_ireplace("[$key]", $value, $subject);
			$body    = str_ireplace("[$key]", $value, $body);
		}

		$emails = explode(',', $config->notification_emails);

		static::send($mailer, $emails, $subject, $body, $logEmails, 1, 'profile_updated_emails');
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
		if (OSMembershipHelper::isMethodOverridden('OSMembershipHelperOverrideMail', 'sendReminderEmails'))
		{
			OSMembershipHelperOverrideMail::sendReminderEmails($rows, $bccEmail, $time);

			return;
		}

		$config = OSMembershipHelper::getConfig();
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);
		$mailer = static::getMailer($config);

		$logEmails = false;

		$bccEmails = explode(',', $bccEmail);

		$bccEmails = array_map('trim', $bccEmails);

		foreach ($bccEmails as $bccEmail)
		{
			if (JMailHelper::isEmailAddress($bccEmail))
			{
				$mailer->addBcc($bccEmail);
			}
		}

		// Get list of payment methods
		$query->select('name, title')
			->from('#__osmembership_plugins');
		$db->setQuery($query);
		$plugins = $db->loadObjectList('name');

		$query->clear()
			->select('*')
			->from('#__osmembership_plans');
		$db->setQuery($query);
		$plans = $db->loadObjectList('id');

		$fieldSuffixes = array();

		switch ($time)
		{
			case 2:
				$fieldPrefix = 'second_reminder_';
				$emailType   = 'second_reminder_emails';

				if (!empty($config->log_email_types) && in_array('second_reminder_emails', explode(',', $config->log_email_types)))
				{
					$logEmails = true;
				}
				break;
			case 3:
				$fieldPrefix = 'third_reminder_';
				$emailType   = 'third_reminder_emails';

				if (!empty($config->log_email_types) && in_array('third_reminder_emails', explode(',', $config->log_email_types)))
				{
					$logEmails = true;
				}
				break;
			default:
				$fieldPrefix = 'first_reminder_';
				$emailType   = 'first_reminder_emails';

				if (!empty($config->log_email_types) && in_array('first_reminder_emails', explode(',', $config->log_email_types)))
				{
					$logEmails = true;
				}
				break;
		}

		$message  = OSMembershipHelper::getMessages();
		$timeSent = $db->quote(JFactory::getDate()->toSql());

		for ($i = 0, $n = count($rows); $i < $n; $i++)
		{
			$row = $rows[$i];

			$query->clear()
				->select('COUNT(*)')
				->from('#__osmembership_subscribers')
				->where('plan_id = ' . $row->plan_id)
				->where('published = 1')
				->where('id > ' . $row->id)
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

			$plan      = $plans[$row->plan_id];
			$planTitle = $plan->{'title' . $fieldSuffix};

			$replaces                  = array();
			$replaces['plan_title']    = $planTitle;
			$replaces['first_name']    = $row->first_name;
			$replaces['last_name']     = $row->last_name;
			$replaces['number_days']   = $row->number_days;
			$replaces['membership_id'] = OSMembershipHelper::formatMembershipId($row, $config);
			$replaces['expire_date']   = JHtml::_('date', $row->to_date, $config->date_format);
			$replaces['gross_amount']  = OSMembershipHelper::formatAmount($row->gross_amount, $config);

			if (isset($plugins[$row->payment_method]))
			{
				$replaces['payment_method'] = $plugins[$row->payment_method]->title;
			}
			else
			{
				$replaces['payment_method'] = '';
			}

			if (strlen($plan->{$fieldPrefix . 'email_subject'}) > 0)
			{
				$subject = $plan->{$fieldPrefix . 'email_subject'};
			}
			elseif (strlen($message->{$fieldPrefix . 'email_subject' . $fieldSuffix}))
			{
				$subject = $message->{$fieldPrefix . 'email_subject' . $fieldSuffix};
			}
			else
			{
				$subject = $message->{$fieldPrefix . 'email_subject'};
			}

			if (self::isValidEmailBody($plan->{$fieldPrefix . 'email_body'}))
			{
				$body = $plan->{$fieldPrefix . 'email_body'};
			}
			elseif (self::isValidEmailBody($message->{$fieldPrefix . 'email_body' . $fieldSuffix}))
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
				static::send($mailer, array($row->email), $subject, $body, $logEmails, 2, $emailType);

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
	 * Send email to user to inform them that he has just added as new member of a group
	 *
	 * @param object $row
	 */
	public static function sendNewGroupMemberEmail($row)
	{
		if (OSMembershipHelper::isMethodOverridden('OSMembershipHelperOverrideMail', 'sendNewGroupMemberEmail'))
		{
			OSMembershipHelperOverrideMail::sendNewGroupMemberEmail($row);

			return;
		}

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

		$emailContent = OSMembershipHelper::getEmailContent($config, $row);

		if (is_callable('OSMembershipHelperOverrideHelper::buildTags'))
		{
			$replaces = OSMembershipHelperOverrideHelper::buildTags($row, $config);
		}
		else
		{
			$replaces = OSMembershipHelper::buildTags($row, $config);
		}

		$replaces['plan_title']       = $plan->title;
		$replaces['group_admin_name'] = JFactory::getUser()->get('name');

		if (strlen($message->{'new_group_member_email_subject' . $fieldSuffix}))
		{
			$subject = $message->{'new_group_member_email_subject' . $fieldSuffix};
		}
		else
		{
			$subject = $message->new_group_member_email_subject;
		}

		$subject = str_replace('[PLAN_TITLE]', $plan->title, $subject);

		if (strlen(strip_tags($message->{'new_group_member_email_body' . $fieldSuffix})))
		{
			$body = $message->{'new_group_member_email_body' . $fieldSuffix};
		}
		else
		{
			$body = $message->new_group_member_email_body;
		}

		$body = str_replace('[SUBSCRIPTION_DETAIL]', $emailContent, $body);

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
	}


	/**
	 * Create and initialize mailer object from configuration data
	 *
	 * @param $config
	 *
	 * @return JMail
	 */
	public static function getMailer($config)
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
	public static function addAttachments($mailer, $fields, $data)
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
	 * Check if the given message is a valid email message
	 *
	 * @param $body
	 *
	 * @return bool
	 */
	public static function isValidEmailBody($body)
	{
		if (strlen(trim(strip_tags($body))) > 20)
		{
			return true;
		}

		return false;
	}

	/**
	 * Process sending after all the data has been initialized
	 *
	 * @param JMail  $mailer
	 * @param array  $emails
	 * @param string $subject
	 * @param string $body
	 * @param bool   $logEmails
	 * @param int    $sentTo
	 * @param string $emailType
	 */
	public static function send($mailer, $emails, $subject, $body, $logEmails = false, $sentTo = 0, $emailType = '')
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

		require_once JPATH_ROOT . '/components/com_osmembership/helper/html.php';

		$email     = $emails[0];
		$bccEmails = array();

		$mailer->addRecipient($email);

		if (count($emails) > 1)
		{
			unset($emails[0]);
			$bccEmails = $emails;
			$mailer->addBcc($bccEmails);
		}

		$body      = OSMembershipHelper::convertImgTags($body);
		$emailBody = OSMembershipHelperHtml::loadCommonLayout('emailtemplates/tmpl/container.php', array('body' => $body, 'subject' => $subject));

		$mailer->setSubject($subject)
			->setBody($emailBody)
			->Send();

		if ($logEmails)
		{
			require_once JPATH_ADMINISTRATOR . '/components/com_osmembership/table/email.php';

			$row             = JTable::getInstance('Email', 'OSMembershipTable');
			$row->sent_at    = JFactory::getDate()->toSql();
			$row->email      = $email;
			$row->subject    = $subject;
			$row->body       = $body;
			$row->sent_to    = $sentTo;
			$row->email_type = $emailType;
			$row->store();

			if (count($bccEmails))
			{
				foreach ($bccEmails as $email)
				{
					$row->id    = 0;
					$row->email = $email;
					$row->store();
				}
			}
		}

	}
}
