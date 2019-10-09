<?php
    require_once 'simple_html_dom.php';

    if ( isset($argv[1]) ) {
        $urls[] = $argv[1];
    } else {
    	$urls[] = "https://live.line.me/r/channels/855/broadcast/33867";
    }

    foreach ($urls as $key => $url) {

        $dom = file_get_html($url);
        if ($dom == false) {
            echo "does not url get dom data.";
            continue;
        }

        foreach($dom->find('#data') as $element) {
            $json = $element->attr["data-broadcast"];
            $json = htmlspecialchars_decode ($json);
            $json = json_decode($json, true);

            $folderName = $json['item']['id']."_".$json['item']['channelId']."_".$json['item']['title'];
            $folderName = str_replace("　", "_", $folderName);
            $folderName = str_replace(" ", "_", $folderName);
            $folderName = str_replace(")", "）", $folderName);
            $folderName = str_replace("(", "（", $folderName);
            $playInfo = "https://lssapi.line-apps.com/v1/vod/playInfo?contentId=".$json["lsaPath"];
            if ( !$playInfo = @file_get_contents($playInfo) ) {
                echo "does not get playInfo.";
                continue;
            }

            $playInfo = json_decode($playInfo, true);
            $exp = explode("/", $playInfo["playUrls"]["720"]);
            if ( empty($exp[4]) ) {
                echo "does not get key.\n";
                break;
            }

            // 動画取得用のkey
            $key = $exp[4];
            $movieDomain = "http://lss.line-cdn.net/rb/";
            $type = "720.";
            $title = $folderName;
            $folderName = "archive/".$folderName;

            $json["playInfo"] = $playInfo;
            $json["key"] = $key;
            $json["url"] = $url;
            $json["folderName"] = $folderName;

            if ( !file_exists($folderName) ) {
                //存在しないときの処理（「$directory_path」で指定されたディレクトリを作成する）
                if ( mkdir($folderName, 0777) ){
                    echo "make directory:".$folderName;
                    //作成したディレクトリのパーミッションを確実に変更
                    chmod($folderName, 0777);
                } else {
                    continue;
                }
            }

            $fp = fopen("./$folderName/info.json", "w");
            fwrite($fp, json_encode($json));
            fclose($fp);

            for ($i=1; $i<=99999; $i++) {
            // for ($i=1; $i<=5; $i++) {
                $pad = str_pad($i, 5, 0, STR_PAD_LEFT);
                $tsName = $type.$pad.".ts";
                $tsUrl = "$movieDomain$key/$tsName";
                $saveFile = "./$folderName/$type".$pad.".ts";
                echo $tsName."\n";
                echo $tsUrl."\n";
                echo $saveFile."\n";

                try {
                	if ( !file_exists($saveFile) ) {
	                    if ( $data = @file_get_contents($tsUrl) ) {
	                        file_put_contents($saveFile,$data);
	                    } else {
	                        echo "end\n";
	                        break;
	                    }
	                }
                } catch (Exception $e) {
                    echo $e-getMessage()."\n";
                    exit;
                    break;
                }
            }
        }


        $command = "cd ./$folderName/; ls -l | awk '{print $9}' | grep '720*' > file_list.txt";
        // $command = "cd ./$folderName/; ls -l | grep '720*' | xargs > file_list.txt";
        echo "$command\n";
        shell_exec($command);
        $ffmpeg_command = "ffmpeg -y";
        $fp = fopen("./$folderName/file_list.txt", "r");
        $n = 0;
        $cnt = 0;
        $split_cnt = 200;
        // $splits = array();
        while( !feof($fp) ) {
            $buffer = fgets( $fp, 4096 );
            // $exps = explode(" ", $buffer);
            if ($buffer != "") {
                $id = ($n / $split_cnt);
                $id = (int)$id;
                $buffer = str_replace("\n", "", $buffer);
                if ( !isset($splits[$id])) {
                    $splits[$id] = "";
                }
                $splits[$id] = $splits[$id] . " -i ".$buffer;
                $n++;
                // $ffmpeg_command .= " -i ".$buffer;
            }
        }
        fclose($fp);
        $split_command = "ffmpeg -y";
        foreach ($splits as $key => $split) {
        	$saveFile = ($key+1).".ts";

        	if ( !file_exists("$folderName/".$saveFile) ) {
	        	$exp = explode("-i", $split);
	        	$n = count($exp) - 1 ;
	        	$command = $ffmpeg_command . $split . ' -filter_complex "concat=n='.$n.':v=1:a=1" '.$saveFile;
	        	echo $command."\n";
                shell_exec("cd ./$folderName/;" . $command);
                if ( !filesize ( "./$folderName/" . $saveFile ) ) {
                    $command = $ffmpeg_command . $split . ' -filter_complex "concat=n='.($n-1).':v=1:a=1" '.$saveFile;
                    echo $command."\n";
                    shell_exec("cd ./$folderName/;" . $command);
                }
	        }
    		$split_command .= " -i ".$saveFile;
        }

        if ( !file_exists("./$folderName/".$title.'.mp4') ) {
            $split_command .= ' -f mp4 -vcodec libx264 -vsync 1 -filter_complex "concat=n='.(count($splits)).':v=1:a=1" '.$title.'.mp4';
            echo $split_command."\n";
        	shell_exec("cd ./$folderName/;" . $split_command);
        }

        if ( filesize ( "./$folderName/" . $title.'.mp4' ) ) {
            shell_exec("cd ./$folderName/; rm -f *.ts");
        }

        $dom->clear();
        unset($dom);
    }
?>
