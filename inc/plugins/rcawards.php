<?php
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("member_profile_end","rcawards_memprofile");
$plugins->add_hook("member_profile_start","rcawards_memprofile_start");

$plugins->add_hook("admin_user_users_delete_commit","rcawards_delete_user");
$plugins->add_hook('admin_load', 'rcawards_admin');
$plugins->add_hook('admin_config_menu', 'rcawards_admin_config_menu');
$plugins->add_hook('admin_config_action_handler', 'rcawards_admin_config_action_handler');
$plugins->add_hook('admin_config_permissions', 'rcawards_admin_config_permissions');

$plugins->add_hook("postbit","rcawards_postbit");
$plugins->add_hook("misc_start", "rcawards_misc");

function rcawards_info()
{

return array(
	"name"		=> "RC Awards",
	"description"		=> "Give awards to users",
	"website"		=> "http://www.sonicrainboom.com.br",
	"author"		=> "Rainbow Cupcake",
	"authorsite"		=> "http://www.sonicrainboom.com.br",
	"version"		=> "1.0",
	"guid" 			=> "",
	"compatibility"	=> "*"
	);
}

function rcawards_install()
{
	global $db;
	
	if (!$db->table_exists('rc_awards_list'))
	{
		$db->write_query("CREATE TABLE ".TABLE_PREFIX."rc_awards_list (
				`aid` int(10) unsigned NOT NULL auto_increment,
				`url` varchar(255) NOT NULL,
				`title` varchar(255) NOT NULL,
				`desc` text NOT NULL default '',
				`enabled` tinyint(1) NOT NULL default '0',
				`type` tinyint unsigned NOT NULL default '0',
				`order` int unsigned NOT NULL default '0',
				PRIMARY KEY (aid)
				) ENGINE=MyISAM;");
	}
	
	if(!$db->table_exists('rc_awards'))
	{
		$db->query("CREATE TABLE ".TABLE_PREFIX.$prefix."rc_awards (
				auid int unsigned NOT NULL auto_increment,
  				uid int unsigned NOT NULL default '0',
				guid int unsigned NOT NULL default '0',
				gip varchar(30) NOT NULL default '',
  				aid int unsigned NOT NULL default '0',
				reason text NOT NULL DEFAULT '',
  				dateline bigint(30) NOT NULL default '0',
				priority smallint(5) unsigned NOT NULL default '0',
  				PRIMARY KEY (auid)
				) ENGINE=MyISAM;");
	}
}

function rcawards_activate()
{
	// Things to do when the plugin is activated
}

function rcawards_is_installed()
{
	global $db;
	if($db->table_exists('rc_awards_list') && $db->table_exists('rc_awards'))
	{
		return true;
	}
	return false;
}

function rcawards_deactivate()
{
	// Things to do when the plugin is deactivated
	// ... things like template changes, language changes etc....
}

function rcawards_uninstall()
{
	global $db;

	$db->drop_table('rc_awards');
	$db->drop_table('rc_awards_list');
}

