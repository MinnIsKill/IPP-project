# XML interpreter
# Task #2 for IPP Project VUTBR FIT
# Author: xkalis03 (xkalis03@stud.fit.vutbr.cz)
# 

# python3 interpret.py --source=name.src --input=name.in

############################################################
#                         IMPORTS                          #
############################################################

import xml.etree.ElementTree as ET # XML parsing module
import sys
import time
import re

from sqlalchemy import true # for command line arguments reading
from os.path import exists as file_exists

############################################################
#                         GLOBALS                          #
############################################################
stdin_file = ""
input_file = ""
source_file = ""

instrs_done = 0  # how many instructions have already been successfully processed (except for DPRINT and BREAK)

STDERR = sys.stderr.write
STDOUT = sys.stdout.write

instruct_cnt = 0
ordernums_list = []
Labels_stack = {}

Call_stack = [] # for CALL and RETURN
Call_stack_instrpos = [] # saves instruct positions for CALL and RETURN

Frames_stack = [] # stack of pushed temporary frames, will be searched through before local frame
pushed_TF_cnt = 0

Data_stack = [] # data stack for values

# - global frame is always active
global_frame = {}
# - local frame is basically just a stack of frames, at the start LF is at its top, but when a temporary frame gets
#   pushed (PUSHFRAME), the TF is inserted at the top of LF and you lose access to LF (because it's now lower in the
#   stack) until the TF at the top is popped (POPFRAME) back out.
# - multiple temporary frames can be pushed to the top of LF
# - local frame is undefined at program start
local_frame = {"status": 0}  # not active
# - temporary frame becomes active when CREATEFRAME is called
temporary_frame = {"status": 0}  # not active

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
############################################################
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

############################################################
## handles everything needed to check and prepare to be able to continue to the program's main function
    @staticmethod
    def program_start_handle():
        # count the number of instructions in XML
        global instruct_cnt
        children = list(myroot)
        for child in children:
            instruct_cnt += 1

        found_instruct_cnt = 0   

        # put all found order numbers in a list
        for node in mytree.findall('.//instruction'):
            if 'order' not in node.attrib:
                STDERR("ERROR[32]: an instruction without 'order' tag found\n")
                AuxFuncs.error_cleanup(32)
            if 'opcode' not in node.attrib:
                STDERR("ERROR[32]: an instruction without 'opcode' tag found\n")
                AuxFuncs.error_cleanup(32)
            if ((node.attrib['order']).isdigit() != 1):
                STDERR("ERROR[32]: an instruction without a number in 'order' tag found\n")
                AuxFuncs.error_cleanup(32)
            else:
                ordernums_list.append(int(node.attrib['order']))
            found_instruct_cnt = found_instruct_cnt+1

        if instruct_cnt != found_instruct_cnt:
            STDERR("ERROR[32]: found an unknown element in place of an instruction\n")
            AuxFuncs.error_cleanup(32)

        # check if duplicate or negative order numbers received (error)
        AuxFuncs.list_dupl_or_neg_check(ordernums_list)

############################################################
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

############################################################
## used by opcodes to get positions or arguments (handles cases when arguments aren't in the right order)
## also checks if number of arguments for given opcode is right
    @staticmethod
    def arg_enumerate(instr, opcode, num, numofargs):
        spot = 1
        
        AuxFuncs.argcnt_check(instr, opcode, num, numofargs, False)

        if (instr[0].tag == "arg{}".format(num)):
            spot = 1
        elif numofargs > 1:
            if (instr[1].tag == "arg{}".format(num)):
                spot = 2
            elif numofargs == 3: #max 3 arguments
                if (instr[2].tag == "arg{}".format(num)):
                    spot = 3
                else:
                    STDERR(f"ERROR[32]: arguments received by {opcode} in wrong format\n")
                    AuxFuncs.error_cleanup(32)
            else:
                STDERR(f"ERROR[32]: arguments received by {opcode} in wrong format\n")
                AuxFuncs.error_cleanup(32)
        else:
            STDERR(f"ERROR[32]: arguments received by {opcode} in wrong format\n")
            AuxFuncs.error_cleanup(32)

        return spot

############################################################
## checks if number of arguments for given opcode is right
## and if arguments are in the right format
    def argcnt_check(instr, opcode, num, numofargs, singlearg):
        arguments = list(instr)
        # check if no arguments received (meaning arg1 is missing)
        if (len(arguments) == 0):
            STDERR(f"ERROR[32]: no arguments received by {opcode}\n")
            AuxFuncs.error_cleanup(32)
        elif (len(arguments) != numofargs):
            STDERR(f"ERROR[32]: {opcode} received either too little or too many arguments\n")
            AuxFuncs.error_cleanup(32)
        # check argument tag
        if (instr[num-1].tag[3].isdigit() == 0):
            STDERR(f"ERROR[32]: arguments received by {opcode} in wrong format\n")
            AuxFuncs.error_cleanup(32)
        if (singlearg == True):
            if (instr[0].tag != "arg1"):
                STDERR(f"ERROR[32]: arguments received by {opcode} in wrong format\n")
                AuxFuncs.error_cleanup(32)

