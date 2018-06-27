<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die;

class OSMembershipModelUrls extends MPFModelList
{
	protected function buildListQuery()
	{
		$query = $this->query;

		$activePlanIds = array_keys(OSMembershipHelperSubscription::getUserSubscriptionsInfo());

		if (empty($activePlanIds))
		{
			$activePlanIds = array(0);
		}

		$query->select('*')
			->from('#__osmembership_urls')
			->where('plan_id IN (' . implode(',', $activePlanIds) . ')')
			->order('id');
		
		return $query;
	}
}
