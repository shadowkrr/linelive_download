<?php
    require_once 'simple_html_dom.php';

    $urls[] = "https://live.line.me/r/channels/777/broadcast/162632";

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

            $playInfo = "https://lssapi.line-apps.com/v1/live/playInfo?contentId=".$json["lsaPath"];
            if ( !$playInfo = @file_get_contents($playInfo) ) {
                echo "does not get playInfo";
                continue;
            }
            $playInfo = json_decode($playInfo, true);
            $exp = explode("/", $playInfo["playUrls"]["720"]);
            if ( empty($exp[5]) ) {
                echo "does not get key.\n";
                break;
            }
            // 動画取得用のkey
            $key = $exp[5];
            $movieDomain = "http://lss.line-cdn.net/p/live/";

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

            while(true) {
                $end = false;
                $chunklist = @file_get_contents($playInfo["playUrls"]["720"]);
                $exp = explode("\n", $chunklist);
                foreach ($exp as $key2 => $value) {
                    if ($value == "#EXTINF:2.0,") {
                        $exp2 = explode("_", $exp[$key2+1]);
                        $media = $exp2[0]."_";
                        $origName = $exp2[1];
                        $exp2 = explode(".", $exp2[1]);
                        $num = $exp2[0];
                        // $tsName = $exp[$key2+1];

                        // 過去20個超えないくらいしか取得できない
                        for ($i=$num; $i>=($num-20); $i--) {
                            $type = "720/";
                            $tsName = $media.$i.".ts";
                            $tsUrl = "$movieDomain$key/$type$tsName";
                            $saveFile = "./$folderName/$tsName";
                            echo $tsName."\n";
                            echo $tsUrl."\n";

                            try {
                                if ( !file_exists($saveFile) ) {
                                    if ( $data = @file_get_contents($tsUrl) ) {
                                        file_put_contents($saveFile, $data);
                                    } else {
                                        echo "end\n";
                                        break;
                                    }
                                }
                            } catch (Exception $e) {
                                echo $e-getMessage()."\n";
                                break;
                            }
                        }
                        break;
                    }
                }
                sleep(15);
            }
        }
    }
    exec("ls -l | awk '{print $9}' | grep '720*' > ./$folderName/file_list.txt");
    $dom->clear();
    unset($dom);
?>