############################################################
## checks basic argument correctness (for further info check the comments inside the function)
#<var>: var
#<label>: label
#<symb>: int, bool, string, nil, var
#<type>: type
    @staticmethod
    def check_arg(instr, opcode, num, type):
        num = num - 1
        symb_is_var = 0
        # check type of argument
        if (instr[num].attrib['type'] != type): #string 'type' by argument doesn't correspond with searched string (typical for <symb>)
            if (type == "symb"):
                if ((instr[num].attrib['type'] != "int") and (instr[num].attrib['type'] != "string") 
                and (instr[num].attrib['type'] != "bool") and (instr[num].attrib['type'] != "nil")
                and (instr[num].attrib['type'] != "var")):
                    STDERR(f"ERROR[53]: {opcode} received an argument of incompatible type\n")
                    AuxFuncs.error_cleanup(53)
                elif (instr[num].attrib['type'] == "var"):
                    symb_is_var = 1
            else:
                STDERR(f"ERROR[53]: {opcode} received an argument of incompatible type\n")
                AuxFuncs.error_cleanup(53)
        # if argument's a variable, check its correct syntax (mainly frame, though this should already be done in parser)
        # and also check if said variable actually exists in given frame
        # this part is ignored for DEFVAR!
        if (((type == "var") or (symb_is_var == 1)) and (opcode != "DEFVAR")):
            if (instr[num].text[0:3] == "GF@"):
                if (instr[num].text[3:len(instr[num].text)] not in global_frame):
                    STDERR(f"ERROR[54]: {opcode} received an access request to a nonexistent variable (in global frame)\n")
                    AuxFuncs.error_cleanup(54)
            elif (instr[num].text[0:3] == "LF@"):
                if (local_frame["status"] == 0):
                    STDERR(f"ERROR[55]: {opcode} received a variable with reference to an uninitialized (or empty) frame stack\n")
                    AuxFuncs.error_cleanup(55)
                if (instr[num].text[3:len(instr[num].text)] not in local_frame):
                    STDERR(f"ERROR[54]: {opcode} received an access request to a nonexistent variable (in local frame)\n")
                    AuxFuncs.error_cleanup(54)
            elif (instr[num].text[0:3] == "TF@"):
                if (temporary_frame["status"] == 0):  # not active
                    STDERR("ERROR[55]: found an attempt at pushing a non-existent Temporary frame\n")
                    AuxFuncs.error_cleanup(55)
                if (instr[num].text[3:len(instr[num].text)] not in temporary_frame):
                    STDERR(f"ERROR[54]: {opcode} received an access request to a nonexistent variable (in temporary frame)\n")
                    AuxFuncs.error_cleanup(54)
                if not Frames_stack: #if Frames_stack is empty
                    STDERR(f"ERROR[55]: {opcode} received a variable with reference to an uninitialized (or empty) frame stack\n")
                    AuxFuncs.error_cleanup(55)
            else:
                STDERR(f"ERROR[55]: {opcode} received a variable with reference to a nonexistent frame stack\n")
                AuxFuncs.error_cleanup(55)
        # if argument's supposed to be of type 'type', check if its contents are a legitimate type name
        if ((instr[num].attrib['type'] == "type") and (type == "type")):
            if ((instr[num].text != "int") and (instr[num].text != "string") and (instr[num].text != "bool")):
                STDERR(f"ERROR[53]: {opcode} received an argument of incompatible type in place of 'type'\n")
                AuxFuncs.error_cleanup(53)
        # if argument's supposed to be of type 'label', check if label exists
        if ((instr[num].attrib['type'] == "label") and (type == "label") and (opcode != "LABEL")):
            if (instr[num].text not in Labels_stack):
                STDERR(f"ERROR[52]: {opcode} encountered an attempt at jump to an uknown/nonexistent label\n")
                AuxFuncs.error_cleanup(52)

############################################################
## mainly for arithmetics' purposes (ADD, SUB, MUL, IDIV)
## checks if given attribute is of type int (returns its value) or not (error)
    @staticmethod
    def symb_int_check_and_ret(instr, opcode, num):
        frame = AuxFuncs.get_frame(instr, num)
        num = num - 1
        global global_frame
        global local_frame
        global temporary_frame

        if (instr[num].attrib['type'] == "int"):
            if ((instr[num].text).lstrip("-").isdigit() == 1):
                return instr[num].text
            else:
                STDERR("ERROR[53]: bad operand value found: attribute of type 'int' does not contain an integer\n")
                AuxFuncs.error_cleanup(53)
        elif (instr[num].attrib['type'] == "var"):
            if (globals()[frame][instr[num].text[3:len(instr[num].text)]][0:2] == "i."):
                return globals()[frame][instr[num].text[3:len(instr[num].text)]][2:len(globals()[frame][instr[num].text[3:len(instr[num].text)]])]
            elif (globals()[frame][instr[num].text[3:len(instr[num].text)]] == ""):
                STDERR(f"ERROR[56]: {opcode} received an argument which is unset (missing a value)\n")
                AuxFuncs.error_cleanup(56)
        else:
            STDERR(f"ERROR[53]: {opcode} received an operant of unexpected type (expected 'int')\n")
            AuxFuncs.error_cleanup(53)
        
############################################################
## handles program exit on error
    @staticmethod
    def error_cleanup(ID):
        ordernums_list.clear()
        global_frame.clear()
        local_frame.clear()
        temporary_frame.clear()

        sys.exit(ID)

############################################################
## returns the prefix for the type of the passed argument
    @staticmethod
    def get_prefix(instr, num):
        num = num - 1
        if (instr[num].attrib['type'] == "int"):
            if ((instr[num].text).lstrip("-").isdigit() == 1):
                return "i."
            else:
                STDERR("ERROR[53]: found an ambiguous argument of type 'int' which doesn't contain an integer\n")
                AuxFuncs.error_cleanup(53)
        elif (instr[num].attrib['type'] == "bool"):
            if (((instr[num].text) == "true") or ((instr[num].text) == "false")):
                return "b."
            else:
                STDERR("ERROR[53]: found an ambiguous argument of type 'bool' which doesn't contain a boolean type\n")
                AuxFuncs.error_cleanup(53)
        elif (instr[num].attrib['type'] == "nil"):
            if ((instr[num].text) == "nil"):
                return "n."
            else:
                STDERR("ERROR[53]: found an ambiguous argument of type 'nil' which doesn't contain \"nil\"\n")
                AuxFuncs.error_cleanup(53)
        elif (instr[num].attrib['type'] == "string"):
            return "s."
        elif (instr[num].attrib['type'] == "var"):
            frame = AuxFuncs.get_frame(instr, num+1)
            if (globals()[frame][instr[num].text[3:len(instr[num].text)]] == ""): # if var is uninitialized
                return "unset_var"
            elif (globals()[frame][instr[num].text[3:len(instr[num].text)]] == "NULL"): # if var isn't initialized but was handled already (for TYPE)
                return "NULL"
            elif (globals()[frame][instr[num].text[3:len(instr[num].text)]][1] != "."): #type
                return "type"
            else:
                return (globals()[frame][instr[num].text[3:len(instr[num].text)]][0:2])
        elif (instr[num].attrib['type'] == "type"):
            return "type"

############################################################
## returns the value of the passed argument
    @staticmethod
    def get_val(instr, num):
        frame = AuxFuncs.get_frame(instr, num)
        num = num - 1

        if (instr[num].text == None):
            return ""

        if (instr[num].attrib['type'] == "int"):
            if ((instr[num].text).lstrip("-").isdigit() == 1):
                return instr[num].text
            else:
                STDERR("ERROR[53]: found an ambiguous argument of type 'int' which doesn't contain an integer\n")
                AuxFuncs.error_cleanup(53)
        elif (instr[num].attrib['type'] == "bool"):
            if (((instr[num].text) == "true") or ((instr[num].text) == "false")):
                return instr[num].text
            else:
                STDERR("ERROR[53]: found an ambiguous argument of type 'bool' which doesn't contain a boolean type\n")
                AuxFuncs.error_cleanup(53)
        elif (instr[num].attrib['type'] == "nil"):
            if ((instr[num].text) == "nil"):
                return instr[num].text
            else:
                STDERR("ERROR[53]: found an ambiguous argument of type 'nil' which doesn't contain \"nil\"\n")
                AuxFuncs.error_cleanup(53)
        elif ((instr[num].attrib['type'] == "string") or (instr[num].attrib['type'] == "label")):
            return instr[num].text
        elif (instr[num].attrib['type'] == "var"):
            return (globals()[frame][instr[num].text[3:len(instr[num].text)]][2:len(globals()[frame][instr[num].text[3:len(instr[num].text)]])])

