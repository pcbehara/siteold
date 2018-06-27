<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
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
if (!$this->userId && $this->config->show_login_box_on_subscribe_page)
{
	$actionUrl = JRoute::_('index.php?option=com_osmembership&task=register.login');
	$returnUrl = JUri::getInstance()->toString();
	?>
	<form method="post" action="<?php echo $actionUrl ; ?>" name="osm_login_form" id="osm_login_form" autocomplete="off" class="form form-horizontal">
		<h2 class="osm-heading"><?php echo JText::_('OSM_EXISTING_USER_LOGIN'); ?></h2>
		<div class="<?php echo $controlGroupClass ?>">
			<div class="<?php echo $controlLabelClass; ?>">
				<label for="username">
					<?php echo  empty($this->config->use_email_as_username) ? JText::_('OSM_USERNAME') : $fields['email']->title; ?><span class="required">*</span>
				</label>
			</div>
			<div class="<?php echo $controlsClass; ?>">
				<input type="text" name="username" id="username" required class="input-large validate[required]" value=""/>
			</div>
		</div>
		<div class="<?php echo $controlGroupClass ?>">
			<div class="<?php echo $controlLabelClass; ?>">
				<label for="password">
					<?php echo  JText::_('OSM_PASSWORD') ?><span class="required">*</span>
				</label>
			</div>
			<div class="<?php echo $controlsClass; ?>">
				<input type="password" id="password" name="password" required class="input-large validate[required" value="" />
			</div>
		</div>
		<div class="<?php echo $controlGroupClass ?>">
			<div class="<?php echo $controlsClass; ?>">
				<input type="submit" value="<?php echo JText::_('OSM_LOGIN'); ?>" class="button btn btn-primary" />
			</div>
		</div>
		<?php
		if ($this->config->registration_integration)
		{
		?>
			<h2 class="eb-heading"><?php echo JText::_('OSM_NEW_USER_REGISTER'); ?></h2>
			<h3 class="osm-heading"><?php echo JText::_('OSM_ACCOUNT_INFORMATION');?></h3>
		<?php
		}
		?>
		<input type="hidden" name="remember" value="1" />
		<input type="hidden" name="return" value="<?php echo base64_encode($returnUrl) ; ?>" />
		<?php echo JHtml::_( 'form.token' ); ?>
	</form>
	<?php
}