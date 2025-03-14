{block name=searchQuery}
	{if $enableAutosuggest}
		<script>

			{capture assign="autocompleteUrl"}{url page="lucene" op="queryAutocomplete" searchField="query"}{/capture}

			document.addEventListener("DOMContentLoaded", function (event) {ldelim}
				$('#queryAutocomplete').find("#query_input").autocomplete(
						{ldelim}
							source: function (request, response) {ldelim}
								$.ajax({ldelim}
									url: ' 	{$autocompleteUrl}',
									data: {ldelim}query: request.term},
									dataType: 'json',
									success: function (jsonData) {ldelim}
										if (jsonData.status == true) {ldelim}
											response(jsonData.content);
											{rdelim
										}
										{rdelim
									}
									{rdelim});
							}
							{rdelim}
				)
				{rdelim
			});
		</script>
	{/if}
		<span id="queryAutocomplete">
			<input type="text" id="query_input" name="query" value="{$query|escape}" class="query" placeholder="{translate|escape key="common.search"}" />
			<input type="hidden" id="query" name="query_hidden" value="{$query|escape}"/>
		</span>

{/block}
{block name=searchQuerySimple}
	{if $enableAutosuggest}
		{capture assign="autocompleteUrl"}{url page="lucene" op="queryAutocomplete" searchField="query"}{/capture}
		<script>

			document.addEventListener("DOMContentLoaded", function (event) {ldelim}
				$('#simpleQueryAutocomplete').find("#simpleQuery_input").autocomplete(
						{ldelim}
							source: function (request, response) {ldelim}
								$.ajax({ldelim}
									url: ' 	{$autocompleteUrl}',
									data: {ldelim}query: request.term},
									dataType: 'json',
									success: function (jsonData) {ldelim}
										if (jsonData.status == true) {ldelim}
											response(jsonData.content);
											{rdelim
										}
										{rdelim
									}
									{rdelim});
							}
							{rdelim}
				)
				{rdelim
			});
		</script>
	{/if}
		<span id="simpleQueryAutocomplete">
			<input type="text" id="simpleQuery_input" name="query" value="{$query|escape}" class="query" placeholder="{translate|escape key="common.search"}" />
			<input type="hidden" id="simpleQuery" name="simpleQuery_hidden" value="{$query|escape}"/>
		</span>

{/block}

{block name=searchAuthors}
	{if $enableAutosuggest}
	{capture assign="autocompleteUrl"}{url page="lucene" op="queryAutocomplete" searchField="authors"}{/capture}
	<script>

		document.addEventListener("DOMContentLoaded", function (event) {ldelim}
			$('#authorsAutocomplete').find("#authors_input").autocomplete(
					{ldelim}
						source: function (request, response) {ldelim}
							$.ajax({ldelim}
								url: ' 	{$autocompleteUrl}',
								data: {ldelim}query: request.term},
								dataType: 'json',
								success: function (jsonData) {ldelim}
									if (jsonData.status == true) {ldelim}
										response(jsonData.content);
										{rdelim
									}
									{rdelim
								}
								{rdelim});
						}
						{rdelim}
			)
			{rdelim
		});
	</script>
	{/if}
	<span id="authorsAutocomplete">
		<input type="text" id="authors_input" name="authors" size="{$size|default:40}" maxlength="255"
			   value="{$authors|escape}" class="textField"/>
		<input type="hidden" id="authors" name="authors_hidden" value="{$query|escape}"/>
	</span>

{/block}
{block name=searchSyntaxInstructions}
	<div>{fieldLabel name="syntaxInstructions" key="plugins.generic.lucene.results.syntaxInstructions"}</div>
{/block}

