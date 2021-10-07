#!/bin/bash

function makeConfig() {
source config.sh
touch "logfile.log"
cat <<EOM > "../src/config/ConfigOverride.php"
<?php

return [
    'db' => [
        'host' => '$DBHOST',
        'user' => '$DBUSER',
        'password' => '$DBPASSWORD',
        'dbname' => '$DBNAME'
    ],
    'logging' => [
        'level' => 'info',
        'path' => ''
    ],
    'authentication' => [
        'native' => [
            'plugin' => 'native',
            'prefix' => 'default',
            'config' => null
        ],
        'config' => [
            'plugin' => 'config',
            'prefix' => 'config',
            'config' => [
                'configuser' => '\$2y\$10\$twlIJ0hYeaHqMsiM7OdLr.4HkV6/EEQneDg9uZiU.l7yn1bpxSD1.',
                'notindb' => '\$2y\$10\$z1dD1Q5u68l5iqEmqnOAVuoR5VWR77HUfxMUycJ9TdDG3H5dLZGVW'
            ]
        ]
    ],
    'proxys' => ['127.0.0.1']
];
EOM
}

function clearConfig() {
    rm "../src/config/ConfigOverride.php"
    rm "logfile.log"
}

SCRIPT=$(readlink -f "$0")
SCRIPTPATH=$(dirname "$SCRIPT")

source config.sh

cd "$SCRIPTPATH"

if [ $# -lt 1 ]
then
    echo "The script needs either run or all as parameter."
    exit 1
fi

if [ $1 == "run" ]
then
    if [ $# -lt 2 ]
    then
        echo "run needs an argument."
        exit 1
    fi

    makeConfig

    echo "Preparing Database"
    if [ -z "$DBPASSWORD" ]
    then
        mysql "-h$DBHOST" "-u$DBUSER" "$DBNAME" < db.sql
    else
        mysql "-h$DBHOST" "-u$DBUSER" "-p$DBPASSWORD" "$DBNAME" < db.sql
    fi

    echo "Executing test"
    if ! node "tests/$2.js" "$TESTURL"
    then
        echo "Test failed"
        clearConfig
        exit 1
    else
        if [ $(cat logfile.log | wc -l) -gt 0 ]
        then
            echo "Errors in logfile:"
            cat "logfile.log"
            clearConfig
            exit 2
        else
            echo "Test successfull"
            clearConfig
            exit 0
        fi
    fi
elif [ $1 == "all" ]
then
    for test in tests/*
    do
        makeConfig

        echo -n $(basename $test .js) "..."

        if [ -z "$DBPASSWORD" ]
        then
            mysql "-h$DBHOST" "-u$DBUSER" "$DBNAME" < db.sql
        else
            mysql "-h$DBHOST" "-u$DBUSER" "-p$DBPASSWORD" "$DBNAME" < db.sql
        fi

        echo -n "..."

        if ! node "$test" "$TESTURL"
        then
            clearConfig
            exit 1
        else
            if [ $(cat logfile.log | wc -l) -gt 0 ]
            then
                cat "logfile.log"
                clearConfig
                exit 2
            else
                echo " OK"
            fi
        fi

        clearConfig
    done
else
    echo "$1 is not a valid command."
    exit 3
fi


