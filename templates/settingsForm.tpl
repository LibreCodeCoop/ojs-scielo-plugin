<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#ScieloSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="ScieloSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" plugin="ScieloPlugin" category="importexport" verb="save"}">
	{csrf}
	{fbvFormArea id="ScieloSettingsFormArea"}
		{fbvFormSection}
			{fbvElement type="text" id="defaultAuthorEmail" value=$defaultAuthorEmail required="true" label="plugins.importexport.scielo.settings.form.defaultAuthorEmail" maxlength="90" size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="text" id="defaultLocale" value=$defaultLocale required="true" label="plugins.importexport.scielo.settings.form.defaultLocale" maxlength="10" size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
	{/fbvFormArea}
	{fbvFormButtons submitText="common.save"}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
