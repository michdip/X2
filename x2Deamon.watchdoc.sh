#!/bin/bash

if [ -e "x2Deamon.pid" ]
then
    PID=`cat x2Deamon.pid`
    STAT=`ps -f --pid ${PID} | wc -l`

    if [ ${STAT} == 2 ]
    then
        exit 0;
    fi
fi

./x2Deamon
