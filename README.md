# loco: Local-Compose Process Manager

`loco` is a process-manager for the Git+Yaml crowd.  It's like `docker-compose` minus `docker`. One creates a `loco.yml` file with a list of services to start, as in:

```yaml
format: 'loco-0.1'
environment:
 - PHPFPM_PORT=9009
 - REDIS_PORT=6379
services:
  redis:
    run: 'redis-server --port $REDIS_PORT'
  php-fpm:
    run: 'php-fpm -y "$LOCO_SVC_VAR/php-fpm.conf" --nodaemonize'
    pid_file: '$LOCO_SVC_VAR/php-fpm.pid'
```

The `redis` service is easy to define because it accepts most configuration via CLI.

For `php-fpm`, it needs a little extra work because some options have to be set in a config file.  One creates a template
(e.g. `.loco/config/php-fpm/php-fpm.conf.loco.tpl`) with content like this (*partial excerpt*):

```
listen = 127.0.0.1:{{PHPFPM_PORT}}
```

Finally, call `loco run` to start and monitor all the services.  Press `Ctrl-C` to stop.

`loco` is a functional proof-of-concept. The example works for me, but there are several items listed under "Specifications/TODO" (below).

## More information

* [Example: locolamp: Using nix-shell+loco to setup Apache+MySQL+PHP+NodeJS+Redis+Mailcatcher](https://github.com/totten/locolamp)
* [About: Motivation and critical comparison](doc/about.md)
* [Download](doc/download.md)
* [Specifications: CLI, File Formats, TODOs](doc/specs.md)
