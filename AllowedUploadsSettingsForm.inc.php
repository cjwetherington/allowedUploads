<?php

/**
 * @file plugins/generic/allowedUploads/AllowedUploadsSettingsForm.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AllowedUploadsSettingsForm
 * @ingroup plugins_generic_allowedUploads
 *
 * @brief Form for managers to modify Allowed Uploads plugin settings
 */

use PKP\form\Form;

class AllowedUploadsSettingsForm extends Form {

	/** @var int */
	var $_contextId;

	/** @var object */
	var $_plugin;

	/**
	 * Constructor
	 * @param $plugin AllowedUploadsPlugin
	 * @param $contextId int
	 */
	function __construct($plugin, $contextId) {
		$this->_contextId = $contextId;
		$this->_plugin = $plugin;

		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));

	}

	/**
	 * Initialize form data.
	 */
	function initData() {
		$this->_data = array(
			'allowedExtensions' => $this->_plugin->getSetting($this->_contextId, 'allowedExtensions'),
			'validateMimeType' => $this->_plugin->getSetting($this->_contextId, 'validateMimeType'),
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('allowedExtensions', 'validateMimeType'));
	}

	/**
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->_plugin->getName());
		return parent::fetch($request, $template, $display);
	}

	/**
	 * Save settings.
	 */
	function execute(...$functionArgs) {
		// Check if MIME validation is being enabled
		$validateMimeType = $this->getData('validateMimeType');
		
		if ($validateMimeType && !$this->testMimeDetection()) {
			// MIME detection isn't working - add an error
			$this->addError('validateMimeType', __('plugins.generic.allowedUploads.settings.mimeValidation.unavailable'));
			return false;
    	}

		$this->_plugin->updateSetting($this->_contextId, 'allowedExtensions', $this->getData('allowedExtensions'), 'string');
		$this->_plugin->updateSetting($this->_contextId, 'validateMimeType', $this->getData('validateMimeType'), 'bool');
		return parent::execute(...$functionArgs);
	}

	/**
	 * Test if MIME type detection is working
	 * @return bool True if MIME detection works
	 */
	private function testMimeDetection() {
		// Test with this PHP file - we know it exists and is readable
		$testFile = __FILE__;

		// Test finfo_open (primary method)
		if (function_exists('finfo_open')) {
			$finfo = @finfo_open(FILEINFO_MIME_TYPE);
			if ($finfo !== false) {
				$mime = @finfo_file($finfo, $testFile);
				@finfo_close($finfo);
				if ($mime !== false) {
					return true;
				}
			}
		}

		// Test fallback method if primary failed
		if (function_exists('mime_content_type')) {
			$mime = @mime_content_type($testFile);
			if ($mime !== false) {
				return true;
			}
		}

		return false;
	}

}

?>
