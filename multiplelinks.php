<?php
/*
 *
 * Thanks to John Eckman https://github.com/jeckman/YouTube-Downloader
 * Author: Vaibhav Joshi https://github.com/vaibhav12321
 * License: GPL v2 or Later
*/


if(isset($_POST['videoids']) && $_POST['videoids']!=''){
ob_start();

function clean($string) {
   $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
   return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
}

function curlGet($url){
	$ch = curl_init();
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
	$output=curl_exec($ch);
	curl_close($ch);
	return $output;
}

function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'); 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . '' . $units[$pow]; 
}

	function getLink($my_id){
			if( preg_match('/^https:\/\/w{3}?.youtube.com\//', $my_id) ){
				$url   = parse_url($my_id);
				$my_id = NULL;
				if( is_array($url) && count($url)>0 && isset($url['query']) && !empty($url['query']) ){
					$parts = explode('&',$url['query']);
					if( is_array($parts) && count($parts) > 0 ){
						foreach( $parts as $p ){
							$pattern = '/^v\=/';
							if( preg_match($pattern, $p) ){
								$my_id = preg_replace($pattern,'',$p);
								break;
							}
						}
					}
					if( !$my_id ){
						echo '<p>No video id passed in</p>';
						exit;
					}
				}else{
					echo '<p>Invalid url</p>';
					exit;
				}
			}elseif( preg_match('/^https?:\/\/youtu.be/', $my_id) ) {
				$url   = parse_url($my_id);
				$my_id = NULL;
				$my_id = preg_replace('/^\//', '', $url['path']);
			}


		/* First get the video info page for this video id */
		//$my_video_info = 'http://www.youtube.com/get_video_info?&video_id='. $my_id;
		$my_video_info = 'http://www.youtube.com/get_video_info?&video_id='. $my_id.'&asv=3&el=detailpage&hl=en_US'; //video details fix *1
		$my_video_info = curlGet($my_video_info);

		$thumbnail_url = $title = $url_encoded_fmt_stream_map = $type = $url = '';

		parse_str($my_video_info);
		if($status=='fail'){
			echo '<p>Error in video ID</p>';
			exit();
		}

		$my_title = $title;
		$cleanedtitle = clean($title);

		if(isset($url_encoded_fmt_stream_map)) {
			/* Now get the url_encoded_fmt_stream_map, and explode on comma */
			$my_formats_array = explode(',',$url_encoded_fmt_stream_map);
		} else {
			echo '<p>No encoded format stream found.</p>';
			echo '<p>Here is what we got from YouTube:</p>';
			echo $my_video_info;
		}
		if (count($my_formats_array) == 0) {
			echo '<p>No format stream map found - was the video id correct?</p>';
			exit;
		}

		/* create an array of available download formats */
		$avail_formats[] = '';
		$i = 0;
		$ipbits = $ip = $itag = $sig = $quality = '';
		$expire = time(); 

		foreach($my_formats_array as $format) {
			parse_str($format);
			$avail_formats[$i]['itag'] = $itag;
			$avail_formats[$i]['quality'] = $quality;
			$type = explode(';',$type);
			$avail_formats[$i]['type'] = $type[0];
			$avail_formats[$i]['url'] = urldecode($url) . '&signature=' . $sig;
			parse_str(urldecode($url));
			$avail_formats[$i]['expires'] = date("G:i:s T", $expire);
			$avail_formats[$i]['ipbits'] = $ipbits;
			$avail_formats[$i]['ip'] = $ip;
			$i++;
		}

		$format =  $_POST['format'];
		$target_formats = '';
		switch ($format) {
			case "best":
				/* largest formats first */
				$target_formats = array('38', '37', '46', '22', '45', '35', '44', '34', '18', '43', '6', '5', '17', '13');
				break;
			case "free":
				/* Here we include WebM but prefer it over FLV */
				$target_formats = array('38', '46', '37', '45', '22', '44', '35', '43', '34', '18', '6', '5', '17', '13');
				break;
			case "ipad":
				/* here we leave out WebM video and FLV - looking for MP4 */
				$target_formats = array('37','22','18','17');
				break;
			default:
				/* If they passed in a number use it */
				if (is_numeric($format)) {
					$target_formats[] = $format;
				} else {
					$target_formats = array('38', '37', '46', '22', '45', '35', '44', '34', '18', '43', '6', '5', '17', '13');
				}
			break;
		}

		/* Now we need to find our best format in the list of available formats */
		$best_format = '';
		for ($i=0; $i < count($target_formats); $i++) {
			for ($j=0; $j < count ($avail_formats); $j++) {
				if($target_formats[$i] == $avail_formats[$j]['itag']) {
					//echo '<p>Target format found, it is '. $avail_formats[$j]['itag'] .'</p>';
					$best_format = $j;
					break 2;
				}
			}
		}

		//echo '<p>Out of loop, best_format is '. $best_format .'</p>';
		if( (isset($best_format)) && 
		  (isset($avail_formats[$best_format]['url'])) && 
		  (isset($avail_formats[$best_format]['type'])) 
		  ) {
			$redirect_url = $avail_formats[$best_format]['url'].'&title='.$cleanedtitle;
			$content_type = $avail_formats[$best_format]['type'];
		}
		if(isset($redirect_url)) {
			//header("Location: $redirect_url"); 
			echo $redirect_url.'<br>';
		}
	}
	if(isset($_POST['videoids'])){
		$links = explode(PHP_EOL, $_POST['videoids']);
		foreach($links as $link){
			getLink($link);
		}
	}
} else{
?>
<html>
<head></head>
<body>
<form action="" method="post">
<textarea name="videoids" style="width: 50%; height: 40%;" placeholder="All links on new line"></textarea>
<br>
Format: <select name="format">
<option value="best">Best</option>
<option value="free">Free</option>
<option value="ipad">Ipad</option>
</select>
<br>
<input type="submit" value="Get Links" />
</form>
</body>
</html>

<?php } ?>