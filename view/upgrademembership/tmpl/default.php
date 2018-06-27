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
$db = JFactory::getDbo();
OSMembershipHelperJquery::validateForm();
?>
<div id="osm-upgrade-options-page" class="osm-container">
<h1 class="osm-page-title"><?php echo JText::_('OSM_UPGRADE_MEMBERSHIP'); ?></h1>
<?php
	if (count($this->upgradeRules))
	{
	?>
		<p class="osm-description"><?php echo JText::_('OSM_UPGRADE_MEMBERSHIP_DESCRIPTION'); ?></p>
		<form action="<?php echo JRoute::_('index.php?option=com_osmembership&task=register.process_upgrade_membership&Itemid='.$this->Itemid, false, $this->config->use_https ? 1 : 0); ?>" method="post" name="osm_form_update_membership" id="osm_form_update_membership" autocomplete="off" class="form form-horizontal">
			<?php echo $this->loadCommonLayout('common/tmpl/upgrade_options.php'); ?>
			<div class="form-actions">
				<input type="submit" class="<?php echo $this->bootstrapHelper->getClassMapping('btn'); ?> btn-primary" value="<?php echo JText::_('OSM_PROCESS_UPGRADE'); ?>"/>
			</div>
		</form>
	<?php
	}
	else
	{
	?>
		<p class="text-info"><?php echo JText::_('OSM_NO_UPGRADE_OPTIONS_AVAILABLE'); ?></p>
	<?php
	}
?>
</div>