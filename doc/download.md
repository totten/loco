# loco: Download

* Option 1: Install PHP 5.6/7.x with the `pcntl` extension. Clone the repo; run composer; update the PATH.
    ```
    git clone https://github.com/totten/loco
    cd loco
    composer install
    export PATH=$PWD/bin:$PATH
    loco <...options...>
    ```

* Option 2: Use `nix run` (without installing)
    ```
    nix run -f 'https://github.com/totten/loco/archive/master.tar.gz' -c loco <...options...>
    ```

* Option 3: Install via `nix-env`
    ```
    nix-env -if 'https://github.com/totten/loco/archive/master.tar.gz'
    loco  <...options...>
    ```

* Option 4: In a `nix` manifest, use the [callPackage pattern](https://nixos.org/nixos/nix-pills/callpackage-design-pattern.html#idm140737315777312)
    ```
    loco = callPackage (fetchTarball https://github.com/totten/loco/archive/master.tar.gz) {};
    ```
