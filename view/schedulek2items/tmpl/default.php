<?php

/**
 * @package        Joomla
 * @subpackage     OSMembership
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;
?>
<div id="osm-subscription-history" class="osm-container row-fluid">
<form method="post" name="adminForm" id="adminForm" action="<?php echo JRoute::_('index.php?option=com_osmembership&view=schedulek2item&Itemid='.$this->Itemid); ?>">
<h1 class="osm-page-title"><?php echo JText::_('OSM_MY_SCHEDULE_K2_ITEMS') ; ?></h1>
<?php
	if (!empty($this->items))
	{
		$items = $this->items;
		$subscriptions = $this->subscriptions;
		$config = $this->config;
		require_once JPATH_ROOT . '/components/com_k2/helpers/route.php';
	?>
		<table class="adminlist table table-striped" id="adminForm">
			<thead>
			<tr>
				<th class="title"><?php echo JText::_('OSM_TITLE'); ?></th>
				<th class="title"><?php echo JText::_('OSM_CATEGORY'); ?></th>
				<th class="title center"><?php echo JText::_('OSM_ACCESSIBLE_ON'); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach ($items as $item)
			{
				$articleLink  = JRoute::_(K2HelperRoute::getItemRoute($item->id, $item->catid));
				$subscription = $subscriptions[$item->plan_id];
				$date         = JFactory::getDate($subscription->active_from_date);
				$date->add(new DateInterval('P' . $item->number_days . 'D'));
				?>
				<tr>
					<td>
						<i class="icon-file"></i>
						<?php
						if ($subscription->active_in_number_days >= $item->number_days)
						{
						?>
							<a href="<?php echo $articleLink ?>" target="_blank"><?php echo $item->title; ?></a>
						<?php
						}
						else
						{
							echo $item->title . ' <span class="label">' . JText::_('OSM_LOCKED') . '</span>';
						}
						?>
					</td>
					<td><?php echo $item->category_title; ?></td>
					<td class="center">
						<?php echo JHtml::_('date', $date->format('Y-m-d H:i:s'), $config->date_format); ?>
					</td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
	<?php
	}
	else
	{
	?>
		<p class="text-info"><?php echo JText::_('OSM_NO_SCHEDULE_CONTENT'); ?></p>
	<?php
	}
?>
</form>
</div>