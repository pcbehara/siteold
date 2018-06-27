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
<div id="osm-urls-manage" class="osm-container row-fluid">
<form method="post" name="adminForm" id="adminForm" action="<?php echo JRoute::_('index.php?option=com_osmembership&view=urls&Itemid='.$this->Itemid); ?>">
<h1 class="osm-page-title"><?php echo JText::_('OSM_MY_PAGES') ; ?></h1>
<?php
    if (!empty($this->items))
	{
	?>
		<table class="adminlist table table-striped" id="adminForm">
			<thead>
			<tr>
				<th class="title"><?php echo JText::_('OSM_PAGE_URL'); ?></th>
			</tr>
			</thead>
			<?php
			if ($this->pagination->total > $this->pagination->limit)
			{
			?>
			<tfoot>
				<tr>
					<td>
						<div class="pagination"><?php echo $this->pagination->getPagesLinks(); ?></div>
					</td>
				</tr>
			</tfoot>
			<?php
			}
			?>
			<tbody>
			<?php
				foreach ($this->items as $item)
				{
				?>
					<tr>
						<td><a href="<?php echo $item->url ?>" target="_blank"><?php echo $item->title ? $item->title : $item->url; ?></a></td>
					</tr>
				<?php
				}
			?>
			</tbody>
		</table>
	<?php
	}
?>
</form>
</div>