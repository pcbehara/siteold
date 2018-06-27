<?php
/**
 * @package        Joomla
 * @subpackage     OSMembership
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

class OSMembershipControllerApi extends MPFController
{
	/**
	 * Method to add a subscription record to the system
	 *
	 * @throws Exception
	 */
	public function add()
	{
		// Validate the request
		$this->validateAPIRequest();

		$data = $this->input->getData();

		// Make sure id is not provided on a add request
		if (isset($data['id']))
		{
			unset($data['id']);
		}

		/* @var OSMembershipModelApi $model */
		$model  = $this->getModel('Api', ['ignore_request' => true]);
		$result = $model->store($data);

		// There are something wrong with the provided data
		if (is_array($result) && count($result))
		{
			$success = false;
			$data    = $result;
		}
		else
		{
			$success = true;
			$data    = $model->getSubscriptionData($result->id);
		}

		$this->sendResponse($success, $data);
	}

	/**
	 * API Method to update an existing subscription
	 *
	 * @throws Exception
	 */
	public function update()
	{
		// Validate the request
		$this->validateAPIRequest();

		$data = $this->input->getData();


		/* @var OSMembershipModelApi $model */
		$model  = $this->getModel('Api', ['ignore_request' => true]);
		$result = $model->store($data);

		// There are something wrong with the provided data
		if (is_array($result) && count($result))
		{
			$success = false;
			$data    = $result;
		}
		else
		{
			$success = true;
			$data    = $model->getSubscriptionData($result->id);
		}

		$this->sendResponse($success, $data);
	}

	/**
	 * Method to get data of a subscription
	 *
	 * @throws Exception
	 */
	protected function get()
	{
		// Validate the request
		$this->validateAPIRequest();

		// Get ID of the subscription from API and call API to get the details
		$id = $this->input->getInt('id');

		/* @var OSMembershipModelApi $model */
		$model = $this->getModel('Api', ['ignore_request' => true]);
		$data  = $model->getSubscriptionData($id);

		if ($data === false)
		{
			$success = false;
			$data    = [];
			$data[]  = JText::sprintf('OSM_INVALID_SUBSCRIPTION_ID', $data['id']);
		}
		else
		{
			$success = true;
		}

		$this->sendResponse($success, $data);
	}

	/**
	 * Method to get id of active subscription of given users
	 *
	 * @throws Exception
	 */
	public function get_active_plan_ids()
	{
		$this->validateAPIRequest();

		$userId      = $this->input->getInt('user_id');
		$activePlans = OSMembershipHelperSubscription::getActiveMembershipPlans($userId);

		array_shift($activePlans);

		$this->sendResponse(true, $activePlans);
	}

	/**
	 * Basic API validation, should be called before each request
	 *
	 * @throws Exception
	 */
	protected function validateAPIRequest()
	{
		$config = OSMembershipHelper::getConfig();

		// Check and make sure API is enabled
		if (!$config->enable_api)
		{
			throw new Exception(JText::_('API is not enabled on this site'));
		}

		// Check API Key
		$apiKey = $this->input->post->getString('api_key');

		if ($apiKey !== $config->api_key)
		{
			throw new Exception(sprintf('The provided API Key %s is invalid', $apiKey));
		}
	}

	/**
	 * Send json response to the API call
	 *
	 * @param bool  $success
	 * @param array $data
	 */
	protected function sendResponse($success, $data)
	{
		$response['success'] = $success;

		if ($success)
		{
			$response['data'] = $data;
		}
		else
		{
			$response['errors'] = $data;
		}

		echo json_encode($response);
		$this->app->close();
	}
}