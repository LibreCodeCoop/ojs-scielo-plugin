{strip}
{include file="common/header.tpl" pageTitle="plugins.importexport.scielo.displayName"}
{/strip}

<script type="text/javascript">
	// Attach the JS file tab handler.
	$(function() {ldelim}
		$('#importExportTabs').pkpHandler('$.pkp.controllers.TabHandler');
	{rdelim});
</script>
<div id="importExportTabs">
	<ul>
		<li><a href="#settings-tab">{translate key="plugins.importexport.common.settings"}</a></li>
	</ul>
	<div id="settings-tab">
		{if !$allowExport}
			<div class="pkp_notification" id="ScieloConfigurationErrors">
				{foreach from=$configurationErrors item=configurationError}
					{if $configurationError == $smarty.const.EXPORT_CONFIG_ERROR_SETTINGS}
						{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=ScieloConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.common.error.pluginNotConfigured"|translate}
					{/if}
				{/foreach}
			</div>
		{/if}

		{capture assign=ScieloSettingsGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.plugins.settingsPluginGridHandler" op="manage" plugin="ScieloPlugin" category="importexport" verb="index" escape=false}{/capture}
		{load_url_in_div id="ScieloSettingsGridContainer" url=$ScieloSettingsGridUrl}
	</div>
</div>

{include file="common/footer.tpl"}
