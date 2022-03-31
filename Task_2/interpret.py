# XML interpreter
# Task #2 for IPP Project VUTBR FIT
# Author: xkalis03 (xkalis03@stud.fit.vutbr.cz)

import xml.etree.ElementTree as ET # XML parsing module
import sys # for command line arguments reading

############################################################
#                         GLOBALS                          #
############################################################
stdin_file = ""
input_file = ""
source_file = ""

instruct_cnt = 0
ordernums_list = []

#return_int = 0

help_msg = "Script of type interpreter (interpreter.py in Python 3.8) loads an XML representation of program and, using\n\
input from parameters entered from the command line, interprets this program and generates its output.\n\
\n\
  Script parameters:\n\
      --help         prints a help message to stdout (doesn't load any input). Returns 0.\n\
      --source=file  file containing the XML representation of input source code.\n\
      --input=file   file containing inputs needed for the interpretation of interpreted source code.\n\
                     Can be empty.\n\
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
                or negative instuction number, etc.)\n"

############################################################
#                        FUNCTIONS                         #
############################################################

#####
## checks input arguments for --help argument
def check_helparg():
    global return_int
    for i, arg in enumerate(sys.argv):
        if ((i == 1) and (arg == "--help")): # first argument is --help
            print(f"{help_msg}")
            exit(0)
        elif ((i >= 2) and (arg == "--help")): # if --help found in rest of arguments
            print(f"ERROR[10]: --help parameter cannot be combined with any other parameters")
            exit(10)
        # else no --help found

#####
## handles everything needed to check and prepare to be able to continue to the program's main function
def program_start_handle():
    #count the number of instructions in XML
    global instruct_cnt
    children = list(myroot)
    for child in children:
        instruct_cnt += 1

    #put all found order numbers in a list
    for node in mytree.findall('.//instruction'):
        ordernums_list.append(node.attrib['order'])

    #check if duplicate order numbers received (error)
    list_dupl_check(ordernums_list)

#####
## finds out if there are duplicate values in a list
def list_dupl_check(ordernums_list):
    l1 = []
    for i in ordernums_list:
        if i not in l1:
            l1.append(i)
        else:
            print(f"ERROR[32]: duplicate order numbers found")
            l1.clear()
            exit(32)
    l1.clear()


############################################################
#                           MAIN                           #
############################################################
# command line arguments reading
if __name__ == "__main__":
    if (len(sys.argv) >= 2): # at least 1 parameter received (other than file call)
        check_helparg() # checks whether program received --help argument, error handling included
        for i, arg in enumerate(sys.argv): # iterate through all arguments
            if (arg[0:9] == "--source="):
                if (source_file == ""):
                    source_file = arg[9:len(arg)]
                else:
                    print(f"ERROR[10]: --source received more than once")
                    exit(10)
            elif (arg[0:8] == "--input="):
                if (input_file == ""):
                    input_file = arg[8:len(arg)]
                else:
                    print(f"ERROR[10]: --input received more than once")
                    exit(10)
    else:
        print(f"ERROR[10]: insufficient number of input parameters")
        exit(10)
            
print(f"Source file:  {source_file}")
print(f"Input file:   {input_file}")

mytree = ET.parse(f'{source_file}')
myroot = mytree.getroot()

program_start_handle()

#####
## MAIN LOOP
#x.attrib, x.tag, x.text, x[0].text, x.tail, x.find("name")
i = 0
while i < instruct_cnt:
    order_num = min(ordernums_list) # get smallest order number from list
    ordernums_list.remove(order_num) # remove smallest order number from list
    for x in myroot: # search through all instructions,
        if (x.attrib['order'] == order_num): # find the one whose order number corresponds to searched one
            print(x.attrib['opcode'])
    i += 1
