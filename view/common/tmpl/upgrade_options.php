<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
// no direct access
defined('_JEXEC') or die;
?>
<ul class="osm-upgrade-options">
	<?php
	$upgradeOptionCount = 0;
	foreach ($this->upgradeRules as $rule)
	{
		$checked = '';
		if ($upgradeOptionCount == 0)
		{
			$checked = ' checked="checked" ';
		}

		$upgradeOptionCount++;
		$upgradeToPlan = $this->plans[$rule->to_plan_id];
		$symbol = $upgradeToPlan->currency_symbol ? $upgradeToPlan->currency_symbol : $upgradeToPlan->currency;

		$taxRate = 0;
		if ($this->config->show_price_including_tax)
		{
			$taxRate = OSMembershipHelper::calculateMaxTaxRate($rule->to_plan_id);
		}
		?>
		<li class="osm-upgrade-option">
			<input type="radio" class="validate[required]" id="upgrade_option_id_<?php echo $upgradeOptionCount; ?>" name="upgrade_option_id" value="<?php echo $rule->id; ?>"<?php echo $checked; ?> />
			<label for="upgrade_option_id_<?php echo $upgradeOptionCount; ?>"><?php JText::printf('OSM_UPGRADE_OPTION_TEXT', $this->plans[$rule->from_plan_id]->title, $upgradeToPlan->title, OSMembershipHelper::formatCurrency($rule->price * (1 + $taxRate / 100), $this->config, $symbol)); ?></label>
		</li>
		<?php
	}
	?>
</ul>
