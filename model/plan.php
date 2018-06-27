<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

class OSMembershipModelPlan extends MPFModel
{
	/**
	 * Constructor
	 *
	 * @param array $config
	 *
	 * @throws Exception
	 */
	public function __construct($config)
	{
		parent::__construct($config);
		$this->state->insert('id', 'int', 0);
	}

	/**
	 * Get plan information from database
	 *
	 * @return mixed
	 */
	public function getData()
	{
		$config            = OSMembershipHelper::getConfig();
		$showUpgradeButton = isset($config->show_upgrade_button) ? $config->show_upgrade_button : 1;
		$db                = $this->getDbo();
		$query             = $db->getQuery(true);
		$query->select('tbl.*')
			->from('#__osmembership_plans AS tbl')
			->where('tbl.id = ' . $this->state->id)
			->where('published = 1')
			->where('access IN (' . implode(',', JFactory::getUser()->getAuthorisedViewLevels()) . ')');

		if ($fieldSuffix = OSMembershipHelper::getFieldSuffix())
		{
			$fields = [
				'tbl.title',
				'tbl.short_description',
				'tbl.description',
				'tbl.page_title',
				'tbl.page_heading',
				'tbl.meta_keywords',
				'tbl.meta_description',
			];

			OSMembershipHelperDatabase::getMultilingualFields($query, $fields, $fieldSuffix);
		}

		$db->setQuery($query);
		$row = $db->loadObject();

		if (JFactory::getUser()->id && $showUpgradeButton)
		{
			$upgradeRules = OSMembershipHelperSubscription::getUpgradeRules();

			if (count($upgradeRules))
			{
				$planUpgradeRules = [];

				foreach ($upgradeRules as $rule)
				{
					$planUpgradeRules[$rule->to_plan_id][] = $rule;
				}

				if (isset($planUpgradeRules[$row->id]))
				{
					$row->upgrade_rules = $planUpgradeRules[$row->id];
				}
			}
		}

		return $row;
	}
}
