{**
 * templates/additionalSectionMetadata.tpl
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * A template to be included via Templates::Manager::Sections::SectionForm::AdditionalMetadata hook.
 *}
<div class="pkp_helpers_clear"></div>
<div class="section">
    <tr>
        <td class="label">{fieldLabel name="rankingBoost" key="plugins.generic.lucene.sectionForm.rankingBoost"}</td>
        <td class="value">
            <span class="instruct">{translate key="plugins.generic.lucene.sectionForm.rankingBoostInstructions"}</span><br />
            <p><select name="rankingBoostOption" size="1" id="rankingBoostOption" class="selectMenu">
                {html_options options=$rankingBoostOptions selected=$rankingBoostOption}
            </select></p>
        </td>
    </tr>
</div>
