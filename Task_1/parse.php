<?php
/**
    ------------------------------------------------------------
    parse.php
    Task #1 for IPP project VUT Brno 2021/22
    Vojtech Kalis, xkalis03@stud.fit.vutbr.cz


    ------------------------------------------------------------
    run with --help for more info
    ------------------------------------------------------------
    cat test.txt | php8.1 parse.php
    ------------------------------------------------------------
**/

ini_set('display_errors', 'stderr'); //to print warnings to stderr

/**
 *  Enum containing error code handles and their respective values
**/
enum ErrHandles: int {
    case ParamErr    = 10; // missing script parameter (if needed) or an attempt at using a forbidden parameter combination
    case InFilesErr  = 11; // error opening input files (e.g. they don't exist, insufficient permission)
    case OutFilesErr = 12; // error opening output files for writing (e.g. insufficient permission, error while writing)
    case InternalErr = 99; // internal error (not affected by input files or command line parameters; e.g. memory allocation error)

    case PreambleErr = 21; // wrong or missing preamble in source file written in IPPcode22
    case OpcodeErr   = 22; // wrong or unknown opcode in source file written in IPPcode22
    case LexSynErr   = 23; // other lexical or syntactical error in source file written in IPPcode22
}

/** GENERAL CLASS
 *    - contains public variables and some basic functions used throughout the program
 *    - to find out more about included functions, refer to their individual descriptions
**/
class _general {
    public static $instr_cnt = 1; // counter for instructions, starts at '1'
    public static $output; // array to which output is saved
    //public static $line_num = 1;

    public static $help_msg = // a static string printed out when '--help' is called
"Script of type filter (parse.php in PHP 8.1) loads a source file written in IPPcode22 from stdin, checks the lexical
and syntactical correctness of written code and prints out its XML reprezentation.

