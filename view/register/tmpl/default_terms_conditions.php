<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die ;

$articleId =  $this->plan->terms_and_conditions_article_id > 0 ? $this->plan->terms_and_conditions_article_id : $this->config->article_id;

if ($articleId > 0)
{
	if (JLanguageMultilang::isEnabled())
	{
		$associations = JLanguageAssociations::getAssociations('com_content', '#__content', 'com_content.item', $articleId);
		$langCode     = JFactory::getLanguage()->getTag();

		if (isset($associations[$langCode]))
		{
			$article = $associations[$langCode];
		}
	}

	if (!isset($article))
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('id, catid')
			->from('#__content')
			->where('id = ' . (int) $articleId);
		$db->setQuery($query);
		$article = $db->loadObject();
	}

	JLoader::register('ContentHelperRoute', JPATH_ROOT.'/components/com_content/helpers/route.php');
	?>
    <div class="<?php echo $controlGroupClass ?> osm-terms-and-conditins-container">
        <input type="checkbox" id="osm-accept-terms-conditions" name="accept_term" value="1" class="validate[required] osm_inputbox inputbox" />
        <strong><?php echo JText::_('OSM_ACCEPT'); ?>&nbsp;<a href="<?php echo JRoute::_(ContentHelperRoute::getArticleRoute($article->id, $article->catid).'&tmpl=component&format=html'); ?>" class="osm-modal" rel="{handler: 'iframe', size: {x: 700, y: 500}}"><?php echo JText::_('OSM_TERM_AND_CONDITION'); ?></a></strong>
    </div>
	<?php
}