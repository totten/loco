# loco: Local-Compose Process Manager

`loco` is a process-manager for the Git+Yaml crowd.  One creates a `loco.yml` file with a list of services to start, as in:

```yaml
format: 'loco-0.1'
default_environment:
 - PHPFPM_PORT=9009
 - REDIS_PORT=6379
services:
  redis:
    run: 'redis-server --port $REDIS_PORT'
  php-fpm:
    run: 'php-fpm -y "$LOCO_SVC_VAR/php-fpm.conf" --nodaemonize'
    pid_file: '$LOCO_SVC_VAR/php-fpm.pid'
```

The `redis` service is easy to define because it accepts most configuration on CLI.

For `php-fpm`, it needs a little extra work because some options have to be set in a config file.  Create a template in
`.loco/config/php-fpm` named `php-fpm.conf.loco.tpl`; use statements like these (*partial excerpt*):

```
listen = 127.0.0.1:{{PHPFPM_PORT}}
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
```

Finally, call `loco run` to start and monitor all the services.  Press `Ctrl-C` to stop.

## Critical Comparison

* Strictly speaking, `loco` a process-manager.  It starts, stops, and restarts processes.  It's technically in the same
  category as sysvinit, runit, or systemd -- although it's not as battle-tested and it wasn't conceived for managing
  a full OS.

* Stylistically, it's more like docker-compose -- one creates a per-project YAML dot-file.  The file lists all the services you
  want, and these are glued together with a few environment variables.  Your work is scoped to a specific project/folder -- and
  not the host OS.

* Specifically, I tend to use it in combination with the cross-platform `nix-shell` and `nix run` for package
  management.  `nix-shell` and `nix run` excel at providing binaries but leave you to roll-your-on for process+data
  management.  In this context, `loco` is a replacement for (a) the manual habit of launching multiple daemons or (b)
  the semi-automated approach of writing per-project bash scripts with bunch of boilerplate.

* Architecturally, it is thinner, less opinionated, and more-limited in value/scope than docker-compose or k8s; which means:

    * It makes no pretense of providing binary-distribution, network/machine management, or long-term state-management.

    * It makes no pretense of enhanced process-isolation (like hypervisors or Linux cgroups/namespaces). It just uses
      POSIX APIs.

    * It should work on any POSIX-style OS (Linux/OS X/BSD) with PHP+pcntl, and you can mix packages from
      different providers (nix, docker, homebrew, apt, etc).

    * All host platforms (including OSX) can achieve native performance for IO/filesystem operations.

    * There's no requirement for port-mapping or volume-mapping, so it's easier to inspect with off-the-shelf
      text-editors/IDEs/tools.

    * Of course, you *can* have services which build on `docker run`, `nix run`, etc.  It's just not *required*.

    * The configuration options for each service are presented in their canonical forms -- the CLI commands and file-formats
      match the official upstream docs.

* `loco` is more "dev" than "ops".  If you imagine dev-ops as a spectrum, tools like `make` and `grunt` live far left on the
  local "development" side; Ansible and `ssh` live far right on the network "operations" side; `loco` lives about 1/3 from the
  "dev" side; `docker-compose` lives 1/3 from the "ops" side.  I like using `loco` during development; it's easy to edit
  configuration files, track the changes, and reset to clean/baseline data.  But it really doesn't care about protecting data,
  maintaining long-term state, defense-in-depth/process-isolation, etc.  If you ask a sysadmin to run production services on it, they
  might call you... loco.

## Specification: YAML Format

```yaml
## The 'format' declaration allows future change in the file-format.
format: 'VERSION-CODE'

## The 'environment' and 'default_environment' list project-wide variables.
## These are visible to all services. The `default_environment` is
## advisory and will not override values that have been inherited
## from the parent CLI.
environment:
  - KEY=VALUE
default_environment:
  - KEY=VALUE

## The 'services' defines each of the processes we will run.
services:

  ## The SERVICE_NAMEs will be used to in CLI commands and in naming data-files, log-files, etc.
  SERVICE_NAME:

    ## The 'depends' clause lists any other services should be started beforehand.
    depends:
      - SERVICE_NAME

    ## The 'enabled' property determines if the service should autostart.
    ## Disabled services can still be enabled by (a) explicitly running
    ## them or (b) using 'depends'. (Default: true)
    enabled: BOOL

    ## The 'environment' and 'default_environment' work the same as above,
    ## but these are only visible within the service.
    environment:
      - KEY=VALUE
    default_environment:
      - KEY=VALUE

    ## The 'init' lists bash commands to run when first initializing the
    ## service.
    init:
      - BASH_COMMAND

    ## The 'run' is the main bash command to execute and monitor.
    run: BASH_COMMAND

    ## By default, 'run' works with non-forking/foreground daemons.
    ## If the daemon forks
    pid_file: FILE_PATH
```

