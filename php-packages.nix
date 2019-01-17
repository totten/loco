{composerEnv, fetchurl, fetchgit ? null, fetchhg ? null, fetchsvn ? null, noDev ? false}:

let
  packages = {
    "psr/log" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "psr-log-6c001f1daafa3a3ac1d8ff69ee4db8e799a654dd";
        src = fetchurl {
          url = https://api.github.com/repos/php-fig/log/zipball/6c001f1daafa3a3ac1d8ff69ee4db8e799a654dd;
          sha256 = "1i351p3gd1pgjcjxv7mwwkiw79f1xiqr38irq22156h05zlcx80d";
        };
      };
    };
    "symfony/console" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-console-a700b874d3692bc8342199adfb6d3b99f62cc61a";
        src = fetchurl {
          url = https://api.github.com/repos/symfony/console/zipball/a700b874d3692bc8342199adfb6d3b99f62cc61a;
          sha256 = "15rgzzdjbf91ak1rkvw222mglz7drjfh72njqgy9jwcfaakflc90";
        };
      };
    };
    "symfony/debug" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-debug-64cb33c81e37d19b7715d4a6a4d49c1c382066dd";
        src = fetchurl {
          url = https://api.github.com/repos/symfony/debug/zipball/64cb33c81e37d19b7715d4a6a4d49c1c382066dd;
          sha256 = "0qg9hwqrsx120v0vl8cmc2hgp56v4h5mjg0yw9fabhry10njn5kp";
        };
      };
    };
    "symfony/finder" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-finder-3f2a2ab6315dd7682d4c16dcae1e7b95c8b8555e";
        src = fetchurl {
          url = https://api.github.com/repos/symfony/finder/zipball/3f2a2ab6315dd7682d4c16dcae1e7b95c8b8555e;
          sha256 = "1bhf3ymd22k47rzpna69hqpk77iy67rqnvh850bl4rglscyzl10a";
        };
      };
    };
    "symfony/polyfill-ctype" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-polyfill-ctype-e3d826245268269cd66f8326bd8bc066687b4a19";
        src = fetchurl {
          url = https://api.github.com/repos/symfony/polyfill-ctype/zipball/e3d826245268269cd66f8326bd8bc066687b4a19;
          sha256 = "16md0qmy5jvvl7lc6n6r5hxjdr5i30vl6n9rpkm4b11rh2nqh7mh";
        };
      };
    };
    "symfony/polyfill-mbstring" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-polyfill-mbstring-c79c051f5b3a46be09205c73b80b346e4153e494";
        src = fetchurl {
          url = https://api.github.com/repos/symfony/polyfill-mbstring/zipball/c79c051f5b3a46be09205c73b80b346e4153e494;
          sha256 = "18v2777cky55ah6xi4dh383mp4iddwzmnvx81qd86y1kgfykwhpi";
        };
      };
    };
    "symfony/yaml" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-yaml-554a59a1ccbaac238a89b19c8e551a556fd0e2ea";
        src = fetchurl {
          url = https://api.github.com/repos/symfony/yaml/zipball/554a59a1ccbaac238a89b19c8e551a556fd0e2ea;
          sha256 = "15is11996s4zppgp0mbiq0d9rpb3qgdrq962qkyk0jkia679llwi";
        };
      };
    };
  };
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