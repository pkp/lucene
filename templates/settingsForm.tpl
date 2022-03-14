{**
 * templates/settingsForm.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Lucene plugin settings
 *}
<div id="luceneSettings">

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#luceneSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="luceneSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="common/formErrors.tpl"}

	{fbvFormArea id="luceneSettingsFormArea" title="plugins.generic.lucene.settings.solrServerSettings"}
		<div id="description"><p>{translate key="plugins.generic.lucene.settings.description"}</p></div>
		{fbvElement type="text" id="searchEndpoint" value=$searchEndpoint label="plugins.generic.lucene.settings.searchEndpoint" required=true}
		<span class="instruct">{translate key="plugins.generic.lucene.settings.searchEndpointInstructions"}</span>
		{fbvElement type="text" id="username" value=$username label="plugins.generic.lucene.settings.username" required=true}
		<span class="instruct">{translate key="plugins.generic.lucene.settings.usernameInstructions"}</span>
		{fbvElement type="text" id="password" value=$password label="plugins.generic.lucene.settings.password" required=true password=true}
		<span class="instruct">{translate key="plugins.generic.lucene.settings.passwordInstructions"}</span>
		{fbvElement type="text" id="instId" value=$instId label="plugins.generic.lucene.settings.instId" required=true}
		<span class="instruct">{translate key="plugins.generic.lucene.settings.instIdInstructions"}</span>

		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="useSolr7" value="1" checked=$useSolr7 label="plugins.generic.lucene.settings.solr7"}
			<span class="instruct">{translate key="plugins.generic.lucene.settings.useSolr7SettingsInstructions"}</span>
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="luceneSearchFeatures" title="plugins.generic.lucene.settings.searchFeatures"}
		<div id="featureDescription"><p>{translate key="plugins.generic.lucene.settings.featureDescription"}</p></div>
        {fbvElement type="checkbox" id="autosuggest" value="1" checked=$autosuggest label="plugins.generic.lucene.settings.autosuggest"}
		{fbvElement type="select" required=true id="autosuggestType" selected=$autosuggestType from=$autosuggestTypes  size=$fbvStyles.size.MEDIUM inline=true translate=false}
		<p class="instruct">{translate key="plugins.generic.lucene.settings.autosuggestTypeExplanation"}</p>
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="highlighting" value="1" checked=$highlighting label="plugins.generic.lucene.settings.highlighting"}
		{/fbvFormSection}
		{fbvFormSection list=true}
            <label for="faceting">{translate key="plugins.generic.lucene.settings.faceting"}</label><br />

			{fbvElement type="checkbox" id="facetCategoryDiscipline" value="1" checked=$facetCategoryDiscipline label="plugins.generic.lucene.faceting.discipline"}
			{fbvElement type="checkbox" id="facetCategorySubject" value="1" checked=$facetCategorySubject label="plugins.generic.lucene.faceting.subject"}
			{fbvElement type="checkbox" id="facetCategoryType" value="1" checked=$facetCategoryType label="plugins.generic.lucene.faceting.type"}
			{fbvElement type="checkbox" id="facetCategoryCoverage" value="1" checked=$facetCategoryCoverage label="plugins.generic.lucene.faceting.coverage"}
			{fbvElement type="checkbox" id="facetCategoryJournalTitle" value="1" checked=$facetCategoryJournalTitle label="plugins.generic.lucene.faceting.journalTitle"}
			{fbvElement type="checkbox" id="facetCategoryAuthors" value="1" checked=$facetCategoryAuthors label="plugins.generic.lucene.faceting.authors"}
			{fbvElement type="checkbox" id="facetCategoryPublicationDate" value="1" checked=$facetCategoryPublicationDate label="plugins.generic.lucene.faceting.publicationDate"}
		{/fbvFormSection}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="spellcheck" value="1" checked=$spellcheck label="plugins.generic.lucene.settings.spellcheck"}
			{fbvElement type="checkbox" id="simdocs" value="1" checked=$simdocs label="plugins.generic.lucene.settings.simdocs"}
			{fbvElement type="checkbox" id="customRanking" value="1" checked=$customRanking label="plugins.generic.lucene.settings.customRanking"}
			{fbvElement type="checkbox" id="pullIndexing" value="1" checked=$pullIndexing label="plugins.generic.lucene.settings.pullIndexing"}
		{/fbvFormSection}
	{fbvFormSection list=true}
		<label for="orderBy">{translate key="plugins.generic.lucene.settings.orderBy"}</label><br />
		{fbvElement type="checkbox" id="orderByRelevance" value="1" checked=$orderByRelevance label="plugins.generic.lucene.settings.orderBy.relevance"}
		{fbvElement type="checkbox" id="orderByAuthor" value="1" checked=$orderByAuthor label="plugins.generic.lucene.settings.orderBy.author"}
		{fbvElement type="checkbox" id="orderByIssue" value="1" checked=$orderByIssue label="plugins.generic.lucene.settings.orderBy.issue"}
		{fbvElement type="checkbox" id="orderByDate" value="1" checked=$orderByDate label="plugins.generic.lucene.settings.orderBy.date"}
		{fbvElement type="checkbox" id="orderByArticle" value="1" checked=$orderByArticle label="plugins.generic.lucene.settings.orderBy.article"}
		{fbvElement type="checkbox" id="orderByJournal" value="1" checked=$orderByJournal label="plugins.generic.lucene.settings.orderBy.journal"}
	{/fbvFormSection}

	{/fbvFormArea}

	{fbvFormButtons}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

	<a id="indexAdmin"> </a>
	<h3>{translate key="plugins.generic.lucene.settings.indexAdministration"}</h3>
	<script>
		function jumpToIndexAdminAnchor() {ldelim}
			$form = $('#luceneSettings form');
			// Return directly to the rebuild index section.
			$form.attr('action', $form.attr('action') + '#indexAdmin');
			return true;
		{rdelim}
	</script>

	<div class="separator"></div>
	<br />

	<div class="section">
		<label>{fieldLabel name="rebuildIndex" key="plugins.generic.lucene.settings.indexRebuild"}</label>
		<select name="journalToReindex" id="journalToReindex" class="selectMenu">
			{html_options options=$journalsToReindex selected=$journalToReindex}
		</select>
		<script>
			function rebuildIndexClick() {ldelim}
				var confirmation = confirm({translate|json_encode key="plugins.generic.lucene.settings.indexRebuild.confirm"});
				if (confirmation === true) jumpToIndexAdminAnchor();
				return confirmation;
				{rdelim}
		</script>
		<input type="submit" name="rebuildIndex" value="{translate key="plugins.generic.lucene.settings.indexRebuild"}" onclick="rebuildIndexClick()" class="action" /><br/>
		<input type="submit" name="rebuildDictionaries"  value="{translate key="plugins.generic.lucene.settings.dictionaryRebuild"}" onclick="rebuildIndexClick()" class="action" /><br/>
		<br/>
		{if $rebuildIndexMessages}
			<div id="rebuildIndexMessage">
				<strong>{translate key="plugins.generic.lucene.settings.indexRebuildMessages"}</strong><br/>
				{$rebuildIndexMessages|escape|replace:$smarty.const.PHP_EOL:"<br/>"|replace:" ":"&nbsp;"}
			</div>
		{else}
			<span class="instruct">{translate key="plugins.generic.lucene.settings.indexRebuildDescription"}</span><br/>
		{/if}
	</div>

	<div class="section">
		<label>{fieldLabel name="startStopServer" key="plugins.generic.lucene.settings.startStopServer"}</label>
		{if $serverIsAvailable}
			{if $serverIsRunning}
				<input type="submit" name="stopServer" value="{translate key="plugins.generic.lucene.settings.stopServer"}" onclick="jumpToIndexAdminAnchor()" class="action" /><br/>
			{else}
				<input type="submit" name="startServer" value="{translate key="plugins.generic.lucene.settings.startServer"}" onclick="jumpToIndexAdminAnchor()" class="action" /><br/>
			{/if}
		{else}
			<div id="serverNotAvailable">
				{translate key="plugins.generic.lucene.settings.serverNotAvailable" canExecuteResult=$canExecuteResult filesAreWriteableResult=$filesAreWriteableResult solrIsRunningUnderPHPUser=$solrIsRunningUnderPHPUser safemodeOrExecDisabled=$safemodeOrExecDisabled}
			</div>
		{/if}
	</div>
</form>
</div>
