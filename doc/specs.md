# loco: Specifications

## Specification: YAML Format

```yaml
## The 'format' declaration allows future change in the file-format.
format: 'VERSION-CODE'

## List of plugin paths to search. If omitted, then default is '.loco/plugin/*.php'
plugins:
  - FILE_GLOB

## The 'environment' and 'default_environment' list project-wide variables.
## These are visible to all services. The `default_environment` is
## advisory and will not override values that have been inherited
## from the parent CLI.
environment:
  - KEY=VALUE
default_environment:
  - KEY=VALUE

## (Experimental, Optional) When daemonizing ("loco start"), specify
## how to handle STDIN/STDOUT/STDERR on the new child process.
## One of: 'open', 'close-all', 'close-output'
## Default: 'close-all'
##
## Ideally, we would have no option here -- and a strong guarantee of directing STDOUT/STDERR
## to a file. However, it needs work and is subject to subtle runtime-variations. So for
## now, we expose an option - so we can experiment without needing to rebuild/republish constantly.
default_io_mode: STRING

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

    ## Daemons should write their PID to a control file.
    ## This is required for 'start'/'stop' commands. (It is suggested for 'run'.)
    pid_file: FILE_PATH

    ## Optionally, redirect the STDOUT/STDERR for the process to file.
    ## If omitted, then output is either written to console ('loco run') or a general log file ('loco start').
    log_file: FILE_PATH

    ## (Experimental, Optional) See top-level "default_io_mode".
    io_mode: STRING

    ## A message to display after the services have started.
    message: STRING
    
    ## TODO: Support for automatically restart whenever a template or config file changes
    # watch:
    # - FILE_GLOB

    ## The 'cleanup' lists extra bash commands to run when destroying
    ## the service.
    cleanup:
      - BASH_COMMAND

    ## The 'export' lists extra options that would be used for exporting to
    ## another process-manager (eg systemd).
    export:
      ## For list of keys, see documentation of the system-level "export" clause


## The "volume" is a special service. It automatically appears as a
## dependency before any other services.
volume:
  # Setup the data-volume with a ramdisk service
  # The special value "off" disables ramdisk service
  # Depends: https://github.com/totten/ramdisk
  ramdisk: SIZE_IN_MB

  # Or with custom commands...
  ...SERVICE_SPEC...

## The 'export' lists extra options that would be used for exporting to
## another process-manager (eg systemd).
export:
  ## When exporting, you may pass-through environment variables from the
  ## runtime context to the systemd unit.
  ## Default: '/^(PATH|NIX_SSL_.*)$/'
  include_env: 'REGEX'

  ## The preferred systemd service "Type", e.g. "simple", "exec", "forking".
  ## Default: 'exec'
  type: SYSTEMD_TYPE

## TODO: Support for mixing configurations from a third-party library.
# include:
# - URL_OF_TARBALL
```

## Specification: Environment variables

Environment variables are stored in multiple scopes. These include (in order of decreasing priority):

1. __Mandatory Loco Service Environment (`services: *: environment`)__: Mandatory variables defined in the YAML file for a specific service.
2. __Mandatory Loco System Environment (`environment`)__: Mandatory variables defined at the top of the YAML file.
3. __Global Environment__: Environment variables inherited from the user's shell.
4. __Default Loco Service Environment (`services: *: default_environment`)__: Default variables defined in the YAML file for a specific service.
5. __Default Loco System Environment (`default_environment`)__: Default variables defined at the top of the YAML file.

If the same variable is defined in two scopes, then the narrower scope overrides the broader scope.

```yaml
## From the perspective of the `cookiemonster` service, the `SNACK` variable is overriden with value `chocolate_chip_cookie`.
environment:
  - SNACK=apple

services:
  cookiemonster:
    environment:
      - SNACK=chocolate_chip_cookie
```

There are a few built-in environment variables for each scope:

* Loco System Scope
    * `LOCO_PRJ`: The absolute path of your project. (Ex: `/home/me/src/my-web-app`; usually the `PWD`.)
    * `LOCO_VAR`: The absolute path of the project's dynamic data-folder.
      Very loosely similar to FHS `/var`. (Ex: `/home/me/src/my-web-app/.loco/var`)
    * `LOCO_CFG_YML`: The path of the `loco.yml` configuration file
* Loco Service Scope
    * `LOCO_SVC`: The name of the service being launched. (Ex: `php-fpm`)
    * `LOCO_SVC_CFG`: The absolute path of a folder  containing configuration-templates (Ex: `/home/me/src/my-web-app/.loco/config/php-fpm`)
    * `LOCO_SVC_VAR`: The absolute path of a dynamic data-folder for this service. (Ex:  `/home/me/src/my-web-app/.loco/var/php-fpm`)

When defining variables in YAML, one may reference other variables, e.g.

```yaml
environment:
  - PATH=/opt/foo/bin:$PATH
  - FOO_BASE=$LOCO_VAR/foo
  - FOO_DATA=$FOO_BASE/data
  - FOO_LOGS=$FOO_BASE/log
  - FOO_SOCKET=$FOO_BASE/foo.socket
```

