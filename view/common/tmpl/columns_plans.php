<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

OSMembershipHelperJquery::equalHeights();

if (isset($input) && $input->getInt('number_columns'))
{
	$numberColumns = $input->getInt('number_columns');
}
elseif (!empty($config->number_columns))
{
	$numberColumns = $config->number_columns;
}
else
{
	$numberColumns = 3;
}

if (!isset($categoryId))
{
	$categoryId = 0;
}

$span = intval(12 / $numberColumns);
$btnClass = $bootstrapHelper->getClassMapping('btn');
$imgClass = $bootstrapHelper->getClassMapping('img-polaroid');
$spanClass = $bootstrapHelper->getClassMapping('span' . $span);
$rowFluidClass = $bootstrapHelper->getClassMapping('row-fluid');
?>
<div class="clearfix <?php echo $rowFluidClass; ?>">
<?php
$i = 0;
$numberPlans = count($items);
$defaultItemId = $Itemid;

$subscribedPlanIds = OSMembershipHelperSubscription::getSubscribedPlans();
$exclusivePlanIds = OSMembershipHelperSubscription::getExclusivePlanIds();

foreach ($items as $item)
{
	$i++;

	$Itemid = OSMembershipHelperRoute::getPlanMenuId($item->id, $item->category_id, $defaultItemId);

	if ($item->thumb)
	{
		$imgSrc = JUri::base().'media/com_osmembership/'.$item->thumb ;
	}

	$url = JRoute::_('index.php?option=com_osmembership&view=plan&catid='.$item->category_id.'&id='.$item->id.'&Itemid='.$Itemid);

	if ($config->use_https)
	{
		$signUpUrl = JRoute::_(OSMembershipHelperRoute::getSignupRoute($item->id, $Itemid), false, 1);
	}
	else
	{
		$signUpUrl = JRoute::_(OSMembershipHelperRoute::getSignupRoute($item->id, $Itemid));
	}
	?>
	<div class="osm-item-wrapper <?php echo $spanClass; ?>">
		<div class="osm-item-heading-box clearfix">
			<h2 class="osm-item-title">
				<a href="<?php echo $url; ?>" title="<?php echo $item->title; ?>">
					<?php echo $item->title; ?>
				</a>
			</h2>
		</div>
		<div class="osm-item-description clearfix">
				<?php
				if ($item->thumb)
				{
				?>
				<a href="<?php echo $url; ?>" title="<?php echo $item->title; ?>">	
					<img src="<?php echo $imgSrc; ?>" class="osm-thumb-left <?php echo $imgClass; ?>" />
				</a>	
				<?php
				}
				if (!$item->short_description)
				{
					$item->short_description = $item->description;
				}
				?>
				<div class="osm-item-description-text"><?php echo $item->short_description; ?></div>
				 <div class="osm-taskbar clearfix">
					<ul>
						<?php
						if (OSMembershipHelper::canSubscribe($item) && (!in_array($item->id, $exclusivePlanIds) || in_array($item->id, $subscribedPlanIds)))
						{
							if (empty($item->upgrade_rules) || !$config->get('hide_signup_button_if_upgrade_available'))
                            {
	                        ?>
                                <li>
                                    <a href="<?php echo $signUpUrl; ?>" class="<?php echo $btnClass; ?> btn-primary">
			                            <?php echo in_array($item->id, $subscribedPlanIds) ? JText::_('OSM_RENEW') : JText::_('OSM_SIGNUP'); ?>
                                    </a>
                                </li>
	                        <?php
                            }

                            if(!empty($item->upgrade_rules))
							{
								if (count($item->upgrade_rules) > 1)
								{
									$link = JRoute::_('index.php?option=com_osmembership&view=upgrademembership&to_plan_id=' . $item->id . '&Itemid=' . OSMembershipHelperRoute::findView('upgrademembership', $Itemid));
								}
								else
								{
									$upgradeOptionId = $item->upgrade_rules[0]->id;
									$link            = JRoute::_('index.php?option=com_osmembership&task=register.process_upgrade_membership&upgrade_option_id=' . $upgradeOptionId . '&Itemid=' . $Itemid);
								}
								?>
                                <li>
                                    <a href="<?php echo $link; ?>" class="<?php echo $btnClass; ?> btn-primary">
										<?php echo JText::_('OSM_UPGRADE'); ?>
                                    </a>
                                </li>
								<?php
							}
						}

						if (empty($config->hide_details_button))
						{
						?>
							<li>
								<a href="<?php echo $url; ?>" class="<?php echo $btnClass; ?>">
									<?php echo JText::_('OSM_DETAILS'); ?>
								</a>
							</li>
						<?php
						}
						?>
					</ul>
			</div>
		</div>
	</div>
	<?php
	if ($i % $numberColumns == 0 && $i < $numberPlans)
	{
	?>
		</div>
		<div class="clearfix <?php echo $rowFluidClass; ?>">
	<?php
	}
}
?>
</div>
<script type="text/javascript">
	OSM.jQuery(function($) {
		$(document).ready(function() {
			$(".osm-item-description-text").equalHeights(130);
		});
	});
</script>