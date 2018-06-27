<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
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
		<th width="25%" class="center">
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
					<a class="btn btn-danger osm-btn-cancel-subscription" href="javascript:cancelSubscription('<?php echo $subscription->subscription_id;  ?>');"><?php echo JText::_('OSM_CANCEL_SUBSCRIPTION'); ?></a>
				<?php
				}

				if ($subscription->recurring_cancelled)
				{
					echo '<br /><span class="text-error">' . JText::_('OSM_RECURRING_CANCELLED').'</span>';
				}
				elseif($subscription->subscription_id)
				{
					$subscription = OSMembershipHelperSubscription::getSubscription($subscription->subscription_id);
					$method = os_payments::getPaymentMethod($subscription->payment_method);

					if (method_exists($method, 'updateCard'))
					{
					?>
						<a href="<?php echo JRoute::_('index.php?option=com_osmembership&view=card&subscription_id=' . $subscription->subscription_id . '&Itemid=' . $this->Itemid); ?>" class="btn btn-primary osm-btn-update-card"><?php echo JText::_('OSM_UPDATE_CARD');  ?></a>
					<?php
					}
				}
				?>
			</td>
		</tr>
	<?php
	}
	?>
	</tbody>
</table>
