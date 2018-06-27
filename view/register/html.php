<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2018 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

class OSMembershipViewRegisterHtml extends MPFViewHtml
{
	public function display()
	{
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$config = OSMembershipHelper::getConfig();
		$input  = $this->input;
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);

		// Add necessary javascript files
		OSMembershipHelper::addLangLinkForAjax();
		$document = JFactory::getDocument();
		$rootUri  = JUri::root(true);
		$document->addScript($rootUri . '/media/com_osmembership/assets/js/paymentmethods.min.js');

		$customJSFile = JPATH_ROOT . '/media/com_osmembership/assets/js/custom.js';

		if (file_exists($customJSFile) && filesize($customJSFile) > 0)
		{
			$document->addScript($rootUri . '/media/com_osmembership/assets/js/custom.js');
		}

		$renewOptionId   = $input->getInt('renew_option_id', 0);
		$upgradeOptionId = $input->getInt('upgrade_option_id', 0);
		$planId          = $input->getInt('id', 0);
		$userId          = $user->get('id');
		$fieldSuffix     = OSMembershipHelper::getFieldSuffix();

		$plan = OSMembershipHelperDatabase::getPlan($planId);

		if (!$this->checkSubscriptionParameters($plan, $config))
		{
			return;
		}

		// Disable free trial for recurring plan if subscribed before
		OSMembershipHelperSubscription::disableFreeTrialForPlan($plan);

		if ($renewOptionId)
		{
			$action = 'renew';
		}
		elseif ($upgradeOptionId)
		{
			$action = 'upgrade';
		}
		else
		{
			$action = 'subscribe';

			// Check exclusive plans requirement
			$exclusivePlanIds  = OSMembershipHelperSubscription::getExclusivePlanIds();
			$subscribedPlanIds = OSMembershipHelperSubscription::getSubscribedPlans();

			if (in_array($plan->id, $exclusivePlanIds) && !in_array($plan->id, $subscribedPlanIds))
			{
				if ($config->exclusive_plans == 1)
				{
					$msg = JText::_('OSM_EXCLUSIVE_PLAN_SYSTEM');
				}
				else
				{
					$msg = JText::_('OSM_EXCLUSIVE_PLAN_CATEGORY');
				}

				$app->enqueueMessage($msg, 'warning');
				$app->redirect(JUri::root());
			}

			// Check to see whether the user signed up for this plan before or not, if he signed up before, we treat this as renewal
			if ($userId)
			{
				$query->clear()
					->select('COUNT(*)')
					->from('#__osmembership_subscribers')
					->where('user_id = ' . $userId)
					->where('plan_id = ' . $plan->id)
					->where('published IN (1, 2)');
				$db->setQuery($query);

				$total = (int) $db->loadResult();

				if ($total)
				{
					$query->clear()
						->select('id')
						->from('#__osmembership_renewrates')
						->where('plan_id = ' . $plan->id);
					$db->setQuery($query);
					$renewOptions = $db->loadObjectList();

					$renewMembershipMenuId = OSMembershipHelperRoute::findView('renewmembership', 0);
					$profileMenuId         = OSMembershipHelperRoute::findView('profile', 0);

					if (count($renewOptions) > 1)
					{
						$app->enqueueMessage(JText::_('OSM_CHOOSE_RENEW_OPTION'));

						if ($renewMembershipMenuId)
						{
							$app->redirect(JRoute::_('index.php?Itemid=' . $renewMembershipMenuId));
						}
						elseif ($profileMenuId)
						{
							$app->redirect(JRoute::_('index.php?Itemid=' . $profileMenuId));
						}
						else
						{
							$app->redirect(JRoute::_('index.php?option=com_osmembership&view=renewmembership&Itemid=' . $this->Itemid));
						}
					}
					else
					{
						$action = 'renew';
						// If there is only one renew option, assume that users will renew of that option

						if (count($renewOptions) == 1)
						{
							$data['renew_option_id'] = $renewOptions[0]->id;
						}
						else
						{
							$data['renew_option_id'] = OSM_DEFAULT_RENEW_OPTION_ID;
						}

						$renewOptionId = $data['renew_option_id'];
					}
				}
			}
		}

