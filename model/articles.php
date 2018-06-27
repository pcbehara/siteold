<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

use Joomla\Registry\Registry;

class OSMembershipModelArticles extends MPFModelList
{
	/**
	 * List of active subscription plans of the current logged in user
	 *
	 * @var array
	 */
	protected $activePlanIds = [];

	/**
	 * List of categories which current users can access to
	 *
	 * @var array
	 */
	protected $catIds = [];

	/**
	 * OSMembershipModelArticles constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config = array())
	{
		parent::__construct($config);

		$this->activePlanIds = OSMembershipHelper::getActiveMembershipPlans();
		$db                  = $this->getDbo();
		$query               = $db->getQuery(true);
		// Get categories
		$query->select('id, params')
			->from('#__osmembership_plans')
			->where('id IN (' . implode(',', $this->activePlanIds) . ')');
		$db->setQuery($query);
		$plans  = $db->loadObjectList();
		$catIds = [];

		foreach ($plans as $plan)
		{
			$params = new Registry($plan->params);

			if ($articleCategories = $params->get('article_categories'))
			{
				$catIds = array_merge($catIds, explode(',', $articleCategories));
			}
		}

		$this->catIds = $catIds;
	}

	/**
	 * Get data
	 *
	 * @return array
	 */
	public function getData()
	{
		if (count($this->activePlanIds) == 1)
		{
			return [];
		}

		return parent::getData();
	}

	/**
	 * Get total articles which user can access
	 *
	 * @return array|int
	 */
	public function getTotal()
	{
		if (count($this->activePlanIds) == 0)
		{
			return 0;
		}

		return parent::getTotal(); // TODO: Change the autogenerated stub
	}

	protected function buildListQuery()
	{
		$db         = $this->getDbo();
		$query      = $db->getQuery(true);
		$articleIds = [];

		if (count($this->catIds))
		{
			$query->select('a.id')
				->from('#__content AS a')
				->innerJoin('#__categories AS b ON a.catid = b.id')
				->where('b.id IN (' . implode(',', $this->catIds) . ')')
				->where('a.state = 1');
			$db->setQuery($query);
			$articleIds = array_merge($articleIds, $db->loadColumn());
		}

		$query->clear()
			->select('a.id')
			->from('#__content AS a')
			->innerJoin('#__osmembership_articles AS b ON a.id = b.article_id')
			->where('b.plan_id IN (' . implode(',', $this->activePlanIds) . ')')
			->where('a.state = 1');
		$db->setQuery($query);
		$articleIds = array_merge($articleIds, $db->loadColumn());

		if (empty($articleIds))
		{
			$articleIds = [0];
		}

		$query = $this->query;

		$query->select('a.id, a.catid, a.title, a.alias, a.hits, a.ordering, b.title AS category_title')
			->from('#__content AS a')
			->innerJoin('#__categories AS b ON a.catid = b.id')
			->where('a.id IN (' . implode(',', $articleIds) . ')')
			->order('a.ordering');

		return $query;
	}
}