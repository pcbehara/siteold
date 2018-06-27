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
$showPlan                = $this->params->get('show_plan', 1);
$showSubscriptionDate    = $this->params->get('show_subscription_date', 1);
$showSubscriptionEndDate = $this->params->get('show_subscription_end_date', 0);
$showLinkToProfile       = $this->params->get('show_link_to_detail', 0);
$showMembershipId        = $this->params->get('show_membership_id', 0);

$fields = $this->fields;

// Remove first_name and last_name as it is displayed in single name field

for ($i = 0, $n = count($fields); $i < $n; $i++)
{
	if (in_array($fields[$i]->name, ['first_name', 'last_name']))
	{
		unset($fields[$i]);
	}
}

$cols    = count($fields);
$rootUri = JUri::root(true);
?>
<div id="osm-members-list" class="osm-container row-fluid">
	<div class="page-header">
		<h1 class="osm-page-title"><?php echo JText::_('OSM_MEMBERS_LIST') ; ?></h1>
	</div>
	<form method="post" name="adminForm" id="adminForm" action="<?php echo JRoute::_('index.php?option=com_osmembership&view=members&Itemid='.$this->Itemid); ?>">
		<fieldset class="filters btn-toolbar clearfix">
            <?php echo $this->loadTemplate('search'); ?>
		</fieldset>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<?php
						if ($showAvatar)
						{
							$cols++;
						?>
							<th>
								<?php echo JText::_('OSM_AVATAR') ?>
							</th>
						<?php
						}

						if ($showMembershipId)
                        {
                            $cols++;
                        ?>
                            <th>
		                        <?php echo JText::_('OSM_MEMBERSHIP_ID') ?>
                            </th>
                        <?php
                        }
						?>
							<th>
								<?php echo JText::_('OSM_NAME') ?>
							</th>
						<?php
						if ($showPlan)
						{
							$cols++;
						?>
							<th>
								<?php echo JText::_('OSM_PLAN') ?>
							</th>
						<?php
						}

						foreach($fields as $field)
						{
						?>
							<th><?php echo $field->title; ?></th>
						<?php
						}

						if ($showSubscriptionDate)
						{
							$cols++;
						?>
							<th class="center">
								<?php echo JText::_('OSM_SUBSCRIPTION_DATE') ; ?>
							</th>
						<?php
						}

						if ($showSubscriptionEndDate)
                        {
                            $cols++;
                        ?>
                            <th class="center">
		                        <?php echo JText::_('OSM_SUBSCRIPTION_END_DATE') ; ?>
                            </th>
                        <?php
                        }
					?>
				</tr>
			</thead>
			<tbody>
			<?php
				$fieldsData = $this->fieldsData;
				for ($i = 0 , $n = count($this->items) ; $i < $n ; $i++)
				{
					$row = $this->items[$i];
					$link = JRoute::_('index.php?option=com_osmembership&view=member&id=' . $row->id . '&Itemid=' . $this->Itemid);
				?>
					<tr>
						<?php
						if ($showAvatar)
						{
						?>
							<td>
								<?php
								if ($row->avatar && file_exists(JPATH_ROOT . '/media/com_osmembership/avatars/' . $row->avatar))
								{
									if ($showLinkToProfile)
									{
									?>
										<a href="<?php echo $link; ?>"><img class="oms-avatar" src="<?php echo $rootUri . '/media/com_osmembership/avatars/' . $row->avatar; ?>"/></a>
									<?php
									}
									else
									{
									?>
										<img class="oms-avatar" src="<?php echo $rootUri . '/media/com_osmembership/avatars/' . $row->avatar; ?>"/>
									<?php
									}
								}
								?>
							</td>
						<?php
						}

						if ($showMembershipId)
                        {
                        ?>
                            <td class="center"><?php echo OSmembershipHelper::formatMembershipId($row, $this->config); ?></td>
                        <?php
                        }
						?>
						<td>
							<?php
								if ($showLinkToProfile)
								{
								?>
									<a href="<?php echo $link; ?>"><?php echo rtrim($row->first_name . ' ' . $row->last_name); ?></a>
								<?php
								}
								else
								{
									echo rtrim($row->first_name . ' ' . $row->last_name);
								}
							?>
						</td>
						<?php

						if ($showPlan)
						{
						?>
							<td>
								<?php echo $row->plan_title; ?>
							</td>
						<?php
						}

						foreach ($fields as $field)
						{
							if ($field->is_core)
							{
								$fieldValue = $row->{$field->name};
							}
							elseif (isset($fieldsData[$row->id][$field->id]))
							{
								$fieldValue = $fieldsData[$row->id][$field->id];
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
								<td>
									<?php echo $fieldValue; ?>
								</td>
							<?php
						}

						if ($showSubscriptionDate)
						{
						?>
							<td class="center">
								<?php echo JHtml::_('date', $row->created_date, $this->config->date_format); ?>
							</td>
						<?php
						}

						if ($showSubscriptionEndDate)
						{
						?>
                            <td class="center">
								<?php echo JHtml::_('date', $row->plan_subscription_to_date, $this->config->date_format); ?>
                            </td>
						<?php
						}
						?>
					</tr>
				<?php
				}
				?>
				</tbody>
				<?php
				if ($this->pagination->total > $this->pagination->limit)
				{
				?>
				<tfoot>
					<tr>
						<td colspan="<?php echo $cols; ?>">
							<div class="pagination"><?php echo $this->pagination->getPagesLinks(); ?></div>
						</td>
					</tr>
				</tfoot>
				<?php
				}
			?>
		</table>
	</form>
</div>