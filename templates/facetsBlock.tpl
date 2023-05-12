{**
 * templates/facetsBlock.tpl
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Faceted search results navigation block.
 *}

<div class="pkp_block block_lucene_facets" id="lucene_facets">
	<span class="title">{translate key="plugins.generic.lucene.faceting.title"}</span>
	<div class="content">
        <ul class="block_lucene_facets_facets accordion">
		    {foreach from=$facets key="facetCategory" item="facetList"}
			{if count($facetList)}
                <li class="block_lucene_facets_facet">
                <a class="lucenetoggle" href="javascript:void(0);">
                    <span class="fa fa-plus"></span>
                    <span>{translate key="plugins.generic.lucene.faceting."|concat:$facetCategory}</span>
                </a>
                        <ul class="inner">
                        {foreach from=$facetList key="facet" item="facetCount"}
                            {if $facetCategory == "publicationDate"}
                                {assign var="dateFromYear" value=$facet}
                                {assign var="dateFromMonth" value=1}
                                {assign var="dateFromDay" value=1}
                                {assign var="dateToYear" value=$facet}
                                {assign var="dateToMOnth" value=12}
                                {assign var="dateToDay" value=31}
                            {else}
                                {if $facetCategory == "journalTitle"}
                                    {assign var=$facetCategory value=$facet}
                                {else}
                                    {* exact phrase search *}
                                    {assign var=$facetCategory value='"'|concat:$facet|concat:'"'}
                                {/if}
                            {/if}
                            <li>
                                <a href="{url query=$query journalTitle=$journalTitle
                                    authors=$authors title=$title abstract=$abstract galleyFullText=$galleyFullText
                                    discipline=$discipline subject=$subject type=$type coverage=$coverage
                                    dateFromMonth=$dateFromMonth dateFromDay=$dateFromDay dateFromYear=$dateFromYear
                                    dateToMonth=$dateToMonth dateToDay=$dateToDay dateToYear=$dateToYear escape=false}">
                                        {$facet|escape}
                                </a> ({$facetCount})
                            </li>
                            {if $facetCategory == "publicationDate"}
                                {assign var="dateFromYear" value=""}
                                {assign var="dateToYear" value=""}
                            {else}
                                {assign var=$facetCategory value=""}
                            {/if}
                        {/foreach}
                        </ul>
                </li>


			{/if}
		{/foreach}
        </ul>
	</div>
</div>
