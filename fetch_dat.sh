#!/bin/bash -x
DIR=$(cd $(dirname $0); pwd)
cd $DIR

php scripts/fetch-dat.php --mode fav
php scripts/fetch-dat.php --mode recent
php scripts/fetch-dat.php --mode res_hist