## Specification: Environment variables

Environment variables are stored in multiple scopes, which are (in order of increasing priority):

* __Global Scope__: Environment variables inherited from the user's shell.
* __Loco System Scope__: Environment variables defined at the top of the YAML file.
* __Loco Service Scope__: Environment variables defined in the YAML file for a specific service.

There are a few built-in environment variables for each scope:

* Loco System Scope
    * `LOCO_PRJ`: The absolute path of your project. (Ex: `/home/me/src/my-web-app`; usually the `PWD`.)
    * `LOCO_VAR`: The absolute path of the project's dynamic data-folder.
      Very loosely similar to FHS `/var`. (Ex: `/home/me/src/my-web-app/.loco/var`)
* Loco Service Scope
    * `LOCO_SVC`: The name of the service being launched. (Ex: `php-fpm`)
    * `LOCO_SVC_CFG`: The absolute path of a folder  containing configuration-templates (Ex: `/home/me/src/my-web-app/.loco/config/php-fpm`)
    * `LOCO_SVC_VAR`: The absolute path of a dynamic data-folder for this service. (Ex:  `/home/me/src/my-web-app/.loco/var/php-fpm`)

When defining variables in YAML, one may reference other variables, e.g.

```yaml
environment:
  - FOO_BASE=$LOCO_VAR/foo
  - FOO_DATA=$FOO_BASE/data
  - FOO_LOGS=$FOO_BASE/log
  - FOO_SOCKET=$FOO_BASE/foo.socket
```

References are evaluated on-demand: specifically, when launching a subcommand (e.g.  `run:`) or evaluating a
user-string/template (e.g.  `pid_file:`), `loco` merges the active scopes (per precedence) and recursively evaluates
any nested references.  `loco` *only* evaluates a nested reference if it's declared in the YAML file.

## Specification: Initializing config files

Many services require a configuration file with deployment-specific details.

You may initialize config files using bash commands, e.g.

```yaml
services:
  php-fpm:
    init:
      - 'cat $LOCO_PRJ/php-fpm.conf.ex | sed "s/PHPFPM_PORT/$PHPFPM_PORT/" > $LOCO_SVC_VAR/php-fpm.conf'
```

This is very common, so there is also a *convention* for automatic file mappings:

* Scan the `LOCO_SVC_CFG` folder (e.g. `.loco/config/php-fpm`) for files named `*.loco.tpl`.
* Interpolate any environment variables using `{{...}}` notation (e.g. `{{PHPFPM_PORT}}`).
* Write new files to the `LOCO_SVC_VAR` folder (e.g. `.loco/var/php-fpm`) (excluding the `*.loco.tpl` suffix).

> WIP: Pick another templating language to embed.

# Specificiation: CLI (WIP/Draft)

```
### Common options
loco ... [--cwd <dir>]                                Change working directory
loco ... [-c <configFile>]                            Load alternative config file

## Multi service commands. (By default, execute on all services.)
loco run [-f] [--ram-disk=<size>] [<svc>...]          Start service(s) in foreground
loco start [-f] [--ram-disk=<size>] [<svc>...]        Start service(s) in background
loco stop [<svc>...]                                  Stop service(s) in background
loco status [<svc>...]                                List background service(s) and their status(es)
loco init [-f] [<svc>...]                             Execute initialization
loco clean [<svc>...]                                 Destroy any generated data files

## Single service commands. (No services picked by default.)
loco env [<svc>|.]                                    Display environment variables
loco shell [<svc>|.] -- <cmd>                         Execute a shell command within the service's environment
loco sh [<svc>|.] -- <cmd>                            Alias for "shell"

## Manipulating YAML content
loco generate [-o <dir>] [[svc@]<file> | [svc@]<url> | <svc>@ | -A]   Generate a new project and import services
loco import [[svc@]<file> | [svc@]<url> | <svc>@ | -A]    Copy svcs (YAML+tpls) from an external location.
            [--detect|-D]                                 Auto-detect which services may be applicable
            [--create|-C]                                 Auto-create new project
loco copy <from-svc> <to-svc>                             Copy svc (YAML+tpls) within a project
loco export [--systemd] [-o <dir>] [--ram-disk=<size>] [<svc>...]    Export service definitions (in systemd format)
```

# Download

* Option 1: Clone the repo; run composer; update the PATH.
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

# Status/TODO

This is a working proof-of-concept. Some TODOs:

