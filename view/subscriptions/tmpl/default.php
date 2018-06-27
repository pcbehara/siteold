<?php
/**
 * @package        Joomla
 * @subpackage     OSMembership
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;
?>
<div id="osm-subscription-history" class="osm-container row-fluid">
<form method="post" name="adminForm" id="adminForm" action="<?php echo JRoute::_('index.php?option=com_osmembership&view=subscriptions&Itemid='.$this->Itemid); ?>">
<h1 class="osm-page-title"><?php echo JText::_('OSM_SUBSCRIPTION_HISTORY') ; ?></h1>
	<?php
		$layoutData = array(
			'showPagination' => true,
			'pagination'     => $this->pagination
		);
		echo $this->loadCommonLayout('common/tmpl/subscriptions_history.php', $layoutData);
	?>
</form>
</div>