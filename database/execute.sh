#!/bin/bash

cat $1 | mysql -u x2 -h localhost -D x2 --password=x2
