<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
// no direct access
defined( '_JEXEC' ) or die ;

$db = JFactory::getDbo();
$query = $db->getQuery(true);
OSMembershipHelperJquery::validateForm();
$selectedState = '';
if ($this->config->use_https)
{
	$ssl = 1;
}
else
{
	$ssl = 0;
}
$bootstrapHelper = $this->bootstrapHelper;

// Get mapping classes, make them ready for using
$controlGroupClass = $bootstrapHelper->getClassMapping('control-group');
$inputPrependClass = $bootstrapHelper->getClassMapping('input-group');
$addOnClass        = $bootstrapHelper->getClassMapping('add-on');
$controlLabelClass = $bootstrapHelper->getClassMapping('control-label');
$controlsClass     = $bootstrapHelper->getClassMapping('controls');
$btnClass          = $bootstrapHelper->getClassMapping('btn');
$fieldSuffix = OSMembershipHelper::getFieldSuffix();
?>
<script type="text/javascript">
	var siteUrl = '<?php echo OSMembershipHelper::getSiteUrl();  ?>';
</script>
<div id="osm-profile-page" class="row-fluid osm-container">
<h1 class="osm_title"><?php echo JText::_('OSM_USER_PROFILE'); ?></h1>
<form action="index.php" method="post" name="osm_form" id="osm_form" autocomplete="off" enctype="multipart/form-data" class="form form-horizontal">
	<?php
	echo JHtml::_('bootstrap.startTabSet', 'osm-profile', array('active' => 'profile-page'));

	echo JHtml::_('bootstrap.addTab', 'osm-profile', 'profile-page', JText::_('OSM_EDIT_PROFILE', true));
	$profileLayoutData = array(
		'controlGroupClass' => $controlGroupClass,
		'controlLabelClass' => $controlLabelClass,
		'controlsClass' => $controlsClass,
		'bootstrapHelper' => $bootstrapHelper,
		'btnClass' => $btnClass
	);
	echo $this->loadTemplate('profile', $profileLayoutData);
	echo JHtml::_('bootstrap.endTab');

	echo JHtml::_('bootstrap.addTab', 'osm-profile', 'my-subscriptions-page', JText::_('OSM_MY_SUBSCRIPTIONS', true));
	echo $this->loadTemplate('subscriptions');
	echo JHtml::_('bootstrap.endTab');

	echo JHtml::_('bootstrap.addTab', 'osm-profile', 'subscription-history-page', JText::_('OSM_SUBSCRIPTION_HISTORY', true));
	$layoutData = array(
		'showPagination' => false,
	);
	echo $this->loadCommonLayout('common/tmpl/subscriptions_history.php', $layoutData);
	echo JHtml::_('bootstrap.endTab');

	if (count($this->plugins))
	{
		$count = 0 ;
		foreach ($this->plugins as $plugin)
		{
			$count++ ;
			if (empty($plugin['form']))
			{
				continue;
			}
			echo JHtml::_('bootstrap.addTab', 'osm-profile', 'tab_'.$count, JText::_($plugin['title'], true));
			echo $plugin['form'];
			echo JHtml::_('bootstrap.endTab');
		}
	}
	echo JHtml::_('bootstrap.endTabSet');
	?>
	<div class="clearfix"></div>
	<input type="hidden" name="option" value="com_osmembership" />
	<input type="hidden" name="cid[]" value="<?php echo $this->item->id; ?>" />
	<input type="hidden" name="task" value="profile.update" />
	<input type="hidden" name="Itemid" value="<?php echo $this->Itemid; ?>" />
	<?php echo JHtml::_( 'form.token' ); ?>
</form>

<?php
// Renew Membership
if ($this->item->group_admin_id == 0 && count($this->planIds))
{
?>
	<form action="<?php echo JRoute::_('index.php?option=com_osmembership&task=register.process_renew_membership&Itemid=' . $this->Itemid, false, $ssl); ?>" method="post" name="osm_form_renew" id="osm_form_renew" autocomplete="off" class="form form-horizontal">
		<h2 class="osm-form-heading"><?php echo JText::_('OSM_RENEW_MEMBERSHIP'); ?></h2>
		<?php echo $this->loadCommonLayout('common/tmpl/renew_options.php');?>
	</form>
<?php
}

// Upgrade Membership
if ($this->item->group_admin_id == 0 && !empty($this->upgradeRules))
{
?>
	<form action="<?php echo JRoute::_('index.php?option=com_osmembership&task=register.process_upgrade_membership&Itemid='.$this->Itemid, false, $ssl); ?>" method="post" name="osm_form_update_membership" id="osm_form_update_membership" autocomplete="off" class="form form-horizontal">
		<h2 class="osm-form-heading"><?php echo JText::_('OSM_UPGRADE_MEMBERSHIP'); ?></h2>
		<?php
			echo $this->loadCommonLayout('common/tmpl/upgrade_options.php');
		?>
		<div class="form-actions">
			<input type="submit" class="<?php echo $btnClass; ?> btn-primary" value="<?php echo JText::_('OSM_PROCESS_UPGRADE'); ?>"/>
		</div>
	</form>
<?php
}
?>

<form action="<?php echo JRoute::_('index.php?option=com_osmembership&task=register.process_cancel_subscription&Itemid='.$this->Itemid, false, $ssl); ?>" method="post" name="osm_form_cancel_subscription" id="osm_form_cancel_subscription" autocomplete="off" class="form form-horizontal">
	<input type="hidden" name="subscription_id" value="" />
	<?php echo JHtml::_( 'form.token' ); ?>
</form>

<script type="text/javascript">
	OSM.jQuery(function($){
		$(document).ready(function(){
			OSMVALIDATEFORM("#osm_form");
			buildStateField('state', 'country', '<?php echo $selectedState; ?>');
		})
	});

	function cancelSubscription(subscriptionId)
	{
		if (confirm("<?php echo JText::_('OSM_CANCEL_SUBSCRIPTION_CONFIRM'); ?>"))
		{
			var form = document.osm_form_cancel_subscription;
			form.subscription_id.value = subscriptionId;
			form.submit();
		}
	}
</script>
</div>