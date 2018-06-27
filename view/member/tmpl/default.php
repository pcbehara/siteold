<?php
/**
 * @package        Joomla
 * @subpackage     OSMembership
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die;
JHtml::_('bootstrap.tooltip');

$showAvatar              = $this->params->get('show_avatar', 1);
$showSubscriptionDate    = $this->params->get('show_subscription_date', 1);
$showSubscriptionEndDate = $this->params->get('show_subscription_end_date', 0);
$showMembershipId        = $this->params->get('show_membership_id', 0);

$bootstrapHelper = new OSMembershipHelperBootstrap($this->config->twitter_bootstrap_version);
$rowFluidClass   = $bootstrapHelper->getClassMapping('row-fluid');
$span3Class      = $bootstrapHelper->getClassMapping('span3');
$span9Class      = $bootstrapHelper->getClassMapping('span9');

$fields     = $this->fields;
$item       = $this->item;
$fieldsData = $this->data;

if (!$item->avatar)
{
	$item->avatar = 'no_avatar.jpg';
}
?>
<div id="osm-members-list" class="osm-container <?php echo $rowFluidClass; ?>">
	<div class="page-header">
		<h1 class="osm-page-title"><?php echo JText::_('OSM_MEMBER_PROFILE') ; ?></h1>
	</div>
	<div class="<?php echo $rowFluidClass; ?>">
		<?php
			if ($showAvatar)
			{
			?>
				<div class="<?php echo $span3Class; ?> avatar-container">
					<img class="oms-avatar img-circle" src="<?php echo JUri::root(true) . '/media/com_osmembership/avatars/' . $item->avatar; ?>"/>
				</div>
				<div class="<?php echo $span9Class; ?>">
			<?php
			}
		?>
			<table class="table table-stripped osm-profile-data">
				<tr>
					<td class="osm-profile-field-title">
						<?php echo JText::_('OSM_NAME'); ?>:
					</td>
					<td class="osm-profile-field-value">
						<?php echo rtrim($item->first_name . ' ' . $item->last_name); ?>
					</td>
				</tr>
				<?php
                if ($showMembershipId)
                {
                ?>
                    <tr>
                        <td class="osm-profile-field-title">
                            <?php echo JText::_('OSM_MEMBERSHIP_ID'); ?>:
                        </td>
                        <td class="osm-profile-field-value">
                            <?php echo OSMembershipHelper::formatMembershipId($item, $this->config); ?>
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
						<td class="osm-profile-field-value">
							<?php echo JHtml::_('date', $item->created_date, $this->config->date_format); ?>
						</td>
					</tr>
				<?php
				}

				if ($showSubscriptionEndDateDate)
				{
				?>
                    <tr>
                        <td class="osm-profile-field-title">
							<?php echo JText::_('OSM_SUBSCRIPTION_END_DATE'); ?>:
                        </td>
                        <td class="osm-profile-field-value">
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
					elseif (isset($fieldsData[$field->name]))
					{
						$fieldValue = $fieldsData[$field->name];
					}
					else
					{
						$fieldValue = '';
					}

					if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
					{
						$fieldValue = implode(', ', json_decode($fieldValue));
					}

					// Make the link and email click-able
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
		<?php
			if ($showAvatar)
			{
			?>
				</div>
			<?php
			}
		?>
	</div>
</div>