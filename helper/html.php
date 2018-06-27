<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2002 - 2013 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

abstract class OSMembershipHelperHtml
{
	/**
	 * Render showon string
	 *
	 * @param array $fields
	 *
	 * @return string
	 */
	public static function renderShowon($fields)
	{
		$output = array();

		$i = 0;

		foreach ($fields as $name => $values)
		{
			$i++;

			$values = (array) $values;

			$data = array(
				'field'  => $name,
				'values' => $values
			);

			if (version_compare(JVERSION, '3.6.99', 'ge'))
			{
				$data['sign'] = '=';
			}

			$data['op'] = $i > 1 ? 'AND' : '';

			$output[] = json_encode($data);
		}

		return '[' . implode(',', $output) . ']';
	}

	/**
	 * Function to render a common layout which is used in different views
	 *
	 * @param string $layout
	 * @param array  $data
	 *
	 * @return string
	 * @throws RuntimeException
	 */
	public static function loadCommonLayout($layout, $data = array())
	{
		$app       = JFactory::getApplication();
		$themeFile = str_replace('/tmpl', '', $layout);

		// This line was added to keep B/C with template override code, don't remove it
		if (strpos($layout, 'common/') === 0 && strpos($layout, 'common/tmpl') === false)
		{
			$layout = str_replace('common/', 'common/tmpl/', $layout);
		}

		if (JFile::exists($layout))
		{
			$path = $layout;
		}
        elseif (JFile::exists(JPATH_THEMES . '/' . $app->getTemplate() . '/html/com_osmembership/' . $themeFile))
		{
			$path = JPATH_THEMES . '/' . $app->getTemplate() . '/html/com_osmembership/' . $themeFile;
		}
        elseif (JFile::exists(JPATH_ROOT . '/components/com_osmembership/view/' . $layout))
		{
			$path = JPATH_ROOT . '/components/com_osmembership/view/' . $layout;
		}
		else
		{
			throw new RuntimeException(JText::_('The given shared template path is not exist'));
		}

		// Start an output buffer.
		ob_start();
		extract($data);

		// Load the layout.
		include $path;

		// Get the layout contents.
		$output = ob_get_clean();

		return $output;
	}

	public static function getPossibleLayouts($layout)
	{
		$layouts = [$layout];

		$config = OSMembershipHelper::getConfig();

		if (empty($config->twitter_bootstrap_version))
		{
			$twitterBootstrapVersion = 2;
		}
		else
		{
			$twitterBootstrapVersion = $config->twitter_bootstrap_version;
		}

		switch ($twitterBootstrapVersion)
		{
			case 2:
				break;
			case 3;
			case 4:
				array_unshift($layouts, $layout . '.bootstrap' . $twitterBootstrapVersion);
				break;
			default:
				array_unshift($layouts, $layout . '.' . $twitterBootstrapVersion);
				break;
		}

		return $layouts;
	}

	/**
	 * Generate category selection dropdown
	 *
	 * @param int    $selected
	 * @param string $name
	 * @param string $attr
	 *
	 * @return mixed
	 */
	public static function buildCategoryDropdown($selected, $name = "parent_id", $attr = null)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('id, parent_id, title')
			->from('#__osmembership_categories')
			->where('published=1');
		$db->setQuery($query);
		$rows     = $db->loadObjectList();
		$children = array();

		if ($rows)
		{
			// first pass - collect children
			foreach ($rows as $v)
			{
				$pt   = $v->parent_id;
				$list = @$children[$pt] ? $children[$pt] : array();
				array_push($list, $v);
				$children[$pt] = $list;
			}
		}

		$list      = JHtml::_('menu.treerecurse', 0, '', array(), $children, 9999, 0, 0);
		$options   = array();
		$options[] = JHtml::_('select.option', '0', JText::_('OSM_SELECT_CATEGORY'));

		foreach ($list as $item)
		{
			$options[] = JHtml::_('select.option', $item->id, '&nbsp;&nbsp;&nbsp;' . $item->treename);
		}