		$defaultPaymentMethod = os_payments::getDefautPaymentMethod($plan->payment_methods, $plan->recurring_subscription);
		$paymentMethod        = $input->post->get('payment_method', $defaultPaymentMethod, 'cmd');

		if (!$paymentMethod)
		{
			$paymentMethod = $defaultPaymentMethod;
		}

		$methods = os_payments::getPaymentMethods($plan->recurring_subscription, $plan->payment_methods);

		if (count($methods) == 0)
		{
			$app->enqueueMessage(JText::_('OSM_NEED_TO_PUBLISH_PLUGIN'));
			$app->redirect(JUri::root());
		}

		$rowFields = OSMembershipHelper::getProfileFields($planId, true, null, $action);
		$data      = $this->getFormData($input, $user, $planId, $rowFields, $config);
		$form      = new MPFForm($rowFields);
		$form->setData($data)->bindData(true);
		$form->prepareFormFields('calculateSubscriptionFee();');

		$data['renew_option_id']   = $renewOptionId;
		$data['upgrade_option_id'] = $upgradeOptionId;
		$data['act']               = $action;

		if (is_callable('OSMembershipHelperOverrideHelper::calculateSubscriptionFee'))
		{
			$fees = OSMembershipHelperOverrideHelper::calculateSubscriptionFee($plan, $form, $data, $config, $paymentMethod);
		}
		else
		{
			$fees = OSMembershipHelper::calculateSubscriptionFee($plan, $form, $data, $config, $paymentMethod);
		}

		if ($plan->recurring_subscription)
		{
			$amount = $fees['regular_gross_amount'];
		}
		else
		{
			$amount = $fees['amount'];
		}

		$this->getFormMessage($plan, $action, $renewOptionId, $upgradeOptionId, $amount, $config, $fieldSuffix);

		$this->loadCaptcha($config, $user);

		// Set document meta data

		$active = JFactory::getApplication()->getMenu()->getActive();

		if ($active)
		{
			$params = OSMembershipHelper::getViewParams($active, array('register'));
			$this->setDocumentMetadata($params);
		}

		// Check to see whether we need to show coupon on subscription form or not
		if ($config->enable_coupon)
		{
			$nullDate = $db->quote($db->getNullDate());
			$query->clear()
				->select('COUNT(*)')
				->from('#__osmembership_coupons')
				->where('published = 1')
				->where($db->quoteName('access') . ' IN (' . implode(',', $user->getAuthorisedViewLevels()) . ')')
				->where('(valid_from = ' . $nullDate . ' OR DATE(valid_from) <= CURDATE())')
				->where('(valid_to = ' . $nullDate . ' OR DATE(valid_to) >= CURDATE())')
				->where('(times = 0 OR times > used)')
				->where('(plan_id = 0 OR id IN (SELECT coupon_id FROM #__osmembership_coupon_plans WHERE plan_id = ' . $plan->id . '))');
			$db->setQuery($query);
			$total = (int) $db->loadResult();

			if (!$total)
			{
				// No coupon for this plan, so we just disable coupon
				$config->enable_coupon = 0;
			}
		}

		if ($userId)
		{
			$query->clear()
				->select('avatar')
				->from('#__osmembership_subscribers')
				->where('user_id = ' . $userId);
			$db->setQuery($query);
			$avatar = $db->loadResult();
		}
		else
		{
			$avatar = '';
		}

		// Payment Methods parameters
		$lists['exp_month'] = JHtml::_('select.integerlist', 1, 12, 1, 'exp_month', ' id="exp_month" class="input-small" ', $input->get('exp_month', date('m'), 'none'), '%02d');
		$currentYear        = date('Y');
		$lists['exp_year']  = JHtml::_('select.integerlist', $currentYear, $currentYear + 10, 1, 'exp_year', ' id="exp_year" class="input-small" ', $input->get('exp_year', date('Y'), 'none'));

		// Check to see if there is payment processing fee or not
		$showPaymentFee = false;
		$useIcon        = false;

		foreach ($methods as $method)
		{
			if ($method->paymentFee)
			{
				$showPaymentFee = true;
			}

			if ($method->iconUri)
			{
				$useIcon = true;
			}
		}

