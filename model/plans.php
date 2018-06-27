<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

use Joomla\Utilities\ArrayHelper;

class OSMembershipModelPlans extends MPFModelList
{
	/**
	 * Instantiate the model.
	 *
	 * @param array $config configuration data for the model
	 */
	public function __construct($config = array())
	{
		$config['remember_states'] = false;

		parent::__construct($config);

		$this->state->insert('id', 'int', 0)
			->insert('filter_plan_ids', '');
	}

	/**
	 * Builds SELECT columns list for the query
	 *
	 * @param JDatabaseQuery $query
	 *
	 * @return $this
	 */
	protected function buildQueryColumns(JDatabaseQuery $query)
	{
		$query->select('tbl.*');

		if ($fieldSuffix = OSMembershipHelper::getFieldSuffix())
		{
			OSMembershipHelperDatabase::getMultilingualFields($query, ['tbl.title', 'tbl.short_description', 'tbl.description'], $fieldSuffix);
		}

		return $this;
	}

	/**
	 * Builds a WHERE clause for the query
	 *
	 * @param JDatabaseQuery $query
	 *
	 * @return $this
	 */
	protected function buildQueryWhere(JDatabaseQuery $query)
	{
		$db       = $this->getDbo();
		$config   = OSMembershipHelper::getConfig();
		$nullDate = $db->quote($db->getNullDate());
		$nowDate  = $db->quote(JHtml::_('date', 'now', 'Y-m-d H:i:s', false));
		$query->where('tbl.published = 1')
			->where('tbl.access IN (' . implode(',', JFactory::getUser()->getAuthorisedViewLevels()) . ')')
			->where('(tbl.publish_up = ' . $nullDate . ' OR tbl.publish_up <= ' . $nowDate . ')')
			->where('(tbl.publish_down = ' . $nullDate . ' OR tbl.publish_down >= ' . $nowDate . ')');

		if ($this->state->id)
		{
			$query->where('tbl.category_id = ' . $this->state->id);
		}

		if (!empty($config->hide_active_plans))
		{
			$activePlanIds = OSMembershipHelperSubscription::getActiveMembershipPlans();

			if (count($activePlanIds) > 1)
			{
				$query->where('tbl.id NOT IN (' . implode(',', $activePlanIds) . ')');
			}
		}

		if ($this->state->filter_plan_ids)
		{
			$planIds = $this->state->filter_plan_ids;

			if (strpos($planIds, 'cat-') !== false)
			{
				$catId = (int) substr($planIds, 4);
				$query->where('tbl.category_id = ' . $catId);
			}
			elseif ($planIds != '*')
			{
				$planIds = explode(',', $planIds);
				$planIds = ArrayHelper::toInteger($planIds);
				$planIds = implode(',', $planIds);
				$query->where('tbl.id IN (' . $planIds . ')');
			}
		}

		return $this;
	}

	/**
	 * Get upgrade options for each plan
	 *
	 * @param array $rows
	 */
	protected function beforeReturnData($rows)
	{
		$config            = OSMembershipHelper::getConfig();
		$showUpgradeButton = isset($config->show_upgrade_button) ? $config->show_upgrade_button : 1;

		if (JFactory::getUser()->id && $showUpgradeButton)
		{
			$upgradeRules = OSMembershipHelperSubscription::getUpgradeRules();

			if (!count($upgradeRules))
			{
				return;
			}

			$planUpgradeRules = [];

			foreach ($upgradeRules as $rule)
			{
				$planUpgradeRules[$rule->to_plan_id][] = $rule;
			}

			foreach ($rows as $row)
			{
				if (isset($planUpgradeRules[$row->id]))
				{
					$row->upgrade_rules = $planUpgradeRules[$row->id];
				}
			}
		}
	}
}
