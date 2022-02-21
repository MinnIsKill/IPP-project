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

ini_set('display_errors', 'stderr'); //to print warning to stderr

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
    public static $instr_cnt = 1;
    public static $output;
    //public static $line_num = 1;

    /**
     * 
    **/
    public static function basic_comment_check($word){
        if ($word[0] == "#") { echo "Found a comment. Skipping it\n"; return true; }
        else { return false; }
    }

    /**
     * 
    **/
    public static function basic_emptyline_check($line){
        if ($line == NULL) { echo "Found an empty line.\n"; return true; }
        else { return false; }
    }

    /** checks whether number of operands received equals the amount required for given opcode
     *  @return: true if everything's fine, false if something goes wrong 
    **/
    public static function basic_operands_check($line, $received_cnt, $desired_cnt){
        $result = true;
        if ($received_cnt < $desired_cnt) {
            $result = false;
        } else if ($received_cnt > $desired_cnt) {
            $result = _general::basic_comment_check($line[$desired_cnt]);
        }

        if ($result == false){
            fputs(STDERR,"! basic_operands_check returned ERROR for line:  ");
            foreach ($line as $word){
                fputs(STDERR,"$word ");
            }
            fputs(STDERR,"\n");
            //exit (error code);
        }
    }

    /** 
     * 
    **/
    public static function basic_argtype_check($word){
        $type = "";
        if (($pre = mb_substr($word, 0, 3)) == "GF@" || $pre == "LF@" || $pre == "TF@"){
            $type = "var";
        } else if (($pre = mb_substr($word, 0, 4)) == "int@"){
            $type = "int";
        } else if ($pre == "nil@"){
            $type = "nil";
        } else if (($pre = mb_substr($word, 0, 5)) == "bool@"){
            $type = "bool";
        } else if (($pre = mb_substr($word, 0, 7)) == "string@"){
            $type = "string";
        }

        if ($type == ""){ //if not matched yet
            if (($pre = mb_substr($word, 0, 3)) == "int" || $pre == "nil" || ($pre = mb_substr($word, 0, 4)) == "bool" ||
            ($pre = mb_substr($word, 0, 6)) == "string") {
                $type = "type";
            } else {
                $type = "label";
            }
        }

        return $type;
    }

    /** Ran after every opcode match. Handles initial prints and checks
     *  @var instr_cnt:   globally kept instruction ID
     *  @var str:         name of opcode to handle
     *  @var line:        line of code to handle
     *  @var desired_cnt: number of required opcode arguments
    **/
    public static function opcode_start($str, $line, $desired_cnt){
        $cnt = _general::$instr_cnt;
        _general::$output .="<instruction order=\"$cnt\" opcode=\"$str\">\n";
        _general::$instr_cnt++;
        _general::basic_operands_check($line, count($line), $desired_cnt);
    }

    /** 
     *   PRINT FUNCTION FOR <var>
    **/
    public static function create_var_print($num, $word){
        if (($type = _general::basic_argtype_check($word)) != "var"){
            _general::$output .="ERROR: passing an unknown or invalid opcode argument where \"var\" is required\n";
            //exit (error code);
        }
        for ($i = $num; $i > 0; $i--){ _general::$output .="    "; }
        $word_modif = _general::special_char_checker($word, "var");
        _general::$output .="<arg$num type=\"$type\">$word_modif</arg$num>\n";
    }

    /** 
     *   PRINT FUNCTION FOR <symbol>
    **/
    public static function create_symb_print($num, $word){
        if (($type = _general::basic_argtype_check($word)) == "type" || $type == "label"){
            _general::$output .="ERROR: passing an unknown or invalid opcode argument where \"symbol\" is required\n";
            //exit (error code);
        }
        for ($i = $num; $i > 0; $i--){ _general::$output .="    "; }
        if ($type == "var") {
            _general::$output .="<arg$num type=\"$type\">$word</arg$num>\n";
        } else if ($type == "string") {
            _general::create_string_print($num, $word);
        } else {
            $length = strlen($type)+1;
            $word_modif = mb_substr($word, $length, NULL);
            _general::$output .="<arg$num type=\"$type\">$word_modif</arg$num>\n";
        }
    }

    /** 
     *   PRINT FUNCTION FOR <label>
    **/
    public static function create_label_print($num, $word){
        if (($type = _general::basic_argtype_check($word)) != "label"){
            _general::$output .="ERROR: passing an unknown or invalid opcode argument where \"label\" is required\n";
            //exit (error code);
        }
        for ($i = $num; $i > 0; $i--){ _general::$output .="    "; }
        _general::$output .="<arg$num type=\"$type\">$word</arg$num>\n";
    }

    /** 
     *   PRINT FUNCTION FOR <type>
    **/
    public static function create_type_print($num, $word){
        if (($type = _general::basic_argtype_check($word)) != "type"){
            _general::$output .="ERROR: passing an unknown or invalid opcode argument where \"type\" is required\n";
            //exit (error code);
        }
        for ($i = $num; $i > 0; $i--){ _general::$output .="    "; }
        _general::$output .="<arg$num type=\"$type\">$word</arg$num>\n";
    }

    /**
     *   PRINT FUNCTION FOR STRINGS
    **/
    public static function create_string_print($num, $word){
        $string = _general::special_char_checker($word, "string");
        _general::$output .="<arg$num type = \"string\">$string</arg$num>\n";
    }

    /**
     * 
    **/
    public static function special_char_checker($word, $type){
        $char_arr = mb_str_split($word);
        $string = "";
        if ($type == "string"){
            $tmp_cnt = 0;
            $char_cnt = 1;
            foreach($char_arr as $char){
                if ($tmp_cnt != 7){
                    $tmp_cnt++;
                } else {
                    if ($char == "\""){ $string .= "&quot;"; }
                    else if ($char == "&"){ $string .= "&amp;"; }
                    else if ($char == "'"){ $string .= "&apos;"; }
                    else if ($char == "<"){ $string .= "&lt;"; }
                    else if ($char == ">"){ $string .= "&gt;"; }
                    else if ($char == "\\"){
                        _general::escseq_checker($word, $char_cnt);
                        $string .= "\\";
                    }
                    else { $string .= $char; }
                }
                $char_cnt++;
            }
            $char_cnt = 0;
        } else if ($type == "var"){
            $tmp_cnt = 0;
            $firstchar = true;
            foreach($char_arr as $char){
                if ($tmp_cnt != 3){
                    $string .= $char;
                    $tmp_cnt++;
                } else {
                    if ($firstchar == true){
                        $firstchar = false;
                        if (((ord($char) >= ord("A")) && (ord($char) <= ord("Z"))) ||
                            ((ord($char) >= ord("a")) && (ord($char) <= ord("z"))) ||
                            ord($char) == ord("_") || ord($char) == ord("-") || ord($char) == ord("$") ||
                            ord($char) == ord("&") || ord($char) == ord("%") || ord($char) == ord("*") ||
                            ord($char) == ord("!") || ord($char) == ord("?")){
                                $string .= $char;
                        } else { fputs(STDERR,"ERROR:!!!!  Character: $char\n"); }
                    } else if (((ord($char) >= ord("0")) && (ord($char) <= ord("9"))) ||
                        ((ord($char) >= ord("A")) && (ord($char) <= ord("Z"))) ||
                        ((ord($char) >= ord("a")) && (ord($char) <= ord("z"))) ||
                        ord($char) == ord("_") || ord($char) == ord("-") || ord($char) == ord("$") ||
                        ord($char) == ord("&") || ord($char) == ord("%") || ord($char) == ord("*") ||
                        ord($char) == ord("!") || ord($char) == ord("?")){
                            $string .= $char;
                    } else { fputs(STDERR,"ERROR:!!!!  Character: $char\n"); }
                }
            }
        }
        return $string;
    }

    public static function escseq_checker($word, $char_cnt){
        $charac = mb_substr($word, $char_cnt, 3);
        $char_arr = mb_str_split($charac);
        foreach ($char_arr as $char){
            if ($char >= 0 && $char <= 9){
                ;
            } else {
                fputs(STDERR,"ERROR: incompatible character '$char' found in an escape sequence\n");
            }
        }
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
    // However, later study found that this check should not be done inside parser, but should be left
    // for the interpreter to handle.
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

    function set_frame($frame) {
        $this->frame = $frame;
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

    function get_frame() {
        return $this->frame;
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

$GF = new _frame;
$GF->name = "Global";


while (($line = trim(fgets(STDIN))) || (! feof(STDIN))){ // reads one line from STDIN (feof is to continue even after a whitespace line)
    if ($PreambleCheck_flag == false){ //runs only for the first line (preamble)
        if ($line != ".IPPcode22"){ //if preamble doesn't equal required string
            fputs(STDERR,"ERROR: Preamble .IPPcode22 is missing or mistyped. Found '$line' instead\n"); // !!! ERROR HANDLING REQUIRED !!!
            $return = ErrHandles::OutFilesErr->value;
        } else {
            //XML print
            _general::$output .="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            _general::$output .="<program language=\"IPPcode22\">\n";
        }
    }

    if ((_general::basic_emptyline_check($line) == false) && ($PreambleCheck_flag == true)){ //check if empty line found
        $word_arr = explode(" ", $line); //return word array
        //var_dump($word_arr);

        //echo "$word\n"; // required output
        switch ($word_arr[0]){ //try to match each word at the start of line with an opcode
/** ⟨var⟩  = variable (GF@foo) 
 *  ⟨symb⟩ = variable (GF@foo) or constant (int, bool, string, nil)
**/
/** MOVE ⟨var⟩ ⟨symb⟩ **/            
            case strcasecmp($word_arr[0], "MOVE") == 0:
                _general::opcode_start("MOVE", $word_arr, 3);

                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                
                _general::$output .="</instruction>\n";
                break;
/** CREATEFRAME **/
            case strcasecmp($word_arr[0], "CREATEFRAME") == 0:
                _general::opcode_start("CREATEFRAME", $word_arr, 1);
                
                _general::$output .="</instruction>\n";
                break;
/** PUSHFRAME **/
            case strcasecmp($word_arr[0], "PUSHFRAME") == 0:
                _general::opcode_start("PUSHFRAME", $word_arr, 1);
                
                _general::$output .="</instruction>\n";
                break;
/** POPFRAME **/
            case strcasecmp($word_arr[0], "POPFRAME") == 0:
                _general::opcode_start("POPFRAME", $word_arr, 1);
                
                _general::$output .="</instruction>\n";
                break;
/** DEFVAR ⟨var⟩ **/
            case strcasecmp($word_arr[0], "DEFVAR") == 0:
                _general::opcode_start("DEFVAR", $word_arr, 2);

                _general::create_var_print(1, $word_arr[1]);
                
                _general::$output .="</instruction>\n";
                break;
/** CALL ⟨label⟩ **/
            case strcasecmp($word_arr[0], "CALL") == 0:
                _general::opcode_start("CALL", $word_arr, 2);

                _general::create_label_print(1, $word_arr[1]);
                
                _general::$output .="</instruction>\n";
                break;
/** RETURN **/
            case strcasecmp($word_arr[0], "RETURN") == 0:
                _general::opcode_start("RETURN", $word_arr, 1);
                
                _general::$output .="</instruction>\n";
                break;
//------
/** PUSHS ⟨symb⟩ **/
            case strcasecmp($word_arr[0], "PUSHS") == 0:
                _general::opcode_start("PUSHS", $word_arr, 2);

                _general::create_symb_print(1, $word_arr[1]);
                
                _general::$output .="</instruction>\n";
                break;
/** POPS ⟨var⟩ **/
            case strcasecmp($word_arr[0], "POPS") == 0:
                _general::opcode_start("POPS", $word_arr, 2);

                _general::create_var_print(1, $word_arr[1]);
                
                _general::$output .="</instruction>\n";
                break;
//------
/** ADD ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "ADD") == 0:
                _general::opcode_start("ADD", $word_arr, 4);

                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(2, $word_arr[3]);
                
                _general::$output .="</instruction>\n";
                break;
/** SUB ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "SUB") == 0:
                _general::opcode_start("SUB", $word_arr, 4);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(3, $word_arr[3]);

                _general::$output .="</instruction>\n";
                break;
/** MUL ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "MUL") == 0:
                _general::opcode_start("MUL", $word_arr, 4);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(3, $word_arr[3]);
                
                _general::$output .="</instruction>\n";
                break;
/** IDIV ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "IDIV") == 0:
                _general::opcode_start("IDIV", $word_arr, 4);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(3, $word_arr[3]);
                
                _general::$output .="</instruction>\n";
                break;
/** LT ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "LT") == 0:
                _general::opcode_start("LT", $word_arr, 4);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(3, $word_arr[3]);
                
                _general::$output .="</instruction>\n";
                break;
/** GT ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "GT") == 0:
                _general::opcode_start("GT", $word_arr, 4);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(3, $word_arr[3]);
                
                _general::$output .="</instruction>\n";
                break;
/** EQ ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "EQ") == 0:
                _general::opcode_start("EQ", $word_arr, 4);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(3, $word_arr[3]);
                
                _general::$output .="</instruction>\n";
                break;
/** AND ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "AND") == 0:
                _general::opcode_start("AND", $word_arr, 4);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(3, $word_arr[3]);
                
                _general::$output .="</instruction>\n";
                break;
/** OR ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "OR") == 0:
                _general::opcode_start("OR", $word_arr, 4);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(3, $word_arr[3]);
                
                _general::$output .="</instruction>\n";
                break;
/** NOT ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "NOT") == 0:
                _general::opcode_start("NOT", $word_arr, 4);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(3, $word_arr[3]);
                
                _general::$output .="</instruction>\n";
                break;
/** INT2CHAR ⟨var⟩ ⟨symb⟩ **/
            case strcasecmp($word_arr[0], "INT2CHAR") == 0:
                _general::opcode_start("INT2CHAR", $word_arr, 3);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);

                _general::$output .="</instruction>\n";
                break;
/** STR2INT ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "STR2INT") == 0:
                _general::opcode_start("STR2INT", $word_arr, 4);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(3, $word_arr[3]);
                
                _general::$output .="</instruction>\n";
                break;
//------
/** READ ⟨var⟩ ⟨type⟩ **/
            case strcasecmp($word_arr[0], "READ") == 0:
                _general::opcode_start("READ", $word_arr, 3);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_type_print(2, $word_arr[2]);
                
                _general::$output .="</instruction>\n";
                break;
/** WRITE ⟨symb⟩ **/
            case strcasecmp($word_arr[0], "WRITE") == 0:
                _general::opcode_start("WRITE", $word_arr, 2);

                _general::create_symb_print(1, $word_arr[1]);

                _general::$output .="</instruction>\n";
                break;
//------
/** CONCAT ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "CONCAT") == 0:
                _general::opcode_start("CONCAT", $word_arr, 4);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(3, $word_arr[3]);
                
                _general::$output .="</instruction>\n";
                break;
/** STRLEN ⟨var⟩ ⟨symb⟩ **/
            case strcasecmp($word_arr[0], "STRLEN") == 0:
                _general::opcode_start("STRLEN", $word_arr, 3);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                
                _general::$output .="</instruction>\n";
                break;
/** GETCHAR ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "GETCHAR") == 0:
                _general::opcode_start("GETCHAR", $word_arr, 4);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(3, $word_arr[3]);
                
                _general::$output .="</instruction>\n";
                break;
/** SETCHAR ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "SETCHAR") == 0:
                _general::opcode_start("SETCHAR", $word_arr, 4);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(3, $word_arr[3]);
                
                _general::$output .="</instruction>\n";
                break;
//------
/** TYPE ⟨var⟩ ⟨symb⟩ **/
            case strcasecmp($word_arr[0], "TYPE") == 0:
                _general::opcode_start("TYPE", $word_arr, 3);
                
                _general::create_var_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                
                _general::$output .="</instruction>\n";
                break;
//------
/** LABEL ⟨label⟩ **/
            case strcasecmp($word_arr[0], "LABEL") == 0:
                _general::opcode_start("LABEL", $word_arr, 2);
                
                _general::create_label_print(1, $word_arr[1]);
                
                _general::$output .="</instruction>\n";
                break;
/** JUMP ⟨label⟩ **/
            case strcasecmp($word_arr[0], "JUMP") == 0:
                _general::opcode_start("JUMP", $word_arr, 2);
                
                _general::create_label_print(1, $word_arr[1]);
                
                _general::$output .="</instruction>\n";
                break;
/** JUMPIFEQ ⟨label⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "JUMPIFEQ") == 0:
                _general::opcode_start("JUMPIFEQ", $word_arr, 4);
                
                _general::create_label_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(3, $word_arr[3]);
                
                _general::$output .="</instruction>\n";
                break;
/** JUMPIFNEQ ⟨label⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "JUMPIFNEQ") == 0:
                _general::opcode_start("JUMPIFNEQ", $word_arr, 4);
                
                _general::create_label_print(1, $word_arr[1]);
                _general::create_symb_print(2, $word_arr[2]);
                _general::create_symb_print(3, $word_arr[3]);
                
                _general::$output .="</instruction>\n";
                break;
/** EXIT ⟨symb⟩ **/
            case strcasecmp($word_arr[0], "EXIT") == 0:
                _general::opcode_start("EXIT", $word_arr, 2);
                
                _general::create_symb_print(1, $word_arr[1]);
                
                _general::$output .="</instruction>\n";
                break;
//------
/** DPRINT ⟨symb⟩ **/
            case strcasecmp($word_arr[0], "DPRINT") == 0:
                _general::opcode_start("DPRINT", $word_arr, 2);
                
                _general::create_symb_print(1, $word_arr[1]);
                
                _general::$output .="</instruction>\n";
                break;
/** BREAK **/
            case strcasecmp($word_arr[0], "BREAK") == 0:
                _general::opcode_start("BREAK", $word_arr, 1);
                
                _general::$output .="</instruction>\n";
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
//$return = ErrHandles::OutFilesErr->value;

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

fputs (STDOUT,"\n========================================================================\n\n");

_general::$output .="</program>\n";

fputs(STDOUT,_general::$output);

exit (0);

?>