############################################################
## returns the frame the passed variable is saved in
    @staticmethod
    def get_frame(instr, num):
        num = num - 1
        if (instr[num].text != None):
            if (instr[num].text[0:3] == "GF@"):
                return "global_frame"
            elif (instr[num].text[0:3] == "LF@"):
                return "local_frame"
            elif (instr[num].text[0:3] == "TF@"):
                return "temporary_frame"
############################################################
#                    OPCODES' FUNCTIONS                    #
############################################################
class OpcodeFuncs:
#####
##
# MOVE:        tested
# CREATEFRAME: tested
# PUSHFRAME:   tested
# POPFRAME:    tested
# DEFVAR:      tested
# CALL:        
# RETURN:      
##
############################################################
    @staticmethod
    def MOVE(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "MOVE", 1, 2)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "MOVE", 2, 2)

        AuxFuncs.check_arg(instr, "MOVE", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "MOVE", secondarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)

        pre = AuxFuncs.get_prefix(instr, secondarg_pos)

        if (pre == "unset_var"):
            STDERR(f"ERROR[56]: MOVE received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        val = AuxFuncs.get_val(instr, secondarg_pos)

        globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = pre + str(val)
############################################################
    @staticmethod
    def CREATEFRAME(instr):
        dict.clear(temporary_frame)
        temporary_frame["status"] = 1  # active
############################################################
    @staticmethod
    def PUSHFRAME(instr):
        global pushed_TF_cnt
        global Frames_stack
        global local_frame

        if (temporary_frame["status"] == 0):  # not active
            STDERR("ERROR[55]: found an attempt at pushing a non-existent Temporary frame\n")
            AuxFuncs.error_cleanup(55)

        pushed_TF_cnt += 1
        Frames_stack.append(temporary_frame.copy())
        local_frame = Frames_stack[pushed_TF_cnt]

        temporary_frame.clear()
        temporary_frame["status"] = 0  # not active
############################################################
    @staticmethod
    def POPFRAME(instr):
        global pushed_TF_cnt
        global Frames_stack
        global temporary_frame
        global local_frame

        if pushed_TF_cnt == 0:
            STDERR("ERROR[55]: found an attempt at popping an empty Frames stack\n")
            AuxFuncs.error_cleanup(55)

        temporary_frame = Frames_stack[-1].copy() #copy top of stack to TF
        del Frames_stack[-1] #remove the copied frame at top of stack
        pushed_TF_cnt -= 1

        local_frame = Frames_stack[pushed_TF_cnt] #adjust LF so it points at new top of stack
        
        temporary_frame["status"] = 1  # active
############################################################
    @staticmethod
    def DEFVAR(instr):
        AuxFuncs.argcnt_check(instr, "DEFVAR", 1, 1, True)

        AuxFuncs.check_arg(instr, "DEFVAR", 1, "var")

        if (instr[0].text[0:3] == "GF@"):
            if ((instr[0].text[3:len(instr[0].text)]) not in global_frame):
                global_frame[instr[0].text[3:len(instr[0].text)]] = ""
            else:
                STDERR("ERROR[52]: found an attempt at variable redefinition in global frame\n")
                AuxFuncs.error_cleanup(52)
        elif (instr[0].text[0:3] == "LF@"): #!!!  AND local frame was initiated
            if (local_frame["status"] == 0):
                STDERR(f"ERROR[55]: {opcode} received a variable with reference to an uninitialized (or empty) frame stack\n")
                AuxFuncs.error_cleanup(55)
            if ((instr[0].text[3:len(instr[0].text)]) not in local_frame):
                local_frame[instr[0].text[3:len(instr[0].text)]] = ""
            else:
                STDERR("ERROR[52]: found an attempt at variable redefinition in local frame\n")
                AuxFuncs.error_cleanup(52)
        elif (instr[0].text[0:3] == "TF@"):
            if (temporary_frame["status"] == 0):
                STDERR(f"ERROR[55]: {instr.attrib['opcode']} received a variable with reference to an uninitialized (or empty) frame stack\n")
                AuxFuncs.error_cleanup(55)
            if ((instr[0].text[3:len(instr[0].text)]) not in temporary_frame):
                temporary_frame[instr[0].text[3:len(instr[0].text)]] = ""
            else:
                STDERR("ERROR[52]: found an attempt at variable redefinition in temporary frame\n")
                AuxFuncs.error_cleanup(52)
            if not Frames_stack: #if Frames_stack is empty
                    STDERR(f"ERROR[55]: {instr.attrib['opcode']} received a variable with reference to an uninitialized (or empty) frame stack\n")
                    AuxFuncs.error_cleanup(55)
############################################################
    @staticmethod
    def CALL(instr):
        global ordernums_list
        global instruct_position
        global Labels_stack
        global Call_stack
        global Call_stack_instrpos

        AuxFuncs.argcnt_check(instr, "CALL", 1, 1, True)

        AuxFuncs.check_arg(instr, "CALL", 1, "label")

        name = AuxFuncs.get_val(instr, 1)

        Call_stack.append(ordernums_list.copy())
        Call_stack_instrpos.append(instruct_position)

        instruct_position = instruct_position + (len(ordernums_list) - len(Labels_stack[name]))

        ordernums_list = Labels_stack[name].copy()
############################################################
    @staticmethod
    def RETURN(instr):
        global ordernums_list
        global instruct_position
        global Labels_stack
        global Call_stack
        global Call_stack_instrpos

        if (len(Call_stack_instrpos) == 0):
            STDERR(f"ERROR[56]: RETURN encountered an attempt at popping from an empty Call stack\n")
            AuxFuncs.error_cleanup(56)

        instruct_position = Call_stack_instrpos.pop() # remove and return last saved order number

        ordernums_list = Call_stack.pop() # remove and return last call save
############################################################
##
# PUSHS: lightly tested
# POPS:  lightly tested
##
############################################################
    @staticmethod
    def PUSHS(instr):
        AuxFuncs.argcnt_check(instr, "PUSHS", 1, 1, True)

        AuxFuncs.check_arg(instr, "PUSHS", 1, "symb")

        pre = AuxFuncs.get_prefix(instr, 1)

        if (pre == "unset_var"):
            STDERR(f"ERROR[56]: PUSHS received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        Data_stack.append(pre + str(instr[0].text))
############################################################
    @staticmethod
    def POPS(instr):
        global Data_stack

        AuxFuncs.argcnt_check(instr, "POPS", 1, 1, True)

        AuxFuncs.check_arg(instr, "POPS", 1, "var")

        frame = AuxFuncs.get_frame(instr, 1)

        globals()[frame][instr[0].text[3:len(instr[0].text)]] = Data_stack[-1]
        
        del Data_stack[-1] #remove the copied value at top of stack
############################################################
##
# ADD:      lightly tested
# SUB:      lightly tested
# MUL:      lightly tested
# IDIV:     lightly tested
# LT:       lightly tested
# GT:       lightly tested
# EQ:       lightly tested
# AND:      lightly tested
# OR:       lightly tested
# NOT:      lightly tested
# INT2CHAR: not tested
# STRI2INT: not tested
##
############################################################
    @staticmethod
    def ADD(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "ADD", 1, 3)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "ADD", 2, 3)
        thirdarg_pos = AuxFuncs.arg_enumerate(instr, "ADD", 3, 3)

        AuxFuncs.check_arg(instr, "ADD", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "ADD", secondarg_pos, "symb")
        AuxFuncs.check_arg(instr, "ADD", thirdarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)
        
        symb1 = AuxFuncs.symb_int_check_and_ret(instr, "ADD", secondarg_pos)
        symb2 = AuxFuncs.symb_int_check_and_ret(instr, "ADD", thirdarg_pos)

        globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "i." + (str(int(symb1) + int(symb2)))
############################################################
    @staticmethod
    def SUB(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "SUB", 1, 3)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "SUB", 2, 3)
        thirdarg_pos = AuxFuncs.arg_enumerate(instr, "SUB", 3, 3)

        AuxFuncs.check_arg(instr, "SUB", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "SUB", secondarg_pos, "symb")
        AuxFuncs.check_arg(instr, "SUB", thirdarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)
        
        symb1 = AuxFuncs.symb_int_check_and_ret(instr, "SUB", secondarg_pos)
        symb2 = AuxFuncs.symb_int_check_and_ret(instr, "SUB", thirdarg_pos)

        globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "i." + (str(int(symb1) - int(symb2)))
