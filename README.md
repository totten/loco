# loco: Local-Compose Process Manager

`loco` is a process-manager for the Git+Yaml crowd.  It's like `docker-compose` minus `docker`. One creates a `loco.yml` file with a list of services to start, as in:

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

The `redis` service is easy to define because it accepts most configuration via command-line arguments. Note the use of `$LOCO_SVC_VAR` to put any runtime data in a managed data folder.

For `php-fpm`, it needs a little extra work because some options require a config file.  One creates a template
(e.g. `.loco/config/php-fpm/php-fpm.conf.loco.tpl`) with content like this (*partial excerpt*):

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

`loco` is a functional proof-of-concept. For more details, see the [working example](https://github.com/totten/locolamp) and [draft specification/todos](doc/specs.md).

## More information

* [Example: *loco*lamp: Using nix-shell+loco to setup Apache+MySQL+PHP+NodeJS+Redis+Mailcatcher](https://github.com/totten/locolamp)
* [About: Motivation and critical comparison](doc/about.md)
* [Download](doc/download.md)
* [Specifications: CLI, File Formats, Environment Variables, TODOs, etc](doc/specs.md)
