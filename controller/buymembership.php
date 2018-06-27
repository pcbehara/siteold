<?php
/**
 * @package        Joomla
 * @subpackage     OSMembership
 * @author         Lee L - www.jumazi.com
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

// no direct access
defined('_JEXEC') or die;

class OSMembershipControllerBuymembership extends OSMembershipController
{
	/**
	 * Buymembership user Buymembership data
	 */
	public function Buymembership()
	{
		
		// Get a db connection.
		$paymentMethod = 'os_cloud_payment';
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query
			->select($db->quoteName(array('params')))
			->from($db->quoteName('#__osmembership_plugins', 'a'))
			->where($db->quoteName('a.name')." = ".$db->quote($paymentMethod));
		$db->setQuery($query);
		$plugin = $db->loadObject();
		$params = $plugin->params;
		$params = new JRegistry($params);
		$aid = $params->get('cp_api_id_login');


		$Itemid     = $this->input->getInt('Itemid', 0);
		$data       = $this->input->getData();




		$subscriber_id  = $data['id'];
		$new_email  = $data['email'];

		$db      = JFactory::getDbo();

    



   
		

		try
		{
			$model->BuymembershipUpdate($data, $this->input);
			$message = JText::_('OSM_YOUR_PROFILE_UPDATED');
			$type = 'message';

			
         

		}
		catch(Exception $e)
		{
			$message = $e->getMessage();
			$type = 'error';
		}

		//Redirect to the profile page
		$this->setRedirect(JRoute::_('index.php?option=com_osmembership&view=Buymembership&Itemid=' . $Itemid), $message, $type);
	}
}
