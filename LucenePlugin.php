<?php

/**
 * @file LucenePlugin.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class LucenePlugin
 * @ingroup plugins_generic_lucene
 *
 * @brief Lucene plugin class
 */


import('lib.pkp.classes.plugins.GenericPlugin');
import('plugins.generic.lucene.classes.SolrWebService');

define('LUCENE_PLUGIN_DEFAULT_RANKING_BOOST', 1.0); // Default: No boost (=weight factor one).

class LucenePlugin extends GenericPlugin {

	/** @var SolrWebService */
	var $_solrWebService;

	/** @var array */
	var $_mailTemplates = [];

	/** @var string */
	var $_spellingSuggestion;

	/** @var string */
	var $_spellingSuggestionField;

	/** @var array */
	var $_highlightedArticles;

	/** @var array */
	var $_enabledFacetCategories;

	/** @var array */
	var $_facets;


	//
	// Getters and Setters
	//
	/**
	 * Get the solr web service.
	 * @return SolrWebService
	 */
	function getSolrWebService() {
		return $this->_solrWebService;
	}

	/**
	 * Facets corresponding to a recent search
	 * (if any).
	 * @return boolean
	 */
	function getFacets() {
		return $this->_facets;
	}

	/**
	 * Set an alternative article mailer implementation.
	 *
	 * NB: Required to override the mailer
	 * implementation for testing.
	 *
	 * @param $emailKey string
	 * @param $mailTemplate MailTemplate
	 */
	function setMailTemplate($emailKey, $mailTemplate) {
		$this->_mailTemplates[$emailKey] = $mailTemplate;
	}

	/**
	 * Instantiate a MailTemplate
	 *
	 * @param $emailKey string
	 * @param $journal Journal
	 */
	function getMailTemplate($emailKey, $journal = null) {
		if (!isset($this->_mailTemplates[$emailKey])) {
			import('lib.pkp.classes.mail.MailTemplate');
			$mailTemplate = new MailTemplate($emailKey, null, $journal, true, true);
			$this->_mailTemplates[$emailKey] = $mailTemplate;
		}

		return $this->_mailTemplates[$emailKey];
	}


	//
	// Implement template methods from Plugin.
	//
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return $success;

		if ($success && $this->getEnabled($mainContextId)) {
			// Register callbacks (application-level).
			HookRegistry::register('PluginRegistry::loadCategory', [$this, 'callbackLoadCategory']);
			HookRegistry::register('LoadHandler', [$this, 'callbackLoadHandler']);

			// Register callbacks (data-access level).
			HookRegistry::register('Schema::get::submission', function ($hookName, $args) {
				$schema = $args[0];

				$schema->properties->indexingState = (object)[
					'type' => 'string',
					'apiSummary' => false,
					'validation' => ['nullable']
				];
			});

			$customRanking = (boolean) $this->getSetting(CONTEXT_SITE, 'customRanking');
			if ($customRanking) {
				HookRegistry::register('sectiondao::getAdditionalFieldNames', [$this,
					'callbackSectionDaoAdditionalFieldNames'
				]);
			}

			// Register callbacks (controller-level).
			//these are current.
			HookRegistry::register('ArticleSearch::getResultSetOrderingOptions', [$this,
				'callbackGetResultSetOrderingOptions'
			]);
			HookRegistry::register('SubmissionSearch::retrieveResults', [$this, 'callbackRetrieveResults']);
			HookRegistry::register('ArticleSearchIndex::articleMetadataChanged', [$this,
				'callbackArticleMetadataChanged'
			]);
			HookRegistry::register('ArticleSearchIndex::submissionFileChanged', [$this,
				'callbackSubmissionFileChanged'
			]);
			HookRegistry::register('ArticleSearchIndex::submissionFileDeleted', [$this,
				'callbackSubmissionFileDeleted'
			]);
			HookRegistry::register('ArticleSearchIndex::submissionFilesChanged', [$this,
				'callbackSubmissionFilesChanged'
			]);

			HookRegistry::register('ArticleSearchIndex::submissionDeleted', [$this, 'callbackArticleDeleted']);
			HookRegistry::register('ArticleSearchIndex::articleDeleted', [$this, 'callbackArticleDeleted']);
			HookRegistry::register('ArticleSearchIndex::articleChangesFinished', [$this,
				'callbackArticleChangesFinished'
			]);
			HookRegistry::register('ArticleSearchIndex::rebuildIndex', [$this, 'callbackRebuildIndex']);
			HookRegistry::register('ArticleSearch::getSimilarityTerms', [$this, 'callbackGetSimilarityTerms']);

			// Register callbacks (forms).
			// For custom ranking. Seems to work, but value is either not saved, or not set as seleceted after loading form
			if ($customRanking) {
				HookRegistry::register('sectionform::Constructor', [$this, 'callbackSectionFormConstructor']);
				HookRegistry::register('sectionform::initdata', [$this, 'callbackSectionFormInitData']);
				HookRegistry::register('sectionform::readuservars', [$this, 'callbackSectionFormReadUserVars']);
				HookRegistry::register('sectionform::execute', [$this, 'callbackSectionFormExecute']);
				HookRegistry::register('Templates::Manager::Sections::SectionForm::AdditionalMetadata', [$this,
					'callbackTemplateSectionFormAdditionalMetadata'
				]);
			}


			$this->import('LuceneFacetsBlockPlugin');
			PluginRegistry::register('blocks', new LuceneFacetsBlockPlugin($this), $this->getPluginPath());


			// Register callbacks (view-level).
			HookRegistry::register('TemplateManager::display', [$this, 'callbackTemplateDisplay']);

			//used to show alterative spelling suggestions
			HookRegistry::register('Templates::Search::SearchResults::PreResults', [$this,
				'callbackTemplatePreResults'
			]);

			//used to show additional filters for selected facet values
			HookRegistry::register('Templates::Search::SearchResults::AdditionalFilters', [$this,
				'callbackTemplateAdditionalFilters'
			]);


			// Called from template article_summary.tpl, used to add highlighted additional info to searchresult.
			//As Templates::Search::SearchResults::AdditionalArticleLinks has been removed
			//from OJS 3, we also need this same hook to display additionalArticleLinks .
			HookRegistry::register('Templates::Issue::Issue::Article', [$this,
				'callbackTemplateSearchResultHighligtedText'
			]);
			HookRegistry::register('Templates::Issue::Issue::Article', [$this,
				'callbackTemplateSimilarDocumentsLinks'
			]);

			//Does not seem to be called anymore. Either has to be added to core search again, or we add it some other way only for lucene
			HookRegistry::register('Templates::Search::SearchResults::SyntaxInstructions', [$this,
				'callbackTemplateSyntaxInstructions'
			]);
			HookRegistry::register('Publication::unpublish', [$this,
				'callbackUnpublish'
			]);
			// Instantiate the web service.
			$searchHandler = $this->getSetting(CONTEXT_SITE, 'searchEndpoint');
			$username = $this->getSetting(CONTEXT_SITE, 'username');
			$password = $this->getSetting(CONTEXT_SITE, 'password');
			$instId = $this->getSetting(CONTEXT_SITE, 'instId');
			$this->_solrWebService = new SolrWebService($searchHandler, $username, $password, $instId);
		}

