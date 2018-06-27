<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die ;

/**@var OSMembershipHelperBootstrap $bootstrapHelper **/
$bootstrapHelper   = $this->bootstrapHelper;
$controlGroupClass = $bootstrapHelper->getClassMapping('control-group');
$inputPrependClass = $bootstrapHelper->getClassMapping('input-prepend');
$inputAppendClass  = $bootstrapHelper->getClassMapping('input-append');
$addOnClass        = $bootstrapHelper->getClassMapping('add-on');
$controlLabelClass = $bootstrapHelper->getClassMapping('control-label');
$controlsClass     = $bootstrapHelper->getClassMapping('controls');
?>
<div class="<?php echo $controlGroupClass ?>">
	<label class="<?php echo $controlLabelClass; ?>">
		<?php echo JText::_('OSM_PAYMENT_TERMS'); ?>
	</label>
	<div class="<?php echo $controlsClass; ?>" id="payment-terms">
		<?php echo $this->fees['payment_terms']; ?>
	</div>
</div>
<?php
if ($this->fees['trial_duration'] > 0)
{
?>
	<div class="<?php echo $controlGroupClass ?>">
		<label class="<?php echo $controlLabelClass; ?>">
			<?php echo JText::_('OSM_TRIAL_DURATION_PRICE'); ?>
		</label>
		<div class="<?php echo $controlsClass; ?>">
			<?php
                $input = '<input id="trial_amount" type="text" readonly="readonly" class="input-small" value="' . OSMembershipHelper::formatAmount($this->fees['trial_amount'], $this->config) . '" />';

                if ($this->config->currency_position == 0)
                {
                    echo $bootstrapHelper->getPrependAddon($input, $this->currencySymbol);
                }
                else
                {
                    echo $bootstrapHelper->getAppendAddon($input, $this->currencySymbol);
                }
			?>
		</div>
	</div>
<?php
	if ($this->fees['trial_discount_amount'] > 0)
	{
		$style = '' ;
	}
	else
	{
		$style = ' style = "display:none;" ' ;
	}
?>
<div class="<?php echo $controlGroupClass ?>" id="trial_discount_amount_container"<?php echo $style; ?>>
	<label class="<?php echo $controlLabelClass; ?>">
		<?php echo JText::_('OSM_TRIAL_DURATION_DISCOUNT'); ?>
	</label>
	<div class="<?php echo $controlsClass; ?>">
		<?php
		$input = '<input id="trial_discount_amount" type="text" readonly="readonly" class="input-small" value="' . OSMembershipHelper::formatAmount($this->fees['trial_discount_amount'], $this->config) . '" />';

		if ($this->config->currency_position == 0)
		{
			echo $bootstrapHelper->getPrependAddon($input, $this->currencySymbol);
		}
		else
		{
			echo $bootstrapHelper->getAppendAddon($input, $this->currencySymbol);
		}
		?>
	</div>
</div>

<?php
	if ($this->fees['trial_tax_amount'] > 0)
	{
		$style = '' ;
	}
	else
	{
		$style = ' style = "display:none;" ' ;
	}
?>
<div class="<?php echo $controlGroupClass ?>" id="trial_tax_amount_container"<?php echo $style; ?>>
	<div class="<?php echo $controlLabelClass; ?>">
		<label><?php echo JText::_('OSM_TRIAL_TAX_AMOUNT'); ?></label>
	</div>
	<div class="<?php echo $controlsClass; ?>">
		<?php
		$input = '<input id="trial_tax_amount" type="text" readonly="readonly" class="input-small" value="' . OSMembershipHelper::formatAmount($this->fees['trial_tax_amount'], $this->config) . '" />';

		if ($this->config->currency_position == 0)
		{
			echo $bootstrapHelper->getPrependAddon($input, $this->currencySymbol);
		}
		else
		{
			echo $bootstrapHelper->getAppendAddon($input, $this->currencySymbol);
		}
		?>
	</div>
</div>
<?php
	if ($this->fees['trial_payment_processing_fee'] > 0)
	{
		$style = '';
	}
	else
	{
		$style = ' style = "display:none;" ' ;
	}
?>
<div class="<?php echo $controlGroupClass ?>" id="trial_payment_processing_fee_container"<?php echo $style; ?>>
	<div class="<?php echo $controlLabelClass; ?>">
		<label><?php echo JText::_('OSM_TRIAL_PAYMENT_FEE'); ?></label>
	</div>
	<div class="<?php echo $controlsClass; ?>">
		<?php
		$input = '<input id="trial_payment_processing_fee" type="text" readonly="readonly" class="input-small" value="' . OSMembershipHelper::formatAmount($this->fees['trial_payment_processing_fee'], $this->config) . '" />';

		if ($this->config->currency_position == 0)
		{
			echo $bootstrapHelper->getPrependAddon($input, $this->currencySymbol);
		}
		else
		{
			echo $bootstrapHelper->getAppendAddon($input, $this->currencySymbol);
		}
		?>
	</div>
</div>
<?php
	if ($this->fees['trial_discount_amount'] > 0 || $this->fees['trial_tax_amount'] || $this->fees['trial_payment_processing_fee'] > 0)
	{
		$style = '';
	}
	else
	{
		$style = ' style = "display:none;" ' ;
	}
?>
	<div class="<?php echo $controlGroupClass ?>" id="trial_gross_amount_container"<?php echo $style; ?>>
		<div class="<?php echo $controlLabelClass; ?>">
			<label><?php echo JText::_('OSM_GROSS_TRIAL_AMOUNT'); ?></label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<?php
			$input = '<input id="trial_gross_amount" type="text" readonly="readonly" class="input-small" value="' . OSMembershipHelper::formatAmount($this->fees['trial_gross_amount'], $this->config) . '" />';

			if ($this->config->currency_position == 0)
			{
				echo $bootstrapHelper->getPrependAddon($input, $this->currencySymbol);
			}
			else
			{
				echo $bootstrapHelper->getAppendAddon($input, $this->currencySymbol);
			}
			?>
			<div class="explain-text inline-display"><?php echo $this->trialPeriorText; ?></div>
		</div>
	</div>
<?php
}
?>
<div class="<?php echo $controlGroupClass ?>">
	<div class="<?php echo $controlLabelClass; ?>">
		<label><?php echo JText::_('OSM_REGULAR_PRICE'); ?></label>
	</div>
	<div class="<?php echo $controlsClass; ?>">
		<?php
		$input = '<input id="regular_amount" type="text" readonly="readonly" class="input-small" value="' . OSMembershipHelper::formatAmount($this->fees['regular_amount'], $this->config) . '" />';

		if ($this->config->currency_position == 0)
		{
			echo $bootstrapHelper->getPrependAddon($input, $this->currencySymbol);
		}
		else
		{
			echo $bootstrapHelper->getAppendAddon($input, $this->currencySymbol);
		}
		?>
	</div>