		return JHtml::_('select.genericlist', $options, $name,
			array(
				'option.text.toHtml' => false,
				'option.text'        => 'text',
				'option.value'       => 'value',
				'list.attr'          => 'class="inputbox" ' . $attr,
				'list.select'        => $selected,));
	}

	/**
	 * Converts a double colon seperated string or 2 separate strings to a string ready for bootstrap tooltips
	 *
	 * @param   string $title     The title of the tooltip (or combined '::' separated string).
	 * @param   string $content   The content to tooltip.
	 * @param   int    $translate If true will pass texts through JText.
	 * @param   int    $escape    If true will pass texts through htmlspecialchars.
	 *
	 * @return  string  The tooltip string
	 *
	 * @since   2.0.7
	 */
	public static function tooltipText($title = '', $content = '', $translate = 1, $escape = 1)
	{
		// Initialise return value.
		$result = '';

		// Don't process empty strings
		if ($content != '' || $title != '')
		{
			// Split title into title and content if the title contains '::' (old Mootools format).
			if ($content == '' && !(strpos($title, '::') === false))
			{
				list($title, $content) = explode('::', $title, 2);
			}

			// Pass texts through JText if required.
			if ($translate)
			{
				$title   = JText::_($title);
				$content = JText::_($content);
			}

			// Use only the content if no title is given.
			if ($title == '')
			{
				$result = $content;
			}
			// Use only the title, if title and text are the same.
            elseif ($title == $content)
			{
				$result = '<strong>' . $title . '</strong>';
			}
			// Use a formatted string combining the title and content.
            elseif ($content != '')
			{
				$result = '<strong>' . $title . '</strong><br />' . $content;
			}
			else
			{
				$result = $title;
			}

			// Escape everything, if required.
			if ($escape)
			{
				$result = htmlspecialchars($result);
			}
		}

		return $result;
	}

	/**
	 * Get label of the field (including tooltip)
	 *
	 * @param        $name
	 * @param        $title
	 * @param string $tooltip
	 * @param bool   $required
	 *
	 * @return string
	 */
	public static function getFieldLabel($name, $title, $tooltip = '', $required = false)
	{
		$label = '';
		$text  = $title;

		// Build the class for the label.
		$class = !empty($tooltip) ? 'hasTooltip hasTip' : '';

		// Add the opening label tag and main attributes attributes.
		$label .= '<label id="' . $name . '-lbl" for="' . $name . '" class="' . $class . '"';

		// If a description is specified, use it to build a tooltip.
		if (!empty($tooltip))
		{
			$label .= ' title="' . self::tooltipText(trim($text, ':'), $tooltip, 0) . '"';
		}

		$label .= '>' . $text . ($required ? '<span class="required">*</span>' : '') . '</label>';

		return $label;
	}

	/**
	 * Get bootstrapped style boolean input
	 *
	 * @param $name
	 * @param $value
	 *
	 * @return string
	 */
	public static function getBooleanInput($name, $value)
	{
		JHtml::_('jquery.framework');
		$field = JFormHelper::loadFieldType('Radio');

		$element = new SimpleXMLElement('<field />');
		$element->addAttribute('name', $name);

		if (version_compare(JVERSION, '4.0.0-dev', 'ge'))
		{
			$element->addAttribute('class', 'switcher');
		}
		else
		{
			$element->addAttribute('class', 'radio btn-group btn-group-yesno');
		}

		$element->addAttribute('default', '0');

		$node = $element->addChild('option', 'JNO');
		$node->addAttribute('value', '0');

		$node = $element->addChild('option', 'JYES');
		$node->addAttribute('value', '1');

		$field->setup($element, (int) $value);

		return $field->input;
	}

	/**
	 * Function to add dropdown menu
	 *
	 * @param string $vName
	 */
	public static function renderSubmenu($vName = 'dashboard')
	{
		?>
        <script language="javascript">
            function confirmBuildTaxRules() {
                if (confirm('This will delete all tax rules you created and build EU tax rules. Are you sure ?')) {
                    location.href = 'index.php?option=com_osmembership&task=tool.build_eu_tax_rules';
                }
            }
        </script>
		<?php
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__osmembership_menus')
			->where('published = 1')
			->where('menu_parent_id = 0')
			->order('ordering');
		$db->setQuery($query);
		$menus = $db->loadObjectList();
		$html  = '';
		$html  .= '<ul id="mp-dropdown-menu" class="nav nav-tabs nav-hover">';

		$currentLink = 'index.php' . JUri::getInstance()->toString(array('query'));

		for ($i = 0; $n = count($menus), $i < $n; $i++)
		{
			$menu = $menus[$i];
			$query->clear();
			$query->select('*')
				->from('#__osmembership_menus')
				->where('published = 1')
				->where('menu_parent_id = ' . intval($menu->id))
				->order('ordering');
			$db->setQuery($query);
			$subMenus = $db->loadObjectList();

			switch ($i)
			{
				case 2:
				case  3:
					$view = 'subscriptions';
					break;
				case 4:
					$view = 'coupons';
					break;
				case 5:
					$view = 'plugins';
					break;
				case 1:
				case 6:
				case 7:
				case 8:
					$view = 'configuration';
					break;
				default:
					$view = '';
					break;
			}

			if ($view && !OSMembershipHelper::canAccessThisView($view))
			{
				continue;
			}

			if (!count($subMenus))
			{
				$class      = '';
				$extraClass = '';

				if ($menu->menu_link == $currentLink)
				{
					$class      = ' class="active"';
					$extraClass = ' active';
				}

				$html .= '<li' . $class . '><a href="' . $menu->menu_link . '" class="nav-link dropdown-item' . $extraClass . '"><span class="icon-' . $menu->menu_class . '"></span> ' . JText::_($menu->menu_name) .
					'</a></li>';
			}
			else
			{
				$class = ' class="dropdown"';

				for ($j = 0; $m = count($subMenus), $j < $m; $j++)
				{
					$subMenu = $subMenus[$j];

					if ($subMenu->menu_link == $currentLink)
					{
						$class = ' class="dropdown active"';
						break;
					}
				}

				$html .= '<li' . $class . '>';
				$html .= '<a id="drop_' . $menu->id . '" href="#" data-toggle="dropdown" role="button" class="dropdown-toggle nav-link dropdown-toggle"><span class="icon-' . $menu->menu_class . '"></span> ' .
					JText::_($menu->menu_name) . ' <b class="caret"></b></a>';
				$html .= '<ul aria-labelledby="drop_' . $menu->id . '" role="menu" class="dropdown-menu" id="menu_' . $menu->id . '">';

				for ($j = 0; $m = count($subMenus), $j < $m; $j++)
				{
					$subMenu    = $subMenus[$j];
					$class      = '';
					$extraClass = '';

					$vars = array();
					parse_str($subMenu->menu_link, $vars);
					$view = isset($vars['view']) ? $vars['view'] : '';

					if ($view && !OSMembershipHelper::canAccessThisView($view))
					{
						continue;
					}

					if ($subMenu->menu_link == $currentLink)
					{
						$class      = ' class="active"';
						$extraClass = ' active';
					}


					$html .= '<li' . $class . '><a class="nav-link dropdown-item' . $extraClass . '" href="' . $subMenu->menu_link .
						'" tabindex="-1"><span class="icon-' . $subMenu->menu_class . '"></span> ' . JText::_($subMenu->menu_name) . '</a></li>';
				}

				$html .= '</ul>';
				$html .= '</li>';
			}
		}

		$html .= '</ul>';

		echo $html;
	}

	/**
	 * Generate article selection box
	 *
	 * @param int    $fieldValue
	 * @param string $fieldName
	 *
	 * @return string
	 */
	public static function getArticleInput($fieldValue, $fieldName = 'article_id')
	{
		JHtml::_('jquery.framework');
		JFormHelper::addFieldPath(JPATH_ADMINISTRATOR . '/components/com_content/models/fields');
		$field = JFormHelper::loadFieldType('Modal_Article');

		$element = new SimpleXMLElement('<field />');
		$element->addAttribute('name', $fieldName);
		$element->addAttribute('select', 'true');
		$element->addAttribute('clear', 'true');

		$field->setup($element, $fieldValue);

		return $field->input;
	}

	/**
	 * Get human readable filesize
	 *
	 * @param string $file
	 * @param int    $precision
	 *
	 * @return string
	 */
	public static function getFormattedFilezize($file, $precision = 2)
	{
		$bytes  = filesize($file);
		$size   = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$factor = floor((strlen($bytes) - 1) / 3);

		return sprintf("%.{$precision}f", $bytes / pow(1024, $factor)) . @$size[$factor];
	}

	/**
	 * Get media input field type
	 *
	 * @param string $value
	 * @param string $fieldName
	 *
	 * @return string
	 */
	public static function getMediaInput($value, $fieldName = 'image')
	{
		JHtml::_('jquery.framework');
		$field = JFormHelper::loadFieldType('Media');

		$element = new SimpleXMLElement('<field />');
		$element->addAttribute('name', $fieldName);
		$element->addAttribute('class', 'readonly input-large');
		$element->addAttribute('preview', 'tooltip');
		$element->addAttribute('directory', 'com_osmembership');

		$form = JForm::getInstance('sample-form', '<form> </form>');
		$field->setForm($form);
		$field->setup($element, $value);

		return $field->input;
	}

	/**
	 * Get BootstrapHelper class for admin UI
	 *
	 * @return OSMembershipHelperBootstrap
	 */
	public static function getAdminBootstrapHelper()
	{
		if (version_compare(JVERSION, '4.0.0-dev', 'ge'))
		{
			return new OSMembershipHelperBootstrap('4');
		}

		return new OSMembershipHelperBootstrap('2');
	}
}
