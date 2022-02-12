<?php
/*
MIT License

Copyright (c) 2022 Lasse Hassing

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

===============================================================================

Web based remote for https://ytmdesktop.app/

Requirements: PHP with cURL support.
Github: https://github.com/hassing/ytm-webremote/
*/

function getData($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, "http://".$_POST["server"]."/".$url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($curl);
    curl_close($curl);
    
    if($result === false) {
        return null;
    }
    return json_decode($result, false);
}
function postCommand($url) {
    $data = ["command" => $_POST["command"]];
    if(isset($_POST["value"])) {
        $data["value"] = $_POST["value"];
    }

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, "http://".$_POST["server"]."/".$url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ["Authorization: Bearer ".$_POST["auth"]]);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

    $result = curl_exec($curl);
    curl_close($curl);
    
    return $data;
}

if(isset($_POST["auth"]) && isset($_POST["server"])) {
    if(preg_match("/^[a-z0-9\.\:\-]+$/i", $_POST["server"]) == false) {
        header("Location: /?error=1");
        exit();
    } 

    if(isset($_POST["type"])) {
        if($_POST["type"] == "data") {
            $data = getData("query");        
            if($data == null) {
                echo json_encode(["player" => ["hasSong" => false]]);
                exit();
            }
            echo json_encode($data);
            exit();
        } else if($_POST["type"] == "queue") {
            $data = getData("query/queue");        
            if($data == null) {
                echo json_encode(["currentIndex" => 0,"list" => []]);
                exit();
            }
            echo json_encode($data);
            exit();
        } else if($_POST["type"] == "command") {
            echo json_encode(["status" => postCommand("query")]);
        }
    
        exit();
    } else {
        if(getData("query") == null) {
            header("Location: /?error=1");
            exit();
        }
    
        if(isset($_POST["remember"])) {
            setcookie("server", $_POST["server"], time()+60*60*24*365);
            setcookie("auth", $_POST["auth"], time()+60*60*24*365);
        }
    }
}


?>
<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="width=device-width">
        <title>Youtube Music Remote</title>
        <style>
            body {
                background: #101519;
                color: #C9E4CA;
                font-family: "Source Code Pro", "Monaco", "Consolas", "Courier New", monospace;
            }
            #song {
                font-weight: bold;
            }
            a {
                color: #B3CCD0;
                text-decoration: none;
            }
            .past {
                color: #3B6064;
            }
            .past a {
                color: #3B6064;
            }
            input[type=text] {
                background: #B3CCD0;
                color: black;
                font-family: "Source Code Pro", "Monaco", "Consolas", "Courier New", monospace;
                border: 2px solid #3B6064;
            }
        </style>
    </head>
    <body>
