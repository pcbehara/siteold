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
?>

<div class="chcl-profile-detail-page-wrapper">
	<div class="chcl-profile-detail-content-wrapper">
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
			<div class="<?php echo $controlGroupClass; ?>">
				<div class="<?php echo $controlLabelClass; ?>">
					<label><?php echo JText::_('OSM_NEW_AVATAR'); ?></label>
				</div>
				<div class="<?php echo $controlsClass; ?>">
					<input type="file" name="profile_avatar" accept="image/*">
				</div>
			</div>
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
			<div class="<?php echo $controlGroupClass; ?>">
				<div class="<?php echo $controlLabelClass; ?>">
					<label for="password"><?php echo JText::_('OSM_PASSWORD'); ?></label>
				</div>
				<div class="<?php echo $controlsClass; ?>">
					<input type="password" id="password" name="password" size="20" value=""<?php echo $class; ?> />
				</div>
			</div>
			<div class="<?php echo $controlGroupClass; ?>">
				<div class="<?php echo $controlLabelClass; ?>">
					<label for="password2">
						<?php echo  JText::_('OSM_RETYPE_PASSWORD') ?>
						<span class="required">*</span>
					</label>
				</div>
				<div class="<?php echo $controlsClass; ?>">
					<input value="" class="validate[equals[password]]" type="password" name="password2" id="password2" />
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
	</div>
<div>