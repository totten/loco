# Plugins

Loco plugins are PHP files which register event listeners.

## Example: Set environment programmatically

```php
// FILE: /etc/loco/plugin/set-locale-archive.php
use Loco\Loco;

Loco::dispatcher()->addListener('loco.system.create', function($e) {
  $e['system']->environment->set('LOCALE_ARCHIVE', '/usr/lib/locale/locale-archive');
});
```

## Example: Add command

```php
// FILE: /etc/loco/plugin/hello-command.php
use Loco\Loco;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

Loco::dispatcher()->addListener('loco.app.commands', function($e) {
  $e['commands'][] = new class extends \Symfony\Component\Console\Command\Command {
    protected function configure() {
      $this->setName('hello');
    }
    protected function execute(InputInterface $input, OutputInterface $output) {
      $output->writeln('Hello there!');
    }
  };
});
```

## Plugin loading

*Global plugins* are loaded from the `LOCO_PLUGIN_PATH`. All `*.php` files
in `LOCO_PLUGIN_PATH` will be loaded automatically during startup.

If otherwise unspecified, the default value of `LOCO_PLUGIN_PATH` is:

```bash
LOCO_PLUGIN_PATH=/etc/loco/plugin:/usr/share/loco/plugin:/usr/local/share/loco/plugin:$HOME/.config/loco/plugin
```

After loading the global plugins, `loco` reads the the `loco.yml` and then loads any *local plugins* (i.e. *project-specific* plugins).

This sequencing meaning that some early events (e.g.  `loco.app.boot` or
`loco.config.find`) are only available to *global plugins*.

## Events

Events are objects based on the `LocoEvent` class.  This class provides an
array-like interface (`ArrayAccess`) to reading and writing arguments.

The following events are defined:

* `loco.app.boot` (*global-only*): Fires immediately when the application starst
* `loco.app.run` (*global-only*): Fires when the application begins executing a command
* `loco.app.commands` (*global-only*): Fires when the application builds a list of available commands
   * __Argument__: `$e['commands`]`: alterable list of commands
* `loco.config.find` (*global-only*): Fires when the application loads a default `.loco.yml`
   * __Argument__: `$e['file']`: the file to load
* `loco.config.plugins` (*global-only*): Fires when the application loads supplemental plugins (per `.loco.yml`)
   * __Argument__: `$e['file']`: the file to load
   * __Argument__: `$e['config']`: the unprocessed configuration data
* `loco.config.filter`: Fires after reading the `.loco.yml` but before parsing
   * __Argument__: `$e['file']`: the configuration file
   * __Argument__: `$e['config']`: the unprocessed configuration data
* `loco.env.create`: Fires when a new environment is instantiated
   * __Argument__: `$e['env']`: the new `LocoEnv`
   * __Argument__: `$e['assignments']`: the list of assignment expressions used to fill the env
* `loco.env.merge`: Fires whenever a series of environments are merged to create a new environment.
   * __Argument__: `$e['srcs']`: an array of `LocoEnv`, ordered by priority
   * __Argument__: `$e['env']`: the new `LocoEnv` built by combining the various sources
* `loco.service.create`: Fires after a `LocoService` is instantiated
   * __Argument__: `$e['service']`: the `LocoService` which needs an environment
* `loco.service.mergeEnv`: Fires whenever a set of environments are merged to build the effective service-environment.
  (This is a specialized form of `loco.env.merge`.)
   * __Argument__: `$e['service']`: the `LocoService` which needs an environment
   * __Argument__: `$e['srcs']`: an array of `LocoEnv`, ordered by priority
   * __Argument__: `$e['env']`: the new `LocoEnv` built by combining the various sources
* `loco.system.create`: Fires after the `LocoSystem` is instantiated (but before services are added)
   * __Argument__: `$e['system']`: the instance of LocoSystem
* `loco.system.mergeEnv`: Fires whenever a set of environments are merged to build the effective system-environment
  (This is a specialized form of `loco.env.merge`.)
   * __Argument__: `$e['system']`: the instance of LocoSystem which needs an environment
   * __Argument__: `$e['srcs']`: an array of `LocoEnv`, ordered by priority
   * __Argument__: `$e['env']`: the new `LocoEnv` built by combining the various sources
