<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012-2014 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
$showPlan = $this->params->get('show_plan', 1);
$showSubscriptionDate = $this->params->get('show_subscription_date', 1);
$numberColumns = $this->params->get('number_columns', 2);

$bootstrapHelper = new OSMembershipHelperBootstrap($this->config->twitter_bootstrap_version);
$span = intval(12 / $numberColumns);
$spanClass = $bootstrapHelper->getClassMapping('span' . $span);
$rowFluidClass = $bootstrapHelper->getClassMapping('row-fluid');

$fieldsData = $this->fieldsData;
$items = $this->items;
$fields = $this->fields;

OSMembershipHelperJquery::equalHeights();
?>
<div id="osm-profile-list" class="osm-container row-fluid">
	<form method="post" name="adminForm" id="adminForm" action="<?php echo JRoute::_('index.php?option=com_osmembership&view=members&Itemid='.$this->Itemid); ?>">
		<h1 class="osm-page-title"><?php echo JText::_('OSM_MEMBERS_LIST') ; ?></h1>
		<table width="100%">
			<tr>
				<td align="left">
					<?php echo JText::_( 'OSM_FILTER' ); ?>:
					<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->state->filter_search;?>" class="input-medium" onchange="this.form.submit();" />
					<button onclick="this.form.submit();" class="btn"><?php echo JText::_( 'OSM_GO' ); ?></button>
					<button onclick="document.getElementById('filter_search').value='';this.form.submit();" class="btn"><?php echo JText::_( 'OSM_RESET' ); ?></button>
				</td >
			</tr>
		</table>
		<div class="clearfix <?php echo $rowFluidClass; ?>">
			<?php
			$i = 0;
			$numberProfiles = count($items);
			foreach ($items as $item)
			{
			$i++;
			if (!$item->avatar)
			{
				$item->avatar = 'no_avatar.jpg';
			}
			?>
			<div class="osm-user-profile-wrapper <?php echo $spanClass; ?>">
				<div class="row-fluid">
					<div class="span4">
						<img class="<?php echo $imgClass; ?> oms-avatar img-circle" src="<?php echo JUri::base(true) . '/media/com_osmembership/avatars/' . $item->avatar; ?>"/>
					</div>
					<div class="span8">
						<div class="profile-name"><?php echo rtrim($item->first_name . ' ' . $item->last_name); ?></div>
						<table class="table table-striped">
							<?php
							if ($showPlan)
							{
							?>
								<tr>
									<td class="osm-profile-field-title">
										<?php echo JText::_('OSM_PLAN'); ?>:
									</td>
									<td>
										<?php echo $item->plan_title; ?>
									</td>
								</tr>
							<?php
							}
							if ($showSubscriptionDate)
							{
								?>
								<tr>
									<td class="osm-profile-field-title">
										<?php echo JText::_('OSM_SUBSCRIPTION_DATE'); ?>:
									</td>
									<td>
										<?php echo JHtml::_('date', $item->created_date, $this->config->date_format); ?>
									</td>
								</tr>
							<?php
							}
							foreach($fields as $field)
							{
								if ($field->name == 'first_name' || $field->name == 'last_name')
								{
									continue;
								}

								if ($field->is_core)
								{
									$fieldValue = $item->{$field->name};
								}
								elseif (isset($fieldsData[$item->id][$field->id]))
								{
									$fieldValue = $fieldsData[$item->id][$field->id];
								}
								else
								{
									$fieldValue = '';
								}
								?>
								<tr>
									<td class="osm-profile-field-title">
										<?php echo $field->title; ?>:
									</td>
									<td class="osm-profile-field-value">
										<?php echo $fieldValue; ?>
									</td>
								</tr>
							<?php
							}
							?>
						</table>
					</div>
				</div>
			</div>
			<?php
			if ($i % $numberColumns == 0 && $i < $numberProfiles)
			{
			?>
		</div>
		<div class="clearfix <?php echo $rowFluidClass; ?>">
			<?php
			}
			}
			?>
		</div>
	</form>
</div>
<script type="text/javascript">
	OSM.jQuery(function($) {
		$(document).ready(function() {
			$(".osm-user-profile-wrapper").equalHeights(150);
		});
	});
</script>