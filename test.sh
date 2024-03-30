DIR=$(cd $(dirname $0) && pwd)

XDEBUG_MODE=coverage "$DIR"/vendor/bin/phpunit --coverage-html _coverage
