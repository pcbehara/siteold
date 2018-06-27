<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die;

class OSMembershipModelProfile extends MPFModel
{
	use OSMembershipModelSubscriptiontrait;

	/**
	 * Get profile data of the users
	 */
	public function getData()
	{
		$user  = JFactory::getUser();
		$db    = $this->getDbo();
		$query = $db->getQuery(true);
		$query->select('a.*, b.username')
			->from('#__osmembership_subscribers AS a ')
			->leftJoin('#__users AS b ON a.user_id=b.id')
			->where('is_profile=1')
			->where("(a.email='$user->email' OR user_id=$user->id)")
			->order('id DESC');

		$db->setQuery($query);

		return $db->loadObject();
	}

	/**
	 * Update profile of the user
	 *
	 * @param array    $data
	 * @param MPFInput $input
	 *
	 * @return boolean
	 * @throws Exception
	 */
	public function updateProfile($data, $input)
	{
		$db   = $this->getDbo();
		$user = JFactory::getUser();

		/* @var OSMembershipTableSubscriber $row */
		$row = $this->getTable('Subscriber');

		if (!$row->load($data['id']))
		{
			throw new Exception('Invalid Subscription Record', 404);
		}

		if ($row->user_id != $user->id)
		{
			throw new Exception('You cannot update profile data of other user', 403);
		}

		//Store custom field data for this profile record
		if (OSMembershipHelper::isUniquePlan($user->id))
		{
			$planId = $row->plan_id;
		}
		else
		{
			$planId = 0;
		}

		list($rowFields, $formFields) = $this->getFields($planId);

		$oldData = OSMembershipHelper::getProfileData($row, $planId, $rowFields);

		$query = $db->getQuery(true);
		$query->select('COUNT(*)')
			->from('#__users')
			->where('email = ' . $db->quote($data['email']))
			->where('id != ' . $row->user_id);
		$db->setQuery($query);
		$total = $db->loadResult();

		// Upload avatar image
		$avatar = $input->files->get('profile_avatar');

		if ($avatar['name'])
		{
			$this->uploadAvatar($avatar, $row);
		}

		$userData         = array();
		$userData['name'] = trim($data['first_name'] . ' ' . $data['last_name']);

		if (!$total)
		{
			$userData['email'] = $data['email'];
		}

		if ($data['password'])
		{
			$userData['password2'] = $userData['password'] = $data['password'];
		}

		$params = JComponentHelper::getParams('com_users');

		if ($params->get('change_login_name'))
		{
			$username    = $data['username'];
			$filterInput = JFilterInput::getInstance();
			$username    = $filterInput->clean($username, 'USERNAME');

			$isUsernameCompliant = !(preg_match('#[<>"\'%;()&\\\\]|\\.\\./#', $username) || strlen(utf8_decode($username)) < 2
				|| trim($username) != $username);

			if ($isUsernameCompliant)
			{
				// Check and make sure the username doesn't exist
				$query->clear()
					->select('COUNT(*)')
					->from('#__users')
					->where('username = ' . $db->quote($username))
					->where('id != ' . $row->user_id);
				$db->setQuery($query);
				$total = $db->loadResult();

				if (!$total)
				{
					$userData['username'] = $username;
				}
			}
		}

		// Remove empty values to avoid saving user data error
		$userData = array_filter($userData, function ($value) {
			return strlen($value) > 0;
		});

		$row->bind($data);

		if (!$row->check())
		{
			throw new Exception($row->getError());
		}

		if (!$row->store())
		{
			throw new Exception($row->getError());
		}

		$form = new MPFForm($formFields);
		$form->storeFormData($row->id, $data);

		//Synchronize profile data of other subscription records from this subscriber
		$config = OSMembershipHelper::getConfig();

		if ($config->synchronize_data !== '0')
		{
			OSMembershipHelperSubscription::synchronizeProfileData($row, $rowFields);
		}

		//Trigger event	onProfileUpdate event
		JPluginHelper::importPlugin('osmembership');
		JFactory::getApplication()->triggerEvent('onProfileUpdate', array($row));

		// Saving user data
		$user->bind($userData);

		if (!$user->save(true))
		{
			JFactory::getApplication()->enqueueMessage($user->getError(), 'warning');
		}

		$newData = OSMembershipHelper::getProfileData($row, $planId, $rowFields);

		$updatedFields = array();

		foreach ($rowFields as $rowField)
		{
			if (isset($oldData[$rowField->name], $newData[$rowField->name]) && $oldData[$rowField->name] != $newData[$rowField->name])
			{
				$updatedField            = new stdClass;
				$updatedField->title     = $rowField->title;
				$updatedField->old_value = $oldData[$rowField->name];
				$updatedField->new_value = $newData[$rowField->name];

				$updatedFields[] = $updatedField;
			}
		}

		OSMembershipHelperMail::sendProfileUpdateEmail($row, $config, $updatedFields);

		return true;
	}

	/**
	 * Method to update subscription credit card
	 *
	 * @param array $data
	 *
	 * @throws Exception
	 */
	public function updateCard($data)
	{
		$subscription = OSMembershipHelperSubscription::getSubscription($data['subscription_id']);

		if (!$subscription)
		{
			throw new Exception(JText::sprintf('Subscription ID %s not found', $data['subscription_id']));
		}

		$method = os_payments::getPaymentMethod($subscription->payment_method);

		if (method_exists($method, 'updateCard'))
		{
			$method->updateCard($data, $subscription);
		}
		else
		{
			throw new Exception(JText::sprintf('Payment method %s does not support update credit card', $subscription->payment_method));
		}
	}

	/**
	 * Get custom fields for the subscription
	 *
	 * @param int    $planId
	 * @param bool   $loadCoreFields
	 * @param string $language
	 * @param string $action
	 *
	 * @return array
	 */
	protected function getFields($planId, $loadCoreFields = true, $language = null, $action = null)
	{
		$rowFields  = OSMembershipHelper::getProfileFields($planId, $loadCoreFields, $language, $action);
		$formFields = [];

		// Remove message and heating custom fields type as it is not needed for calculation and storing data
		for ($i = 0, $n = count($rowFields); $i < $n; $i++)
		{
			$rowField = $rowFields[$i];

			// These fields could not be changed (fee field, field not being editable on profile), thus need to be removed
			if (in_array($rowField->fieldtype, ['Heading', 'Message'])
				|| !$rowField->can_edit_on_profile
				|| $rowField->fee_field)
			{
				unset($rowFields[$i]);

				continue;
			}

			if (!$rowField->is_core)
			{
				$formFields[] = $rowField;
			}
		}

		reset($rowFields);

		return [$rowFields, $formFields];
	}
}
