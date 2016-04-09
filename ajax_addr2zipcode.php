<?php
/**
 *
 * 住所から郵便番号を割り出す
 *
 * @author      kokko
 * @version     1.0
 * @create      2016-04-09
 *
 **/
if ( $debug = 0 ) {
	header('Content-Type: text/plain;charset=UTF-8');
	header('Cache-Control: no-cache');
	header('Pragma: no-cache');
}

/*	初期化処理	--------------------------------*/

$result = array();

$errors = array();



/*	環境の確認	--------------------------------*/

if ( !function_exists('file_get_contents') ) {
	$errors[] = "file_get_contents 関数が存在しません";
}
if ( ini_get('allow_url_fopen') != 1 ) {
	$errors[] = "allow_url_fopen が 1 ではありません";
}



/*	GETパラメータの取得	------------------------*/

//検索する住所
if ( isset($_GET['addr']) ) {
	$addr = trim($_GET['addr']);
	if ( $debug ) {
		echo "addr: {$addr}\n\n";
	}
} else {
	$errors[] = "addr が指定されていません";
}


/*	(1)都道府県の取得	------------------------*/

if ( !count($errors) ) {
	//都道府県JSONデータの取得
	$request_url = 'http://api.thni.net/jzip/X0401/JSON/J/state_index.js';
	if ( ($json_pref = file_get_contents($request_url)) !== false ) {
		$arr_pref = json_decode($json_pref, true);
		if ( !isset($arr_pref) || !is_array($arr_pref) ) {
			$errors[] = "都道府県JSONが正しい形式で取得できませんでした";
		}
		if ( !count($errors) ) {
			//住所の始め３～４文字が都道府県に該当するか確かめる
			foreach ( $arr_pref as $i => $pref_data ) {
				if ( preg_match('/^'.preg_quote($pref_data['name'], '/').'/', $addr) ) {
					$pref_name = $pref_data['name'];
					$addr = preg_replace('/^'.preg_quote($pref_name, '/').'/', '', $addr);
					break;
				}
			}
			if ( !isset($pref_name) ) {
				$errors[] = "都道府県の取得ができません";
			}
			
			if ( $debug ) {
				echo "都道府県: {$pref_name}\n\n";
			}
		}
	} else {
		$errors[] = "都道府県リストの取得に失敗しました";
	}
}


/*	(2)都道府県から市区郡の取得	------------*/

if ( !count($errors) ) {
	//市区町村JSONデータの取得
	$request_url = 'http://api.thni.net/jzip/X0401/JSON/J/'.urlencode($pref_name).'/city_index.js';
	if ( ($json_city = file_get_contents($request_url)) !== false ) {
		$arr_city = json_decode($json_city, true);
		if ( !isset($arr_city) || !is_array($arr_city) ) {
			$errors[] = "市区郡JSONが正しい形式で取得できませんでした";
		}
		if ( !count($errors) ) {
			//住所に市区郡の該当するか確かめる
			foreach ( $arr_city as $i => $city_data ) {
				if ( preg_match('/^'.preg_quote($city_data['name'], '/').'/', $addr) ) {
					$city_name = $city_data['name'];
					$addr = preg_replace('/^'.preg_quote($city_name, '/').'/', '', $addr);
					break;
				}
			}
			if ( !isset($city_name) ) {
				$errors[] = "市区郡の取得ができません";
			}
			
			if ( $debug ) {
				echo "市区郡: {$city_name}\n\n";
			}
		}
	} else {
		$errors[] = "市区郡リストの取得に失敗しました";
	}
}


/*	(3)市区郡から町村大字の取得	------------*/

if ( !count($errors) ) {
	//町名大字JSONデータの取得
	$request_url = 'http://api.thni.net/jzip/X0401/JSON/J/'.urlencode($pref_name).'/'.urlencode($city_name).'/street_index.js';
	if ( ($json_town = file_get_contents($request_url)) !== false ) {
		$arr_town = json_decode($json_town, true);
		if ( !isset($arr_town) || !is_array($arr_town) ) {
			$errors[] = "町村大字JSONが正しい形式で取得できませんでした";
		}
		if ( !count($errors) ) {
			//「大字」を外す
			$addr = preg_replace('/大字/', '', $addr);
			//住所に町村大字の該当するか確かめる
			foreach ( $arr_town as $i => $town_data ) {
				if ( preg_match('/^'.preg_quote($town_data['name'], '/').'/', $addr) ) {
					$town_name = $town_data['name'];
					$addr = preg_replace('/^'.preg_quote($town_name, '/').'/', '', $addr);
					break;
				}
			}
			if ( !isset($town_name) ) {
				$town_name = '以下に掲載がない場合';
			}
			
			if ( $debug ) {
				echo "町村大字: {$town_name}\n\n";
			}
		}
	} else {
		$errors[] = "町村大字リストの取得に失敗しました";
	}
}


/*	(4)都道府県・市区郡・町村大字から郵便番号を取得する */

if ( !count($errors) ) {
	//町名大字JSONデータの取得
	$request_url = 'http://api.thni.net/jzip/X0401/JSON/J/'.urlencode($pref_name).'/'.urlencode($city_name).'/'.urlencode($town_name).'.js';
	if ( ($json_zip = file_get_contents($request_url)) !== false ) {
		$arr_zip = json_decode($json_zip, true);
		if ( !isset($arr_zip) || !is_array($arr_zip) ) {
			$errors[] = "郵便番号JSONが正しい形式で取得できませんでした";
		}
		if ( !count($errors) ) {
			if ( $debug ) {
				echo "取得結果：\n";
				var_dump($arr_zip);
			}
			if ( isset($arr_zip['zipcode']) ) {
				$result = $arr_zip;
			}
		}
	} else {
		$errors[] = "町村大字リストの取得に失敗しました";
	}
}


/*	結果の返却	--------------------------------*/

if ( !count($errors) ) {
	$res = array(
		'result'	=>	true,
		'zipcode'	=>	$result['zipcode'],
	);
} else {
	$res = array(
		'result'	=>	false,
		'errors'	=>	$errors,
	);
}

if ( !$debug ) {
	header('Content-Type: text/plain;charset=UTF-8');
	header('Cache-Control: no-cache');
	header('Pragma: no-cache');
}
echo json_encode($res);
exit;
