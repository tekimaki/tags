{strip}
<div class="listing liberty">
	<div class="header">
		<h1>{tr}Tagged Content Listing{/tr}</h1>
	</div>

	<div class="body">
		{form legend="Search Content by Tags"}
			<input type="hidden" name="user_id" value="{$user_id}" />
			<div class="row">
				{formlabel label="Enter Tags" for="search_tags"}
				{forminput}
					<input type="text" name="tags" id="search_tags" value="{$listInfo.tags}" />
					<input type="submit" value="{tr}Search{/tr}" name="search_objects" />
				{/forminput}
			</div>
		{/form}

		{* assign the correct sort columns for user name sorting *}
		{if $gBitSystem->getConfig( 'users_display_name' ) eq 'login'}
			{assign var=isort_author value=creator_user}
			{assign var=isort_editor value=modifier_user}
		{else}
			{assign var=isort_author value=creator_real_name}
			{assign var=isort_editor value=modifier_real_name}
		{/if}

		<table class="data">
			<caption>{tr}Available Content{/tr} <span class="total">[ {$listInfo.total_records} ]</span></caption>
			<thead>
				<tr>
					{if $gBitSystem->isFeatureActive( 'tags_list_id' )}
						<th style="width:2%;">{smartlink ititle="ID" tags=$tagsReq isort=content_id list_page=$listInfo.current_page user_id=$user_id content_type_guid=$contentSelect find=$listInfo.find}</th>
					{/if}
					{if $gBitSystem->isFeatureActive( 'tags_list_title' )}
						<th>{smartlink ititle="Title" tags=$tagsReq isort=title list_page=$listInfo.current_page user_id=$user_id content_type_guid=$contentSelect find=$listInfo.find idefault=1}</th>
					{/if}
					{if $gBitSystem->isFeatureActive( 'tags_list_type' )}
						<th>{smartlink ititle="Content Type" tags=$tagsReq isort=content_type_guid list_page=$listInfo.current_page user_id=$user_id content_type_guid=$contentSelect find=$listInfo.find}</th>
					{/if}
					{if $gBitSystem->isFeatureActive( 'tags_list_author' )}
						<th>{smartlink ititle="Author" tags=$tagsReq isort=$isort_author list_page=$listInfo.current_page user_id=$user_id content_type_guid=$contentSelect find=$listInfo.find}</th>
					{/if}
					{if $gBitSystem->isFeatureActive( 'tags_list_editor' )}
						<th>{smartlink ititle="Most recent editor" tags=$tagsReq isort=$isort_editor list_page=$listInfo.current_page user_id=$user_id content_type_guid=$contentSelect find=$listInfo.find}</th>
					{/if}
					{if $gBitSystem->isFeatureActive( 'tags_list_lastmodif' )}
						<th>{smartlink ititle="Last Modified" tags=$tagsReq isort=last_modified list_page=$listInfo.current_page user_id=$user_id content_type_guid=$content_type_guids find=$listInfo.find}</th>
					{/if}
					{if $gBitSystem->isFeatureActive( 'tags_list_ip' )}
						<th>{smartlink ititle="IP" tags=$tagsReq isort=ip list_page=$listInfo.current_page user_id=$user_id content_type_guid=$contentSelect find=$listInfo.find}</th>				
					{/if}
				</tr>
			</thead>
			<tbody>
				{foreach from=$contentList item=item}
					<tr class="{cycle values='odd,even'}">
						{if $gBitSystem->isFeatureActive( 'tags_list_id' )}
							<td style="text-align:right;">{$item.content_id}</td>
						{/if}
						{if $gBitSystem->isFeatureActive( 'tags_list_title' )}
							<td>{$item.display_link}</td>
						{/if}
						{if $gBitSystem->isFeatureActive( 'tags_list_type' )}
							<td>{assign var=content_type_guid value=`$item.content_type_guid`}{$contentTypes.$content_type_guid}</td>
						{/if}
						{if $gBitSystem->isFeatureActive( 'tags_list_author' )}
							<td>{displayname real_name=$item.creator_real_name user=$item.creator_user}</td>
						{/if}
						{if $gBitSystem->isFeatureActive( 'tags_list_editor' )}
							<td style="text-align:left;">{displayname real_name=$item.modifier_real_name user=$item.modifier_user}</td>
						{/if}
						{if $gBitSystem->isFeatureActive( 'tags_list_lastmodif' )}
							<td style="text-align:center;">{$item.last_modified|bit_short_date}</td>
						{/if}
						{if $gBitSystem->isFeatureActive( 'tags_list_ip' )}
							<td style="text-align:center;">{$item.ip}</td>
						{/if}
					</tr>
				{/foreach}
			</tbody>
		</table>

		{pagination tags=$tagsReq}
	</div><!-- end .body -->
	
	<div class="header">
		<h1>{tr}Tags{/tr}</h1>
	</div>

	<div class="body">
		{if $cloud}
			{include file="bitpackage:tags/tags_cloud.tpl"}
		{else}
			{include file="bitpackage:tags/tags_list.tpl"}
		{/if}
	</div><!-- end .body -->
	
</div><!-- end .liberty -->
{/strip}