* Port to Go or Rust s.t. binary doesn't depend on `php`+`pcntl`. Learn enough of Go or Rust to write a port.
* Implement support for background launching
* For BG processes, route console output to log files
* If a variable definition references itself, then check parent scope(s) (Ex: `PATH=$LOCO_PRJ/bin:$PATH`)
* Add test coverage for variable evaluation
* Add test coverage for CLI options
* Add test coverage for start/stop/restart
* Export to systemd unit
* Implement support for mapping LOCO_VAR to a ram disk. (Debate: Better to take that from CLI or YAML? YAML might be more stable.)
* Add options for updating YAML - import/copy
* Add options for including YAML
* Add options for exporting to systemd
* Bug: (Observed OSX+nix-shell php72) When ShellCommand launches bash, bash doesn't recognize arrow-keys. But other programs (mysql, nano, vi, joe) do.

Sketching how it might work with imports and project initialization:

```
## Make a project folder
me@localhost:~$ mkdir project
me@localhost:~$ cd project
me@localhost:~$ git init

## Get some binaries
me@localhost:~$ nix-shell -p mariadb -p redis -p apacheHttpd -p loco

## Create a new loco.yaml by scanning local system for usable service definitions
[nix-shell:~/project]$ loco import -CDLi
Import service "redis" (/nix/store/foobar-loco-lib/redis/loco.yaml) [Y/n]? Y
Import service "mysql" (/nix/store/foobar-loco-lib/mysql/loco.yaml) [Y/n]? Y
Import service "apache" (/nix/store/foobar-loco-lib/apacheHttpd/loco.yaml) [Y/n]? Y

## Edit the configuration. Save it for later.
[nix-shell:~/project]$ vi .loco/loco.yaml
[nix-shell:~/project]$ git add .loco

## Run
[nix-shell:~/project]$ loco run
```

Instead of autodetection, import specific definitions:

```
## Make a project folder
me@localhost:~$ mkdir project
me@localhost:~$ cd project
## Get some binaries
me@localhost:~$ nix-shell -p mariadb -p redis -p apacheHttpd -p loco
## Create a new loco.yaml with a mix of local+published templates
[nix-shell:~/project]$ loco import -C redis@ apacheHttpd@ github://myuser/mariadb-master-slave
Import service "redis" (/nix/foobar-redis/loco.yaml) [Y/n]? Y
Import service "apache" (/nix/foobar-apacheHttpd/loco.yaml) [Y/n]? Y
Import service "mysql_master" (github://myuser/mariadb-master-slave) [Y/n]? Y
Import service "mysql_slave" (github://myuser/mariadb-master-slave) [Y/n]? Y
## Run
[nix-shell:~/project]$ loco run
```

Maybe "loco generate" is sugar for "mkdir + loco import -CDi"

```
## Get some binaries
me@localhost:~$ nix-shell -p mariadb -p redis -p apacheHttpd -p loco

## Create a new project folder and loco.yaml by scanning local system for usable service definitions
[nix-shell:~]$ loco generate -o myproject -L
Import service "redis" (/nix/store/foobar-loco-lib/redis/loco.yaml) [Y/n]? Y
Import service "mysql" (/nix/store/foobar-loco-lib/mysql/loco.yaml) [Y/n]? Y
Import service "apache" (/nix/store/foobar-loco-lib/apacheHttpd/loco.yaml) [Y/n]? Y
[nix-shell:~]$ cd myroject

## Edit the configuration. Save it for later.
[nix-shell:~/project]$ vi .loco/loco.yaml
[nix-shell:~/project]$ git add .loco

## Run
[nix-shell:~/project]$ loco run
```

And again with specific imports

```
## Get some binaries
me@localhost:~$ nix-shell -p mariadb -p redis -p apacheHttpd -p loco

## Create a new project folder and loco.yaml by scanning local system for usable service definitions
[nix-shell:~]$ loco generate -o myproject redis@ apacheHttpd@ github://myuser/mariadb-master-slave
Import service "redis" (/nix/store/foobar-loco-lib/redis/loco.yaml) [Y/n]? Y
Import service "mysql" (/nix/store/foobar-loco-lib/mysql/loco.yaml) [Y/n]? Y
Import service "apache" (/nix/store/foobar-loco-lib/apacheHttpd/loco.yaml) [Y/n]? Y
[nix-shell:~]$ cd myroject

## Edit the configuration. Save it for later.
[nix-shell:~/project]$ vi .loco/loco.yaml
[nix-shell:~/project]$ git add .loco

## Run
[nix-shell:~/project]$ loco run
```

Can we reconcile the notations for `<svc>` and `[<svc>@]<file-or-url>`?

For nix distribution, perhaps distinguish `loco` (the command) and `locolib` or `loconix` (library of templates for
common packages which don't provide their own). So typical usage would be `nix-shell -p locolib`, and I guess
`locolib` does `makeWrapper` to set a search-path
