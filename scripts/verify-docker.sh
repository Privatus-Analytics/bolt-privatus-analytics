#!/bin/sh
# Creates a real Bolt CMS project in Docker, installs this extension from the
# working tree with Composer, sets a site ID and checks the tag on front-end
# pages, the back end and for a logged-in user.
# Usage: ./scripts/verify-docker.sh [bolt/project constraint]   (default ^6.0)
set -e
CONSTRAINT="${1:-^6.0}"
cd "$(dirname "$0")/.."
docker run --rm -e CONSTRAINT="$CONSTRAINT" -e COMPOSER_ALLOW_SUPERUSER=1 -v "$PWD":/ext:ro php:8.3-cli sh -ec '
  apt-get update -qq >/dev/null
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq git unzip libicu-dev libpng-dev libzip-dev libjpeg-dev libfreetype6-dev >/dev/null 2>&1
  docker-php-ext-configure gd --with-jpeg --with-freetype >/dev/null
  docker-php-ext-install -j6 intl gd zip exif >/dev/null 2>&1
  curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer >/dev/null

  cd /tmp
  composer create-project "bolt/project:$CONSTRAINT" site --no-interaction --no-progress -q >/dev/null 2>&1
  cd site
  echo "Bolt core $(composer show bolt/core 2>/dev/null | grep "^versions" | sed "s/.*: \* //")"
  php bin/console doctrine:schema:create -q >/dev/null 2>&1 || true
  php bin/console bolt:add-user tester tester12345 tester@example.com Tester --admin -q

  composer config repositories.privatus path /ext
  composer require "privatus/bolt-privatus-analytics:@dev" --no-interaction --no-progress -q
  php bin/console extensions:list | grep -q "Privatus\\\\BoltAnalytics\\\\Extension" || { echo "FAIL: extension not listed"; exit 1; }
  echo "ok   extension is listed"
  php bin/console extensions:configure -q
  CFG=config/extensions/privatus-boltanalytics.yaml
  test -f $CFG || { echo "FAIL: default config not copied"; exit 1; }
  echo "ok   default config copied to $CFG"

  (php -S 127.0.0.1:8000 -t public public/index.php >/tmp/server.log 2>&1 &)
  sleep 2
  B=http://127.0.0.1:8000
  count() { curl -s -b /tmp/jar -c /tmp/jar "$1" | grep -o "privatusstats.com/js/pa.js" | wc -l | tr -d " "; }

  test "$(count $B/search)" = 0 || { echo "FAIL: tag printed with no site id"; exit 1; }
  rm -f /tmp/jar
  echo "ok   no tag before a site id is set"

  sed -i "s/^site_id: .*/site_id: pa_Docker123/; s/^modules: .*/modules: [engage, vitals]/" $CFG
  php bin/console cache:clear -q
  curl -s -o /tmp/page.html "$B/search"
  EXPECTED="<script defer src=\"https://privatusstats.com/js/pa.js\" data-site=\"pa_Docker123\" data-modules=\"engage,vitals\"></script>"
  grep -qF "$EXPECTED" /tmp/page.html || { echo "FAIL: tag not on page"; exit 1; }
  sed -n "/<head>/,/<\/head>/p" /tmp/page.html | grep -qF "privatusstats.com" || { echo "FAIL: tag is not inside head"; exit 1; }
  test "$(grep -o "privatusstats.com/js/pa.js" /tmp/page.html | wc -l | tr -d " ")" = 1 || { echo "FAIL: tag printed more than once"; exit 1; }
  echo "ok   tag is in the head of a front-end page, once"
  # With no content the home page is a Bolt "not found" page. Bolt then runs a
  # second controller in the same request, which used to print the tag twice.
  test "$(count $B/)" = 1 || { echo "FAIL: home page should have the tag once"; exit 1; }
  rm -f /tmp/jar
  echo "ok   home page (not found) has the tag once"
  test "$(count $B/bolt/login)" = 0 || { echo "FAIL: back end got the tag"; exit 1; }
  rm -f /tmp/jar
  echo "ok   back end gets no tag"

  J=/tmp/jar
  TOKEN=$(curl -s -c $J -b $J "$B/bolt/login" | grep -o "name=\"login\[_token\]\" value=\"[^\"]*\"" | sed "s/.*value=\"\(.*\)\"/\1/")
  curl -s -o /dev/null -c $J -b $J --data-urlencode "login[username]=tester" --data-urlencode "login[password]=tester12345" --data-urlencode "login[_token]=$TOKEN" "$B/bolt/login"
  curl -s -c $J -b $J "$B/bolt/" | grep -qi "logout" || { echo "FAIL: could not log in"; exit 1; }
  test "$(curl -s -c $J -b $J $B/search | grep -o "privatusstats.com/js/pa.js" | wc -l | tr -d " ")" = 0 || { echo "FAIL: logged-in user was tracked"; exit 1; }
  echo "ok   logged-in user gets no tag"

  sed -i "s/^site_id: .*/site_id: \"pa_x\\\\\" onload=alert(1)\"/" $CFG
  php bin/console cache:clear -q
  test "$(count $B/search)" = 0 || { echo "FAIL: bad site id printed a tag"; exit 1; }
  echo "ok   a bad site id prints no tag"
  if grep -i "PHP Fatal\|PHP Warning" /tmp/server.log; then echo "FAIL: PHP errors"; exit 1; fi
  echo "ok   no PHP errors"
'
