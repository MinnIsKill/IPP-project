# <div style="text-align: center;">Implementation documentation for 1st task of project for IPP 2021/2022</div>
<h3 style="text-align: center;">Author: Vojtěch Kališ <br/> Login: xkalis03</h3>

## Parser - handling the basic problems
<div style="font-size: 10pt;">
The purpose of this parser was quite clear—to load input passed from STDIN, check its lexical and syntactical correctness and print its output to STDOUT. However, this brings with certain pitfalls which need to be handled right off the bat, namely to check whether there even is an input to read (or if it's readable), if script was called correctly (regarding passed parameters) or whether output is redirected to a file and if so whether said file's permissions allow for it to be opened and written into. The parser handles all of this before any and all actual parsing is done, at the start of its function, through simple arguments and STDIN + STDOUT checks.
  
Should the parser receive the optional argument '--help', it instead prints the corresponding help message to STDOUT and ceases its function.
</div>

## Preamble
<div style="font-size: 10pt;">
Should all aforementioned initial checks be passed, the parser starts loading input line-by-line in one large while loop which reads from STDIN as long as there's something to read. First, before the program's main loop starts parsing operation codes, it finds the first non-empty line with the first character in said line not being a hash (which would mean the line is only a comment which can be discarded), and once it finds a line meeting said criteria then its first string is compared to the preamble's required format, meaning case-insensitive 'IPP-code22' followed by nothing other than a comment or whitespaces. In case this step is successfully passed as well, the first two lines of the XML output are added to the output string and the program continues on.
</div>

## Opcodes
<div style="font-size: 10pt;">
Every received line at this point has to start with a known operation code (hereinafter referred to as OPCODE). A simple switch with string compares suffices here. After that, the parser increments found OPCODEs count and checks whether the number of arguments received for the currently parsed OPCODE equals the number of arguments it requires.

Should an unknown OPCODE be found, the parser first checks whether the offending line isn't actually just a commented line and if not, an appropriate error is thrown and parser ceases its function. The same goes for when parser finds that it received fewer or more arguments than the found OPCODE needs.
</div>

## Opcode arguments
<div style="font-size: 10pt;">
The last thing the parser is tasked to do is handle printing out the correct XML representation of every OPCODE's arguments. As every OPCODE comes with predefined types and order of arguments, it seemed only logical to handle this by creating a special function for each argument type and call them in their respective order for the currently parsed OPCODE, directly after the checks mentioned in the previous section were passed. Following are the decriptions of each of these argument types and what exactly is handled within their specific functions:
  
  * ⟨var⟩   - &nbsp;an OPCODE accepts variables in place of the 'var' argument type. These have to start with one of three specific prefixes which tell us which frame the variable's saved in ('TF@','LF@' or 'GF@), followed by its name, which has to start with a character from a specific set of characters except for a number, and followed by characters from the same list of characters but this time including numbers. This can be achieved through a simple RegEx shown below. Additionally, any '&' character found in the name of a variable has to be replaced by its XML representation, handled outside of the RegEx (\&amp;).
  
  ```regex
   ^(GF@|LF@|TF@)+([A-Z]|[a-z]|\_|\-|\$|\&|\%|\*|\!|\?)(([0-9]|[A-Z]|[a-z]|\_|\-|\$|\&|\%|\*|\!|\?))*$
  ```
  
  * ⟨symb⟩  - &nbsp;an OPCODE accepts either a variable or a constant in place of a 'symb' argument. Should it receive a variable, it calls the function which handles them (already described in ⟨var⟩). In case of receiving a constant, parser checks whether these are in the correct format as well. There are four types of acceptable constants defined by having the correct prefixes: **[1] 'nil@'**, which can only be received as 'nil@nil', **[2] 'bool@'**, which can only by received with values 'true' or 'false', **[3] 'int@'**, which can be followed by nothing but numbers, and last but not least **[4] 'string@'**, whose handling is more complicated and therefore has a special dedicated function for it, in which the string's contents are searched for any possible escape sequences and whether they are correct, done similarly as in the case of variables through another RegEx (shown below) and the contents are also searched through afterwards for specific special characters (&, \', <, >, \\) which are then replaced by their XML representations (\&amp;, \&apos;, \&lt;, \&gt;, \&quot;).

  ```regex
   (\\\\[^0-9])|(\\\\[0-9][^0-9])|(\\\\[0-9][0-9][^0-9])
  ```

  * ⟨label⟩  &nbsp;- &nbsp;'label' follows the same rules as a variable, except it mustn't have the prefix
  * ⟨type⟩  &nbsp;&nbsp;- &nbsp;'type' refers simply to a string carrying the name of type of a variable or constant. The only acceptable strings in place of 'type' are: 'nil', 'int', 'bool' and 'string'
  
</div>
<div style="font-size: 10pt;">
Should any of the criteria for any given OPCODE argument not be met parser throws and appropriate error and ceases its function.
  
NOTE: It isn't the parser's job to check semantical correctness! It doesn't, for example, check whether a variable or label passed as an OPCODE's argument has been defined or not.
</div>