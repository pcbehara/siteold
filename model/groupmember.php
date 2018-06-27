<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die;

/**
 * Membership Pro Component Groupmember Model
 *
 * @package        Joomla
 * @subpackage     Membership Pro
 */
class OSMembershipModelGroupmember extends MPFModel
{
	/**
	 * Instantiate the model.
	 *
	 * @param array $config configuration data for the model
	 */
	public function __construct($config = array())
	{
		$config['table'] = '#__osmembership_subscribers';

		parent::__construct($config);

		$this->state->insert('id', 'int', 0);
	}

	/**
	 * Initialize data for group member
	 *
	 *
	 * @return JTable
	 */
	public function getData()
	{
		$row = $this->getTable('Subscriber');
		if ($this->state->id)
		{
			$row->load($this->state->id);
		}

		return $row;
	}

	/**
	 * Override store function to perform specific saving
	 * @see OSModel::store()
	 */
	public function store(&$data)
	{
		$row   = $this->getTable('Subscriber');
		$isNew = true;

		if (!$data['id'] && $data['username'] && $data['password'] && empty($data['user_id']))
		{
			$data['user_id'] = OSMembershipHelper::saveRegistration($data);
		}

		if ($data['id'])
		{
			$isNew = false;
			$row->load($data['id']);
		}

		$row->bind($data);

		if ($isNew)
		{
			$row->user_id        = (int) $row->user_id;
			$row->published      = 1;
			$row->group_admin_id = JFactory::getUser()->id;
			$row->created_date   = gmdate('Y-m-d H:i:s');
			$row->from_date      = gmdate('Y-m-d H:i:s');
			$row->is_profile     = 1;

			// Calculate to_date
			$db    = $this->getDbo();
			$query = $db->getQuery(true);
			$query->select('MAX(to_date)')
				->from('#__osmembership_subscribers')
				->where('user_id=' . $row->group_admin_id . ' AND plan_id=' . $row->plan_id . ' AND published = 1');
			$db->setQuery($query);
			$row->to_date = $db->loadResult();
		}

		if (!$row->store())
		{
			throw new Exception($row->getError());
		}

		if ($isNew)
		{
			$row->profile_id = $row->id;
			$row->store();
		}

		$rowFields = OSMembershipHelper::getProfileFields($row->plan_id, false);
		$form      = new MPFForm($rowFields);
		$form->storeData($row->id, $data);

		if ($isNew && $row->user_id)
		{
			JPluginHelper::importPlugin('osmembership');
			$app = JFactory::getApplication();
			$app->triggerEvent('onAfterStoreSubscription', array($row));
			$app->triggerEvent('onMembershipActive', array($row));

			OSMembershipHelperMail::sendNewGroupMemberEmail($row);
		}

		return true;
	}

	/**
	 * Delete group member record
	 *
	 * @param array $id
	 *
	 * @return bool
	 */
	public function deleteMember($id)
	{
		$row = $this->getTable('Subscriber');
		$row->load($id);

		if ($row)
		{
			$db    = $this->getDbo();
			$query = $db->getQuery(true);
			$query->delete('#__osmembership_field_value')
				->where('subscriber_id = ' . $id);
			$db->setQuery($query);
			$db->execute();

			JPluginHelper::importPlugin('osmembership');
			JFactory::getApplication()->triggerEvent('onMembershipExpire', array($row));

			if ($row->user_id)
			{
				// If there is only one subscription record, we will delete Joomla account as well
				$query->clear()
					->select('COUNT(*)')
					->from('#__osmembership_subscribers')
					->where('user_id = ' . $row->user_id);
				$db->setQuery($query);
				$total = (int) $db->loadResult();

				/*if ($total == 1)
				{
					// Only one record
					$rowUser = new JUser();
					$rowUser->load($row->user_id);

					if ($rowUser)
					{
						$rowUser->delete();
					}
				}*/
			}

			// Delete the subscription record
			$row->delete();
		}
	}
}