<?php if(isset($_POST["auth"]) && isset($_POST["server"])) { ?>
    <p>
        <span id="song">Not playing ...</span>
        [<a href="javascript:;" onclick="command('track-thumbs-down', null);">DISLIKE</a>
        /
        <a href="javascript:;" onclick="command('track-thumbs-up', null);">LIKE</a>]<br>
        <span id="artist">-</span><br>
        <br>
        [<a href="javascript:;" onclick="command('track-previous', null);">BACK</a>]
        [<a href="javascript:;" onclick="command('track-play', null);">PLAY</a> / <a href="javascript:;" onclick="command('track-pause');">PAUSE</a></a>]
        [<a href="javascript:;" onclick="command('track-next', null);">NEXT</a>]<br>
        <br>
        <span id="playlist"></span><br>
        [<a href="/?forget=1">LOGOUT</a>]
    </p>

    <script>
        var currentData = {
            trackTimer: 0,

            song: "",
            artist: "",

            playlist: []
        };

        function dataChanged() {
            elSong = document.getElementById("song");
            elArtist = document.getElementById("artist");
            elPlaylist = document.getElementById("playlist");

            if(currentData.song != "") {
                elSong.innerHTML = currentData.song;
                elArtist.innerHTML = currentData.artist;
            } else {
                elSong.innerHTML = "Not playing ...";
                elArtist.innerHTML = "-";
            }

            var playlistHTML = "";
            var track = 0;
            for(var i=0; i<currentData.playlist.length; i++) {
                playlistHTML += "<span class=\""+currentData.playlist[i].status.toLowerCase()+"\">"
                playlistHTML += "[<a href=\"javascript:;\" onclick=\"command('player-set-queue', '"+currentData.playlist[i].index+"')\">";
                if(currentData.playlist[i].status == "PAST") {
                    playlistHTML += "--</a>] "
                } else if(currentData.playlist[i].status == "CURRENT") {
                    playlistHTML += "=&gt;</a>] "
                } else {
                    track++;
                    if(track < 10) {
                        playlistHTML += "0";
                    }
                    playlistHTML += track + "</a>] ";
                }
                playlistHTML += "<strong>"+currentData.playlist[i].song+"</strong>";
                playlistHTML += "<br>&nbsp;&nbsp;&nbsp;&nbsp; - " + currentData.playlist[i].artist + "<br>";
                playlistHTML += "</span>";
            }
            elPlaylist.innerHTML = playlistHTML;
        }

        function command(action, value) {
            fetch("/", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "server=<?= $_POST["server"] ?>&auth=<?= $_POST["auth"] ?>&type=command&command="+action + (value==null?"":"&value="+value)
            });

            // Wait 1 sec to update since YTMDesktop API is very slow to reflect changes.
            trackChange(currentData.song, currentData.artist, 1);
        }
        
        function trackChange(song, artist, nextUpdate) {
            if(currentData.artist != artist || currentData.song != song) {
                currentData.artist = artist;
                currentData.song = song;
                dataChanged();
                updateQueue();
            }
            
            clearTimeout(currentData.trackTimer);
            currentData.trackTimer = setTimeout(() => {
                updateTrack();
            }, nextUpdate * 1000);
        }
        function updateTrack() {
            fetch("/", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "server=<?= $_POST["server"] ?>&auth=<?= $_POST["auth"] ?>&type=data"
            }).then(response => response.json())
            .then(data => {
                if(data.player.hasSong) {
                    trackChange(data.track.title, data.track.author, (data.track.duration - data.player.seekbarCurrentPosition) + 1);
                } else if(currentData.song != "") {
                    trackChange("", "", 30);
                }
            });
        }
        

        function updateQueue() {
            fetch("/", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "server=<?= $_POST["server"] ?>&auth=<?= $_POST["auth"] ?>&type=queue"
            }).then(response => response.json())
            .then(data => {
                var list = [];
                for(var i=2; i>0; i--) {
                    var idx = data.currentIndex - i;
                    if(idx < 0) {
                        continue;
                    }
                    list.push({
                        "artist": data.list[idx].author,
                        "song": data.list[idx].title,
                        "status": "PAST",
                        "index": idx
                    });
                }

                for(var i=0; i<20; i++) {
                    if(list.length == 20) {
                        break;
                    }
                    var idx = data.currentIndex + i;
                    if(idx >= data.list.length) {
                        break;
                    }
                    list.push({
                        "artist": data.list[idx].author,
                        "song": data.list[idx].title,
                        "status": i == 0 ? "CURRENT" : "NEXT",
                        "index": idx
                    });
                }
                
                currentData.playlist = list;
                dataChanged();
            });
        }

        updateTrack();
    </script>
<?php } else {
    if(isset($_GET["forget"])) {
        setcookie("server", "", time()-60);
        setcookie("auth", "", time()-60);
        header("Location: /");
        exit();
    }   
?>
    <?php if(isset($_GET["error"])) { ?>
        <p style="color:red;">FAILED TO CONNECT!</p>
    <?php } ?>
    <form action="/" method="POST">
        <p>
            <label for="server">YTMDesktop Remote</label><br>
            <br>
            Server must be accessible through the internet and not just LAN.<br>
            [<a href="https://github.com/hassing/ytm-webremote">SOURCE CODE</a>]
            [<a href="https://hassing.org">MADE BY</a>]<br>
            <br>
            <input type="text" name="server" id="server" placeholder="ip.or.domain:port" value="<?= isset($_COOKIE["server"]) ? $_COOKIE["server"] : "" ?>"><br>
            <input type="text" name="auth" id="auth" placeholder="password ..." value="<?= isset($_COOKIE["auth"]) ? $_COOKIE["auth"] : "" ?>"><br>
            <label for="remember"><input type="checkbox" name="remember" id="remember" checked="checked"> Remember</label><br>
            <input type="submit" value="START">
        </p>
    </form>
<?php } ?>
    </body>
</html>