  Script parameters
      --help    prints a help message to stdout (doesn't load any input). Returns 0.

  General error codes:
        10      missing script parameter (if needed) or an attempt at using a forbidden parameter combination
        11      error opening input files (e.g. they don't exist, insufficient permission)
        12      error opening output files for writing (e.g. insufficient permission, error while writing)
        99      internal error (not affected by input files or command line parameters; e.g. memory allocation error)

  Error codes specific for parser:
        21      wrong or missing preamble in source file written in IPPcode22
        22      wrong or unknown opcode in source file written in IPPcode22
        23      other lexical or syntactical error in source file written in IPPcode22\n";



    /** basic_comment_check
     *  --------------------------------------------------------------------
     *  checks whether string or line starts with "#" (ergo is a comment)
     *  @param word: string to be checked
     *  @return: true if comment found, false if not
    **/
    public static function basic_comment_check($word){
        if ($word[0] == "#") { /**echo "Found a comment. Skipping it\n";**/ return true; }
        else { return false; }
    }

    /** ext_comment_check
     *  --------------------------------------------------------------------
     *  checks whether string contains "#" (ergo has a comment)
     *  @param word: string to be checked
     *  @return: true if comment found, false if not
    **/
    public static function ext_comment_check($word){
        $char_arr = mb_str_split($word);
        foreach ($char_arr as $char){
            if ($char == "#"){ return true; }
            else { ; }
        }
        return false;
    }

    /** comment_cutter
     *  --------------------------------------------------------------------
     *  finds comment in string and cuts it out
     *  @param word: string containing comment
     *  @return: string without a comment
    **/
    public static function comment_cutter($word){
        $char_arr = mb_str_split($word);
        $string = "";
        foreach ($char_arr as $char){
            if ($char == "#"){ return $string; }
            else { $string .= $char; }
        }
        return $string;
    }

    /** basic_emptyline_check
     *  --------------------------------------------------------------------
     *  checks whether line is empty (containst no chars)
     *  @param line: line to be checked
     *  @return: true if empty line found, false if not
    **/
    public static function basic_emptyline_check($line){
        if ($line == NULL) { /**echo "Found an empty line. Skipping it.\n";**/ return true; }
        else { return false; }
    }

    /** basic_operands_check
     *  --------------------------------------------------------------------
     *  checks whether number of operands received equals the amount required for given opcode
     *  @param line: line containing the opcode and operands to be checked
     *  @param received_cnt: number of operands (+ opcode) received
     *  @param desired_cnt: number of operands (+opcode) needed
     *  @return: true if everything's fine, false if something goes wrong 
    **/
    public static function basic_operands_check($line, $received_cnt, $desired_cnt){
        $result = true;
        if ($received_cnt < $desired_cnt) {
            $result = false;
        } else if ($received_cnt > $desired_cnt) {
            $result = _general::ext_comment_check($line[$desired_cnt-1]); // check if a comment is in string
            if ($result == false){
                $result = _general::basic_comment_check($line[$desired_cnt]); // check if a comment is directly behind last desired operand
            }
        }

        if ($result == false){ // if number of operands wrong but no comment found (where it wouldn't be bothersome)
            fputs(STDERR,"ERROR[23]: Too many/few operands received for opcode on line: ");
            foreach ($line as $word){ // can't print $line outright, array to string conversion not allowed
                fputs(STDERR," $word");
            }
            fputs(STDERR,"\n");
            exit (ErrHandles::LexSynErr->value);
        }
    }

    /** basic_argtype_check
     *  --------------------------------------------------------------------
     *  tries to match string with a known argtype
     *  returns "label" by default (whether that's an error is handled elsewhere)
     *  @param word: string to be matched
     *  @return: name of type of operand based on received string
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

    /** opcode_start
     *  --------------------------------------------------------------------
     *  Ran after every opcode match. Handles initial prints and checks
     *  @param instr_cnt: globally kept instruction ID
     *  @param str: name of opcode to handle
     *  @param line: line of code to handle
     *  @param desired_cnt: number of required opcode arguments
    **/
    public static function opcode_start($str, $line, $desired_cnt){
        $cnt = _general::$instr_cnt;
        _general::$output .= "    <instruction order=\"$cnt\" opcode=\"$str\">\n";
        _general::$instr_cnt++;
        _general::basic_operands_check($line, count($line), $desired_cnt);
    }

}

/** PRINTS CLASS
 *    - contains functions used to handle output printing of operands
 *    - to find out more about included functions, refer to their individual descriptions
**/
class _prints {
    /** PRINT FUNCTION FOR <var>
     *  --------------------------------------------------------------------
     *  checks whether var name is lexically and syntactically correct and if so, handles its output printing
     *  @param num: number of operand (arg1, arg2, arg3)
     *  @param word: string to be checked
    **/
    public static function create_var_print($num, $word){
        if (($type = _general::basic_argtype_check($word)) != "var"){
            fputs(STDERR,"ERROR[23]: passing an unknown or invalid opcode argument where \"var\" is required.\n           Offending argument: $word\n");
            exit (ErrHandles::LexSynErr->value);
        }
        
        if ((preg_match('/^(GF@|LF@|TF@)+([A-Z]|[a-z]|\_|\-|\$|\&|\%|\*|\!|\?)(([0-9]|[A-Z]|[a-z]|\_|\-|\$|\&|\%|\*|\!|\?))*$/u',$word)) != true){ // check if string is fine
            //if not fine
            fputs(STDERR,"ERROR[23]: variable name error for:   $word\n");
            exit (ErrHandles::LexSynErr->value);
        }
        if ((_general::ext_comment_check($word)) == true){ // check if a comment is in string
            $word = _general::comment_cutter($word); // if so, cut comment out of string
        }

        $string = str_replace("&", "&amp;", $word); // replace special characters with equivalent XML entities

        for ($i = /**$num**/2; $i > 0; $i--){ _general::$output .="    "; }
        _general::$output .="<arg$num type=\"$type\">$string</arg$num>\n";
    }

    /** PRINT FUNCTION FOR <symbol>
     *  --------------------------------------------------------------------
     *  checks whether symbol name is lexically and syntactically correct and if so, handles its output printing
     *  both 'var' and 'string' printing are handled through their respective functions (to avoid duplicity)
     *  @param num: number of operand (arg1, arg2, arg3)
     *  @param word: string to be checked
    **/
    public static function create_symb_print($num, $word){
        //for ($i = $num; $i > 0; $i--){ _general::$output .="    "; }
        if (($type = _general::basic_argtype_check($word)) == "type" || $type == "label"){
            fputs(STDERR,"ERROR[23]: passing an unknown or invalid opcode argument where \"symbol\" is required.\n           Offending argument: $word\n");
            exit (ErrHandles::LexSynErr->value);
        } else if ($type == "var") {
            _prints::create_var_print($num, $word);
        } else if ($type == "string") {
            _prints::create_string_print($num, $word);
        } else {
            if ((_general::ext_comment_check($word)) == true){ // check if a comment is in string
                $word = _general::comment_cutter($word); // if so, cut comment out of string
            }

            if (($type == "nil") && (($post = mb_substr($word, 4, NULL)) == "nil")){
                for ($i = /**$num**/2; $i > 0; $i--){ _general::$output .="    "; }
                _general::$output .="<arg$num type=\"$type\">$post</arg$num>\n";
            } else if (($type == "bool") && ((($post = mb_substr($word, 5, NULL)) == "true") || ($post == "false"))){
                for ($i = /**$num**/2; $i > 0; $i--){ _general::$output .="    "; }
                _general::$output .="<arg$num type=\"$type\">$post</arg$num>\n";
            } else if (($type == "int") && (is_numeric($post = mb_substr($word, 4, NULL)) == true)){
                for ($i = /**$num**/2; $i > 0; $i--){ _general::$output .="    "; }
                _general::$output .="<arg$num type=\"$type\">$post</arg$num>\n";
            } else {
                fputs(STDERR,"ERROR[23]: passing an opcode argument with invalid value.\n           Offending argument: $word\n");
                exit (ErrHandles::LexSynErr->value);
            }

            //$length = strlen($type)+1;
            //$word_modif = mb_substr($word, $length, NULL);
            //
            //for ($i = /**num**/2; $i > 0; $i--){ _general::$output .="    "; }
            //_general::$output .="<arg$num type=\"$type\">$word_modif</arg$num>\n";
        }
    }

    /** PRINT FUNCTION FOR <label>
     *  --------------------------------------------------------------------
     *  checks whether label name is lexically and syntactically correct and if so, handles its output printing
     *  @param num: number of operand (arg1, arg2, arg3)
     *  @param word: string to be checked
    **/
    public static function create_label_print($num, $word){
        if (($type = _general::basic_argtype_check($word)) != "label"){
            fputs(STDERR,"ERROR[23]: passing an unknown or invalid opcode argument where \"label\" is required.\n           Offending argument: $word\n");
            exit (ErrHandles::LexSynErr->value);
        }
        
        if ((_general::ext_comment_check($word)) == true){ // check if a comment is in string
            $word = _general::comment_cutter($word); // if so, cut comment out of string
        }
        if ((preg_match('/^([A-Z]|[a-z]|\_|\-|\$|\&|\%|\*|\!|\?)(([0-9]|[A-Z]|[a-z]|\_|\-|\$|\&|\%|\*|\!|\?))*$/u',$word)) != true){ // check if string is fine
            //if not fine
            fputs(STDERR,"ERROR[23]: label name error for:   $word\n");
            exit (ErrHandles::LexSynErr->value);
        }

        $string = str_replace("&", "&amp;", $word); // replace special characters with equivalent XML entities

        for ($i = /**$num**/2; $i > 0; $i--){ _general::$output .="    "; }
        _general::$output .="<arg$num type=\"$type\">$string</arg$num>\n";
    }

    /** PRINT FUNCTION FOR <type>
     *  --------------------------------------------------------------------
     *  checks whether type name is lexically and syntactically correct and if so, handles its output printing
     *  @param num: number of operand (arg1, arg2, arg3)
     *  @param word: string to be checked
    **/
    public static function create_type_print($num, $word){
        if (($type = _general::basic_argtype_check($word)) != "type"){
            fputs(STDERR,"ERROR[23]: passing an unknown or invalid opcode argument where \"type\" is required\n           Offending argument: $word\n");
            exit (ErrHandles::LexSynErr->value);
        }

        if (($word != "nil") && ($word != "int") && ($word != "bool") && ($word != "string")){
            fputs(STDERR,"ERROR[23]: type not recognized:   $word\n");
            exit (ErrHandles::LexSynErr->value);
        }
        if ((_general::ext_comment_check($word)) == true){ // check if a comment is in string
            $word = _general::comment_cutter($word); // if so, cut comment out of string
        }

        for ($i = /**$num**/2; $i > 0; $i--){ _general::$output .="    "; }
        _general::$output .="<arg$num type=\"$type\">$word</arg$num>\n";
    }

    /** PRINT FUNCTION FOR STRINGS
     *  --------------------------------------------------------------------
     *  checks whether string is lexically and syntactically correct and if so, handles its output printing
     *  handles escape sequences (whether they're not interrupted) and XML entity replacements as well
     *  @param num: number of operand (arg1, arg2, arg3)
     *  @param word: string to be checked
    **/
    public static function create_string_print($num, $word){
        $string = "";
        $tmp_word = $word." "; // a quick hack to solve the issue of the following regex not working when offending escseq is at the end of string
        if ((_general::ext_comment_check($word)) == true){ // check if a comment is in string
            $word = _general::comment_cutter($word); // if so, cut comment out of string
        }
        if ((preg_match('/(\\\\[^0-9])|(\\\\[0-9][^0-9])|(\\\\[0-9][0-9][^0-9])/u',$tmp_word)) == true){ // option #2:  (\\[^\d])|(\\\d[^\d])|(\\\d\d[^\d])
            // if '\[INCOMPATIBLE]' or '\[0-9][INCOMPATIBLE]' or '\[0-9][0-9][INCOMPATIBLE]' found
            fputs(STDERR,"ERROR[23]: Wrong format of an escape sequence found in string:  $word\n");
            exit (ErrHandles::LexSynErr->value);
        }

        $special_chars = array("&", "'", "<", ">", "\"");
        $replacements  = array("&amp;", "&apos;", "&lt;", "&gt;", "&quot;");

        $string = str_replace($special_chars, $replacements, $word); // replace special characters with equivalent XML entities
        $string = mb_substr($string, 7, NULL); // cut 'string@' out

        for ($i = /**$num**/2; $i > 0; $i--){ _general::$output .="    "; }
        _general::$output .="<arg$num type=\"string\">$string</arg$num>\n";
    }

}

/**
    ------------------------
    |   START OF PROGRAM   |
    ------------------------
**/
$PreambleCheck_flag = false;
$PreambleCheck_done_flag = false;

/** SCRIPT PARAMETERS HANDLING **/
if ($argc != 1){
    if($argc == 2){
        if ($argv[1] == "--help"){
            fputs(STDOUT, _general::$help_msg);
            exit (0);
        } else {
            $param = $argv[1];
            fputs(STDERR, "ERROR[10]: Script parameter '$param' not recognized. Use '--help' for more information.\n");
            exit (ErrHandles::ParamErr->value);
        }
    } else {
        fputs(STDERR, "ERROR[10]: Too many script parameters entered for this script. Use '--help' for more information.\n");
        exit (ErrHandles::ParamErr->value);
    }
}

/** SCRIPT INPUT HANDLING **/
$fhandle = fopen("php://stdin", 'r');
stream_set_blocking($fhandle, false);
$read  = array($fhandle);
$write = NULL;
$except = NULL;
if (stream_select($read, $write, $except, 0) === 0 ) {
    fputs(STDERR, "ERROR[11]: Either no input found or input is unreadable.\n");
    exit (ErrHandles::InFilesErr->value);
}

/** SCRIPT OUTPUT HANDLING **/
if (! stream_isatty(STDOUT)) { //this just detects if output was redirected to a file, I have not been able to figure out how to check its writability
   //fputs(STDERR,"php script.php > output_file\n\n");
}

/** START OF SCRIPT INPUT LOADING **/
while (($line = trim(fgets(STDIN))) || (! feof(STDIN))){ // reads one line from STDIN (feof is to continue even after a whitespace line)
    if ($PreambleCheck_flag == false){ //runs only for the first line (preamble)
        $word_arr = preg_split('@ @', $line, 0, PREG_SPLIT_NO_EMPTY); //return word array
        if (_general::basic_emptyline_check($line) == true){
            ;
        } else if (strncasecmp($line,".IPPCODE22",10) != 0) { //if start of preamble doesn't equal required string (case insensitive)
            if ((_general::basic_comment_check($word_arr[0]) == false) && (_general::basic_emptyline_check($line) == false)){
                fputs(STDERR,"ERROR[21]: Preamble .IPPcode22 is missing or mistyped. Found '$line' instead\n");
                exit (ErrHandles::PreambleErr->value);
            }
        } else { //line's starting with required string, now check if everything after it is correct as well
            if (count($word_arr) > 1){ //if there's more to find after preamble
                if (_general::basic_comment_check($word_arr[1]) == false){ //then the first non-whitespace character found after it has to be '#' (comment)
                    fputs(STDERR,"ERROR[21]: Preamble .IPPcode22 is missing or mistyped. Found '$line' instead\n");
                    exit (ErrHandles::PreambleErr->value);
                }
            }
            //everything is fine, initiate the program's XML output
            _general::$output .="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            _general::$output .="<program language=\"IPPcode22\">\n";
            $PreambleCheck_done_flag = true;
        }
    }

    if ((_general::basic_emptyline_check($line) == false) && ($PreambleCheck_flag == true)){ //check if empty line found
        $word_arr = preg_split('@ @', $line, 0, PREG_SPLIT_NO_EMPTY); //return word array
        //var_dump($word_arr);

        //echo "$word\n"; // required output
        switch ($word_arr[0]){ //try to match each word at the start of line with an opcode
/** ⟨var⟩  = variable (GF@foo) 
 *  ⟨symb⟩ = variable (GF@foo) or constant (int, bool, string, nil)
**/
/** MOVE ⟨var⟩ ⟨symb⟩ **/            
            case strcasecmp($word_arr[0], "MOVE") == 0:
                _general::opcode_start("MOVE", $word_arr, 3);

                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** CREATEFRAME **/
            case strcasecmp($word_arr[0], "CREATEFRAME") == 0:
                _general::opcode_start("CREATEFRAME", $word_arr, 1);
                
                _general::$output .= "    </instruction>\n";
                break;
/** PUSHFRAME **/
            case strcasecmp($word_arr[0], "PUSHFRAME") == 0:
                _general::opcode_start("PUSHFRAME", $word_arr, 1);
                
                _general::$output .= "    </instruction>\n";
                break;
/** POPFRAME **/
            case strcasecmp($word_arr[0], "POPFRAME") == 0:
                _general::opcode_start("POPFRAME", $word_arr, 1);
                
                _general::$output .= "    </instruction>\n";
                break;
/** DEFVAR ⟨var⟩ **/
            case strcasecmp($word_arr[0], "DEFVAR") == 0:
                _general::opcode_start("DEFVAR", $word_arr, 2);

                _prints::create_var_print(1, $word_arr[1]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** CALL ⟨label⟩ **/
            case strcasecmp($word_arr[0], "CALL") == 0:
                _general::opcode_start("CALL", $word_arr, 2);

                _prints::create_label_print(1, $word_arr[1]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** RETURN **/
            case strcasecmp($word_arr[0], "RETURN") == 0:
                _general::opcode_start("RETURN", $word_arr, 1);
                
                _general::$output .= "    </instruction>\n";
                break;
//------
/** PUSHS ⟨symb⟩ **/
            case strcasecmp($word_arr[0], "PUSHS") == 0:
                _general::opcode_start("PUSHS", $word_arr, 2);

                _prints::create_symb_print(1, $word_arr[1]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** POPS ⟨var⟩ **/
            case strcasecmp($word_arr[0], "POPS") == 0:
                _general::opcode_start("POPS", $word_arr, 2);

                _prints::create_var_print(1, $word_arr[1]);
                
                _general::$output .= "    </instruction>\n";
                break;
//------
/** ADD ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "ADD") == 0:
                _general::opcode_start("ADD", $word_arr, 4);

                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** SUB ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "SUB") == 0:
                _general::opcode_start("SUB", $word_arr, 4);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);

                _general::$output .= "    </instruction>\n";
                break;
/** MUL ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "MUL") == 0:
                _general::opcode_start("MUL", $word_arr, 4);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** IDIV ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "IDIV") == 0:
                _general::opcode_start("IDIV", $word_arr, 4);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** LT ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "LT") == 0:
                _general::opcode_start("LT", $word_arr, 4);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** GT ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "GT") == 0:
                _general::opcode_start("GT", $word_arr, 4);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** EQ ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "EQ") == 0:
                _general::opcode_start("EQ", $word_arr, 4);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** AND ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "AND") == 0:
                _general::opcode_start("AND", $word_arr, 4);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** OR ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "OR") == 0:
                _general::opcode_start("OR", $word_arr, 4);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** NOT ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "NOT") == 0:
                _general::opcode_start("NOT", $word_arr, 4);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** INT2CHAR ⟨var⟩ ⟨symb⟩ **/
            case strcasecmp($word_arr[0], "INT2CHAR") == 0:
                _general::opcode_start("INT2CHAR", $word_arr, 3);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);

                _general::$output .= "    </instruction>\n";
                break;
/** STR2INT ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "STR2INT") == 0:
                _general::opcode_start("STR2INT", $word_arr, 4);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);
                
                _general::$output .= "    </instruction>\n";
                break;
//------
/** READ ⟨var⟩ ⟨type⟩ **/
            case strcasecmp($word_arr[0], "READ") == 0:
                _general::opcode_start("READ", $word_arr, 3);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_type_print(2, $word_arr[2]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** WRITE ⟨symb⟩ **/
            case strcasecmp($word_arr[0], "WRITE") == 0:
                _general::opcode_start("WRITE", $word_arr, 2);

                _prints::create_symb_print(1, $word_arr[1]);

                _general::$output .= "    </instruction>\n";
                break;
//------
/** CONCAT ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "CONCAT") == 0:
                _general::opcode_start("CONCAT", $word_arr, 4);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** STRLEN ⟨var⟩ ⟨symb⟩ **/
            case strcasecmp($word_arr[0], "STRLEN") == 0:
                _general::opcode_start("STRLEN", $word_arr, 3);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** GETCHAR ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "GETCHAR") == 0:
                _general::opcode_start("GETCHAR", $word_arr, 4);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** SETCHAR ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "SETCHAR") == 0:
                _general::opcode_start("SETCHAR", $word_arr, 4);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);
                
                _general::$output .= "    </instruction>\n";
                break;
//------
/** TYPE ⟨var⟩ ⟨symb⟩ **/
            case strcasecmp($word_arr[0], "TYPE") == 0:
                _general::opcode_start("TYPE", $word_arr, 3);
                
                _prints::create_var_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                
                _general::$output .= "    </instruction>\n";
                break;
//------
/** LABEL ⟨label⟩ **/
            case strcasecmp($word_arr[0], "LABEL") == 0:
                _general::opcode_start("LABEL", $word_arr, 2);
                
                _prints::create_label_print(1, $word_arr[1]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** JUMP ⟨label⟩ **/
            case strcasecmp($word_arr[0], "JUMP") == 0:
                _general::opcode_start("JUMP", $word_arr, 2);
                
                _prints::create_label_print(1, $word_arr[1]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** JUMPIFEQ ⟨label⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "JUMPIFEQ") == 0:
                _general::opcode_start("JUMPIFEQ", $word_arr, 4);
                
                _prints::create_label_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** JUMPIFNEQ ⟨label⟩ ⟨symb1⟩ ⟨symb2⟩ **/
            case strcasecmp($word_arr[0], "JUMPIFNEQ") == 0:
                _general::opcode_start("JUMPIFNEQ", $word_arr, 4);
                
                _prints::create_label_print(1, $word_arr[1]);
                _prints::create_symb_print(2, $word_arr[2]);
                _prints::create_symb_print(3, $word_arr[3]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** EXIT ⟨symb⟩ **/
            case strcasecmp($word_arr[0], "EXIT") == 0:
                _general::opcode_start("EXIT", $word_arr, 2);
                
                _prints::create_symb_print(1, $word_arr[1]);
                
                _general::$output .= "    </instruction>\n";
                break;
//------
/** DPRINT ⟨symb⟩ **/
            case strcasecmp($word_arr[0], "DPRINT") == 0:
                _general::opcode_start("DPRINT", $word_arr, 2);
                
                _prints::create_symb_print(1, $word_arr[1]);
                
                _general::$output .= "    </instruction>\n";
                break;
/** BREAK **/
            case strcasecmp($word_arr[0], "BREAK") == 0:
                _general::opcode_start("BREAK", $word_arr, 1);
                
                _general::$output .= "    </instruction>\n";
                break;
//------
            default:
                if (_general::basic_comment_check($word_arr[0]) == true){ //check if comment found
                    break;
                } else {
                    fprintf(STDERR,"ERROR[22]: Found a line which isn't empty or a comment but doesn't start with a known Opcode either.\n");
                    exit (ErrHandles::OpcodeErr->value);
                }
        }
    } else {
        if ($PreambleCheck_done_flag == true){
            $PreambleCheck_flag = true;
        }
    }
}


/** 
    --------------------------------
    |   AUXILIARY PRINTS SECTION   |
    --------------------------------
**/
// this is how to return errors with specified type (and number)
//$return = ErrHandles::OutFilesErr->value;

/**$code = "MOVE";
$code_w = "MOVEE";

var_dump($argv);

if (($OpcodesEnumReflection->hasCase($code)) == true){ echo "yay, $code is in enum\n"; } 
else { echo "uh, $code not found in enum\n"; }
}**/

//fputs (STDOUT,"\n========================================================================\n\n");

_general::$output .="</program>\n";
fputs(STDOUT,_general::$output);

exit (0);

?>