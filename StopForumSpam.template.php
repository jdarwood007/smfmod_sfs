<?php

function template_profile_tracksfs()
{
	global $txt, $context, $scripturl;

	if (!empty($context['submission_success']))
		echo '
	<div class="infobox">', $context['submission_success'] , '</div>';
	elseif (!empty($context['submission_failed']))
		echo '
	<div class="errorbox">', $context['submission_failed'], '</div>';

	if (!empty($context['sfs_allow_submit']))
		echo '
	<form action="', $context['sfs_submit_url'], '" method="post">';

	echo '
		<div class="tborder">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['sfs_profile'], '</h3>
			</div>

			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th class="lefttext half_table">', $txt['sfs_check'], '</th>
						<th class="lefttext half_table">', $txt['sfs_result'], '</th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['sfs_checks'] as $id_check => $checkGrp)
	{
		foreach ($checkGrp as $check)
		{
			echo '
					<tr class="windowbg">
						<td title="sfs_check_', $id_check, '">
							', $txt['sfs_check_' . $id_check];

			// Show the IP we tested.
			if ($id_check === 'ip')
				echo ' <span class="smalltext">(', $check['value'], ')</span>';

			echo '
						</td>
						<td class="smalltext">
							', (!empty($check['appears']) ? $txt['yes'] : $txt['no']);

			// Some checks will show the last seen, convert it and show it.
			if (!empty($check['lastseen']))
				echo '<br>' . $txt['sfs_last_seen'] . ': ' . timeformat(strtotime($check['lastseen']));

			if (!empty($check['confidence']))
				echo '<br>' . $txt['sfs_confidence'] . ': ' . $check['confidence'];

			if (!empty($check['frequency']))
				echo '<br>' . $txt['sfs_frequency'] . ': ' . $check['frequency'];

			// IP address may be normalized
			if (!empty($check['torexit']))
				echo '<br>' . $txt['sfs_torexit'];

			// IP address may be normalized
			if (!empty($check['asn']))
				echo '<br><a href="', $scripturl, '?action=profile;area=tracking;sa=ip;searchip=' . urlencode(str_replace('::*', ':*', !empty($check['normalized']) ? $check['normalized'] . '*' : $check['value'])) . '">', $txt['trackIP'], ' ',  (!empty($check['normalized']) ? $check['normalized'] . '*' : $check['value']), '</a>';

			echo '
						</td>
					</tr>';
		}
	}

	echo '
				</tbody>
			</table>';

	if (!empty($context['sfs_allow_submit']))
		echo '
			<br>
			<div>
				<div class="cat_bar">
					<h3 class="catbg">', $txt['sfs_submit_title'], '</h3>
				</div>
				<div class="roundframe">
					<div>', $txt['sfs_evidence'], '</div>
					<textarea name="reason" rows="4" cols="60">', $context['reason'], '</textarea>
					<div class="righttext">
						<input id="notify_submit" type="submit" name="sfs_submit" value="', $txt['sfs_submit'], '" class="button">
						<input id="notify_submit" type="submit" name="sfs_submitban" value="', $txt['sfs_submit_ban'], '" class="button">
					</div>
				</div>
			</div>';

	echo '
		</div><!-- .tborder -->';

	if (!empty($context['sfs_allow_submit']))
	{
		echo '
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

		if (!empty($context['token_check']))
			echo '
		<input type="hidden" name="' . $context[$context['token_check'] . '_token_var'] . '" value="' . $context[$context['token_check'] . '_token'] . '">';

		echo '
	</form>';
	}
}

function template_sfsa_testapi()
{
	global $context, $txt, $scripturl;

	echo '
		<div id="admin_form_wrapper">
			<form id="postForm" action="', $context['sfs_test_url'], '" method="post" accept-charset="', $context['character_set'], '" name="postForm">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['sfs_testapi_title'], '</h3>
				</div>
					<dl class="register_form" id="sfs_testapi_form">
						<dt>
							<strong><label for="username_input">', $txt['username'], ':</label></strong>
						</dt>
						<dd>
							<input type="text" name="username" id="username_input" tabindex="', $context['tabindex']++, '" size="50" maxlength="25" value="', $context['sfs_checks']['username'][0]['value'], '">
						</dd>
						<dt>
							<strong><label for="email_input">', $txt['email_address'], ':</label></strong>
						</dt>
						<dd>
							<input type="email" name="email" id="email_input" tabindex="', $context['tabindex']++, '" size="50" value="', $context['sfs_checks']['email'][0]['value'], '">
						</dd>
						<dt>
							<strong><label for="ip_input">', $txt['ip_address'], ':</label></strong>
						</dt>
						<dd>
							<input type="text" name="ip" id="ip_input" tabindex="', $context['tabindex']++, '" size="100" value="', $context['sfs_checks']['ip'][0]['value'], '">
						</dd>
					</dl>
					<div class="flow_auto">
						<input type="submit" name="send" value="', $txt['sfs_testapi_submit'], '" tabindex="', $context['tabindex']++, '" class="button">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

	if (!empty($context['token_check']))
		echo '
		<input type="hidden" name="' . $context[$context['token_check'] . '_token_var'] . '" value="' . $context[$context['token_check'] . '_token'] . '">';

	echo '
					</div>
				</div><!-- #sfs_testapi_form -->
			</form>
		</div><!-- #admin_form_wrapper -->
	<br class="clear">';

	// Do not show results yet.
	if (empty($context['test_sent']))
		return;

	echo '
		<div class="tborder">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['sfs_testapi_results'], '</h3>
			</div>

			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th class="lefttext">', $txt['sfs_check'], '</th>
						<th class="lefttext">', $txt['sfs_value'], '</th>
						<th class="lefttext">', $txt['sfs_result'], '</th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['sfs_checks'] as $id_check => $checkGrp)
	{
		foreach ($checkGrp as $check)
		{
			echo '
					<tr class="windowbg">
						<td title="sfs_check_', $id_check, '">
							', $txt['sfs_check_' . $id_check], '
						</td>
						<td>
							', $check['value'], '
						</td>
						<td class="smalltext">
							', (!empty($check['appears']) ? $txt['yes'] : $txt['no']);

			// Some checks will show the last seen, convert it and show it.
			if (!empty($check['lastseen']))
				echo '<br>' . $txt['sfs_last_seen'] . ': ' . timeformat(strtotime($check['lastseen']));

			if (!empty($check['confidence']))
				echo '<br>' . $txt['sfs_confidence'] . ': ' . $check['confidence'];

			if (!empty($check['frequency']))
				echo '<br>' . $txt['sfs_frequency'] . ': ' . $check['frequency'];

			// IP address may be normalized
			if (!empty($check['torexit']))
				echo '<br>' . $txt['sfs_torexit'];

			// IP address may be normalized
			if (!empty($check['asn']))
				echo '<br><a href="', $scripturl, '?action=profile;area=tracking;sa=ip;searchip=' . urlencode(str_replace('::*', ':*', !empty($check['normalized']) ? $check['normalized'] . '*' : $check['value'])) . '">', $txt['ip_address'], ' ',  (!empty($check['normalized']) ? $check['normalized'] . '*' : $check['value']), '</a>';

			echo '
						</td>
					</tr>';
		}
	}

	echo '
				</tbody>
			</table>';

}