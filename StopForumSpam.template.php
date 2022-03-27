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
							', $txt['sfs_check_' . $id_check], '
						</td>
						<td class="smalltext">
							', (!empty($check->appears) ? $txt['yes'] : $txt['no']);

			// Some checks will show the last seen, convert it and show it.
			if (!empty($check->lastseen))
				echo '<br>' . $txt['sfs_last_seen'] . ': ' . timeformat(strtotime($check->lastseen));

			if (!empty($check->confidence))
				echo '<br>' . $txt['sfs_confidence'] . ': ' . $check->confidence;

			if (!empty($check->frequency))
				echo '<br>' . $txt['sfs_frequency'] . ': ' . $check->frequency;

			// IP address may be normalized
			if (!empty($check->torexit))
				echo '<br>' . $txt['sfs_torexit'];

			// IP address may be normalized
			if (!empty($check->normalized) && !empty($check->asn))
				echo '<br><a href="', $scripturl, '?action=profile;area=tracking;sa=ip;searchip=' . urlencode($check->normalized) . '">', $txt['trackIP'], '</a>';

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
					<textarea name="reason" rows="4"></textarea>
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