############################################################
    @staticmethod
    def MUL(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "MUL", 1, 3)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "MUL", 2, 3)
        thirdarg_pos = AuxFuncs.arg_enumerate(instr, "MUL", 3, 3)

        AuxFuncs.check_arg(instr, "MUL", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "MUL", secondarg_pos, "symb")
        AuxFuncs.check_arg(instr, "MUL", thirdarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)
        
        symb1 = AuxFuncs.symb_int_check_and_ret(instr, "MUL", secondarg_pos)
        symb2 = AuxFuncs.symb_int_check_and_ret(instr, "MUL", thirdarg_pos)

        globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "i." + (str(int(symb1) * int(symb2)))
############################################################
    @staticmethod
    def IDIV(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "IDIV", 1, 3)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "IDIV", 2, 3)
        thirdarg_pos = AuxFuncs.arg_enumerate(instr, "IDIV", 3, 3)

        AuxFuncs.check_arg(instr, "IDIV", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "IDIV", secondarg_pos, "symb")
        AuxFuncs.check_arg(instr, "IDIV", thirdarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)
        
        symb1 = AuxFuncs.symb_int_check_and_ret(instr, "IDIV", secondarg_pos)
        symb2 = AuxFuncs.symb_int_check_and_ret(instr, "IDIV", thirdarg_pos)

        if (int(symb2) == 0):
            STDERR("ERROR[57]: IDIV received '0' as a second attribute (attempt in division by zero)\n")
            AuxFuncs.error_cleanup(57)

        globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "i." + (str(int(symb1) // int(symb2)))
############################################################
    @staticmethod
    def LT(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "LT", 1, 3)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "LT", 2, 3)
        thirdarg_pos = AuxFuncs.arg_enumerate(instr, "LT", 3, 3)

        AuxFuncs.check_arg(instr, "LT", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "LT", secondarg_pos, "symb")
        AuxFuncs.check_arg(instr, "LT", thirdarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)

        type1 = AuxFuncs.get_prefix(instr, secondarg_pos)
        type2 = AuxFuncs.get_prefix(instr, thirdarg_pos)

        if ((type1 == "unset_var") or (type2 == "unset_var")):
            STDERR(f"ERROR[56]: LT received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        val1 = AuxFuncs.get_val(instr, secondarg_pos)
        val2 = AuxFuncs.get_val(instr, thirdarg_pos)

        if ((type1 == "i.") and (type2 == "i.")):
            if (int(val1) < int(val2)):
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.true"
            else:
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.false"
        elif ((type1 == "b.") and (type2 == "b.")):
            if ((val1 == "true") or ((val1 == "false") and (val2 == "false"))):
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.false"
            else: #the only time val1 can be less than val2 is if val1=false and val2=true 
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.true"
        elif ((type1 == "s.") and (type2 == "s.")):
            if (val1 < val2): #python handles string comparisons lexicographically already
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.true"
            else:
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.false"
        elif ((type1 == "n.") or (type2 == "n.")):
            STDERR("ERROR[53]: LT received 'nil' as an attribute\n")
            AuxFuncs.error_cleanup(53)
        else:
            STDERR("ERROR[53]: LT received an attempt at comparing two attributes of different types\n")
            AuxFuncs.error_cleanup(53)
############################################################
    @staticmethod
    def GT(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "GT", 1, 3)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "GT", 2, 3)
        thirdarg_pos = AuxFuncs.arg_enumerate(instr, "GT", 3, 3)

        AuxFuncs.check_arg(instr, "GT", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "GT", secondarg_pos, "symb")
        AuxFuncs.check_arg(instr, "GT", thirdarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)

        type1 = AuxFuncs.get_prefix(instr, secondarg_pos)
        type2 = AuxFuncs.get_prefix(instr, thirdarg_pos)

        if ((type1 == "unset_var") or (type2 == "unset_var")):
            STDERR(f"ERROR[56]: GT received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        val1 = AuxFuncs.get_val(instr, secondarg_pos)
        val2 = AuxFuncs.get_val(instr, thirdarg_pos)

        if ((type1 == "i.") and (type2 == "i.")):
            if (int(val1) > int(val2)):
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.true"
            else:
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.false"
        elif ((type1 == "b.") and (type2 == "b.")):
            if ((val1 == "false") or ((val1 == "true") and (val2 == "true"))):
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.false"
            else: #the only time val1 can be more than val2 is if val1=true and val2=false 
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.true"
        elif ((type1 == "s.") and (type2 == "s.")):
            if (val1 > val2): #python handles string comparisons lexicographically already
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.true"
            else:
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.false"
        elif ((type1 == "n.") or (type2 == "n.")):
            STDERR("ERROR[53]: GT received 'nil' as an attribute\n")
            AuxFuncs.error_cleanup(53)
        else:
            STDERR("ERROR[53]: GT received an attempt at comparing two attributes of different types\n")
            AuxFuncs.error_cleanup(53)
############################################################
    @staticmethod
    def EQ(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "EQ", 1, 3)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "EQ", 2, 3)
        thirdarg_pos = AuxFuncs.arg_enumerate(instr, "EQ", 3, 3)

        AuxFuncs.check_arg(instr, "EQ", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "EQ", secondarg_pos, "symb")
        AuxFuncs.check_arg(instr, "EQ", thirdarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)

        type1 = AuxFuncs.get_prefix(instr, secondarg_pos)
        type2 = AuxFuncs.get_prefix(instr, thirdarg_pos)

        if ((type1 == "unset_var") or (type2 == "unset_var")):
            STDERR(f"ERROR[56]: EQ received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        val1 = AuxFuncs.get_val(instr, secondarg_pos)
        val2 = AuxFuncs.get_val(instr, thirdarg_pos)

        if ((type1 == "i.") and (type2 == "i.")):
            if (int(val1) == int(val2)):
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.true"
            else:
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.false"
        elif ((type1 == "b.") and (type2 == "b.")):
            if (((val1 == "true") and (val2 == "true")) or ((val1 == "false") and (val2 == "false"))):
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.true"
            else:
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.false"
        elif ((type1 == "s.") and (type2 == "s.")):
            if (val1 == val2): #python handles string comparisons lexicographically already
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.true"
            else:
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.false"
        elif ((type1 == "n.") or (type2 == "n.")):
            if (val1 == val2):
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.true"
            else:
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.false"
        else:
            STDERR("ERROR[53]: EQ received an attempt at comparing two attributes of different types\n")
            AuxFuncs.error_cleanup(53)
############################################################
    @staticmethod
    def AND(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "AND", 1, 3)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "AND", 2, 3)
        thirdarg_pos = AuxFuncs.arg_enumerate(instr, "AND", 3, 3)

        AuxFuncs.check_arg(instr, "AND", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "AND", secondarg_pos, "symb")
        AuxFuncs.check_arg(instr, "AND", thirdarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)

        type1 = AuxFuncs.get_prefix(instr, secondarg_pos)
        type2 = AuxFuncs.get_prefix(instr, thirdarg_pos)

        if ((type1 == "unset_var") or (type2 == "unset_var")):
            STDERR(f"ERROR[56]: AND received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        val1 = AuxFuncs.get_val(instr, secondarg_pos)
        val2 = AuxFuncs.get_val(instr, thirdarg_pos)

        if ((type1 == "b.") and (type2 == "b.")):
            if ((val1 == "true") and (val2 == "true")):
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.true"
            else:
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.false"
        else:
            STDERR("ERROR[53]: AND received at least one attribute not of boolean type\n")
            AuxFuncs.error_cleanup(53)
############################################################
    @staticmethod
    def OR(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "OR", 1, 3)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "OR", 2, 3)
        thirdarg_pos = AuxFuncs.arg_enumerate(instr, "OR", 3, 3)

        AuxFuncs.check_arg(instr, "OR", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "OR", secondarg_pos, "symb")
        AuxFuncs.check_arg(instr, "OR", thirdarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)

        type1 = AuxFuncs.get_prefix(instr, secondarg_pos)
        type2 = AuxFuncs.get_prefix(instr, thirdarg_pos)

        if ((type1 == "unset_var") or (type2 == "unset_var")):
            STDERR(f"ERROR[56]: OR received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        val1 = AuxFuncs.get_val(instr, secondarg_pos)
        val2 = AuxFuncs.get_val(instr, thirdarg_pos)

        if ((type1 == "b.") and (type2 == "b.")):
            if ((val1 == "true") or (val2 == "true")):
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.true"
            else:
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.false"
        else:
            STDERR("ERROR[53]: OR received at least one attribute not of boolean type\n")
            AuxFuncs.error_cleanup(53)
############################################################
    @staticmethod
    def NOT(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "NOT", 1, 2)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "NOT", 2, 2)

        AuxFuncs.check_arg(instr, "NOT", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "NOT", secondarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)

        type = AuxFuncs.get_prefix(instr, secondarg_pos)

        if (type == "unset_var"):
            STDERR(f"ERROR[56]: NOT received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        val = AuxFuncs.get_val(instr, secondarg_pos)

        if (type == "b."):
            if (val == "true"):
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.false"
            else:
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.true"
        else:
            STDERR("ERROR[53]: NOT received attribute not of boolean type\n")
            AuxFuncs.error_cleanup(53)
############################################################
    @staticmethod
    def INT2CHAR(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "INT2CHAR", 1, 2)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "INT2CHAR", 2, 2)

        AuxFuncs.check_arg(instr, "INT2CHAR", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "INT2CHAR", secondarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)

        type = AuxFuncs.get_prefix(instr, secondarg_pos)

        if (type == "unset_var"):
            STDERR(f"ERROR[56]: INT2CHAR received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        val = AuxFuncs.get_val(instr, secondarg_pos)

        if (type != "i."):
            STDERR("ERROR[53]: INT2CHAR encountered an attempt at converting a value that isn't an integer or in Unicode format\n")
            AuxFuncs.error_cleanup(53)
        elif int(val) not in range(0, 1114111):
            STDERR("ERROR[58]: INT2CHAR encountered an attempt at converting a value out of possible range\n")
            AuxFuncs.error_cleanup(58)
        else:
            globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "s." + str(chr(int(val)))
############################################################
    @staticmethod
    def STRI2INT(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "STR2INT", 1, 3)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "STR2INT", 2, 3)
        thirdarg_pos = AuxFuncs.arg_enumerate(instr, "STR2INT", 3, 3)

        AuxFuncs.check_arg(instr, "STRI2INT", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "STRI2INT", secondarg_pos, "symb")
        AuxFuncs.check_arg(instr, "STRI2INT", thirdarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)

        type = AuxFuncs.get_prefix(instr, thirdarg_pos)

        if (type == "unset_var"):
            STDERR(f"ERROR[56]: STRI2INT received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        symb1 = AuxFuncs.get_val(instr, secondarg_pos)
        symb2 = AuxFuncs.get_val(instr, thirdarg_pos)

        if (type != "i."):
            STDERR("ERROR[53]: STRI2INT received a value that isn't an integer as its second argument\n")
            AuxFuncs.error_cleanup(53)
        elif int(symb2) not in range(0, len(symb1)):
            STDERR("ERROR[58]: STRI2INT encountered an attempt at accessing a character outside of string range\n")
            AuxFuncs.error_cleanup(58)
        else:
            globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "i." + ord(symb1[symb2])
############################################################
##
# READ:  lightly tested
# WRITE: tested
##
############################################################
    @staticmethod
    def READ(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "READ", 1, 2)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "READ", 2, 2)

        AuxFuncs.check_arg(instr, "READ", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "READ", secondarg_pos, "type")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)

        loaded = input()
        
        if (instr[1].text == "int"):
            if (loaded.lstrip("-").isdigit() == 1):
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "i." + str(loaded)
            else: # wrong input type
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "n.nil"
        elif (instr[1].text == "bool"):
            if (((loaded.lower() == "true")) or (loaded.lower() == "false")):
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b." + str(loaded.lower())
            elif (loaded == "1"):
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.true"
            elif (loaded == "0"):
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "b.false"
            else: # wrong input type
                globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "n.nil"
        elif (instr[1].text == "string"):
            globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "s." + str(loaded)
