<?php
/**
 * @package        Joomla
 * @subpackage     OSMembership
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;
?>
<div id="osm-my-articles" class="osm-container row-fluid">
    <h1 class="osm-page-title"><?php echo JText::_('OSM_MY_ARTICLES') ; ?></h1>
    <?php
        if (!empty($this->items))
        {
            require_once JPATH_ROOT . '/components/com_content/helpers/route.php';
        ?>
            <table class="adminlist table table-striped" id="adminForm">
                <thead>
                <tr>
                    <th class="title"><?php echo JText::_('OSM_TITLE'); ?></th>
                    <th class="title"><?php echo JText::_('OSM_CATEGORY'); ?></th>
                    <th class="center"><?php echo JText::_('OSM_HITS'); ?></th>
                </tr>
                </thead>
                <?php
                if ($this->pagination->total > $this->pagination->limit)
                {
                ?>
                <tfoot>
                    <tr>
                        <td colspan="3">
                            <div class="pagination"><?php echo $this->pagination->getPagesLinks(); ?></div>
                        </td>
                    </tr>
                </tfoot>
                <?php
                }
                ?>
                <tbody>
                <?php
                    foreach ($this->items as $item)
                    {
                        $articleLink = JRoute::_(ContentHelperRoute::getArticleRoute($item->id, $item->catid));
                    ?>
                        <tr>
                            <td><a href="<?php echo $articleLink ?>"><?php echo $item->title; ?></a></td>
                            <td><?php echo $item->category_title; ?></td>
                            <td class="center">
                                <?php echo $item->hits; ?>
                            </td>
                        </tr>
                    <?php
                    }
                ?>
                </tbody>
            </table>
        <?php
        }
    ?>
</div>