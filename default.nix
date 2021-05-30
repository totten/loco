## Pin to variant of 20.03. Same pin as buildkit/nix.
{pkgs ? import (fetchTarball https://github.com/NixOS/nixpkgs-channels/archive/70717a337f7ae4e486ba71a500367cad697e5f09.tar.gz) {
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

    buildInputs = [ php phpPackages.composer phpPackages.box ];
    builder = "${src}/scripts/nix-builder.sh";
    shellHook = ''
      PATH="$PWD/bin:$PWD/extern:$PATH"
      export PATH
    '';
}