############################################################
    @staticmethod
    def WRITE(instr):
        AuxFuncs.argcnt_check(instr, "WRITE", 1, 1, True)

        AuxFuncs.check_arg(instr, "WRITE", 1, "symb")

        type = AuxFuncs.get_prefix(instr, 1)

        if (type == "n."):
            STDOUT("")
        if(instr[0].attrib['type'] == "var"):
            frame = AuxFuncs.get_frame(instr, 1)
            if (type == "unset_var"):
                STDERR(f"ERROR[56]: WRITE received an argument which is unset (missing a value)\n")
                AuxFuncs.error_cleanup(56)
            elif (type == "NULL"): # var was somewhat initialized (already handled) but with TYPE where TYPE inserted only NULL
                STDOUT("")
            elif (type == "s."):
                string = str(globals()[frame][instr[0].text[3:len(instr[0].text)]][2:len(globals()[frame][instr[0].text[3:len(instr[0].text)]])])
                string = re.sub("\\\\032", ' ', string)
                string = re.sub("\\\\010", '\n', string)
                string = string.encode('utf-8')
                string = string.decode('unicode_escape').encode('latin1').decode('utf-8')
                STDOUT(string)
            elif (type == "type"):
                STDOUT(str(globals()[frame][instr[0].text[3:len(instr[0].text)]]))
            else:
                STDOUT(str(globals()[frame][instr[0].text[3:len(instr[0].text)]][2:len(globals()[frame][instr[0].text[3:len(instr[0].text)]])]))
        else:
            if (type == "s."):
                string = str(instr[0].text)
                string = re.sub("\\\\032", ' ', string)
                string = re.sub("\\\\010", '\n', string)
                string = string.encode('utf-8')
                string = string.decode('unicode_escape').encode('latin1').decode('utf-8')
                STDOUT(string)
            else:
                STDOUT(str(instr[0].text))
