{pkgs ? import <nixpkgs> {
    inherit system;
  },
  system ? builtins.currentSystem,
  noDev ? false,
  php ? pkgs.php72,
  phpPackages ? pkgs.php72Packages
  }:

let
  stdenv = pkgs.stdenv;

  ## Make a single "src" with copies of all our files.
  makeLatestSrc = stdenv.mkDerivation rec {
    ## There must be a better way...
    name = "loco-src";

    src = ./src;
    bin = ./bin;
    scripts = ./scripts;
    composerJson = ./composer.json;
    composerLock = ./composer.lock;

    buildCommand = ''
      mkdir $out
      cp -r $src $out/src
      cp -r $bin $out/bin
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

    buildInputs = [ php phpPackages.composer ];
    builder = "${src}/scripts/nix-builder.sh";
}