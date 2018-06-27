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

if (count($this->methods) > 1)
{
?>
	<div class="<?php echo $controlGroupClass; ?> payment_information" id="payment_method_container">
		<div class="<?php echo $controlLabelClass; ?>" >
			<label for="payment_method">
				<?php echo JText::_('OSM_PAYMENT_OPTION'); ?>
				<span class="required">*</span>
			</label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<ul id="osm-payment-method-list" class="nav clearfix">
				<?php
				$method = null ;
				for ($i = 0 , $n = count($this->methods); $i < $n; $i++)
				{
					$paymentMethod = $this->methods[$i];
					if ($paymentMethod->getName() == $this->paymentMethod)
					{
						$checked = ' checked="checked" ';
						$method = $paymentMethod ;
					}
					else
					{
						$checked = '';
					}
					?>
					<li class="osm-payment-method-item radio">
						<input onclick="changePaymentMethod();" id="osm-payment-method-item-<?php echo $i; ?>" type="radio" name="payment_method" value="<?php echo $paymentMethod->getName(); ?>" <?php echo $checked; ?> />
						<label for="osm-payment-method-item-<?php echo $i; ?>"><?php echo JText::_($paymentMethod->title) ; ?></label>
					</li>
					<?php
				}
				?>
			</ul>
		</div>
	</div>
<?php
}
else
{
	$method = $this->methods[0] ;
?>
	<div class="<?php echo $controlGroupClass; ?> payment_information" id="payment_method_container">
		<div class="<?php echo $controlLabelClass; ?>">
			<label for="payment_method">
				<?php echo JText::_('OSM_PAYMENT_OPTION'); ?>
			</label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<?php echo JText::_($method->title); ?>
		</div>
	</div>
<?php
}
if ($method->getCreditCard())
{
	$style = '' ;
}
else
{
	$style = 'style = "display:none"';
}
?>
	<div class="<?php echo $controlGroupClass; ?> payment_information" id="tr_card_number" <?php echo $style; ?>>
		<div class="<?php echo $controlLabelClass; ?>">
			<label><?php echo  JText::_('AUTH_CARD_NUMBER'); ?><span class="required">*</span></label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<input type="text" name="x_card_num" class="validate[required,creditCard] osm_inputbox inputbox" value="<?php echo $this->escape($this->input->post->getAlnum('x_card_num'));?>" size="20" />
		</div>
	</div>
	<div class="<?php echo $controlGroupClass; ?> payment_information" id="tr_exp_date" <?php echo $style; ?>>
		<div class="<?php echo $controlLabelClass; ?>">
			<label>
				<?php echo JText::_('AUTH_CARD_EXPIRY_DATE'); ?><span class="required">*</span>
			</label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<?php echo $this->lists['exp_month'] .'  /  '.$this->lists['exp_year'] ; ?>
		</div>
	</div>
	<div class="<?php echo $controlGroupClass; ?> payment_information" id="tr_cvv_code" <?php echo $style; ?>>
		<div class="<?php echo $controlLabelClass; ?>">
			<label>
				<?php echo JText::_('AUTH_CVV_CODE'); ?><span class="required">*</span>
			</label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<input type="text" name="x_card_code" class="validate[required,custom[number]] osm_inputbox input-small" value="<?php echo $this->escape($this->input->post->getString('x_card_code')); ?>" size="20" />
		</div>
	</div>
<?php
if ($method->getCardHolderName())
{
	$style = '' ;
}
else
{
	$style = ' style = "display:none;" ' ;
}
?>
<div class="<?php echo $controlGroupClass; ?> payment_information" id="tr_card_holder_name" <?php echo $style; ?>>
	<div class="<?php echo $controlLabelClass; ?>">
		<label>
			<?php echo JText::_('OSM_CARD_HOLDER_NAME'); ?><span class="required">*</span>
		</label>
	</div>
	<div class="<?php echo $controlsClass; ?>">
		<input type="text" name="card_holder_name" class="validate[required] osm_inputbox inputbox"  value="<?php echo $this->input->post->getString('card_holder_name'); ?>" size="40" />
	</div>
</div>