############################################################
##
# CONCAT:  not tested
# STRLEN:  not tested
# GETCHAR: not tested
# SETCHAR: not tested
##
############################################################
    @staticmethod
    def CONCAT(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "CONCAT", 1, 3)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "CONCAT", 2, 3)
        thirdarg_pos = AuxFuncs.arg_enumerate(instr, "CONCAT", 3, 3)

        AuxFuncs.check_arg(instr, "CONCAT", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "CONCAT", secondarg_pos, "symb")
        AuxFuncs.check_arg(instr, "CONCAT", thirdarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)

        type1 = AuxFuncs.get_prefix(instr, secondarg_pos)
        type2 = AuxFuncs.get_prefix(instr, thirdarg_pos)
        
        if ((type1 == "unset_var") or (type2 == "unset_var")):
            STDERR(f"ERROR[56]: CONCAT received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        string1 = AuxFuncs.get_val(instr, secondarg_pos)
        string2 = AuxFuncs.get_val(instr, thirdarg_pos)

        if ((type1 == "s.") and (type2 == "s.")):
            globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "s." + string1 + string2
        else:
            STDERR("ERROR[53]: CONCAT received at least one argument not of type 'string'\n")
            AuxFuncs.error_cleanup(53)
############################################################
    @staticmethod
    def STRLEN(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "STRLEN", 1, 2)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "STRLEN", 2, 2)
        
        AuxFuncs.check_arg(instr, "STRLEN", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "STRLEN", secondarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)

        type = AuxFuncs.get_prefix(instr, secondarg_pos)

        if (type == "unset_var"):
            STDERR(f"ERROR[56]: STRLEN received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        string = AuxFuncs.get_val(instr, secondarg_pos)

        if (type == "s."):
            globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "i." + str(len(string))
        else:
            STDERR("ERROR[53]: STRLEN received an argument not of type 'string'\n")
            AuxFuncs.error_cleanup(53)
############################################################
    @staticmethod
    def GETCHAR(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "GETCHAR", 1, 3)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "GETCHAR", 2, 3)
        thirdarg_pos = AuxFuncs.arg_enumerate(instr, "GETCHAR", 3, 3)

        AuxFuncs.check_arg(instr, "GETCHAR", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "GETCHAR", secondarg_pos, "symb")
        AuxFuncs.check_arg(instr, "GETCHAR", thirdarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)

        type1 = AuxFuncs.get_prefix(instr, secondarg_pos)
        type2 = AuxFuncs.get_prefix(instr, thirdarg_pos)

        if ((type1 == "unset_var") or (type2 == "unset_var")):
            STDERR(f"ERROR[56]: GETCHAR received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        string = AuxFuncs.get_val(instr, secondarg_pos)
        position = AuxFuncs.get_val(instr, thirdarg_pos)

        if ((type1 != "s.") or (type2 != "i.")):
            STDERR("ERROR[53]: GETCHAR received either non-string as second argument or non-integer as third argument\n")
            AuxFuncs.error_cleanup(53)
        elif int(position) not in range(0, len(string)):
            STDERR("ERROR[58]: GETCHAR encountered an attempt at accessing a character outside of string range\n")
            AuxFuncs.error_cleanup(58)
        else:
            globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "s." + str(string[int(position)])
############################################################
    @staticmethod
    def SETCHAR(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "SETCHAR", 1, 3)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "SETCHAR", 2, 3)
        thirdarg_pos = AuxFuncs.arg_enumerate(instr, "SETCHAR", 3, 3)

        AuxFuncs.check_arg(instr, "SETCHAR", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "SETCHAR", secondarg_pos, "symb")
        AuxFuncs.check_arg(instr, "SETCHAR", thirdarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)

        type1 = AuxFuncs.get_prefix(instr, firstarg_pos)
        type2 = AuxFuncs.get_prefix(instr, secondarg_pos)
        type3 = AuxFuncs.get_prefix(instr, thirdarg_pos)

        if ((type1 == "unset_var") or (type2 == "unset_var") or (type3 == "unset_var")):
            STDERR(f"ERROR[56]: SETCHAR received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        position = AuxFuncs.get_val(instr, secondarg_pos)
        newchar = AuxFuncs.get_val(instr, thirdarg_pos)

        if ((type1 != "s.") or (type2 != "i.") or (type3 != "s.")):
            STDERR("ERROR[53]: SETCHAR received either non-string as first or third argument or non-integer as second argument\n")
            AuxFuncs.error_cleanup(53)
        elif int(position) not in range(0, len(globals()[frame][instr[0].text[3:len(instr[0].text)]])):
            STDERR("ERROR[58]: SETCHAR encountered an attempt at accessing a character outside of string range\n")
            AuxFuncs.error_cleanup(58)
        elif (newchar == ""):
            STDERR("ERROR[58]: third argument received by SETCHAR is an empty string (no character to replace with)\n")
            AuxFuncs.error_cleanup(58)
        else:
            globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]][0:position] +   \
            newchar[0] + globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]][position+1:len(globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]])]
