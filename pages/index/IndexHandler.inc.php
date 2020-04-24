<?php

/**
 * @file pages/index/IndexHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IndexHandler
 * @ingroup pages_index
 *
 * @brief Handle site index requests.
 */

import('lib.pkp.pages.index.PKPIndexHandler');

class IndexHandler extends PKPIndexHandler {
	//
	// Public handler operations
	//
	/**
	 * If no press is selected, display list of presses.
	 * Otherwise, display the index page for the selected press.
	 * @param $args array
	 * @param $request Request
	 */
	function index($args, $request) {
		$press = $request->getPress();
		$user = $request->getUser();

		if ($user && !$this->getTargetContext($request) && Validation::isSiteAdmin()) {
			// If the user is a site admin and no press exists,
			// send them to press administration to create one.
			return $request->redirect(null, 'admin', 'contexts');
		}

		$this->setupTemplate($request);

		if ($press) {
			// Display the current press home.
			$this->_displayPressIndexPage($press, $request);
		} else {
			// Display the site home.
			$site = $request->getSite();
			$this->_displaySiteIndexPage($site, $request);
		}

	}


	//
	// Private helper methods.
	//
	/**
	 * Display the site index page.
	 * @param $site Site
	 * @param $request Request
	 */
	function _displaySiteIndexPage($site, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$pressDao = DAORegistry::getDAO('PressDAO'); /* @var $pressDao PressDAO */

		if ($site->getRedirect() && ($press = $pressDao->getById($site->getRedirect())) != null) {
			$request->redirect($press->getPath());
		}

		$templateMgr->assign(array(
			'pageTitleTranslated' => $site->getLocalizedTitle(),
			'about' => $site->getLocalizedAbout(),
			'pressesFilesPath' => $request->getBaseUrl() . '/' . Config::getVar('files', 'public_files_dir') . '/presses/',
			'presses' => $pressDao->getAll(true),
			'site' => $site,
		));
		$templateMgr->setCacheability(CACHEABILITY_PUBLIC);
		$templateMgr->display('frontend/pages/indexSite.tpl');
	}

	/**
	 * Display a given press index page.
	 * @param $press Press
	 * @param $request Request
	 */
	function _displayPressIndexPage($press, $request) {
		$templateMgr = TemplateManager::getManager($request);

		// Display New Releases
		if ($press->getSetting('displayNewReleases')) {
			$newReleaseDao = DAORegistry::getDAO('NewReleaseDAO'); /* @var $newReleaseDao NewReleaseDAO */
			$newReleases = $newReleaseDao->getMonographsByAssoc(ASSOC_TYPE_PRESS, $press->getId());
			$templateMgr->assign('newReleases', $newReleases);
		}

		// Assign header and content for home page.
		$templateMgr->assign('additionalHomeContent', $press->getLocalizedSetting('additionalHomeContent'));
		$templateMgr->assign('homepageImage', $press->getLocalizedSetting('homepageImage'));
		$templateMgr->assign('pageTitleTranslated', $press->getLocalizedSetting('name'));

		// Display creative commons logo/licence if enabled.
		$templateMgr->assign('displayCreativeCommons', $press->getSetting('includeCreativeCommons'));

		$this->_setupAnnouncements($press, $templateMgr);

		// Display Featured Books
		if ($press->getSetting('displayFeaturedBooks')) {
			$featureDao = DAORegistry::getDAO('FeatureDAO'); /* @var $featureDao FeatureDAO */
			$featuredMonographIds = $featureDao->getSequencesByAssoc(ASSOC_TYPE_PRESS, $press->getId());
			$featuredMonographs = array();
			if (!empty($featuredMonographIds)) {
				foreach($featuredMonographIds as $submissionId => $value) {
					$featuredMonographs[] = Services::get('submission')->get($submissionId);
				}
			}
			$templateMgr->assign('featuredMonographs', $featuredMonographs);
		}

		// Display In Spotlight
		if ($press->getSetting('displayInSpotlight')) {
			// Include random spotlight items for the press home page.
			$spotlightDao = DAORegistry::getDAO('SpotlightDAO'); /* @var $spotlightDao SpotlightDAO */
			$spotlights = $spotlightDao->getRandomByPressId($press->getId(), MAX_SPOTLIGHTS_VISIBLE);
			$templateMgr->assign('spotlights', $spotlights);
		}

		$templateMgr->display('frontend/pages/index.tpl');
	}
}

