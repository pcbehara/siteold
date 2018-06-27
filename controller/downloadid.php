<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

class OSMembershipControllerDownloadId extends OSMembershipController
{
	/**
	 * Generate Download IDs for user
	 *
	 * @throws Exception
	 */
	public function generate_download_ids()
	{
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		if (!JFactory::getUser()->id)
		{
			throw new Exception('OSM_GUEST_COULD_NOT_GENERATE_DOWNLOAD_IDS', 403);
		}

		$numberDownloadIds = $this->input->post->getInt('number_download_ids', 1);

		if (!$numberDownloadIds)
		{
			$numberDownloadIds = 1;
		}

		/* @var OSMembershipModelDownloadids $model */
		$model = $this->getModel('Downloadids');
		$model->generateDownloadIds($numberDownloadIds);

		$this->setRedirect(JRoute::_('index.php?option=com_osmembership&view=downloadidsItemid=' . $this->input->getInt('Itemid')), JText::sprintf('OSM_COUNT_DOWNLOAD_ID_GENERATED', $numberDownloadIds));
	}
}