		$this->showPaymentFee = $showPaymentFee;

		// Assign variables to template
		$this->userId            = $userId;
		$this->paymentMethod     = $paymentMethod;
		$this->lists             = $lists;
		$this->config            = $config;
		$this->plan              = $plan;
		$this->methods           = $methods;
		$this->action            = $action;
		$this->renewOptionId     = $renewOptionId;
		$this->upgradeOptionId   = $upgradeOptionId;
		$this->form              = $form;
		$this->fees              = $fees;
		$this->countryBaseTax    = (int) OSMembershipHelper::isCountryBaseTax();
		$this->taxRate           = OSMembershipHelper::calculateMaxTaxRate($planId, '', '', 2, false);
		$this->taxStateCountries = OSMembershipHelper::getTaxStateCountries();
		$this->countryCode       = OSMembershipHelper::getCountryCode($data['country']);
		$this->bootstrapHelper   = new OSMembershipHelperBootstrap($config->twitter_bootstrap_version);
		$this->avatar            = $avatar;

		$this->useIconForPaymentMethods = $useIcon;

		$this->setLayout('default');

		parent::display();
	}

	/**
	 * Make sure user is allowed to access to this subscription form
	 *
	 * @param $plan
	 * @param $config
	 *
	 * @return bool
	 */
	protected function checkSubscriptionParameters($plan, $config)
	{
		// Check to see whether this is a valid form or not
		if (!$plan->id)
		{
			$this->displayOrRedirect(JText::_('OSM_INVALID_MEMBERSHIP_PLAN'));

			return false;
		}

		if (!$plan || $plan->published == 0)
		{
			$this->displayOrRedirect(JText::_('OSM_CANNOT_ACCESS_UNPUBLISHED_PLAN'));

			return false;
		}

		if (!in_array($plan->access, JFactory::getUser()->getAuthorisedViewLevels()))
		{
			$this->displayOrRedirect(JText::_('OSM_NOT_ALLOWED_PLAN'));

			return false;
		}

		// Check if user can subscribe to the plan
		if (!OSMembershipHelper::canSubscribe($plan))
		{
			$loginRedirectUrl = OSMembershipHelper::getLoginRedirectUrl();

			if ($loginRedirectUrl)
			{
				$this->displayOrRedirect('', JRoute::_($loginRedirectUrl));

				return false;
			}
			elseif ($config->number_days_before_renewal)
			{
				// Redirect to membership profile page
				$profileItemId = OSMembershipHelperRoute::findView('profile', $this->Itemid);
				$redirectUrl   = JRoute::_('index.php?option=com_osmembership&view=profile&Itemid=' . $profileItemId);
				$this->displayOrRedirect(JText::sprintf('OSM_COULD_NOT_RENEWAL', $config->number_days_before_renewal), $redirectUrl);

				return false;
			}
			else
			{
				$this->displayOrRedirect(JText::_('OSM_YOU_ARE_NOT_ALLOWED_TO_SIGNUP'), false);

				return false;
			}
		}

		// All conditions passed, return true
		return true;
	}

	/**
	 * Get data using for subscription form
	 *
	 * @param MPFInput $input
	 * @param JUser    $user
	 * @param int      $planId
	 * @param array    $rowFields
	 * @param stdClass $config
	 *
	 * @return array
	 */
	protected function getFormData($input, $user, $planId, $rowFields, $config)
	{
		$userId = $user->id;

		if ($input->getInt('validation_error', 0))
		{
			$data = $input->getData();
		}
		else
		{
			$data = array();

			if ($userId)
			{
				// Check to see if this user has profile data already
				$db    = JFactory::getDbo();
				$query = $db->getQuery(true);
				$query->select('*')
					->from('#__osmembership_subscribers')
					->where('user_id=' . $userId . ' AND is_profile=1');
				$db->setQuery($query);
				$rowProfile = $db->loadObject();

				if ($rowProfile)
				{
					$data = OSMembershipHelper::getProfileData($rowProfile, $planId, $rowFields);
				}
				else
				{
					$mappings = array();

					foreach ($rowFields as $rowField)
					{
						if ($rowField->field_mapping)
						{
							$mappings[$rowField->name] = $rowField->field_mapping;
						}
					}

					JPluginHelper::importPlugin('osmembership');
					$results = JFactory::getApplication()->triggerEvent('onGetProfileData', array($userId, $mappings));

					if (count($results))
					{
						foreach ($results as $res)
						{
							if (is_array($res) && count($res))
							{
								$data = $res;
								break;
							}
						}
					}
				}

				if (!count($data) && JPluginHelper::isEnabled('user', 'profile'))
				{
					$synchronizer = new MPFSynchronizerJoomla();
					$mappings     = array();

					foreach ($rowFields as $rowField)
					{
						if ($rowField->profile_field_mapping)
						{
							$mappings[$rowField->name] = $rowField->profile_field_mapping;
						}
					}

					$data = $synchronizer->getData($userId, $mappings);
				}
			}
			else
			{
				$data = $input->getData();
			}
		}

		if ($userId && !isset($data['first_name']))
		{
			// Load the name from Joomla default name
			$name = $user->name;

			if ($name)
			{
				$pos = strpos($name, ' ');

				if ($pos !== false)
				{
					$data['first_name'] = substr($name, 0, $pos);
					$data['last_name']  = substr($name, $pos + 1);
				}
				else
				{
					$data['first_name'] = $name;
					$data['last_name']  = '';
				}
			}
		}

		if ($userId && !isset($data['email']))
		{
			$data['email'] = $user->email;
		}

		if (!isset($data['country']) || !$data['country'])
		{
			$data['country'] = $config->default_country;
		}

		$data += $input->get->getData();

		return $data;
	}

	/**
	 * Get subscription form message
	 *
	 * @param $plan
	 * @param $action
	 * @param $renewOptionId
	 * @param $upgradeOptionId
	 * @param $amount
	 * @param $config
	 * @param $fieldSuffix
	 *
	 * @return string
	 */
	protected function getFormMessage($plan, $action, $renewOptionId, $upgradeOptionId, $amount, $config, $fieldSuffix)
	{
		$db      = JFactory::getDbo();
		$query   = $db->getQuery(true);
		$message = OSMembershipHelper::getMessages();

		if ($plan->currency_symbol)
		{
			$symbol = $plan->currency_symbol;
		}
		elseif ($plan->currency)
		{
			$symbol = $plan->currency;
		}
		else
		{
			$symbol = $config->currency_symbol;
		}

		if ($action == 'renew')
		{
			if (strlen(strip_tags($message->{'subscription_renew_form_msg' . $fieldSuffix})))
			{
				$formMessage = $message->{'subscription_renew_form_msg' . $fieldSuffix};
			}
			else
			{
				$formMessage = $message->subscription_renew_form_msg;
			}

			if ($renewOptionId == OSM_DEFAULT_RENEW_OPTION_ID)
			{
				$renewOptionFrequency = $plan->subscription_length_unit;
				$renewOptionLength    = $plan->subscription_length;
			}
			else
			{
				$query->select('*')
					->from('#__osmembership_renewrates')
					->where('id = ' . $renewOptionId);
				$db->setQuery($query);
				$renewOption          = $db->loadObject();
				$renewOptionFrequency = $renewOption->renew_option_length_unit;
				$renewOptionLength    = $renewOption->renew_option_length;
			}

			switch ($renewOptionFrequency)
			{
				case 'D':
					$text = $renewOptionLength > 1 ? JText::_('OSM_DAYS') : JText::_('OSM_DAY');
					break;
				case 'W' :
					$text = $renewOptionLength > 1 ? JText::_('OSM_WEEKS') : JText::_('OSM_WEEK');
					break;
				case 'M' :
					$text = $renewOptionLength > 1 ? JText::_('OSM_MONTHS') : JText::_('OSM_MONTH');
					break;
				case 'Y' :
					$text = $renewOptionLength > 1 ? JText::_('OSM_YEARS') : JText::_('OSM_YEAR');
					break;
			}

			$formMessage = str_replace('[NUMBER_DAYS] days', $renewOptionLength . ' ' . $text, $formMessage);
			$formMessage = str_replace('[RENEW_OPTION]', $renewOptionLength . ' ' . $text, $formMessage);
			$formMessage = str_replace('[PLAN_TITLE]', $plan->title, $formMessage);
			$formMessage = str_replace('[AMOUNT]', OSMembershipHelper::formatCurrency($amount, $config, $symbol), $formMessage);
		}
		elseif ($action == 'upgrade')
		{
			if (strlen(strip_tags($message->{'subscription_upgrade_form_msg' . $fieldSuffix})))
			{
				$formMessage = $message->{'subscription_upgrade_form_msg' . $fieldSuffix};
			}
			else
			{
				$formMessage = $message->subscription_upgrade_form_msg;
			}

			$query->select('b.title')
				->from('#__osmembership_upgraderules AS a')
				->innerJoin('#__osmembership_plans AS b ON a.from_plan_id=b.id')
				->where('a.id=' . $upgradeOptionId);
			$db->setQuery($query);
			$fromPlan    = $db->loadResult();
			$formMessage = str_replace('[PLAN_TITLE]', $plan->title, $formMessage);
			$formMessage = str_replace('[AMOUNT]', OSMembershipHelper::formatCurrency($amount, $config, $symbol), $formMessage);
			$formMessage = str_replace('[FROM_PLAN_TITLE]', $fromPlan, $formMessage);
		}
		else
		{
			if (strlen(strip_tags($plan->{'subscription_form_message' . $fieldSuffix})) || strlen(strip_tags($plan->subscription_form_message)))
			{
				if (strlen(strip_tags($plan->{'subscription_form_message' . $fieldSuffix})))
				{
					$formMessage = $plan->{'subscription_form_message' . $fieldSuffix};
				}
				else
				{
					$formMessage = $plan->subscription_form_message;
				}

			}
			else
			{
				if (strlen(strip_tags($message->{'subscription_form_msg' . $fieldSuffix})))
				{
					$formMessage = $message->{'subscription_form_msg' . $fieldSuffix};
				}
				else
				{
					$formMessage = $message->subscription_form_msg;
				}
			}

			$formMessage = str_replace('[PLAN_TITLE]', $plan->title, $formMessage);
			$formMessage = str_replace('[AMOUNT]', OSMembershipHelper::formatCurrency($amount, $config, $symbol), $formMessage);
		}

		$this->message        = $formMessage;
		$this->currencySymbol = $symbol;

		return $formMessage;
	}

	/**
	 *  Load captcha for subscription form
	 *
	 * @param $config
	 * @param $user
	 */
	protected function loadCaptcha($config, $user)
	{
		$showCaptcha = 0;

		if ($config->enable_captcha == 1 || ($config->enable_captcha == 2 && !$user->id))
		{
			$captchaPlugin = JFactory::getApplication()->getParams()->get('captcha', JFactory::getConfig()->get('captcha'));

			if (!$captchaPlugin)
			{
				// Hardcode to recaptcha, reduce support request
				$captchaPlugin = 'recaptcha';
			}

			$plugin = JPluginHelper::getPlugin('captcha', $captchaPlugin);

			if ($plugin)
			{
				$showCaptcha   = 1;
				$this->captcha = JCaptcha::getInstance($captchaPlugin)->display('dynamic_recaptcha_1', 'dynamic_recaptcha_1', 'required');
			}
			else
			{
				JFactory::getApplication()->enqueueMessage(JText::_('OSM_CAPTCHA_NOT_ACTIVATED_IN_YOUR_SITE'), 'error');
			}
		}

		$this->showCaptcha = $showCaptcha;
	}

	/**
	 * Method to display a message or perform redirection, depends on whether this is a HMVC call or not
	 *
	 * @param string $message
	 * @param string $url
	 * @param string $messageType
	 */
	protected function displayOrRedirect($message = '', $url = '', $messageType = 'message')
	{
		if ($this->input->get('hmvc_call'))
		{
			echo $message;
		}
		else
		{
			if (!$url)
			{
				$url = JUri::root();
			}

			$app = JFactory::getApplication();

			if ($message)
			{
				$app->enqueueMessage($message, $messageType);
			}

			$app->redirect($url);
		}
	}
}
