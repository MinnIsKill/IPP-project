<?php
/**
    ------------------------------------------------------------
    parse.php
    Task #1 for IPP project VUT Brno 2021/22
    Vojtech Kalis, xkalis03@stud.fit.vutbr.cz


    ------------------------------------------------------------
    TODO: run with --help for more info
    ------------------------------------------------------------
    cat test.txt | php8.1 parse.php
    ------------------------------------------------------------
**/

/**
    !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    !                           ATTETION                       !
    !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    I have found out I've done more than necessary for this
    script, namely saving variables into an array and checking
    whether they already exist within given frame before creating 
    them. This is NOT a function of this project's parser, and
    therefore should be erased and carried on into interpreter.
**/

enum ErrHandles: int {
    case ParamErr    = 10; // chybejici parametr skriptu (je-li treba) nebo pouziti zakazané kombinace parametru
    case InFilesErr  = 11; // chyba pri otevirani vstupnich souboru (napr. neexistence, nedostatecne opravneni)
    case OutFilesErr = 12; // chyba pri otevreni vystupnich souboru pro zapis (napr. nedostatecne opravneni, chyba pri zapisu)
    case InternalErr = 99; // interni chyba (neovlivnena vstupnimi soubory ci parametry prikazove radky; napr. chyba alokace pameti)

    case HeaderErr   = 21; // chybna nebo chybejici hlavicka ve zdrojovem kodu zapsanem v IPPcode22
    case OpcodeErr   = 22; // neznamy nebo chybny operacni kod ve zdrojovem kodu zapsanem v IPPcode22
    case LexicalErr  = 23; // jina lexikalni nebo syntakticka chyba zdrojoveho kodu zapsaneho v IPPcode22
}

enum Opcodes {
    case MOVE;
    case CREATEFRAME;
    case PUSHFRAME;
    case POPFRAME;
    case DEFVAR;
    case CALL;
    case RETURN;

    case PUSHS;
    case POPS;

    case ADD;
    case SUB;
    case MUL;
    case IDIV;
    case LT;
    case GT;
    case EQ;
    case AND;
    case OR;
    case NOT;
    case INT2CHAR;
    case STRI2INT;

    case READ;
    case WRITE;

    case CONCAT;
    case STRLEN;
    case GETCHAR;
    case SETCHAR;

    case TYPE;

    case LABEL;
    case JUMP;
    case JUMPIFEQ;
    case JUMPIFNEQ;
    case EXIT;

    case DPRINT;
    case BREAK;
}

class _general {
    public static function basic_comment_check($word){
        if ($word[0] == "#") { echo "Found a comment. Skipping it\n"; return true; }
        else { return false; }
    }

    public static function basic_emptyline_check($line){
        if ($line == NULL) { echo "Found an empty line.\n"; return true; }
        else { return false; }
    }

    // true if everything's fine, false if something goes wrong
    public static function basic_operands_check($line, $received_cnt, $desired_cnt){
        $result = true;
        echo "* \$received_cnt: $received_cnt\n* \$desired_cnt: $desired_cnt\n";
        if ($received_cnt < $desired_cnt) {
            echo "ERROR: missing arguments for DEFVAR\n";
        } else if ($received_cnt > $desired_cnt) {
            $result = _general::basic_comment_check($line[$desired_cnt]);
        }
        return $result;
    }
}

class _frame { //for local and temporary frames
    public $name;
    public $type;
}

class _var {
    public $name;
    public $type;
    public $value;
    public $frame;

    protected static $vars_arr = array();

    public function __construct($name, $frame){
        $this->name = _var::set_name($name);
        $this->frame = $frame;
    }

    // this function should not be used, as it was created for the purpose of checking whether 
    // a given variable already exists within a given frame and handling the resulting situation. 
    // However, later study found that this check should not be done inside parser.
    public static function check_if_exists($searched, $frame) {
        $found = false;
        foreach (self::$vars_arr as $item){
            if (($item == $searched) && ($item->frame == $frame)){
                $found = true;
            }
        }
        return $found;
    }

    function set_name($name) {
        static $cnt = 0;
        $this->name = $name;
        self::$vars_arr[$cnt] = $name;
        //var_dump(self::$vars_arr);
        $cnt++;
    }

    function set_type($type) {
        $this->type = $type;
    }

    function set_value($value) {
        $this->value = $value;
    }

    function get_name() {
        return $this->name;
    }

    function get_type() {
        return $this->type;
    }

    function get_value() {
        return $this->value;
    }
}

/**
    ------------------------
    |   START OF PROGRAM   |
    ------------------------
**/
//define reflection of the Opcodes enum for the purpose of using it in matching input to predefined Opcodes enum values
$OpcodesEnumReflection = new ReflectionEnum(Opcodes::class);

$PreambleCheck_flag = false;

$instr_cnt = 0;

$GF = new _frame;
$GF->name = "Global";

