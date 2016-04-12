<?

error_reporting(E_ALL - E_NOTICE);
ini_set('display_errors', true);

function fstToGV($con) {
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
$baseSid = basename($sid);

if (isset($_POST['fst'])) {
    $fst = $_POST['fst'];
    $fst = str_replace("\r", "", $fst);
}
else if (strlen($sid) && file_exists("{$sid}.fst")) {
    $fst = file_get_contents("{$sid}.fst");
}
else {
    $fst = file_get_contents('example.fst');
}

$processed = false;

if (strlen($sid) && isset($_POST['fst'])) {
    file_put_contents("{$sid}.fst", $fst);
}

if (strlen($fst) && strlen($sid)) {
    $processed = true;
    @unlink("{$sid}.png");
    @unlink("{$sid}.fstb");
    @unlink("{$sid}.rank.txt");
    @unlink("{$sid}.aspic.txt");
    $gv = fstToGV($fst);
    file_put_contents("{$sid}.gv", $gv);
    exec("dot -Tpng {$sid}.gv -o {$sid}.png");
    
    if ($_POST['analyze']) {
        exec("bin/aspic -ranking {$sid}.fst > {$sid}.aspic.txt 2>&1");
        
        if (file_exists("{$baseSid}.fstb")) {
            copy("{$baseSid}.fstb", "{$sid}.fstb");
            unlink("{$baseSid}.fstb");
            unlink("{$baseSid}.log");
        }
    }
}

$invariants = [];
$ranks = [];
$rank = "";
if ($analyzed = file_exists("{$sid}.fstb")) {
    $fstb = file_get_contents("{$sid}.fstb");
    
    preg_match_all("'//invariant ([a-zA-Z0-9\-\_]+) := (.*) ;'", $fstb, $matches);
    foreach($matches[0] as $index => $match) {
        $state = $matches[1][$index];
        $invariants[$state] = explode(" && ", $matches[2][$index]);
    
        if (substr($state, 0, 4) == "____") {
            $initialState = $state;
        }
    }
    
    exec("bin/rank -wcet {$sid}.fstb > {$sid}.rank.txt 2>&1");
    $rank = file_get_contents("{$sid}.rank.txt");
    
    $terminates = false;    
    if (preg_match("'\| +Ranking Function +\|'", $rank, $match)) {
        $terminates = true;
        
        list($rank1, $rank2) = explode($match[0], $rank);
        $rankLines = array_slice(explode("\n", $rank2), 3);
        
        $state = "";
        foreach($rankLines as $index => $line) {
            if (preg_match("'state ([a-zA-Z0-9\-\_]+):'", $line, $match)) {
                $state = $match[1];
                $ranks[$state] = [];
            }
            else if (strlen($line)) {
                if (strlen($state)) {
                    $ranks[$state][] = $line;
                }
            }
            else {
                $state = "";
            }
        }
    }
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
        
        table {
            border-collapse: collapse;
        }
        
        table thead {
            background: #ddd;
        }
        
        table td {
            border: 1px solid #ccc;
            padding: 6px;
            margin: 0;
        }
        
        ul, li {
            margin: 0;
            margin-left: 12px;
            padding: 0;
        }
        
        a {
            text-decoration: none;
        }
        
        a:hover {
            text-decoration: underline;
        }
    </style>
    <meta charset="utf-8">
    <script>
        var rankOutput = <?= json_encode($rank) ?>;
    </script>
</head><body>
    <div style="text-align: center">
        <div style="display: inline-block; border: 1px solid black; padding: 6px; text-align: center;  vertical-align: top">
            <form action="?" style="padding: 0; margin: 0" method="post">
                <b>Autómata (formato RANK)</b><br>
                <textarea name="fst" style="min-width: 400px; min-height: 400px; padding: 0; margin: 3px"><?= htmlentities($fst) ?></textarea><br>
                <input type="checkbox" name="analyze" <?=$_POST['analyze'] ? 'checked' : ''?>> Análisis completo
                <div style="display: inline-block; width: 20px"></div>
                <button type="submit">Mostrar autómata</button>
            </form>
        </div>
        <? if ($processed) { ?>
            <div style="display: inline-block; vertical-align: top">
                <div style="border: 1px solid black; padding: 6px; text-align: center; vertical-align: middle">
                    <a target="_blank" href="<?=$sid?>.png?<?=time()?>" title="View automata">
                        <img src="<?=$sid?>.png?<?=time()?>" style="max-width: 640px" alt="Automata"><br>
                    </a>
                    <i>(click en la imagen para ampliar)</i>
                </div>
                
                <? if ($analyzed) { ?>
                    <br>
                    <div style="border: 1px solid black; padding: 6px; text-align: center; vertical-align: middle">
                        <table style="width: 100%; line-height: 1.5em">
                            <thead><tr>
                                <td>Estado</td>
                                <td>Invariantes</td>
                                <td>Función de rango</td>
                            </tr></thead>
                        <?
                            foreach ($invariants as $state => $conditions) {
                                ?>
                                <tr>
                                    <td><?= $state ?></td>
                                    <td style="text-align: left">
                                        <ul>
                                        <?
                                            foreach ($conditions as $condition) {
                                                if ($state == $initialState || !in_array($condition, $invariants[$initialState])) {
                                                    $condition = preg_replace("'(>=|=|<=|>|<|\+|\-|\*)'", " $1 ", $condition);
                                                    $condition = str_replace("__o", "<sub>0</sub>", $condition);
                                                    ?>
                                                    <li><?= $condition ?></li>
                                                    <?
                                                }
                                            }
                                        ?>
                                        </ul>
                                    </td>
                                    <td style="text-align: left">
                                        <ul>
                                        <?
                                            if (!$terminates) {
                                                ?>
                                                <a href="javascript:void(0)" onclick="alert(rankOutput)">
                                                    No disponible
                                                </a>
                                                <?
                                            }
                                            else {
                                                foreach ($ranks[$state] as $val) {
                                                    $val = preg_replace("'(>=|=|<=|>|<|\+|\-|\*)'", " $1 ", $val);
                                                    $val = str_replace("__o", "<sub>0</sub>", $val);
                                                    ?>
                                                    <li><?= $val ?></li>
                                                    <?
                                                }
                                            }
                                        ?>
                                        </ul>
                                    </td>
                                </tr>
                                <?
                            }
                        ?>
                        </table>
                    </div>
                <? } ?>
                <? if ($_POST['analyze']) { ?>
                    <br>
                    <div style="border: 1px solid black; padding: 6px; text-align: center; vertical-align: middle;">
                        <table style="width: 100%; line-height: 1.5em">
                            <thead><tr>
                                <td colspan="4">Archivos generados</td>
                            </tr></thead>
                            <tr>
                                <? if (file_exists("{$sid}.fst")) { ?><td><a target="_blank" href="<?=$sid?>.fst">FST original</td><a><? } ?>
                                <? if (file_exists("{$sid}.fstb")) { ?><td><a target="_blank" href="<?=$sid?>.fstb">FSTB por ASPIC</td><a><? } ?>
                                <? if (file_exists("{$sid}.aspic.txt")) { ?><td><a target="_blank" href="<?=$sid?>.aspic.txt">Salida de ASPIC</td><a><? } ?>
                                <? if (file_exists("{$sid}.rank.txt")) { ?><td><a target="_blank" href="<?=$sid?>.rank.txt">Salida de RANK</td><a><? } ?>
                            </tr>
                        </table>
                    </div>
                <? } ?>
            </div>
        <? } ?>
    </div>
</body></html>