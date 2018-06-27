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
$selectedState = '';
?>
<script type="text/javascript">
	var siteUrl = '<?php echo OSMembershipHelper::getSiteUrl();  ?>';
</script>
<?php
OSMembershipHelperJquery::validateForm();
switch($this->action)
{
	case 'upgrade' :
		$headerText = JText::_('OSM_SUBSCRIION_UPGRADE_FORM_HEADING');
		break ;
	case 'renew' :
		$headerText = JText::_('OSM_SUBSCRIION_RENEW_FORM_HEADING');
		break ;
	default :
		$headerText = JText::_('OSM_SUBSCRIPTION_FORM_HEADING') ;
		break ;
}
$headerText        = str_replace('[PLAN_TITLE]', $this->plan->title, $headerText);

/**@var OSMembershipHelperBootstrap $bootstrapHelper **/
$bootstrapHelper   = $this->bootstrapHelper;
$controlGroupClass = $bootstrapHelper->getClassMapping('control-group');
$inputPrependClass = $bootstrapHelper->getClassMapping('input-prepend');
$inputAppendClass  = $bootstrapHelper->getClassMapping('input-append');
$addOnClass        = $bootstrapHelper->getClassMapping('add-on');
$controlLabelClass = $bootstrapHelper->getClassMapping('control-label');
$controlsClass     = $bootstrapHelper->getClassMapping('controls');

$fields = $this->form->getFields();

if (isset($fields['state']))
{
	$selectedState = $fields['state']->value;
}

/**@var OSMembershipViewRegisterHtml $this **/

?>
<div id="osm-singup-page" class="osm-container">
<h1 class="osm-page-title"><?php echo $headerText; ?></h1>
<?php
if (strlen($this->message))
{
?>
	<div class="osm-message clearfix"><?php echo $this->message; ?></div>
<?php
}

// Login form for existing user
echo $this->loadTemplate('login', array('fields' => $fields));
?>
<form method="post" name="os_form" id="os_form" action="<?php echo JRoute::_('index.php?option=com_osmembership&task=register.process_subscription&Itemid='.$this->Itemid, false, $this->config->use_https ? 1 : 0); ?>" enctype="multipart/form-data" autocomplete="off" class="form form-horizontal">
	<?php
	echo $this->loadTemplate('form', array('fields' => $fields));

	if ((isset($this->fees['amount']) && $this->fees['amount'] > 0) || $this->form->containFeeFields() || $this->plan->recurring_subscription)
	{
	?>
		<h3 class="osm-heading"><?php echo JText::_('OSM_PAYMENT_INFORMATION');?></h3>
	<?php
		echo $this->loadTemplate('payment_information');
		echo $this->loadTemplate('payment_methods');
	}

	$layoutData = [
		'controlGroupClass' => $controlGroupClass,
		'controlLabelClass' => $controlLabelClass,
		'controlsClass'     => $controlsClass,
	];

	if ($this->config->show_privacy_policy_checkbox || $this->config->show_subscribe_newsletter_checkbox)
    {
        echo $this->loadTemplate('gdpr', $layoutData);
    }


    echo $this->loadTemplate('terms_conditions', $layoutData);

	if ($this->showCaptcha) 
	{
	?>
		<div class="<?php echo $controlGroupClass ?> osm-captcha-container">
			<label class="<?php echo $controlLabelClass; ?>">
				<?php echo JText::_('OSM_CAPTCHA'); ?><span class="required">*</span>
			</label>
			<div class="<?php echo $controlsClass; ?>">
				<?php echo $this->captcha;?>
			</div>
		</div>
	<?php
	}
	?>
	<div class="form-actions">
		<input type="submit" class="<?php echo $bootstrapHelper->getClassMapping('btn'); ?> btn-primary" name="btnSubmit" id="btn-submit" value="<?php echo  JText::_('OSM_PROCESS_SUBSCRIPTION') ;?>">
		<img id="ajax-loading-animation" src="<?php echo JUri::base();?>media/com_osmembership/ajax-loadding-animation.gif" style="display: none;"/>
	</div>
<?php
	if (count($this->methods) == 1)
	{
	?>
		<input type="hidden" name="payment_method" value="<?php echo $this->methods[0]->getName(); ?>" />
	<?php
	}
