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

    //...

}

class _general {
}



class _var {
    public $name;

    function check_if_already_def($name) {
        ;
    }

    function set_name($name) {
        $this->name = $name;
    }

    function get_name() {
        return $this->name;
    }
}

/**
    ------------------------
    |   START OF PROGRAM   |
    ------------------------
**/


/** 
    --------------------------------
    |   AUXILIARY PRINTS SECTION   |
    --------------------------------
**/
$return = ErrHandles::OutFilesErr->value;
echo "return value is $return.\n";

//define reflection of the Opcodes enum for the purpose of using it in matching input to predefined Opcodes enum values
$OpcodesEnumReflection = new ReflectionEnum(Opcodes::class);

$code = "MOVE";
$code_w = "MOVEE";

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

$line = trim(fgets(STDIN)); // reads one line from STDIN
echo "$line\n";
$line = trim(fgets(STDIN)); // reads one line from STDIN
echo "$line\n";

$word_arr = explode(" ", $line); //return word array

foreach($word_arr as $word){
    echo "$word\n"; // required output
}

?>