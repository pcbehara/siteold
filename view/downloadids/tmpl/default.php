<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

JHtml::_('behavior.core');
?>
<h1 class="dm_title"><?php echo JText::_('OSM_MANAGE_DOWNLOAD_IDS') ?></h1>
<form method="post" name="adminForm" id="adminForm" action="<?php echo JRoute::_('index.php?option=com_osmembership&Itemid=' . $this->Itemid); ?>">
    <?php echo $this->message->download_ids_manage_message; ?>
    <p class="pull-right">
        <?php echo JText::_('OSM_GENERATE');?> <?php echo JHtml::_('select.integerlist', 1, 5, 1, 'number_download_ids', 'class="input-mini"'); ?> <?php echo JText::_('OSM_NEW_DOWNLOAD_IDS'); ?>
        <button type="button" class="btn btn-small btn-primary" onclick="Joomla.submitform('generate_download_ids');"><i class="icon-new icon-white"></i><?php echo JText::_('OSM_PROCESS'); ?></button>
    </p>
	<table class="table table-striped table-bordered">
		<thead>
		<tr>
			<th width="5%">
				<?php echo JText::_('OSM_NO'); ?>
			</th>
			<th>
				<?php echo JText::_('OSM_DOWNLOAD_ID'); ?>
			</th>
			<th>
				<?php echo JText::_('OSM_DOMAIN'); ?>
			</th>
			<th>
				<?php echo JText::_('OSM_CREATED_DATE'); ?>
			</th>
			<th class="center">
				<?php echo JText::_('OSM_ENABLED'); ?>
			</th>
		</tr>
		</thead>
        <tfoot>
            <tr>
                <td colspan="5"><div class="pagination"><?php echo $this->pagination->getListFooter(); ?></div></td>
            </tr>
        </tfoot>
		<tbody>
		<?php
		$rootUri = JUri::root(true);

		for ($i = 0 , $n = count($this->items) ; $i < $n; $i++)
		{
			$item = $this->items[$i] ;
			$img 	= $item->published ? 'tick.png' : 'publish_x.png';
			$alt 	= $item->published ? JText::_( 'OSM_ENABLED' ) : JText::_( 'OSM_DISABLED' );
			?>
			<tr>
				<td>
					<?php echo $i + 1 ; ?>
				</td>
				<td>
					<?php echo $item->download_id; ?>
				</td>
				<td>
				    <?php echo $item->domain; ?>
				</td>    
				</td>
				<td>
					<?php echo JHtml::_('date', $item->created_date, $this->config->date_format); ?>
				</td>
				<td class="center">
					<img src="<?php echo $rootUri . '/media/com_osmembership/assets/images/' . $img; ?>" alt="<?php echo $alt; ?>" />
				</td>
			</tr>
			<?php
		}
		?>
		</tbody>
	</table>
	<input type="hidden" name="task" value="" />
	<?php echo JHtml::_('form.token'); ?>
</form>