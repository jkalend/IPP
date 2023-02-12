<?php
function usage() {
    fwrite(STDERR ,"Usage: parse.php [--help] [--stats=file] [--loc] [--comments] [--labels] [--jumps] [--insts] [--vars] [--stack] [--input=file]\n");
    exit(0);
}

function addXML($xml, $name, $order, $args) {
    $xml_in = $xml->addChild('instruction');
    $xml_in->addAttribute('order', $order);
    $xml_in->addAttribute('opcode', $name);
    foreach ($args as $i => $arg) {
        $xml_arg = $xml_in->addChild('arg' . ($i + 1), $arg['value']);
        $xml_arg->addAttribute('type', $arg['type']);
    }
    return $xml;
}

function parseArg($arg) {
    if (preg_match('/^int@/', $arg)) {
        $arg = preg_replace('/^int@/', '', $arg);
        if (preg_match('/^[-+]?[0-9]+$/', $arg)) {
            return ['type' => 'int', 'value' => $arg];
        } else {
            fwrite(STDERR ,"Invalid argument\n");
            exit(23);
        }
    } elseif (preg_match('/^bool@/', $arg)) {
        $arg = preg_replace('/^bool@/', '', $arg);
        if (preg_match('/^(true|false)$/', $arg)) {
            return ['type' => 'bool', 'value' => $arg];
        } else {
            fwrite(STDERR ,"Invalid argument\n");
            exit(23);
        }
    } elseif (preg_match('/^string@/', $arg)) {
        $arg = preg_replace('/^string@/', '', $arg);
        if (preg_match('/^([^#\\\]|(\\\\[0-9]{3}))*$/', $arg)) {
            return ['type' => 'string', 'value' => $arg];
        } else {
            fwrite(STDERR ,"Invalid argument\n");
            exit(23);
        }
    } elseif (preg_match('/^nil@nil$/', $arg)) {
        return ['type' => 'nil', 'value' => 'nil'];
    } elseif (preg_match('/^(GF|LF|TF)@/', $arg)) {
        $pre = preg_split('/@/', $arg);
        $arg = preg_replace('/^(GF|LF|TF)@/', '', $arg);
        if (preg_match('/^([a-zA-Z]|[_$&%*!?-])[a-zA-Z0-9_$&%*!?-]*$/', $arg)) {
            return ['type' => 'var', 'value' => $pre[0] . "@" . $arg];
        } else {
            fwrite(STDERR ,"Invalid argument\n");
            exit(23);
        }
    } elseif (preg_match('/^(int|bool|string)$/', $arg)) {
        return ['type' => 'type', 'value' => $arg];
    } elseif (preg_match('/^[a-zA-Z0-9_$&%*!?-]*$/', $arg)) {
        if (preg_match('/^[a-zA-Z0-9_$&%*!?-]*$/', $arg)) {
            return ['type' => 'label', 'value' => $arg];
        } else {
            fwrite(STDERR ,"Invalid argument\n");
            exit(23);
        }
    } else {
        fwrite(STDERR ,"Invalid argument\n");
        exit(23);
    }
}

function check_ops($args, $x) {
    $symb = ["var", "int", "bool", "string", "nil"];
    switch ($x) {
        case "var":
            if ($args[0]['type'] != 'var') {
                fwrite(STDERR ,"Invalid operands\n");
                exit(23);
            }
            return 1;
        case "symb":
            if (!in_array($args[0]['type'], $symb)) {
                fwrite(STDERR ,"Invalid operands\n");
                exit(23);
            }
            return 1;
        case "label":
            if ($args[0]['type'] != 'label') {
                fwrite(STDERR ,"Invalid operands\n");
                exit(23);
            }
            return 1;
        case "var_symb":
            if ($args[0]['type'] != 'var' || !in_array($args[1]['type'], $symb)) {
                fwrite(STDERR ,"Invalid operands\n");
                exit(23);
            }
            return 1;
        case "var_type":
            if ($args[0]['type'] != 'var' || $args[1]['type'] != 'type') {
                fwrite(STDERR ,"Invalid operands\n");
                exit(23);
            }
            return 1;
        case "var_symb_symb":
            if ($args[0]['type'] != 'var' || !in_array($args[1]['type'], $symb) || !in_array($args[2]['type'], $symb)) {
                fwrite(STDERR ,"Invalid operands\n");
                exit(23);
            }
            return 1;
        case "label_symb_symb":
            if ($args[0]['type'] != 'label' || !in_array($args[1]['type'], $symb) || !in_array($args[2]['type'], $symb)) {
                fwrite(STDERR ,"Invalid operands\n");
                exit(23);
            }
            return 1;
        default:
            return 1;
    }
}

$longopts  = array("help");
$options = getopt("", $longopts, $rest_index);

if ($rest_index > 2) {
    fwrite(STDERR ,"Invalid number of arguments\n");
    exit(1);
}
if (isset($options['help'])) {
    fwrite(STDOUT ,"HELP ME\n");
    exit(0);
} elseif ($rest_index != 1) {
    fwrite(STDERR ,"Invalid argument\n");
    exit(1);
}

