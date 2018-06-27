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

if (isset($input) && $input->getInt('recommended_plan_id'))
{
	$recommendedPlanId = $input->getInt('recommended_plan_id');
}
else
{
	/* @var JApplicationSite $app */
	$app = JFactory::getApplication();
	$recommendedPlanId = (int) $app->getParams()->get('recommended_campaign_id');
}

if (isset($config->number_columns))
{
	$numberColumns = $config->number_columns ;
}
else
{
	$numberColumns = 3 ;
}

$numberColumns = min($numberColumns, 4);

if (!isset($categoryId))
{
	$categoryId = 0;
}

$span = intval(12 / $numberColumns);
$btnClass = $bootstrapHelper->getClassMapping('btn');
$imgClass = $bootstrapHelper->getClassMapping('img-polaroid');
$spanClass = $bootstrapHelper->getClassMapping('span' . $span);

$subscribedPlanIds = OSMembershipHelperSubscription::getSubscribedPlans();
$exclusivePlanIds = OSMembershipHelperSubscription::getExclusivePlanIds();

$rootUri       = JUri::root(true);
$i             = 0;
$numberPlans   = count($items);
$defaultItemId = $Itemid;

foreach ($items as $item)
{
	$Itemid = OSMembershipHelperRoute::getPlanMenuId($item->id, $item->category_id, $defaultItemId);

	if ($item->thumb)
	{
		$imgSrc = $rootUri . '/media/com_osmembership/' . $item->thumb;
	}

	$url = JRoute::_('index.php?option=com_osmembership&view=plan&catid=' . $item->category_id . '&id=' . $item->id . '&Itemid=' . $Itemid);

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
	}
	else
	{
		$recommended = false;
	}

	if ($i % $numberColumns == 0)
	{
	?>
		<div class="<?php echo $bootstrapHelper->getClassMapping('row-fluid'); ?> osm-pricing-table clearfix">
	<?php
	}
	?>
	<div class="<?php echo $spanClass; ?>">
		<div class="osm-plan<?php if ($recommended) echo ' osm-plan-recommended'; ?> osm-plan-<?php echo $item->id; ?>">
			<?php
				if ($recommended)
				{
				?>
					<p class="plan-recommended"><?php echo JText::_('OSM_RECOMMENDED'); ?></p>
				<?php
				}
			?>
			<div class="osm-plan-header">
				<h2 class="osm-plan-title">
					<?php echo $item->title; ?>
				</h2>
			</div>
			<div class="osm-plan-price">
				<h2>
					<p class="price">
						<span>
						<?php
							if ($item->price > 0)
							{
								$symbol = $item->currency_symbol ? $item->currency_symbol : $item->currency;
								echo OSMembershipHelper::formatCurrency($item->price, $config, $symbol);
							}
							else
							{
								echo JText::_('OSM_FREE');
							}
							?>
						</span>
					</p>
				</h2>
			</div>
			<div class="osm-plan-short-description">
				<?php echo $item->short_description;?>
			</div>
			<?php
			if (OSMembershipHelper::canSubscribe($item) && (!in_array($item->id, $exclusivePlanIds) || in_array($item->id, $subscribedPlanIds)))
			{
			?>
				 <ul class="osm-signup-container">
                     <?php
                     if (empty($item->upgrade_rules) || !$config->get('hide_signup_button_if_upgrade_available'))
                     {
                     ?>
                         <li>
                             <a href="<?php echo $signUpUrl; ?>" class="<?php echo $btnClass; ?> btn-primary btn-singup">
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
                             <a href="<?php echo $link; ?>" class="<?php echo $btnClass; ?> btn-primary btn-singup">
			                     <?php echo JText::_('OSM_UPGRADE'); ?>
                             </a>
                         </li>
	                     <?php
                     }
                     ?>
				</ul>
			<?php
			}
			?>
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