function rcawards_give_award($uid, $guid, $gip, $aid, $reason)
{
	global $db, $lang;
	
	$lang->load("rcawards");

	$insert = array(
		'aid' => $db->escape_string($aid),
		'reason'   => $db->escape_string($reason),
		'gip'  => $db->escape_string($gip),
		'guid' => $db->escape_string($guid),
		'dateline' => TIME_NOW,
		'uid' => $db->escape_string($uid)
	);
	
	$query = $db->query("
					SELECT aid
					FROM ".TABLE_PREFIX."rc_awards_list
					WHERE aid='{$insert['aid']}'
					LIMIT 1
					");
	
	if (!$db->fetch_array($query))
	{
		return false;
	}
	
	$query = $db->query("
					SELECT uid
					FROM ".TABLE_PREFIX."users
					WHERE uid='{$insert['uid']}'
					LIMIT 1
					");
	
	if (!$db->fetch_array($query))
	{
		return false;
	}
	
	$db->insert_query("rc_awards", $insert);
	
	return true;
}

function rcawards_postbit($post)
{
	global $db, $pids, $awards_cache;
	global $mybb;
	
	$post['rcawards'] = "";
	
	if (!is_array($awards_cache))
	{
		$awards_cache = array();
		
		if($pids != '')
		{
			$rc_pids = 'p.'.trim($pids);
		}
		else
		{
			$rc_pids = "p.pid='".$post['pid']."'";
		}
		
		$query = $db->query("
			SELECT awards.auid, awards.uid, awards.aid, awards.reason, awards.dateline, awards.priority, alist.url, alist.title, alist.enabled, alist.desc
			FROM (SELECT aw.auid, aw.uid, aw.aid, aw.reason, aw.dateline, aw.priority FROM ".TABLE_PREFIX."rc_awards AS aw INNER JOIN (SELECT DISTINCT p.uid
			FROM ".TABLE_PREFIX."posts AS p
			WHERE ".$rc_pids.") AS uids ON aw.uid=uids.uid) AS awards
			INNER JOIN ".TABLE_PREFIX."rc_awards_list AS alist ON awards.aid=alist.aid ORDER BY awards.priority DESC, awards.dateline DESC");
			
		$post['rcawards'] .= "<script>var awards = new Array();\n";
		
		while($t = $db->fetch_array($query))
		{
			if ($t['enabled'] == '1')
			{
				if (!isset($awards_cache[$t['uid']]))
				{
					$post['rcawards'] .= "awards[{$t['uid']}] = new Array();\n";
				}
				$awards_cache[$t['uid']][] = $t;
				$post['rcawards'] .= "awards[{$t['uid']}].push([\"{$t['url']}\", \"{$t['title']}\", \"{$t['reason']}\", \"{$t['aid']}\", \"{$t['auid']}\"]);\n";
			}
		}
		
		$post['rcawards'] .= "</script>";
	}
	
	$post['rcawards'] .= "<div id=\"awards_{$post['pid']}\" style=\"width:100%;white-space:normal;\"></div><script>awards_table({$post['uid']}, {$post['pid']}, 0);</script>";
	
	return $post;
}

function rcawards_memprofile_start()
{
	global $lang;
	
	$lang->load("rcawards");
}

function rcawards_memprofile()
{
	global $db, $mybb, $lang, $memprofile, $uid;
	
	$query = $db->query("SELECT COUNT(auid) AS count 
					FROM ".TABLE_PREFIX."rc_awards AS awards 
					INNER JOIN ".TABLE_PREFIX."rc_awards_list AS aw
						ON aw.aid=awards.aid
					WHERE uid='{$uid}' AND aw.enabled='1'");
	$count = $db->fetch_field($query, "count");
	
	$memprofile['awardslink'] = "{$count} [<a href='misc.php?action=userawards&uid={$uid}'>{$lang->rc_details}</a>]";
	$memprofile['awardscount'] = $count;
	$memprofile['awardgivelink'] = "<a href=\"misc.php?action=addawardpanel&uid={$uid}\">{$lang->rc_give_award}</a>";
}

function rcawards_misc()
{
	global $mybb, $lang, $db, $theme, $headerinclude, $header, $footer, $multipage, $alt_bg;
	
	$lang->load("rcawards");
	
	//Lista de Awards de usuario
	if ($mybb->input['action'] == "userawards")
	{
		if($mybb->usergroup['canviewprofiles'] == 0)
		{
			error_no_permission();
		}
		
		$uid = intval($mybb->input['uid']);
		$user = get_user($uid);
		if(!$user['uid'])
		{
			error($lang->invalid_user);
		}
		
		$lang->nav_profile = $lang->sprintf($lang->nav_profile, $user['username']);
		$lang->rc_awards_for = $lang->sprintf($lang->rc_awards_for, $user['username']);

		add_breadcrumb($lang->nav_profile, get_profile_link($user['uid']));
		add_breadcrumb($lang->rc_nav_userawards);
		
		if(!$mybb->settings['membersperpage'])
		{
			$mybb->settings['membersperpage'] = 20;
		}

		$perpage = intval($mybb->input['perpage']);
		if(!$perpage || $perpage <= 0)
		{
			$perpage = intval($mybb->settings['membersperpage']);
		}

		$query = $db->query("SELECT COUNT(auid) AS count 
						FROM ".TABLE_PREFIX."rc_awards AS awards 
						INNER JOIN ".TABLE_PREFIX."rc_awards_list AS aw
							ON aw.aid=awards.aid
						WHERE uid='{$user['uid']}' AND aw.enabled='1'");
		$result = $db->fetch_field($query, "count");
		
		if($mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}

		$pages = $result / $perpage;
		$pages = ceil($pages);

		if($mybb->input['page'] == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}
		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}
		
		$multipage = multipage($result, $perpage, $page, "misc.php?action=userawards&uid={$user['uid']}");
		
		$query = $db->query("
			SELECT aw.auid, aw.aid, awl.url, awl.desc, awl.title, aw.reason, aw.dateline, aw.guid, aw.priority, aw.gip
			FROM ".TABLE_PREFIX."rc_awards AS aw
			INNER JOIN ".TABLE_PREFIX."rc_awards_list AS awl
			ON awl.aid=aw.aid
			WHERE uid='{$user['uid']}' AND awl.enabled='1'
			ORDER BY dateline desc
			LIMIT {$start}, {$perpage}
		");
		
		$users = array();
		
		while($row = $db->fetch_array($query))
		{
			$alt_bg = alt_trow();
			$dateline = my_date($mybb->settings['dateformat'], $row['dateline'])."<br />".my_date($mybb->settings['timeformat'], $row['dateline']);
			
			
			
			// Display IP address and admin notation of username changes if user is a mod/admin
			if($mybb->usergroup['canmodcp'] == 1)
			{
				if (!$users[$row['guid']])
				{	
					$users[$row['guid']] = get_user(intval($row['guid']));
					$users[$row['guid']]['formatedname'] = format_name($users[$row['guid']]['username'], $users[$row['guid']]['usergroup'], $users[$row['guid']]['displaygroup']);
					$users[$row['guid']]['profilelink'] = build_profile_link($users[$row['guid']]['formatedname'], $row['guid']);
				}
				
				$ipaddressbit = "<td class='{$alt_bg}' width=\"5%\" align='center'>{$users[$row['guid']]['profilelink']}</td>
<td class=\"{$alt_bg}\" align=\"center\">{$row['gip']}</td>
<td class=\"{$alt_bg}\" align=\"center\"><a href=\"javascript:void(0)\" onclick=\"deleteAward({$row['auid']})\" href=\"misc.php?action=removeaward&auid={$row['auid']}\"><img src=\"images/icons/delete.png\" alt=\"R\"></img></a></td>";
			}

			eval("\$usernamehistory_bit .= \"".'<tr>
<td class=\'{$alt_bg}\' align=\'center\'><b><a href=\'misc.php?action=awardsgiven&aid={$row[\'aid\']}\'>{$row[\'title\']}</a></b></td>
<td class=\'{$alt_bg}\' align=\'center\'>{$row[\'reason\']}</td>
<td class=\'{$alt_bg}\' align=\'center\'><img src=\'{$row[\'url\']}\' title=\'{$row[\'title\']}\'></img></td>
<td class=\'{$alt_bg}\' align=\'center\'>{$dateline}</td>
{$ipaddressbit}
</tr>'."\";");
		}
		
		if(!$usernamehistory_bit)
		{
			eval("\$usernamehistory_bit = \"".'<tr>
<td class=\'trow1\' colspan=\'7\' align=\'center\'>{$lang->rc_award_noawards}</td>
</tr>'."\";");
		}

		// Display IP address of scores if user is a mod/admin
		if($mybb->usergroup['canmodcp'] == 1)
		{
			$ipaddresscol = "<td class='tcat' width='15%' align='center'><span class='smalltext'><strong>{$lang->rc_award_given_by}</strong></span></td>
<td class=\"tcat\" width=\"7%\" align=\"center\"><span class=\"smalltext\"><strong>{$lang->ip_address}</strong></span></td>
<td class='tcat' width='2%' align='center'><span class='smalltext'> </span></td>";
			$colspan = "7";
		}
		else
		{
			$colspan = "4";
		}

		eval("\$usernamehistory = \"".'<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->rc_awards_for}</title>
{$headerinclude}
</head>
<body>
{$header}
{$multipage}
<table border=\'0\' cellspacing=\'{$theme[\'borderwidth\']}\' cellpadding=\'{$theme[\'tablespace\']}\' class=\'tborder\'>
<tr>
<td class=\'thead\' colspan=\'{$colspan}\'><strong>{$lang->rc_awards_for}</strong></td>
</tr>
<tr>
<td class=\'tcat\' width=\'15%\' align=\'center\'><span class=\'smalltext\'><strong>{$lang->rc_award_title}</strong></span></td>
<td class=\'tcat\' align=\'center\'><span class=\'smalltext\'><strong>{$lang->rc_award_reason}</strong></span></td>
<td class=\'tcat\' width=\'15%\' align=\'center\'><span class=\'smalltext\'><strong>{$lang->rc_award_image}</strong></span></td>
<td class=\'tcat\' width=\'15%\' align=\'center\'><span class=\'smalltext\'><strong>{$lang->rc_award_given_time}</strong></span></td>
{$ipaddresscol}
</tr>
{$usernamehistory_bit}
</tr>
</table>
{$multipage}
{$footer}
<script>
function deleteAward(auid)
{
var r=confirm(\"{$lang->rc_delete_award}\");
if (r==true)
  {
  document.location.href = \"misc.php?action=removeaward&auid=\"+auid+\"&my_post_key={$mybb->post_code}\";
  }
}
</script>
</body>
</html>'."\";");
		output_page($usernamehistory);
	}
	
	// Lista de awards
	else if ($mybb->input['action'] == "awardslist")
	{
		add_breadcrumb($lang->rc_nav_awardslist);
		
		if(!$mybb->settings['membersperpage'])
		{
			$mybb->settings['membersperpage'] = 20;
		}

		$perpage = intval($mybb->input['perpage']);
		if(!$perpage || $perpage <= 0)
		{
			$perpage = intval($mybb->settings['membersperpage']);
		}

		$query = $db->query("SELECT COUNT(aid) AS count 
						FROM ".TABLE_PREFIX."rc_awards_list
						WHERE enabled='1'");
		$result = $db->fetch_field($query, "count");
		
		if($mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}

		$pages = $result / $perpage;
		$pages = ceil($pages);

		if($mybb->input['page'] == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}
		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}
		
		$multipage = multipage($result, $perpage, $page, "misc.php?action=userawards&aid={$aid}");
		
		$query = $db->query("
			SELECT *
			FROM ".TABLE_PREFIX."rc_awards_list AS awl
			WHERE awl.enabled='1'
			ORDER BY title asc
			LIMIT {$start}, {$perpage}
		");
		
		while($row = $db->fetch_array($query))
		{
			$alt_bg = alt_trow();

			eval("\$usernamehistory_bit .= \"".'<tr>
<td class=\'{$alt_bg}\' align=\'center\'><b><a href=\'misc.php?action=awardsgiven&aid={$row[\'aid\']}\'>{$row[\'title\']}</a></b></td>
<td class=\'{$alt_bg}\' align=\'center\'>{$row[\'desc\']}</td>
<td class=\'{$alt_bg}\' align=\'center\'><img src=\'{$row[\'url\']}\' title=\'{$row[\'title\']}\'></img></td>
</tr>'."\";");
		}
		
		if(!$usernamehistory_bit)
		{
			eval("\$usernamehistory_bit = \"".'<tr>
<td class=\'trow1\' colspan=\'3\' align=\'center\'>{$lang->rc_noawards}</td>
</tr>'."\";");
		}

		$colspan = "3";

		eval("\$usernamehistory = \"".'<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->rc_nav_awardsgiven}</title>
{$headerinclude}
</head>
<body>
{$header}
{$multipage}
<table border=\'0\' cellspacing=\'{$theme[\'borderwidth\']}\' cellpadding=\'{$theme[\'tablespace\']}\' class=\'tborder\'>
<tr>
<td class=\'thead\' colspan=\'{$colspan}\'><strong>{$lang->rc_nav_awardsgiven}</strong></td>
</tr>
<tr>
<td class=\'tcat\' width=\'20%\' align=\'center\'><span class=\'smalltext\'><strong>{$lang->rc_award_title}</strong></span></td>
<td class=\'tcat\' align=\'center\'><span class=\'smalltext\'><strong>{$lang->rc_award_desc}</strong></span></td>
<td class=\'tcat\' width=\'20%\' align=\'center\'><span class=\'smalltext\'><strong>{$lang->rc_award_image}</strong></span></td>
{$ipaddresscol}
</tr>
{$usernamehistory_bit}
</tr>
</table>
{$multipage}
{$footer}
</body>
</html>'."\";");
		output_page($usernamehistory);
	}
	
	else if ($mybb->input['action'] == "awardsgiven")
	{
		$aid = intval($mybb->input['aid']);
		if (!$aid)
		{
			error($lang->rc_invalidaward);
		}

		add_breadcrumb($lang->rc_nav_awardslist, "misc.php?action=awardslist");
		add_breadcrumb($lang->rc_nav_awardsgiven);
		
		if(!$mybb->settings['membersperpage'])
		{
			$mybb->settings['membersperpage'] = 20;
		}

		$perpage = intval($mybb->input['perpage']);
		if(!$perpage || $perpage <= 0)
		{
			$perpage = intval($mybb->settings['membersperpage']);
		}

		$query = $db->query("SELECT COUNT(auid) AS count 
						FROM ".TABLE_PREFIX."rc_awards AS awards 
						INNER JOIN ".TABLE_PREFIX."rc_awards_list AS aw
							ON aw.aid=awards.aid
						WHERE awards.aid='{$aid}' AND aw.enabled='1'");
		$result = $db->fetch_field($query, "count");
		
		if($mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}

		$pages = $result / $perpage;
		$pages = ceil($pages);

		if($mybb->input['page'] == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}
		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}
		
		$multipage = multipage($result, $perpage, $page, "misc.php?action=awardsgiven&aid={$aid}");
		
		$query = $db->query("
			SELECT aw.auid, aw.aid, awl.url, awl.desc, awl.title, aw.reason, aw.dateline, aw.guid, aw.priority, aw.gip, aw.uid
			FROM ".TABLE_PREFIX."rc_awards AS aw
			INNER JOIN ".TABLE_PREFIX."rc_awards_list AS awl
			ON awl.aid=aw.aid
			WHERE aw.aid='{$aid}' AND awl.enabled='1'
			ORDER BY dateline desc
			LIMIT {$start}, {$perpage}
		");
		
		$users = array();
		
		while($row = $db->fetch_array($query))
		{
			$alt_bg = alt_trow();
			$dateline = my_date($mybb->settings['dateformat'], $row['dateline'])."<br />".my_date($mybb->settings['timeformat'], $row['dateline']);
			
			
			
			if (!$users[$row['uid']])
			{	
				$users[$row['uid']] = get_user(intval($row['uid']));
				$users[$row['uid']]['formatedname'] = format_name($users[$row['uid']]['username'], $users[$row['uid']]['usergroup'], $users[$row['uid']]['displaygroup']);
				$users[$row['uid']]['profilelink'] = build_profile_link($users[$row['uid']]['formatedname'], $row['uid']);
			}
			
			// Display IP address and admin notation of username changes if user is a mod/admin
			if($mybb->usergroup['canmodcp'] == 1)
			{
				if (!$users[$row['guid']])
				{	
					$users[$row['guid']] = get_user(intval($row['guid']));
					$users[$row['guid']]['formatedname'] = format_name($users[$row['guid']]['username'], $users[$row['guid']]['usergroup'], $users[$row['guid']]['displaygroup']);
					$users[$row['guid']]['profilelink'] = build_profile_link($users[$row['guid']]['formatedname'], $row['guid']);
				}
				
				$ipaddressbit = "<td class='{$alt_bg}' align='center'>{$users[$row['guid']]['profilelink']}</td>
<td class=\"{$alt_bg}\" align=\"center\">{$row['gip']}</td>";
			}

			eval("\$usernamehistory_bit .= \"".'<tr>
<td class=\'{$alt_bg}\' align=\'center\'>{$users[$row[\'uid\']][\'profilelink\']}</td>
<td class=\'{$alt_bg}\' align=\'center\'>{$row[\'reason\']}</td>
<td class=\'{$alt_bg}\' align=\'center\'><img src=\'{$row[\'url\']}\' title=\'{$row[\'title\']}\'></img></td>
<td class=\'{$alt_bg}\' align=\'center\'>{$dateline}</td>
{$ipaddressbit}
</tr>'."\";");
		}
		
		if(!$usernamehistory_bit)
		{
			eval("\$usernamehistory_bit = \"".'<tr>
<td class=\'trow1\' colspan=\'6\' align=\'center\'>{$lang->rc_award_noawardsgiven}</td>
</tr>'."\";");
		}

		// Display IP address of scores if user is a mod/admin
		if($mybb->usergroup['canmodcp'] == 1)
		{
			
			$ipaddresscol = "<td class='tcat' width='15%' align='center'><span class='smalltext'><strong>{$lang->rc_award_given_by}</strong></span></td>
<td class=\"tcat\" width=\"7%\" align=\"center\"><span class=\"smalltext\"><strong>{$lang->ip_address}</strong></span></td>";
			$colspan = "6";
		}
		else
		{
			$colspan = "4";
		}

		eval("\$usernamehistory = \"".'<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->rc_nav_awardsgiven}</title>
{$headerinclude}
</head>
<body>
{$header}
{$multipage}
<table border=\'0\' cellspacing=\'{$theme[\'borderwidth\']}\' cellpadding=\'{$theme[\'tablespace\']}\' class=\'tborder\'>
<tr>
<td class=\'thead\' colspan=\'{$colspan}\'><strong>{$lang->rc_nav_awardsgiven}</strong></td>
</tr>
<tr>
<td class=\'tcat\' width=\'15%\' align=\'center\'><span class=\'smalltext\'><strong>{$lang->rc_award_given_to}</strong></span></td>
<td class=\'tcat\' align=\'center\'><span class=\'smalltext\'><strong>{$lang->rc_award_reason}</strong></span></td>
<td class=\'tcat\' width=\'15%\' align=\'center\'><span class=\'smalltext\'><strong>{$lang->rc_award_image}</strong></span></td>
<td class=\'tcat\' width=\'15%\' align=\'center\'><span class=\'smalltext\'><strong>{$lang->rc_award_given_time}</strong></span></td>
{$ipaddresscol}
</tr>
{$usernamehistory_bit}
</tr>
</table>
{$multipage}
{$footer}
</body>
</html>'."\";");
		output_page($usernamehistory);
	}
	
	else if($mybb->input['action'] == 'addawardpanel')
	{
		if($mybb->usergroup['canmodcp'] == 0)
		{
			error_no_permission();
		}
		
		$uid = intval($mybb->input['uid']);
		$user = get_user($uid);
		if(!$user['uid'])
		{
			error($lang->invalid_user);
		}
		
		$user['formatedname'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
		$user['profilelink'] = build_profile_link($user['formatedname'], $uid);
		
		$lang->nav_profile = $lang->sprintf($lang->nav_profile, $user['username']);
		$lang->rc_awards_for = $lang->sprintf($lang->rc_awards_for, $user['username']);

		add_breadcrumb($lang->nav_profile, get_profile_link($user['uid']));
		add_breadcrumb($lang->rc_nav_giveawardpanel);
		
		$query = $db->query("
			SELECT *
			FROM ".TABLE_PREFIX."rc_awards_list AS awl
			WHERE awl.enabled='1'
			ORDER BY title asc
		");
		
		while($row = $db->fetch_array($query))
		{
			$jarray .= "awards_desc[{$row['aid']}] = \"{$row['desc']}\";\n";
			$jarray .= "awards_url[{$row['aid']}] = \"{$row['url']}\";\n";
			eval("\$availableaward_bit .= \"".'<option value=\'{$row[\'aid\']}\'>{$row[\'title\']}</option>\'>'."\";");
		}
		
		eval("\$giveawardpanel = \"".'<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->rc_nav_giveawardpanel}</title>
{$headerinclude}
</head>
<body>
{$header}
<form action=\"misc.php?action=addawardcommit\" method=\"post\" name=\"input\">
<table border=\'0\' cellspacing=\'{$theme[\'borderwidth\']}\' cellpadding=\'{$theme[\'tablespace\']}\' class=\'tborder\'>
<tr>
<td class=\'thead\' colspan=\'2\'><strong>{$lang->rc_nav_giveawardpanel}</strong></td>
</tr>
<tr>
<td class=\'tcat\' colspan=\'2\'><span class=\'smalltext\'><strong>{$lang->rc_giveaward_desc}</strong></span></td>
</tr>
<tr>
<td class=\'trow1\' valign=\'top\' width=\'20%\'><b>{$lang->rc_award_given_to}:</b></td>
<td class=\'trow1\' valign=\'top\' >{$user[\'profilelink\']}</td>
</tr>
<tr>
<td class=\'trow2\' valign=\'top\' width=\'20%\'><b>{$lang->rc_award_image}:</b></td>
<td class=\'trow2\' valign=\'top\' ><select name=\'aid\' onchange=\'updateDesc()\' id=\"award_select\">{$availableaward_bit}</select></td>
</tr>
<tr>
<td class=\'trow1\' valign=\'top\' width=\'20%\'><b>{$lang->rc_image}:</b></td>
<td class=\'trow1\' valign=\'top\' ><span id=\'award_img\'></span></td>
</tr>
<tr>
<td class=\'trow2\' valign=\'top\' width=\'20%\'><b>{$lang->rc_award_desc}:</b></td>
<td class=\'trow2\' valign=\'top\' ><span id=\'award_desc\'></span></td>
</tr>
<tr>
<td class=\'trow1\' valign=\'top\' width=\'20%\'><b>{$lang->rc_award_reason}:</b></td>
<td class=\'trow1\' valign=\'top\' ><textarea name=\"reason\" cols=\"60\" rows=\"4\"></textarea></td>
</tr>
</table>
<script>
var awards_url=new Array();
var awards_desc=new Array(); 
{$jarray}

function updateDesc() {
var d = document.getElementById(\"award_select\").value;
document.getElementById(\"award_desc\").innerHTML = awards_desc[d];
document.getElementById(\"award_img\").innerHTML = \"<img src=\'\"+awards_url[d]+\"\'></img>\";
}

updateDesc()
</script>
<br />
<div align=\"center\"><input type=\"submit\" class=\"button\" value=\"{$lang->rc_nav_giveawardpanel}\" /></div>
<input type=\"hidden\" name=\"uid\" value=\"$uid\" />
<input type=\"hidden\" name=\"my_post_key\" value=\"{$mybb->post_code}\" />
</form>
{$footer}
</body>
</html>'."\";");
		
		output_page($giveawardpanel);
	}
	
	else if($mybb->input['action'] == 'addawardcommit')
	{
		global $session;
		
		if($mybb->usergroup['canmodcp'] == 0)
		{
			error_no_permission();
		}
		
		if(!isset($mybb->input['my_post_key']) || $mybb->post_code != $mybb->input['my_post_key'])
		{
			error($lang->rc_post_key_invalid);
		}
		
		$insert = array(
			'aid' => $db->escape_string($mybb->input['aid']),
			'reason'   => $db->escape_string($mybb->input['reason']),
			'gip'  => $db->escape_string($session->ipaddress),
			'guid' => $db->escape_string($mybb->user['uid']),
			'dateline' => TIME_NOW,
			'uid' => $db->escape_string($mybb->input['uid'])
		);
		
		$query = $db->query("
						SELECT aid
						FROM ".TABLE_PREFIX."rc_awards_list
						WHERE aid='{$insert['aid']}'
						LIMIT 1
						");
		
		if (!$db->fetch_array($query))
		{
			error($lang->rc_invalidaward);
		}
		
		$query = $db->query("
						SELECT uid
						FROM ".TABLE_PREFIX."users
						WHERE uid='{$insert['uid']}'
						LIMIT 1
						");
		
		if (!$db->fetch_array($query))
		{
			error($lang->invalid_user);
		}
		
		$db->insert_query("rc_awards", $insert);
		
		redirect("member.php?action=profile&uid={$insert['uid']}");
	}
	
	else if($mybb->input['action'] == 'removeaward')
	{
		if($mybb->usergroup['canmodcp'] == 0)
		{
			error_no_permission();
		}
		
		$auid = intval($mybb->input['auid']);
		if (!$auid)
		{
			error($lang->rc_invalidaward);
		}
		
		if(!isset($mybb->input['my_post_key']) || $mybb->post_code != $mybb->input['my_post_key'])
		{
			error($lang->rc_post_key_invalid);
		}
		
		$query = $db->query("
						SELECT *
						FROM ".TABLE_PREFIX."rc_awards aw
						LEFT JOIN ".TABLE_PREFIX."rc_awards_list awl
						ON aw.aid=awl.aid
						WHERE auid='{$db->escape_string($auid)}'
						LIMIT 1
						");
		
		if (!($award = $db->fetch_array($query)))
		{
			error($lang->rc_invalidaward);
		}
		
		$uid = intval($award['uid']);
		$user = get_user($uid);
		
		$lang->rc_removed_award = $lang->sprintf($lang->rc_removed_award, $award['title']);
		
		log_moderator_action(array("uid" => $user['uid'], "username" => $user['username']), $lang->rc_removed_award);
		
		$db->delete_query('rc_awards', "auid='{$db->escape_string($auid)}'");
		
		redirect("misc.php?action=userawards&uid={$award['uid']}");
	}
}

function rcawards_delete_user()
{
}

function rcawards_admin_config_menu(&$sub_menu)
{
	global $lang;
	$lang->load('rcawards');

	$sub_menu[] = array('id' => 'rcawards', 'title' => $lang->rcawards, 'link' => 'index.php?module=config/rcawards');
}

function rcawards_admin_config_action_handler(&$actions)
{
	$actions['rcawards'] = array('active' => 'rcawards', 'file' => 'rcawards');
}



function rcawards_admin_config_permissions(&$admin_permissions)
{
	global $lang;
	$admin_permissions['rcawards'] = $lang->can_manage_rcawards;
}

function rcawards_admin()
{
	global $db, $lang, $mybb, $page, $run_module, $action_file;

	if($run_module == 'config' && $action_file == 'rcawards')
	{
		$page->add_breadcrumb_item($lang->rcawards, 'index.php?module=config/rcawards');

		if($mybb->input['action'] == 'add')
		{
			if($mybb->request_method == 'post')
			{
				if(!trim($mybb->input['title']))
				{
					$errors[] = $lang->rc_error_no_title;
				}

				if(!$errors)
				{
					$new_award = array(
						'title' => $db->escape_string($mybb->input['title']),
						'desc'   => $db->escape_string($mybb->input['desc']),
						'url'  => $db->escape_string($mybb->input['url']),
						'enabled' => intval($mybb->input['enabled'])
					);

					$mid = $db->insert_query('rc_awards_list', $new_award);

					log_admin_action($mid);

					flash_message($lang->rc_success_award_saved, 'success');
					admin_redirect('index.php?module=config/rcawards');
				}
			}

			$page->add_breadcrumb_item($lang->rc_add_award);
			$page->output_header($lang->rcawards.' - '.$lang->rc_add_award);

			$sub_tabs['manage_messages'] = array(
				'title' => $lang->rcawards,
				'link'  => 'index.php?module=config/rcawards',
			);

			$sub_tabs['rc_add_award'] = array(
				'title'       => $lang->rc_add_award,
				'link'        => 'index.php?module=config/rcawards&amp;action=add',
				'description' => $lang->rc_add_award_desc
			);

			$page->output_nav_tabs($sub_tabs, 'rc_add_award');

			if($errors)
			{
				$page->output_inline_error($errors);
			}

			$form = new Form('index.php?module=config/rcawards&amp;action=add', 'post', 'add');
			$form_container = new FormContainer($lang->rc_add_award);
			$form_container->output_row($lang->rc_admin_title.' <em>*</em>', $lang->rc_admin_title_desc, $form->generate_text_box('title', $mybb->input['title']));
			$form_container->output_row($lang->rc_admin_desc, $lang->rc_admin_desc_desc, $form->generate_text_area('desc', $mybb->input['desc']));
			$form_container->output_row($lang->rc_admin_url.' <em>*</em>', $lang->rc_admin_url_desc, $form->generate_text_box('url', $mybb->input['url']));
			$form_container->output_row($lang->rc_admin_enabled.' <em>*</em>', $lang->rc_admin_enabled_desc, $form->generate_yes_no_radio('enabled', $mybb->input['enabled'], true));
			$form_container->end();

			$buttons[] = $form->generate_submit_button($lang->rc_save_award);

			$form->output_submit_wrapper($buttons);

			$form->end();

			$page->output_footer();
		}

		if($mybb->input['action'] == 'edit')
		{
			$query = $db->simple_select('rc_awards_list', '*', "aid='".intval($mybb->input['aid'])."'");
			$message = $db->fetch_array($query);

			if(!$message['aid'])
			{
				flash_message($lang->rc_error_invalid_award, 'error');
				admin_redirect('index.php?module=config/rcawards');
			}

			if($mybb->request_method == 'post')
			{
				if(!trim($mybb->input['title']))
				{
					$errors[] = $lang->rc_error_no_title;
				}

				if(!$errors)
				{
					$award = array(
						'title' => $db->escape_string($mybb->input['title']),
						'desc'   => $db->escape_string($mybb->input['desc']),
						'url'  => $db->escape_string($mybb->input['url']),
						'enabled' => intval($mybb->input['enabled'])
					);

					$db->update_query('rc_awards_list', $award, "aid='".intval($mybb->input['aid'])."'");

					log_admin_action(intval($mybb->input['aid']));

					flash_message($lang->rc_success_award_saved, 'success');
					admin_redirect('index.php?module=config/rcawards');
				}
			}

			$page->add_breadcrumb_item($lang->rc_admin_edit_award);
			$page->output_header($lang->rcawards.' - '.$lang->rc_admin_edit_award);

			$sub_tabs['edit_award'] = array(
				'title'       => $lang->rc_admin_edit_award,
				'link'        => 'index.php?module=config/rcawards',
				'description' => $lang->rc_admin_edit_award_desc
			);

			$page->output_nav_tabs($sub_tabs, 'edit_award');

			if($errors)
			{
				$page->output_inline_error($errors);
			}
			else
			{
				$mybb->input = $message;
			}

			$form = new Form('index.php?module=config/rcawards&amp;action=edit', 'post', 'edit');
			echo $form->generate_hidden_field('aid', $message['aid']);

			$form_container = new FormContainer($lang->rc_admin_edit_award);
			$form_container->output_row($lang->rc_admin_title.' <em>*</em>', $lang->rc_admin_title_desc, $form->generate_text_box('title', $mybb->input['title']));
			$form_container->output_row($lang->rc_admin_desc, $lang->rc_admin_desc_desc, $form->generate_text_area('desc', $mybb->input['desc']));
			$form_container->output_row($lang->rc_admin_url.' <em>*</em>', $lang->rc_admin_url_desc, $form->generate_text_box('url', $mybb->input['url']));
			$form_container->output_row($lang->rc_admin_enabled.' <em>*</em>', $lang->rc_admin_enabled_desc, $form->generate_yes_no_radio('enabled', $mybb->input['enabled'], true));
			$form_container->end();

			$buttons[] = $form->generate_submit_button($lang->rc_save_award);
			$buttons[] = $form->generate_reset_button($lang->rc_reset);

			$form->output_submit_wrapper($buttons);

			$form->end();

			$page->output_footer();
		}

		if($mybb->input['action'] == 'delete')
		{
			$query = $db->simple_select('rc_awards_list', '*', "aid='".intval($mybb->input['aid'])."'");
			$message = $db->fetch_array($query);

			if(!$message['aid'])
			{
				flash_message($lang->rc_error_invalid_award, 'error');
				admin_redirect('index.php?module=config/rcawards');
			}

			if($mybb->input['no'])
			{
				admin_redirect('index.php?module=config/rcawards');
			}

			if($mybb->request_method == 'post')
			{
				$db->delete_query('rc_awards_list', "aid='{$message['aid']}'");
				$db->delete_query('rc_awards', "aid='{$message['aid']}'");

				log_admin_action($message['aid']);

				flash_message($lang->rc_success_award_deleted, 'success');
				admin_redirect('index.php?module=config/rcawards');
			}
			else
			{
				$page->output_confirm_action("index.php?module=config/rcawards&amp;action=delete&amp;aid={$message['aid']}", $lang->rc_confirm_message_deletion);
			}
		}

		if(!$mybb->input['action'])
		{
			$page->output_header($lang->rcawards);

			$sub_tabs['manage_awards'] = array(
				'title'       => $lang->rcawards,
				'link'        => 'index.php?module=config/rcawards',
				'description' => $lang->rc_manage_awards_desc
			);

			$sub_tabs['add_award'] = array(
				'title' => $lang->rc_add_award,
				'link'  => 'index.php?module=config/rcawards&amp;action=add'
			);

			$page->output_nav_tabs($sub_tabs, 'manage_awards');

			$table = new Table;
			$table->construct_header($lang->rc_admin_award, array('colspan' => 2));
			$table->construct_header($lang->rc_admin_desc, array('class' => "align_center"));
			$table->construct_header($lang->rc_admin_image, array('class' => "align_center"));
			$table->construct_header($lang->controls, array('class' => "align_center", 'colspan' => 2));

			$query = $db->simple_select('rc_awards_list', '*');
			while($award = $db->fetch_array($query))
			{
				if($award['enabled'] == 1)
				{
					$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_on.gif\" alt=\"(Enabled)\" title=\"Enabled\"  style=\"vertical-align: middle;\" />";
				}
				else
				{
					$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_off.gif\" alt=\"(Disabled)\" title=\"Disabled\"  style=\"vertical-align: middle;\" />";
				}

				$table->construct_cell($icon, array('width' => 1));
				$table->construct_cell($award['title'], array());
				$table->construct_cell($award['desc'], array('width' => '50%', 'class' => "align_center"));
				$table->construct_cell("<img src=\"{$award['url']}\"></img>", array('class' => "align_center"));
				$table->construct_cell("<a href=\"index.php?module=config/rcawards&amp;action=edit&amp;aid={$award['aid']}\">{$lang->edit}</a>", array("class" => "align_center"));
				$table->construct_cell("<a href=\"index.php?module=config/rcawards&amp;action=delete&amp;aid={$award['aid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->rc_confirm_message_deletion}')\">{$lang->delete}</a>", array("class" => "align_center"));
				$table->construct_row();
			}

			if($table->num_rows() == 0)
			{
				$table->construct_cell($lang->rc_admin_no_awards, array('colspan' => 6));
				$table->construct_row();
			}

			$table->output($lang->rcawards);

			$page->output_footer();
		}

		exit;
	}
}
?>