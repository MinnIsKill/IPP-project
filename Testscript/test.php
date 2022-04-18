<?php 
/**
    ------------------------------------------------------------
    test.php
    part of Task #2 for IPP project VUT Brno 2021/22
    Vojtech Kalis, xkalis03@stud.fit.vutbr.cz


    ------------------------------------------------------------
    run with --help for more info
    ------------------------------------------------------------
    script must be placed in the same folder as parse.php and 
    interpret.py
    ------------------------------------------------------------
    the individual tests must either be inside the same folder 
    as well or the script must be given their exact whereabouts
    through the optional parameter '--directory='
    when using '--directory=', another parameter '--recursive'
    can also be passed which will enable the test script to also
    search through all subdirectories of given directory
    ------------------------------------------------------------
    php8.1 test.php --help
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

    case FileDirNotF = 41; // given file or directory not found
}

/** AUXILIARY FUNCS CLASS
 *    - contains mainly functions used for debugging
 */
class _Auxiliary {
    public static function writeout_loadargs_result(){
        echo "Source files found:  ";
        print_r(_general::$source_files);

        echo "\ndirectory:    ";
        echo _general::$directory;
        echo "\nrecursive:    ";
        echo _general::$recursive;
        echo "\nparse_script: ";
        echo _general::$parse_script;
        echo "\nint_script:   ";
        echo _general::$int_script;
        echo "\nparse_only:   ";
        echo _general::$parse_only;
        echo "\nint_only:     ";
        echo _general::$int_only;
        echo "\njexampath:    ";
        echo _general::$jexampath;
        echo "\nnoclean:      ";
        echo _general::$noclean;
        echo "\n";

        echo "\nRC Results: ";
        var_dump(_general::$RCresults);
        echo "\nRC Results nums: ";
        var_dump(_general::$RCresults_nums);
        echo "resultsParse: ";
        var_dump(_general::$resultsParse);
        echo "resultsInt: ";
        var_dump(_general::$resultsInt);
        echo "resultsBoth: ";
        var_dump(_general::$resultsBoth);
    }
}

/** GENERAL CLASS
 *    - contains public variables and some basic functions used throughout the program
 *    - to find out more about included functions, refer to their individual descriptions
**/
class _general {
    public static $tests_cnt = 0; // counter for tests
    public static $tests_passed = 0; // counter for successful tests
    public static $output; // array to which output is saved
    //public static $line_num = 1;

    public static $argsfound_cnt = 0;
    
    public static $directory = "";
    public static $recursive = FALSE;
    public static $parse_script = "parse.php";
    public static $int_script = "interpret.py";
    public static $parse_only = FALSE;
    public static $int_only = FALSE;
    public static $jexampath = "/pub/courses/ipp/jexamxml/";
    public static $noclean = FALSE;

    public static $source_files = array();

    public static $html_output;

    // Array of test results
    public static $resultsParse = [];
    public static $resultsInt = [];
    public static $resultsBoth = [];
    public static $RCresults = [];
    public static $RCresults_nums = [];
    public static $RCresults_orig_nums = [];

    public static $help_msg = // a static string printed out when '--help' is called
"Script of type filter (parse.php in PHP 8.1) loads a source file written in IPPcode22 from stdin, checks the lexical
and syntactical correctness of written code and prints out its XML reprezentation.

