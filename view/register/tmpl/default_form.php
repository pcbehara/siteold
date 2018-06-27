<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die ;
JHtml::_('behavior.modal', 'a.osm-modal');

/**@var OSMembershipHelperBootstrap $bootstrapHelper **/
$bootstrapHelper   = $this->bootstrapHelper;
$controlGroupClass = $bootstrapHelper->getClassMapping('control-group');
$inputPrependClass = $bootstrapHelper->getClassMapping('input-prepend');
$inputAppendClass  = $bootstrapHelper->getClassMapping('input-append');
$addOnClass        = $bootstrapHelper->getClassMapping('add-on');
$controlLabelClass = $bootstrapHelper->getClassMapping('control-label');
$controlsClass     = $bootstrapHelper->getClassMapping('controls');

if ($this->config->enable_avatar && empty($this->avatar))
{
?>
	<div class="<?php echo $controlGroupClass ?>">
		<div class="<?php echo $controlLabelClass; ?>">
			<label  for="profile_avatar"><?php echo  JText::_('OSM_AVATAR') ?></label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<input type="file" name="profile_avatar" accept="image/*">
		</div>
	</div>
<?php
}

if (!$this->userId && $this->config->registration_integration)
{
	$params = JComponentHelper::getParams('com_users');
	$minimumLength = $params->get('minimum_length', 4);
	($minimumLength) ? $minSize = ",minSize[$minimumLength]" : $minSize = "";
	$passwordValidation = ',ajax[ajaxValidatePassword]';

	if (empty($this->config->use_email_as_username))
	{
	?>
		<div class="<?php echo $controlGroupClass ?>">
			<div class="<?php echo $controlLabelClass; ?>">
				<?php echo OSMembershipHelperHtml::getFieldLabel('username1', JText::_('OSM_USERNAME'), JText::_('OSM_USERNAME_TOOLTIP'), true); ?>
			</div>
			<div class="<?php echo $controlsClass; ?>">
				<input type="text" name="username" id="username1" class="validate[required,minSize[2],ajax[ajaxUserCall]]" value="<?php echo $this->escape($this->input->post->getUsername('username')); ?>" size="15" autocomplete="off"/>
			</div>
		</div>
	<?php
	}
	else
	{
		echo $fields['email']->getControlGroup($bootstrapHelper);
		unset($fields['email']);
	}
	?>
	<div class="<?php echo $controlGroupClass ?>">
		<div class="<?php echo $controlLabelClass; ?>">
			<?php echo OSMembershipHelperHtml::getFieldLabel('password1', JText::_('OSM_PASSWORD'), JText::_('OSM_PASSWORD_TOOLTIP'), true); ?>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<input value="" class="validate[required<?php echo $minSize.$passwordValidation;?>] text-input osm_inputbox inputbox" type="password" name="password1" id="password1" autocomplete="off"/>
		</div>
	</div>
	<div class="<?php echo $controlGroupClass ?>">
		<div class="<?php echo $controlLabelClass; ?>">
			<label for="password2">
				<?php echo  JText::_('OSM_RETYPE_PASSWORD') ?>
				<span class="required">*</span>
			</label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<input value="" class="validate[required,equals[password1]] text-input osm_inputbox inputbox" type="password" name="password2" id="password2" />
		</div>
	</div>
	<?php
}

foreach ($fields as $field)
{
	echo $field->getControlGroup($bootstrapHelper);
}