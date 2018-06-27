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
OSMembershipHelperJquery::validateForm();
?>
<div id="osm-renew-options-page" class="osm-container">
<h1 class="osm-page-title"><?php echo JText::_('OSM_RENREW_MEMBERSHIP'); ?></h1>
<form action="<?php echo JRoute::_('index.php?option=com_osmembership&task=register.process_renew_membership&Itemid='.$this->Itemid, false, $this->config->use_https ? 1 : 0); ?>" method="post" name="osm_form_renew" id="osm_form_renew" autocomplete="off" class="form form-horizontal">
	<p class="osm-description"><?php echo JText::_('OSM_RENREW_MEMBERSHIP_DESCRIPTION'); ?></p>
	<?php
		echo $this->loadCommonLayout('common/tmpl/renew_options.php');
	?>
</form>
</div>