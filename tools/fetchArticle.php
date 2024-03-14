<?php

/**
 * @file tools/fetchLuceneArticle.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class fetchLuceneArticleTool
 * @ingroup plugins_generic_lucene
 *
 * @brief CLI tool to fetch a Lucene document for an article
 */

require(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/tools/bootstrap.inc.php');

import('plugins.generic.lucene.LucenePlugin');
import('plugins.generic.lucene.classes.SolrWebService');
import('plugins.generic.lucene.classes.EmbeddedServer');

class fetchLuceneArticleTool extends CommandLineTool {

	/**
	 * Constructor.
	 * @param $argv array command-line arguments
	 */
	function __construct($argv = array()) {
		parent::__construct($argv);

		if (!sizeof($this->argv)) {
			$this->usage();
			exit(1);
		}

		$this->parameters = $this->argv;
	}

	/**
	 * Print command usage information.
	 */
	function usage() {
		echo _('plugins.generic.lucene.fetchLuceneDoc') . "\n"
			. "Usage:\n"
			. "{$this->scriptName} submission submission_id [...]\n";
	}

	/**
	 * Check citations DOIs
	 */
	function execute() {
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$contextDao = Application::getContextDAO();

		switch(array_shift($this->parameters)) {
			case 'submission':
				foreach($this->parameters as $submissionId) {
					$submission = $submissionDao->getById($submissionId);
					if(!isset($submission)) {
						printf("Error: Skipping $submissionId. Unknown submission.\n");
						continue;
					}
					$plugin = PluginRegistry::loadPlugin('generic', 'lucene');
					$solrWebService = $plugin->getSolrWebService();
					$doc = $solrWebService->getArticleFromIndex($submissionId);
					var_dump($doc);
				}
				break;
			default:
				$this->usage();
				break;
		}
	}
}

$tool = new fetchLuceneArticleTool(isset($argv) ? $argv : array());
$tool->execute();
