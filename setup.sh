#!/bin/bash -x
DIR=$(cd $(dirname $0); pwd)
cd $DIR

curl -O http://getcomposer.org/composer.phar || exit 1
chmod +x composer.phar || exit 1
mv composer.phar /usr/local/bin/composer || exit 1

/usr/local/bin/composer install || exit 1

chmod 0777 data/* rep2/ic || exit 1

php scripts/p2cmd.php check


