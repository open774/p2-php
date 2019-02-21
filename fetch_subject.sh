#!/bin/bash -x
DIR=$(cd $(dirname $0); pwd)
cd $DIR

php scripts/fetch-subject-txt.php --mode fav
php scripts/fetch-subject-txt.php --mode recent
php scripts/fetch-subject-txt.php --mode res_hist



