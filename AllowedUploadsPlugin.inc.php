<?php

/**
 * @file plugins/generic/allowedUploads/AllowedUploadsPlugin.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AllowedUploadsPlugin
 * @ingroup plugins_generic_allowedUploads
 *
 * @brief Allowed Uploads plugin class
 */

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\plugins\GenericPlugin;
use PKP\plugins\PluginRegistry;
use PKP\validation\ValidatorFactory;


class AllowedUploadsPlugin extends GenericPlugin {
	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @return boolean True iff plugin initialized successfully; if false,
	 * 	the plugin will not be registered.
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return true;
		if ($success && $this->getEnabled()) {

			HookRegistry::register('SubmissionFile::validate', array($this, 'checkUploadWizard'));
			HookRegistry::register('submissionfilesuploadform::validate', array($this, 'checkUpload'));
		}
		return $success;
	}

	/**
	 * Get the plugin display name.
	 * @return string
	 */
	function getDisplayName() {
		return __('plugins.generic.allowedUploads.displayName');
	}

	/**
	 * Get the plugin description.
	 * @return string
	 */
	function getDescription() {
		return __('plugins.generic.allowedUploads.description');
	}

	/**
	 * @copydoc Plugin::getActions()
	 */
	function getActions($request, $verb) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			$this->getEnabled()?array(
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			):array(),
			parent::getActions($request, $verb)
		);
	}

 	/**
	 * @copydoc Plugin::manage()
	 */
	function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				$context = $request->getContext();

				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->registerPlugin('function', 'plugin_url', array($this, 'smartyPluginUrl'));

				$this->import('AllowedUploadsSettingsForm');
				$form = new AllowedUploadsSettingsForm($this, $context->getId());

				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						return new JSONMessage(true);
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}

	/**
	 * Get MIME type from file content
     * @param string $filePath Path to the file
     * @return string|false MIME type or false if detection fails
	 */
    private function getMimeTypeFromFile($filePath) {
        if (!file_exists($filePath)) {
            return false;
        }

        // Use finfo if available
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                return $mimeType;
            }
        }

        // Fallback to mime_content_type as failsafe
        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        }

        return false;
    }

    /**
     * Get expected MIME types for file extension
     * @param string $extension File extension
     * @return array Array of acceptable MIME types
     */
    private function getExpectedMimeTypes($extension) {
        $mimeMap = [
			// Document formats
			'doc' => ['application/msword'],
			'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
			'odt' => ['application/vnd.oasis.opendocument.text'],
			'pdf' => ['application/pdf'],
			'rtf' => ['application/rtf', 'text/rtf'],
			'txt' => ['text/plain'],

			// LaTeX and related
			'bib' => ['text/x-bibtex', 'application/x-bibtex'],
			'latex' => ['text/x-tex', 'application/x-latex'],
			'tex' => ['text/x-tex', 'application/x-tex'],

			// Spreadsheets and data
			'csv' => ['text/csv', 'text/plain'],
			'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
			'tsv' => ['text/tab-separated-values', 'text/plain'],
			'xls' => ['application/vnd.ms-excel'],
			'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],

			// Data formats
			'json' => ['application/json'],
			'xml' => ['application/xml', 'text/xml'],
			'yaml' => ['text/yaml', 'application/yaml'],
			'yml' => ['text/yaml', 'application/yaml'],

			// Presentations
			'odp' => ['application/vnd.oasis.opendocument.presentation'],
			'ppt' => ['application/vnd.ms-powerpoint'],
			'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],

			// Images
			'bmp' => ['image/bmp'],
			'eps' => ['application/postscript'],
			'gif' => ['image/gif'],
			'jpg' => ['image/jpeg'],
			'jpeg' => ['image/jpeg'],
			'png' => ['image/png'],
			'svg' => ['image/svg+xml'],
			'tif' => ['image/tiff'],
			'tiff' => ['image/tiff'],

			// Vector graphics
			'ai' => ['application/postscript'],
			'ps' => ['application/postscript'],

			// Archives
			'7z' => ['application/x-7z-compressed'],
			'gz' => ['application/gzip'],
			'tar' => ['application/x-tar'],
			'tar.bz2' => ['application/x-bzip2'],
			'tar.gz' => ['application/gzip'],
			'tar.xz' => ['application/x-xz'],
			'zip' => ['application/zip'],

			// Code and markup
			'htm' => ['text/html'],
			'html' => ['text/html'],
			'ipynb' => ['application/json'],
			'md' => ['text/markdown', 'text/plain'],
			'py' => ['text/x-python', 'text/plain'],
			'r' => ['text/plain'],

			// Statistical formats
			'sav' => ['application/x-spss-sav'],
			'dta' => ['application/x-stata-dta'],
			'sas7bdat' => ['application/x-sas-data'],

			// Audio/Video
			'avi' => ['video/x-msvideo'],
			'mp3' => ['audio/mpeg'],
			'mp4' => ['video/mp4'],
			'wav' => ['audio/wav'],
        ];

        return $mimeMap[strtolower($extension)] ?? [];
    }

    /**
     * Validate file extension and optionally MIME type
     * @param string $fileName Original filename
     * @param string|null $filePath Path to uploaded file
     * @param string $allowedExtensions Semicolon-separated list of allowed extensions
     * @param int $contextId Context ID
     * @return array Array with 'valid' boolean and 'error' message
     */
    private function validateFileType($fileName, $filePath, $allowedExtensions, $contextId) {
		$parts = explode('.', $fileName);
		$allowedExtensionsArray = array_filter(array_map('trim', explode(';', $allowedExtensions)), 'strlen');

        // Check for multiple extensions
        if (count($parts) > 2) {
            // Check if a double extension is explicitly allowed
            $doubleExtension = strtolower($parts[count($parts)-2] . '.' . $parts[count($parts)-1]);

            if (in_array($doubleExtension, $allowedExtensionsArray)) {
                $extension = $doubleExtension; // Use the double extension for MIME type checking
            } else {
                return [
                    'valid' => false,
                    'error' => __('plugins.generic.allowedUploads.error.multiExtension', ['fileName' => $fileName])
                ];
            }
        } else {
            $extension = strtolower(end($parts));
        }

        // Check extension against allowlist
        if (!in_array($extension, $allowedExtensionsArray)) {
            return [
                'valid' => false,
                'error' => __('plugins.generic.allowedUploads.error', ['allowedExtensions' => $allowedExtensions])
            ];
        }

        // Check if MIME type validation is enabled and we have a file to check
        $validateMimeType = $this->getSetting($contextId, 'validateMimeType');
        if (!$validateMimeType || !$filePath || !file_exists($filePath)) {
            return ['valid' => true, 'error' => null];
        }

        // Perform MIME type validation
        $detectedMimeType = $this->getMimeTypeFromFile($filePath);
        if ($detectedMimeType === false) {
            error_log("AllowedUploads: Could not detect MIME type for file " . $fileName);
            return ['valid' => true, 'error' => null];
        }

        $expectedMimeTypes = $this->getExpectedMimeTypes($extension);
        if (!empty($expectedMimeTypes) && !in_array($detectedMimeType, $expectedMimeTypes)) {
            return [
                'valid' => false,
                'error' => __('plugins.generic.allowedUploads.error.mimeType', [
                    'fileName' => $fileName,
                    'detectedType' => $detectedMimeType,
                    'allowedExtensions' => $allowedExtensions
                ])
            ];
        }

        return ['valid' => true, 'error' => null];
    }

	/**
	 * Check the uploaded file in the submission wizard
	 * Hook: SubmissionFile::validate
	 * @param string $hookName Name of hook being called
	 * @param array $params Hook parameters: errors array, submission, props, actions, locale
	 * @return bool Always returns false to allow other hooks to process
	 */
    function checkUploadWizard($hookName, $params) {
        $props = $params[2];
        $locale = $params[4];

        if ($fileName = $props['name'][$locale]){
            $errors =& $params[0];
            $request = Application::get()->getRequest();
            $context = $request->getContext();
            $contextId = $context->getId();

            $allowedExtensions = $this->getSetting($contextId, 'allowedExtensions');

            if ($allowedExtensions){
				// Get the uploaded file path from $_FILES
				$filePath = null;
				if (isset($_FILES['file']) && isset($_FILES['file']['tmp_name'])) {
					$filePath = $_FILES['file']['tmp_name'];
				}

                $validation = $this->validateFileType($fileName, $filePath, $allowedExtensions, $contextId);
                if (!$validation['valid']) {
                    $errors[] = $validation['error'];
                }
            }
        }
		return false;
    }

	/**
	 * Check the uploaded file in the upload form
	 * Hook: submissionfilesuploadform::validate
	 * @param string $hookName Name of hook being called
	 * @param array $params Hook parameters: form object
	 * @return bool Always returns fale to allow other hooks to process
	 */
    function checkUpload($hookName, $params) {
        $form = $params[0];
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $contextId = $context->getId();
        $userVars = $request->getUserVars();
        $fileName = $userVars['name'] ?? null;
		if (!$fileName) {
			return false;
		}

        $allowedExtensions = $this->getSetting($contextId, 'allowedExtensions');

        if ($allowedExtensions){
			// Get the uploaded file path from $_FILES
			$filePath = null;
			if (isset($_FILES['file']) && isset($_FILES['file']['tmp_name'])) {
				$filePath = $_FILES['file']['tmp_name'];
			}

            $validation = $this->validateFileType($fileName, $filePath, $allowedExtensions, $contextId);
            if (!$validation['valid']) {
                $form->addError('allowedFileType', $validation['error']);
            }
        }
        return false;
    }

}
?>
