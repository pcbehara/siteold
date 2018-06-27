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

$stripePaymentMethod = null;

if (count($this->methods) > 1)
{
?>
	<div class="<?php echo $controlGroupClass; ?> payment_information<?php if ($this->useIconForPaymentMethods) echo ' payment-methods-icons'; ?>" id="payment_method_container">
		<div class="<?php echo $controlLabelClass; ?>" >
			<label for="payment_method">
				<?php echo JText::_('OSM_PAYMENT_OPTION'); ?>
				<span class="required">*</span>
			</label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<ul id="osm-payment-method-list" class="nav clearfix">
				<?php
				$baseUri = JUri::base(true);
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

					if (strpos($paymentMethod->getName(), 'os_stripe') !== false)
					{
						$stripePaymentMethod = $paymentMethod;
					}
					?>
					<li class="osm-payment-method-item <?php echo $paymentMethod->getName(); ?> radio clearfix">
						<input onclick="changePaymentMethod();" id="osm-payment-method-item-<?php echo $i; ?>" type="radio" name="payment_method" value="<?php echo $paymentMethod->getName(); ?>" <?php echo $checked; ?> />
						<label for="osm-payment-method-item-<?php echo $i; ?>">
							<?php
								if ($paymentMethod->iconUri)
								{
								?>
									<img class="osm-payment-method-icon clearfix" src="<?php echo $paymentMethod->iconUri; ?>" title="<?php echo JText::_($paymentMethod->title); ?>"  />
								<?php
								}
								else
								{
									echo JText::_($paymentMethod->title);
								}
							?>
						</label>
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
	$method = $this->methods[0];

	if (strpos($method->getName(), 'os_stripe') !== false)
	{
		$stripePaymentMethod = $method;
	}
?>
	<div class="<?php echo $controlGroupClass; ?> payment_information" id="payment_method_container">
		<div class="<?php echo $controlLabelClass; ?>">
			<label for="payment_method">
				<?php echo JText::_('OSM_PAYMENT_OPTION'); ?>
			</label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<?php
				if ($method->iconUri)
				{
				?>
					<img class="osm-payment-method-icon clearfix" src="<?php echo $method->iconUri; ?>" title="<?php echo JText::_($method->title); ?>"  />
				<?php
				}
				else
				{
					echo JText::_($method->title);
				}
			?>
		</div>
	</div>
<?php
}

if ($method->getName() == 'os_squareup')
{
	$style = '';
}
else
{
	$style = 'style = "display:none"';
}
?>
<div class="<?php echo $controlGroupClass;  ?> payment_information" id="sq_field_zipcode" <?php echo $style; ?>>
    <label class="<?php echo $controlLabelClass; ?>" for="sq_billing_zipcode">
		<?php echo JText::_('OSM_SQUAREUP_ZIPCODE'); ?><span class="required">*</span>
    </label>

    <div class="<?php echo $controlsClass; ?>" id="field_zip_input">
        <input type="text" id="sq_billing_zipcode" name="sq_billing_zipcode"
               class="input-large"
               value="<?php echo $this->escape($this->input->getString('sq_billing_zipcode')); ?>" />
    </div>
</div>
<?php

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
		<div class="<?php echo $controlsClass; ?>" id="sq-card-number">
			<input type="text" name="x_card_num" class="validate[required,creditCard] osm_inputbox inputbox" value="<?php echo $this->escape($this->input->post->getAlnum('x_card_num'));?>" size="20" />
		</div>
	</div>
	<div class="<?php echo $controlGroupClass; ?> payment_information" id="tr_exp_date" <?php echo $style; ?>>
		<div class="<?php echo $controlLabelClass; ?>">
			<label>
				<?php echo JText::_('AUTH_CARD_EXPIRY_DATE'); ?><span class="required">*</span>
			</label>
		</div>
		<div class="<?php echo $controlsClass; ?>" id="sq-expiration-date">
			<?php echo $this->lists['exp_month'] .'  /  '.$this->lists['exp_year'] ; ?>
		</div>
	</div>
	<div class="<?php echo $controlGroupClass; ?> payment_information" id="tr_cvv_code" <?php echo $style; ?>>
		<div class="<?php echo $controlLabelClass; ?>">
			<label>
				<?php echo JText::_('AUTH_CVV_CODE'); ?><span class="required">*</span>
			</label>
		</div>
		<div class="<?php echo $controlsClass; ?>" id="sq-cvv">
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

<?php
if ($stripePaymentMethod !== null && method_exists($stripePaymentMethod, 'getParams'))
{
	/* @var os_stripe $stripePaymentMethod */
	$params = $stripePaymentMethod->getParams();
	$useStripeCardElement = $params->get('use_stripe_card_element', 0);

	if ($useStripeCardElement)
	{
		if ($method->getName() === 'os_stripe')
		{
			$style = '';
		}
		else
		{
			$style = ' style = "display:none;" ';
		}
		?>
        <div class="control-group payment_information" <?php echo $style; ?> id="stripe-card-form">
            <label class="control-label" for="stripe-card-element">
				<?php echo JText::_('OSM_CREDIT_OR_DEBIT_CARD'); ?><span class="required">*</span>
            </label>
            <div class="controls" id="stripe-card-element">

            </div>
        </div>
		<?php
	}
}
