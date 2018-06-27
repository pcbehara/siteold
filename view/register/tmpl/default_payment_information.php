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
if ($this->config->enable_coupon)
{
?>
	<div class="<?php echo $controlGroupClass ?>">
		<div class="<?php echo $controlLabelClass; ?>">
			<label><?php echo JText::_('OSM_COUPON'); ?></label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<input type="text" class="input-medium" name="coupon_code" id="coupon_code" value="<?php echo JRequest::getVar('coupon_code');?>" onchange="calculateSubscriptionFee();" />
			<span class="invalid" id="coupon_validate_msg" style="display: none;"><?php echo JText::_('OSM_INVALID_COUPON'); ?></span>
		</div>
	</div>
<?php
}
if ($this->plan->recurring_subscription)
{
	echo $this->loadTemplate('payment_information_recurring');
}
else
{
?>
	<div class="<?php echo $controlGroupClass ?>">
		<div class="<?php echo $controlLabelClass; ?>">
			<label><?php echo JText::_('OSM_PRICE'); ?></label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<?php
			if ($this->config->currency_position == 0)
			{
			?>
				<div class="<?php echo $inputPrependClass; ?> inline-display">
					<span class="<?php echo $addOnClass; ?>"><?php echo $this->currencySymbol?></span>
					<input id="amount" type="text" readonly="readonly" class="input-small" value="<?php echo OSMembershipHelper::formatAmount($this->fees['amount'], $this->config); ?>" />
				</div>
			<?php
			}
			else
			{
			?>
				<div class="<?php echo $inputAppendClass; ?> inline-display">
					<input id="amount" type="text" readonly="readonly" class="input-small" value="<?php echo OSMembershipHelper::formatAmount($this->fees['amount'], $this->config); ?>" />
					<span class="<?php echo $addOnClass; ?>"><?php echo $this->currencySymbol?></span>
				</div>
			<?php
			}
			?>
		</div>
	</div>
	<?php
	if ($this->config->enable_coupon)
	{
	?>
		<div class="<?php echo $controlGroupClass ?>">
			<div class="<?php echo $controlLabelClass; ?>">
				<label><?php echo JText::_('OSM_DISCOUNT'); ?></label>
			</div>
			<div class="<?php echo $controlsClass; ?>">
				<?php
				if ($this->config->currency_position == 0)
				{
				?>
					<div class="<?php echo $inputPrependClass; ?> inline-display">
						<span class="<?php echo $addOnClass; ?>"><?php echo $this->currencySymbol?></span>
						<input id="discount_amount" type="text" readonly="readonly" class="input-small" value="<?php echo OSMembershipHelper::formatAmount($this->fees['discount_amount'], $this->config); ?>" />
					</div>
				<?php
				}
				else
				{
				?>
					<div class="<?php echo $inputAppendClass; ?> inline-display">
						<input id="discount_amount" type="text" readonly="readonly" class="input-small" value="<?php echo OSMembershipHelper::formatAmount($this->fees['discount_amount'], $this->config); ?>" />
						<span class="<?php echo $addOnClass; ?>"><?php echo $this->currencySymbol?></span>
					</div>
				<?php
				}
				?>
			</div>
		</div>
		<?php
	}
	if ($this->taxRate > 0)
	{
	?>
		<div class="<?php echo $controlGroupClass ?>">
			<div class="<?php echo $controlLabelClass; ?>">
				<label><?php echo JText::_('OSM_TAX'); ?></label>
			</div>
			<div class="<?php echo $controlsClass; ?>">
				<?php
				if ($this->config->currency_position == 0)
				{
				?>
					<div class="<?php echo $inputPrependClass; ?> inline-display">
						<span class="<?php echo $addOnClass; ?>"><?php echo $this->currencySymbol?></span>
						<input id="tax_amount" type="text" readonly="readonly" class="input-small" value="<?php echo OSMembershipHelper::formatAmount($this->fees['tax_amount'], $this->config); ?>" />
					</div>
				<?php
				}
				else
				{
				?>
					<div class="<?php echo $inputAppendClass; ?> inline-display">
						<input id="tax_amount" type="text" readonly="readonly" class="input-small" value="<?php echo OSMembershipHelper::formatAmount($this->fees['tax_amount'], $this->config); ?>" />
						<span class="<?php echo $addOnClass; ?>"><?php echo $this->currencySymbol?></span>
					</div>
				<?php
				}
				?>
			</div>
		</div>
		<?php
	}
	if ($this->showPaymentFee)
	{
	?>
		<div class="<?php echo $controlGroupClass ?>">
			<div class="<?php echo $controlLabelClass; ?>">
				<label><?php echo JText::_('OSM_PAYMENT_FEE'); ?></label>
			</div>
			<div class="<?php echo $controlsClass; ?>">
				<?php
				if ($this->config->currency_position == 0)
				{
				?>
					<div class="<?php echo $inputPrependClass; ?> inline-display">
						<span class="<?php echo $addOnClass; ?>"><?php echo $this->currencySymbol?></span>
						<input id="payment_processing_fee" type="text" readonly="readonly" class="input-small" value="<?php echo OSMembershipHelper::formatAmount($this->fees['payment_processing_fee'], $this->config); ?>" />
					</div>
				<?php
				}
				else
				{
				?>
					<div class="<?php echo $inputAppendClass; ?> inline-display">
						<input id="payment_processing_fee" type="text" readonly="readonly" class="input-small" value="<?php echo OSMembershipHelper::formatAmount($this->fees['payment_processing_fee'], $this->config); ?>" />
						<span class="<?php echo $addOnClass; ?>"><?php echo $this->currencySymbol?></span>
					</div>
				<?php
				}
				?>
			</div>
		</div>
	<?php
	}
	if ($this->config->enable_coupon || $this->taxRate > 0 || $this->showPaymentFee)
	{
	?>
		<div class="<?php echo $controlGroupClass ?>">
			<div class="<?php echo $controlLabelClass; ?>">
				<label><?php echo JText::_('OSM_GROSS_AMOUNT'); ?></label>
			</div>
			<div class="<?php echo $controlsClass; ?>">
				<?php
				if ($this->config->currency_position == 0)
				{
				?>
					<div class="<?php echo $inputPrependClass; ?> inline-display">
						<span class="<?php echo $addOnClass; ?>"><?php echo $this->currencySymbol?></span>
						<input id="gross_amount" type="text" readonly="readonly" class="input-small" value="<?php echo OSMembershipHelper::formatAmount($this->fees['gross_amount'], $this->config); ?>" />
					</div>
				<?php
				}
				else
				{
				?>
					<div class="<?php echo $inputAppendClass; ?> inline-display">
						<input id="gross_amount" type="text" readonly="readonly" class="input-small" value="<?php echo OSMembershipHelper::formatAmount($this->fees['gross_amount'], $this->config); ?>" />
						<span class="<?php echo $addOnClass; ?>"><?php echo $this->currencySymbol?></span>
					</div>
				<?php
				}
				?>
			</div>
		</div>
	<?php
	}
}