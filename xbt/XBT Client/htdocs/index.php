<?php
	ob_start('ob_gzhandler');

	require_once('common.php');
	require_once('templates.php');

	if (!isset($config['users'][$_SERVER['PHP_AUTH_USER']])
		|| $config['users'][$_SERVER['PHP_AUTH_USER']] != $_SERVER['PHP_AUTH_PW'])
	{
		header('www-authenticate: basic realm="XBT Client"');
		return;
	}
	set_time_limit(5);
	$s = fsockopen($config['client_host'], $config['client_port']);
	if ($s === false)
		die('fsockopen failed');
	if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']))
	{
		$d = file_get_contents($_FILES['file']['tmp_name']);
		send_string($s, sprintf('d6:action12:open torrent7:torrent%d:%se', strlen($d), $d));
		recv_string($s);
	}
	$actions = array
	(
		'close' => 'close torrent',
		'pause' => 'pause torrent',
		'priority_high' => 'set priority',
		'priority_normal' => 'set priority',
		'priority_low' => 'set priority',
		'set_options' => 'set options',
		'start' => 'start torrent',
		'stop' => 'stop torrent',
		'unpause' => 'unpause torrent',
	);
	if (array_key_exists($_REQUEST['a'], $actions))
	{
		$action = $actions[$_REQUEST['a']];
		switch ($_REQUEST['a'])
		{
		case 'set_options':
			send_string($s, sprintf('d6:action%d:%s9:peer porti%de13:seeding ratioi%de12:tracker porti%de11:upload ratei%de12:upload slotsi%dee',
				strlen($action), $action, $_REQUEST['peer_port'], $_REQUEST['seeding_ratio'], $_REQUEST['tracker_port'], $_REQUEST['upload_rate'] << 10, $_REQUEST['upload_slots']));
			break;
		default:
			foreach ($_REQUEST as $name => $value)
			{
				$name = urldecode($name);
				if (strlen($name) != 20 || $value != 'on')
					continue;
				switch ($_REQUEST['a'])
				{
				case 'priority_high':
					send_string($s, sprintf('d6:action%d:%s4:hash20:%s8:priorityi1ee', strlen($action), $action, $name));
					break;
				case 'priority_normal':
					send_string($s, sprintf('d6:action%d:%s4:hash20:%s8:priorityi0ee', strlen($action), $action, $name));
					break;
				case 'priority_low':
					send_string($s, sprintf('d6:action%d:%s4:hash20:%s8:priorityi-1ee', strlen($action), $action, $name));
					break;
				default:
					send_string($s, sprintf('d6:action%d:%s4:hash20:%se', strlen($action), $action, $name));
				}
				recv_string($s);
			}
		}
	}
	if ($_SERVER['REQUEST_METHOD'] != 'GET')
	{
		header('location: ' . $_SERVER['SCRIPT_NAME']);
		exit();
	}
	send_string($s, 'd6:action10:get statuse');
	$v = recv_string($s);
	$v = bdec($v);
	$rows = '';
	foreach ($v['value']['files']['value'] as $info_hash => $file)
	{
		$rows .= template_torrent(array_merge($file['value'], array('info_hash' => array('value' => $info_hash))));
	}
	$torrents = template_torrents(array('rows' => $rows));
	send_string($s, 'd6:action11:get optionse');
	$v = recv_string($s);
	$v = bdec($v);
	$options = template_options($v['value']);
	echo(template_page(array('options' => $options, 'torrents' => $torrents)));
?>