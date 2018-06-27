<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
// no direct access
defined('_JEXEC') or die;
?>
<table class="table table-bordered table-striped">
	<thead>
	<tr>
		<th>
			<?php echo JText::_('OSM_PLAN') ?>
		</th>
		<th width="25%" class="center">
			<?php echo JText::_('OSM_ACTIVATE_TIME') ; ?>
		</th>
		<th width="10%" class="center">
			<?php echo JText::_('OSM_SUBSCRIPTION_STATUS'); ?>
		</th>
	</tr>
	</thead>
	<tbody>
	<?php
	foreach($this->subscriptions as $subscription)
	{
	?>
		<tr>
			<td>
				<?php echo $subscription->title; ?>
			</td>
			<td class="center">
				<strong><?php echo JHtml::_('date', $subscription->subscription_from_date, $this->config->date_format, null); ?></strong> <?php echo JText::_('OSM_TO'); ?>
				<strong>
					<?php
					if ($subscription->lifetime_membership || $subscription->subscription_to_date  == '2099-12-31 23:59:59')
					{
						echo JText::_('OSM_LIFETIME');
					}
					else
					{
						echo JHtml::_('date', $subscription->subscription_to_date , $this->config->date_format);
					}
					?>
				</strong>
			</td>
			<td class="center">
				<?php
				switch ($subscription->subscription_status)
				{
					case 0 :
						echo JText::_('OSM_PENDING');
						break ;
					case 1 :
						echo JText::_('OSM_ACTIVE');
						break ;
					case 2 :
						echo JText::_('OSM_EXPIRED');
						break ;
					default:
						echo JText::_('OSM_CANCELLED');
						break;
				}

				if ($subscription->subscription_status == 1 && $subscription->subscription_id)
				{
				?>
					<input type="button" class="btn btn-danger" value="<?php echo JText::_('OSM_CANCEL_SUBSCRIPTION'); ?>" onclick="cancelSubscription('<?php echo $subscription->subscription_id;  ?>');" />
				<?php
				}

				if ($subscription->recurring_cancelled)
				{
					echo '<br /><span class="text-error">' . JText::_('OSM_RECURRING_CANCELLED').'</span>';
				}
				?>
			</td>
		</tr>
	<?php
	}
	?>
	</tbody>
</table>
