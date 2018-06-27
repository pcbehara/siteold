<?php
/**
 * @package        Joomla
 * @subpackage     OSMembership
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;
?>
<div id="osm-subscription-history" class="osm-container row-fluid">
<form method="post" name="adminForm" id="adminForm" action="<?php echo JRoute::_('index.php?option=com_osmembership&view=documents&Itemid='.$this->Itemid); ?>">
<h1 class="osm-page-title"><?php echo JText::_('OSM_MY_DOWNLOADS') ; ?></h1>
<?php
	if ($this->items)
	{
		jimport('joomla.filesystem.path');

		$documents = $this->items;
		$path      = JPath::clean($this->documentsPath . '/');
	?>
		<table class="table table-striped table-bordered table-hover">
			<thead>
			<tr>
				<th class="title"><?php echo JText::_('OSM_TITLE'); ?></th>
				<th class="title"><?php echo JText::_('OSM_DOCUMENT'); ?></th>
				<th class="center"><?php echo JText::_('OSM_SIZE'); ?></th>
				<th class="center"><?php echo JText::_('OSM_DOWNLOAD'); ?></th>
			</tr>
			</thead>
			<tfoot>
			<tr>
				<td colspan="<?php echo 4 ; ?>">
					<?php echo $this->pagination->getListFooter(); ?>
				</td>
			</tr>
			</tfoot>
			<tbody>
			<?php
			for ($i = 0, $n = count($documents); $i < count($documents); $i++)
			{
				$document     = $documents[$i];
				$downloadLink = JRoute::_('index.php?option=com_osmembership&task=download_document&id=' . $document->id . '&Itemid=' . $this->Itemid);
				?>
				<tr>
					<td><a href="<?php echo $downloadLink ?>"><?php echo $document->title; ?></a></td>
					<td><?php echo $document->attachment; ?></td>
					<td class="center"><?php echo OSMembershipHelperHtml::getFormattedFilezize($path . $document->attachment); ?></td>
					<td class="center">
						<a href="<?php echo $downloadLink; ?>"><i class="icon-download"></i></a>
					</td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
	<?php
	}
	else
	{
	?>
		<p class="text-info"><?php echo JText::_('OSM_NO_DOCUMENTS_AVAILABLE'); ?></p>
	<?php
	}
?>
</form>
</div>