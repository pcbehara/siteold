<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

/**
 * Layout variables
 *
 * @var OSMembershipHelperBootstrap $bootstrapHelper
 * @var array                       $items
 * @var stdClass                    $config
 * @var int                         $Itemid
 */

// Load equals height script
OSMembershipHelperJquery::equalHeights();

/* @var JApplicationSite $app */
$app    = JFactory::getApplication();
$params = $app->getParams();

if (isset($input) && $input->getInt('recommended_plan_id'))
{
	$recommendedPlanId = $input->getInt('recommended_plan_id');
}
else
{
	$recommendedPlanId = (int) $params->get('recommended_campaign_id');
}

$standardPlanBackgroundColor    = $params->get('standard_plan_color', '#00B69C');
$recommendedPlanBackgroundColor = $params->get('recommended_plan_color', '#bF75500');
$showDetailsButton              = $params->get('show_details_button', 0);

if (isset($config->number_columns))
{
	$numberColumns = $config->number_columns;
}
else
{
	$numberColumns = 3;
}

$numberColumns = min($numberColumns, 5);

if (!isset($categoryId))
{
	$categoryId = 0;
}

$span      = intval(12 / $numberColumns);
$imgClass  = $bootstrapHelper->getClassMapping('img-polaroid');
$spanClass = $bootstrapHelper->getClassMapping('span' . $span);

$subscribedPlanIds = OSMembershipHelperSubscription::getSubscribedPlans();
$exclusivePlanIds  = OSMembershipHelperSubscription::getExclusivePlanIds();

$i             = 0;
$numberPlans   = count($items);
$defaultItemId = $Itemid;
$rootUri       = JUri::root(true);

foreach ($items as $item)
{
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

	if (!$item->short_description)
	{
		$item->short_description = $item->description;
	}

	if ($item->id == $recommendedPlanId)
	{
		$recommended = true;
		$backgroundColor = $recommendedPlanBackgroundColor;
	}
	else
	{
		$recommended = false;
		$backgroundColor = $standardPlanBackgroundColor;
	}

	if ($i % $numberColumns == 0)
	{
	?>
		<div class="<?php echo $bootstrapHelper->getClassMapping('row-fluid'); ?> osm-pricing-table-flat clearfix">
	<?php
	}
	?>
	<div class="<?php echo $spanClass; ?>">
		<div class="osm-plan osm-plan-<?php echo $item->id; ?>" style="background-color: <?php echo $backgroundColor; ?>">
			<div class="osm-plan-header">
				<h2 class="osm-plan-title">
					<?php echo $item->title; ?>
				</h2>
			</div>
			<div class="osm-plan-price">
				<p class="price">
					<?php
					if ($item->lifetime_membership)
					{
						$subscriptionLengthText = JText::_('OSM_LIFETIME');
					}
					else
					{
						switch ($item->subscription_length_unit)
						{
							case 'D':
								$subscriptionLengthText = $item->subscription_length > 1 ? $item->subscription_length . ' ' . JText::_('OSM_DAYS') : JText::_('OSM_DAY');
								break;
							case 'W':
								$subscriptionLengthText = $item->subscription_length > 1 ? $item->subscription_length . ' ' . JText::_('OSM_WEEKS') : JText::_('OSM_WEEK');
								break;
							case 'M':
								$subscriptionLengthText = $item->subscription_length > 1 ? $item->subscription_length . ' ' . JText::_('OSM_MONTHS') : JText::_('OSM_MONTHS');
								break;
							case 'Y':
								$subscriptionLengthText = $item->subscription_length > 1 ? $item->subscription_length . ' ' . JText::_('OSM_YEARS') : JText::_('OSM_YEAR');
								break;
							default:
								$subscriptionLengthText = '';
						}
					}

					if ($item->price > 0)
					{
						$priceParts = explode('.', $item->price);

						if ($priceParts[1] == '00')
						{
							$numberDecimals = 0;
						}
						else
						{
							$numberDecimals = 2;
						}

						$symbol = $item->currency_symbol ? $item->currency_symbol : $item->currency;

						if (!$symbol)
						{
							$symbol = $config->currency_symbol;
						}

						echo $symbol . number_format($item->price, $numberDecimals) . ($subscriptionLengthText ? "<sub> /$subscriptionLengthText</sub>" : '');
					}
					else
					{
						echo JText::_('OSM_FREE') . ($subscriptionLengthText ? "<sub> /$subscriptionLengthText</sub>" : '');
					}
					?>
				</p>
			</div>
			<div class="osm-plan-short-description">
				<?php echo $item->short_description;?>
			</div>
			<ul class="osm-signup-container">
			<?php
				if (OSMembershipHelper::canSubscribe($item) && (!in_array($item->id, $exclusivePlanIds) || in_array($item->id, $subscribedPlanIds)))
				{
					if (empty($item->upgrade_rules) || !$config->get('hide_signup_button_if_upgrade_available'))
                    {
	                ?>
                        <li>
                            <a href="<?php echo $signUpUrl; ?>" class="btn-signup">
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
                            <a href="<?php echo $link; ?>" class="btn-signup">
								<?php echo JText::_('OSM_UPGRADE'); ?>
                            </a>
                        </li>
						<?php
					}
				}

				if ($showDetailsButton)
				{
				?>
					<li>
						<a href="<?php echo $url; ?>" class="btn-signup oms-btn-details">
							<?php echo JText::_('OSM_DETAILS'); ?>
						</a>
					</li>
				<?php
				}
			?>
			</ul>
		</div>
	</div>
	<?php
	if (($i + 1) % $numberColumns == 0)
	{
	?>
		</div>
	<?php
	}
	$i++;
}

if ($i % $numberColumns != 0)
{
	echo "</div>" ;
}
?>
<script type="text/javascript">
	OSM.jQuery(function($) {
		$(document).ready(function() {
			$(".osm-plan-short-description").equalHeights(130);
		});
	});
</script>