</div>

<?php
if ($this->fees['regular_discount_amount'] > 0)
{
	$style = '' ;
}
else
{
	$style = ' style = "display:none;" ' ;
}
?>
	<div class="<?php echo $controlGroupClass ?>" id="regular_discount_amount_container"<?php echo $style; ?>>
		<div class="<?php echo $controlLabelClass; ?>">
			<label><?php echo JText::_('OSM_REGULAR_DISCOUNT'); ?></label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<?php
			$input = '<input id="regular_discount_amount" type="text" readonly="readonly" class="input-small" value="' . OSMembershipHelper::formatAmount($this->fees['regular_discount_amount'], $this->config) . '" />';

			if ($this->config->currency_position == 0)
			{
				echo $bootstrapHelper->getPrependAddon($input, $this->currencySymbol);
			}
			else
			{
				echo $bootstrapHelper->getAppendAddon($input, $this->currencySymbol);
			}
			?>
		</div>
	</div>

<?php
if ($this->fees['regular_tax_amount'] > 0)
{
	$style = '' ;
}
else
{
	$style = ' style = "display:none;" ' ;
}
?>
	<div class="<?php echo $controlGroupClass ?>" id="regular_tax_amount_container"<?php echo $style; ?>>
		<div class="<?php echo $controlLabelClass; ?>">
			<label><?php echo JText::_('OSM_REGULAR_TAX'); ?></label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<?php
			$input = '<input id="regular_tax_amount" type="text" readonly="readonly" class="input-small" value="' . OSMembershipHelper::formatAmount($this->fees['regular_tax_amount'], $this->config) . '" />';

			if ($this->config->currency_position == 0)
			{
				echo $bootstrapHelper->getPrependAddon($input, $this->currencySymbol);
			}
			else
			{
				echo $bootstrapHelper->getAppendAddon($input, $this->currencySymbol);
			}
			?>
		</div>
	</div>
<?php
if ($this->fees['regular_payment_processing_fee'] > 0)
{
	$style = '';
}
else
{
	$style = ' style = "display:none;" ' ;
}
?>
	<div class="<?php echo $controlGroupClass ?>" id="regular_payment_processing_fee_container"<?php echo $style; ?>>
		<div class="<?php echo $controlLabelClass; ?>">
			<label><?php echo JText::_('OSM_PAYMENT_FEE'); ?></label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<?php
			$input = '<input id="regular_payment_processing_fee" type="text" readonly="readonly" class="input-small" value="' . OSMembershipHelper::formatAmount($this->fees['regular_payment_processing_fee'], $this->config) . '" />';

			if ($this->config->currency_position == 0)
			{
				echo $bootstrapHelper->getPrependAddon($input, $this->currencySymbol);
			}
			else
			{
				echo $bootstrapHelper->getAppendAddon($input, $this->currencySymbol);
			}
			?>
		</div>
	</div>
<?php
if ($this->fees['regular_discount_amount'] > 0 || $this->fees['regular_tax_amount'] || $this->fees['regular_payment_processing_fee'] > 0)
{
	$style = '';
}
else
{
	$style = ' style = "display:none;" ' ;
}
?>
<div class="<?php echo $controlGroupClass ?>" id="regular_gross_amount_container"<?php echo $style; ?>>
	<div class="<?php echo $controlLabelClass; ?>">
		<label><?php echo JText::_('OSM_REGULAR_GROSS_AMOUNT'); ?></label>
	</div>
	<div class="<?php echo $controlsClass; ?>">
		<?php
		$input = '<input id="regular_gross_amount" type="text" readonly="readonly" class="input-small" value="' . OSMembershipHelper::formatAmount($this->fees['regular_gross_amount'], $this->config) . '" />';

		if ($this->config->currency_position == 0)
		{
			echo $bootstrapHelper->getPrependAddon($input, $this->currencySymbol);
		}
		else
		{
			echo $bootstrapHelper->getAppendAddon($input, $this->currencySymbol);
		}
		?>
	</div>
</div>

