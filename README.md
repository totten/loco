# loco: Local-Compose Process Manager

`loco` is a development-oriented process-manager.  It's like `docker-compose` minus `docker`. One creates a `loco.yml` file with a list of services to start, as in:

```yaml
format: 'loco-0.1'
environment:
 - PHPFPM_PORT=9009
 - REDIS_PORT=6379
services:
  redis:
    run: 'redis-server --port "$REDIS_PORT" --dir "$LOCO_SVC_VAR"'
  php-fpm:
    run: 'php-fpm -y "$LOCO_SVC_VAR/php-fpm.conf" --nodaemonize'
    pid_file: '$LOCO_SVC_VAR/php-fpm.pid'
```

In this example, the `redis` service is easy to define because it accepts most configuration via command-line arguments. Note that any persistent data is directed to a managed data folder (`$LOCO_SVC_VAR` aka `.loco/var/redis`).

For `php-fpm`, we need a config file to set some options.  One creates a template (e.g. `.loco/config/php-fpm/php-fpm.conf.loco.tpl` per convention) which incorporates the environment variables, as with:

```
[global]
pid = {{LOCO_SVC_VAR}}/php-fpm.pid
...
[www]
listen = 127.0.0.1:{{PHPFPM_PORT}}
...
```

Finally, start the services with:

```
$ loco run
```

To stop, press Ctrl-C.

`loco` is a functional proof-of-concept. For more details, see the [working example (*loco*lamp)](https://github.com/totten/locolamp) and [draft specification/todos](doc/specs.md).

## More information

* [__*loco*lamp__: Example project using nix-shell+loco to setup Apache+MySQL+PHP+NodeJS+Redis+Mailcatcher](https://github.com/totten/locolamp)
* [__About__: Motivation and critical comparison](doc/about.md)
* [__Download__](doc/download.md)
* [__Specifications__: CLI, File Formats, Environment Variables, Roadmap/TODOs, etc](doc/specs.md)
