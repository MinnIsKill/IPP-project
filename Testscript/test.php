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
        echo "directory:    ";
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


    public static function arg_find($argv, $argument){
        if (($argument == "recursive") || ($argument == "parse-only") || ($argument == "int-only") || ($argument == "noclean")){
            $argsearch = preg_grep("/^--$argument$/", $argv);
        } else if (($argument == "directory") || ($argument == "parse-script") || ($argument == "int-script") || ($argument == "jexampath")){
            $argsearch = preg_grep("/^--$argument=.+$/", $argv);
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
                    $dirpath = substr($argsearch[_general::$argsfound_cnt], 12); // from 13th character (after '--directory=')
                    $dirpath = realpath($dirpath);

                    if (is_dir($dirpath) == false){
                        fputs(STDERR, "ERROR[?]: directory received in argument '--directory=' doesn't exist\n");
                        exit(11);
                    }

                    _general::${$argument} = realpath($dirpath);
                } else if ($argument == "parse_script"){ // parse-script
                    $parser = substr($argsearch[_general::$argsfound_cnt], 15); // from 16th character (after '--parse-script=')

                    if (file_exists($parser) == false){
                        fputs(STDERR, "ERROR[?]: file received in argument '--parse-script=' doesn't exist\n");
                        exit(11);
                    }

                    _general::${$argument} = $parser;
                } else if ($argument == "int_script"){ // int-script
                    $interpreter = substr($argsearch[_general::$argsfound_cnt], 13); // from 14th character (after '--int-script=')

                    if (file_exists($interpreter) == false){
                        fputs(STDERR, "ERROR[?]: file received in argument '--int-script=' doesn't exist\n");
                        exit(11);
                    }

                    _general::${$argument} = $interpreter;
                } else if ($argument == "jexampath"){ // jexampath
                    $jexpath = substr($argsearch[_general::$argsfound_cnt], 12); // from 13th character (after '--jexampath=')
                    $jexpath = realpath($jexpath);

                    if (is_dir($jexpath) == false){
                        fputs(STDERR, "ERROR[?]: directory received in argument '--jexampath=' doesn't exist\n");
                        exit(11);
                    }

                    _general::${$argument} = realpath($jexpath);
                }
            } else if(count($argsearch) > 1){
                fputs(STDERR, "ERROR[10]: Script received the '$argument' parameter more than once\n");
                exit (ErrHandles::ParamErr->value);
            }
        }
        Unset($argsearch[0]);
        Sort($argsearch);
    }

    /** SCRIPT PARAMETERS HANDLING **/
    public static function load_args($argv, $argc){
/* find '--help' */
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
    }
}


