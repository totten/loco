#!/bin/bash

## About: Execute all the test suites... with different versions of PHP.

## Example usage: ./tests.sh
## Example usage: env DEBUG=2 PHPUNIT=phpunit5 ./tests.sh

## TODO: Maybe some JUnit output?

###############################################################################
## Determine the absolute path of the directory with the file
## usage: absdirname <file-path>
function absdirname() {
  pushd $(dirname $0) >> /dev/null
    pwd
  popd >> /dev/null
}

function nixrun() {
  # nix run "$@"
  ## ^^^ Nix 2.3? 2.4? Something like that. No longer works in 2.7.
  # https://github.com/NixOS/nix/issues/5081 + https://discourse.nixos.org/t/error-experimental-nix-feature-nix-command-is-disabled/18089/2
  nix --extra-experimental-features nix-command shell "$@"
  return $?
}

###############################################################################
## Execute the PHP, unit-level tests
## usage: test_phpunit <php-pkg-name> <nix-repo-url> [phpunit_bin] [phpunit-options]
## example: test_phpunit php72 https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz --group foobar
function test_phpunit() {
  local pkg="$1"
  local url="$2"
  local phpunit="extern/$3"
  shift 3
  local name="Unit tests ($pkg from $url; $@)"
  echo "[$name] Start"
  echo nixrun -f "$url" "$pkg" -c php "$phpunit" "$@"
  if nixrun -f "$url" "$pkg" -c php "$phpunit" "$@" ; then
    echo "[$name] OK"
  else
    echo "[$name] Fail"
    EXIT_CODE=1
  fi
}

###############################################################################
## Main

SCRIPTDIR=$(absdirname "$0")
PRJDIR=$(dirname "$SCRIPTDIR")
COMPOSER=${COMPOSER:-composer}
PATH="$PRJDIR/bin:$PATH"
export PATH

if [ ! -f "$PRJDIR/bin/loco" ]; then
  echo "Failed to determine loco source dir" 1>&2
  exit 1
fi

EXIT_CODE=0
pushd "$PRJDIR"
  "$COMPOSER" install

  NIX_2205="https://github.com/nixos/nixpkgs/archive/ce6aa13369b667ac2542593170993504932eb836.tar.gz"

  #test_phpunit     php56   https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.03.tar.gz phpunit5
  test_phpunit     php72   https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz phpunit5
  test_phpunit     php74   "$NIX_2205" phpunit5
  test_phpunit     php80   "$NIX_2205" phpunit8

popd
exit $EXIT_CODE
