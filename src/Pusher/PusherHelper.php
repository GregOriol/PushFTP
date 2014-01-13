<?php

namespace Pusher;

class PusherHelper
{
	var $repo_root;
	var $repo_rpath;

	function svn_changes_parse($v) {
		$arr = split('       ', $v);

		if (count($arr) != 2) {
			$arr = array();
			$arr[0] = '???';
			$arr[1] = '';
		}

		return array(
			'status' => $arr[0],
			'file' => str_replace($this->repo_root.'/'.$this->repo_rpath.'/', '', $arr[1])
		);
	}
}

?>
