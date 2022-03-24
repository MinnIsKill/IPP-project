#!/usr/bin/env bash

# DISCLAIMER: THESE TESTS WERE DONE WITH PARSER LINES HAVING WAS LESS SPACES (1 instead of 4, 2 instead of 8...)
#             BECAUSE I WAS TOO LAZY TO FIGURE OUT HOW TO COMFORTABLY MODIFY OUT FILES OF ALL THE TESTS

declare -i total=0;
declare -i okay=0;
declare -i errors=0;

declare -i comp_value=0;

#for i in *.txt; do echo "hello $i"; done

path="tests/parse-only";

for file in $(find $path -name *.src);
do
    #echo "hello $file";
    total=$(( total + 1 )); #add file to total file cnt
    name=${file::-4}; #cut away the last four characters (".src")

    php8.1 parse.php < $name.src > garbage_output.out;

    diff --brief <(sort $name.out) <(sort garbage_output.out) >/dev/null;
    comp_value=$?;

    if [ $comp_value -eq 1 ]
    then
        echo "file #$total NOT OK:  $file";
        errors=$(( errors + 1));
    else
        #echo "file OK:  $file";
        okay=$(( okay + 1 ));
    fi
done

echo "successful tests:  [$okay/$total]";
echo "total number of errors: $errors";
echo "16 files are known to give errors even if they're okay XML-wise, due to these tests not being exacty XML-supportive."