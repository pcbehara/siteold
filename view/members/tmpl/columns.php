<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012-2014 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
$showPlan                = $this->params->get('show_plan', 1);
$showSubscriptionDate    = $this->params->get('show_subscription_date', 1);
$showSubscriptionEndDate = $this->params->get('show_subscription_end_date', 0);
$numberColumns           = $this->params->get('number_columns', 2);
$showLinkToProfile       = $this->params->get('show_link_to_detail', 0);
$showMembershipId        = $this->params->get('show_membership_id', 0);

$bootstrapHelper = new OSMembershipHelperBootstrap($this->config->twitter_bootstrap_version);
$span = intval(12 / $numberColumns);
$spanClass = $bootstrapHelper->getClassMapping('span' . $span);
$rowFluidClass = $bootstrapHelper->getClassMapping('row-fluid');

$fieldsData = $this->fieldsData;
$items = $this->items;
$fields = $this->fields;

// Remove first_name and last_name as it is displayed in single name field
for ($i = 0, $n = count($fields); $i < $n; $i++)
{
	if (in_array($fields[$i]->name, ['first_name', 'last_name']))
	{
		unset($fields[$i]);
	}
}

OSMembershipHelperJquery::equalHeights();
?>
<div id="osm-members-list" class="osm-container row-fluid">
	<div class="page-header">
		<h1 class="osm-page-title"><?php echo JText::_('OSM_MEMBERS_LIST') ; ?></h1>
	</div>
	<form method="post" name="adminForm" id="adminForm" action="<?php echo JRoute::_('index.php?option=com_osmembership&view=members&Itemid='.$this->Itemid); ?>">
		<fieldset class="filters btn-toolbar clearfix">
			<div class="filter-search btn-group pull-left">
				<label for="filter_search" class="element-invisible"><?php echo JText::_('OSM_FILTER_SEARCH_MEMBERS_DESC');?></label>
				<input type="text" name="filter_search" id="filter_search" placeholder="<?php echo JText::_('JSEARCH_FILTER'); ?>" value="<?php echo $this->escape($this->state->filter_search); ?>" class="hasTooltip" title="<?php echo JHtml::tooltipText('OSM_SEARCH_MEMBERS_DESC'); ?>" />
			</div>
			<div class="btn-group pull-left">
				<button type="submit" class="btn hasTooltip" title="<?php echo JHtml::tooltipText('JSEARCH_FILTER_SUBMIT'); ?>"><span class="icon-search"></span></button>
				<button type="button" class="btn hasTooltip" title="<?php echo JHtml::tooltipText('JSEARCH_FILTER_CLEAR'); ?>" onclick="document.getElementById('filter_search').value='';this.form.submit();"><span class="icon-remove"></span></button>
			</div>
		</fieldset>
		<div class="clearfix <?php echo $rowFluidClass; ?>">
		<?php
		$i              = 0;
		$numberProfiles = count($items);
		$rootUri        = JUri::root(true);

		foreach ($items as $item)
		{
			$i++;

			if (!$item->avatar)
			{
				$item->avatar = 'no_avatar.jpg';
			}

			$link = JRoute::_('index.php?option=com_osmembership&view=member&id=' . $item->id . '&Itemid=' . $this->Itemid);
		?>
			<div class="osm-user-profile-wrapper <?php echo $spanClass; ?>">
				<div class="row-fluid">
					<div class="span4">
						<?php
							if ($showLinkToProfile)
							{
							?>
								<a href="<?php echo $link; ?>"><img class="oms-avatar img-circle" src="<?php echo $rootUri . '/media/com_osmembership/avatars/' . $item->avatar; ?>"/></a>
							<?php
							}
							else
							{
							?>
								<img class="oms-avatar img-circle" src="<?php echo $rootUri . '/media/com_osmembership/avatars/' . $item->avatar; ?>"/>
							<?php
							}
						?>
					</div>
					<div class="span8">
						<div class="profile-name">
							<?php
							if ($showLinkToProfile)
							{
							?>
								<a href="<?php echo $link; ?>"><?php echo rtrim($item->first_name . ' ' . $item->last_name); ?></a>
							<?php
							}
							else
							{
							?>
								<?php echo rtrim($item->first_name . ' ' . $item->last_name); ?>
							<?php
							}
							?>
						</div>
						<table class="table table-striped">
							<?php
                            if ($showMembershipId)
                            {
                            ?>
                                <tr>
                                    <td class="osm-profile-field-title">
			                            <?php echo JText::_('OSM_MEMBERSHIP_OD'); ?>:
                                    </td>
                                    <td>
			                            <?php echo OSMembershipHelper::formatMembershipId($item, $this->config); ?>
                                    </td>
                                </tr>
                            <?php
                            }

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

							if ($showSubscriptionEndDate)
							{
							?>
                                <tr>
                                    <td class="osm-profile-field-title">
										<?php echo JText::_('OSM_SUBSCRIPTION_END_DATE'); ?>:
                                    </td>
                                    <td>
										<?php echo JHtml::_('date', $item->plan_subscription_to_date, $this->config->date_format); ?>
                                    </td>
                                </tr>
							<?php
							}

							foreach($fields as $field)
							{
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
								
								if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
								{
									$fieldValue = implode(', ', json_decode($fieldValue));
								}

								if (filter_var($fieldValue, FILTER_VALIDATE_URL))
								{
									$fieldValue = '<a href="' . $fieldValue . '" target="_blank">' . $fieldValue . '<a/>';
								}
                                elseif (filter_var($fieldValue, FILTER_VALIDATE_EMAIL))
								{
									$fieldValue = '<a href="mailto:' . $fieldValue . '">' . $fieldValue . '<a/>';
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
		
		<?php
			if ($this->pagination->total > $this->pagination->limit)
			{
			?>			
				<div class="pagination">
					<?php echo $this->pagination->getPagesLinks(); ?>
				</div>					
			<?php
			}
		?>	
		
	</form>
</div>
<script type="text/javascript">
	OSM.jQuery(function($) {
		$(document).ready(function() {
			$(".osm-user-profile-wrapper").equalHeights(150);
		});
	});
</script>