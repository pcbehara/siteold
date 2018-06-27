<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die;

class OSMembershipHelperDatabase
{
	/**
	 * Get category data from database
	 *
	 * @param $id
	 *
	 * @return mixed
	 */
	public static function getCategory($id)
	{
		$db          = JFactory::getDbo();
		$query       = $db->getQuery(true);
		$fieldSuffix = OSMembershipHelper::getFieldSuffix();
		$query->select('*')
			->from('#__osmembership_categories')
			->where('id=' . (int) $id);

		if ($fieldSuffix)
		{
			self::getMultilingualFields($query, array('title', 'description'), $fieldSuffix);
		}

		$db->setQuery($query);

		return $db->loadObject();
	}

	/**
	 * Get category data from database
	 *
	 * @param $id
	 *
	 * @return mixed
	 */
	public static function getPlan($id)
	{
		$db          = JFactory::getDbo();
		$query       = $db->getQuery(true);
		$fieldSuffix = OSMembershipHelper::getFieldSuffix();
		$query->select('*')
			->from('#__osmembership_plans')
			->where('id=' . (int) $id);

		if ($fieldSuffix)
		{
			self::getMultilingualFields($query, array('title', 'short_description', 'description'), $fieldSuffix);
		}

		$db->setQuery($query);

		return $db->loadObject();
	}

	/**
	 * Get all published subscription plans
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public static function getAllPlans($key = '')
	{
		$db          = JFactory::getDbo();
		$query       = $db->getQuery(true);
		$fieldSuffix = OSMembershipHelper::getFieldSuffix();
		$query->select('*')
			->from('#__osmembership_plans')
			->where('published = 1');

		if ($fieldSuffix)
		{
			self::getMultilingualFields($query, array('title', 'short_description', 'description'), $fieldSuffix);
		}

		$db->setQuery($query);

		return $db->loadObjectList($key);
	}

	/**
	 * Helper method to get fields from database table in case the site is multilingual
	 *
	 * @param JDatabaseQuery $query
	 * @param array          $fields
	 * @param string         $fieldSuffix
	 */
	public static function getMultilingualFields(JDatabaseQuery $query, $fields = array(), $fieldSuffix)
	{
		$db = JFactory::getDbo();

		foreach ($fields as $field)
		{
			$alias  = $field;
			$dotPos = strpos($field, '.');

			if ($dotPos !== false)
			{
				$alias = substr($field, $dotPos + 1);
			}

			$query->select($db->quoteName($field . $fieldSuffix, $alias));
		}
	}

	/**
	 * Method to get a upgrade rule base on given id
	 *
	 * @param int $id
	 *
	 * @return stdClass|null
	 */
	public static function getUpgradeRule($id)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__osmembership_upgraderules')
			->where('id = ' . (int) $id);
		$db->setQuery($query);

		return $db->loadObject();
	}

	/**
	 * Method to get a renew option
	 *
	 * @param int $id
	 *
	 * @return stdClass|null
	 */
	public static function getRenewOption($id)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__osmembership_renewrates')
			->where('id = ' . (int) $id);
		$db->setQuery($query);

		return $db->loadObject();
	}
}
