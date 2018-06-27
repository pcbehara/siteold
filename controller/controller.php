<?php
/**
 * @package        Joomla
 * @subpackage     OSMembership
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

// no direct access
defined('_JEXEC') or die;

class OSMembershipController extends MPFController
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
	 */

	public function display($cachable = false, array $urlparams = array())
	{
		$document = JFactory::getDocument();

		$rootUri = JUri::base(true);

		$document->addStylesheet($rootUri . '/media/com_osmembership/assets/css/style.css', 'text/css', null, null);

		$customCssFile = JPATH_ROOT . '/media/com_osmembership/assets/css/custom.css';
		if (file_exists($customCssFile) && filesize($customCssFile) > 0)
		{
			$document->addStylesheet($rootUri . '/media/com_osmembership/assets/css/custom.css', 'text/css', null, null);
		}

		JHtml::_('jquery.framework');

		OSMembershipHelper::loadBootstrap(true);

		JHtml::_('script', 'media/com_osmembership/assets/js/jquery-noconflict.js', false, false);

		$document->addScript($rootUri . '/media/com_osmembership/assets/js/ajaxupload.min.js');

		return parent::display($cachable, $urlparams);
	}

	/**
	 * Process downloading invoice for a subscription record based on given ID
	 */
	public function download_invoice()
	{
		JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_osmembership/table');
		$id  = $this->input->getInt('id', 0);
		$row = JTable::getInstance('osmembership', 'Subscriber');
		$row->load($id);

		// Check download invoice permission
		$canDownload = false;
		if ($row)
		{
			$user = JFactory::getUser();
			if ($user->authorise('core.admin') || ($row->user_id > 0 && ($row->user_id == $user->id)))
			{
				$canDownload = true;
			}
		}

		if ($canDownload)
		{
			OSMembershipHelper::downloadInvoice($id);
		}
		else
		{
			throw new Exception(JText::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}
	}

	/**
	 * Download selected document from membership profile
	 *
	 * @throws Exception
	 */
	public function download_document()
	{
		$planIds = OSMembershipHelper::getActiveMembershipPlans();

		if (count($planIds) == 1)
		{
			throw new Exception(JText::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}

		$id    = $this->input->getInt('id');
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__osmembership_documents')
			->where('plan_id IN (' . implode(',', $planIds) . ')')
			->where('id = ' . $id);
		$db->setQuery($query);
		$document = $db->loadObject();
		if (!$document)
		{
			throw new Exception(JText::_('Document not found or you are not allowed to download this document'), 404);
		}

		$filePath = JPATH_ROOT . '/media/com_osmembership/documents/';
		$fileName = $document->attachment;
		if (file_exists($filePath . $fileName))
		{
			while (@ob_end_clean()) ;
			OSMembershipHelper::processDownload($filePath . $fileName, $fileName, true);
			exit();
		}
		else
		{
			throw new Exception(JText::_('Document not found. Please contact administrator'), 404);
		}
	}

	/**
	 * Download a file uploaded by users
	 *
	 * @throws Exception
	 */
	public function download_file()
	{
		$filePath = JPATH_ROOT . '/media/com_osmembership/upload/';
		$fileName = $this->input->get('file_name', '', 'string');
		$fileName = basename($fileName);
		if (file_exists($filePath . $fileName))
		{
			while (@ob_end_clean()) ;
			OSMembershipHelper::processDownload($filePath . $fileName, $fileName, true);
			exit();
		}
		else
		{
			$this->app->redirect('index.php?option=com_osmembership&Itemid=' . $this->input->getInt('Itemid'), JText::_('OSM_FILE_NOT_EXIST'));
		}
	}

	/**
	 * Process upload file
	 */
	public function upload_file()
	{
		jimport('joomla.filesystem.folder');

		$config     = OSMembershipHelper::getConfig();
		$json       = array();
		$pathUpload = JPATH_ROOT . '/media/com_osmembership/upload';
		if (!JFolder::exists($pathUpload))
		{
			JFolder::create($pathUpload);
		}
		$allowedExtensions = $config->allowed_file_types;
		if (!$allowedExtensions)
		{
			$allowedExtensions = 'doc|docx|ppt|pptx|pdf|zip|rar|bmp|gif|jpg|jepg|png|swf|zipx';
		}
		if (strpos($allowedExtensions, ',') !== false)
		{
			$allowedExtensions = explode(',', $allowedExtensions);
		}
		else
		{
			$allowedExtensions = explode('|', $allowedExtensions);
		}

		$allowedExtensions = array_map('trim', $allowedExtensions);

		$file     = $this->input->files->get('file', array(), 'raw');
		$fileName = $file['name'];
		$fileExt  = JFile::getExt($fileName);

		if (in_array(strtolower($fileExt), $allowedExtensions))
		{
			$fileName = JFile::makeSafe($fileName);
			if (JFile::exists($pathUpload . '/' . $fileName))
			{
				$targetFileName = time() . '_' . $fileName;
			}
			else
			{
				$targetFileName = $fileName;
			}

			if (version_compare(JVERSION, '3.4.4', 'ge'))
			{
				JFile::upload($file['tmp_name'], $pathUpload . '/' . $targetFileName, false, true);
			}
			else
			{
				JFile::upload($file['tmp_name'], $pathUpload . '/' . $targetFileName);
			}

			$json['success'] = JText::sprintf('OSM_FILE_UPLOADED', $fileName);
			$json['file']    = $targetFileName;
		}
		else
		{
			$json['error'] = JText::sprintf('OSM_FILE_NOT_ALLOWED', $fileExt, implode(', ', $allowedExtensions));
		}

		echo json_encode($json);

		$this->app->close();
	}
}