############################################################
##
# TYPE: not tested
##
############################################################
    @staticmethod
    def TYPE(instr):
        firstarg_pos = AuxFuncs.arg_enumerate(instr, "TYPE", 1, 2)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "TYPE", 2, 2)

        AuxFuncs.check_arg(instr, "TYPE", firstarg_pos, "var")
        AuxFuncs.check_arg(instr, "TYPE", secondarg_pos, "symb")

        frame = AuxFuncs.get_frame(instr, firstarg_pos)

        type = AuxFuncs.get_prefix(instr, secondarg_pos)

        if (type == "unset_var"):
            globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "NULL"
        elif (type == "i."):
            globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "int"
        elif (type == "n."):
            globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "nil"
        elif (type == "b."):
            globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "bool"
        elif (type == "s."):
            globals()[frame][instr[firstarg_pos-1].text[3:len(instr[firstarg_pos-1].text)]] = "string"
############################################################
##
# LABEL:     lightly tested
# JUMP:      not tested
# JUMPIFEQ:  lightly tested
# JUMPIFNEQ: lightly tested
# EXIT:      
##
############################################################
    @staticmethod
    def LABEL(instr):
        AuxFuncs.argcnt_check(instr, "LABEL", 1, 1, True)

        AuxFuncs.check_arg(instr, "LABEL", 1, "label")

        name = AuxFuncs.get_val(instr, 1)

        if (instr[0].text in Labels_stack):
            STDERR(f"ERROR[52]: duplicate '{instr.text}' label definition found\n")
            AuxFuncs.error_cleanup(52)

        Labels_stack[name] = temp_ordernums_list.copy()
############################################################
    @staticmethod
    def JUMP(instr):
        global ordernums_list
        global instruct_position
        global Labels_stack

        AuxFuncs.argcnt_check(instr, "JUMP", 1, 1, True)

        AuxFuncs.check_arg(instr, "JUMP", 1, "label")

        name = AuxFuncs.get_val(instr, 1)

        instruct_position = instruct_position + (len(ordernums_list) - len(Labels_stack[name]))

        ordernums_list = Labels_stack[name].copy()
