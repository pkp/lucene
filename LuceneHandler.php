<?php

/**
 * @file LuceneHandler.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LuceneHandler
 * @brief Handle lucene AJAX and XML requests (auto-completion, pull indexation, etc.)
 */

namespace APP\plugins\generic\lucene;

use APP\handler\Handler;
use APP\plugins\generic\lucene\classes\SolrSearchRequest;
use APP\plugins\generic\lucene\classes\SolrWebService;
use PKP\core\JSONMessage;
use APP\search\ArticleSearch;

class LuceneHandler extends Handler {
	protected LucenePlugin $plugin;

	function __construct(LucenePlugin $plugin) {
		$this->plugin = $plugin;
	}

	//
	// Public operations
	//
	/**
	 * AJAX request for search query auto-completion.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function queryAutocomplete($args, $request) {
		$this->validate(null, $request);

		// Check whether auto-suggest is enabled.
		$suggestionList = [];
		$enabled = (bool)$this->plugin->getSetting(CONTEXT_SITE, 'autosuggest');
		if ($enabled) {
			// Retrieve search criteria from the user input.
			$articleSearch = new ArticleSearch();
			$searchFilters = $articleSearch->getSearchFilters($request);

			// Get the autosuggest input and remove it from
			// the filter array.
			$autosuggestField = $request->getUserVar('searchField');
			$userInput = isset($searchFilters[$autosuggestField]) ? $searchFilters[$autosuggestField] : $searchFilters['query'];
			if (isset($searchFilters[$autosuggestField])) {
				unset($searchFilters[$autosuggestField]);
			}

			// Instantiate a search request.
			$searchRequest = new SolrSearchRequest();
			$searchRequest->setJournal($searchFilters['searchJournal']);
			$searchRequest->setFromDate($searchFilters['fromDate']);
			$searchRequest->setToDate($searchFilters['toDate']);
			$keywords = $articleSearch->getKeywordsFromSearchFilters($searchFilters);
			$searchRequest->addQueryFromKeywords($keywords);

			// Get the web service.
			$solrWebService = $this->plugin->getSolrWebService(); /* @var $solrWebService SolrWebService */
			$suggestions = $solrWebService->getAutosuggestions(
				$searchRequest, $autosuggestField, $userInput,
				(int)$this->plugin->getSetting(CONTEXT_SITE, 'autosuggestType')
			);

			// Prepare a suggestion list as understood by the
			// autocomplete JS handler.
			foreach($suggestions as $suggestion) {
				$suggestionList[] = ['label' => $suggestion, 'value' => $suggestion];
			}
		}

		// Return the suggestions as JSON message.
		return new JSONMessage(true, $suggestionList);
	}

	/**
	 * If pull-indexing is enabled then this handler returns
	 * article metadata in a formate that can be consumed by
	 * the Solr data import handler.
	 * @param $args array
	 * @param $request Request
	 * @return JSON string
	 */
	function pullChangedArticles($args, $request) {
		$this->validate(null, $request);

		// Do not allow access to this operation from journal context.
		$router = $request->getRouter();
		$journal = $router->getContext($request);
		if (!is_null($journal)) {
			// Redirect to the index context. We do this so that providers
			// can secure a single entry point when providing subscription-only
			// content.
			$request->redirect('index', 'lucene', 'pullChangedArticles');
		}

		// Die if pull indexing is disabled.
		if (!$this->plugin->getSetting(CONTEXT_SITE, 'pullIndexing')) die(__('plugins.generic.lucene.message.pullIndexingDisabled'));

		// Execute the pull indexing transaction.
		$solrWebService = $this->plugin->getSolrWebService(); /* @var $solrWebService SolrWebService */
		$solrWebService->pullChangedArticles(
			[$this, 'pullIndexingCallback'], SOLR_INDEXING_MAX_BATCHSIZE
		);
	}


	/**
	 * If the "similar documents" feature is enabled then this
	 * handler redirects to a search query that shows documents
	 * similar to the one identified by an article id in the
	 * request.
	 * @param $args array
	 * @param $request Request
	 */
	function similarDocuments($args, $request) {
		$this->validate(null, $request);

		// Retrieve the ID of the article that
		// we want similar documents for.
		$articleId = $request->getUserVar('articleId');

		// Check error conditions.
		// - The "similar documents" feature is not enabled.
		// - We got a non-numeric article ID.
		if (!($this->plugin->getSetting(0, 'simdocs')
			&& is_numeric($articleId))) {
			$request->redirect(null, 'search');
		}

		// Identify "interesting" terms of the
		// given article.
		$solrWebService = $this->plugin->getSolrWebService(); /* @var $solrWebService SolrWebService */
		$searchTerms = $solrWebService->getInterestingTerms($articleId);
		if (empty($searchTerms)) {
			$request->redirect(null, 'search');
		}

		// Redirect to a search query with these
		// terms.
		$searchParams = [
			'query' => implode(' ', $searchTerms),
		];
		$request->redirect(null, 'search', 'search', null, $searchParams);
	}


	//
	// Public methods
	//
	/**
	 * Return XML with index changes to the Solr server
	 * where it will be stored for later processing.
	 *
	 * @param $articleXml string The XML with index changes
	 *  to be transferred to the Solr server.
	 * @param $batchCount integer The number of articles in
	 *  the XML list (i.e. the expected number of documents
	 *  to be indexed).
	 * @param $numDeleted integer The number of articles in
	 *  the XML list that are marked for deletion.
	 *
	 * @return integer The number of articles processed.
	 */
	function pullIndexingCallback($articleXml, $batchCount, $numDeleted) {
		// Flush the XML to the Solr server to make sure it
		// arrives there before we commit our transaction.
		echo $articleXml;
		flush();

		// We assume that when the flush succeeds that
		// all changed documents will eventually be indexed.
		// By implementing a rejection mechanism on the server
		// we make sure this actually happens (or that we at
		// least realize if something goes wrong). If this
		// is not working in practice then we'll have to
		// implement a real application-level two-way handshake.
		return $batchCount;
	}
}
