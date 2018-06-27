<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

JLoader::register('OSMembershipControllerSubscription', JPATH_ADMINISTRATOR . '/components/com_osmembership/controller/subscription.php');
JLoader::register('OSMembershipModelSubscriptions', JPATH_ADMINISTRATOR . '/components/com_osmembership/model/subscriptions.php');
JLoader::register('OSMembershipModelSubscription', JPATH_ADMINISTRATOR . '/components/com_osmembership/model/subscription.php');
JLoader::register('OSMembershipController', JPATH_ADMINISTRATOR . '/components/com_osmembership/controller/controller.php');

/**
 * OSMembership Plugin controller
 *
 * @package        Joomla
 * @subpackage     Membership Pro
 */
class OSMembershipControllerSubscriber extends OSMembershipControllerSubscription
{
	/**
	 * Method to display a view
	 *
	 * This function is provide as a default implementation, in most cases
	 * you will need to override it in your own controllers.
	 *
	 * @param boolean $cachable  If true, the view output will be cached
	 *
	 * @param array   $urlparams An array of safe url parameters and their variable types, for valid values see {@link JFilterInput::clean()}.
	 *
	 * @return MPFController A MPFController object to support chaining.
	 *
	 * @throws Exception
	 */

	public function display($cachable = false, array $urlparams = array())
	{
		$user = JFactory::getUser();

		if (!$user->authorise('membershippro.subscriptions', 'com_osmembership'))
		{
			if ($user->id)
			{
				throw new Exception('You do not have subscriptions management permission', 403);
			}
			else
			{
				$active = $this->app->getMenu()->getActive();

				$option = isset($active->query['option']) ? $active->query['option'] : '';
				$view   = isset($active->query['view']) ? $active->query['view'] : '';

				if ($option == 'com_osmembership' && $view == 'subscribers')
				{
					$returnUrl = 'index.php?Itemid=' . $active->id;
				}
				else
				{
					$returnUrl = JUri::getInstance()->toString();
				}

				$this->app->redirect(JRoute::_('index.php?option=com_users&view=login&return=' . base64_encode($returnUrl), false));
			}
		}

		/* @var JDocumentHtml $document */
		$document = JFactory::getDocument();

		$rootUri = JUri::base(true);

		$document->addStyleSheet($rootUri . '/media/com_osmembership/assets/css/style.css', 'text/css', null, null);

		$customCssFile = JPATH_ROOT . '/media/com_osmembership/assets/css/custom.css';

		if (file_exists($customCssFile) && filesize($customCssFile) > 0)
		{
			$document->addStyleSheet($rootUri . '/media/com_osmembership/assets/css/custom.css', 'text/css', null, null);
		}

		JHtml::_('jquery.framework');

		OSMembershipHelper::loadBootstrap(true);

		JHtml::_('script', 'media/com_osmembership/assets/js/jquery-noconflict.js', false, false);

		$document->addScript($rootUri . '/media/com_osmembership/assets/js/ajaxupload.min.js');

		return parent::display($cachable, $urlparams);
	}

	/**
	 * Get url of the page which display list of records
	 *
	 * @return string
	 */
	protected function getViewListUrl()
	{
		return 'index.php?option=com_osmembership&view=subscribers&Itemid=' . $this->input->getInt('Itemid', OSMembershipHelperRoute::findView('subscribers'));
	}

	/**
	 * Get url of the page which allow adding/editing a record
	 *
	 * @param int $recordId
	 *
	 * @return string
	 */
	protected function getViewItemUrl($recordId = null)
	{
		$url = 'index.php?option=' . $this->option . '&view=' . $this->viewItem;

		if ($recordId)
		{
			$url .= '&id=' . $recordId;
		}

		$url .= '&Itemid=' . $this->input->getInt('Itemid', OSMembershipHelperRoute::findView('subscribers'));

		return $url;
	}

	/**
	 * Method to check if you can add a new record.
	 *
	 * Extended classes can override this if necessary.
	 *
	 * @param   array $data An array of input data.
	 *
	 * @return  boolean
	 */
	protected function allowAdd($data = array())
	{
		if (!JFactory::getUser()->authorise('membershippro.subscriptions', 'com_osmembership'))
		{
			return false;
		}

		return parent::allowAdd($data);
	}

	/**
	 * Method to check if you can edit a new record.
	 *
	 * Extended classes can override this if necessary.
	 *
	 * @param   array  $data An array of input data.
	 * @param   string $key  The name of the key for the primary key; default is id.
	 *
	 * @return  boolean
	 */
	protected function allowEdit($data = array(), $key = 'id')
	{
		if (!JFactory::getUser()->authorise('membershippro.subscriptions', 'com_osmembership'))
		{
			return false;
		}

		return parent::allowEdit($data, $key);
	}

	/**
	 * Method to check whether the current user is allowed to delete a record
	 *
	 * @param   int $id Record ID
	 *
	 * @return  boolean  True if allowed to delete the record. Defaults to the permission for the component.
	 */
	protected function allowDelete($id)
	{
		if (!JFactory::getUser()->authorise('membershippro.subscriptions', 'com_osmembership'))
		{
			return false;
		}

		return parent::allowDelete($id);
	}

	/**
	 * Method to check whether the current user can change status (publish, unpublish of a record)
	 *
	 * @param   int $id Id of the record
	 *
	 * @return  boolean  True if allowed to change the state of the record. Defaults to the permission for the component.
	 */
	protected function allowEditState($id)
	{
		if (!JFactory::getUser()->authorise('membershippro.subscriptions', 'com_osmembership'))
		{
			return false;
		}

		return parent::allowEditState($id);
	}
}
