<?php
/**
 * @package        Joomla
 * @subpackage     OSMembership
 * @author         Lee L - www.jumazi.com
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

// no direct access
defined('_JEXEC') or die;
?>
<h2 class="osm-form-heading"><?php echo JText::_('OSM_PROFILE_DATA'); ?></h2>
<?php
if ($this->config->enable_avatar)
{
	if ($this->item->avatar && file_exists(JPATH_ROOT . '/media/com_osmembership/avatars/' . $this->item->avatar))
	{
		?>
		<div class="<?php echo $controlGroupClass; ?>">
			<div class="<?php echo $controlLabelClass; ?>">
				<label><?php echo JText::_('OSM_AVATAR'); ?></label>
			</div>
			<div class="<?php echo $controlsClass; ?>">
				<img class="oms-avatar" src="<?php echo JUri::base(true) . '/media/com_osmembership/avatars/' . $this->item->avatar; ?>"/>
			</div>
		</div>
		<?php
	}
	?>
	
	<?php
}
if ($this->item->user_id)
{
	$params = JComponentHelper::getParams('com_users');
	$validationRules = array();
	$minimumLength = $params->get('minimum_length', 4);
	if ($minimumLength)
	{
		$validationRules[] = "minSize[$minimumLength]";
	}
	$validationRules[] = 'ajax[ajaxValidatePassword]';
	if (count($validationRules))
	{
		$class = ' class="validate['.implode(',', $validationRules).']"';
	}
	else
	{
		$class = '';
	}
	?>
	<div class="<?php echo $controlGroupClass; ?>">
		<div class="<?php echo $controlLabelClass; ?>">
			<label><?php echo JText::_('OSM_USERNAME'); ?></label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<?php echo $this->item->username; ?>
		</div>
	</div>
	
	<?php
}
if ($this->item->membership_id)
{
?>
	<div class="<?php echo $controlGroupClass; ?>">
		<div class="<?php echo $controlLabelClass; ?>">
			<label><?php echo JText::_('OSM_MEMBERSHIP_ID'); ?></label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<?php echo OSMembershipHelper::formatMembershipId($this->item, $this->config); ?>
		</div>
	</div>
<?php
}

$fields = $this->form->getFields();
if (isset($fields['state']))
{
	$selectedState = $fields['state']->value;
}
foreach ($fields as $field)
{
	if ($field->fee_field)
	{
		echo $field->getOutput(true, $bootstrapHelper);
	}
	else
	{
		echo $field->getControlGroup($bootstrapHelper);
	}
}
?>
<div class="form-actions">
	<input type="submit" class="<?php echo $btnClass; ?> btn-primary" value="<?php echo JText::_('OSM_UPDATE'); ?>"/>
</div>