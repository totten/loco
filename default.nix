## Prefer nixpkgs 22.05 (same as buildkit)
{pkgs ? import (fetchTarball { url = "https://github.com/nixos/nixpkgs/archive/ce6aa13369b667ac2542593170993504932eb836.tar.gz"; sha256 = "0d643wp3l77hv2pmg2fi7vyxn4rwy0iyr8djcw1h5x72315ck9ik"; }) {
    inherit system;
  },
  system ? builtins.currentSystem,
  noDev ? false,
  php ? pkgs.php74,
  phpPackages ? pkgs.php74Packages
  }:

let
  stdenv = pkgs.stdenv;

  ## Make a single "src" with copies of all our files.
  makeLatestSrc = stdenv.mkDerivation rec {
    ## There must be a better way...
    name = "loco-src";

    src = ./src;
    bin = ./bin;
    patches = ./patches;
    scripts = ./scripts;
    composerJson = ./composer.json;
    composerLock = ./composer.lock;

    buildCommand = ''
      mkdir $out
      cp -r $src $out/src
      cp -r $bin $out/bin
      cp -r $patches $out/patches
      cp -r $scripts $out/scripts
      cp $composerJson $out/composer.json
      cp $composerLock $out/composer.lock
    '';
  };

in stdenv.mkDerivation rec {
    name = "loco";

    src = makeLatestSrc;

    #src = pkgs.fetchFromGitHub {
    #  owner = "totten";
    #  repo = "loco";
    #  rev = "FIXME";
    #  sha256 = "FIXME";
    #};

    buildInputs = [ php phpPackages.composer phpPackages.box pkgs.git pkgs.cacert ];
    builder = "${src}/scripts/nix-builder.sh";
    shellHook = ''
      PATH="$PWD/bin:$PWD/extern:$PATH"
      export PATH
    '';
}