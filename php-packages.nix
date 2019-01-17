{composerEnv, fetchurl, fetchgit ? null, fetchhg ? null, fetchsvn ? null, noDev ? false}:

let
  packages = {};
  devPackages = {};
in
composerEnv.buildPackage {
  inherit packages devPackages noDev;
  name = "totten-loco";
  src = ./.;
  executable = true;
  symlinkDependencies = false;
  meta = {
    license = "BSD";
  };
}