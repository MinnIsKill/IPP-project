# XML interpreter
# Task #2 for IPP Project VUTBR FIT
# Author: xkalis03 (xkalis03@stud.fit.vutbr.cz)
# 

# python3 interpret.py --source=instruction_order1.src --input=instruction_order1.in

import xml.etree.ElementTree as ET # XML parsing module
import sys # for command line arguments reading

############################################################
#                         GLOBALS                          #
############################################################
stdin_file = ""
input_file = ""
source_file = ""

STDERR = sys.stderr.write
STDOUT = sys.stdout.write

instruct_cnt = 0
ordernums_list = []

global_frame = {}
local_frame = {}
temporary_frame = {}

#return_int = 0

help_msg = "Script of type interpreter (interpreter.py in Python 3.8) loads an XML representation of program and,\n\
using input from parameters entered from the command line, interprets this program and generates its output.\n\
\n\
  Script parameters:\n\
      --help         prints a help message to stdout (doesn't load any input). Returns 0\n\
      --source=file  file containing the XML representation of input source code\n\
      --input=file   file containing inputs needed for the interpretation of interpreted source code.\n\
                     Can be empty\n\
\n\
  General error codes:\n\
        10      missing script parameter (if needed) or an attempt at using a forbidden parameter combination\n\
        11      error opening input files (e.g. they don't exist, insufficient permission)\n\
        12      error opening output files for writing (e.g. insufficient permission, error while writing)\n\
        99      internal error (not affected by input files or command line parameters; e.g. memory allocation\n\
                error)\n\
\n\
  Error codes specific for parser:\n\
        31      wrong XML format of source file (file isn't so-called well-formed)\n\
        32      unexpected XML structure (e.g. element for argument outside element for instruction, duplicate\n\
                or negative instuction number, etc.)\n\
        52      IPPcode22 input semantic error (e.g. usage of undefined label, redefinition of a variable, etc.)\n\
        53      runtime interpretation error - wrong types of operands\n\
        54      runtime interpretation error - access request to a nonexistent variable (frame exists)\n\
        55      runtime interpretation error - frame doesn't exist (e.g. reading from an empty frame stack)\n\
        56      runtime interpretation error - missing value (in a variable, data stack or in call stack)\n\
        57      runtime interpretation error - bad operand value (e.g. division by zero, bad return value of\n\
                instruction exit\n\
        58      runtime interpretation error - incorrect string handling\n"

############################################################
#                   AUXILIARY FUNCTIONS                    #
############################################################

