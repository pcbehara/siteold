<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012-2014 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

// no direct access
defined('_JEXEC') or die;
?>
<ul class="osm-renew-options">
	<?php
	$renewOptionCount = 0;
	$fieldSuffix = OSMembershipHelper::getFieldSuffix();
	foreach ($this->planIds as $planId)
	{
		$plan = $this->plans[$planId];
		$taxRate = 0;
		if ($this->config->show_price_including_tax)
		{
			$taxRate = OSMembershipHelper::calculateMaxTaxRate($planId);
		}
		$symbol = $plan->currency_symbol ? $plan->currency_symbol : $plan->currency;
		$renewOptions = isset($this->renewOptions[$planId]) ? $this->renewOptions[$planId] : array();

		if (count($renewOptions))
		{
			foreach ($renewOptions as $renewOption)
			{
				$checked = '';
				if ($renewOptionCount == 0)
				{
					$checked = ' checked="checked" ';
				}

				$renewOptionCount++;
				$renewOptionFrequency = $renewOption->renew_option_length_unit;
				$renewOptionLength = $renewOption->renew_option_length;
				switch ($renewOptionFrequency)
				{
					case 'D':
						$text = $renewOptionLength > 1 ? JText::_('OSM_DAYS') : JText::_('OSM_DAY');
						break ;
					case 'W' :
						$text = $renewOptionLength > 1 ? JText::_('OSM_WEEKS') : JText::_('OSM_WEEK');
						break ;
					case 'M' :
						$text = $renewOptionLength > 1 ? JText::_('OSM_MONTHS') : JText::_('OSM_MONTH');
						break ;
					case 'Y' :
						$text = $renewOptionLength > 1 ? JText::_('OSM_YEARS') : JText::_('OSM_YEAR');
						break ;
				}

				?>
				<li class="osm-renew-option">
					<input type="radio" class="validate[required]" id="renew_option_id_<?php echo $renewOptionCount; ?>" name="renew_option_id" value="<?php echo $planId.'|'.$renewOption->id; ?>" <?php echo $checked; ?> />
					<label for="renew_option_id_<?php echo $renewOptionCount; ?>"><?php JText::printf('OSM_RENEW_OPTION_TEXT', $plan->title, $renewOptionLength.' '. $text, OSMembershipHelper::formatCurrency($renewOption->price * (1 + $taxRate / 100), $this->config, $symbol)); ?></label>
				</li>
				<?php
			}
		}
		else
		{
			$checked = '';
			if ($renewOptionCount == 0)
			{
				$checked = ' checked="checked" ';
			}

			$renewOptionCount++;
			$length = $plan->subscription_length;
			switch ($plan->subscription_length_unit)
			{
				case 'D':
					$text = $length > 1 ? JText::_('OSM_DAYS') : JText::_('OSM_DAY');
					break;
				case 'W' :
					$text = $length > 1 ? JText::_('OSM_WEEKS') : JText::_('OSM_WEEK');
					break;
				case 'M' :
					$text = $length > 1 ? JText::_('OSM_MONTHS') : JText::_('OSM_MONTH');
					break;
				case 'Y' :
					$text = $length > 1 ? JText::_('OSM_YEARS') : JText::_('OSM_YEAR');
					break;
			}
			?>
			<li class="osm-renew-option">
				<input type="radio" class="validate[required]" id="renew_option_id_<?php echo $renewOptionCount; ?>" name="renew_option_id" value="<?php echo $planId;?>" <?php echo $checked; ?>/>
				<label for="renew_option_id_<?php echo $renewOptionCount; ?>"><?php JText::printf('OSM_RENEW_OPTION_TEXT', $plan->title, $length.' '.$text, OSMembershipHelper::formatCurrency($plan->price * (1 + $taxRate / 100), $this->config, $symbol)); ?></label>
			</li>
			<?php
		}
	}
	?>
</ul>
<div class="form-actions">
	<input type="submit" class="<?php echo $this->bootstrapHelper->getClassMapping('btn'); ?> btn-primary" value="<?php echo JText::_('OSM_PROCESS_RENEW'); ?>"/>
</div>