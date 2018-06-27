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
	if ($this->fees['amount'] > 0 || $this->form->containFeeFields() || $this->plan->recurring_subscription)
	{
	?>
		<h3 class="osm-heading"><?php echo JText::_('OSM_PAYMENT_INFORMATION');?></h3>
		<?php
		echo $this->loadTemplate('payment_information');
		echo $this->loadTemplate('payment_methods');
	}
	$articleId =  $this->plan->terms_and_conditions_article_id > 0 ? $this->plan->terms_and_conditions_article_id : $this->config->article_id;
	if ($articleId > 0)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__content')
			->where('id = '. (int) $articleId);
		$db->setQuery($query);
		$rowArticle = $db->loadObject() ;
		$catId = $rowArticle->catid ;
		require_once JPATH_ROOT.'/components/com_content/helpers/route.php' ;
	?>
	<div class="<?php echo $controlGroupClass ?>">
		<input type="checkbox" name="accept_term" value="1" class="validate[required] osm_inputbox inputbox" />
		<strong><?php echo JText::_('OSM_ACCEPT'); ?>&nbsp;<a href="<?php echo JRoute::_(ContentHelperRoute::getArticleRoute($articleId, $catId).'&tmpl=component&format=html'); ?>" class="osm-modal" rel="{handler: 'iframe', size: {x: 700, y: 500}}"><?php echo JText::_('OSM_TERM_AND_CONDITION'); ?></a></strong>
	</div>
	<?php
	}
	if ($this->config->enable_captcha) {
	?>
		<div class="<?php echo $controlGroupClass ?>">
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
									if (typeof stripePublicKey !== 'undefined')
									{
										if($('input:radio[name^=payment_method]').length)
										{
											var paymentMethod = $('input:radio[name^=payment_method]:checked').val();
										}
										else
										{
											var paymentMethod = $('input[name^=payment_method]').val();
										}

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
								<?php
								}
							?>
							return true;
						}
						return false;
					}
				});

				<?php
					if ($this->fees['amount'] == 0 && !$this->plan->recurring_subscription)
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
						$('input[name^=<?php echo $this->config->eu_vat_number_field   ?>]').before('<div class="<?php echo $inputPrependClass; ?>"><span class="<?php echo $addOnClass; ?>" id="vat_country_code"><?php echo $this->countryCode; ?></span>');
						$('input[name^=<?php echo $this->config->eu_vat_number_field   ?>]').after('<span class="invalid" id="vatnumber_validate_msg" style="display: none;"><?php echo ' '.JText::_('OSM_INVALID_VATNUMBER'); ?></span></div>');
						$('input[name^=<?php echo $this->config->eu_vat_number_field   ?>]').change(function(){
							calculateSubscriptionFee();
						});
						<?php
						}
					?>
					buildStateField('state', 'country', '<?php echo $selectedState; ?>');
			})
		});
		<?php
			os_payments::writeJavascriptObjects();
		?>
	</script>