?>
	<input type="hidden" name="Itemid" value="<?php echo $this->Itemid; ?>" />
	<input type="hidden" name="plan_id" value="<?php echo $this->plan->id ; ?>" />
	<input type="hidden" name="option" value="com_osmembership" />
	<input type="hidden" name="act" value="<?php echo $this->action ; ?>" />
	<input type="hidden" name="renew_option_id" value="<?php echo $this->renewOptionId ; ?>" />
	<input type="hidden" name="upgrade_option_id" value="<?php echo $this->upgradeOptionId ; ?>" />
	<input type="hidden" name="show_payment_fee" value="<?php echo (int)$this->showPaymentFee ; ?>" />
	<input type="hidden" name="vat_number_field" value="<?php echo $this->config->eu_vat_number_field ; ?>" />
	<input type="hidden" name="country_base_tax" value="<?php echo $this->countryBaseTax; ?>" />	
	<input type="hidden" name="default_country" id="default_country" value="<?php echo $this->config->default_country; ?>" />
    <input type="hidden" id="card-nonce" name="nonce" />
	<?php echo JHtml::_( 'form.token' ); ?>
</form>
</div>
	<script type="text/javascript">
		var taxStateCountries = "<?php echo $this->taxStateCountries;?>";
		taxStateCountries = taxStateCountries.split(',');
		OSM.jQuery(function($){
			$(document).ready(function(){
				<?php
				if (!$this->userId && $this->config->show_login_box_on_subscribe_page)
				{
				?>
					OSMVALIDATEFORM("#osm_login_form");
				<?php
				}
				?>
				$("#os_form").validationEngine('attach', {
					onValidationComplete: function(form, status){
						if (status == true) {
							form.on('submit', function(e) {
								e.preventDefault();
							});

							form.find('#btn-submit').prop('disabled', true);
							<?php
								if ($this->plan->price > 0)
								{
								?>
                                    if($('input:radio[name^=payment_method]').length)
                                    {
                                        var paymentMethod = $('input:radio[name^=payment_method]:checked').val();
                                    }
                                    else
                                    {
                                        var paymentMethod = $('input[name^=payment_method]').val();
                                    }

                                    if (typeof stripePublicKey !== 'undefined' && paymentMethod.indexOf('os_stripe') == 0 && $('#tr_card_number').is(':visible'))
									{
											Stripe.card.createToken({
												number: $('input[name^=x_card_num]').val(),
												cvc: $('input[name^=x_card_code]').val(),
												exp_month: $('select[name^=exp_month]').val(),
												exp_year: $('select[name^=exp_year]').val(),
												name: $('input[name^=card_holder_name]').val()
											}, stripeResponseHandler);

											return false;
									}

                                    // Stripe card element
                                    if (typeof stripe !== 'undefined' && paymentMethod.indexOf('os_stripe') == 0 && $('#stripe-card-form').is(":visible"))
                                    {
                                        stripe.createToken(card).then(function(result) {
                                            if (result.error) {
                                                // Inform the customer that there was an error.
                                                //var errorElement = document.getElementById('card-errors');
                                                //errorElement.textContent = result.error.message;
                                                alert(result.error.message);
                                            } else {
                                                // Send the token to your server.
                                                stripeTokenHandler(result.token);
                                            }
                                        });

                                        return false;
                                    }

                                    if (paymentMethod == 'os_squareup' && $('#tr_card_number').is(':visible'))
                                    {
                                        sqPaymentForm.requestCardNonce();

                                        return false;
                                    }
								<?php
								}
							?>
							return true;
						}
						return false;
					}
				});

				<?php
					if (isset($this->fees['amount']) && $this->fees['amount'] == 0 && !$this->plan->recurring_subscription)
					{
					?>
						$('.payment_information').css('display', 'none');
					<?php
					}
					if ($this->config->eu_vat_number_field)
					{
					?>
						// Add css class for vat number field
						$('input[name^=<?php echo $this->config->eu_vat_number_field   ?>]').addClass('taxable');
						$('input[name^=<?php echo $this->config->eu_vat_number_field   ?>]').before('<div class="<?php echo $inputPrependClass; ?> inline-display"><span class="<?php echo $addOnClass; ?>" id="vat_country_code"><?php echo $this->countryCode; ?></span>');
						$('input[name^=<?php echo $this->config->eu_vat_number_field   ?>]').after('<span class="invalid" id="vatnumber_validate_msg" style="display: none;"><?php echo ' '.JText::_('OSM_INVALID_VATNUMBER'); ?></span></div>');
						$('input[name^=<?php echo $this->config->eu_vat_number_field   ?>]').change(function(){
							calculateSubscriptionFee();
						});
						<?php
						}
					?>
					buildStateField('state', 'country', '<?php echo $selectedState; ?>');

                    if (typeof stripe !== 'undefined')
                    {
                        var style = {
                            base: {
                                // Add your base input styles here. For example:
                                fontSize: '16px',
                                color: "#32325d",
                            }
                        };

                        // Create an instance of the card Element.
                        var card = elements.create('card', {style: style});

                        // Add an instance of the card Element into the `card-element` <div>.
                        card.mount('#stripe-card-element');
                    }
			})
		});
		<?php
			os_payments::writeJavascriptObjects();
		?>
	</script>