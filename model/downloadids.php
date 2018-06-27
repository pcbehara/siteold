<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

class OSMembershipModelDownloadids extends MPFModelList
{
	/**
	 * Constructor.
	 *
	 * @param array $config
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		$this->state->set('filter_order', 'tbl.id');
		$this->state->set('filter_order_Dir', 'DESC');
	}

	/**
	 * @param JDatabaseQuery $query
	 *
	 * @return $this
	 */
	protected function buildQueryWhere(JDatabaseQuery $query)
	{
		$query->where('tbl.user_id = ' . JFactory::getUser()->id);

		return $this;
	}

	/**
	 * Generate download Ids for the current user
	 *
	 * @param int $numberIds
	 */
	public function generateDownloadIds($numberIds = 1)
	{
		$user        = JFactory::getUser();
		$db          = $this->getDbo();
		$query       = $db->getQuery(true);
		$createdDate = JFactory::getDate('now')->toSql();

		$columns = array(
			'user_id',
			'download_id',
			'created_date',
			'published'
		);

		$query->insert('#__osmembership_downloadids')
			->columns($db->quoteName($columns));

		for ($i = 0; $i < $numberIds; $i++)
		{
			$downloadId = strtoupper(md5($user->username . JFactory::getConfig()->get('secret') . JUserHelper::genRandomPassword(10) . time()));
			$values     = [$user->id, $db->quote($downloadId), $db->quote($createdDate), 1];
			$query->values(implode(',', $values));
		}

		$db->setQuery($query);
		$db->execute();
	}
}