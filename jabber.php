<?php
/*
Add the Jabber hook to all of your repos

* At this phase, it's just a quick and dirty script. If I
  still have the motivation, I'll blow it up into something
  better. And probably in Python.

Copyright (c) 2012, Bryce Chidester <bryce@cobryce.com>

Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby granted, provided that the above copyright notice and this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
*/

/*
Syntax: $0 github_username github_password jabber_address
*/
list($app, $github_username, $github_password, $jabber_address) = $_SERVER['argv'];

if($_SERVER['argc'] < 2 || $_SERVER['argv'][1] == '-h' || $_SERVER['argv'][1] == '--help')
	die("Syntax: $app github_username github_password jabber_address [jabber_secret]".PHP_EOL);
if(!$github_username)
	die("You must specify your GitHub username.".PHP_EOL);
if(!$github_password)
	die("You must specify your GitHub password.".PHP_EOL);
if(!$jabber_address)
	die("You must specify the jabber address.".PHP_EOL);

$ch_repos = curl_init("https://api.github.com/user/repos");
apply_curl_setopt($ch_repos);
$response = curl_exec($ch_repos);
$json = json_decode($response);
if($json === false)
	die("Unable to fetch your GitHub repos: $response".PHP_EOL);
elseif(sizeof($json) === 0)
	die("You have no GitHub repos, at all. I cannot continue.".PHP_EOL);

/* Fetch the list of all repos to which we have Admin privileges. */
$repos = array();
foreach($json as $repo)
{
	if($repo->permissions->admin)
	{
		$repos[ $repo->full_name ] = $repo->url;
		echo "Adding {$repo->full_name} to the list of repos.".PHP_EOL;
	} else
		echo "Skipping {$repo->full_name} -- You do not have Admin privileges.".PHP_EOL;
}
if(sizeof($repos) === 0)
	die("You have no workable GitHub repos. You can only add hooks to repos to which you have administrative privileges.".PHP_EOL);

/* Now review those repos and exclude any with Jabber already enabled */
$ch_repos = curl_multi_init();
$ch = array();
foreach($repos as $repo_name => $repo_url)
{
	$ch[ $repo_name ] = curl_init($repo_url."/hooks");
	apply_curl_setopt($ch[ $repo_name ]);
	curl_multi_add_handle($ch_repos, $ch[ $repo_name ]);
}
// Spool the requests
$active = null;
do
{
	$mrc = curl_multi_exec($ch_repos, $active);
} while ($mrc == CURLM_CALL_MULTI_PERFORM);
// Everything has been "spooled", so now we handle things as they're ready
while ($active && $mrc == CURLM_OK)
{
	// select() and wait for one to return
	if(curl_multi_select($ch_repos) != -1)
	{
		do
		{
			$mrc = curl_multi_exec($ch_repos, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);
	}
}
// All sockets complete, now we get to process the returned data
foreach($ch as $repo_name => $c)
{
	$response = curl_multi_getcontent($c);
	curl_multi_remove_handle($ch_repos, $c);
	$json = json_decode($response);
	if($json === false)
	{
		echo "ERROR! Error in GitHub's response for /hooks on $repo_name. Skipping.".PHP_EOL;
		echo $response.PHP_EOL;
		unset($repos[ $repo_name ]);
		continue;
	}
	foreach($json as $hook)
	{
		if($hook->name == "jabber")
		{
			echo "Jabber service hook already enabled for $repo_name. Ignoring this repo.".PHP_EOL;
			unset($repos[ $repo_name ]);
			break;
		}	// else, it stays in the list $repos
	}
}
curl_multi_close($ch_repos);

echo "Adding the Jabber hook to ".sizeof($repos)." repos.".PHP_EOL;

// Create the object we'll use
$hook = new stdClass;
$hook->active = true;
$hook->name = "jabber";
$hook->config = new stdClass;
$hook->config->user = $jabber_address;
$json = json_encode($hook);

$ch_repos = curl_multi_init();
$ch = array();
foreach($repos as $repo_name => $repo_url)
{
	$ch[ $repo_name ] = curl_init($repo_url."/hooks");
	apply_curl_setopt($ch[ $repo_name ]);
	curl_setopt($ch[ $repo_name ], CURLOPT_CUSTOMREQUEST, "POST");	// CustomRequest so avoid setting an encoding type
	curl_setopt($ch[ $repo_name ], CURLOPT_POSTFIELDS, $json);
	curl_multi_add_handle($ch_repos, $ch[ $repo_name ]);
}
// Spool the requests
$active = null;
do
{
	$mrc = curl_multi_exec($ch_repos, $active);
} while ($mrc == CURLM_CALL_MULTI_PERFORM);
// Everything has been "spooled", so now we handle things as they're ready
while ($active && $mrc == CURLM_OK)
{
	// select() and wait for one to return
	if(curl_multi_select($ch_repos) != -1)
	{
		do
		{
			$mrc = curl_multi_exec($ch_repos, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);
	}
}
// All sockets complete, now we get to process the returned data
foreach($ch as $repo_name => $c)
{
	$response = curl_multi_getcontent($c);
	curl_multi_remove_handle($ch_repos, $c);
	$json = json_decode($response);
	if($json === false)
	{
		echo "ERROR! Error in GitHub's response for POST /hooks on $repo_name. You should check this manually.".PHP_EOL;
		echo $response.PHP_EOL;
		continue;
	}
	if($json->id)
		echo "Jabber service hook added to $repo_name.".PHP_EOL;
	else
		echo "Jabber service hook not added to $repo_name, or something else is weird. $response".PHP_EOL;
}
curl_multi_close($ch_repos);


function apply_curl_setopt(&$ch)
{
	global $github_username, $github_password;
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_USERPWD, "{$github_username}:{$github_password}");
	curl_setopt($ch, CURLOPT_USERAGENT, "GitHub-Multihook-Enable v1.0 (Jabber, http://github.com/brycied00d/GitHub-Multihook-Enable)");
}
?>
