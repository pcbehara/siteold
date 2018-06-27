<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die;

OSMembershipHelperJquery::validateForm();

$bootstrapHelper   = $this->bootstrapHelper;
$controlGroupClass = $bootstrapHelper->getClassMapping('control-group');
$controlLabelClass = $bootstrapHelper->getClassMapping('control-label');
$controlsClass     = $bootstrapHelper->getClassMapping('controls');
?>
<h1 class="osm-page-title"><?php echo JText::_('OSM_UPDATE_CARD'); ?></h1>
<form method="post" name="os_form" id="os_form" action="<?php echo JRoute::_('index.php?option=com_osmembership&task=profile.update_card&Itemid='.$this->Itemid, false, $this->config->use_https ? 1 : 0); ?>" autocomplete="off" class="form form-horizontal">
	<div class="<?php echo $controlGroupClass; ?> payment_information" id="tr_card_number">
		<div class="<?php echo $controlLabelClass; ?>">
			<label><?php echo  JText::_('AUTH_CARD_NUMBER'); ?><span class="required">*</span></label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<input type="text" name="x_card_num" class="validate[required,creditCard] osm_inputbox inputbox" value="<?php echo $this->escape($this->input->post->getAlnum('x_card_num'));?>" size="20" />
		</div>
	</div>
	<div class="<?php echo $controlGroupClass; ?> payment_information" id="tr_exp_date">
		<div class="<?php echo $controlLabelClass; ?>">
			<label>
				<?php echo JText::_('AUTH_CARD_EXPIRY_DATE'); ?><span class="required">*</span>
			</label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<?php echo $this->lists['exp_month'] .'  /  '.$this->lists['exp_year'] ; ?>
		</div>
	</div>
	<div class="<?php echo $controlGroupClass; ?> payment_information" id="tr_cvv_code">
		<div class="<?php echo $controlLabelClass; ?>">
			<label>
				<?php echo JText::_('AUTH_CVV_CODE'); ?><span class="required">*</span>
			</label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<input type="text" name="x_card_code" class="validate[required,custom[number]] osm_inputbox input-small" value="<?php echo $this->escape($this->input->post->getString('x_card_code')); ?>" size="20" />
		</div>
	</div>
	<div class="<?php echo $controlGroupClass; ?> payment_information" id="tr_card_holder_name">
		<div class="<?php echo $controlLabelClass; ?>">
			<label>
				<?php echo JText::_('OSM_CARD_HOLDER_NAME'); ?><span class="required">*</span>
			</label>
		</div>
		<div class="<?php echo $controlsClass; ?>">
			<input type="text" name="card_holder_name" class="validate[required] osm_inputbox inputbox"  value="<?php echo $this->input->post->getString('card_holder_name'); ?>" size="40" />
		</div>
	</div>
	<div class="form-actions">
		<input type="submit" class="<?php echo $this->bootstrapHelper->getClassMapping('btn'); ?> btn-primary" name="btnSubmit" id="btn-submit" value="<?php echo  JText::_('OSM_UPDATE') ;?>" />
	</div>

	<input type="hidden" name="subscription_id" value="<?php echo $this->subscription->subscription_id; ?>" />
	<?php echo JHtml::_( 'form.token' ); ?>

	<script type="text/javascript">
		OSM.jQuery(function($){
			$(document).ready(function(){
				$("#os_form").validationEngine('attach', {
					onValidationComplete: function(form, status){
						if (status == true) {
							form.on('submit', function(e) {
								e.preventDefault();
							});

							form.find('#btn-submit').prop('disabled', true);

							if (typeof stripePublicKey !== 'undefined')
							{
								paymentMethod = '<?php echo $this->subscription->payment_method; ?>';

								if (paymentMethod == 'os_stripe' && $('input[name^=x_card_code]').is(':visible'))
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
							}

							return true;
						}
						return false;
					}
				});
			});
		});
	</script>
</form>