class AuxFuncs:
    #####
    ## checks input arguments for --help argument
    @staticmethod
    def check_helparg():
        for i, arg in enumerate(sys.argv):
            if ((i == 1) and (arg == "--help")): # first argument is --help
                STDOUT({help_msg} + '\n')
                exit(0)
            elif ((i >= 2) and (arg == "--help")): # if --help found in rest of arguments
                STDERR("ERROR[10]: --help parameter cannot be combined with any other parameters\n")
                exit(10)
            # else no --help found

    #####
    ## handles everything needed to check and prepare to be able to continue to the program's main function
    @staticmethod
    def program_start_handle():
        # count the number of instructions in XML
        global instruct_cnt
        children = list(myroot)
        for child in children:
            instruct_cnt += 1

        # put all found order numbers in a list
        for node in mytree.findall('.//instruction'):
            ordernums_list.append(node.attrib['order']) #!!! maybe  int(node.attrib['order'])  ??

        # check if duplicate or negative order numbers received (error)
        AuxFuncs.list_dupl_or_neg_check(ordernums_list)

    #####
    ## finds out if there are duplicate values in a list
    @staticmethod
    def list_dupl_or_neg_check(ordernums_list):
        l1 = []
        for i in ordernums_list:
            if ((i not in l1) and (int(i) > 0)):
                l1.append(i)
            else:
                STDERR("ERROR[32]: duplicate or negative order numbers found\n")
                l1.clear()
                AuxFuncs.error_cleanup(32)
        l1.clear()

    #####
    ## checks basic argument correctness
    #<var>: var
    #<label>: label
    #<symb>: int, bool, string, nil
    #<type>: type
    @staticmethod
    def check_arg(instr, opcode, num, type):
        symb_is_var = 0
        # check argument tag
        if (instr[num-1].tag != "arg{}".format(num)):
            STDERR("?ERROR?: arguments received by {opcode} in wrong order or format\n")
            AuxFuncs.error_cleanup(666)
        # check type of argument
        if (instr[num-1].attrib['type'] != type): #<symb>
            if (type == "symb"):
                if ((instr[num-1].attrib['type'] != "int") and (instr[num-1].attrib['type'] != "string") 
                and (instr[num-1].attrib['type'] != "bool") and (instr[num-1].attrib['type'] != "nil")
                and (instr[num-1].attrib['type'] != "var")):
                    STDERR("ERROR[53]: {opcode} received an argument of incompatible type\n")
                    AuxFuncs.error_cleanup(53)
                elif (instr[num-1].attrib['type'] == "var"):
                    symb_is_var = 1
            else:
                STDERR("ERROR[53]: {opcode} received an argument of incompatible type\n")
                AuxFuncs.error_cleanup(53)
        # if argument's a variable, check its correct syntax (mainly frame, though this should already be done in parser)
        if (((type == "var") or (symb_is_var == 1)) and (opcode != "DEFVAR")):
            if (instr[num-1].text[0:3] == "GF@"):
                if (instr[num-1].text[3:len(instr[num-1].text)] not in global_frame):
                    STDERR("ERROR[54]: {opcode} received an access request to a nonexistent variable (in global frame)\n")
            elif (instr[num-1].text[0:3] == "LF@"):
                if (instr[num-1].text[3:len(instr[num-1].text)] not in local_frame):
                    STDERR("ERROR[54]: {opcode} received an access request to a nonexistent variable (in local frame)\n")
            #elif (instr[num-1].text[0:3] == "TF@"):   #!!!  ?????
            else:
                STDERR("ERROR[55]: {opcode} received a variable with reference to a nonexistent (or empty) frame stack\n")

    #####
    ## handles program exit on error
    @staticmethod
    def error_cleanup(ID):
        ordernums_list.clear()
        global_frame.clear()

        sys.exit(ID)

    @staticmethod
    def get_prefix(instr):
        if (instr[1].attrib['type'] == "int"):
            return "i."
        elif (instr[1].attrib['type'] == "bool"):
            return "b."
        elif (instr[1].attrib['type'] == "string"):
            return "s."
        elif (instr[1].attrib['type'] == "nil"):
            return "n."

############################################################
#                    OPCODES' FUNCTIONS                    #
############################################################
class OpcodeFuncs:
    #####
    ##
    @staticmethod
    def MOVE(instr):
        AuxFuncs.check_arg(instr, "MOVE", 1, "var")
        AuxFuncs.check_arg(instr, "MOVE", 2, "symb")
        pre = AuxFuncs.get_prefix(instr)
        #TODO: add variable type to dictionary and check if two variables of different types can coexist (probably not)
        if (instr[0].text[0:3] == "GF@"):
            global_frame[instr[0].text[3:len(instr[0].text)]] = pre + str(instr[1].text)
        elif (instr[0].text[0:3] == "LF@"):
            local_frame[instr[0].text[3:len(instr[0].text)]] = pre + str(instr[1].text)
        #elif (instr[0].text[0:3] == "TF@"):   #!!!  ?????
    @staticmethod
    def CREATEFRAME():
        0
    @staticmethod
    def PUSHFRAME():
        0
    @staticmethod
    def POPFRAME():
        0
    @staticmethod
    def DEFVAR(instr):
        AuxFuncs.check_arg(instr, "DEFVAR", 1, "var")
        if (instr[0].text[0:3] == "GF@"):
            if ((instr[0].text[3:len(instr[0].text)]) not in global_frame):
                global_frame[instr[0].text[3:len(instr[0].text)]] = ""
            else:
                STDERR("ERROR[52]: found an attempt at variable redefinition in global frame\n")
                AuxFuncs.error_cleanup(52)
        elif (instr[0].text[0:3] == "LF@"): #!!!  AND local frame was initiated
            if ((instr[0].text[3:len(instr[0].text)]) not in local_frame):
                local_frame[instr[0].text[3:len(instr[0].text)]] = ""
            else:
                STDERR("ERROR[52]: found an attempt at variable redefinition in local frame\n")
                AuxFuncs.error_cleanup(52)
        #elif (instr[0].text[0:3] == "TF@"):   #!!!  ?????
    @staticmethod
    def CALL():
        0
    @staticmethod
    def RETURN():
        0
    #####
    ##
    @staticmethod
    def PUSHS():
        0
    @staticmethod
    def POPS():
        0
    #####
    ##
    @staticmethod
    def ADD():
        0
    @staticmethod
    def SUB():
        0
    @staticmethod
    def MUL():
        0
    @staticmethod
    def IDIV():
        0
    @staticmethod
    def LT():
        0
    @staticmethod
    def GT():
        0
    @staticmethod
    def EQ():
        0
    @staticmethod
    def AND():
        0
    @staticmethod
    def OR():
        0
    @staticmethod
    def NOT():
        0
    @staticmethod
    def INT2CHAR():
        0
    @staticmethod
    def STRI2INT():
        0
    #####
    ##
    @staticmethod
    def READ():
        0
    @staticmethod
    def WRITE(instr):
        AuxFuncs.check_arg(instr, "WRITE", 1, "symb")
        if(instr[0].attrib['type'] == "var"):
            if (instr[0].text[0:3] == "GF@"):
                STDOUT(str(global_frame[instr[0].text[3:len(instr[0].text)]][2:len(global_frame[instr[0].text[3:len(instr[0].text)]])]) + '\n')
            elif (instr[0].text[0:3] == "LF@"):
                STDOUT(str(local_frame[instr[0].text[3:len(instr[0].text)]][2:len(local_frame[instr[0].text[3:len(instr[0].text)]])]) + '\n')
            #elif (instr[0].text[0:3] == "TF@"):
        else:
            STDOUT(str(instr[0].text[3:len(instr[0].text)]) + '\n')
    #####
    ##
    @staticmethod
    def CONCAT():
        0
    @staticmethod
    def STRLEN():
        0
    @staticmethod
    def GETCHAR():
        0
    @staticmethod
    def SETCHAR():
        0
    #####
    ##
    @staticmethod
    def TYPE():
        0
    #####
    ##
    @staticmethod
    def LABEL():
        0
    @staticmethod
    def JUMP():
        0
    @staticmethod
    def JUMPIFEQ():
        0
    @staticmethod
    def JUMPIFNEQ():
        0
    #####
    ##
    @staticmethod
    def DPRINT():
        0
    @staticmethod
    def BREAK():
        0

