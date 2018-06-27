<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

$html = [];

foreach ($subscriptions as $subscription)
{
	$fromDate = JHtml::_('date', $subscription->subscription_from_date, $config->date_format, null);

	if ($subscription->lifetime_membership || $subscription->subscription_to_date == '2099-12-31 23:59:59')
	{
		$toDate = JText::_('OSM_LIFETIME');
	}
	else
	{
		$toDate = JHtml::_('date', $subscription->subscription_to_date, $config->date_format);
	}

	$html[] = $subscription->title . ': ' . $fromDate . ' ' . JText::_('OSM_TO') . ' ' . $toDate;
}

echo implode("<br />", $html);

