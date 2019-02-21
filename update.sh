#!/bin/bash -x
DIR=$(cd $(dirname $0); pwd)
cd $DIR

php scripts/p2cmd.php update || exit 1


