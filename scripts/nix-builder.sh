source $stdenv/setup

dumpEnv() {
  echo "---"; echo "---"; env | sort ; echo "---"; echo "---"
}

buildPhase() {
  package_version=$(cat scripts/git-export-info.txt)
  sed -i "s;@package_version@;$package_version;" src/*.php
  env COMPOSER_HOME=$TEMPDIR/composer_home COMPOSER_CACHE_DIR=$TEMPDIR/composer_cache \
    composer install --optimize-autoloader
}

installPhase() {
  mkdir $out
  cp -r bin $out/bin
  cp -r src $out/src
  cp -r vendor $out/vendor
}

genericBuild
