<?php

 /**
  * version.xml
  *
  * Copyright (c) 2020 Dimitris Sioulas
  * Copyright (c) 2020 National Documentation Center
  * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
  *
  * Plugin version information.
  */

import('lib.pkp.classes.plugins.GenericPlugin');

class EpubJsViewerPlugin extends GenericPlugin {
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		if (parent::register($category, $path, $mainContextId)) {
			if ($this->getEnabled($mainContextId)) {
				HookRegistry::register('ArticleHandler::view::galley', array($this, 'submissionCallback'), HOOK_SEQUENCE_LAST);
				HookRegistry::register('IssueHandler::view::galley', array($this, 'issueCallback'), HOOK_SEQUENCE_LAST);
				AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON);
			}
			return true;
		}
		return false;
	}

	/**
	 * Install default settings on context creation.
	 * @return string
	 */
	function getContextSpecificPluginSettingsFile() {
		return $this->getPluginPath() . '/settings.xml';
	}

	/**
	 * @copydoc Plugin::getDisplayName
	 */
	function getDisplayName() {
		return __('plugins.generic.epubJsViewer.name');
	}

	/**
	 * @copydoc Plugin::getDescription
	 */
	function getDescription() {
		return __('plugins.generic.epubJsViewer.description');
	}

	function displaySubmissionFile($publishedMonograph, $publicationFormat, $submissionFile) {

        $request = $this->getRequest();
        $context = $request->getContext();
        $router = $request->getRouter();
        $dispatcher = $request->getDispatcher();
        $templateMgr = TemplateManager::getManager($request);

        $pubId = null;
        $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true);
        foreach ($pubIdPlugins as $pubIdPlugin) {
            if ($pubIdPlugin->getPubIdType() == 'doi'){
                $pubId = $publishedMonograph->getStoredPubId($pubIdPlugin->getPubIdType());
            }
        }


        $templateMgr->assign(array(
            'pluginTemplatePath' => $this->getTemplatePath(),
            'pluginUrl' => $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath(),
            'downloadUrl' => $dispatcher->url($request, ROUTE_PAGE, null, null, 'download', array($publishedMonograph->getId(), $submissionFile->getAssocId(), $submissionFile->getFileIdAndRevision()), array('inline' => true)),
            'annotationsEnabled' => $this->getSetting($context->getId(),'annotationsEnabled'),
            'doi' => $pubId
        ));


        return parent::displaySubmissionFile($publishedMonograph, $publicationFormat, $submissionFile);
    }

	/**
	 * Callback that renders the submission galley.
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	function submissionCallback($hookName, $args) {
		$request =& $args[0];
		$application = Application::get();

		$issue =& $args[1];
		$galley =& $args[2];
		$submission =& $args[3];
		$submissionNoun = 'article';

		if ($galley && $galley->getFileType() == 'application/epub+zip') {
			$galleyPublication = null;
			foreach ($submission->getData('publications') as $publication) {
				if ($publication->getId() === $galley->getData('publicationId')) {
					$galleyPublication = $publication;
					break;
				}
			}
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign(array(
				'displayTemplateResource' => $this->getTemplateResource('display.tpl'),
				'pluginUrl' => $request->getBaseUrl() . '/' . $this->getPluginPath(),
				'galleyFile' => $galley->getFile(),
				'issue' => $issue,
				'submission' => $submission,
				'submissionNoun' => $submissionNoun,
				'bestId' => $submission->getBestId(),
				'galley' => $galley,
				'currentVersionString' => $application->getCurrentVersion()->getVersionString(false),
				'isLatestPublication' => $submission->getData('currentPublicationId') === $galley->getData('publicationId'),
				'galleyPublication' => $galleyPublication,
			));
			$templateMgr->display($this->getTemplateResource('submissionGalley.tpl'));
			return true;
		}

		return false;
	}

	/**
	 * Callback that renders the issue galley.
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	function issueCallback($hookName, $args) {
		$request =& $args[0];
		$issue =& $args[1];
		$galley =& $args[2];

		$templateMgr = TemplateManager::getManager($request);
		if ($galley && $galley->getFileType() == 'application/epub+zip') {
			$application = Application::get();
			$templateMgr->assign(array(
				'displayTemplateResource' => $this->getTemplateResource('display.tpl'),
				'pluginUrl' => $request->getBaseUrl() . '/' . $this->getPluginPath(),
				'galleyFile' => $galley->getFile(),
				'issue' => $issue,
				'galley' => $galley,
				'currentVersionString' => $application->getCurrentVersion()->getVersionString(false),
				'isLatestPublication' => true,
			));
			$templateMgr->display($this->getTemplateResource('issueGalley.tpl'));
			return true;
		}

		return false;
	}

}