		return $success;
	}

	/**
	 * @see Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.lucene.displayName');
	}

	/**
	 * @see Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.lucene.description');
	}

	/**
	 * @see Plugin::getInstallSitePluginSettingsFile()
	 */
	function getInstallSitePluginSettingsFile() {
		return $this->getPluginPath() . '/settings.xml';
	}

	/**
	 * @see Plugin::getInstallEmailTemplatesFile()
	 */
	function getInstallEmailTemplatesFile() {
		return ($this->getPluginPath() . '/emailTemplates.xml');
	}

	/**
	 * @see Plugin::getInstallEmailTemplateDataFile()
	 */
	function getInstallEmailTemplateDataFile() {
		return ($this->getPluginPath() . '/locale/{$installedLocale}/emailTemplates.xml');
	}

	/**
	 * @see Plugin::isSitePlugin()
	 */
	function isSitePlugin() {
		return true;
	}

	//
	// Implement template methods from GenericPlugin.
	//
	/**
	 * @copydoc Plugin::getActions()
	 */
	function getActions($request, $actionArgs) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');

		return array_merge(
			$this->getEnabled() ? [
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, array_merge($actionArgs, array('verb' => 'settings'))),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			] : [],
			parent::getActions($request, $actionArgs)
		);
	}

	/**
	 * @copydoc Plugin::manage()
	 */
	function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				// Instantiate an embedded server instance.
				$this->import('classes.EmbeddedServer');
				$embeddedServer = new EmbeddedServer();

				// Instantiate the settings form.
				$this->import('classes.form.LuceneSettingsForm');
				$form = new LuceneSettingsForm($this, $embeddedServer);

				// Handle request to save configuration data.
				if ($request->getUserVar('rebuildIndex')) {
					// Re-init data. It should be visible to users
					// that whatever data they may have entered into
					// the form was not saved.
					$form->initData();
					//journal = null reindexes all dictionaries
					$message = null;
					$journal = null;
					if ($request->getUserVar('journalToReindex')) {
						$journalId = $request->getUserVar('journalToReindex');
						$journalDao = DAORegistry::getDAO('JournalDAO');
						$journal = $journalDao->getById($journalId);
						if (!is_a($journal, 'Journal')) $journal = null;
					}

					$this->_rebuildIndex(false, $journal, true, false, $message);
					$form->setData('rebuildIndexMessages', $message);
				}
				else if ($request->getUserVar('rebuildDictionaries')) {
					// Re-init data. It should be visible to users
					// that whatever data they may have entered into
					// the form was not saved.
					$form->initData();
					// rebuild dictionaries
					$message = '';
					$this->_rebuildIndex(false, null, false, true, $message);
					$form->setData('rebuildIndexMessages', $message);
				}
				else if ($request->getUserVar('stopServer')) {
					$form->initData();
					// As this is a system plug-in we follow usual
					// plug-in policy and allow journal managers to start/
					// stop the server although this will affect all journals
					// of the installation.
					$embeddedServer->stopAndWait();
				}
				else if ($request->getUserVar('startServer')) {
					$form->initData();
					$embeddedServer->start();
				}
				else if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();

						return new JSONMessage(true);
					}
				}
				else {
					// Re-init data. It should be visible to users
					// that whatever data they may have entered into
					// the form was not saved.
					$form->initData();

				}

				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}


	//
	// Application level hook implementations.
	//
	/**
	 * @see PluginRegistry::loadCategory()
	 */
	function callbackLoadCategory($hookName, $args) {
		// We only contribute to the block plug-in category.
		$category = $args[0];
		if ($category != 'blocks') return false;

		// We only contribute a plug-in if at least one
		// faceting category is enabled.
		$enabledFacetCategories = $this->_getEnabledFacetCategories();
		if (empty($enabledFacetCategories)) return false;

		// Instantiate the block plug-in for facets.
		$this->import('LuceneFacetsBlockPlugin');
		$luceneFacetsBlockPlugin = new LuceneFacetsBlockPlugin($this);

		// Add the plug-in to the registry.
		$plugins =& $args[1];
		$seq = $luceneFacetsBlockPlugin->getSeq();
		if (!isset($plugins[$seq])) $plugins[$seq] = [];
		$plugins[$seq][$luceneFacetsBlockPlugin->getPluginPath()] = $luceneFacetsBlockPlugin;

		return false;
	}

	/**
	 * @see PKPPageRouter::route()
	 */
	function callbackLoadHandler($hookName, $args) {
		// Check the page.
		$page = $args[0];
		if ($page !== 'lucene') return;

		// Check the operation.
		$op = $args[1];
		$publicOps = [
			'queryAutocomplete',
			'pullChangedArticles',
			'similarDocuments',
		];
		if (!in_array($op, $publicOps)) return;

		// Get the journal object from the context (optimized).
		$request = Application::get()->getRequest();
		$router = $request->getRouter();
		$journal = $router->getContext($request);
		/* @var $journal Journal */

		// Looks as if our handler had been requested.
		define('HANDLER_CLASS', 'LuceneHandler');
		define('LUCENE_PLUGIN_NAME', $this->getName());
		$handlerFile =& $args[2];
		$handlerFile = $this->getPluginPath() . '/' . 'LuceneHandler.inc.php';
	}


	//
	// Data-access level hook implementations.
	//
	/**
	 * @see DAO::getAdditionalFieldNames()
	 */
	function callbackSectionDaoAdditionalFieldNames($hookName, $args) {
		// Add the custom ranking setting to the field names.
		// This will be used to adjust the ranking boost of all
		// articles in a given section.
		$returner =& $args[1];
		$returner[] = 'rankingBoost';
	}


	//
	// Controller level hook implementations.
	//
	/**
	 * @see ArticleSearch::getResultSetOrderingOptions()
	 */
	function callbackGetResultSetOrderingOptions($hookName, $params) {
		$resultSetOrderingOptions =& $params[1];
	}

	/**
	 */
	function callbackRetrieveResults($hookName, $params) {
		assert($hookName == 'SubmissionSearch::retrieveResults');

		// Unpack the parameters.
		list($journal, $keywords, $fromDate, $toDate, $orderBy, $orderDir, $exclude, $page, $itemsPerPage) = $params;
		$totalResults =& $params[9]; // need to use reference
		$error =& $params[10]; // need to use reference
		$results =& $params[11];

		// Instantiate a search request.
		$searchRequest = new SolrSearchRequest();
		$searchRequest->setJournal($journal);
		$searchRequest->setFromDate($fromDate);
		$searchRequest->setToDate($toDate);
		if (isset($keywords[SUBMISSION_SEARCH_AUTHOR])) $searchRequest->setAuthors($keywords[SUBMISSION_SEARCH_AUTHOR]);
		$searchRequest->setOrderBy($orderBy);
		$searchRequest->setOrderDir($orderDir == 'asc' ? true : false);
		$searchRequest->setPage($page);
		$searchRequest->setItemsPerPage($itemsPerPage);
		$searchRequest->addQueryFromKeywords($keywords);
		$searchRequest->setExcludedIds($exclude);

		// Get the ordering criteria.
		list($orderBy, $orderDir) = $this->_getResultSetOrdering($journal);
		$searchRequest->setOrderBy($orderBy);
		$searchRequest->setOrderDir($orderDir == 'asc' ? true : false);

		// Configure alternative spelling suggestions.
		$spellcheck = (boolean) $this->getSetting(CONTEXT_SITE, 'spellcheck');
		$searchRequest->setSpellcheck($spellcheck);

		// Configure highlighting.
		$highlighting = (boolean) $this->getSetting(CONTEXT_SITE, 'highlighting');
		$searchRequest->setHighlighting($highlighting);

		// Configure faceting.
		// 1) Faceting will be disabled for filtered search categories.
		$activeFilters = array_keys($searchRequest->getQuery());
		if ($journal instanceof Journal) $activeFilters[] = 'journalTitle';
		if (!empty($fromDate) || !empty($toDate)) $activeFilters[] = 'publicationDate';
		// 2) Switch faceting on for enabled categories that have no
		// active filters.
		$facetCategories = array_values(array_diff($this->_getEnabledFacetCategories(), $activeFilters));
		$searchRequest->setFacetCategories($facetCategories);

		// Configure custom ranking.
		$customRanking = (boolean) $this->getSetting(CONTEXT_SITE, 'customRanking');
		if ($customRanking) {
			$sectionDao = DAORegistry::getDAO('SectionDAO');
			/* @var $sectionDao SectionDAO */
			if (is_a($journal, 'Journal')) {
				$sections = $sectionDao->getByJournalId($journal->getId());
			}
			else {
				$sections = $sectionDao->getAll();
			}
			while ($section = $sections->next()) {
				/* @var $sections DAOResultFactory */
				if ($section != null) {
					$sectionBoost = (float) $section->getData('rankingBoost');
					if ($sectionBoost != null && $sectionBoost != LUCENE_PLUGIN_DEFAULT_RANKING_BOOST) {
						$searchRequest->addBoostFactor(
							'section_id', $section->getId(), $sectionBoost
						);
					}
				}
			}
			unset($sections);
		}

		// Call the solr web service.
		$solrWebService = $this->getSolrWebService();
		$result = $solrWebService->retrieveResults($searchRequest, $totalResults, $this->getSetting(CONTEXT_SITE, 'useSolr7'));
		if (is_null($result)) {
			$error = $solrWebService->getServiceMessage();
			$this->_informTechAdmin($error, $journal, true);
			$error .= ' ' . __('plugins.generic.lucene.message.techAdminInformed');

			return [];
		}
		else {
			// Store spelling suggestion, highlighting and faceting info
			// internally. We cannot route these back through the request
			// as the default search implementation does not support
			// these features.
			if ($spellcheck && isset($result['spellingSuggestion'])) {
				$this->_spellingSuggestion = $result['spellingSuggestion'];

				// Identify the field for which we got the suggestion.
				foreach ($keywords as $bitmap => $searchPhrase) {
					if (!empty($searchPhrase)) {
						switch ($bitmap) {
							case null:
								$queryField = 'query';
								break;

							case SUBMISSION_SEARCH_INDEX_TERMS:
								$queryField = 'indexTerms';
								break;

							default:
								$articleSearch = new ArticleSearch();
								$indexFieldMap = $articleSearch->getIndexFieldMap();
								assert(isset($indexFieldMap[$bitmap]));
								$queryField = $indexFieldMap[$bitmap];
						}
					}
				}
				$this->_spellingSuggestionField = $queryField;
			}
			if ($highlighting && isset($result['highlightedArticles'])) {
				$this->_highlightedArticles = $result['highlightedArticles'];
			}
			if (!empty($facetCategories) && isset($result['facets'])) {
				$this->_facets = $result['facets'];
			}

			// Return the scored results.
			if (isset($result['scoredResults']) && !empty($result['scoredResults'])) {
				$results = $result['scoredResults'];

				return $result['scoredResults'];
			}
			else {
				return [];
			}
		}
	}

	/**
	 * @see ArticleSearchIndex::articleMetadataChanged()
	 */
	function callbackArticleMetadataChanged($hookName, $params) {
		assert($hookName == 'ArticleSearchIndex::articleMetadataChanged');
		list($article) = $params;
		/* @var $article Article */
		$this->_solrWebService->setArticleStatus($article->getId());
		// in OJS core in many cases callbackArticleChangesFinished is not called.
		// So we call it ourselves, it won't do anything is pull-indexing is active
		$this->callbackArticleChangesFinished(null, null);

		return true;
	}

	/**
	 * @see ArticleSearchIndex::submissionFilesChanged()
	 */
	function callbackSubmissionFilesChanged($hookName, $params) {
		assert($hookName == 'ArticleSearchIndex::submissionFilesChanged');
		list($article) = $params;
		/* @var $article Article */
		$this->_solrWebService->setArticleStatus($article->getId());

		return true;
	}

	/**
	 * @see ArticleSearchIndex::submissionFileChanged()
	 */
	function callbackSubmissionFileChanged($hookName, $params) {
		assert($hookName == 'ArticleSearchIndex::submissionFileChanged');
		list($articleId, $type, $fileId) = $params;
		$this->_solrWebService->setArticleStatus($article->getId());
		// in OJS core in many cases callbackArticleChangesFinished is not called.
		// So we call it ourselves, it won't do anything is pull-indexing is active
		$this->callbackArticleChangesFinished(null, null);

		return true;
	}

	/**
	 * @see ArticleSearchIndex::submissionFileDeleted()
	 */
	function callbackSubmissionFileDeleted($hookName, $params) {
		assert($hookName == 'ArticleSearchIndex::submissionFileDeleted');
		list($articleId, $type, $assocId) = $params;
		$this->_solrWebService->setArticleStatus($article->getId());

		return true;
	}

	/**
	 * @see ArticleSearchIndex::articleDeleted()
	 */
	function callbackArticleDeleted($hookName, $params) {
		assert($hookName == 'ArticleSearchIndex::articleDeleted');
		list($articleId) = $params;
		// Deleting an article must always be done synchronously
		// (even in pull-mode) as we'll no longer have an object
		// to keep our change information.
		$this->_solrWebService->deleteArticleFromIndex($articleId);

		return true;
	}

	function callbackUnpublish($hookName, $params) {
		list($newPublication, $publication, $submission) = $params;

		$solrWebService = $this->getSolrWebService();
		$solrWebService->deleteArticleFromIndex($submission->getId());
		return true;
	}

	/**
	 * @see ArticleSearchIndex::articleChangesFinished()
	 */
	function callbackArticleChangesFinished($hookName, $params) {
		// In the case of pull-indexing we ignore this call
		// and let the Solr server initiate indexing.
		if ($this->getSetting(CONTEXT_SITE, 'pullIndexing')) return true;

		// If the plugin is configured to push changes to the
		// server then we'll now batch-update all articles that
		// changed since the last update. We use a batch size of 5
		// for online index updates to limit the time a request may be
		// locked in case a race condition with a large index update
		// occurs.
		$solrWebService = $this->getSolrWebService();
		$result = $solrWebService->pushChangedArticles(5);
		if (is_null($result)) {
			$this->_informTechAdmin($solrWebService->getServiceMessage());
		}

		return true;
	}

	/**
	 * @see ArticleSearchIndex::rebuildIndex()
	 */
	function callbackRebuildIndex($hookName, $params) {
		assert($hookName == 'ArticleSearchIndex::rebuildIndex');

		// Unpack the parameters.
		list($log, $journal, $switches) = $params;

		// Check switches.
		$rebuildIndex = true;
		$rebuildDictionaries = false;
		//$updateBoostFile = false;
		if (is_array($switches)) {
			if (in_array('-n', $switches)) {
				$rebuildIndex = false;
			}
			if (in_array('-d', $switches)) {
				$rebuildDictionaries = true;
			}
			/*
			if (in_array('-b', $switches)) {
				$updateBoostFile = true;
			}*/
		}

		// Rebuild the index.
		$messages = null;
		$this->_rebuildIndex($log, $journal, $rebuildIndex, $rebuildDictionaries, $messages);

		return true;
	}

	/**
	 * @see ArticleSearch::getSimilarityTerms()
	 */
	function callbackGetSimilarityTerms($hookName, $params) {
		$articleId = $params[0];
		$searchTerms =& $params[1];

		// Identify "interesting" terms of the
		// given article and return them "by ref".
		$solrWebService = $this->getSolrWebService();
		$searchTerms = $solrWebService->getInterestingTerms($articleId);

		return true;
	}


	//
	// Form hook implementations.
	//
	/**
	 * @see Form::__construct()
	 */
	function callbackSectionFormConstructor($hookName, $params) {
		// Check whether we got a valid ranking boost option.
		$acceptedValues = array_keys($this->_getRankingBoostOptions());
		$form =& $params[0];
		$form->addCheck(
			new FormValidatorInSet(
				$form, 'rankingBoostOption', FORM_VALIDATOR_REQUIRED_VALUE,
				'plugins.generic.lucene.sectionForm.rankingBoostInvalid',
				$acceptedValues
			)
		);

		return false;
	}

	/**
	 * @see Form::initData()
	 */
	function callbackSectionFormInitData($hookName, $params) {
		$form =& $params[0];
		/* @var $form SectionForm */

		// Read the section's ranking boost.
		$rankingBoost = LUCENE_PLUGIN_DEFAULT_RANKING_BOOST;
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$journal = Application::get()->getRequest()->getJournal();
		$section = null;
		if ($form->getSectionId()) {
			$section = $sectionDao->getById($form->getSectionId(), $journal->getId());
		}

		if (is_a($section, 'Section')) {
			$rankingBoostSetting = $section->getData('rankingBoost');
			if (is_numeric($rankingBoostSetting)) $rankingBoost = (float) $rankingBoostSetting;
		}

		// The ranking boost is a floating-poing multiplication
		// factor (0, 0.5, 1, 2). Translate this into an integer
		// option value (0, 1, 2, 4).
		$rankingBoostOption = (int) ($rankingBoost * 2);
		$rankingBoostOptions = $this->_getRankingBoostOptions();
		if (!in_array($rankingBoostOption, array_keys($rankingBoostOptions))) {
			$rankingBoostOption = (int) (LUCENE_PLUGIN_DEFAULT_RANKING_BOOST * 2);
		}
		$form->setData('rankingBoostOption', $rankingBoostOption);

		return false;
	}

	/**
	 * @see Form::readUserVars()
	 */
	function callbackSectionFormReadUserVars($hookName, $params) {
		$userVars =& $params[1];
		$userVars[] = 'rankingBoostOption';

		return false;
	}

	/**
	 * Callback for execution upon section form save
	 * @param $hookName string
	 * @param $params array
	 * @return mixed
	 */
	function callbackSectionFormExecute($hookName, $params) {
		// Convert the ranking boost option back into a ranking boost factor.
		$form =& $params[0];
		/* @var $form SectionForm */
		$rankingBoostOption = $form->getData('rankingBoostOption');
		$rankingBoostOptions = $this->_getRankingBoostOptions();
		if (in_array($rankingBoostOption, array_keys($rankingBoostOptions))) {
			$rankingBoost = ((float) $rankingBoostOption) / 2;
		}
		else {
			$rankingBoost = LUCENE_PLUGIN_DEFAULT_RANKING_BOOST;
		}

		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$journal = Application::get()->getRequest()->getJournal();

		// Get or create the section object
		if ($form->getSectionId()) {
			$section = $sectionDao->getById($form->getSectionId(), $journal->getId());
			// Update the ranking boost of the section.
			$section->setData('rankingBoost', $rankingBoost);
			$sectionDao->updateObject($section);
		}

		return false;
	}


	//
	// View level hook implementations.
	//
	/**
	 * @see TemplateManager::display()
	 */
	function callbackTemplateDisplay($hookName, $params) {
		$template = $params[1];

		if ($template == 'frontend/pages/indexSite.tpl') return false;
		// avoid recursive calls of the template
		if (strpos($template,'plugins-') !== false) return false;
		if (strpos($template,'frontend/pages/') === false) return false;

		// Get the request.
		$request = Application::get()->getRequest();
		// Get the context
		$journal = $request->getContext();

		// Assign our private stylesheet.
		/** @var TemplateManager */
		$templateMgr = $params[0];
		$templateMgr->addStylesheet('lucene', $request->getBaseUrl() . '/' . $this->getPluginPath() . '/templates/lucene.css');

		// Result set ordering options.
		$orderByOptions = $this->_getResultSetOrderingOptions($journal);
		$templateMgr->assign('luceneOrderByOptions', $orderByOptions);
		$orderDirOptions = $this->_getResultSetOrderingDirectionOptions();
		$templateMgr->assign('luceneOrderDirOptions', $orderDirOptions);

		// Similar documents.
		if ($this->getSetting(CONTEXT_SITE, 'simdocs')) {
			$templateMgr->assign('simDocsEnabled', true);
		}

		if ($this->getSetting(CONTEXT_SITE, 'autosuggest')) {
			$templateMgr->assign('enableAutosuggest', true);
			$templateMgr->display('extends:'.$template.'|'.$this->getTemplateResource('luceneSearch.tpl'));
			return true;
		}

		return false;
	}

	/**
	 * @see templates/search/searchResults.tpl
	 */
	function callbackTemplatePreResults($hookName, $params) {
		$request = Application::get()->getRequest();
		$templateMgr = TemplateManager::getManager($request);
		// The spelling suggestion value is set in
		// LucenePlugin::callbackRetrieveResults(), see there.
		$templateMgr->assign('spellingSuggestion', $this->_spellingSuggestion);
		$templateMgr->assign(
			'spellingSuggestionUrlParams',
			[$this->_spellingSuggestionField => $this->_spellingSuggestion]
		);

		$templateMgr->display($this->getTemplateResource('preResults.tpl'));

		return false;

	}

	function callbackTemplateAdditionalFilters($hookName, $params) {
		$request = Application::get()->getRequest();
		$templateMgr = TemplateManager::getManager($request);

		$smarty =& $params[1];
		$enabledFacets = $this->_getEnabledFacetCategories();
		$facets = $this->getFacets();
		$selectedFacets = [];
		foreach ($enabledFacets as $currentFacet) {
			if ($currentValue = $smarty->getTemplateVars($currentFacet)) {
				$facetDisplayName = __('plugins.generic.lucene.faceting.' . $currentFacet);
				//author is already available in standard filtersection, so we don't show that again
				if ($currentFacet != "authors") {
					$selectedFacets[$currentFacet] = ['facetDisplayName' => $facetDisplayName,
						'facetValue' => $currentValue
					];
				}
			}
		}

		$templateMgr->assign('selectedFacets', $selectedFacets);

		$templateMgr->display($this->getTemplateResource('additionalFilters.tpl'));

		return false;
	}

	/**
	 * @see templates/frontend/objects/article_summary.tpl
	 */
	function callbackTemplateSearchResultHighligtedText($hookName, $params) {
		$smarty =& $params[1];
		$article = $smarty->getTemplateVars('article');

		// Check whether the "highlighting" feature is enabled.
		if (!$this->getSetting(CONTEXT_SITE, 'highlighting')) return false;

		// Check and prepare the article parameter.
		if (!(isset($article) && is_numeric($article->getId()))) {
			return false;
		}
		$articleId = $article->getId();

		// Check whether we have highlighting info for the given article.
		if (!isset($this->_highlightedArticles[$articleId])) return false;

		// Return the excerpt (a template seems overkill here).
		// Escaping should have been taken care of when analyzing the text, so
		// there should be no XSS risk here (but we need the <em> tag in the
		// highlighted result).
		$output =& $params[2];
		$output .= '<div class="plugins_generic_lucene_highlighting">...&nbsp;'
			. trim($this->_highlightedArticles[$articleId]) . '&nbsp;..."</div>';

		return false;
	}

	/**
	 * @see templates/search/searchResults.tpl
	 */
	function callbackTemplateSimilarDocumentsLinks($hookName, $params) {
		// Check whether the "similar documents" feature is
		// enabled.
		if (!$this->getSetting(0, 'simdocs')) return false;

		$smarty =& $params[1];
		$article = $smarty->getTemplateVars('article');

		// Check and prepare the article parameter.
		if (!(isset($article) && is_numeric($article->getId()))) {
			return false;
		}

		$urlParams = ['articleId' => $article->getId()];

		// Create a URL that links to "similar documents".
		$request = PKPApplication::get()->getRequest();
		$router = $request->getRouter();
		$simdocsUrl = $router->url(
			$request, null, 'lucene', 'similarDocuments', null, $urlParams
		);

		// Return a link to the URL (a template seems overkill here).
		$output =& $params[2];
		$output .= '<div class="plugins_generic_lucene_similardocuments"><a href="' . $simdocsUrl . '" class="file">'
			. __('plugins.generic.lucene.results.similarDocuments')
			. '</a></div>';

		return false;
	}

	/**
	 * @see templates/search/searchResults.tpl
	 */
	function callbackTemplateSyntaxInstructions($hookName, $params) {
		$output =& $params[2];
		$output .= __('plugins.generic.lucene.results.syntaxInstructions');

		return false;
	}

	/**
	 * @see templates/manager/sections/sectionForm.tpl
	 */
	function callbackTemplateSectionFormAdditionalMetadata($hookName, $params) {
		// Assign the ranking boost options to the template.
		$request = Application::get()->getRequest();
		$templateMgr = TemplateManager::getManager($request);

		$templateMgr->assign('rankingBoostOptions', $this->_getRankingBoostOptions());;

		$templateMgr->display($this->getTemplateResource('additionalSectionMetadata.tpl'));

		return false;

	}


	/**
	 * Return the available options for result
	 * set ordering.
	 * @param $journal Journal
	 * @return array
	 */
	function _getResultSetOrderingOptions($journal) {
		$resultSetOrderingOptions = [];
		if ($this->getSetting(CONTEXT_SITE, 'orderByRelevance')) {
			$resultSetOrderingOptions['score'] =  __('plugins.generic.lucene.results.orderBy.relevance');
		}
		if ($this->getSetting(CONTEXT_SITE, 'orderByAuthor')) {
			$resultSetOrderingOptions['authors'] =  __('plugins.generic.lucene.results.orderBy.author');
		}
		if ($this->getSetting(CONTEXT_SITE, 'orderByIssue')) {
			$resultSetOrderingOptions['issuePublicationDate'] =  __('plugins.generic.lucene.results.orderBy.issue');
		}
		if ($this->getSetting(CONTEXT_SITE, 'orderByDate')) {
			$resultSetOrderingOptions['publicationDate'] =  __('plugins.generic.lucene.results.orderBy.date');
		}
		if ($this->getSetting(CONTEXT_SITE, 'orderByArticle')) {
			$resultSetOrderingOptions['title'] =  __('plugins.generic.lucene.results.orderBy.article');
		}

		// Only show the "journal title" option if we have several journals.
		if (!is_a($journal, 'Journal') && $this->getSetting(CONTEXT_SITE, 'orderByJournal')) {
			$resultSetOrderingOptions['journalTitle'] = __('plugins.generic.lucene.results.orderBy.journal');
		}

		return $resultSetOrderingOptions;
	}

	/**
	 * Return the available options for the result
	 * set ordering direction.
	 * @return array
	 */
	function _getResultSetOrderingDirectionOptions() {
		return [
			'asc' => __('plugins.generic.lucene.results.orderDir.asc'),
			'desc' => __('plugins.generic.lucene.results.orderDir.desc')
		];
	}

		//
	// Private helper methods
	//
	/**
	 * Return the currently selected result
	 * set ordering option (default: descending relevance).
	 * @param $journal Journal
	 * @return array An array with the order field as the
	 *  first entry and the order direction as the second
	 *  entry.
	 */
	function _getResultSetOrdering($journal) {
		// Retrieve the request.
		$request = Application::get()->getRequest();

		// Order field.
		$orderBy = $request->getUserVar('orderBy');
		$orderByOptions = $this->_getResultSetOrderingOptions($journal);
		if (is_null($orderBy) || !in_array($orderBy, array_keys($orderByOptions))) {
			$orderBy = 'score';
		}

		// Ordering direction.
		$orderDir = $request->getUserVar('orderDir');
		$orderDirOptions = $this->_getResultSetOrderingDirectionOptions();
		if (is_null($orderDir) || !in_array($orderDir, array_keys($orderDirOptions))) {
			if (in_array($orderBy, ['score', 'publicationDate', 'issuePublicationDate'])) {
				$orderDir = 'desc';
			}
			else {
				$orderDir = 'asc';
			}
		}

		return [$orderBy, $orderDir];
	}


	/**
	 * Get all currently enabled facet categories.
	 * @return array
	 */
	function _getEnabledFacetCategories() {
		if (!is_array($this->_enabledFacetCategories)) {
			$this->_enabledFacetCategories = [];
			$availableFacetCategories = [
				'discipline',
				'subject',
				'type',
				'coverage',
				'journalTitle',
				'authors',
				'publicationDate'
			];
			foreach ($availableFacetCategories as $facetCategory) {
				if ($this->getSetting(CONTEXT_SITE, 'facetCategory' . ucfirst($facetCategory))) {
					$this->_enabledFacetCategories[] = $facetCategory;
				}
			}
		}

		return $this->_enabledFacetCategories;
	}

	/**
	 * Rebuild the index for all journals or a single journal
	 * @param $log boolean Whether to write the log to standard output.
	 * @param $journal Journal If given, only re-index this journal.
	 * @param $buildIndex boolean Whether to rebuild the journal index.
	 * @param $buildDictionaries boolean Whether to rebuild dictionaries.
	 * @param $messages string Return parameter for log message output.
	 * @return boolean True on success, otherwise false.
	 */
	function _rebuildIndex($log, $journal, $buildIndex, $buildDictionaries, &$messages) {
		// Rebuilding the index can take a long time.
		@set_time_limit(0);
		$solrWebService = $this->getSolrWebService();

		if ($buildIndex) {
			// If we got a journal instance then only re-index
			// articles from that journal.
			$journalIdOrNull = (is_a($journal, 'Journal') ? $journal->getId() : null);

			// Clear index (if the journal id is null then
			// all journals will be deleted from the index).
			$this->_indexingMessage($log, 'LucenePlugin: ' . __('search.cli.rebuildIndex.clearingIndex') . ' ... ', $messages);
			$solrWebService->deleteArticlesFromIndex($journalIdOrNull);
			$this->_indexingMessage($log, __('search.cli.rebuildIndex.done') . PHP_EOL, $messages);

			// Re-build index, either of a single journal...
			if ($journal instanceof Journal) {
				$journals = [$journal];
				unset($journal);
				// ...or for all journals.
			}
			else {
				$journalDao = DAORegistry::getDAO('JournalDAO');
				/* @var $journalDao JournalDAO */
				$journalIterator = $journalDao->getAll(true);
				$journals = $journalIterator->toArray();
			}

			// We re-index journal by journal to partition the task a bit
			// and provide better progress information to the user.
			foreach ($journals as $journal) {
				$this->_indexingMessage($log, 'LucenePlugin: ' . __('search.cli.rebuildIndex.indexing', ['journalName' => $journal->getLocalizedName()]) . ' ', $messages);

				// Mark all articles in the journal for re-indexing.
				$numMarked = $this->_solrWebService->markJournalChanged($journal->getId());

				// Pull or push?
				if ($this->getSetting(CONTEXT_SITE, 'pullIndexing')) {
					// When pull-indexing is configured then we leave it up to the
					// Solr server to decide when the updates will actually be done.
					$this->_indexingMessage($log, '... ' . __('plugins.generic.lucene.rebuildIndex.pullResult', ['numMarked' => $numMarked]) . PHP_EOL, $messages);
				}
				else {
					// In case of push indexing we immediately update the index.
					$numIndexed = 0;
					do {
						// We update the index in batches to reduce max memory usage
						// and make a few intermediate commits.
						$articlesInBatch = $solrWebService->pushChangedArticles(SOLR_INDEXING_MAX_BATCHSIZE, $journal->getId());
						if (is_null($articlesInBatch)) {
							$error = $solrWebService->getServiceMessage();
							$this->_indexingMessage($log, ' ' . __('search.cli.rebuildIndex.error') . (empty($error) ? '' : ": $error") . PHP_EOL, $messages);
							if (!$log) {
								// If logging is switched off then inform the
								// tech admin with an email (e.g. in the case of
								// an OJS upgrade).
								$this->_informTechAdmin($error, $journal);
							}

							return true;
						}
						$this->_indexingMessage($log, '.', $messages);
						$numIndexed += $articlesInBatch;
					} while ($articlesInBatch == SOLR_INDEXING_MAX_BATCHSIZE );
					$this->_indexingMessage($log, ' ' . __('search.cli.rebuildIndex.result', ['numIndexed' => $numIndexed]) . PHP_EOL, $messages);
				}
			}
		}

		if ($buildDictionaries) {
			// Rebuild dictionaries.
			$this->_indexingMessage($log, 'LucenePlugin: ' . __('plugins.generic.lucene.rebuildIndex.rebuildDictionaries') . ' ... ', $messages);
			$solrWebService->rebuildDictionaries();
			//if ($updateBoostFile) $this->_indexingMessage($log, __('search.cli.rebuildIndex.done') . PHP_EOL, $messages);
		}

		// Remove the field cache file as additional fields may be available after re-indexing. If we don't
		// do this it may seem that indexing didn't work as the cache will only be invalidated after 24 hours.
		$cacheFile = 'cache/fc-plugins-lucene-fieldCache.php';
		if (file_exists($cacheFile)) {
			if (is_writable(dirname($cacheFile))) {
				unlink($cacheFile);
			}
			else {
				$this->_indexingMessage($log, 'LucenePlugin: ' . __('plugins.generic.lucene.rebuildIndex.couldNotDeleteFieldCache') . PHP_EOL, $messages);
			}
		}

		/*if ($updateBoostFile) {
			// Update the boost file.
			$this->_indexingMessage($log, 'LucenePlugin: ' . __('plugins.generic.lucene.rebuildIndex.updateBoostFile') . ' ... ', $messages);
			$this->_updateBoostFiles();
		}*/

		if ($this->getSetting(CONTEXT_SITE, 'pullIndexing')) {
			$this->_indexingMessage($log, 'LucenePlugin: ' . __('plugins.generic.lucene.rebuildIndex.pullWarning') . PHP_EOL, $messages);
		}

		$this->_indexingMessage($log, __('search.cli.rebuildIndex.done') . PHP_EOL, $messages);

		return true;
	}

	/**
	 * Output an indexing message.
	 * @param $log boolean Whether to write the log to standard output.
	 * @param $message string The message to display/add.
	 * @param $messages string Return parameter for log message output.
	 */
	function _indexingMessage($log, $message, &$messages) {
		if ($log) echo $message;
		$messages .= $message;
	}

	/**
	 * Checks whether a minimum amount of time has passed since
	 * the last email message went out.
	 *
	 * @return boolean True if a new email can be sent, false if
	 *  we better keep silent.
	 */
	function _spamCheck() {
		// Avoid spam.
		$lastEmailTimstamp = (integer) $this->getSetting(CONTEXT_SITE, 'lastEmailTimestamp');
		$threeHours = 60 * 60 * 3;
		$now = time();
		if ($now - $lastEmailTimstamp < $threeHours) return false;
		$this->updateSetting(CONTEXT_SITE, 'lastEmailTimestamp', $now);

		return true;
	}

	/**
	 * Send an email to the site's tech admin
	 * warning that an indexing error has occurred.
	 *
	 * @param $error array An array of article ids.
	 * @param $journal Journal A journal object.
	 * @param $isSearchProblem boolean Whether a search problem
	 *  is being reported.
	 */
	function _informTechAdmin($error, $journal = null, $isSearchProblem = false) {
		if (!$this->_spamCheck()) return;

		// Is this a search or an indexing problem?
		if ($isSearchProblem) {
			$mail = $this->getMailTemplate('LUCENE_SEARCH_SERVICE_ERROR_NOTIFICATION', $journal);
		}
		else {
			// Check whether this is journal or article index update problem.
			if (is_a($journal, 'Journal')) {
				// This must be a journal indexing problem.
				$mail = $this->getMailTemplate('LUCENE_JOURNAL_INDEXING_ERROR_NOTIFICATION', $journal);
			}
			else {
				// Instantiate an article mail template.
				$mail = $this->getMailTemplate('LUCENE_ARTICLE_INDEXING_ERROR_NOTIFICATION');
			}
		}

		// Assign parameters.
		$request = Application::get()->getRequest();
		$site = $request->getSite();
		$mail->assignParams(
			['siteName' => $site->getLocalizedTitle(), 'error' => $error]
		);

		// Send to the site's tech contact.
		$mail->addRecipient($site->getLocalizedContactEmail(), $site->getLocalizedContactName());

		// Send the mail.
		$mail->send($request);
	}

	/**
	 * Return the available ranking boost options.
	 * @return array
	 */
	function _getRankingBoostOptions() {
		return [
			0 => __('plugins.generic.lucene.sectionForm.ranking.never'),
			1 => __('plugins.generic.lucene.sectionForm.ranking.low'),
			2 => __('plugins.generic.lucene.sectionForm.ranking.normal'),
			4 => __('plugins.generic.lucene.sectionForm.ranking.high')
		];
	}
}