  Script parameters:
      --help               prints a help message to stdout (doesn't load any input). Returns 0.
      --directory=path     tests will be searched for in given directory path (if this parameter isn't received,
                           script will search for tests in current directory)
      --recursive          tests will be searched for not only in given directory, but also in all its subdirectories
      --parse-script=file  file with parser script in PHP8.1 (implicitly 'parse.php' searched for in current directory)
      --int-script=file    file with interpreter script in Python3.8 (implicitly 'interpret.py' searched for in current 
                           directory)
      --parse-only         only parser's functionality will be tested (this parameter mustn't be combined with the 
                           following parameters:  --int-only,  --int-script). Output is compared with contents of 
                           reference .out file using the A7Soft JExamXML tool
      --int-only           only interpreter's functionality will be tested (this parameter mustn't be combined with the 
                           following parameters:  --parse-only,  --parse-script,  --jexampath). Input XML program will
                           be in a file with the .src suffix.
      --jexampath=path     path to directory containing jexamxml.jar file with JAR package which contains the A7Soft 
                           JExamXML tool and a file with configuration called 'options'. (implicitly searched for at
                           /pub/courses/ipp/jexamxml/ on VUTBR FIT server Merlin, where this project's being evaluated)
                           Final '/' in path will be added by the program if not entered by user.
      --noclean            during test.php's function, auxiliary files containing interim results, meaning the script
                           will not delete files created during the execution of tested scripts (for example file with
                           resulting XML after parse.php was run, etc.)

  General error codes:
        10      missing script parameter (if needed) or an attempt at using a forbidden parameter combination
        11      error opening input files (e.g. they don't exist, insufficient permission)
        12      error opening output files for writing (e.g. insufficient permission, error while writing)
        99      internal error (not affected by input files or command line parameters; e.g. memory allocation error)

  Error codes specific for test:
        41      either given tests directory (--directory=path) doesn't exist, or parse script, interpret script or 
                jexamxml don't exist or are inaccessible\n";


/** 
* 
**/
    public static function arg_find($argv, $argument){
        if (($argument == "recursive") || ($argument == "parse-only") || ($argument == "int-only") || ($argument == "noclean")){
            $argsearch = preg_grep("/^--$argument$/", $argv);
            $argpos = array_keys($argsearch);
        } else if (($argument == "directory") || ($argument == "parse-script") || ($argument == "int-script") || ($argument == "jexampath")){
            $argsearch = preg_grep("/^--$argument=.+$/", $argv);
            $argpos = array_keys($argsearch);
        }

        if ($argument == "parse-only"){
            $argument = "parse_only";
        } else if ($argument == "int-only"){
            $argument = "int_only";
        } else if ($argument == "parse-script"){
            $argument = "int_only";
        } else if ($argument == "int-script"){
            $argument = "int_only";
        }

        if (($argument == "recursive") || ($argument == "parse_only") || ($argument == "int_only") || ($argument == "noclean")){
            if(count($argsearch) == 1){ // if argument found only once
                _general::$argsfound_cnt++;
                _general::${$argument} = TRUE;
            } else if(count($argsearch) > 1){
                fputs(STDERR, "ERROR[10]: Script received the '$argument' parameter more than once\n");
                exit (ErrHandles::ParamErr->value);
            }
        } else if (($argument == "directory") || ($argument == "parse_script") || ($argument == "int_script") || ($argument == "jexampath")){
            if(count($argsearch) == 1){ // if argument found only once
                _general::$argsfound_cnt++;
                if ($argument == "directory"){ // directory
                    $dirpath = substr($argsearch[$argpos[0]], 12); // from 13th character (after '--directory=')
                    $dirpath = realpath($dirpath);

                    if (is_dir($dirpath) == false){
                        fputs(STDERR, "ERROR[41]: directory received in argument '--directory=' doesn't exist\n");
                        exit(ErrHandles::FileDirNotF->value);
                    }

                    _general::${$argument} = realpath($dirpath);
                } else if ($argument == "parse_script"){ // parse-script
                    $parser = substr($argsearch[$argpos[0]], 15); // from 16th character (after '--parse-script=')

                    if (file_exists($parser) == false){
                        fputs(STDERR, "ERROR[41]: file received in argument '--parse-script=' doesn't exist\n");
                        exit(ErrHandles::FileDirNotF->value);
                    }

                    _general::${$argument} = $parser;
                } else if ($argument == "int_script"){ // int-script
                    $interpreter = substr($argsearch[$argpos[0]], 13); // from 14th character (after '--int-script=')

                    if (file_exists($interpreter) == false){
                        fputs(STDERR, "ERROR[41]: file received in argument '--int-script=' doesn't exist\n");
                        exit(ErrHandles::FileDirNotF->value);
                    }

                    _general::${$argument} = $interpreter;
                } else if ($argument == "jexampath"){ // jexampath
                    $jexpath = substr($argsearch[$argpos[0]], 12); // from 13th character (after '--jexampath=')
                    $jexpath = realpath($jexpath);

                    if (is_dir($jexpath) == false){
                        fputs(STDERR, "ERROR[41]: directory received in argument '--jexampath=' doesn't exist\n");
                        exit(ErrHandles::FileDirNotF->value);
                    }

                    _general::${$argument} = realpath($jexpath) . "/";
                }
            } else if(count($argsearch) > 1){
                fputs(STDERR, "ERROR[10]: script received the '$argument' parameter more than once\n");
                exit (ErrHandles::ParamErr->value);
            }
        }
        Unset($argsearch[0]);
        Sort($argsearch);
    }

/** 
* 
**/
    public static function args_compatibility_check($argv){
        if (_general::$parse_only == TRUE){
            if ((_general::$int_only == TRUE) || (count(preg_grep("/^--int-script=.+$/", $argv)) >= 1)){
                fputs(STDERR, "ERROR[10]: script discovered an attempt at using a forbidden parameter combination\n");
                exit(ErrHandles::ParamErr->value);
            }
        } else if (_general::$int_only == TRUE) {
            if ((_general::$parse_only == TRUE) || (count(preg_grep("/^--parse-script=.+$/", $argv)) >= 1) || (count(preg_grep("/^--jexampath=.+$/", $argv)) >= 1)){
                fputs(STDERR, "ERROR[10]: script discovered an attempt at using a forbidden parameter combination\n");
                exit(ErrHandles::ParamErr->value);
            }
        }
    }

/** SCRIPT PARAMETERS HANDLING 
 * 
**/
    public static function load_args($argv, $argc){
        // find '--help'
        if(count(preg_grep("/^--help$/", $argv)) == 1){
            if ($argc == 2){
                fputs(STDOUT, _general::$help_msg);
                exit (0);
            } else {
                fputs(STDERR, "ERROR[10]: Script received the '--help' parameter in combination with others\n");
                exit (ErrHandles::ParamErr->value);
            }
        }

        for ($i = 1; $i < $argc; $i++){
            if ((substr($argv[$i],0,12) != "--directory=") && ($argv[$i] != "--recursive") && (substr($argv[$i],0,15) != "--parse-script=") && 
            (substr($argv[$i],0,13) != "--int-script=") && ($argv[$i] != "--parse-only") && ($argv[$i] != "--int-only") && 
            (substr($argv[$i],0,12) != "--jexampath=") && ($argv[$i] != "--noclean")){
                $offendingarg = $argv[$i];
                fputs(STDERR, "ERROR[10]: argument '$offendingarg' not recognized\n");
                exit (ErrHandles::ParamErr->value);
            }
        }
        
        _general::arg_find($argv, "directory");
        _general::arg_find($argv, "recursive");
        _general::arg_find($argv, "parse-script");
        _general::arg_find($argv, "int-script");
        _general::arg_find($argv, "parse-only");
        _general::arg_find($argv, "int-only");
        _general::arg_find($argv, "jexampath");
        _general::arg_find($argv, "noclean");

        _general::args_compatibility_check($argv);
    }

/** 
* 
**/
    public static function load_testssrc(){
        if (_general::$recursive == TRUE){
            /** NOTE: - practically directly ripped off https://www.php.net/manual/en/class.recursivedirectoryiterator.php
            *         - go check RecursiveDirectoryIterator out, it's awesome   **/
            $Directory = new RecursiveDirectoryIterator(_general::$directory);
            $Iterator = new RecursiveIteratorIterator($Directory);
            $Regex = new RegexIterator($Iterator, '/^.+\.src$/i', RecursiveRegexIterator::GET_MATCH);
            
            foreach ($Regex as $file){
                _general::$source_files = array_merge(_general::$source_files, $file);
            }
        } else {
            _general::$source_files = glob(_general::$directory."/*.src");
        }
    }

/** 
*   - saves results into their respective arrays found at the beginning of class _general
**/
    public static function run_tests(){
    // parsing
        if (_general::$int_only == FALSE){
            foreach(_general::$source_files as $file){
                $file = substr($file, 0, -4); #cut away the last four characters (".src")

                //if these files are missing, generate empty ones
                if (!is_file("$file.in")){
                    $newfile = fopen("$file.in", "w");
                    fclose($newfile);
                }
                if (!is_file("$file.out")){
                    $newfile = fopen("$file.out", "w");
                    fclose($newfile);
                }
                
                $parser = _general::$parse_script;
                $rc = 0;
                $output_dump = array();

                exec("php8.1 $parser < $file.src > $file-my_garbage.out;", $output_dump, $rc);
                $newfile = fopen("$file-my_garbage.rc", "w");
                fwrite($newfile, $rc);
                fclose($newfile);

                if (is_file("$file.rc")){
                    $rc_orig = file_get_contents("$file.rc");
                } else {
                    $newfile = fopen("$file.rc", "w");
                    fwrite($newfile, "0");
                    fclose($newfile);
                    $rc_orig = file_get_contents("$file.rc");
                }

                if (_general::$parse_only == TRUE){ // if parse-only, there's an XML to compare with
                    if ($rc_orig == $rc){
                        array_push(_general::$RCresults_nums, $rc);
                        array_push(_general::$RCresults_orig_nums, $rc_orig);
                        array_push(_general::$RCresults, "true");
                    } else {
                        array_push(_general::$RCresults_nums, $rc);
                        array_push(_general::$RCresults_orig_nums, $rc_orig);
                        array_push(_general::$RCresults, "false");
                    }

                    $jexamjar = _general::$jexampath . "jexamxml.jar";
                    $jexamops = _general::$jexampath . "options";
    
                    exec("java -jar $jexamjar \"$file.out\" \"$file-my_garbage.out\" /dev/null  /D $jexamops", $output, $diff);
                    if($diff == "0\n"){
                        array_push(_general::$resultsParse, "true");
                    } else {
                        $outFile = file_get_contents("$file.out");
                        $customOut = file_get_contents("$file-my_garbage.out");
                        if($outFile == $customOut){
                            array_push(_general::$resultsParse, "true");
                        } else {
                            array_push(_general::$resultsParse, "false");
                        }
                    }
                }
            // interpreting after parsing done
                if (_general::$parse_only == FALSE){
                    $detected_error = false;
                    $interpreter = _general::$int_script;

                    exec("python3.8 $interpreter --source=$file-my_garbage.out --input=$file.in;", $output_dump, $rc);
                    if ($rc == 0){
                        $output_dump = shell_exec("python3.8 $interpreter --source=$file-my_garbage.out --input=$file.in;");
                    } else {
                        $detected_error = true;
                    }

                    if(is_file("$file-my_garbage.rc")){ 
                        unlink("$file-my_garbage.rc"); 
                    }

                    $newfile = fopen("$file-my_garbage.out", "w");
                    file_put_contents("$file-my_garbage.out", $output_dump);
                    fclose($newfile);

                    $newfile = fopen("$file-my_garbage.rc", "w");
                    fwrite($newfile, $rc);
                    fclose($newfile);

                    if ($rc_orig == $rc){
                        array_push(_general::$RCresults_nums, $rc);
                        array_push(_general::$RCresults_orig_nums, $rc_orig);
                        array_push(_general::$RCresults, "true");
                    } else {
                        array_push(_general::$RCresults_nums, $rc);
                        array_push(_general::$RCresults_orig_nums, $rc_orig);
                        array_push(_general::$RCresults, "false");
                    }

                    $outFile = file_get_contents("$file.out");
                    $myoutFile = file_get_contents("$file-my_garbage.out");

                    if(($outFile == $myoutFile) || ($detected_error == true)){
                        array_push(_general::$resultsBoth, "true");
                    } else {
                        array_push(_general::$resultsBoth, "false");
                    }
                }
                // always clean up after yourself
                _general::clean_up_garbage($file);
            }
        }
    // interpreting if no parsing done
        else if (_general::$parse_only == FALSE){
            foreach(_general::$source_files as $file){
                $file = substr($file, 0, -4); #cut away the last four characters (".src")

                //if these files are missing, generate empty ones
                if (!is_file("$file.in")){
                    $newfile = fopen("$file.in", "w");
                    fclose($newfile);
                }
                if (!is_file("$file.out")){
                    $newfile = fopen("$file.out", "w");
                    fclose($newfile);
                }
                    
                $parser = _general::$parse_script;
                $rc = 0;
                $output_dump = array();

                $detected_error = false;
                $interpreter = _general::$int_script;

                if (is_file("$file.rc")){
                    $rc_orig = file_get_contents("$file.rc");
                } else {
                    $newfile = fopen("$file.rc", "w");
                    fwrite($newfile, "0");
                    fclose($newfile);
                    $rc_orig = file_get_contents("$file.rc");
                }

                exec("python3.8 $interpreter --source=$file.src --input=$file.in;", $output_dump, $rc);
                if ($rc == 0){
                    $output_dump = shell_exec("python3.8 $interpreter --source=$file.src --input=$file.in;");
                } else {
                    $detected_error = true;
                }

                $newfile = fopen("$file-my_garbage.rc", "w");
                fwrite($newfile, $rc);
                fclose($newfile);

                $newfile = fopen("$file-my_garbage.out", "w");
                file_put_contents("$file-my_garbage.out", $output_dump);
                fclose($newfile);

                if ($rc_orig == $rc){
                    array_push(_general::$RCresults_nums, $rc);
                    array_push(_general::$RCresults_orig_nums, $rc_orig);
                    array_push(_general::$RCresults, "true");
                } else {
                    array_push(_general::$RCresults_nums, $rc);
                    array_push(_general::$RCresults_orig_nums, $rc_orig);
                    array_push(_general::$RCresults, "false");
                }

                $outFile = file_get_contents("$file.out");
                $myoutFile = file_get_contents("$file-my_garbage.out");

                if(($outFile == $myoutFile) || ($detected_error == true)){
                    array_push(_general::$resultsInt, "true");
                } else {
                    array_push(_general::$resultsInt, "false");
                }

                // always clean up after yourself
                _general::clean_up_garbage($file);
            }
        }
    }

/** 
 * 
**/
    public static function clean_up_garbage($file){
        if (_general::$noclean == FALSE){
            if(is_file("$file-my_garbage.src")){ unlink("$file-my_garbage.src"); }
            if(is_file("$file-my_garbage.in")){ unlink("$file-my_garbage.in"); }
            if(is_file("$file-my_garbage.out")){ unlink("$file-my_garbage.out"); }
            if(is_file("$file-my_garbage.rc")){ unlink("$file-my_garbage.rc"); }
        }
    }

/** 
 * 
**/
    public static function print_result(){
        _general::$html_output .= 
        "<!DOCTYPE html>
<html lang=\"en\">
    <head>
        <meta charset=\"UTF-8\">
        <title>IPP 2021/22 Project - Test Script Results</title>
        <style>
            body {background-color: grey; color: black; font-size: 16px; font-family: Helvetica;}
            h1 {font-size: 30px; float: left; margin-left: 20px;}
            h2 {font-size: 22px; text-align: center; float: right; position: absolute; right: 3%; top: 0%;}
            h3 {font-size: 22px; text-align: center; float: right; position: absolute; right: 3%; top: 3%;}
            h4 {font-size: 22px; text-align: center; float: right; position: absolute; right: 8%; top: 8%; border-radius: 10px; border: 1px solid; border-color: #bdbbb9; padding: 5px; color: #bdbbb9; background-color: #6e6c69;}
            h5 {text-align: center; font-size: 22px; background-color: grey; color: #bdbbb9; margin-top: 0px; margin-bottom: 0px; padding-bottom: 10px;}
            .wrapper {width: 1500px; margin: auto;}
            .column {float: left; text-align: center; margin: 10px;}
            .row {display:block; text-align: center; margin-bottom: 3px; background-color: #6e6c69;}
            .first {width: 1000px;}
            .second .third {width: 200px;}
            .ok {display:block; width:200px; background-color: green;}
            .error {display:block; width:200px; background-color: red}
        </style>
    </head>
    <body>
        <h1>TEST RESULTS of test.php</h1>
        <h2>Tested files: ";
        if (_general::$int_only == TRUE){_general::$html_output.=_general::$int_script;}
        else if (_general::$parse_only == TRUE){_general::$html_output.=_general::$parse_script;}
        else {_general::$html_output.=_general::$parse_script . ", " . _general::$int_script;}
        _general::$html_output .=
        "</h2>
        <h3>Author: Vojtěch Kališ, xkalis03</h3>
        <h4>Tests passed: ";
        $successes = count(array_keys(_general::$RCresults, "true"));
        $total = count(_general::$RCresults);
        _general::$html_output .= "$successes/$total";
        _general::$html_output .=
        "</h4>
        <br><br><br><br><br><br><br><br>
        <div class=\"wrapper\">
            <div class=\"row\">
                <div class=\"column first\">
                    <h5>TESTED FILES</h5>\n";
        foreach(_general::$source_files as $file){
            _general::$html_output .= "                        <div class=\"row\">$file</div>\n";
        }
        _general::$html_output .=
        "                </div>
                <div class=\"column second\">
                    <h5>";
        if (_general::$int_only == TRUE){_general::$html_output.="INTERPRET</h5>\n";}
        else if (_general::$parse_only == TRUE){_general::$html_output.="PARSER</h5>\n";}
        else {_general::$html_output.="PARSE + INT</h5>\n";}

        if (_general::$int_only == TRUE){
            foreach(_general::$resultsInt as $result){
                if ($result == "true"){
                    _general::$html_output .= "                        <div class=\"row ok\">OK</div>\n";
                } else {
                    _general::$html_output .= "                        <div class=\"row error\">FAILED</div>\n";
                }
            }
        }
        else if (_general::$parse_only == TRUE){
            foreach(_general::$resultsParse as $result){
                if ($result == "true"){
                    _general::$html_output .= "                        <div class=\"row ok\">OK</div>\n";
                } else {
                    _general::$html_output .= "                        <div class=\"row error\">FAILED</div>\n";
                }
            }
        }
        else {
            foreach(_general::$resultsBoth as $result){
                if ($result == "true"){
                    _general::$html_output .= "                        <div class=\"row ok\">OK</div>\n";
                } else {
                    _general::$html_output .= "                        <div class=\"row error\">FAILED</div>\n";
                }
            }
        }
        _general::$html_output .=
        "                </div>
                <div class=\"column third\">
                    <h5>RETURN CODE</h5>\n";
        $i = 0;
        foreach(_general::$RCresults as $result){
            if ($result == "true"){
                $rc = _general::$RCresults_nums[$i];
                $rc_orig =_general::$RCresults_orig_nums[$i];
                _general::$html_output .= "                        <div class=\"row ok\">$rc [wanted: $rc_orig]</div>\n";
            } else {
                $rc = _general::$RCresults_nums[$i];
                $rc_orig =_general::$RCresults_orig_nums[$i];
                _general::$html_output .= "                        <div class=\"row error\">$rc [wanted: $rc_orig]</div>\n";
            }
            $i = $i+1;
        }
        _general::$html_output .=
        "                </div>
            </div>
        </div>
</html>";
    }
}



/** MAIN */
_general::$directory = getcwd(); # needs to be initialized, saves current working directory

_general::load_args($argv, $argc);

_general::load_testssrc();

_general::run_tests();

//_Auxiliary::writeout_loadargs_result();

_general::print_result();
fputs(STDOUT,_general::$html_output);
fputs(STDOUT,"\n");

$htmlout = fopen("output.html", "w");
fwrite($htmlout, _general::$html_output);
fclose($htmlout);
?>