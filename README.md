# loco: Local-Compose Process Manager

`loco` is a process-manager for the Git+Yaml crowd.  One creates a `loco.yaml` file with a list of services to start, as in:

```yaml
format: 'loco-0.1'
default_environment:
 - PHPFPM_PORT=9009
 - REDIS_PORT=6379
services:
  redis:
    run: 'redis-server --port $REDIS_PORT'
  phpfpm:
    run: 'php-fpm -y "$LOCO_SVC_VAR/php-fpm.conf" --nodaemonize'
    pid_file: '$LOCO_SVC_VAR/php-fpm.pid'
```

The `redis` service is easy to define because it accepts most configuration on CLI.

For `php-fpm`, it needs a little extra work because some options have to be set in a config file.  Create a template in
`.loco/config/phpfpm` named `php-fpm.conf.loco.tpl`; use statements like these (*partial excerpt*):

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
  category as sysvinit, runit, or systemd -- although it's not as battle-tested and it's not intended for managing
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

    * It should work on any POSIX-style OS (Linux/OS X/BSD) with PHP+pcntl, and you can easily mix packages from
      different providers (nix, docker, homebrew, apt, etc).

    * All platforms (including OSX) get native performance for IO/filesystem operations.

    * There's no requirement for port-mapping or volume-mapping, so it's easier to inspect with off-the-shelf
      text-editors/IDEs/tools.

    * Of course, you *can* have services which build on `docker run`, `nix run`, etc.  It's just not *required*.

    * The configuration options for each service are presented in their canonical forms -- the CLI commands and file-formats
      match the official upstream docs.

* `loco` is more "dev" than "ops".  If you imagine dev-ops as a spectrum, tools like `make` and `grunt` live far left on the
  local "development" side; Ansible and `ssh` live far right on the network "operations" side; `loco` lives about 1/3 from the
  "dev" side; docker-compose lives 1/3 from the "ops" side.  I like using `loco` during development; it's easy to edit
  configuration files, track the changes, and reset to clean/baseline data.  But it really doesn't care about protecting data,
  maintaining long-term state, defense-in-depth, etc.  If I needed to manage a cluster of production servers, I'd pick something
  else.

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

  ## The SERVICE_NAME will be used to identify data-files, log-files, etc.
  SERVICE_NAME:

    ## The 'depends' lists any other services should be started beforehand.
    depends:
      - SERVICE_NAME

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

There are several built-in environment variables:

* `LOCO_PRJ` (*scope: top-level*): The absolute path of your project. (Ex:
  `/home/me/src/my-web-app`)
* `LOCO_VAR` (*scope: top-level*): The absolute path of the dynamic data-folder. Very
  loosely similar to FHS `/var`. (Ex: `/home/me/src/my-web-app/.loco/var`)
* `LOCO_SVC` (*scope: per-service*): The name of the service being launched.
  (Ex: `phpfpm`)
* `LOCO_SVC_CFG` (*scope: per-service*): The absolute path of a folder
  containing configuration-templates (Ex:  `/home/me/src/my-web-app/.loco/config/phpfpm`)
* `LOCO_SVC_VAR` (*scope: per-service*): The absolute path of a dynamic data-folder
  for this service. (Ex:  `/home/me/src/my-web-app/.loco/var/phpfpm`)

When defining environment variables in YAML, you may reference other variables, e.g.

```yaml
environment:
  - FOO_BASE=$LOCO_VAR/foo
  - FOO_DATA=$FOO_BASE/data
  - FOO_LOGS=$FOO_BASE/log
  - FOO_SOCKET=$FOO_BASE/foo.socket
```

## Specification: Initializing config files

Many services require a configuration file with deployment-specific details.

You may initialize config files using bash commands, e.g.

```yaml
services:
  phpfpm:
    init:
      - 'cat $LOCO_PRJ/php-fpm.conf.ex | sed "s/FOO/bar/" > $LOCO_SVC_VAR/php-fpm.conf'
```

Because this is very common, there is a convention for automatic file mappings:

* Scan the folder `.loco/config/SERVICE_NAME` for files named `*.loco.tpl`.
* Interpolate any environment variables using the notation `{{ENV_VAR}}`.
* Write new files to folder `.loco/var/SERVICE_NAME` (excluding the `*.loco.tpl` suffix).

> WIP: Pick another templating language to embed.

# Specificiation: CLI (WIP/Draft)

```
### Common options
loco ... [--cwd <dir>]                                Change working directory
loco ... [-c <configFile>]                            Load alternative config file
loco ... [-s <svc>]                                   Focus on a specific service. For some commands, this may be used multiple times.

## Multi service commands. (By default, execute on all services.)
loco run [-f] [--ram-disk=<size>]                     Start service(s) in foreground
loco start [-f] [--ram-disk=<size>]                    Start service service(s) in background
loco stop                                             Stop service service(s) in background
loco init [-f]                                        Execute initialization
loco clean                                            Destroy any generated data files
loco export-systemd -o <dir> [--ram-disk=<size>]      Export all service definitions in systemd format

## Single service commands. (No services picked by default.)
loco env                                              Display environment variables
loco shell -- <cmd>                                   Execute a shell command within the service's environment
loco sh -- <cmd>                                      Alias for "shell"
```

# Download (Rough WIP)

This is a basic composer-style project, which means one could say:

```
git clone https://github.com/totten/loco
cd loco
composer install
export PATH=$PWD/bin:$PATH
```

But I should figure out how to package it in other media; e.g. nix or composer...

```
rm -f composer.lock && composer2nix --executable && nix-build  && ./result/bin/loco
```
