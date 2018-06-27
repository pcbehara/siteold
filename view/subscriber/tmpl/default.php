<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined( '_JEXEC' ) or die ;
JHtml::_('formbehavior.chosen', 'select');

$document = JFactory::getDocument();
$document->addScriptDeclaration('
	var siteUrl = "' . JUri::root() . '";			
');
$document->addScript(JUri::root(true) . '/media/com_osmembership/assets/js/membershippro.js');

OSMembershipHelper::loadLanguage();
OSMembershipHelperJquery::validateForm();

$bootstrapHelper = new OSMembershipHelperBootstrap($this->config->twitter_bootstrap_version);
$controlGroupClass = $bootstrapHelper->getClassMapping('control-group');
$controlLabelClass = $bootstrapHelper->getClassMapping('control-label');
$controlsClass     = $bootstrapHelper->getClassMapping('controls');

$selectedState = '';
?>
<script type="text/javascript">
	Joomla.submitbutton = function(pressbutton)
	{
		var form = document.adminForm;

		if (pressbutton == 'cancel')
		{
			jQuery("#adminForm").validationEngine('detach');
			Joomla.submitform(pressbutton, form);
		}
		else
		{
			//Validate the entered data before submitting
			Joomla.submitform(pressbutton, form);
		}
	}
</script>
<h1><?php echo $this->item->id ? JText::_('OSM_EDIT_SUBSCRIPTION') : JText::_('OSM_ADD_SUBSCRIPTION'); ?></h1>
<div class="row-fluid">
<form action="index.php?option=com_osmembership&view=subscriber&Itemid=<?php echo $this->Itemid; ?>" method="post" name="adminForm" id="adminForm" autocomplete="off" enctype="multipart/form-data" class="form form-horizontal">
	<div class="btn-toolbar" id="btn-toolbar">
		<?php echo JToolbar::getInstance('toolbar')->render('toolbar'); ?>
	</div>
	<fieldset>
        <div class="<?php echo $controlGroupClass; ?>">
            <label class="<?php echo $controlLabelClass; ?>">
                <?php echo JText::_('OSM_PLAN'); ?><span class="required">&nbsp;*</span>
            </label>
            <div class="<?php echo $controlsClass; ?>">
                <?php echo $this->lists['plan_id'] ; ?>
            </div>
        </div>
        <div class="<?php echo $controlGroupClass; ?>">
            <label class="<?php echo $controlLabelClass; ?>">
                <?php echo JText::_('OSM_SELECT_USER'); ?>
            </label>
            <div class="<?php echo $controlsClass; ?>">
                <?php echo OSMembershipHelper::getUserInput($this->item->user_id, (int) $this->item->id) ; ?>
            </div>
        </div>
		<?php
		if (!$this->item->id)
		{
		?>
			<div class="<?php echo $controlGroupClass; ?>" id="username_container">
				<label class="<?php echo $controlLabelClass; ?>">
					<?php echo JText::_('OSM_USERNAME'); ?><span class="required">*</span>
				</label>
				<div class="<?php echo $controlsClass; ?>">
					<input type="text" name="username" size="20" class="validate[ajax[ajaxUserCall]]" value="" />
					<?php echo JText::_('OSM_USERNAME_EXPLAIN'); ?>
				</div>
			</div>
			<div class="<?php echo $controlGroupClass; ?>" id="password_container">
				<label class="<?php echo $controlLabelClass; ?>">
					<?php echo JText::_('OSM_PASSWORD'); ?><span class="required">*</span>
				</label>
				<div class="<?php echo $controlsClass; ?>">
					<?php
						$params = JComponentHelper::getParams('com_users');
						$minimumLength = $params->get('minimum_length', 4);
						($minimumLength) ? $minSize = "minSize[$minimumLength]" : $minSize = "";
						$passwordValidation = ',ajax[ajaxValidatePassword]';
					?>
					<input type="password" name="password" size="20" value="" class="validate[<?php echo $minSize.$passwordValidation;?>]" />
				</div>
			</div>
		<?php
		}
		if ($this->config->auto_generate_membership_id)
		{
		?>
			<div class="<?php echo $controlGroupClass; ?>">
				<label class="<?php echo $controlLabelClass; ?>">
					<?php echo JText::_('OSM_MEMBERSHIP_ID'); ?>
				</label>
				<div class="<?php echo $controlsClass; ?>">
					<input type="text" name="membership_id" value="<?php echo $this->item->membership_id > 0 ? $this->item->membership_id : ''; ?>" class="inputbox" size="20" />
				</div>
			</div>
		<?php
		}

		$fields = $this->form->getFields();

		$stateType = 0;

		if (isset($fields['state']))
		{
			if ($fields['state']->type == 'State')
			{
				$stateType = 1;
			}
			else
			{
				$stateType = 0;
			}

			$selectedState = $fields['state']->value;
		}

		if (isset($fields['email']))
		{
			$fields['email']->setAttribute('class', 'validate[required,custom[email]]');
		}

		foreach ($fields as $field)
		{
			/* @var MPFFormField $field */
			echo $field->getControlGroup($bootstrapHelper);
		}
		?>
		<div class="<?php echo $controlGroupClass; ?>">
			<label class="<?php echo $controlLabelClass; ?>">
				<?php echo  JText::_('OSM_CREATED_DATE'); ?>
			</label>
			<div class="<?php echo $controlsClass; ?>">
				<?php echo JHtml::_('calendar', $this->item->created_date, 'created_date', 'created_date', '%Y-%m-%d %H:%M:%S') ; ?>
			</div>
		</div>
		<div class="<?php echo $controlGroupClass; ?>">
			<label class="<?php echo $controlLabelClass; ?>">
				<?php echo  JText::_('OSM_SUBSCRIPTION_START_DATE'); ?>
			</label>
			<div class="<?php echo $controlsClass; ?>">
				<?php echo JHtml::_('calendar', $this->item->from_date, 'from_date', 'from_date', '%Y-%m-%d %H:%M:%S') ; ?>
			</div>
		</div>
		<div class="<?php echo $controlGroupClass; ?>">
			<label class="<?php echo $controlLabelClass; ?>">
				<?php echo  JText::_('OSM_SUBSCRIPTION_END_DATE'); ?>
			</label>
			<div class="<?php echo $controlsClass; ?>">
				<?php
				if ($this->item->lifetime_membership || $this->item->to_date == '2099-12-31 23:59:59')
				{
					echo JText::_('OSM_LIFETIME');
				}
				else
				{
					echo JHtml::_('calendar', $this->item->to_date, 'to_date', 'to_date', '%Y-%m-%d %H:%M:%S') ;
				}
				?>
			</div>
		</div>
		<?php
		if ($this->item->setup_fee > 0 || !$this->item->id)
		{
		?>
			<div class="<?php echo $controlGroupClass; ?>">
				<label class="<?php echo $controlLabelClass; ?>">
					<?php echo  JText::_('OSM_SETUP_FEE'); ?>
				</label>
				<div class="<?php echo $controlsClass; ?>">
                    <?php
                        $input = '<input type="text" class="form-control" name="setup_fee" value="' . ($this->item->setup_fee > 0 ? round($this->item->setup_fee, 2) : "") . '" size="7" />';
                        echo $bootstrapHelper->getPrependAddon($input, $this->config->currency_symbol);
                    ?>
				</div>
			</div>
		<?php
		}
		$showDiscount = false;
		$showTax = false;
		$showPaymentProcessingFee = false;
		?>
		<div class="<?php echo $controlGroupClass; ?>">
			<label class="<?php echo $controlLabelClass; ?>">
				<?php echo  JText::_('OSM_NET_AMOUNT'); ?>
			</label>
			<div class="<?php echo $controlsClass; ?>">
                <?php
                    $input = '<input type="text" class="form-control" name="amount" value="' . ($this->item->amount > 0 ? round($this->item->amount, 2) : "") . '" size="7" />';
                    echo $bootstrapHelper->getPrependAddon($input, $this->config->currency_symbol);
                ?>
			</div>
		</div>
		<?php
		if ($this->item->discount_amount > 0 || !$this->item->id)
		{
		    $showDiscount = true;
		?>
			<div class="<?php echo $controlGroupClass; ?>">
				<label class="<?php echo $controlLabelClass; ?>">
					<?php echo  JText::_('OSM_DISCOUNT_AMOUNT'); ?>
				</label>
				<div class="<?php echo $controlsClass; ?>">
					<?php
                        $input = '<input type="text" class="form-control" name="discount_amount" value="' . ($this->item->discount_amount > 0 ? round($this->item->discount_amount, 2) : "") . '" size="7" />';
                        echo $bootstrapHelper->getPrependAddon($input, $this->config->currency_symbol);
					?>
				</div>
			</div>
		<?php
		}

		if ($this->item->tax_amount > 0 || !$this->item->id)
		{
		    $showTax = true;
		?>
			<div class="<?php echo $controlGroupClass; ?>">
				<label class="<?php echo $controlLabelClass; ?>">
					<?php echo  JText::_('OSM_TAX_AMOUNT'); ?>
				</label>
				<div class="<?php echo $controlsClass; ?>">
                    <?php
                        $input = '<input type="text" class="form-control" name="tax_amount" value="' . ($this->item->tax_amount > 0 ? round($this->item->tax_amount, 2) : "") . '" size="7" />';
                        echo $bootstrapHelper->getPrependAddon($input, $this->config->currency_symbol);
                    ?>
				</div>
			</div>
		<?php
		}
		if ($this->item->payment_processing_fee > 0 || !$this->item->id)
		{
		    $showPaymentProcessingFee = true;
		?>
			<div class="<?php echo $controlGroupClass; ?>">
				<label class="<?php echo $controlLabelClass; ?>">
					<?php echo  JText::_('OSM_PAYMENT_FEE'); ?>
				</label>
				<div class="<?php echo $controlsClass; ?>">
					<?php
                        $input = '<input type="text" class="form-control" name="payment_processing_fee" value="' . ($this->item->payment_processing_fee > 0 ? round($this->item->payment_processing_fee, 2) : "") . '" size="7" />';
                        echo $bootstrapHelper->getPrependAddon($input, $this->config->currency_symbol);
					?>
				</div>
			</div>
		<?php
		}

		if ($showDiscount || $showTax || $showPaymentProcessingFee)
        {
        ?>
            <div class="<?php echo $controlGroupClass; ?>">
                <label class="<?php echo $controlLabelClass; ?>">
			        <?php echo  JText::_('OSM_GROSS_AMOUNT'); ?>
                </label>
                <div class="<?php echo $controlsClass; ?>">
			        <?php
			        $input = '<input type="text" class="form-control" name="gross_amount" value="' . ($this->item->gross_amount > 0 ? round($this->item->gross_amount, 2) : "") . '" size="7" />';
			        echo $bootstrapHelper->getPrependAddon($input, $this->config->currency_symbol);
			        ?>
                </div>
            </div>
        <?php
        }
		?>
		<div class="<?php echo $controlGroupClass; ?>">
			<label class="<?php echo $controlLabelClass; ?>">
				<?php echo JText::_('OSM_PAYMENT_METHOD') ?>
			</label>
			<div class="<?php echo $controlsClass; ?>">
				<?php echo $this->lists['payment_method'] ; ?>
			</div>
		</div>
		<div class="<?php echo $controlGroupClass; ?>">
			<label class="<?php echo $controlLabelClass; ?>">
				<?php echo JText::_('OSM_TRANSACTION_ID'); ?>
			</label>
			<div class="<?php echo $controlsClass; ?>">
				<input type="text" class="inputbox" size="50" name="transaction_id" id="transaction_id" value="<?php echo $this->item->transaction_id ; ?>" />
			</div>
		</div>
		<div class="<?php echo $controlGroupClass; ?>">
			<label class="<?php echo $controlLabelClass; ?>">
				<?php echo JText::_('OSM_SUBSCRIPTION_STATUS'); ?>
			</label>
			<div class="<?php echo $controlsClass; ?>">
				<?php echo $this->lists['published'] ; ?>
			</div>
		</div>
		<?php
		if ($this->item->payment_method == "os_creditcard")
		{
			$params = new \Joomla\Registry\Registry($this->item->params);
		?>
			<div class="<?php echo $controlGroupClass; ?>">
				<label class="<?php echo $controlLabelClass; ?>">
					<?php echo JText::_('OSM_FIRST_12_DIGITS_CREDITCARD_NUMBER'); ?>
				</label>
				<div class="<?php echo $controlsClass; ?>">
					<?php echo $params->get('card_number'); ?>
				</div>
			</div>
			<div class="<?php echo $controlGroupClass; ?>">
				<label class="<?php echo $controlLabelClass; ?>">
					<?php echo JText::_('AUTH_CARD_EXPIRY_DATE'); ?>
				</label>
				<div class="<?php echo $controlsClass; ?>">
					<?php echo $params->get('exp_date'); ?>
				</div>
			</div>
			<div class="<?php echo $controlGroupClass; ?>">
				<label class="<?php echo $controlLabelClass; ?>">
					<?php echo JText::_('AUTH_CVV_CODE'); ?>
				</label>
				<div class="<?php echo $controlsClass; ?>">
					<?php echo $params->get('cvv'); ?>
				</div>
			</div>
		<?php
		}
		?>
	</fieldset>
<div class="clr"></div>
<input type="hidden" name="id" value="<?php echo $this->item->id; ?>" />
<input type="hidden" name="task" value="" />
<input type="hidden" name="Itemid" value="<?php echo $this->Itemid; ?>" />
<?php echo JHtml::_( 'form.token' ); ?>
	<script type="text/javascript">
		(function($){
			$(document).ready(function(){
				$('#adminForm').validationEngine('attach', {
					onValidationComplete: function(form, status){
						if (status == true) {
							form.on('submit', function(e) {
								e.preventDefault();
							});
							return true;
						}
						return false;
					}
				});
				<?php
				if ($stateType)
				{
				?>
				buildStateField('state', 'country', '<?php echo $selectedState; ?>');
				<?php
				}
				?>
			});
		})(jQuery);
	</script>
</form>
</div>