<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die;

class OSMembershipModelDocuments extends MPFModelList
{
	/**
	 * Builds a WHERE clause for the query
	 *
	 * @param JDatabaseQuery $query
	 *
	 * @return $this
	 */
	protected function buildQueryWhere(JDatabaseQuery $query)
	{
		$activePlanIds = OSMembershipHelper::getActiveMembershipPlans();

		if (empty($activePlanIds))
		{
			$activePlanIds = array(0);
		}

		$query->where('tbl.plan_id IN (' . implode(',', $activePlanIds) . ')');

		return $this;
	}

	/**
	 * Builds a generic ORDER BY clause based on the model's state
	 *
	 * @param JDatabaseQuery $query
	 *
	 * @return $this
	 */
	protected function buildQueryOrder(JDatabaseQuery $query)
	{
		$query->order('tbl.plan_id, tbl.ordering');

		return $this;
	}
}
