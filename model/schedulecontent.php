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

class OSMembershipModelSchedulecontent extends MPFModelList
{
	/**
	 * Clear join clause for getTotal method
	 *
	 * @var bool
	 */
	protected $clearJoin = false;

	/**
	 * Instantiate the model.
	 *
	 * @param array $config configuration data for the model
	 */
	public function __construct($config = array())
	{
		$config['table'] = '#__osmembership_schedulecontent';

		parent::__construct($config);
	}

	/**
	 * Build the query object which is used to get list of records from database
	 *
	 * @return JDatabaseQuery
	 */
	protected function buildListQuery()
	{
		$query = $this->query;

		$activePlanIds = array_keys(OSMembershipHelperSubscription::getUserSubscriptionsInfo());
		if (empty($activePlanIds))
		{
			$activePlanIds = array(0);
		}

		$query->select('a.id, a.catid, a.title, a.alias, a.hits, c.title AS category_title, b.plan_id, b.number_days')
			->from('#__content AS a')
			->innerJoin('#__categories AS c ON a.catid = c.id')
			->innerJoin('#__osmembership_schedulecontent AS b ON a.id = b.article_id')
			->where('b.plan_id IN (' . implode(',', $activePlanIds) . ')')
			->where('a.state = 1')
			->order('plan_id')
			->order('a.ordering');

		return $query;
	}
}