$stdin = fopen('php://stdin', 'r');

while (true) {
    if (feof($stdin)) {
        fwrite(STDERR ,"Missing header\n");
        exit(21);
    }
    $line = trim(fgets(STDIN));
    if (strlen($line) == 0) {
        continue;
    }
    if (preg_match('/^#/', $line)) {
        continue;
    }

    $line = strtolower($line);
    $line = trim(preg_split('/#/', $line)[0]);
    if (preg_match('/^\.ippcode23$/', $line)) {
        break;
    } else {
        fwrite(STDERR ,"Invalid header\n");
        exit(21);
    }
    if (feof($stdin)) {
        fwrite(STDERR ,"Missing header\n");
        exit(21);
    }
}

$order = 0;
$zeros = ["CREATEFRAME", "PUSHFRAME", "POPFRAME", "RETURN", "BREAK"];
$only_label = ["CALL", "LABEL", "JUMP"];
$symb_only = ["PUSHS", "EXIT", "DPRINT"];
$var_only = ["DEFVAR", "POPS"];
$var_symb = ["MOVE", "INT2CHAR", "STRLEN", "TYPE", "NOT"];
$var_type = ["READ"];
$var_symb_symb = ["ADD", "SUB", "MUL", "IDIV", "LT", "GT", "EQ", "AND", "OR", "STRI2INT", "CONCAT", "GETCHAR", "SETCHAR"];
$label_symb_symb = ["JUMPIFEQ", "JUMPIFNEQ"];

$out = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<program language="IPPcode23"></program>
XML;

$xml = new SimpleXMLElement($out);

while ($line = fgets(STDIN)) {
    $line = trim($line);
    if (strlen($line) == 0) {
        continue;
    }
    if (preg_match('/^#/', $line)) {
        continue;
    }

    $clean = preg_split('/#/', $line);
    $elements = preg_split('/\s+/', trim($clean[0]));

    $instr = strtoupper($elements[0]);
    $order++;

    if (in_array($instr, $zeros)) {
        if (count($elements) != 1) {
            fwrite(STDERR ,"Invalid number of arguments\n");
            exit(23);
        }
        addXML($xml, $instr, $order, array_map('parseArg', array_slice($elements,1)));

    } elseif (in_array($instr, $only_label)) {
        if (count($elements) != 2) {
            fwrite(STDERR ,"Invalid number of arguments\n");
            exit(23);
        }
        $args = array_map('parseArg', array_slice($elements,1));
        check_ops($args,"label");
        addXML($xml, $instr, $order, $args);
    } elseif (in_array($instr, $var_only)) {
        if (count($elements) != 2) {
            fwrite(STDERR ,"Invalid number of arguments\n");
            exit(23);
        }
        $args = array_map('parseArg', array_slice($elements,1));
        check_ops($args,"var");
        addXML($xml, $instr, $order, $args);
    } elseif (in_array($instr, $symb_only)) {
        if (count($elements) != 2) {
            fwrite(STDERR ,"Invalid number of arguments\n");
            exit(23);
        }
        $args = array_map('parseArg', array_slice($elements,1));
        check_ops($args,"symb");
        addXML($xml, $instr, $order, $args);
    } elseif (in_array($instr, $var_symb)) {
        if (count($elements) != 3) {
            fwrite(STDERR ,"Invalid number of arguments\n");
            exit(23);
        }
        $args = array_map('parseArg', array_slice($elements,1));
        check_ops($args,"var_symb");
        addXML($xml, $instr, $order, $args);
    } elseif (in_array($instr, $var_type)) {
        if (count($elements) != 3) {
            fwrite(STDERR ,"Invalid number of arguments\n");
            exit(23);
        }
        $args = array_map('parseArg', array_slice($elements,1));
        check_ops($args,"var_type");
        addXML($xml, $instr, $order, $args);
    } elseif (in_array($instr, $var_symb_symb)) {
        if (count($elements) != 4) {
            fwrite(STDERR ,"Invalid number of arguments\n");
            exit(23);
        }
        $args = array_map('parseArg', array_slice($elements,1));
        check_ops($args,"var_symb_symb");
        addXML($xml, $instr, $order, $args);
    } elseif (in_array($instr, $label_symb_symb)) {
        if (count($elements) != 4) {
            fwrite(STDERR ,"Invalid number of arguments\n");
            exit(23);
        }
        $args = array_map('parseArg', array_slice($elements,1));
        check_ops($args,"label_symb_symb");
        addXML($xml, $instr, $order, $args);
    } elseif ($instr == "WRITE") {
        if (count($elements) < 2) {
            fwrite(STDERR ,"Invalid number of arguments\n");
            exit(23);
        }
        $args = array_map('parseArg', array_slice($elements,1));
        for ($i = 0; $i < count($args); $i++) {
            check_ops(array_slice($args, $i),"symb");
        }
        addXML($xml, $instr, $order, $args);
    } else {
        fwrite(STDERR ,"Invalid instruction\n");
        exit(22);
    }
}

echo $xml->asXML();



?>