############################################################
#                           MAIN                           #
############################################################
# command line arguments reading
if __name__ == "__main__":
    if (len(sys.argv) >= 2): # at least 1 parameter received (other than file call)
        AuxFuncs.check_helparg() # checks whether program received --help argument, error handling included
        for i, arg in enumerate(sys.argv): # iterate through all arguments
            if (arg[0:9] == "--source="):
                if (source_file == ""):
                    source_file = arg[9:len(arg)]
                else:
                    STDERR("ERROR[10]: --source received more than once\n")
                    AuxFuncs.error_cleanup(10)
            elif (arg[0:8] == "--input="):
                if (input_file == ""):
                    input_file = arg[8:len(arg)]
                else:
                    STDERR("ERROR[10]: --input received more than once\n")
                    AuxFuncs.error_cleanup(10)
    else:
        STDERR("ERROR[10]: insufficient number of input parameters\n")
        AuxFuncs.error_cleanup(10)
            
print(f"--Source file:  {source_file}")
print(f"--Input file:   {input_file}")

#TODO: do the same as the following but for Input file
if (source_file != ""):
    mytree = ET.parse(f'{source_file}')
else:
    mytree = ET.parse(f'{sys.stdin}')
myroot = mytree.getroot()

AuxFuncs.program_start_handle()

#####
## MAIN LOOP
#instr.attrib, instr.tag, instr.text, instr[0].text, instr.tail, instr.find("name")
class_opfuncs = OpcodeFuncs
i = 0
while i < instruct_cnt:
    order_num = min(ordernums_list) # get smallest order number from list
    ordernums_list.remove(order_num) # remove smallest order number from list
    for instr in myroot: # search through all instructions and
        if (instr.attrib['order'] == order_num): # find the one whose order number corresponds to searched one
            try:
                opcode = getattr(class_opfuncs, instr.attrib['opcode'])
            except:
                # shouldn't happen, as parser has already checked that all received OPCODEs are known
                STDERR("Method %s not implemented\n" % instr.attrib['opcode'])
                AuxFuncs.error_cleanup(666) #!!!
            opcode(instr)

            print('--' + instr.attrib['opcode'])
    i += 1


############################################################
#                     AUXILIARY PRINTS                     #
############################################################

# global frame contents
print(f"\nGLOBAL FRAME CONTENTS: ")
for var in global_frame:
    print(f"[{var}: {str(global_frame[var])}]", end=" ")
print(f"") #newline