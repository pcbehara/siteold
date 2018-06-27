<?php
/**
 * @package        Joomla
 * @subpackage     OSMembership
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

use Joomla\Utilities\ArrayHelper;

class OSMembershipModelSubscription extends MPFModelAdmin
{
	use OSMembershipModelSubscriptiontrait;
	/**
	 * Allow subscription model to trigger event
	 *
	 * @var boolean
	 */
	protected $triggerEvents = true;

	/**
	 * Instantiate the model.
	 *
	 * @param array $config configuration data for the model
	 */
	public function __construct($config = array())
	{
		$config['table'] = '#__osmembership_subscribers';

		parent::__construct($config);

		// Import osmembership plugin group
		JPluginHelper::importPlugin('osmembership');
	}

	/**
	 * Method to store a subscription record
	 *
	 * @param MPFInput $input
	 * @param array    $ignore
	 *
	 * @return bool
	 *
	 * @throws Exception
	 */
	public function store($input, $ignore = array())
	{
		$app    = JFactory::getApplication();
		$db     = $this->getDbo();
		$config = OSMembershipHelper::getConfig();

		/* @var OSMembershipTableSubscriber $row */
		$row   = $this->getTable('Subscriber');
		$data  = $input->getData();
		$isNew = true;

		// Create new user account for the subscription
		if (!$data['id'] && !$data['user_id'] && $data['username'] && $data['password'] && $data['email'])
		{
			$data['user_id'] = $this->createUserAccount($data);
		}

		$planChanged = false;

		if ($data['id'])
		{
			$isNew = false;
			$row->load($data['id']);
			$published = $row->published;

			$planFields                   = OSMembershipHelper::getProfileFields($row->plan_id, true);
			$beforeUpdateSubscriptionData = OSMembershipHelper::getProfileData($row, $row->plan_id, $planFields);

			if ($row->plan_id != $data['plan_id'])
			{
				$planChanged = true;

				// Since plan change, we need to trigger onMembershipExpire for the current subscription
				$app->triggerEvent('onMembershipExpire', [$row]);
			}
		}
		else
		{
			$published = 0; //Default is pending
		}

		// Avatar
		$avatar = $input->files->get('profile_avatar');

		if ($avatar['name'])
		{
			$this->uploadAvatar($avatar, $row);
		}

		$row->bind($data);

		if (!$row->check())
		{
			throw new Exception($row->getError());
		}

		$row->user_id = (int) $row->user_id;
		$row->plan_id = (int) $row->plan_id;


		if ($isNew && $row->user_id)
		{
			$query = $db->getQuery(true)
				->select('COUNT(*)')
				->from('#__osmembership_subscribers')
				->where('user_id = ' . $row->user_id)
				->where('plan_id = ' . $row->plan_id)
				->where('(published >= 1 OR payment_method LIKE "os_offline%")');
			$db->setQuery($query);
			$total = $db->loadResult();

			if ($total > 0)
			{
				$row->act = 'renew';
			}
			else
			{
				$row->act = 'subscribe';
			}
		}

		$rowPlan = OSMembershipHelperDatabase::getPlan($row->plan_id);

		list($rowFields, $formFields) = $this->getFields($row->plan_id);

		if ($rowPlan->lifetime_membership == 1 && $data['to_date'] == '')
		{
			$row->to_date = "2099-12-31 00:00:00";
		}

		// Calculate price, from date, to date for new subscription record in case admin leave it empty
		$nullDate = $db->getNullDate();

		if ($isNew && $rowPlan)
		{
			if (!$row->created_date)
			{
				$row->created_date = JFactory::getDate()->toSql();
			}

			if (!$row->from_date)
			{
				$date = $this->calculateSubscriptionFromDate($row, $rowPlan);
			}

			if (!$row->to_date)
			{
				if (empty($date))
				{
					$date = JFactory::getDate($row->from_date);
				}

				$this->calculateSubscriptionEndDate($row, $rowPlan, $date, $rowFields, $data);
			}
		}
		else
		{
			// When editing, we should convert the data back to UTC
			$offset = JFactory::getUser()->getParam('timezone', JFactory::getConfig()->get('offset'));

			// Return a MySQL formatted datetime string in UTC.
			$row->created_date = JFactory::getDate($row->created_date, $offset)->toSql();
			$row->from_date    = JFactory::getDate($row->from_date, $offset)->toSql();

			if (!$rowPlan->lifetime_membership)
			{
				$row->to_date = JFactory::getDate($row->to_date, $offset)->toSql();
			}
		}

		$form = new MPFForm($formFields);

		// In case data for amount field empty, mean users don't enter it, we will calculate subscription fee automatically
		if ($isNew && $row->amount === '' && $rowPlan)
		{
			$form->setData($data)->bindData(true);
			$data['act'] = 'subscribe';
			$fees        = OSMembershipHelper::calculateSubscriptionFee($rowPlan, $form, $data, $config, $data['payment_method']);

			// Set the fee here
			$row->amount                 = $fees['amount'];
			$row->discount_amount        = $fees['discount_amount'];
			$row->tax_amount             = $fees['tax_amount'];
			$row->payment_processing_fee = $fees['payment_processing_fee'];
			$row->gross_amount           = $fees['gross_amount'];
			$row->tax_rate               = $fees['tax_rate'];
		}

		// Reset send reminder information on save2copy
		if ($isNew && $input->getCmd('task') == 'save2copy')
		{
			$row->first_reminder_sent    = $row->second_reminder_sent = $row->third_reminder_sent = 0;
			$row->first_reminder_sent_at = $row->second_reminder_sent_at = $row->third_reminder_sent_at = $nullDate;
		}

		if (!$row->store())
		{
			throw new Exception($row->getError());
		}

		$form->storeFormData($row->id, $data);

		if ($isNew)
		{
			$app->triggerEvent('onAfterStoreSubscription', array($row));
		}

		if ($planChanged && $row->published == 1)
		{
			$app->triggerEvent('onMembershipActive', array($row));
		}

		if ($published != 1 && $row->published == 1)
		{
			/**
			 * Recalculate subscription from date and subscription to date when offline subscription is approved to
			 * avoid users loose some days in their subscription
			 */

			if ($row->payment_method == 'os_offline'
				&& $published == 0 &&
				!$isNew &&
				!$rowPlan->expired_date
				&& $rowPlan->expired_date != $nullDate)
			{
				// Need to re-calculate the start date and end date of this record
				$createdDate = JFactory::getDate($row->created_date);
				$fromDate    = JFactory::getDate($row->from_date);
				$toDate      = JFactory::getDate($row->to_date);
				$todayDate   = JFactory::getDate('now');
				$diff        = $createdDate->diff($todayDate);
				$fromDate->add($diff);
				$toDate->add($diff);
				$row->from_date = $fromDate->toSql();
				$row->to_date   = $toDate->toSql();
				$row->store();
			}

			//Membership active, trigger plugin
			$app->triggerEvent('onMembershipActive', array($row));

			// Upgrade membership
			if ($row->act == 'upgrade' && $published == 0)
			{
				OSMembershipHelperSubscription::processUpgradeMembership($row);
			}

			if (!$isNew && $published == 0)
			{
				OSMembershipHelper::sendMembershipApprovedEmail($row);
			}
		}
		elseif ($published == 1)
		{
			if ($row->published != 1)
			{
				$app->triggerEvent('onMembershipExpire', array($row));
			}
		}

		// Send notification about new subscription
		if ($isNew)
		{
			OSMembershipHelper::sendEmails($row, $config);
		}

		$data['id'] = $row->id;
		$input->set('id', $row->id);

		if (!$isNew)
		{
			$app->triggerEvent('onMembershipUpdate', array($row));

			$afterUpdateSubscriptionData = OSMembershipHelper::getProfileData($row, $row->plan_id, $planFields);
			$app->triggerEvent('onSubscriptionUpdate', array($row, $beforeUpdateSubscriptionData, $afterUpdateSubscriptionData));
		}

		if ($config->synchronize_data !== '0')
		{
			OSMembershipHelperSubscription::synchronizeProfileData($row, $rowFields);
		}

		return true;
	}

	/**
	 * Delete custom fields data related to selected subscribers, trigger event before actual delete the data
	 *
	 * @param array $cid
	 */
	protected function beforeDelete($cid)
	{
		if (count($cid))
		{
			$app   = JFactory::getApplication();
			$db    = $this->getDbo();
			$query = $db->getQuery(true);
			$query->delete('#__osmembership_field_value')
				->where('subscriber_id IN (' . implode(',', $cid) . ')');
			$db->setQuery($query);
			$db->execute();

			// Trigger onMembershipExpire event before subscriptions being deleted

			/* @var OSMembershipTableSubscriber $row */
			$row = $this->getTable('Subscriber');

			foreach ($cid as $id)
			{
				$row->load($id);
				$app->triggerEvent('onMembershipExpire', array($row));
			}
		}
	}

	/**
	 * Method to change the published state of one or more records.
	 *
	 * @param array $pks   A list of the primary keys to change.
	 * @param int   $value The value of the published state.
	 *
	 * @throws Exception
	 */
	public function publish($pks, $value = 1)
	{
		$app = JFactory::getApplication();
		$pks = (array) $pks;

		$this->beforePublish($pks, $value);

		// Change state of the records
		foreach ($pks as $pk)
		{
			/* @var OSMembershipTableSubscriber $row */
			$row     = $this->getTable();
			$trigger = false;

			if (!$row->load($pk))
			{
				throw new Exception('Invalid Subscription Record: ' . $pk);
			}

			if ($value == 1 && $row->published == 0)
			{
				$trigger = true;
			}

			$row->published = $value;

			$row->store();

			if ($trigger)
			{
				// Upgrade membership
				if ($row->act == 'upgrade')
				{
					OSMembershipHelperSubscription::processUpgradeMembership($row);
				}

				$app->triggerEvent('onMembershipActive', array($row));

				OSMembershipHelper::sendMembershipApprovedEmail($row);
			}
		}

		$app->triggerEvent($this->eventChangeState, array($this->context, $pks, $value));

		$this->afterPublish($pks, $value);

		// Clear the component's cache
		$this->cleanCache();
	}

	/**
	 * Renew subscription for a given subscriber
	 *
	 * @param $id
	 *
	 * @return bool
	 *
	 * @throws Exception
	 */
	public function renew($id)
	{
		$model = new OSMembershipModelApi;
		$model->renew($id);

		return true;
	}

	/**
	 * Send batch emails to selected subscriptions by quangnv
	 *
	 * @param MPFInput $input
	 *
	 * @throws Exception
	 */
	public function batchMail($input)
	{
		$cid          = $input->get('cid', array(), 'array');
		$emailSubject = $input->getString('subject');
		$emailMessage = $input->get('message', '', 'raw');

		if (empty($cid))
		{
			throw new Exception('Please select subscriptions to send mass mail');
		}

		if (empty($emailSubject))
		{
			throw new Exception('Please enter subject of the email');
		}

		if (empty($emailMessage))
		{
			throw new Exception('Please enter message of the email');
		}

		// OK, data is valid, process sending email
		$mailer = JFactory::getMailer();
		$config = OSMembershipHelper::getConfig();
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);

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

		// Get list of subscriptions records
		$query->select('a.*, b.title')
			->from('#__osmembership_subscribers AS a')
			->innerJoin('#__osmembership_plans AS b ON a.plan_id = b.id')
			->where('a.id IN (' . implode(',', $cid) . ')');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		// Get list of core fields
		$query->clear()
			->select('name')
			->from('#__osmembership_fields')
			->where('published = 1')
			->where('is_core = 1');

		$db->setQuery($query);
		$fields = $db->loadObjectList();

		foreach ($rows as $row)
		{
			$subject = $emailSubject;
			$message = $emailMessage;

			$replaces                 = array();
			$replaces['plan_title']   = $row->title;
			$replaces['from_date']    = JHtml::_('date', $row->from_date, $config->date_format);
			$replaces['to_date']      = JHtml::_('date', $row->to_date, $config->date_format);
			$replaces['created_date'] = JHtml::_('date', $row->created_date, $config->date_format);
			$replaces['gross_amount'] = OSMembershipHelper::formatAmount($row->gross_amount, $config);

			foreach ($replaces as $key => $value)
			{
				$key     = strtoupper($key);
				$subject = str_ireplace("[$key]", $value, $subject);
				$message = str_ireplace("[$key]", $value, $message);
			}

			foreach ($fields as $field)
			{
				$key     = $field->name;
				$value   = $row->{$field->name};
				$subject = str_ireplace("[$key]", $value, $subject);
				$message = str_ireplace("[$key]", $value, $message);
			}

			if (JMailHelper::isEmailAddress($row->email))
			{
				$mailer->sendMail($fromEmail, $fromName, $row->email, $subject, $message, 1);
				$mailer->clearAllRecipients();
			}
		}
	}

	/**
	 * Get JTable object for the model
	 *
	 * @param string $name
	 *
	 * @return JTable
	 */
	public function getTable($name = 'Subscriber')
	{
		return parent::getTable($name);
	}

	/**
	 * Resend confirmation email to subscriber
	 *
	 * @param int $id
	 *
	 * @return void
	 */
	public function resendEmail($id)
	{
		/* @var OSMembershipTableSubscriber $row */
		$row = $this->getTable();
		$row->load($id);

		// Load the default frontend language
		$tag = $row->language;

		if (!$tag || $tag == '*')
		{
			$tag = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
		}

		JFactory::getLanguage()->load('com_osmembership', JPATH_ROOT, $tag);

		$config = OSMembershipHelper::getConfig();

		OSMembershipHelperMail::sendEmails($row, $config);
	}
}