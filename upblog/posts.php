<?php

function NoWarnings($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno)) {
        return;
    }

	if ($errno === E_USER_WARNING)
	{
		//Ignore warnings
		return true;
	}
	else
	{
		//Allow default handler for 
		// all other error types
		return false;
	}
}

//Returns an associative array of all posts
// based on current file system state
function rebuild_posts()
{
	$posts = [];

	//Index all posts by modification date
	foreach(glob(POSTS . '*.md') as $file) 
	{
		//We want to skip directories
		//Suppress warnings when is_dir returns false
		set_error_handler("NoWarnings");
		
		if (is_dir($file))
		{
			continue;
		}
		
		set_error_handler(null);
		
		$info = metadata($file);
		
		//Store posts by their modified time
		// but if the slot is taken, we just keep going up until
		// there is an empty slot.
		$storageTime = $info['modified'];
		while (isset($posts[$storageTime])){
			//Files are traversed alphabetically
			// so going back in time puts them in A-Z order
			$storageTime--;
		}
		
		//Store it under the agreed key
		$posts[$storageTime] = $info;
	}
	
	return $posts;
}

function manifest($name)
{
	//Get matching json file
	return str_ireplace('.md', '.json', $name);
}

function metadata($filename)
{
	$manifest = existing(manifest($filename));
	$modified = filemtime($filename);
	
	//Returns post info as assoc array 
	$info = [];
	
	if ($manifest)
	{
		//JSON exists; decode and update
		$json = file_get_contents($manifest);
		$info = json_decode($json, true);
		
		/*
		//If file has been modified since metadata was built
		if ($modified !== $info['modified'])
		{
			//Update metadata
			$info['modified'] = $modified;
		}
		*/
	}
	else
	{
		//Build first time manifest
		$info = [
			'file' => $filename,
			'link' => basename($filename, '.md'),
			'title' => title_of_file($filename),
			'created' => $modified,
			'modified' => $modified
		];
		
		//Encode JSON and save
		$json = json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		$destination = manifest($filename);
		file_put_contents($destination, $json);
	}
	
	return $info;
}
