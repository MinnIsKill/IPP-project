# <div style="text-align: center;">Implementation documentation for 2nd task of project for IPP 2021/2022</div>
<h3 style="text-align: center;">Author: Vojtěch Kališ <br/> Login: xkalis03</h3>

## Interpret
<div style="font-size: 10pt;">
The primary goal of script interpret.py is to take the output of the parser implemented in the first task, meaning the XML representation of the 
initial program written in IPPcode22, and, applying the logic of individual OPCODE functions described in task description starting at page 12, 
evaluate and print out the output of the program while checking its semantical correctness. For the purpose of this script's implementation, we 
could also to assume that the received XML input was without any fundamental flaws filtered out by the parser script from the previous task.

Should the interpret receive the optional argument '--help', it instead prints the corresponding help message to STDOUT and ceases its function.
</div>

## First and obvious tasks
<div style="font-size: 10pt;">
Primary concerns and preparations needed implementing first and foremost, which meant starting at arguments loading. Since this script's only 
received arguments are those which pass it the source file with the XML representation of the IPPcode22 program and the input file with user 
input, this was a fairly easy step, with the biggest challenge perhaps being not to forget to check whether said files can actually be opened 
for reading, if they actually exist or whether the source file contained a so-called "well-formed" XML.

The next big step lied in program start handling, where first semantics checks already take place, namely the ones which check whether received 
XML truly contains what is needed, whether there aren't any obvious errors such as some unknown tag being where an 'instruction' is expected etc.

Finally, handling loading each instruction based on their 'order' tag needed implementing as well, which was handled through the creation of a 
list to which all found OPCODE 'order' numbers are pushed and then this list is traversed upwardly from smallest number to highest.
</div>

## The not-so obvious - Labels, Frames
<div style="font-size: 10pt;">
While this went unnoticed at first, it later became obvious Labels needed to be handled first, as any interpreted instruction could be a jump to 
a Label which usually is ahead in the program and thus, if it wasn't created beforehand, there would be no way of getting to it. As such, the 
problem is handled by usage of a first loop which goes through the entirety of the program and only looks for the 'LABEL' OPCODE, creates a 
label of given name by using that name as key in a dictionary stack prepared specifically for labels, and then copies the remaining contents of 
the OPCODE 'order' numbers' list into this stack as the label's value. This way, anytime a jump at any label is made, this list of 'order' 
numbers is used to traverse the program from that point onwards. The only exception is the CALL function, which requires its own stack that can 
only be accessed by RETURN.

Frames are handled in a way that is very similar as well, that is, using dictionaries as stacks, though there are differences in between how 
each individual frame needs to be handled. Where Global frame (hereinafter GF) is defined at start, Local frame (LF) and Temporary frame (TF) 
aren't, with TF requiring a CREATEFRAME call and LF at least one TF to be pushed into it for them to become active. However, the way they work 
is fairly simple - whenever a new variable is created through the usage of DEFVAR, it is simply inserted into the frame defined in its creations 
(assuming the frame is already active and doesn't already contain a variable with the same name) with its name being the key inserted into the 
dictionary, and its value is set to being uninitialized at first, with inserting a value being the job of some other OPCODES.
</div>

## Making the work simpler
<div style="font-size: 10pt;">
From hereinafter, we already have all the necessary arrangements and structures and the only thing to do now is to enter our second and main 
loop, which, using the OPCODE 'order' numbers' list mentioned previously, reads XML input and calls functions based on received OPCODE, which 
all handle their specified jobs. However, implementing everything for each OPCODE individually would obviously be very tedious - not to mention 
pointless and wasteful as well. Which is why most OPCODEs use auxiliary functions specifically made to handle these tedious tasks. Following are 
some of the most used ones, together with their brief descriptions:

   * check_arg &nbsp;&nbsp; - checks basic argument correctness, such as whether it is of compatible type, if variable was received then checks whether it actually exists, etc.
   * get_prefix &nbsp;&nbsp; - returns the type of the passed argument (in the form of a short prefix, such ast "i." for int, "b." for bool, etc.).
   * get_val &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; - returns the value of the passed argument.
   * get_frame &nbsp;&nbsp; - returns the frame the passed variable is saved in.
   * symb_int_check_and_ret &nbsp; - checks if given attribute is of type int (returns its value) or not (error), mainly for arithmetics' purposes.
</div>

<br><br><br>
<div style="page-break-after: always;"></div>

## Testscript
<div style="font-size: 10pt;">
Test script's primary function lies in automatized testing of both previous scripts, parser.php and interpret.py, and printing out the results 
in the form of a HTML5 code. The script will traverse either the directory it's in or any other directory path it is given through the 
'--directory=' argument, recursively if '--recursive' also received, tests found .src and .in files using aforementioned scripts and prints the 
results out to stdout, in the HTML5 form as was also mentioned before.
</div>

## Arguments
<div style="font-size: 10pt;">
Argument loading is handled using three functions:

   * load_args &nbsp;&nbsp; - the "main" function, functions as a sort of hub which primarily calls the 'arg_find' function. Its other job is to 
   check whether '--help' was received and handling receiving an unknown &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; argument.
   * arg_find &nbsp;&nbsp;&nbsp;&nbsp; - searches for received argument in script arguments using regex and saves/updates value of said argument 
   if known. Handles receiving duplicate arguments and also paths or files &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; which don't exist.
   * args_compatibility_check &nbsp; - checks whether arguments that were received can actually go together (e.g. '--parse-only' can't go with '--int-only').
</div>

## Running the tests
<div style="font-size: 10pt;">
The actual running of the tests is done in a single function 'run_tests', which basically loads .src and .in files' input in a loop, runs it 
through parser, interpreter or both (based on received arguments) and then checks whether resulting output and Return code are the same as the 
control output and Return code attached (if either of these is missing, they're created as blank or with value "0" respectively). The script 
generates some auxiliary files in this step as well so some checks can be made, which are then deleted, but this can be prevented through the 
'--noclean' argument should anyone want to check those as well.

The results are saved into their respective, already pre-prepared arrays, which are then used by the next and last function whose job is to 
create the HTML5 output.
</div>

## HTML5 output
<div style="font-size: 10pt;">
The way this task was gone about was, well, by creating a desired webpage look in HTML5 first, and then simply inserting it directly into a 
string which would then be printed to STDOUT as a whole. Most of the code was simply that, just copying what was already prepared, only where 
variables such as the tests and their results themselves, the very purpose of this script, needed insertion did some sort of coding need to be
applied. It was still fairly simple, just pushing the already prepared arrays of source files, resulting Return codes, etc. into the output 
string.
</div>