# loco: Download

* Option 1: Install PHP 7.x with the `pcntl` extension. Download a PHAR.

    Browse to https://github.com/totten/loco/releases and identify the latest PHAR. Download it to `/usr/local/bin`.

    ```
    sudo wget https://github.com/totten/loco/releases/download/v0.6.2/loco-0.6.2.phar -O /usr/local/bin/loco
    chmod +x /usr/local/bin/loco
    ```

* Option 2: Install PHP 7.x with the `pcntl` extension. Clone the repo; run composer; update the `PATH`.
    ```
    git clone https://github.com/totten/loco
    cd loco
    composer install
    export PATH=$PWD/bin:$PATH
    ```

* Option 3: Use `nix run` (without installing)
    ```
    nix run -f 'https://github.com/totten/loco/archive/master.tar.gz' -c loco <...options...>
    ```

* Option 4: Install via `nix-env`
    ```
    nix-env -if 'https://github.com/totten/loco/archive/master.tar.gz'
    ```

* Option 5: In a `nix` manifest, use the [callPackage pattern](https://nixos.org/nixos/nix-pills/callpackage-design-pattern.html#idm140737315777312)
    ```
    loco = callPackage (fetchTarball https://github.com/totten/loco/archive/master.tar.gz) {};
    ```
