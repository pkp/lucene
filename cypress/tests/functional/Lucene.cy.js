/**
 * @file cypress/tests/functional/Lucene.spec.js
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Lucene plugin tests', function() {
	it('Enables and configures the Lucene plugin', function() {
		cy.login('admin', 'admin', 'publicknowledge');

		// We need to create a second context to get site-wide plugin settings to display (FIXME)
		cy.get('.app__navItem').contains('Administration').click();
		cy.get('a').contains('Hosted Journals').click();
		cy.get('div[id=contextGridContainer]').find('a').contains('Create').click();

		// Fill in various details
		cy.wait(1000); // https://github.com/tinymce/tinymce/issues/4355
		cy.get('div[id=editContext]').find('button[label="French"]').click();
		cy.get('input[name="name-fr_CA"]').type('journal2-fr', {delay: 0});
		cy.get('input[name="name-en"]').type('journal2-en', {delay: 0});
		cy.get('input[name=acronym-en]').type('J2', {delay: 0});
		cy.get('#context-contactName-control').type('Ramiro Vaca');
		cy.get('#context-contactEmail-control').type('rvaca@mailinator.com');
		cy.get('#context-country-control').select('Canada');
		cy.get('span').contains('Enable this journal').siblings('input').check();
		cy.get('input[name="supportedLocales"][value="en').check();
		cy.get('input[name="supportedLocales"][value="fr_CA').check();
		cy.get('input[name="primaryLocale"][value="en').check();
		cy.get('input[name=urlPath]').clear().type('journal2', {delay: 0});
		cy.setTinyMceContent('context-description-control-en', 'description-en');
		cy.setTinyMceContent('context-description-control-fr_CA', 'description-fr');
		cy.get('button').contains('Save').click();

		// Wait for it to finish up before moving on
		cy.contains('Settings Wizard', {timeout: 30000});

		// Navigate to the plugins area
		cy.get('a').contains('Administration').click();
		cy.get('a').contains('Site Settings').click();
		cy.get('button[id="plugins-button"]').click();

		// Find and enable the plugin
		cy.get('input[id^="select-cell-luceneplugin-enabled"]').click();
		cy.get('div:contains(\'The plugin "Lucene Search Plugin" has been enabled.\')');
		cy.waitJQuery();
		cy.wait(500); // FIXME: Necessary for the juggling of plugin rows pre/post enable?

		// Go to settings
		cy.get('tr[id*="luceneplugin"] a.show_extras').click();
		cy.get('a[id*="luceneplugin-settings"]').click();
		cy.get('input[id^="searchEndpoint-"]', {timeout: 60000}).clear().type('http://127.0.0.1:8983/solr/ojs/search', {delay: 0});
		cy.get('input[id^="username-"]').clear().type('solr', {delay: 0});
		cy.get('input[id^="password-"]').clear().type('SolrRocks', {delay: 0});
		cy.get('input[id^="instId-"]').clear().type('ojs', {delay: 0});
		cy.get('input[id="autosuggest"]').check();
		cy.get('form[id="luceneSettingsForm"] button[id^="submitFormButton-"]').click();
		cy.waitJQuery();
	});

	it('Rebuilds the search index using the Lucene plugin', function() {
		cy.exec('php tools/rebuildSearchIndex.php')
			.its('stdout')
			.should('contain', 'LucenePlugin');
	});

	it('Tests a search without results using the Lucene plugin', function() {
		cy.visit('/index.php/publicknowledge/search');
		cy.get('#query_input').type('primary', {delay: 0});
		cy.get('button.submit').click();
		cy.get('div').contains('No Results');
	});

	it('Tests a search with results using the Lucene plugin', function() {
		cy.visit('/index.php/publicknowledge/search');
		cy.get('#query_input').type('plasmid', {delay: 0});
		cy.get('button.submit').click();
		cy.get('a').contains('Antimicrobial, heavy metal resistance and plasmid profile of coliforms isolated from nosocomial infections in a hospital in Isfahan, Iran');
	});
})