References are evaluated on-demand: specifically, when launching a subcommand (e.g.  `run:`) or evaluating a
user-string/template (e.g.  `pid_file:`), `loco` merges the active scopes (per precedence) and recursively evaluates
any nested references.  `loco` *only* evaluates a nested reference if it's declared in the YAML file.

If a variable is defined recursively (e.g. `PATH=/opt/foo/bin:$PATH`), then it incorporates the value from the parent scope.

## Specification: Inline function calls

There is experimental support for assigning variables with inline function calls (following a subset of shell-style syntax).

```yaml
environment:
  - FOO_NAME=$(basename "$FILE")
  - FOO_PATH=$(dirname "$FILE")
  - FOO_SIBLING=$(dirname "$FILE")/sibling
  - GREETING=$(echo "Hello $NAME")!
```

Important details:

- These are not literally `bash` expressions.
- The syntax and semantics may still change in subtle ways.
- These are internal functions -- not external programs.
- Subexpressions are prohibited from having `(` or `)` characters. `$(echo "foo()")` will not work.
- `$(basename "$FILE")` and `$(basename $FILE)` are equivalent -- whitespace in variable content does not currently expand to multiple parameters. Never-the-less, you should use the quotes for consistency/readability/portability.

<!-- Why not just call out to bash for evaluation? You'd have to materialize the env-vars first. I don't quite have my finger on why, but this feels tricky. -->

If further computation is required, then use a [plugin](plugins.md) to define custom variables or custom functions.

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

# Specificiation: CLI (WIP/Draft/Wishlist)

```
### Common options
loco ... [--cwd <dir>]                                Change working directory
loco ... [-c <configFile>]                            Load alternative config file

## Multi service commands. (By default, execute on all services.)
loco init [-f] [<svc>...]                             Execute initialization
loco run [-f] [-X] [<svc>...]                         Start service(s) in foreground
loco start [-f] [<svc>...]                            Start service(s) in background
loco stop [<svc>...]                                  Stop service(s) in background
loco status [<svc>...]                                List background service(s) and their status(es) (Wishlist)
loco clean [<svc>...]                                 Destroy any generated data files

## Single service commands. (No services picked by default.)
loco env [<svc>|.]                                    Display environment variables
loco shell [<svc>|.] [-- <cmd>]                       Open a subshell or run a commad in a subshell, using the service's environment
loco sh [<svc>|.] -- <cmd>                            Alias for "shell"

## Manipulating YAML content
loco generate [-o <dir>] [[svc@]<file> | [svc@]<url> | <svc>@ | -A]   Generate a new project and import services
loco import [[svc@]<file> | [svc@]<url> | <svc>@ | -A]    Copy svcs (YAML+tpls) from an external location.
            [--detect|-D]                                 Auto-detect which services may be applicable
            [--create|-C]                                 Auto-create new project
loco copy <from-svc> <to-svc>                             Copy svc (YAML+tpls) within a project
loco export [-o <dir>] [--ram-disk=<size>] [<svc>...]    Export service definitions (in systemd format)
```

# Status/TODOs

This is a working proof-of-concept. Some TODOs (no particular oder):

* Sharing / maintaining / mixing / reusing configurations
    * Add options for importing YAML statically (i.e. copying from a URL/Github project and putting the content into `.loco`) -- e.g. bash command:
      ```bash
      loco import 'https://github.com/someone/mysql-template'
      ```
    * Add options for including YAML dynamically (i.e. loading from a URL/Github project by referencing it in `.loco/loco.yml`) -- e.g. yaml attribute:
      ```yaml
      include: 'https://github.com/someone/mysql-template'
      ```
    * Add options for scanning environment and comparing against a library of service templates -- e.g. bash command:
      ```bash
      loco import --detect
      ```
* Process management
    * Find a more meaningful protocol to detect when a service has really come online, then:
        * Use this to improve/fix launching of dependencies (so that we don't need 'sleep' hacks in any of the config files)
        * Use this to improve/fix timing of the startup messages.
* Quality assurance
    * Add test coverage for CLI options
    * Add test coverage for start/stop/restart
* Other usability
    * When initializing services, create a checksum of the configuration. When starting a service, compare the checksum and warn if it's changed.
    * Allow flagging some services to *always* init on startup.
    * Allow flagging some services to *autorestart* when certain files are changed.
    * In `loco sh` and `loco env`, allow sourcing shell startup scripts like `git-completion.bash`
* Implement support for mapping LOCO_VAR to a ram disk. (Debate: Better to take that from CLI or YAML? YAML might be more stable.)
    * Bug: (Observed OSX+nix-shell php72) When ShellCommand launches bash, bash doesn't recognize arrow-keys. But other programs (mysql, nano, vi, joe) do.
* Distributability
    * Submit to nixpkgs
    * Port to Go or Rust to allow more flexible distribution. (PHP+pcntl is not common.) Learn enough of Go or Rust to write a port.

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
