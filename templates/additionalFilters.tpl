{**
 * templates/additionalFilters.tpl
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * A template to be included via Templates::Manager::Sections::SectionForm::AdditionalMetadata hook.
 *}

{if !empty($selectedFacets)}
    <div class="pkp_helpers_clear"></div>
    <h4>{translate key="search.advancedFilters"}</h4>

    {foreach from=$selectedFacets key="facetCategory" item=value}
        {* remember the original value*}
        {assign var = "orgValue" value = $value.facetValue }
        {* Temporarily remove the filter *}
        {assign var = $facetCategory value=""}

    <div class="facetsection">
            <label class="label">{$value.facetDisplayName}</label>
             {$value.facetValue}
        <a href="{url query=$query journalTitle=$journalTitle
        authors=$authors title=$title abstract=$abstract galleyFullText=$galleyFullText
        discipline=$discipline subject=$subject type=$type coverage=$coverage
        dateFromMonth=$dateFromMonth dateFromDay=$dateFromDay dateFromYear=$dateFromYear
        dateToMonth=$dateToMonth dateToDay=$dateToDay dateToYear=$dateToYear escape=false}">
            Löschen
        </a>

        {*restore original filter value*}
        {assign var=$facetCategory value=$orgValue}

    </div>
    {/foreach}
{/if}