############################################################
    @staticmethod
    def JUMPIFEQ(instr):
        global ordernums_list
        global instruct_position
        global Labels_stack

        firstarg_pos = AuxFuncs.arg_enumerate(instr, "JUMPIFEQ", 1, 3)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "JUMPIFEQ", 2, 3)
        thirdarg_pos = AuxFuncs.arg_enumerate(instr, "JUMPIFEQ", 3, 3)

        AuxFuncs.check_arg(instr, "JUMPIFEQ", firstarg_pos, "label")
        AuxFuncs.check_arg(instr, "JUMPIFEQ", secondarg_pos, "symb")
        AuxFuncs.check_arg(instr, "JUMPIFEQ", thirdarg_pos, "symb")

        name = AuxFuncs.get_val(instr, firstarg_pos)

        type1 = AuxFuncs.get_prefix(instr, secondarg_pos)
        type2 = AuxFuncs.get_prefix(instr, thirdarg_pos)

        if ((type1 == "unset_var") or (type2 == "unset_var")):
            STDERR(f"ERROR[56]: JUMPIFEQ received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        val1 = AuxFuncs.get_val(instr, secondarg_pos)
        val2 = AuxFuncs.get_val(instr, thirdarg_pos)

        if ((type1 == "i.") and (type2 == "i.")):
            if (int(val1) == int(val2)):
                instruct_position = instruct_position + (len(ordernums_list) - len(Labels_stack[name]))
                ordernums_list = Labels_stack[name].copy()
        elif ((type1 == "b.") and (type2 == "b.")):
            if (((val1 == "true") and (val2 == "true")) or ((val1 == "false") and (val2 == "false"))):
                instruct_position = instruct_position + (len(ordernums_list) - len(Labels_stack[name]))
                ordernums_list = Labels_stack[name].copy()
        elif ((type1 == "s.") and (type2 == "s.")):
            if (val1 == val2): #python handles string comparisons lexicographically already
                instruct_position = instruct_position + (len(ordernums_list) - len(Labels_stack[name]))
                ordernums_list = Labels_stack[name].copy()
        elif ((type1 == "n.") or (type2 == "n.")):
            if (val1 == val2):
                instruct_position = instruct_position + (len(ordernums_list) - len(Labels_stack[name]))
                ordernums_list = Labels_stack[name].copy()
        else:
            STDERR("ERROR[53]: JUMPIFEQ received an attempt at comparing two attributes of different types\n")
            AuxFuncs.error_cleanup(53)
############################################################
    @staticmethod
    def JUMPIFNEQ(instr):
        global ordernums_list
        global instruct_position
        global Labels_stack

        firstarg_pos = AuxFuncs.arg_enumerate(instr, "JUMPIFNEQ", 1, 3)
        secondarg_pos = AuxFuncs.arg_enumerate(instr, "JUMPIFNEQ", 2, 3)
        thirdarg_pos = AuxFuncs.arg_enumerate(instr, "JUMPIFNEQ", 3, 3)

        AuxFuncs.check_arg(instr, "JUMPIFNEQ", firstarg_pos, "label")
        AuxFuncs.check_arg(instr, "JUMPIFNEQ", secondarg_pos, "symb")
        AuxFuncs.check_arg(instr, "JUMPIFNEQ", thirdarg_pos, "symb")

        name = AuxFuncs.get_val(instr, firstarg_pos)

        type1 = AuxFuncs.get_prefix(instr, secondarg_pos)
        type2 = AuxFuncs.get_prefix(instr, thirdarg_pos)

        if ((type1 == "unset_var") or (type2 == "unset_var")):
            STDERR(f"ERROR[56]: JUMPIFNEQ received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        val1 = AuxFuncs.get_val(instr, secondarg_pos)
        val2 = AuxFuncs.get_val(instr, thirdarg_pos)

        if ((type1 == "i.") and (type2 == "i.")):
            if (int(val1) != int(val2)):
                instruct_position = instruct_position + (len(ordernums_list) - len(Labels_stack[name]))
                ordernums_list = Labels_stack[name].copy()
        elif ((type1 == "b.") and (type2 == "b.")):
            if (((val1 == "true") and (val2 == "false")) or ((val1 == "false") and (val2 == "true"))):
                instruct_position = instruct_position + (len(ordernums_list) - len(Labels_stack[name]))
                ordernums_list = Labels_stack[name].copy()
        elif ((type1 == "s.") and (type2 == "s.")):
            if (val1 != val2): #python handles string comparisons lexicographically already
                instruct_position = instruct_position + (len(ordernums_list) - len(Labels_stack[name]))
                ordernums_list = Labels_stack[name].copy()
        elif ((type1 == "n.") or (type2 == "n.")):
            if (val1 != val2):
                instruct_position = instruct_position + (len(ordernums_list) - len(Labels_stack[name]))
                ordernums_list = Labels_stack[name].copy()
        else:
            STDERR("ERROR[53]: JUMPIFEQ received an attempt at comparing two attributes of different types\n")
            AuxFuncs.error_cleanup(53)
############################################################
    @staticmethod
    def EXIT(instr, instrs_done):
        AuxFuncs.argcnt_check(instr, "EXIT", 1, 1, True)

        AuxFuncs.check_arg(instr, "EXIT", 1, "symb")

        type = AuxFuncs.get_prefix(instr, 1)

        if (type == "unset_var"):
            STDERR(f"ERROR[56]: EXIT received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        val = AuxFuncs.get_val(instr, 1)

        if (type == "i."):
            if (int(val) not in range (0,50)):
                print(f"val is:  {val}\n")
                STDERR(f"ERROR[57]: EXIT given return value which is outside of predetermined range of 0 to 49 (included)\n")
                AuxFuncs.error_cleanup(57)
            else:
                sys.stdout.flush()
                sys.stderr.flush()
                STDERR(f"\n\nEXIT ENCOUNTERED, executing 'BREAK' for statictics output (in stderr) and shutting down\n\n")
                OpcodeFuncs.BREAK(instr, instrs_done)
                AuxFuncs.error_cleanup(int(val))
        else:
            STDERR(f"ERROR[53]: EXIT received an argument not of type int\n")
            AuxFuncs.error_cleanup(53)
############################################################
##
# DPRINT: not tested
# BREAK:  not tested
##
############################################################
    @staticmethod
    def DPRINT(instr, instrs_done):
        AuxFuncs.argcnt_check(instr, "DPRINT", 1, 1, True)

        AuxFuncs.check_arg(instr, "DPRINT", 1, "symb")

        type = AuxFuncs.get_prefix(instr, 1)

        if (type == "unset_var"):
            STDERR(f"ERROR[56]: DPRINT received an argument which is unset (missing a value)\n")
            AuxFuncs.error_cleanup(56)

        if (type == "n."):
            STDERR("")
        if(instr[0].attrib['type'] == "var"):
            frame = AuxFuncs.get_frame(instr, 1)
            STDERR(str(globals()[frame][instr[0].text[3:len(instr[0].text)]][2:len(globals()[frame][instr[0].text[3:len(instr[0].text)]])]))
        else:
            STDERR(str(instr[0].text))
############################################################
    @staticmethod
    def BREAK(instr, instrs_done):
        # count of successfully finished instructions
        STDERR(f"TOTAL INSTRUCTIONS PROCESSED:  {instrs_done}")

        # global frame contents
        STDERR(f"\nGLOBAL FRAME CONTENTS: ")
        for var in global_frame:
            STDERR(f"[{var}: {str(global_frame[var])}]")

        # local frame contents
        STDERR(f"\nLOCAL FRAME CONTENTS: ")
        for var in local_frame:
            STDERR(f"[{var}: {str(local_frame[var])}]")

        # local frame contents
        STDERR(f"\nTEMPORARY FRAME CONTENTS: ")
        for var in temporary_frame:
            STDERR(f"[{var}: {str(temporary_frame[var])}]")

        # frame stack contents
        STDERR(f"\nFRAME STACK CONTENTS: ")
        STDERR(f"{Frames_stack}")

        # data stack contents
        STDERR(f"\nDATA STACK CONTENTS: ")
        STDERR(f"{Data_stack}")

        # labels stack contents
        STDERR(f"\nLABEL STACK CONTENTS: ")
        STDERR(f"{Labels_stack}")

        STDERR(f"\n")


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

#
if ((file_exists(f'{source_file}') == 0) and (source_file != "")):
    STDERR("ERROR[10]: file passed as --source not found\n")
    AuxFuncs.error_cleanup(10)
elif ((file_exists(f'{input_file}') == 0) and (input_file != "")):
    STDERR("ERROR[10]: file passed as --input not found\n")
    AuxFuncs.error_cleanup(10)

#STDERR(f"\n--Source file:  {source_file}\n")
#print(f"--Input file:   {input_file}")
#print(f"\n\n\n")

#try to read input file
if (input_file != ""):
    try:
        sys.stdin = open(f'{input_file}', 'r')
    except OSError:
        STDERR("ERROR[11]: file passed as --input couldn't be read\n")
        AuxFuncs.error_cleanup(11)


# try parsing source file or stdin
if (source_file != ""):
    try:
        mytree = ET.parse(f'{source_file}')
    except ET.ParseError:
        STDERR("ERROR[31]: wrong XML format of source file (file isn't so-called well-formed)\n")
        AuxFuncs.error_cleanup(31)
else:
    try:
        mytree = ET.parse(f'{sys.stdin}')
    except ET.ParseError:
        STDERR("ERROR[31]: wrong XML format of source file (file isn't so-called well-formed)\n")
        AuxFuncs.error_cleanup(31)

myroot = mytree.getroot()

AuxFuncs.program_start_handle()

Frames_stack.append(local_frame)

class_opfuncs = OpcodeFuncs

#####
## FIRST LOOP TO CREATE LABELS
temp_ordernums_list = ordernums_list.copy()
i = 0
while i < instruct_cnt:
    temp_order_num = min(temp_ordernums_list) # get smallest order number from list
    temp_ordernums_list.remove(temp_order_num) # remove smallest order number from list
    for instr in myroot: # search through all instructions and
        if ((int(instr.attrib['order']) == temp_order_num) and (instr.attrib['opcode'] == "LABEL")): # find the one whose order number corresponds to searched one
            OpcodeFuncs.LABEL(instr)
    i += 1

#####
## MAIN LOOP
#instr.attrib, instr.tag, instr.text, instr[0].text, instr.tail, instr.find("name")
instruct_position = 0
while instruct_position < instruct_cnt:
    order_num = min(ordernums_list) # get smallest order number from list
    ordernums_list.remove(order_num) # remove smallest order number from list
    for instr in myroot: # search through all instructions and
        if (int(instr.attrib['order']) == order_num): # find the one whose order number corresponds to searched one
            try:
                opcode = getattr(class_opfuncs, instr.attrib['opcode'].upper())
            except:
                # shouldn't happen, as parser has already checked that all received OPCODEs are known
                STDERR("ERROR[32]: Method %s not implemented\n" % instr.attrib['opcode'])
                AuxFuncs.error_cleanup(32) #!!!

            if ((instr.attrib['opcode'] != "BREAK") and (instr.attrib['opcode'] != "DPRINT") and (instr.attrib['opcode'] != "EXIT") and (instr.attrib['opcode'] != "LABEL")):
                opcode(instr)
                instrs_done = instrs_done + 1
            elif (instr.attrib['opcode'] != "LABEL"):
                opcode(instr, instrs_done)

            #print('--' + instr.attrib['opcode'])
    instruct_position += 1


############################################################
#                     AUXILIARY PRINTS                     #
############################################################
#print(f"\n\n\n")

#OpcodeFuncs.BREAK("NULL", instrs_done)

#print(f"") #newline