/** MAIN */
_general::load_args($argv, $argc);
_Auxiliary::writeout_loadargs_result();










    // Represents tester as whole class
    Class Tester
    {
        // Settings from command line
        protected $recursive = false;
        protected $directory = NULL;
        protected $parser = NULL;
        protected $interpret = NULL;
        protected $parseOnly = false;
        protected $intOnly = false;
        protected $files = NULL;
        protected $argc = 1;
        protected $parsedArgs = 1;

        // Array of test results
        protected $resultsParse = [];
        protected $resultsInt = [];
        protected $resultsRetval = [];

        // Method searches recursively folders by regex
        private function rsearch($folder, $pattern)
        {
            $dir = new RecursiveDirectoryIterator($folder);
            $ite = new RecursiveIteratorIterator($dir);

            $files = new RegexIterator($ite, $pattern, RegexIterator::GET_MATCH);
            
            $fileList = array();
            foreach($files as $file) 
            {
                $fileList = array_merge($fileList, $file);
            }

            return $fileList;
        }

        // Method parses arguments
        public function parse_args($args)
        {
            $this->argc = count($args);

            $help = preg_grep("/^--help$|^-h$/", $args);
            if(!empty($help) and count($args) != 2)
            {
                fwrite(STDERR, "Error, wrong parameters!\n");
                exit(10);
            }
            else if(!empty($help))
            {
                print "Testing tool test.php for parse.php and interpret.py\n";
                print "Legal arguments:\n";
                print "--help               Shows this message.\n";
                print "--directory=path     Searches .src tests in <path>, if empty searches cwd().\n";
                print "--recursive          Searches for tests recursively.\n";
                print "--parse-script=file  Path to the \"parse.php\", if empty searches cwd().\n";
                print "--int-script=file    Path to the \"interpret.py\", if empty searches cwd().\n";
                print "--parse-only         Tests are performed only on \"parse.php\".\n";
                print "--int-only           Tests are performed only on \"interpret.py\".\n";
                exit(0);
            }

            $recursive = preg_grep("/^--recursive$|^-r$/", $args);
            if(!empty($recursive) and count($recursive) == 1)
            {
                $this->recursive = true;
                $this->parsedArgs++;
            }
            else if(!empty($recursive) and count($recursive) != 1)
            {
                fwrite(STDERR, "Error, wrong parameters!\n");
                exit(10);
            }

            $directory = preg_grep("/^--directory=.+$/", $args);
            if(!empty($directory) && count($directory) == 1)
            {
                $directory = implode($directory);
                
                $path = explode("=", $directory, 2);
                $path = $path[1];
                
                $path = realpath($path);
                $exists = is_dir($path);
                if($exists == false)
                {
                    fwrite(STDERR, "Error, invalid directory!\n");
                    exit(11);
                }
                
                $this->directory = realpath($path);
                $this->parsedArgs++;

            }
            else if(!empty($directory) && count($directory) != 1)
            {
                fwrite(STDERR, "Error, wrong parameters!\n");
                exit(10);
            }
            else
            {
                $this->directory = getcwd();
            }

            
            
            $parseOnly = preg_grep("/^--parse-only$/", $args);
            if(!empty($parseOnly) and count($parseOnly) == 1)
            {
                $this->parseOnly = true;
                $this->parsedArgs++;
            }
            else if(!empty($parseOnly) and count($parseOnly) != 1)
            {
                fwrite(STDERR, "Error, wrong parameters!\n");
                exit(10);
            }
            
            $intOnly = preg_grep("/^--int-only$/", $args);
            if(!empty($intOnly) and count($intOnly) == 1)
            {
                $this->intOnly = true;
                $this->parsedArgs++;
            }
            else if(!empty($intOnly) and count($intOnly) != 1)
            {
                fwrite(STDERR, "Error, wrong parameters!\n");
                exit(10);
            }
            
            $parser = preg_grep("/^--parse-script=.+$/", $args);
            if(!empty($parser) and count($parser) == 1)
            {
                if($this->intOnly)
                {
                    fwrite(STDERR, "Error, wrong arguments!\n");
                    exit(10);
                }

                $parser = implode($parser);

                $path = explode("=", $parser, 2);
                $path = $path[1];

                $this->parser = $path;
                $this->parsedArgs++;
            }
            else if(!empty($parse) and count($parse) != 1)
            {
                fwrite(STDERR, "Error, wrong parameters!\n");
                exit(10);
            }
            else
            {
                $this->parser = getcwd() . "/parse.php";
            }

            
            $interpret = preg_grep("/^--int-script=.+$/", $args);
            if(!empty($interpret) and count($interpret) == 1)
            {
                if($this->parseOnly)
                {
                    fwrite(STDERR, "Error, wrong arguments!\n");
                    exit(10);
                }

                $interpret = implode($interpret);
                
                $path = explode("=", $interpret, 2);
                $path = $path[1];
                
                $this->interpret = $path;
                $this->parsedArgs++;
            }
            else if(!empty($parse) and count($parse) != 1)
            {
                fwrite(STDERR, "Error, wrong parameters!\n");
                exit(10);
            }
            else
            {
                $this->interpret = getcwd() . "/interpret.py";
            }

            if(($this->intOnly and $this->parseOnly))
            {
                fwrite(STDERR, "Error, wrong parameters!\n");
                exit(10);
            }
            else if($this->argc != $this->parsedArgs)
            {
                fwrite(STDERR, "Error, wrong parameters!\n");
                exit(10);
            }
            
            if(!is_file($this->parser))
            {
                fwrite(STDERR, "Error, parser not found!\n");
                exit(11);
            }

            if(!is_file($this->interpret))
            {
                fwrite(STDERR, "Error, interpret not found!\n");
                exit(11);
            }
        }
        
        // Method searches the tests and gets their realpath in list
        // based on the --recursive flag
        public function fetch_tests()
        {
            if($this->recursive == true)
            {
                $this->files = $this->rsearch($this->directory, "/^.*\.src$/");
            }
            else
            {
                $regex = $this->directory . "/*.src";
                $this->files = glob($regex);
            }
        }

        // Method runs a test based on every .src file in "files" variable
        public function run_tests()
        {
            foreach($this->files as $i)
            {
                $i = substr($i, 0, -4);
                $filename = $i . ".in";
                $creator = fopen($filename, "a");
                if($creator == false)
                {
                    exit(11);
                }
                fclose($creator);

                $filename = $i . ".out";
                $creator = fopen($filename, "a");
                if($creator == false)
                {
                    exit(11);
                }

                fclose($creator);

                $filename = $i . ".rc";
                if(!is_file($filename))
                {
                    $creator = fopen($filename, "a");
                    if($creator == false)
                    {
                        exit(12);
                    }
                    fwrite($creator, "0");
                    fclose($creator);
                }
                
                // Parse-only or both
                if(!$this->intOnly or ($this->intOnly == $this->parseOnly))
                {
                    $command = "php8.1 \"" . $this->parser . "\" <\"" . $i . ".src\"" . ">\"" . $i . ".superdupermemexml\"";
                    exec($command, $output, $retval);
                    
                    shell_exec("echo -n \"$retval\" > \"$i.superdupermemeretval\"");
                    if($this->intOnly == $this->parseOnly && $retval == "0\n")
                    {
                        $command = "python3.8 \"" . $this->interpret . "\" \"--input=$i.in\"" . "<\"" . $i . ".superdupermemexml\"" . ">\"" . $i . ".superdupermemeint\"";
                        exec($command, $output, $retval);
                        
                        shell_exec("echo -n \"$retval\" > \"$i.superdupermemeretval\"");
                    }
                    else if($this->intOnly == $this->parseOnly)
                    {
                        shell_exec("echo -n \"$retval\" > \"$i.superdupermemeint\"");
                    }
                }
                // Int-only
                else if(!$this->parseOnly)
                {
                    $command = "python3.8 \"" . $this->interpret . "\" \"--input=$i.in\"" . "<\"" . $i . ".src\"" . ">\"" . $i . ".superdupermemeint\"";
                    exec($command, $output, $retval);

                    shell_exec("echo -n \"$retval\" > \"$i.superdupermemeretval\"");
                }
            }
            $this->compare_results();
        }

        // Method checks out the results of tests, based on temporary files
        // and pushes true or false, based if test failed
        private function compare_results()
        {
            foreach($this->files as $i)
            {
                $testname = substr($i, 0, -4);

                exec("diff -b \"$testname.rc\" \"$testname.superdupermemeretval\"", $output, $diff);
                if($diff == "0\n")
                {
                    array_push($this->resultsRetval, "true");
                }
                else
                {
                    array_push($this->resultsRetval, "false");
                }

                if(!$this->parseOnly or ($this->parseOnly == $this->intOnly))
                {
                    exec("diff -b \"$testname.out\" \"$testname.superdupermemeint\"", $output, $diff);
                    if($diff == "0\n")
                    {
                        array_push($this->resultsInt, "true");
                    }
                    else
                    {
                        array_push($this->resultsInt, "false");
                    }
                }

                else
                {
                    exec("java -jar /pub/courses/ipp/jexamxml/jexamxml.jar \"$testname.out\" \"$testname.superdupermemexml\" /dev/null  /D /pub/courses/ipp/jexamxml/options", $output, $diff);
                    if($diff == "0\n")
                    {
                        array_push($this->resultsParse, "true");
                    }
                    else
                    {
                        $outFile = file_get_contents("$testname.out");
                        $customOut = file_get_contents("$testname.superdupermemexml");
                        if($outFile == $customOut)
                        {
                            array_push($this->resultsParse, "true");
                            continue;
                        }
                        array_push($this->resultsParse, "false");
                    }
                }
            }
        }

        // Method prints out the HTML page based on results on STDOUT
        public function print_result()
        {
            date_default_timezone_set("Europe/Prague");
            $time = date("Y-m-d - H:m:s");
            print "<!DOCTYPE html>
            <html>
            <head>
            <title>Test results</title>
            </head>
            <body style=\"background-color:rgb(23,24,28);color:white;font-family:arial\">";
            if($this->intOnly == $this->parseOnly)
            {
                print "<h1 style=\"text-align:center;\">Test results for \"parse.php\" and \"interpret.py\"</h1>";
            }
            else if($this->intOnly == TRUE)
            {
                print "<h1 style=\"text-align:center;\">Test results for \"interpret.py\"</h1>";
            }
            else if($this->parseOnly == TRUE)
            {
                print "<h1 style=\"text-align:center;\">Test results for \"parse.php\"</h1>";
            }
            print "<h2 style=\"text-align:center;\">$time</h2>
            <br>
            <table style=\"text-align:center;width:100%;font-size:12px\">\n";
            print "<tr style=\"font-size:17px\">";
            print "<th>File</th>";

            if(!$this->parseOnly or ($this->parseOnly == $this->intOnly))
            {
                print "<th>Interpret</th>";
            }
            else if(!$this->intOnly)
            {
                print "<th>Parser</th>";
            }
            
            print "<th>Return code</th> </tr>";
            
            $passedTests = 0;
            for($i = 0; $i < count($this->files); $i++)
            {
                print "<tr>";
                $file = $this->files[$i];
                print "<th style=\"font-size:10px\">$file</th>";
                if(!$this->parseOnly or ($this->parseOnly == $this->intOnly))
                {
                    if($this->resultsInt[$i] == "N/A")
                    {
                        print "<th style=\"color:white\"> - </th>";
                    }
                    else if($this->resultsInt[$i] == "true")
                    {
                        print "<th style=\"color:green\"> Success </th>";
                    }
                    else if($this->resultsInt[$i] == "false")
                    {
                        print "<th style=\"color:red\"> Failed </th>";
                    }
                }
                else if(!$this->intOnly)
                {
                    if($this->resultsParse[$i] == "N/A")
                    {
                        print "<th style=\"color:white\"> - </th>";
                    }
                    else if($this->resultsParse[$i] == "true")
                    {
                        print "<th style=\"color:green\"> Success </th>";
                    }
                    else if($this->resultsParse[$i] == "false")
                    {
                        print "<th style=\"color:red\"> Failed </th>";
                    }
                }

                if($this->resultsRetval[$i] == "true")
                {
                    print "<th style=\"color:green\"> Success </th>";
                    if(!$this->parseOnly or ($this->parseOnly == $this->intOnly))
                    {
                        if($this->resultsInt[$i] == "N/A" or $this->resultsInt[$i] == "true")
                        {
                            $passedTests++;
                        }
                    }
                    else
                    {
                        if($this->resultsParse[$i] == "N/A" or $this->resultsParse[$i] == "true")
                        {
                            $passedTests++;
                        }
                    }
                }
                else
                {
                    print "<th style=\"color:red\"> Failed </th>";
                }

                print "</tr>";

            }

            print "</table>";

            $testsCount = count($this->files);
            print "<p style=\"text-align:center;width:100%;font-size:14px\">Passed tests: $passedTests out of $testsCount </p>";
        }

        // Method prints out little hints, if interpret/parser
        // was not found
        public function print_hints()
        {
            if(!is_file($this->parser) && !$this->intOnly)
            {
                print "<p style=\"font-size:10px;text-align:center;\">Warning! \"parser.php\" not found! All tests for parser will fail.</p>"; 
            }
            if(!is_file($this->interpret) && !$this->parseOnly)
            {
                print "<p style=\"font-size:10px;text-align:center;\">Warning! \"interpret.py\" not found! All tests for interpret will fail.</p>"; 
            }
            print "</body>
            </html>";
        }

        // Method cleans up all temporary files
        public function clean_up()
        {
            foreach($this->files as $i)
            {
                $filename = substr($i, 0, -4);

                if(is_file("$filename.superdupermemeint"))
                {
                    exec("rm \"$filename.superdupermemeint\"");
                }

                if(is_file("$filename.superdupermemexml"))
                {
                    exec("rm \"$filename.superdupermemexml\"");
                }

		        if(is_file("$filename.out.log"))
		        {
		            exec("rm \"$filename.out.log\"");
		        }
                 
                exec("rm \"$filename.superdupermemeretval\"");
            }
        }
    }

    /**$tester = new Tester();

    $tester->parse_args($argv);

    $tester->fetch_tests();

    $tester->run_tests();

    $tester->print_result();

    $tester->print_hints();

    $tester->clean_up();**/
?>