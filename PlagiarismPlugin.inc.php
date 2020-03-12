<?php

/**
 * @file plugins/generic/plagiarism/PlagiarismPlugin.inc.php
 *
 * Copyright (c) 2003-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Plagiarism plugin
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class PlagiarismPlugin extends GenericPlugin {
	/**
	 * @copydoc Plugin::register()
	 */
	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		$this->addLocaleData();

		if ($success && Config::getVar('ithenticate', 'ithenticate') && $this->getEnabled()) {
			HookRegistry::register('submissionsubmitstep4form::display', array($this, 'callback'));
		}
		return $success;
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	public function getDisplayName() {
		return __('plugins.generic.plagiarism.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	public function getDescription() {
		return Config::getVar('ithenticate', 'ithenticate')?__('plugins.generic.plagiarism.description'):__('plugins.generic.plagiarism.description.seeReadme');
	}

	/**
	 * @copydoc LazyLoadPlugin::getCanEnable()
	 */
	function getCanEnable() {
		if (!parent::getCanEnable()) return false;
		return Config::getVar('ithenticate', 'ithenticate');
	}

	/**
	 * @copydoc LazyLoadPlugin::getEnabled()
	 */
	function getEnabled($contextId = null) {
		if (!parent::getEnabled($contextId)) return false;
		return Config::getVar('ithenticate', 'ithenticate');
	}
	
	/**
	 * @copydoc Plugin::getAuth()
	 */
	function getAuth() {
		$auth = array(username => '', password => '');
		$source = Config::getVar('ithenticate', 'ithenticate_backend');
		
		if($source == "redis") {
			try {
				$redis = new Redis();
				// Connecting to Redis
				$redis->connect('redis','6379');
				//$redis->auth('redis_password');
				$auth['username'] = $redis->get("ithenticate_username");
				$auth['password'] = $redis->get("ithenticate_password");
			} catch (Exception $e) {
				error_log($e->getMessage());
			}
		} else {
			$auth['username'] = Config::getVar('ithenticate', 'username');
			$auth['password'] = Config::getVar('ithenticate', 'password');
		}

		return $auth;
	}

	/**
	 * Send submission files to iThenticate.
	 * @param $hookName string
	 * @param $args array
	 */
	public function callback($hookName, $args) {
		$request = Application::getRequest();
		$context = $request->getContext();
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($request->getUserVar('submissionId'));

		require_once(dirname(__FILE__) . '/vendor/autoload.php');
		
		// Get ithenticate username and password
		$auth = $this->getAuth();

		$ithenticate = new \bsobbe\ithenticate\Ithenticate(
			$auth['username'],
			$auth['password']
		);

		// Make sure there's a group list for this context, creating if necessary.
		$groupList = $ithenticate->fetchGroupList();
		$contextName = $context->getLocalizedName($context->getPrimaryLocale());
		if (!($groupId = array_search($contextName, $groupList))) {
			// No folder group found for the context; create one.
			$groupId = $ithenticate->createGroup($contextName);
                        if (!$groupId) {
				error_log('Could not create folder group for context ' . $contextName . ' on iThenticate.');
				return false;
			}
		}

		// Create a folder for this submission.
		if (!($folderId = $ithenticate->createFolder(
			'Submission_' . $submission->getId(),
			'Submission_' . $submission->getId() . ': ' . $submission->getLocalizedTitle($submission->getLocale()),
			$groupId,
			1
		))) {
			error_log('Could not create folder for submission ID ' . $submission->getId() . ' on iThenticate.');
			return false;
		}

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$submissionFiles = $submissionFileDao->getBySubmissionId($submission->getId());
		$authors = $submission->getAuthors();
		$author = array_shift($authors);
		foreach ($submissionFiles as $submissionFile) {
			if (!$ithenticate->submitDocument(
				$submissionFile->getLocalizedName(),
				$author->getLocalizedGivenName(),
				$author->getLocalizedFamilyName(),
				$submissionFile->getOriginalFileName(),
				file_get_contents($submissionFile->getFilePath()),
				$folderId
			)) {
				error_log('Could not submit ' . $submissionFile->getFilePath() . ' to iThenticate.');
			}
		}

		return false;
	}
}
