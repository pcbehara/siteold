<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die;

trait OSMembershipModelValidationtrait
{
	/**
	 * Validate username
	 *
	 * @param $username
	 *
	 * @return array
	 */
	protected function validateUsername($username)
	{
		/* @var JDatabaseDriver $db */
		$db          = $this->getDbo();
		$query       = $db->getQuery(true);
		$filterInput = JFilterInput::getInstance();
		$errors      = [];

		if ($filterInput->clean($username, 'TRIM') == '')
		{
			$errors[] = JText::_('JLIB_DATABASE_ERROR_PLEASE_ENTER_A_USER_NAME');
		}

		if (preg_match('#[<>"\'%;()&\\\\]|\\.\\./#', $username) || strlen(utf8_decode($username)) < 2
			|| $filterInput->clean($username, 'TRIM') !== $username
		)
		{
			$errors[] = JText::sprintf('JLIB_DATABASE_ERROR_VALID_AZ09', 2);
		}

		$query->select('COUNT(*)')
			->from('#__users')
			->where('username = ' . $db->quote($username));
		$db->setQuery($query);
		$total = $db->loadResult();

		if ($total)
		{
			$errors[] = JText::_('OSM_INVALID_USERNAME');
		}

		return $errors;
	}

	/**
	 * Validate password
	 *
	 * @param $password
	 *
	 * @return array
	 */
	protected function validatePassword($password)
	{
		//Load language from user component
		$lang = JFactory::getLanguage();
		$tag  = $lang->getTag();

		if (!$tag)
		{
			$tag = 'en-GB';
		}

		$lang->load('com_users', JPATH_ROOT, $tag);

		$errors = [];

		$params           = JComponentHelper::getParams('com_users');
		$minimumIntegers  = $params->get('minimum_integers');
		$minimumSymbols   = $params->get('minimum_symbols');
		$minimumUppercase = $params->get('minimum_uppercase');

		// We don't allow white space inside passwords
		$valueTrim   = trim($password);
		$valueLength = strlen($password);

		if (strlen($valueTrim) !== $valueLength)
		{
			$errors[] = \JText::_('COM_USERS_MSG_SPACES_IN_PASSWORD');
		}

		if (!empty($minimumIntegers))
		{
			$nInts = preg_match_all('/[0-9]/', $password, $imatch);

			if ($nInts < $minimumIntegers)
			{
				$errors[] = JText::plural('COM_USERS_MSG_NOT_ENOUGH_INTEGERS_N', $minimumIntegers);
			}
		}

		if (!empty($minimumSymbols))
		{
			$nsymbols = preg_match_all('[\W]', $password, $smatch);

			if ($nsymbols < $minimumSymbols)
			{
				$errors[] = JText::plural('COM_USERS_MSG_NOT_ENOUGH_SYMBOLS_N', $minimumSymbols);
			}
		}

		if (!empty($minimumUppercase))
		{
			$nUppercase = preg_match_all("/[A-Z]/", $password, $umatch);

			if ($nUppercase < $minimumUppercase)
			{
				$errors[] = JText::plural('COM_USERS_MSG_NOT_ENOUGH_UPPERCASE_LETTERS_N', $minimumUppercase);
			}
		}

		return $errors;
	}

	/**
	 * Validate email for user account
	 *
	 * @param string $email
	 * @param bool   $checkExists
	 *
	 * @return array
	 */
	protected function validateEmail($email, $checkExists = true)
	{
		$filterInput = JFilterInput::getInstance();
		$errors      = [];

		// Validate email
		if (empty($email))
		{
			$errors[] = JText::sprintf('OSM_FIELD_NAME_IS_REQUIRED', JText::_('Email'));
		}


		if (($filterInput->clean($email, 'TRIM') == "") || !JMailHelper::isEmailAddress($email))
		{
			$errors[] = JText::_('JLIB_DATABASE_ERROR_VALID_MAIL');
		}

		if ($checkExists)
		{
			/* @var JDatabaseDriver $db */
			$db    = $this->getDbo();
			$query = $db->getQuery(true);
			$query->select('COUNT(*)')
				->from('#__users')
				->where('email = ' . $db->quote($email));
			$db->setQuery($query);
			$total = $db->loadResult();

			if ($total)
			{
				$errors[] = JText::_('OSM_INVALID_EMAIL');
			}
		}

		return $errors;
	}
}