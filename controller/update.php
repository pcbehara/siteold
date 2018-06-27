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

class OSMembershipControllerUpdate extends OSMembershipController
{
	/**
	 * Update user Update data
	 */
	public function update()
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


		/**@var OSMembershipModelUpdate $model **/
		$model      = $this->getModel();
		$data['id'] = (int) $data['cid'][0];


		$subscriber_id  = $data['id'];
		$new_email  = $data['email'];

		$db      = JFactory::getDbo();

        // Get $user_id
		$sql2="SELECT user_id FROM `#__osmembership_subscribers` WHERE id=" . $subscriber_id . " ; ";
		$db->setQuery($sql2);
		$db->query();
		$user_id = $db->loadResult();

        // Get $acid	
		$query = $db->getQuery(true);
		$query->select('acid')
			  ->from('#__osmembership_users_acid')
			  ->where('user_id = ' . $db->quote($user_id));
		$db->setQuery($query);
		$acid = (int) $db->loadResult();


        // Get $old_email
        $query_x = $db->getQuery(true);
		$query_x->select('email')
				->from('#__users')
				->where('id = ' . $db->quote($user_id));
		$db->setQuery($query_x);
		$old_email = $db->loadResult();
               
		/*
		echo "<pre>";
		print_r($acid);
		echo "<pre>";

		echo "<pre>";
		print_r($data);
		echo "<pre>";

		echo "<pre>";
		print_r('https://credit.j-payment.co.jp/gateway/accgate.aspx?aid=101964&cmd=1&acid='.$acid.'&em='.$new_email);
		echo "</pre>";

		exit();
		*/

		try
		{
			$model->updateUpdate($data, $this->input);
			$message = JText::_('OSM_YOUR_PROFILE_UPDATED');
			$type = 'message';

			
            if($new_email <> $old_email)
            {                          

	            $ch_x = curl_init();

				curl_setopt($ch_x, CURLOPT_URL, 'https://credit.j-payment.co.jp/gateway/accgate.aspx?aid='.$aid.'&cmd=1&acid='.$acid.'&em='.$new_email);

				// curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch_x, CURLOPT_RETURNTRANSFER, true);

				// curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
				curl_setopt($ch_x, CURLOPT_HTTPHEADER, false);

				curl_setopt($ch_x, CURLOPT_CONNECTTIMEOUT, 15);

				$jp_result_x = curl_exec($ch_x); 

            } 

		}
		catch(Exception $e)
		{
			$message = $e->getMessage();
			$type = 'error';
		}

		//Redirect to the profile page
		$this->setRedirect(JRoute::_('index.php?option=com_osmembership&view=Update&Itemid=' . $Itemid), $message, $type);
	}
}
