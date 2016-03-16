<?
function fstbToGV($con) {
    $inModel = false;
    $inTransition = false;

    $states = [];
    $transitions = [];

    foreach(explode("\n", $con) as $index => $line) {
        $line = str_replace("\t", " ", $line);
        $line = str_replace("\r", " ", $line);
        $line = preg_replace("'[\s][\s]+'", " ", $line);
        $line = trim($line);
        if (!strlen($line)) continue;
        
        if ($line[0] == '}') {
            // end model or transition
            if ($inTransition) {
                $transitions[] = $transition;
                $inTransition = false;
            }
            else if ($inModel) {
                $inModel = false;
            }
        }
        else {
            list($command) = explode(" ", $line);
            
            if ($command == 'model') {
                $inModel = true;
            }
            
            if ($inModel) {
                if (substr($line, 0, 2) == '//') continue;
                // echo "- $command\n";
                
                if (!$inTransition) {
                    if ($command == 'states') {
                        if (preg_match("'^states ([^;]+);'", $line, $match)) {
                            $states = explode(",", str_replace(" ", "", trim($match[1])));
                            // print_r($states);
                        }
                    }
                    else if ($command == 'transition') {
                        $inTransition = true;
                    }
                }
                else {
                    if (preg_match("'([^\s]+):=([^;]*);'", str_replace(" ", "", $line), $match)) {
                        $transition[$command] = $match[2];
                    }
                    // else die("Cannot process line $index\n");
                }
                
            }
        }
    }

    ob_start();
    ?>
    digraph finite_state_machine {
        rankdir=LR;
        size="16"
        <?
            echo "\n";
            foreach($states as $state) {
                $label = '<font face="Arial" point-size="16">' . $state . '</font>';
            
                echo "    node [shape = circle, label =< $label >]; $state\n";
            }
            echo "\n";
            foreach($transitions as $transition) {
                $action = str_replace(",", ", ", $transition['action']);
                $guard = str_replace(",", ", ", $transition['guard']);
                
                $htmls = [];
                
                if ($guard) {
                    $htmls[] = '<font face="Arial" point-size="15" color="blue">'
                    .htmlentities($guard)
                    .'</font>';
                }
                
                if ($action) {
                    $htmls[] = '<font face="Arial" point-size="15" color="red">'
                        .htmlentities($action)
                        .'</font>';
                }
                    
                $html = implode("<br></br>", $htmls);
                
                echo "    {$transition['from']} -> {$transition['to']} [ label =< $html > ]\n";
            }
        ?>
    }
    <? 
    $result = ob_get_contents();
    ob_end_clean();
    return $result;
}

session_start();
@mkdir('sessions');
$sid = 'sessions/' . preg_replace("'[^a-zA-Z0-9]+'", "", session_id());


if (isset($_POST['fstb'])) {
    $fstb = $_POST['fstb'];
}
else if (strlen($sid) && file_exists("{$sid}.fstb")) {
    $fstb = file_get_contents("{$sid}.fstb");
}
else {
    $fstb = file_get_contents('example.fstb');
}

$processed = false;

if (strlen($sid) && isset($_POST['fstb'])) {
    file_put_contents("{$sid}.fstb", $fstb);
}

if (strlen($fstb) && strlen($sid)) {
    $processed = true;
    @unlink("{$sid}.png");
    $gv = fstbToGV($fstb);
    file_put_contents("{$sid}.gv", $gv);
    exec("dot -Tpng {$sid}.gv -o {$sid}.png");
}

?>
<!doctype html>
<html><head>
    <title>FSTB Viewer</title>
    <style type="text/css">
        html, input, textarea, button {
            font-family: Verdana;
            font-size: 11px;
        }
    </style>
    <meta charset="utf-8">
</head><body>
    <div style="text-align: center">
        <div style="display: inline-block; border: 1px solid black; padding: 6px; text-align: center;  vertical-align: middle">
            <form action="?" style="padding: 0; margin: 0" method="post">
                <b>Autómata (formato RANK)</b><br>
                <textarea name="fstb" style="min-width: 400px; min-height: 400px; padding: 0; margin: 3px"><?= htmlentities($fstb) ?></textarea><br>
                <button type="submit">Show automata</button>
            </form>
        </div>
        <? if ($processed) { ?>
            <div style="display: inline-block; border: 1px solid black; padding: 6px; text-align: center; vertical-align: middle">
                <a target="_blank" href="<?=$sid?>.png?<?=time()?>" title="View automata">
                    <img src="<?=$sid?>.png?<?=time()?>" style="max-width: 640px" alt="Automata"><br>
                </a>
                <i>(click en la imagen para ampliar)</i>
            </div>
        <? } ?>
    </div>
</body></html>