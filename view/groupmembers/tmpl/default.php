<?php
/**
 * @package        Joomla
 * @subpackage     OSMembership
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die;
$fields = $this->fields;
$cols = count($fields) + 2;
?>
<div id="osm-subscription-history" class="osm-container row-fluid">
	<div class="page-header">
		<h1 class="osm-page-title">
			<?php
			echo JText::_('OSM_GROUP_MEMBERS_LIST') ;

			if ($this->canManage == 2)
			{
			?>
				<span class="osm-add-group-member_link pull-right"><a href="<?php echo JRoute::_('index.php?option=com_osmembership&view=groupmember&Itemid='. OSMembershipHelperRoute::findView('groupmember', $this->Itemid)); ?>"><?php echo JText::_('OSM_ADD_NEW_GROUP_MEMBER'); ?></a></span>
			<?php
			}
			?>
		</h1>
	</div>
<form method="post" name=os_form id="os_form" action="<?php echo JRoute::_('index.php?option=com_osmembership&view=groupmembers&Itemid='.$this->Itemid); ?>">
	<fieldset class="filters btn-toolbar clearfix">
        <?php echo $this->loadTemplate('search'); ?>
	</fieldset>
	<table class="table table-striped table-bordered table-hover">
		<thead>
			<tr>
				<th><?php echo JText::_('OSM_PLAN'); ?></th>
				<?php
					foreach($fields as $field)
					{
					?>
						<th><?php echo $field->title; ?></th>
					<?php
					}

					if ($this->config->auto_generate_membership_id)
					{
						$cols++ ;
					?>
						<th width="8%" class="center">
							<?php echo JText::_('OSM_MEMBERSHIP_ID'); ?>
						</th>
					<?php
					}
				?>
				<th class="center">
					<?php echo JText::_('OSM_CREATED_DATE') ; ?>
				</th>
			</tr>
		</thead>
		<tbody>
		<?php
			for ($i = 0 , $n = count($this->items) ; $i < $n ; $i++)
			{
				$row = $this->items[$i];
				$link = JRoute::_('index.php?option=com_osmembership&view=groupmember&id=' . $row->id . '&Itemid=' . $this->Itemid);
			?>
				<tr>
					<td>
						<a href="<?php echo $link; ?>"><?php echo $row->plan_title;?></a>
						<a href="javascript:deleteMemberConfirm(<?php echo $row->id; ?>)" title="<?php echo JText::_('OSM_DELETE_THIS_MEMBER'); ?>"><i
								class="icon-remove alert-danger"></i></a>
					</td>
					<?php
					foreach ($fields as $field)
					{
					?>
						<td>
							<?php
								if ($field->is_core)
								{
									echo $row->{$field->name};
								}
								elseif (isset($this->fieldsData[$row->id][$field->id]))
								{
									echo $this->fieldsData[$row->id][$field->id];
								}
							?>
						</td>
					<?php
					}

					if ($this->config->auto_generate_membership_id)
					{
					?>
						<td class="center">
							<?php echo $row->membership_id ? OSMembershipHelper::formatMembershipId($row, $this->config) : ''; ?>
						</td>
					<?php
					}
					?>
					<td class="center">
						<?php echo JHtml::_('date', $row->created_date, $this->config->date_format); ?>
					</td>
				</tr>
			<?php
			}
			?>
			</tbody>
			<?php
			if ($this->pagination->total > $this->pagination->limit)
			{
			?>
			<tfoot>
				<tr>
					<td colspan="<?php echo $cols; ?>">
						<div class="pagination"><?php echo $this->pagination->getPagesLinks(); ?></div>
					</td>
				</tr>
			</tfoot>
			<?php
			}
		?>
	</table>
	<script language="javascript">
		function deleteMemberConfirm(id)
		{
			if (confirm("<?php echo JText::_('OSM_DELETE_MEMBER_CONFIRM'); ?>"))
			{
				form = document.os_form;
				form.task.value = 'delete';
				form.member_id.value = id;
				form.submit();
			}
		}
	</script>
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="member_id" value="0" />
	<?php echo JHtml::_( 'form.token' ); ?>
</form>
</div>