//XML print
fputs(STDOUT,"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
fputs(STDOUT,"<program language=\"IPPcode22\">\n");

while (($line = trim(fgets(STDIN))) || (! feof(STDIN))){ // reads one line from STDIN (feof is to continue even after a whitespace line)
    if ($PreambleCheck_flag == false){ //runs only for the first line (preamble)
        if ($line != ".IPPcode22"){ //if preamble doesn't equal required string
            echo "ERROR: Preamble .IPPcode22 is missing or mistyped. Found '$line' instead\n"; // !!! ERROR HANDLING REQUIRED !!!
        }
    }

    if ((_general::basic_emptyline_check($line) == false) && ($PreambleCheck_flag == true)){ //check if empty line found
        $word_arr = explode(" ", $line); //return word array
        //var_dump($word_arr);

        //echo "$word\n"; // required output
        switch ($word_arr[0]){ //try to match each word at the start of line with an opcode
            case "MOVE":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"MOVE\">\n");
                $instr_cnt++;
                if (_general::basic_operands_check($word_arr, count($word_arr), 3) == false){
                    echo "!!! ERROR detected\n";
                    //exit;
                }

                if ($pre = mb_substr($word_arr[1], 0, 2) == "GF"){
                    if ((_var::check_if_exists(mb_substr($word_arr[1], 3, NULL), "GF")) == true){ //if var exists in GF
                        //TODO: check if $word_arr[2] hints to a variable or a constant, if variable then if that variable 
                        //exists, then check whether types of $word_arr[2] and $word_arr[3] are compatible
                        ;
                        if ($pre2 = mb_substr($word_arr[1], 0, 2) == "GF"){

                        } else if ($pre2 == "LF"){

                        }
                    }
                } else if ($pre == "LF") {
                    if ((_var::check_if_exists(mb_substr($word_arr[1], 3, NULL), "LF")) == true){ //if var exists in LF
                        ;
                    }
                } /**else if ($pre == "TF") {

                }**/ else {
                    echo "ERROR: unknown or incompatible operand found for DEFVAR\n";
                    //exit;
                }
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "CREATEFRAME":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"CREATEFRAME\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "PUSHFRAME":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"PUSHFRAME\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "POPFRAME":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"POPFRAME\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "DEFVAR":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"DEFVAR\">\n");
                $instr_cnt++;

                if (_general::basic_operands_check($word_arr, count($word_arr), 2) == false){
                    echo "!!! ERROR detected\n";
                    //exit;
                }

                if ($pre = mb_substr($word_arr[1], 0, 2) == "GF"){
                    //echo "--created new \$var\n";
                    if ((_var::check_if_exists(mb_substr($word_arr[1], 3, NULL), "GF")) == true){
                        echo "--ERROR: variable with given name already exists within global frame\n";
                    } else {
                        $var = new _var(mb_substr($word_arr[1], 3, NULL), "GF");
                        //echo "--\$var->name is $var->name\n";
                    }
                } else if ($pre == "LF") {
                    ; /// TODO: Check if LF exists, meaning if we're inside a function, if not -> ERROR ???
                } /**else if ($pre == "TF") {

                }**/ else {
                    echo "ERROR: unknown or incompatible operand found for DEFVAR\n";
                    //exit;
                }
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "CALL":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"CALL\">\n");
                $instr_cnt++;
                 /// TODO: create LF as well ???
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "RETURN":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"RETURN\">\n");
                $instr_cnt++;
                 /// TODO: when returning from a function, destroy LF ???
                
                fputs(STDOUT,"</instruction>\n");
                break;
//------
            case "PUSHS":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"PUSHS\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "POPS":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"POPS\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
//------
            case "ADD":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"ADD\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "SUB":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"SUB\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "MUL":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"MUL\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "IDIV":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"IDIV\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "LT":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"LT\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "GT":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"GT\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "EQ":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"EQ\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "AND":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"AND\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "OR":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"OR\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "NOT":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"NOT\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "INT2CHAR":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"INT2CHAR\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "STRI2INT":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"STR2INT\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
//------
            case "READ":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"READ\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "WRITE":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"WRITE\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
//------
            case "CONCAT":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"CONCAT\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "STRLEN":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"STRLEN\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "GETCHAR":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"GETCHAR\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "SETCHAR":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"SETCHAR\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
//------
            case "TYPE":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"TYPE\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
//------
            case "LABEL":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"LABEL\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "JUMP":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"JUMP\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "JUMPIFEQ":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"JUMPIFEQ\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "JUMPIFNEQ":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"JUMPIFNEQ\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "EXIT":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"EXIT\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
//------
            case "DPRINT":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"DPRINT\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
            case "BREAK":
                //XML print
                fputs(STDOUT,"<instruction order=\"$instr_cnt\" opcode=\"BREAK\">\n");
                $instr_cnt++;
                
                fputs(STDOUT,"</instruction>\n");
                break;
//------
            default:
                if (_general::basic_comment_check($word_arr[0]) == true){ //check if comment found
                    break;
                } else {
                    echo "ERROR: Found a line which isn't a comment but doesn't start with a known Opcode either.\n";
                    // !!! ERROR HANDLING REQUIRED !!!
                }
        }
    } else {
        $PreambleCheck_flag = true;
    }
}


/** 
    --------------------------------
    |   AUXILIARY PRINTS SECTION   |
    --------------------------------
**/
// this is how to return errors with specified type (and number)
$return = ErrHandles::OutFilesErr->value;
echo "return value is $return.\n";

$code = "MOVE";
$code_w = "MOVEE";

var_dump($argv);

if (($OpcodesEnumReflection->hasCase($code)) == true){
    echo "yay, $code is in enum\n";
} else {
    echo "uh, $code not found in enum\n";
}
if (($OpcodesEnumReflection->hasCase($code_w)) == true){
    echo "yay, $code_w is in enum\n";
} else {
    echo "uh, $code_w not found in enum\n";
}

//////////////////////////////
/**if (($obj instanceof _var) != true){
    $obj = new _var();
}**/


?>