<?php
/**
 * @package        Joomla
 * @subpackage     OSMembership
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

// no direct access
defined('_JEXEC') or die;

/**
 * Membership Pro Component Subscriber Model
 *
 * @package        Joomla
 * @subpackage     Membership Pro
 */
class OSMembershipModelSubscription extends MPFModelAdmin
{
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
	}

	/**
	 * Method to store a subscription record
	 *
	 * @param MPFInput $input
	 * @param array    $ignore
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function store($input, $ignore = array())
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		/* @var OSMembershipTableSubscriber $row */
		$row   = $this->getTable('Subscriber');
		$data  = $input->getData();
		$isNew = true;
		if (!$data['id'] && !$data['user_id'] && $data['username'] && $data['password'] && $data['email'])
		{
			//Store this account into the system and get the username
			jimport('joomla.user.helper');
			$params      = JComponentHelper::getParams('com_users');
			$newUserType = $params->get('new_usertype', 2);

			$data['groups']    = array();
			$data['groups'][]  = $newUserType;
			$data['block']     = 0;
			$data['name']      = $data['first_name'] . ' ' . $data['last_name'];
			$data['password1'] = $data['password2'] = $data['password'];
			$data['email1']    = $data['email2'] = $data['email'];
			$user              = new JUser();
			$user->bind($data);
			if (!$user->save())
			{
				throw new Exception($user->getError());
			}
			$data['user_id'] = $user->id;
		}
		if ($data['id'])
		{
			$isNew = false;
			$row->load($data['id']);
			$published = $row->published;
		}
		else
		{
			$published = 0; //Default is pending
		}
		if (!$row->bind($data))
		{
			throw new Exception($db->getErrorMsg());
		}
		if (!$row->check())
		{
			throw new Exception($db->getErrorMsg());
		}
		$row->user_id          = (int) $row->user_id;
		$row->is_profile       = 1;
		$row->plan_main_record = 1;

		if ($row->user_id > 0)
		{
			$query->select('id')
				->from('#__osmembership_subscribers')
				->where('is_profile = 1')
				->where('user_id = ' . $row->user_id);
			$db->setQuery($query);
			$profileId = $db->loadResult();

			if ($profileId && ($profileId != $row->id))
			{
				$row->is_profile = 0;
				$row->profile_id = $profileId;
			}

			$query->clear()
				->select('plan_subscription_from_date')
				->from('#__osmembership_subscribers')
				->where('plan_main_record = 1')
				->where('user_id = ' . $row->user_id)
				->where('plan_id = ' . $row->plan_id);
			if ($row->id > 0)
			{
				$query->where('id != ' . $row->id);
			}

			$db->setQuery($query);
			$planMainRecord = $db->loadObject();
			if ($planMainRecord)
			{
				$row->plan_main_record            = 0;
				$row->plan_subscription_from_date = $planMainRecord->plan_subscription_from_date;
			}
		}

		$query->clear()
			->select('lifetime_membership')
			->from('#__osmembership_plans')
			->where('id=' . (int) $data['plan_id']);
		$db->setQuery($query);
		$lifetimeMembership = $db->loadResult();
		if ($lifetimeMembership == 1 && $data['to_date'] == '')
		{
			$row->to_date = "2099-12-31 00:00:00";
		}

		// Calculate price, from date, to date for new subscription record in case admin leave it empty
		$nullDate = $db->getNullDate();
		$query->clear()
			->select('*')
			->from('#__osmembership_plans')
			->where('id = ' . (int) $row->plan_id);
		$db->setQuery($query);
		$rowPlan = $db->loadObject();

		if ($isNew && $rowPlan)
		{
			if (!$row->created_date)
			{
				$row->created_date = JFactory::getDate()->toSql();
			}
			if (!$row->from_date)
			{
				$maxDate = null;
				if ($row->user_id > 0)
				{
					//Subscriber, user existed
					$query->clear();
					$query->select('MAX(to_date)')
						->from('#__osmembership_subscribers')
						->where('user_id=' . $row->user_id . ' AND plan_id=' . $row->plan_id . ' AND (published=1 OR (published = 0 AND payment_method LIKE "os_offline%"))');
					$db->setQuery($query);
					$maxDate = $db->loadResult();
				}

				if ($maxDate)
				{
					$date           = JFactory::getDate($maxDate);
					$row->from_date = $date->add(new DateInterval('P1D'))->toSql();
				}
				else
				{
					$date           = JFactory::getDate();
					$row->from_date = $date->toSql();
				}

				if ($rowPlan->expired_date && $rowPlan->expired_date != $nullDate)
				{

					$expiredDate = JFactory::getDate($rowPlan->expired_date, JFactory::getConfig()->get('offset'));

					// Change year of expired date to current year
					if ($date->year > $expiredDate->year)
					{
						$expiredDate->setDate($date->year, $expiredDate->month, $expiredDate->day);
					}

					$expiredDate->setTime(23, 59, 59);
					$date->setTime(23, 59, 59);

					$numberYears = 1;

					if ($rowPlan->subscription_length_unit == 'Y')
					{
						$numberYears = $rowPlan->subscription_length;
					}

					if ($date >= $expiredDate)
					{
						$numberYears++;
					}

					$expiredDate->setDate($expiredDate->year + $numberYears - 1, $expiredDate->month, $expiredDate->day);

					$row->to_date = $expiredDate->toSql();
				}
				else
				{
					if ($rowPlan->lifetime_membership)
					{
						$row->to_date = '2099-12-31 23:59:59';
					}
					else
					{
						$dateIntervalSpec = 'P' . $rowPlan->subscription_length . $rowPlan->subscription_length_unit;
						$row->to_date     = $date->add(new DateInterval($dateIntervalSpec))->toSql();
					}
				}
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
		$rowFields = OSMembershipHelper::getProfileFields($row->plan_id, false);
		$form      = new MPFForm($rowFields);
		if ($isNew && !$row->amount && $rowPlan)
		{
			// Calculate the fee
			$form->setData($data)->bindData(true);
			$data['act'] = 'subscribe';
			$config      = OSMembershipHelper::getConfig();
			$fees        = OSMembershipHelper::calculateSubscriptionFee($rowPlan, $form, $data, $config, $data['payment_method']);

			// Set the fee here
			$row->amount                 = $fees['amount'];
			$row->discount_amount        = $fees['discount_amount'];
			$row->tax_amount             = $fees['tax_amount'];
			$row->payment_processing_fee = $fees['payment_processing_fee'];
			$row->gross_amount           = $fees['gross_amount'];
		}

		if (!$row->id && $row->plan_main_record)
		{
			$row->plan_subscription_status    = $row->published;
			$row->plan_subscription_from_date = $row->from_date;
			$row->plan_subscription_to_date   = $row->to_date;
		}

		if (!$row->store())
		{
			$this->setError($db->getErrorMsg());

			return false;
		}

		if (!$row->profile_id)
		{
			$row->profile_id = $row->id;
			$row->store();
		}

		$form->storeData($row->id, $data);
		JPluginHelper::importPlugin('osmembership');
		$dispatcher = JEventDispatcher::getInstance();
		if ($isNew)
		{
			$dispatcher->trigger('onAfterStoreSubscription', array($row));
		}
		if ($published != 1 && $row->published == 1)
		{
			if ($row->payment_method == 'os_offline' && $published == 0 && !$isNew)
			{
				// Need to re-calculate the start date and end date of this record
				$createdDate = JFactory::getDate($row->created_date);
				$fromDate    = JFactory::getDate($row->from_date);
				$toDate      = JFactory::getDate($row->to_date);
				$todayDate   = JFactory::getDate('now');
				//$diff        = $createdDate->diff($todayDate);
				//$fromDate->add($diff);
				//$toDate->add($diff);
				$row->from_date = $fromDate->toSql();
				$row->to_date   = $toDate->toSql();
				$row->store();
			}

			//Membership active, trigger plugin
			$dispatcher->trigger('onMembershipActive', array($row));

			// Upgrade membership
			if ($row->act == 'upgrade' && $published == 0)
			{
				OSMembershipHelperSubscription::processUpgradeMembership($row);
			}

			if ($published == 0 && $row->email)
			{
				OSMembershipHelper::sendMembershipApprovedEmail($row);
			}
		}
		elseif ($published == 1)
		{
			if ($row->published != 1)
			{
				$dispatcher->trigger('onMembershipExpire', array($row));
			}
		}
		$data['id'] = $row->id;
		if (!$isNew)
		{
			$dispatcher->trigger('onMembershipUpdate', array($row));
		}

		$config = OSMembershipHelper::getConfig();
		if ($config->synchronize_data !== '0')
		{
			OSMembershipHelper::syncronizeProfileData($row, $data);
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
			//
			$db    = $this->getDbo();
			$query = $db->getQuery(true);
			$query->delete('#__osmembership_field_value')
				->where('subscriber_id IN (' . implode(',', $cid) . ')');
			$db->setQuery($query);
			$db->execute();
			JPluginHelper::importPlugin('osmembership');
			$dispatcher = JEventDispatcher::getInstance();
			$row        = $this->getTable('Subscriber');
			foreach ($cid as $id)
			{
				$row->load($id);
				$dispatcher->trigger('onMembershipExpire', array($row));
			}
		}
	}

	/**
	 * Pre-process before publishing the actual record
	 *
	 * @param array $cid
	 * @param int   $state
	 *
	 * @throws Exception
	 */
	protected function beforePublish($cid, $state)
	{
		if ($state == 1)
		{
			$row = $this->getTable('Subscriber');
			JPluginHelper::importPlugin('osmembership');
			$dispatcher = JEventDispatcher::getInstance();
			foreach ($cid as $id)
			{
				$row->load($id);
				if (!$row->published)
				{
					$dispatcher->trigger('onMembershipActive', array($row));
					OSMembershipHelper::sendMembershipApprovedEmail($row);
				}
			}
		}
	}

	/**
	 * Renew subscription for a given subscriber
	 *
	 * @param $id
	 *
	 * @return bool
	 */
	public function renew($id)
	{
		$rowOld = $this->getTable('Subscriber');
		$row    = $this->getTable('Subscriber');
		$rowOld->load($id);
		$data       = JArrayHelper::fromObject($rowOld);
		$data['id'] = 0;
		$row->bind($data);
		$row->published      = 1;
		$row->is_profile     = 0;
		$row->invoice_number = 0;
		$row->act            = 'renew';

		// Now, need to calculate subscription from date and to date
		$db       = $this->getDbo();
		$nullDate = $db->getNullDate();
		$query    = $db->getQuery(true);
		$query->select('*')
			->from('#__osmembership_plans')
			->where('id = ' . (int) $row->plan_id);
		$db->setQuery($query);
		$rowPlan = $db->loadObject();

		$row->created_date = JFactory::getDate()->toSql();

		$maxDate = null;
		if ($row->user_id > 0)
		{
			//Subscriber, user existed
			$query->clear();
			$query->select('MAX(to_date)')
				->from('#__osmembership_subscribers')
				->where('user_id=' . $row->user_id . ' AND plan_id=' . $row->plan_id . ' AND (published=1 OR (published = 0 AND payment_method LIKE "os_offline%"))');
			$db->setQuery($query);
			$maxDate = $db->loadResult();
		}
		if ($maxDate)
		{
			$date           = JFactory::getDate($maxDate);
			$row->from_date = $date->add(new DateInterval('P1D'))->toSql();
		}
		else
		{
			$date           = JFactory::getDate();
			$row->from_date = $date->toSql();
		}

		if ($rowPlan->expired_date && $rowPlan->expired_date != $nullDate)
		{
			$expiredDate = JFactory::getDate($rowPlan->expired_date, JFactory::getConfig()->get('offset'));

			// Change year of expired date to current year
			if ($date->year > $expiredDate->year)
			{
				$expiredDate->setDate($date->year, $expiredDate->month, $expiredDate->day);
			}

			$expiredDate->setTime(23, 59, 59);
			$date->setTime(23, 59, 59);

			$numberYears = 1;

			if ($rowPlan->subscription_length_unit == 'Y')
			{
				$numberYears = $rowPlan->subscription_length;
			}

			if ($date >= $expiredDate)
			{
				$numberYears++;
			}

			$expiredDate->setDate($expiredDate->year + $numberYears - 1, $expiredDate->month, $expiredDate->day);

			$row->to_date = $expiredDate->toSql();
		}
		else
		{
			if ($rowPlan->lifetime_membership)
			{
				$row->to_date = '2099-12-31 23:59:59';
			}
			else
			{
				$dateIntervalSpec = 'P' . $rowPlan->subscription_length . $rowPlan->subscription_length_unit;
				$row->to_date     = $date->add(new DateInterval($dateIntervalSpec))->toSql();
			}
		}

		$row->store();

		// Insert data for custom fields
		$sql = "INSERT INTO #__osmembership_field_value(subscriber_id ,field_id, field_value) SELECT $row->id, field_id, field_value FROM #__osmembership_field_value WHERE  subscriber_id= " . $rowOld->id;
		$db->setQuery($sql);
		try
		{
			$db->execute();
		}
		catch (Exception $e)
		{

		}

		JPluginHelper::importPlugin('osmembership');
		$dispatcher = JEventDispatcher::getInstance();
		$dispatcher->trigger('onAfterStoreSubscription', array($row));
		$dispatcher->trigger('onMembershipActive', array($row));

		return true;
	}

	/**
	 * Send batch emails to selected subscriptions by quangnv
	 *
	 * @param RADInput $input
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
		$query->clear();
		$query->select('name')
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
}
