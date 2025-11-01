{**
 * plugins/generic/allowedUploads/settingsForm.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Allowed Uploads plugin settings
 *
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#allowedUploadsSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="allowedUploadsSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="allowedUploadsSettingsFormNotification"}

	<div id="description">{translate key="plugins.generic.allowedUploads.manager.settings.description"}</div>

	{fbvFormArea id="allowedUploadsSettingsFormArea"}
		{fbvFormSection}
			{fbvElement type="text" id="allowedExtensions" name="allowedExtensions" value=$allowedExtensions label="plugins.generic.allowedUploads.manager.settings.allowedExtensions"}
		{/fbvFormSection}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="validateMimeType" name="validateMimeType" checked=$validateMimeType label="plugins.generic.allowedUploads.manager.settings.validateMimeType"}
			<div class="sub_label">{translate key="plugins.generic.allowedUploads.manager.settings.validateMimeType.description"}</div>
		{/fbvFormSection}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="allowEmptyFiles" name="allowEmptyFiles" checked=$allowEmptyFiles label="plugins.generic.allowedUploads.manager.settings.allowEmptyFiles"}
			<div class="sub_label">{translate key="plugins.generic.allowedUploads.manager.settings.allowEmptyFiles.description"}</div>
		{/fbvFormSection}
		{if $errors}
			<div class="pkp_form_error">
					{foreach from=$errors item=error}
							<p>{$error}</p>
					{/foreach}
			</div>
    {/if}
	{/fbvFormArea}

	{fbvFormButtons}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
